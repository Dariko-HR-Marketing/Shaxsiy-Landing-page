/* DARIKO admin panel — CRM · Murojaatlar (v12: admin-ext.js dan ajratildi). Bog'liqlik: admin.js, admin-core.js. */
(() => {
  'use strict';
  const { A, $, esc, toast, get, post, dt, day, SRC, ST, srcTag, stTag, fail, uploadMedia, addTimer, shared } = window.DARIKO_ADMIN_EXT;
  /* ============================== CRM ============================== */
  let currentLead = 0, leadTimer = null;
  async function loadLeads() {
    const qs = new URLSearchParams({ q: $('#leadSearch').value, status: $('#leadStatus').value, source: $('#leadSource').value }).toString();
    try {
      const d = await get('admin/leads?' + qs);
      const c = Object.fromEntries((d.counts || []).map(x => [x.status, x.n]));
      $('#leadCounts').textContent = `Yangi: ${c.yangi || 0} · Aloqada: ${c.aloqada || 0} · Muvaffaqiyatli: ${c.muvaffaqiyatli || 0} · Yopilgan: ${c.yopilgan || 0}`;
      const b = $('#crmBadge'); b.hidden = !c.yangi; b.textContent = c.yangi || '';
      $('#leadRows').innerHTML = d.leads.length ? d.leads.map(l => `<button type="button" class="lead-row${l.id === currentLead ? ' active' : ''}" data-id="${l.id}">
        <span class="lr-top">${srcTag(l.source)}<b>${esc(l.name || l.tg_username || l.phone || 'Nomsiz')}</b>${stTag(l.status)}</span>
        <span class="lr-msg">${l.last_direction === 'out' ? '↩ ' : ''}${esc(l.last_message || l.service_interest || '')}</span>
        <span class="lr-time">${esc(dt(l.updated_at))}</span></button>`).join('') : '<p class="muted">Murojaat topilmadi.</p>';
      $('#leadRows').querySelectorAll('.lead-row').forEach(el => el.addEventListener('click', () => openLead(Number(el.dataset.id))));
    } catch (e) { $('#leadRows').innerHTML = `<p class="error">${esc(e.status === 503 ? 'pdo_sqlite kerak' : e.message)}</p>`; }
  }
  async function openLead(id, keepScroll) {
    currentLead = id;
    document.querySelectorAll('.lead-row').forEach(el => el.classList.toggle('active', Number(el.dataset.id) === id));
    let d; try { d = await get('admin/lead?id=' + id); } catch (e) { return fail(e); }
    const l = d.lead;
    const box = $('#leadDetail');
    const prevDraft = box.querySelector('#replyText') ? box.querySelector('#replyText').value : '';
    const links = [l.phone && `<a href="tel:${esc(l.phone)}">${esc(l.phone)}</a>`, l.email && `<a href="mailto:${esc(l.email)}">${esc(l.email)}</a>`,
      l.tg_username && `<a href="https://t.me/${esc(l.tg_username)}" target="_blank" rel="noopener noreferrer">@${esc(l.tg_username)}</a>`,
      l.phone && `<a href="https://wa.me/${esc(String(l.phone).replace(/\D/g, ''))}" target="_blank" rel="noopener noreferrer">WhatsApp</a>`].filter(Boolean).join(' · ');
    box.innerHTML = `<div class="card-head"><h2>${esc(l.name || 'Nomsiz murojaat')} ${srcTag(l.source)}</h2><span class="muted small">#${l.id} · ${esc(dt(l.created_at))}</span></div>
      <p class="small">${links || '<span class="muted">Kontakt ma’lumoti yo‘q</span>'}</p>
      ${l.service_interest ? `<p class="small"><b>Xizmat:</b> ${esc(l.service_interest)}${l.company ? ' · <b>Kompaniya:</b> ' + esc(l.company) : ''}</p>` : ''}
      ${d.bookings.length ? `<p class="small"><b>Bron:</b> ${d.bookings.map(b => `${esc(b.requested_date)} ${esc(b.requested_time_slot)} (${esc({ pending: 'kutilmoqda', confirmed: 'tasdiqlangan', cancelled: 'bekor' }[b.status])})`).join(', ')}</p>` : ''}
      <div class="lead-edit">
        <label for="leadSt">Holat</label><select id="leadSt">${Object.entries(ST).map(([k, v]) => `<option value="${k}"${k === l.status ? ' selected' : ''}>${esc(v[0])}</option>`).join('')}</select>
        <label for="leadNote">Izoh (faqat admin ko‘radi)</label><textarea id="leadNote" rows="2" maxlength="4000">${esc(l.assigned_note)}</textarea>
        <details><summary class="small">Kontaktni tahrirlash</summary>
          <div class="grid2 tight">${['name:Ism', 'phone:Telefon', 'email:Email', 'company:Kompaniya', 'service_interest:Xizmat'].map(x => { const [k, lab] = x.split(':'); return `<label>${esc(lab)}<input data-lf="${k}" value="${esc(l[k])}"></label>`; }).join('')}</div>
        </details>
        <div class="row"><button type="button" class="btn primary" id="leadSave">Saqlash</button><span class="spacer"></span><button type="button" class="btn ghost danger" id="leadDel">O‘chirish</button></div>
      </div>
      <h3 class="thread-title">Yozishmalar</h3>
      <div class="thread" id="thread">${d.messages.map(m => `<div class="msg ${m.direction}"><div class="bubble">${esc(m.body)}</div><span class="meta">${esc({ web_form: 'Sayt', telegram: 'Telegram', instagram: 'Instagram', facebook: 'Facebook', manual: 'Qayd' }[m.channel] || m.channel)} · ${esc(dt(m.created_at))}</span></div>`).join('') || '<p class="muted">Xabar yo‘q.</p>'}</div>
      ${l.can_reply ? `<div class="reply">${l.reply_window_open ? '' : '<p class="hint warn-text">⚠️ Mijozning oxirgi xabaridan 24 soatdan ko‘p o‘tdi — Meta qoidalariga ko‘ra oddiy javob yuborilmasligi mumkin.</p>'}
          <label for="replyText">${esc(SRC[l.source][0])} orqali javob</label><textarea id="replyText" rows="3" maxlength="3500"></textarea>
          <div class="row"><button type="button" class="btn primary" id="replySend">Yuborish</button><span class="muted small">Xabar mijozga ${esc(SRC[l.source][0])}da boradi.</span></div></div>`
        : `<div class="reply"><label for="replyText">Qayd qo‘shish (qo‘ng‘iroq/uchrashuv natijasi)</label><textarea id="replyText" rows="2" maxlength="3500"></textarea>
          <div class="row"><button type="button" class="btn ghost" id="noteAdd">Tarixga yozish</button><span class="muted small">Bu murojaatga kanal orqali javob berib bo‘lmaydi — telefon/email/WhatsApp orqali bog‘laning.</span></div></div>`}`;
    if (prevDraft && keepScroll) box.querySelector('#replyText').value = prevDraft;
    const th = $('#thread'); th.scrollTop = th.scrollHeight;
    $('#leadSave').addEventListener('click', async () => {
      const body = { id, status: $('#leadSt').value, assigned_note: $('#leadNote').value };
      box.querySelectorAll('[data-lf]').forEach(i => { body[i.dataset.lf] = i.value; });
      try { await post('admin/lead/update', body); toast('Saqlandi'); loadLeads(); } catch (e) { fail(e); }
    });
    $('#leadDel').addEventListener('click', async () => {
      if (!confirm('Murojaat va butun yozishma o‘chirilsinmi? (qaytarib bo‘lmaydi)')) return;
      try { await post('admin/lead/delete', { id }); currentLead = 0; box.innerHTML = '<p class="muted">O‘chirildi.</p>'; loadLeads(); } catch (e) { fail(e); }
    });
    const send = $('#replySend') || $('#noteAdd');
    send.addEventListener('click', async () => {
      const text = $('#replyText').value.trim(); if (!text) return;
      send.disabled = true;
      try { await post(l.can_reply ? 'admin/lead/reply' : 'admin/lead/note', { id, text }); $('#replyText').value = ''; toast(l.can_reply ? 'Yuborildi' : 'Qayd qo‘shildi'); await openLead(id); loadLeads(); }
      catch (e) { fail(e); } finally { send.disabled = false; }
    });
  }
  let leadSearchT = null;
  $('#leadSearch').addEventListener('input', () => { clearTimeout(leadSearchT); leadSearchT = setTimeout(loadLeads, 300); });
  $('#leadStatus').addEventListener('change', loadLeads);
  $('#leadSource').addEventListener('change', loadLeads);
  $('#leadNew').addEventListener('click', () => {
    currentLead = 0;
    $('#leadDetail').innerHTML = `<h2>Yangi murojaat (qo‘lda)</h2><div class="grid2 tight">
      ${['name:Ism *', 'phone:Telefon', 'email:Email', 'company:Kompaniya', 'service:Xizmat'].map(x => { const [k, lab] = x.split(':'); return `<label>${esc(lab)}<input data-nf="${k}"></label>`; }).join('')}</div>
      <label>Izoh / xabar<textarea data-nf="message" rows="3"></textarea></label><div class="row"><button type="button" class="btn primary" id="nlCreate">Qo‘shish</button></div>`;
    $('#nlCreate').addEventListener('click', async () => {
      const b = {}; document.querySelectorAll('[data-nf]').forEach(i => { b[i.dataset.nf] = i.value; });
      try { const r = await post('admin/lead/create', b); await loadLeads(); openLead(r.id); } catch (e) { fail(e); }
    });
  });
  function startCrm() {
    if (shared.openLeadId) { currentLead = shared.openLeadId; shared.openLeadId = 0; } // bron kalendaridan o'tilganda
    loadLeads(); if (currentLead) openLead(currentLead);
    addTimer(setInterval(() => { if (!document.hidden) { loadLeads(); if (currentLead && !(document.activeElement && document.activeElement.id === 'replyText')) openLead(currentLead, true); } }, 20000));
  }

  Object.assign(window.DARIKO_ADMIN_TABS, { crm: startCrm });
})();
