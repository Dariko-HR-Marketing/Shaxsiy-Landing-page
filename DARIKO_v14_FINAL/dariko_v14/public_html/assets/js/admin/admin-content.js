/* DARIKO admin panel — Blog, Keyslar, Mijozlar fikri (v12: admin-ext.js dan ajratildi). Bog'liqlik: admin.js, admin-core.js. */
(() => {
  'use strict';
  const { A, $, esc, toast, get, post, dt, day, SRC, ST, srcTag, stTag, fail, uploadMedia, addTimer, shared } = window.DARIKO_ADMIN_EXT;
  /* ============================== Blog ============================== */
  let posts = [];
  async function loadPosts() {
    try { posts = (await get('admin/blog')).posts; } catch (e) { return fail(e); }
    $('#postRows').innerHTML = posts.map(p => `<tr><td><b>${esc(p.title)}</b><br><span class="muted small">/blog/${esc(p.slug)}</span></td>
      <td><span class="tag ${p.status === 'published' ? 'ok' : 'warn'}">${p.status === 'published' ? 'Nashr qilingan' : 'Qoralama'}</span></td><td class="small">${esc(day(p.published_at))}</td>
      <td class="actions"><button type="button" class="btn ghost sm" data-edit="${p.id}">Tahrirlash</button>${p.status === 'published' ? `<a class="btn ghost sm" href="/blog/${esc(p.slug)}" target="_blank" rel="noopener">Ko‘rish ↗</a>` : ''}</td></tr>`).join('') || '<tr><td colspan="4" class="muted">Maqola yo‘q.</td></tr>';
    $('#postRows').querySelectorAll('[data-edit]').forEach(b => b.addEventListener('click', () => editPost(posts.find(p => p.id === Number(b.dataset.edit)))));
  }
  function editPost(p) {
    p = p || { id: 0, title: '', slug: '', excerpt: '', body: '', cover_image: '', author: 'Khikmatullo Turaev', tags: [], seo_title: '', seo_description: '', lang: 'uz', status: 'draft', published_at: null };
    const ed = $('#postEditor'); ed.hidden = false;
    ed.innerHTML = `<div class="card-head"><h2>${p.id ? 'Maqolani tahrirlash' : 'Yangi maqola'}</h2><button type="button" class="btn ghost" id="peClose">Yopish</button></div>
      <label for="peTitle">Sarlavha *</label><input id="peTitle" maxlength="200" value="${esc(p.title)}">
      <div class="grid3 tight"><label>Slug (URL)<input id="peSlug" maxlength="80" value="${esc(p.slug)}" placeholder="avtomatik"></label>
        <label>Muallif<input id="peAuthor" maxlength="120" value="${esc(p.author)}"></label>
        <label>Nashr sanasi<input id="peDate" type="date" value="${esc(day(p.published_at))}"></label></div>
      <label for="peExcerpt">Qisqa tavsif (ro‘yxatda ko‘rinadi)</label><textarea id="peExcerpt" rows="2" maxlength="500">${esc(p.excerpt)}</textarea>
      <label for="peBody">Matn (Markdown)</label><textarea id="peBody" class="mono-area" rows="18" maxlength="60000">${esc(p.body)}</textarea>
      <div class="grid2 tight"><label>Teglar (vergul bilan)<input id="peTags" value="${esc(p.tags.join(', '))}"></label>
        <label>Til<select id="peLang"><option value="uz"${p.lang === 'uz' ? ' selected' : ''}>O‘zbekcha</option><option value="ru"${p.lang === 'ru' ? ' selected' : ''}>Русский</option></select></label>
        <label>SEO sarlavha (ixtiyoriy)<input id="peSeoT" maxlength="180" value="${esc(p.seo_title)}"></label>
        <label>SEO tavsif (ixtiyoriy)<input id="peSeoD" maxlength="300" value="${esc(p.seo_description)}"></label></div>
      <label>Muqova rasmi</label><div class="cover-row"><div class="preview mini">${p.cover_image ? `<img src="${esc(p.cover_image)}" alt="">` : '<span class="muted small">yo‘q</span>'}</div>
        <input type="file" id="peFile" accept="image/png,image/jpeg,image/webp"><button type="button" class="btn ghost" id="peUp">Yuklash</button>${p.cover_image ? '<button type="button" class="btn ghost" id="peRm">Olib tashlash</button>' : ''}</div>
      <div class="row"><label class="check"><input type="checkbox" id="pePub"${p.status === 'published' ? ' checked' : ''}> Nashr qilingan (saytda ko‘rinadi)</label><span class="spacer"></span>
        ${p.id ? '<button type="button" class="btn ghost danger" id="peDel">O‘chirish</button>' : ''}<button type="button" class="btn primary" id="peSave">Saqlash</button></div>`;
    let cover = p.cover_image;
    ed.scrollIntoView({ behavior: 'smooth' });
    $('#peClose').addEventListener('click', () => { ed.hidden = true; });
    $('#peUp').addEventListener('click', async () => { const u = await uploadMedia($('#peFile').files[0]); if (u) { cover = u; ed.querySelector('.preview').innerHTML = `<img src="${esc(u)}" alt="">`; } });
    if ($('#peRm')) $('#peRm').addEventListener('click', () => { cover = ''; ed.querySelector('.preview').innerHTML = '<span class="muted small">yo‘q</span>'; });
    $('#peSave').addEventListener('click', async () => {
      try {
        const r = await post('admin/blog/save', { id: p.id, title: $('#peTitle').value, slug: $('#peSlug').value, author: $('#peAuthor').value, published_at: $('#peDate').value,
          excerpt: $('#peExcerpt').value, body: $('#peBody').value, tags: $('#peTags').value, lang: $('#peLang').value, seo_title: $('#peSeoT').value,
          seo_description: $('#peSeoD').value, cover_image: cover, status: $('#pePub').checked ? 'published' : 'draft' });
        toast('Saqlandi' + (r.sitemap && !r.sitemap.ok ? ' — ' + r.sitemap.warning : ' · sitemap yangilandi'), r.sitemap && !r.sitemap.ok);
        await loadPosts(); editPost(posts.find(x => x.id === r.id));
      } catch (e) { fail(e); }
    });
    if ($('#peDel')) $('#peDel').addEventListener('click', async () => { if (!confirm('Maqola o‘chirilsinmi?')) return; try { await post('admin/blog/delete', { id: p.id }); ed.hidden = true; loadPosts(); } catch (e) { fail(e); } });
  }
  $('#postNew').addEventListener('click', () => editPost(null));

  /* ============================== Keyslar ============================== */
  let cases = [];
  async function loadCases() {
    try { cases = (await get('admin/cases')).cases; } catch (e) { return fail(e); }
    $('#caseRows').innerHTML = cases.map(c => `<tr><td><b>${esc(c.title)}</b>${c.is_sample ? ' <span class="tag warn">Namuna</span>' : ''}</td><td>${esc(c.industry)}</td>
      <td><span class="tag ${c.status === 'published' ? 'ok' : 'warn'}">${c.status === 'published' ? 'Nashr qilingan' : 'Qoralama'}</span></td>
      <td class="actions"><button type="button" class="btn ghost sm" data-edit="${c.id}">Tahrirlash</button></td></tr>`).join('') || '<tr><td colspan="4" class="muted">Keys yo‘q.</td></tr>';
    $('#caseRows').querySelectorAll('[data-edit]').forEach(b => b.addEventListener('click', () => editCase(cases.find(c => c.id === Number(b.dataset.edit)))));
  }
  function editCase(c) {
    c = c || { id: 0, title: '', slug: '', client_name: '', industry: '', challenge: '', approach: '', result_summary: '', result_metrics: [], testimonial_quote: '', cover_image: '', status: 'draft', sort_order: 0, is_sample: false };
    const ed = $('#caseEditor'); ed.hidden = false;
    const metricRow = m => `<div class="metric-row"><input data-m="label" placeholder="Ko‘rsatkich (masalan: Javob vaqti)" value="${esc(m.label)}"><input data-m="before" placeholder="Avval" value="${esc(m.before)}"><input data-m="after" placeholder="Keyin" value="${esc(m.after)}"><button type="button" class="btn ghost sm" data-rm>×</button></div>`;
    ed.innerHTML = `<div class="card-head"><h2>${c.id ? 'Keysni tahrirlash' : 'Yangi keys'}</h2><button type="button" class="btn ghost" id="ceClose">Yopish</button></div>
      ${c.is_sample ? '<p class="hint warn-text">Bu yozuv rezyumedagi tajribadan olingan NAMUNA. «[?]» va «[NAMUNA…]» joylarini real, tasdiqlangan ma’lumot bilan almashtiring, «Namuna» belgisini olib tashlang va shundan keyingina nashr qiling.</p>' : ''}
      <label for="ceTitle">Sarlavha *</label><input id="ceTitle" maxlength="200" value="${esc(c.title)}">
      <div class="grid3 tight"><label>Mijoz (yoki anonim: «Chakana savdo tarmog‘i»)<input id="ceClient" maxlength="160" value="${esc(c.client_name)}"></label>
        <label>Soha<input id="ceInd" maxlength="120" value="${esc(c.industry)}"></label><label>Slug<input id="ceSlug" maxlength="80" value="${esc(c.slug)}" placeholder="avtomatik"></label></div>
      <label for="ceCh">Vazifa / muammo (Markdown)</label><textarea id="ceCh" rows="4">${esc(c.challenge)}</textarea>
      <label for="ceAp">Yondashuv (Markdown)</label><textarea id="ceAp" rows="4">${esc(c.approach)}</textarea>
      <label for="ceRs">Natija (Markdown)</label><textarea id="ceRs" rows="3">${esc(c.result_summary)}</textarea>
      <label>Natija raqamlarda (avval → keyin)</label><div id="ceMetrics">${c.result_metrics.map(metricRow).join('')}</div><button type="button" class="btn ghost sm" id="ceAddM">+ Ko‘rsatkich</button>
      <label for="ceQ">Mijoz iqtibosi (ixtiyoriy, faqat rozilik bilan)</label><textarea id="ceQ" rows="2" maxlength="1000">${esc(c.testimonial_quote)}</textarea>
      <label>Muqova rasmi</label><div class="cover-row"><div class="preview mini">${c.cover_image ? `<img src="${esc(c.cover_image)}" alt="">` : '<span class="muted small">yo‘q</span>'}</div><input type="file" id="ceFile" accept="image/png,image/jpeg,image/webp"><button type="button" class="btn ghost" id="ceUp">Yuklash</button></div>
      <div class="row"><label class="check"><input type="checkbox" id="ceSample"${c.is_sample ? ' checked' : ''}> Namuna (to‘ldirilmagan)</label><label class="check"><input type="checkbox" id="cePub"${c.status === 'published' ? ' checked' : ''}> Nashr qilingan</label>
        <label class="inline">Tartib <input id="ceSort" type="number" value="${Number(c.sort_order) || 0}"></label><span class="spacer"></span>${c.id ? '<button type="button" class="btn ghost danger" id="ceDel">O‘chirish</button>' : ''}<button type="button" class="btn primary" id="ceSave">Saqlash</button></div>`;
    let cover = c.cover_image;
    const bindRm = () => ed.querySelectorAll('[data-rm]').forEach(b => { b.onclick = () => b.parentElement.remove(); });
    bindRm();
    ed.scrollIntoView({ behavior: 'smooth' });
    $('#ceClose').addEventListener('click', () => { ed.hidden = true; });
    $('#ceAddM').addEventListener('click', () => { $('#ceMetrics').insertAdjacentHTML('beforeend', metricRow({ label: '', before: '', after: '' })); bindRm(); });
    $('#ceUp').addEventListener('click', async () => { const u = await uploadMedia($('#ceFile').files[0]); if (u) { cover = u; ed.querySelector('.preview').innerHTML = `<img src="${esc(u)}" alt="">`; } });
    $('#ceSave').addEventListener('click', async () => {
      if ($('#cePub').checked && $('#ceSample').checked) return toast('«Namuna» belgili keysni nashr qilib bo‘lmaydi — avval real ma’lumot bilan to‘ldiring.', true);
      const metrics = [...ed.querySelectorAll('.metric-row')].map(r => ({ label: r.querySelector('[data-m=label]').value, before: r.querySelector('[data-m=before]').value, after: r.querySelector('[data-m=after]').value }));
      try {
        const r = await post('admin/case/save', { id: c.id, title: $('#ceTitle').value, client_name: $('#ceClient').value, industry: $('#ceInd').value, slug: $('#ceSlug').value,
          challenge: $('#ceCh').value, approach: $('#ceAp').value, result_summary: $('#ceRs').value, result_metrics: metrics, testimonial_quote: $('#ceQ').value,
          cover_image: cover, status: $('#cePub').checked ? 'published' : 'draft', sort_order: Number($('#ceSort').value), is_sample: $('#ceSample').checked });
        toast('Saqlandi'); await loadCases(); editCase(cases.find(x => x.id === r.id));
      } catch (e) { fail(e); }
    });
    if ($('#ceDel')) $('#ceDel').addEventListener('click', async () => { if (!confirm('Keys o‘chirilsinmi?')) return; try { await post('admin/case/delete', { id: c.id }); ed.hidden = true; loadCases(); } catch (e) { fail(e); } });
  }
  $('#caseNew').addEventListener('click', () => editCase(null));

  /* ============================== Fikrlar ============================== */
  let testi = [];
  async function loadTesti() {
    try { testi = (await get('admin/testimonials')).testimonials; } catch (e) { return fail(e); }
    $('#testiRows').innerHTML = testi.map((t, i) => `<tr><td class="actions"><button type="button" class="btn ghost sm" data-up="${i}" ${i ? '' : 'disabled'} aria-label="Yuqoriga">↑</button><button type="button" class="btn ghost sm" data-down="${i}" ${i < testi.length - 1 ? '' : 'disabled'} aria-label="Pastga">↓</button></td>
      <td><b>${esc(t.client_name)}</b><br><span class="muted small">${esc([t.role, t.company].filter(Boolean).join(', '))}</span></td><td class="small">${esc(String(t.quote).slice(0, 120))}${t.rating ? '<br>' + '★'.repeat(Number(t.rating)) : ''}</td>
      <td>${esc(t.source)}</td><td><label class="check"><input type="checkbox" data-pub="${t.id}"${Number(t.published) ? ' checked' : ''}> ${Number(t.published) ? 'Nashrda' : 'Yashirin'}</label></td>
      <td class="actions"><button type="button" class="btn ghost sm" data-edit="${t.id}">Tahrirlash</button></td></tr>`).join('') || '<tr><td colspan="6" class="muted">Hozircha fikr yo‘q. Bosh sahifadagi «Mijozlar fikri» bo‘limi fikr nashr qilinmaguncha yashirin turadi.</td></tr>';
    const reorder = async ids => { try { await post('admin/testimonial/reorder', { ids }); loadTesti(); } catch (e) { fail(e); } };
    $('#testiRows').querySelectorAll('[data-up],[data-down]').forEach(b => b.addEventListener('click', () => {
      const i = Number(b.dataset.up ?? b.dataset.down), j = b.dataset.up !== undefined ? i - 1 : i + 1, ids = testi.map(t => t.id);
      [ids[i], ids[j]] = [ids[j], ids[i]]; reorder(ids);
    }));
    $('#testiRows').querySelectorAll('[data-pub]').forEach(cb => cb.addEventListener('change', async () => {
      const t = testi.find(x => x.id === Number(cb.dataset.pub));
      try { await post('admin/testimonial/save', { ...t, published: cb.checked }); loadTesti(); } catch (e) { cb.checked = !cb.checked; fail(e); }
    }));
    $('#testiRows').querySelectorAll('[data-edit]').forEach(b => b.addEventListener('click', () => editTesti(testi.find(t => t.id === Number(b.dataset.edit)))));
  }
  function editTesti(t) {
    t = t || { id: 0, client_name: '', company: '', role: '', quote: '', rating: '', photo: '', source: 'manual', published: 0 };
    const ed = $('#testiEditor'); ed.hidden = false;
    ed.innerHTML = `<div class="card-head"><h2>${t.id ? 'Fikrni tahrirlash' : 'Yangi fikr'}</h2><button type="button" class="btn ghost" id="teClose">Yopish</button></div>
      <p class="hint">Faqat haqiqiy mijoz fikrini, uning roziligi bilan kiriting. Google/Yandex’dagi sharhni ko‘chirsangiz, manbani belgilang.</p>
      <div class="grid3 tight"><label>Mijoz ismi *<input id="teName" maxlength="120" value="${esc(t.client_name)}"></label><label>Lavozim<input id="teRole" maxlength="120" value="${esc(t.role)}"></label><label>Kompaniya<input id="teComp" maxlength="160" value="${esc(t.company)}"></label></div>
      <label for="teQuote">Fikr matni *</label><textarea id="teQuote" rows="4" maxlength="1500">${esc(t.quote)}</textarea>
      <div class="grid3 tight"><label>Baho (ixtiyoriy)<select id="teRating"><option value="">—</option>${[5, 4, 3, 2, 1].map(v => `<option${Number(t.rating) === v ? ' selected' : ''}>${v}</option>`).join('')}</select></label>
        <label>Manba<select id="teSrc">${['manual', 'google', 'yandex'].map(v => `<option${t.source === v ? ' selected' : ''}>${v}</option>`).join('')}</select></label>
        <label class="check"><input type="checkbox" id="tePub"${Number(t.published) ? ' checked' : ''}> Nashr qilish</label></div>
      <label>Surat (ixtiyoriy)</label><div class="cover-row"><div class="preview mini round">${t.photo ? `<img src="${esc(t.photo)}" alt="">` : '<span class="muted small">yo‘q</span>'}</div><input type="file" id="teFile" accept="image/png,image/jpeg,image/webp"><button type="button" class="btn ghost" id="teUp">Yuklash</button></div>
      <div class="row"><span class="spacer"></span>${t.id ? '<button type="button" class="btn ghost danger" id="teDel">O‘chirish</button>' : ''}<button type="button" class="btn primary" id="teSave">Saqlash</button></div>`;
    let photo = t.photo;
    ed.scrollIntoView({ behavior: 'smooth' });
    $('#teClose').addEventListener('click', () => { ed.hidden = true; });
    $('#teUp').addEventListener('click', async () => { const u = await uploadMedia($('#teFile').files[0]); if (u) { photo = u; ed.querySelector('.preview').innerHTML = `<img src="${esc(u)}" alt="">`; } });
    $('#teSave').addEventListener('click', async () => {
      try { await post('admin/testimonial/save', { id: t.id, client_name: $('#teName').value, role: $('#teRole').value, company: $('#teComp').value, quote: $('#teQuote').value,
        rating: $('#teRating').value, source: $('#teSrc').value, published: $('#tePub').checked, photo }); toast('Saqlandi'); ed.hidden = true; loadTesti(); } catch (e) { fail(e); }
    });
    if ($('#teDel')) $('#teDel').addEventListener('click', async () => { if (!confirm('Fikr o‘chirilsinmi?')) return; try { await post('admin/testimonial/delete', { id: t.id }); ed.hidden = true; loadTesti(); } catch (e) { fail(e); } });
  }
  $('#testiNew').addEventListener('click', () => editTesti(null));

  Object.assign(window.DARIKO_ADMIN_TABS, { blog: loadPosts, cases: loadCases, testimonials: loadTesti });
})();
