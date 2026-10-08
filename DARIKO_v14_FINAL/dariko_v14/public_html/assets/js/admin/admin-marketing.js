/* DARIKO admin panel — Newsletter va A/B testlar (v12: admin-ext.js dan ajratildi). Bog'liqlik: admin.js, admin-core.js. */
(() => {
  'use strict';
  const { A, $, esc, toast, get, post, dt, day, SRC, ST, srcTag, stTag, fail, uploadMedia, addTimer, shared } = window.DARIKO_ADMIN_EXT;
  /* ============================== Newsletter ============================== */
  async function loadNl() {
    let d; try { d = await get('admin/subscribers'); } catch (e) { return fail(e); }
    $('#nlEnabled').checked = !!d.enabled;
    const s = d.subscribers, active = s.filter(x => Number(x.confirmed) && !x.unsubscribed_at).length;
    $('#nlKpis').innerHTML = [['Tasdiqlangan', active], ['Tasdiqlanmagan', s.filter(x => !Number(x.confirmed) && !x.unsubscribed_at).length], ['Bekor qilgan', s.filter(x => x.unsubscribed_at).length]]
      .map(([l, n]) => `<div class="kpi"><span class="kpi-label">${esc(l)}</span><strong>${A().fmtNum(n)}</strong></div>`).join('');
    $('#nlMode').textContent = d.mail_mode === 'smtp' ? 'Yuborish usuli: SMTP (Kanallar sozlamalarida).' : 'Yuborish usuli: PHP mail(). Ishonchli yetkazish uchun «Kanallar sozlamalari»da SMTP ni sozlang va test xat yuboring.';
    $('#campRows').innerHTML = d.campaigns.map(c => `<tr><td class="small">${esc(dt(c.created_at))}</td><td>${esc(c.subject)}</td><td>${c.sent}/${c.recipients}</td><td>${c.failed ? `<span class="tag bad">${c.failed}</span>` : '0'}</td></tr>`).join('') || '<tr><td colspan="4" class="muted">Hali xat yuborilmagan.</td></tr>';
    $('#subRows').innerHTML = s.map(x => `<tr><td>${esc(x.email)}</td><td>${esc(x.name)}</td><td>${x.unsubscribed_at ? '<span class="tag bad">Bekor qilgan</span>' : Number(x.confirmed) ? '<span class="tag ok">Tasdiqlangan</span>' : '<span class="tag warn">Kutilmoqda</span>'}</td>
      <td class="small">${esc(dt(x.subscribed_at))}</td><td class="actions"><button type="button" class="btn ghost sm" data-del="${x.id}">O‘chirish</button></td></tr>`).join('') || '<tr><td colspan="5" class="muted">Obunachi yo‘q.</td></tr>';
    $('#subRows').querySelectorAll('[data-del]').forEach(b => b.addEventListener('click', async () => { if (!confirm('Obunachi butunlay o‘chirilsinmi?')) return; try { await post('admin/subscriber/delete', { id: Number(b.dataset.del) }); loadNl(); } catch (e) { fail(e); } }));
  }
  $('#nlEnabled').addEventListener('change', async e => {
    const on = e.target.checked;
    if (on && !confirm('Ommaviy obuna API’si yoqilsinmi? Faqat saytda obuna formasi bo‘lsa kerak.')) { e.target.checked = false; return; }
    try { await post('admin/newsletter/enabled', { enabled: on }); toast(on ? 'Obuna API yoqildi' : 'Obuna API o‘chirildi'); } catch (err) { e.target.checked = !on; fail(err); }
  });
  $('#nlSend').addEventListener('click', async () => {
    const subject = $('#nlSubject').value.trim(), body = $('#nlBody').value.trim();
    if (!subject || body.length < 10) return toast('Mavzu va matnni kiriting', true);
    if (!confirm('Xat barcha tasdiqlangan obunachilarga yuborilsinmi?')) return;
    const btn = $('#nlSend'); btn.disabled = true;
    try {
      const c = await post('admin/newsletter/create', { subject, body });
      let left = c.recipients, sent = 0, failed = 0;
      while (left > 0) {
        $('#nlProgress').textContent = `Yuborilmoqda… ${sent}/${c.recipients}`;
        const r = await post('admin/newsletter/send-batch', { id: c.id });
        sent += r.sent; failed += r.failed; left = r.remaining;
        if (left > 0) await new Promise(res => setTimeout(res, 2000));
      }
      $('#nlProgress').textContent = `Tayyor: ${sent} yuborildi, ${failed} xato.`; toast('Xat yuborildi');
      $('#nlSubject').value = ''; $('#nlBody').value = ''; loadNl();
    } catch (e) { fail(e); $('#nlProgress').textContent = 'To‘xtadi — qayta bosganingizda yangi xat yaratiladi.'; } finally { btn.disabled = false; }
  });

  /* ============================== A/B ============================== */
  const SLOT = { hero_title_accent: 'Hero sarlavha — yashil qism («HR va marketing»)', hero_title_rest: 'Hero sarlavha — davomi («tizimini biznesingizga mos quramiz»)', hero_cta: 'Hero asosiy tugma («Konsultatsiyaga yozilish»)' };
  const GOAL = { lead: 'Ariza yoki bron yuborildi', cta_click: 'Konsultatsiya tugmasi bosildi' };
  async function loadAb() {
    let d; try { d = await get('admin/ab'); } catch (e) { $('#abList').innerHTML = `<p class="error">${esc(e.message)}</p>`; return; }
    $('#abList').innerHTML = d.experiments.map(x => `<div class="card"><div class="card-head"><h2>${esc(x.name)}</h2>
        <span><span class="tag ${x.status === 'running' ? 'ok' : x.status === 'stopped' ? 'bad' : 'warn'}">${esc({ draft: 'Qoralama', running: 'Ishlamoqda', stopped: 'To‘xtatilgan' }[x.status])}</span></span></div>
      <p class="small muted">${esc(SLOT[x.slot])} · Maqsad: ${esc(GOAL[x.goal])}${x.started_at ? ' · Boshlangan: ' + esc(dt(x.started_at)) : ''}</p>
      <div class="table-wrap"><table class="table"><thead><tr><th>Variant</th><th>Matn (UZ)</th><th>Sessiyalar</th><th>Ko‘rishlar</th><th>Konversiya</th><th>Konversiya %</th></tr></thead><tbody>
      ${x.stats.map(s => `<tr><td><b>${esc(s.label)}</b></td><td>${esc(x.variants[s.variant].uz || '(asl matn)')}</td><td>${A().fmtNum(s.sessions)}</td><td>${A().fmtNum(s.views)}</td><td>${A().fmtNum(s.conversions)}</td>
        <td><b>${s.rate.toFixed(2)}%</b><svg class="ab-bar" viewBox="0 0 100 6" preserveAspectRatio="none" aria-hidden="true"><rect class="track" width="100" height="6" rx="3"/><rect class="fill" width="${Math.max(1, Math.min(100, s.rate * (100 / Math.max(1, ...x.stats.map(z => z.rate)))))}" height="6" rx="3"/></svg></td></tr>`).join('')}</tbody></table></div>
      ${Math.min(...x.stats.map(s => s.sessions)) < 300 ? '<p class="hint">Hali kam ma’lumot: har bir variantda kamida ~300 sessiya bo‘lmaguncha xulosa chiqarmang.</p>' : ''}
      <div class="row">${x.status === 'draft' ? `<button type="button" class="btn ghost" data-ab-edit="${x.id}">Tahrirlash</button>` : ''}
        ${x.status !== 'running' ? `<button type="button" class="btn primary" data-ab-st="running" data-id="${x.id}">${x.status === 'stopped' ? 'Davom ettirish' : 'Boshlash'}</button>` : `<button type="button" class="btn ghost" data-ab-st="stopped" data-id="${x.id}">To‘xtatish</button>`}
        <span class="spacer"></span><button type="button" class="btn ghost danger" data-ab-del="${x.id}">O‘chirish</button></div></div>`).join('') || '<p class="muted card">Hali test yo‘q.</p>';
    $('#abList').querySelectorAll('[data-ab-st]').forEach(b => b.addEventListener('click', async () => { try { await post('admin/ab/status', { id: Number(b.dataset.id), status: b.dataset.abSt }); loadAb(); } catch (e) { fail(e); } }));
    $('#abList').querySelectorAll('[data-ab-del]').forEach(b => b.addEventListener('click', async () => { if (!confirm('Test va uning statistikasi o‘chirilsinmi?')) return; try { await post('admin/ab/delete', { id: Number(b.dataset.abDel) }); loadAb(); } catch (e) { fail(e); } }));
    $('#abList').querySelectorAll('[data-ab-edit]').forEach(b => b.addEventListener('click', () => editAb(d.experiments.find(x => x.id === Number(b.dataset.abEdit)))));
  }
  function editAb(x) {
    x = x || { id: 0, name: '', slot: 'hero_cta', goal: 'lead', variants: [{ uz: '', ru: '' }, { uz: '', ru: '' }] };
    const ed = $('#abEditor'); ed.hidden = false;
    const vRow = (v, i) => `<div class="ab-var"><b>${String.fromCharCode(65 + i)}${i === 0 ? ' (nazorat)' : ''}</b><input data-v="uz" placeholder="${i === 0 ? 'Bo‘sh = saytdagi joriy matn' : 'O‘zbekcha matn *'}" value="${esc(v.uz)}"><input data-v="ru" placeholder="Ruscha (ixtiyoriy)" value="${esc(v.ru)}"></div>`;
    ed.innerHTML = `<div class="card-head"><h2>${x.id ? 'Testni tahrirlash' : 'Yangi A/B test'}</h2><button type="button" class="btn ghost" id="abClose">Yopish</button></div>
      <label for="abName">Nomi</label><input id="abName" maxlength="120" value="${esc(x.name)}" placeholder="Masalan: CTA matni — sentabr">
      <div class="grid2 tight"><label>Element<select id="abSlot">${Object.entries(SLOT).map(([k, v]) => `<option value="${k}"${k === x.slot ? ' selected' : ''}>${esc(v)}</option>`).join('')}</select></label>
        <label>Maqsad (konversiya)<select id="abGoal">${Object.entries(GOAL).map(([k, v]) => `<option value="${k}"${k === x.goal ? ' selected' : ''}>${esc(v)}</option>`).join('')}</select></label></div>
      <label>Variantlar</label><div id="abVars">${x.variants.map(vRow).join('')}</div>
      <div class="row"><button type="button" class="btn ghost sm" id="abAddV">+ Variant (maks. 4)</button><span class="spacer"></span><button type="button" class="btn primary" id="abSave">Saqlash (qoralama)</button></div>`;
    $('#abClose').addEventListener('click', () => { ed.hidden = true; });
    $('#abAddV').addEventListener('click', () => { const n = ed.querySelectorAll('.ab-var').length; if (n < 4) $('#abVars').insertAdjacentHTML('beforeend', vRow({ uz: '', ru: '' }, n)); });
    $('#abSave').addEventListener('click', async () => {
      const variants = [...ed.querySelectorAll('.ab-var')].map(r => ({ uz: r.querySelector('[data-v=uz]').value, ru: r.querySelector('[data-v=ru]').value }));
      try { await post('admin/ab/save', { id: x.id, name: $('#abName').value, slot: $('#abSlot').value, goal: $('#abGoal').value, variants }); toast('Saqlandi. «Boshlash» tugmasi bilan ishga tushiring.'); ed.hidden = true; loadAb(); } catch (e) { fail(e); }
    });
  }
  $('#abNew').addEventListener('click', () => editAb(null));

  Object.assign(window.DARIKO_ADMIN_TABS, { newsletter: loadNl, ab: loadAb });
})();
