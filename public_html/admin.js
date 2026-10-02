/* DARIKO admin panel — vanilla JS. Qoidalar:
   - Serverdan kelgan HAR QANDAY matn (ayniqsa User-Agent/referrer/audit) faqat esc() orqali HTML'ga qo'yiladi.
   - Inline style/script yo'q (CSP: script-src 'self'; style-src 'self'). */
(() => {
  'use strict';
  let csrf = '', data = null, period = '7d', liveTimer = null, sumTimer = null, textLimit = 40, transTimer = null;
  const $ = s => document.querySelector(s);
  const esc = x => String(x ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const nf = new Intl.NumberFormat('uz-UZ');
  const fmtNum = n => nf.format(Number(n) || 0);

  function toast(msg, isErr = false) {
    const t = $('#toast'); t.textContent = msg; t.classList.toggle('err', isErr); t.hidden = false;
    clearTimeout(toast.h); toast.h = setTimeout(() => { t.hidden = true; }, 3500);
  }

  async function api(route, opts = {}) {
    const headers = { ...(opts.body && typeof opts.body === 'string' ? { 'Content-Type': 'application/json' } : {}), ...(csrf ? { 'X-CSRF-Token': csrf } : {}), ...(opts.headers || {}) };
    const [path, qs] = route.split('?');
    const r = await fetch('api.php?route=' + encodeURIComponent(path) + (qs ? '&' + qs : ''), { credentials: 'same-origin', ...opts, headers });
    let d = {}; try { d = await r.json(); } catch (e) { d = { error: 'Server javobi noto‘g‘ri' }; }
    if (r.status === 401) { showLogin(); }
    if (!r.ok) { const e = new Error(d.error || 'Xatolik'); e.status = r.status; throw e; }
    return d;
  }

  /* ---------------- auth ---------------- */
  // v13: Telegram /unlock havolasi (#unlock=<32 hex>) — kod faqat xotirada saqlanadi, URL'dan darhol olib tashlanadi.
  let unlockCode = '';
  { const um = location.hash.match(/^#unlock=([a-f0-9]{32})$/); if (um) { unlockCode = um[1]; history.replaceState(null, '', location.pathname + location.search); } }
  function showLogin() {
    stopTimers(); $('#app').hidden = true; $('#login').hidden = false;
    if (unlockCode) $('#loginError').textContent = 'Telegram bir martalik kodi qabul qilindi — parolni kiriting (global blok chetlab o‘tiladi).';
  }
  // v13 (audit 8-masala): barcha `defer` modul skriptlari (admin-*.js) DARIKO_ADMIN_TABS ga o'zini qo'shib bo'lgach
  // DOMContentLoaded chiqadi. Boshlang'ich (#hash) bo'limi shundan keyin ochiladi — sekin tarmoqda bo'sh bo'lim poygasi yo'q.
  // So'rovlar (me, admin/content) kutilmaydi — parallel ketadi, shuning uchun sezilarli kechikish qo'shilmaydi.
  // Eslatma: defer skript bajarilayotganda readyState allaqachon 'interactive', lekin DOMContentLoaded hali chiqmagan —
  // shuning uchun readyState ga qaramay DCL kutiladi; zaxira sifatida 'load' (DCL dan keyin har doim chiqadi) ham.
  const domReady = document.readyState === 'complete' ? Promise.resolve() : new Promise(r => {
    document.addEventListener('DOMContentLoaded', r, { once: true }); window.addEventListener('load', r, { once: true });
  });
  async function boot() {
    const me = await api('me');
    csrf = me.csrf || '';
    if (!me.authenticated) { showLogin(); return; }
    $('#login').hidden = true; $('#app').hidden = false;
    data = await api('admin/content');
    await domReady;
    renderTexts(); renderAssets(); renderSettings();
    openTab(location.hash.replace('#', '') || 'dashboard');
  }
  $('#loginForm').addEventListener('submit', async e => {
    e.preventDefault(); const btn = e.target.querySelector('button'); btn.disabled = true; $('#loginError').textContent = '';
    try { const r = await api('login', { method: 'POST', body: JSON.stringify(unlockCode ? { password: e.target.password.value, unlock: unlockCode } : { password: e.target.password.value }) }); csrf = r.csrf; unlockCode = ''; e.target.reset(); await boot(); }
    catch (ex) { $('#loginError').textContent = ex.status === 429 ? 'Juda ko‘p urinish. Keyinroq qayta urinib ko‘ring. (Kirish global bloklangan bo‘lsa: ulangan admin Telegram chatidan botga /unlock yuboring.)' : ex.status === 401 ? 'Parol noto‘g‘ri.' : ex.message; }
    finally { btn.disabled = false; }
  });
  $('#logout').addEventListener('click', async () => { try { await api('logout', { method: 'POST', body: '{}' }); } catch (e) {} csrf = ''; showLogin(); });

  /* ---------------- tabs ---------------- */
  function openTab(name) {
    if (!document.getElementById('tab-' + name)) name = 'dashboard';
    document.querySelectorAll('.nav button').forEach(b => b.classList.toggle('active', b.dataset.tab === name));
    document.querySelectorAll('.tab').forEach(s => { s.hidden = s.id !== 'tab-' + name; });
    history.replaceState(null, '', '#' + name);
    stopTimers();
    if (name === 'dashboard') startDashboard();
    if (name === 'security') loadSecurity();
    if (window.DARIKO_ADMIN_TABS && window.DARIKO_ADMIN_TABS[name]) window.DARIKO_ADMIN_TABS[name]();
  }
  document.querySelectorAll('.nav button').forEach(b => b.addEventListener('click', () => openTab(b.dataset.tab)));
  function stopTimers() { clearInterval(liveTimer); clearInterval(sumTimer); liveTimer = sumTimer = null; if (window.DARIKO_ADMIN_STOP) window.DARIKO_ADMIN_STOP(); }

  /* ---------------- dashboard ---------------- */
  const periodLabel = { today: 'bugun', '7d': 'oxirgi 7 kun', '30d': 'oxirgi 30 kun', '90d': 'oxirgi 90 kun' };
  document.querySelectorAll('.seg button').forEach(b => b.addEventListener('click', () => {
    period = b.dataset.period; document.querySelectorAll('.seg button').forEach(x => x.classList.toggle('active', x === b)); loadSummary();
  }));
  function startDashboard() {
    loadSummary(); loadLive();
    liveTimer = setInterval(() => { if (!document.hidden) loadLive(); }, 15000);
    sumTimer = setInterval(() => { if (!document.hidden) loadSummary(); }, 60000);
  }
  function analyticsError(e) { const el = $('#analyticsError'); el.hidden = false; el.textContent = e.status === 503 ? 'Analitika o‘chiq: serverda pdo_sqlite PHP kengaytmasini yoqing.' : 'Analitika yuklanmadi: ' + e.message; }
  async function loadLive() {
    try {
      const d = await api('admin/analytics/live');
      $('#kOnline').textContent = fmtNum(d.online);
      $('#liveUpdated').textContent = new Date().toLocaleTimeString('uz-UZ');
      const rows = d.sessions || [];
      $('#liveRows').innerHTML = rows.length ? rows.map(s => `<tr>
        <td class="mono">${esc(s.session_id)}</td><td>${esc(s.path)}</td><td>${esc(deviceName(s.device_type))}</td>
        <td>${esc(s.browser)}</td><td>${esc(s.os)}</td><td>${esc(String(s.lang || '–').toUpperCase())}</td>
        <td>${esc(duration(d.now - s.first_seen))}</td><td>${esc(ago(d.now - s.last_seen))}</td></tr>`).join('')
        : '<tr><td colspan="8" class="muted">Hozir saytda hech kim yo‘q.</td></tr>';
    } catch (e) { analyticsError(e); }
  }
  async function loadSummary() {
    try {
      const d = await api('admin/analytics/summary?period=' + encodeURIComponent(period));
      $('#analyticsError').hidden = true;
      $('#kViews').textContent = fmtNum(d.totals.views);
      $('#kSessions').textContent = fmtNum(d.totals.sessions);
      $('#kVisitors').textContent = fmtNum(d.totals.visitors);
      $('#kAll').textContent = fmtNum(d.all_time_views);
      $('#kPeriod').textContent = periodLabel[period];
      drawTrend(d.daily || []);
      bars('#bDevice', d.device_type, deviceName); bars('#bBrowser', d.browser); bars('#bOs', d.os);
      bars('#bPath', d.path); bars('#bRef', d.referrers, x => x || 'To‘g‘ridan-to‘g‘ri'); bars('#bLang', d.lang, x => x ? x.toUpperCase() : 'Noma’lum');
    } catch (e) { analyticsError(e); }
  }
  const deviceName = d => ({ mobile: 'Mobil', tablet: 'Planshet', desktop: 'Kompyuter' }[d] || d);
  const duration = s => s < 60 ? s + ' s' : s < 3600 ? Math.floor(s / 60) + ' daq' : Math.floor(s / 3600) + ' soat';
  const ago = s => s < 5 ? 'hozir' : s + ' s oldin';

  function bars(sel, rows, label = x => x) {
    rows = rows || [];
    const el = $(sel);
    if (!rows.length) { el.innerHTML = '<p class="empty">Ma’lumot yo‘q</p>'; return; }
    const total = rows.reduce((a, r) => a + Number(r.n), 0) || 1;
    const max = Math.max(...rows.map(r => Number(r.n))) || 1;
    el.innerHTML = rows.map(r => {
      const pct = Math.round(Number(r.n) / total * 100);
      const w = Math.max(1, Number(r.n) / max * 100).toFixed(2);
      return `<div class="brow" title="${esc(label(r.label))}: ${fmtNum(r.n)} (${pct}%)"><span class="lbl">${esc(label(r.label))}</span><span class="val">${fmtNum(r.n)} · ${pct}%</span>
        <svg viewBox="0 0 100 8" preserveAspectRatio="none" aria-hidden="true"><rect class="track" x="0" y="0" width="100" height="8" rx="4"/><rect class="fill" x="0" y="0" width="${w}" height="8" rx="4"/></svg></div>`;
    }).join('');
  }

  function drawTrend(days) {
    const W = 900, H = 220, pl = 34, pr = 8, pt = 10, pb = 24;
    const max = Math.max(4, ...days.map(d => d.views));
    const raw = max / 4, mag = Math.pow(10, Math.floor(Math.log10(raw)));
    const step = [1, 2, 5, 10].map(m => m * mag).find(s => s >= raw);
    const top = Math.ceil(max / step) * step;
    const y = v => pt + (H - pt - pb) * (1 - v / top);
    const bw = (W - pl - pr) / days.length;
    let g = '';
    for (let v = 0; v <= top; v += step) g += `<line class="grid" x1="${pl}" x2="${W - pr}" y1="${y(v)}" y2="${y(v)}"/><text class="axis" x="${pl - 6}" y="${y(v) + 3}" text-anchor="end">${fmtNum(v)}</text>`;
    const today = days.length - 1;
    days.forEach((d, i) => {
      const x = pl + i * bw, h = H - pb - y(d.views);
      const bx = x + 2, bwid = Math.max(2, bw - 4);
      g += `<g class="col" data-i="${i}"><rect class="hit" x="${x}" y="${pt}" width="${bw}" height="${H - pt - pb}"/>`;
      if (d.views > 0) g += `<path class="bar${i === today ? ' today' : ''}" d="M${bx},${H - pb} V${H - pb - h + Math.min(4, h)} q0,-${Math.min(4, h)} ${Math.min(4, bwid / 2)},-${Math.min(4, h)} H${bx + bwid - Math.min(4, bwid / 2)} q${Math.min(4, bwid / 2)},0 ${Math.min(4, bwid / 2)},${Math.min(4, h)} V${H - pb} Z"/>`;
      g += '</g>';
      if (i % 5 === 0 || i === today) g += `<text class="axis" x="${x + bw / 2}" y="${H - 8}" text-anchor="middle">${esc(d.day.slice(8, 10) + '.' + d.day.slice(5, 7))}</text>`;
    });
    $('#trend').innerHTML = `<svg viewBox="0 0 ${W} ${H}" preserveAspectRatio="none">${g}</svg>`;
    const sum = days.reduce((a, d) => a + d.views, 0);
    $('#trendNote').textContent = '30 kunda jami: ' + fmtNum(sum);
    const tip = $('#tip'), card = $('#trend').parentElement;
    $('#trend').querySelectorAll('.col').forEach(c => {
      c.addEventListener('mousemove', ev => {
        const d = days[Number(c.dataset.i)], rc = card.getBoundingClientRect();
        tip.textContent = `${d.day}: ${fmtNum(d.views)} ko‘rish · ${fmtNum(d.sessions)} sessiya`;
        tip.hidden = false; tip.style.left = (ev.clientX - rc.left) + 'px'; tip.style.top = (ev.clientY - rc.top) + 'px';
      });
      c.addEventListener('mouseleave', () => { tip.hidden = true; });
    });
  }

  /* ---------------- texts ---------------- */
  async function save(field, key, value) {
    try {
      const r = await api('admin/save', { method: 'POST', body: JSON.stringify({ revision: data.revision, field, key, value }) });
      data.revision = r.revision;
      if (field === 'text') data.texts[key] = { ...data.texts[key], ...value }; else data[field][key] = value;
      toast('Saqlandi — reviziya ' + r.revision); return true;
    } catch (e) {
      toast('Xato: ' + e.message, true);
      if (e.status === 409) toast('Boshqa oynada o‘zgartirilgan. Sahifani yangilang.', true);
      return false;
    }
  }
  async function translate(value, output) {
    if (!value.trim()) return;
    try { const r = await api('admin/translate', { method: 'POST', body: JSON.stringify({ text: value }) }); output.value = r.translation; toast('Ruscha tarjima taklif qilindi; tekshirib saqlang.'); }
    catch (e) { toast('Avtomatik tarjima: ' + e.message, true); }
  }
  function renderTexts() {
    const q = $('#search').value.toLowerCase().trim();
    const all = Object.entries(data.texts);
    const list = all.filter(([k, t]) => (!$('#draftOnly').checked || t.status !== 'reviewed' || !t.ru) &&
      (!q || k.includes(q) || String(t.uz).toLowerCase().includes(q) || String(t.ru).toLowerCase().includes(q)));
    $('#textCount').textContent = list.length + ' / ' + all.length + ' maydon';
    $('#entries').innerHTML = list.slice(0, textLimit).map(([k, t]) => `<article class="entry" data-key="${esc(k)}">
      <div class="entry-meta"><span>${esc(k)} · ${esc(t.context)}</span><span class="tag${t.status === 'reviewed' ? '' : ' warn'}">${t.status === 'reviewed' ? 'Tasdiqlangan' : 'Qoralama'}</span></div>
      <div class="entry-grid"><div><label>O‘zbekcha</label><textarea class="uz" maxlength="5000">${esc(t.uz)}</textarea></div><div><label>Русский</label><textarea class="ru" maxlength="5000">${esc(t.ru)}</textarea></div></div>
      <div class="row"><label class="check"><input type="checkbox" class="review"${t.status === 'reviewed' ? ' checked' : ''}> Ruscha tasdiqlandi</label><span class="spacer"></span>
      <button type="button" class="btn ghost translate">Tarjima qilish</button><button type="button" class="btn primary save">Saqlash</button></div></article>`).join('');
    $('#moreTexts').hidden = list.length <= textLimit;
    $('#entries').querySelectorAll('.entry').forEach(el => {
      const key = el.dataset.key, uz = el.querySelector('.uz'), ru = el.querySelector('.ru');
      el.querySelector('.save').addEventListener('click', async () => { if (await save('text', key, { uz: uz.value, ru: ru.value, status: el.querySelector('.review').checked ? 'reviewed' : 'draft' })) el.classList.remove('dirty'); });
      el.querySelector('.translate').addEventListener('click', () => translate(uz.value, ru));
      el.addEventListener('input', () => el.classList.add('dirty'));
      uz.addEventListener('input', () => { clearTimeout(transTimer); transTimer = setTimeout(() => { if (uz.value.trim() && uz.value !== data.texts[key].uz) translate(uz.value, ru); }, 1200); });
    });
  }
  $('#search').addEventListener('input', () => { textLimit = 40; renderTexts(); });
  $('#draftOnly').addEventListener('change', () => { textLimit = 40; renderTexts(); });
  $('#moreTexts').addEventListener('click', () => { textLimit += 40; renderTexts(); });

  /* ---------------- assets ---------------- */
  const slots = { logo: 'Asosiy logotip', favicon: 'Favicon' };
  const members = [1, 2, 3, 4];
  const memberName = i => (data.texts['t' + (147 + i * 3)] || {}).uz || ('A’zo ' + i);
  async function upload(slot, file, kind) {
    const isPdf = kind === 'pdf';
    if (!file) { toast(isPdf ? 'PDF faylni tanlang' : 'Rasm tanlang', true); return false; }
    if (isPdf && !/\.pdf$/i.test(file.name)) { toast('Faqat .pdf fayl', true); return false; }
    if (file.size > (isPdf ? 10e6 : 5e6)) { toast(isPdf ? 'PDF 10 MB dan oshmasin' : 'Rasm 5 MB dan oshmasin', true); return false; }
    try {
      const v = await api('admin/upload', { method: 'POST', headers: { 'X-Asset-Slot': slot, 'Content-Type': 'application/octet-stream' }, body: file });
      data.revision = v.revision; data.assets[slot] = v.url; toast(isPdf ? 'PDF saqlandi' : 'Rasm saqlandi'); return true;
    } catch (e) { toast('Xato: ' + e.message, true); return false; }
  }
  async function clearAsset(slot) {
    if (!confirm('Faylni saytdan olib tashlaysizmi?')) return false;
    try { const r = await api('admin/save', { method: 'POST', body: JSON.stringify({ revision: data.revision, field: 'asset_clear', key: slot, value: null }) });
      data.revision = r.revision; delete data.assets[slot]; toast('Olib tashlandi'); return true; }
    catch (e) { toast('Xato: ' + e.message, true); return false; }
  }
  const imgPreview = (url, label, empty) => url ? `<img src="${esc(url)}" alt="${esc(label)}">` : `<span class="muted small">${esc(empty)}</span>`;
  const fileName = url => String(url || '').split('/').pop().split('?')[0];
  function bindUploads(root, rerender) {
    root.querySelectorAll('[data-upload]').forEach(btn => btn.addEventListener('click', async () => {
      const input = root.querySelector('input[type=file][data-for="' + btn.dataset.upload + '"]');
      btn.disabled = true; try { if (await upload(btn.dataset.upload, input.files[0], btn.dataset.kind)) rerender(); } finally { btn.disabled = false; }
    }));
    root.querySelectorAll('[data-clear]').forEach(btn => btn.addEventListener('click', async () => { if (await clearAsset(btn.dataset.clear)) rerender(); }));
    root.querySelectorAll('input[data-flag]').forEach(cb => cb.addEventListener('change', async () => {
      if (!(await save('flags', cb.dataset.flag, cb.checked))) cb.checked = !cb.checked; else rerender();
    }));
  }
  function renderAssets() {
    $('#assetInputs').innerHTML = Object.entries(slots).map(([k, label]) => `<div class="asset"><strong>${esc(label)}</strong>
      <div class="preview">${imgPreview(data.assets[k], label, 'Standart rasm')}</div>
      <input type="file" accept="image/png,image/jpeg,image/webp" data-for="${esc(k)}"><div class="row"><button type="button" class="btn primary" data-upload="${esc(k)}" data-kind="image">Yuklash</button>
      ${data.assets[k] ? `<button type="button" class="btn ghost" data-clear="${esc(k)}">Standartga qaytarish</button>` : ''}</div></div>`).join('');
    bindUploads($('#assetInputs'), renderAssets);
    renderTeam();
  }
  function renderTeam() {
    const f = data.flags || (data.flags = {});
    const heroOn = f.hero_photo_visible === true;
    const hp = data.assets['hero-resume'], hrOn = f.hero_resume_visible === true;
    const ppu = (data.settings && data.settings.personal_page_url) || '', ppOn = f.personal_page_visible === true;
    $('#heroInputs').innerHTML = `<div class="hero-box hero-row"><div class="preview">${imgPreview(data.assets.hero, 'Bosh sahifa surati', 'KT (standart)')}</div>
      <div><input type="file" accept="image/png,image/jpeg,image/webp" data-for="hero">
      <div class="row"><button type="button" class="btn primary" data-upload="hero" data-kind="image">Surat yuklash</button>${data.assets.hero ? '<button type="button" class="btn ghost" data-clear="hero">Suratni o‘chirish</button>' : ''}</div>
      <label class="check"><input type="checkbox" data-flag="hero_photo_visible"${heroOn ? ' checked' : ''}> Saytda suratni ko‘rsatish (o‘chiq bo‘lsa «KT» doirasi ko‘rinadi)</label>
      <p class="hint">${heroOn && !data.assets.hero ? 'Belgi yoqilgan, lekin surat yuklanmagan — saytda hozircha «KT» ko‘rinadi.' : heroOn ? 'Holat: saytda surat ko‘rinmoqda.' : 'Holat: saytda «KT» ko‘rinmoqda.'}</p></div></div>
      <div class="hero-box hero-row"><div class="preview"><span class="muted">PDF</span></div>
      <div><b>Bosh sahifa: «Rezyume (PDF)» kartochkasi</b><br><span class="small">${hp ? `<a class="file-name" href="${esc(hp)}" target="_blank" rel="noopener">${esc(fileName(hp))}</a>` : '<span class="muted">yuklanmagan</span>'}</span>
      <input type="file" accept="application/pdf,.pdf" data-for="hero-resume" aria-label="Bosh sahifa rezyume PDF">
      <div class="row"><button type="button" class="btn primary" data-upload="hero-resume" data-kind="pdf">PDF yuklash</button>${hp ? '<button type="button" class="btn ghost" data-clear="hero-resume">PDFni o‘chirish</button>' : ''}</div>
      <label class="check"><input type="checkbox" data-flag="hero_resume_visible"${hrOn ? ' checked' : ''}> Saytda doira yonida «Rezyume (PDF)» kartochkasini ko‘rsatish</label>
      <p class="hint">${hrOn && !hp ? 'Belgi yoqilgan, lekin PDF yo‘q — kartochka chiqmaydi.' : hrOn ? 'Holat: kartochka saytda ko‘rinmoqda.' : 'Holat: kartochka yashirin (DOMda yo‘q).'}</p></div></div>
      <div class="hero-box hero-row"><div class="preview"><span class="muted">URL</span></div>
      <div class="pp-box"><b id="pp-lbl">Shaxsiy sahifa havolasi</b>
      <input id="s-personal_page_url" type="url" inputmode="url" maxlength="2048" placeholder="https://..." value="${esc(ppu)}" aria-labelledby="pp-lbl" aria-describedby="pp-help pp-err">
      <p class="hint" id="pp-help">Doira yonidagi «Shaxsiy sahifa» kartochkasi shu havolani yangi oynada (yangi tabda) ochadi. Faqat http:// yoki https:// bilan boshlanuvchi to‘liq havola.</p>
      <p class="hint pp-err" id="pp-err" role="alert"></p>
      <div class="row"><button type="button" class="btn primary" id="ppSave">Havolani saqlash</button>${ppu ? '<button type="button" class="btn ghost" id="ppClear">Havolani o‘chirish</button>' : ''}</div>
      <label class="check"><input type="checkbox" data-flag="personal_page_visible"${ppOn ? ' checked' : ''}> Saytda doira yonida «Shaxsiy sahifa» kartochkasini ko‘rsatish</label>
      <p class="hint">${ppOn && !ppu ? 'Belgi yoqilgan, lekin havola yo‘q — kartochka chiqmaydi.' : ppOn ? 'Holat: kartochka saytda ko‘rinmoqda.' : 'Holat: kartochka yashirin (DOMda yo‘q).'}</p></div></div>`;
    bindUploads($('#heroInputs'), renderTeam);
    {
      const inp = $('#s-personal_page_url'), err = $('#pp-err');
      const check = v => { if (v === '') return ''; if (v.length > 2048) return 'Havola juda uzun (2048 belgigacha).'; if (/[\s<>"'`\\]/.test(v)) return 'Havolada bo‘sh joy yoki taqiqlangan belgilar bor.';
        let u; try { u = new URL(v); } catch (e) { return 'Noto‘g‘ri havola. Masalan: https://example.com/profil'; }
        if (u.protocol !== 'http:' && u.protocol !== 'https:') return 'Faqat http:// yoki https:// havolalarga ruxsat beriladi.';
        if (!/^https?:\/\//i.test(v) || !u.hostname) return 'To‘liq havola kiriting (https:// bilan).'; if (u.username || u.password) return 'Havolada login/parol bo‘lmasligi kerak.'; return ''; };
      inp.addEventListener('input', () => { err.textContent = check(inp.value.trim()); });
      $('#ppSave').addEventListener('click', async () => { const v = inp.value.trim(), m = check(v); err.textContent = m; if (m) { inp.focus(); return; }
        if (await save('settings', 'personal_page_url', v)) renderTeam(); });
      const cl = $('#ppClear'); if (cl) cl.addEventListener('click', async () => { if (await save('settings', 'personal_page_url', '')) renderTeam(); });
    }
    $('#teamInputs').innerHTML = members.map(i => {
      const photo = data.assets['expert-' + i], pdf = data.assets['resume-' + i], on = f['resume_visible_' + i] === true, name = memberName(i);
      return `<div class="asset member"><strong>${esc(name)}</strong>
        <div class="preview">${imgPreview(photo, name, 'Surat yo‘q — standart ikonka')}</div>
        <input type="file" accept="image/png,image/jpeg,image/webp" data-for="expert-${i}" aria-label="${esc(name)} surati">
        <div class="row"><button type="button" class="btn primary" data-upload="expert-${i}" data-kind="image">Surat yuklash</button>${photo ? `<button type="button" class="btn ghost" data-clear="expert-${i}">O‘chirish</button>` : ''}</div>
        <hr><span class="small"><b>Rezyume (PDF):</b> ${pdf ? `<a class="file-name" href="${esc(pdf)}" target="_blank" rel="noopener">${esc(fileName(pdf))}</a>` : '<span class="muted">yuklanmagan</span>'}</span>
        <input type="file" accept="application/pdf,.pdf" data-for="resume-${i}" aria-label="${esc(name)} rezyumesi">
        <div class="row"><button type="button" class="btn primary" data-upload="resume-${i}" data-kind="pdf">PDF yuklash</button>${pdf ? `<button type="button" class="btn ghost" data-clear="resume-${i}">O‘chirish</button>` : ''}</div>
        <label class="check"><input type="checkbox" data-flag="resume_visible_${i}"${on ? ' checked' : ''}> Saytda «Rezyume (PDF)» tugmasini ko‘rsatish</label>
        ${on && !pdf ? '<p class="hint">Belgi yoqilgan, lekin PDF yo‘q — tugma chiqmaydi.</p>' : ''}</div>`;
    }).join('');
    bindUploads($('#teamInputs'), renderTeam);
  }

  /* ---------------- settings ---------------- */
  function settingRows(target, specs) {
    $(target).innerHTML = specs.map(([kind, key, label, type, hint]) => `<div class="setting" data-kind="${kind}" data-key="${key}">
      <label for="s-${key}">${esc(label)}</label><input id="s-${key}" type="${type}" value="${esc(data[kind][key] || '')}">
      <button type="button" class="btn primary">Saqlash</button>${hint ? `<p class="hint">${esc(hint)}</p>` : ''}</div>`).join('');
    $(target).querySelectorAll('.setting').forEach(el => el.querySelector('button').addEventListener('click', () => save(el.dataset.kind, el.dataset.key, el.querySelector('input').value)));
  }
  function renderSettings() {
    settingRows('#themeInputs', [['theme', 'navy', 'To‘q ko‘k (asosiy)', 'color'], ['theme', 'green', 'Yashil (aksent)', 'color']]);
    settingRows('#contactInputs', [
      ['settings', 'phone', 'Telefon', 'tel', 'Xalqaro formatda: +998XXXXXXXXX'],
      ['settings', 'email', 'Email', 'email'],
      ['settings', 'telegram', 'Telegram username', 'text', '@ belgisisiz'],
      ['settings', 'instagram', 'Instagram username', 'text'],
      ['settings', 'map', 'Manzil (xarita qidiruvi)', 'text', 'Google/Yandex/2GIS profilidagi manzil bilan bir xil yozing.']]);
    settingRows('#seoInputs', [
      ['settings', 'seo_title_uz', 'SEO sarlavha · O‘zbekcha', 'text', '50–60 belgi tavsiya etiladi'],
      ['settings', 'seo_title_ru', 'SEO sarlavha · Русский', 'text'],
      ['settings', 'seo_description_uz', 'SEO tavsif · O‘zbekcha', 'text', '140–160 belgi tavsiya etiladi'],
      ['settings', 'seo_description_ru', 'SEO tavsif · Русский', 'text']]);
  }

  /* ---------------- security ---------------- */
  const eventName = { webhook_rejected: 'Webhook rad etildi', lead_reply: 'Javob yuborildi', lead_update: 'Lead yangilandi', lead_delete: 'Lead o‘chirildi', channel_setting_save: 'Kanal sozlamasi', telegram_webhook_set: 'Telegram webhook', blog_publish: 'Blog: nashr', blog_unpublish: 'Blog: nashrdan olindi', blog_save: 'Blog: saqlash', blog_create: 'Blog: yangi', blog_delete: 'Blog: o‘chirildi', case_save: 'Keys: saqlash', case_delete: 'Keys: o‘chirildi', testimonial_save: 'Fikr: saqlash', testimonial_delete: 'Fikr: o‘chirildi', booking_confirmed: 'Bron tasdiqlandi', booking_cancelled: 'Bron bekor qilindi', booking_pending: 'Bron kutilmoqda', newsletter_campaign_created: 'Newsletter yaratildi', newsletter_campaign_finished: 'Newsletter yuborildi', media_upload: 'Media yuklandi', ab_save: 'A/B saqlash', ab_running: 'A/B boshlandi', ab_stopped: 'A/B to‘xtatildi',
    login_success: 'Kirish', login_failed: 'Noto‘g‘ri parol', login_blocked_ip: 'IP bloklandi', login_blocked_global: 'Global blok', login_unlock_issued: 'Telegram unlock kodi', login_unlock_used: 'Unlock bilan kirish', logout: 'Chiqish', content_save: 'Saqlash', asset_upload: 'Fayl yuklash', asset_clear: 'Fayl o‘chirildi', setup_admin_created: 'Admin yaratildi' };
  const badEvents = ['webhook_rejected', 'login_failed', 'login_blocked_ip', 'login_blocked_global'];
  async function loadSecurity() {
    try {
      const d = await api('admin/security'); const c = d.checks;
      const item = (label, ok, text) => `<div class="check-item"><span>${esc(label)}</span><span class="tag${ok ? '' : ' bad'}">${esc(text)}</span></div>`;
      $('#secChecks').innerHTML = [
        item('HTTPS', c.https, c.https ? 'Yoqilgan' : 'Yo‘q'),
        item('setup.php o‘chirilgan', !c.setup_php_present, c.setup_php_present ? 'O‘chiring!' : 'Ha'),
        item('pdo_sqlite (analitika)', c.pdo_sqlite, c.pdo_sqlite ? 'Bor' : 'Yo‘q'),
        item('GD (rasmlar)', c.gd, c.gd ? 'Bor' : 'Yo‘q'),
        item('cURL (tarjima)', c.curl, c.curl ? 'Bor' : 'Yo‘q'),
        item('storage yoziladi', c.storage_writable, c.storage_writable ? 'Ha' : 'Yo‘q'),
        item('auth.json ruxsati', c.auth_file_mode === '600', c.auth_file_mode || '–'),
        item('PHP versiyasi', true, c.php_version)].join('');
      $('#auditRows').innerHTML = (d.audit || []).map(a => `<tr><td>${esc(new Date(a.ts * 1000).toLocaleString('uz-UZ'))}</td>
        <td><span class="tag${badEvents.includes(a.event) ? ' bad' : ''}">${esc(eventName[a.event] || a.event)}</span></td>
        <td class="mono">${esc(a.detail && Object.keys(a.detail).length ? JSON.stringify(a.detail) : '')}</td><td class="mono">${esc(a.ip)}</td><td class="small">${esc(a.ua)}</td></tr>`).join('')
        || '<tr><td colspan="5" class="muted">Hozircha yozuv yo‘q.</td></tr>';
    } catch (e) { toast(e.message, true); }
  }
  $('#secReload').addEventListener('click', loadSecurity);

  // v7: assets/js/admin/*.js (v12 gacha admin-ext.js; CRM, kalendar, blog, ...) shu yordamchilardan foydalanadi.
  window.DARIKO_ADMIN = { api, esc, toast, fmtNum, getData: () => data };
  boot().catch(e => { showLogin(); $('#loginError').textContent = e.message; });
})();
