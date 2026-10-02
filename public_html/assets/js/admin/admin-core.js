/* DARIKO admin panel — v12: admin-ext.js (v7–v10 bo'limlari) mantiqiy fayllarga bo'lindi (assets/js/admin/).
   Bu fayl — umumiy yordamchilar va bo'limlar registri. Yuklash tartibi (admin.html, hammasi defer):
   admin.js -> admin-core.js -> admin-crm.js -> admin-booking.js -> admin-content.js -> admin-marketing.js -> admin-channels.js.
   Qoidalar admin.js bilan bir xil: serverdan kelgan har qanday matn faqat esc() orqali HTML'ga; inline style/script yo'q. */
(() => {
  'use strict';
  const A = () => window.DARIKO_ADMIN;
  const $ = s => document.querySelector(s);
  const esc = x => A().esc(x);
  const toast = (m, e) => A().toast(m, e);
  const get = r => A().api(r);
  const post = (r, b) => A().api(r, { method: 'POST', body: JSON.stringify(b || {}) });
  const dt = ts => ts ? new Date(ts * 1000).toLocaleString('uz-UZ', { dateStyle: 'short', timeStyle: 'short' }) : '–';
  const day = ts => ts ? new Date(ts * 1000).toISOString().slice(0, 10) : '';
  const SRC = { web_form: ['Sayt', 'src-web'], booking: ['Bron', 'src-book'], telegram: ['Telegram', 'src-tg'], instagram: ['Instagram', 'src-ig'], facebook: ['Facebook', 'src-fb'], manual: ['Qo‘lda', 'src-man'] };
  const ST = { yangi: ['Yangi', 'warn'], aloqada: ['Aloqada', ''], muvaffaqiyatli: ['Muvaffaqiyatli', 'ok'], yopilgan: ['Yopilgan', 'muted-tag'] };
  const srcTag = s => `<span class="src ${esc((SRC[s] || ['', ''])[1])}">${esc((SRC[s] || [s])[0])}</span>`;
  const stTag = s => `<span class="tag ${esc((ST[s] || ['', ''])[1])}">${esc((ST[s] || [s])[0])}</span>`;
  let timers = [];
  window.DARIKO_ADMIN_STOP = () => { timers.forEach(clearInterval); timers = []; };
  const fail = e => toast('Xato: ' + e.message, true);
  async function uploadMedia(file) {
    if (!file) { toast('Rasm tanlang', true); return null; }
    if (file.size > 5e6) { toast('Rasm 5 MB dan oshmasin', true); return null; }
    try { const r = await A().api('admin/media/upload', { method: 'POST', headers: { 'Content-Type': 'application/octet-stream' }, body: file }); toast('Rasm yuklandi'); return r.url; }
    catch (e) { fail(e); return null; }
  }

  // Bo'limlar o'zini shu registrga qo'shadi (admin.js tab ochilganda DARIKO_ADMIN_TABS[name]() ni chaqiradi).
  window.DARIKO_ADMIN_TABS = window.DARIKO_ADMIN_TABS || {};
  // Bo'limlararo umumiy holat: bron kalendari -> CRM'da murojaatni ochish.
  const shared = { openLeadId: 0 };
  window.DARIKO_ADMIN_EXT = { A, $, esc, toast, get, post, dt, day, SRC, ST, srcTag, stTag, fail, uploadMedia, addTimer: t => timers.push(t), shared };
  // Boshqa bo'limda turganda ham "yangi murojaat" nishonini yangilab turish (1 daqiqada).
  setInterval(() => { if (!document.hidden && !$('#app').hidden && $('#tab-crm').hidden) get('admin/leads').then(d => { const c = (d.counts || []).find(x => x.status === 'yangi'); const b = $('#crmBadge'); b.hidden = !c; b.textContent = c ? c.n : ''; }).catch(() => {}); }, 60000);
})();
