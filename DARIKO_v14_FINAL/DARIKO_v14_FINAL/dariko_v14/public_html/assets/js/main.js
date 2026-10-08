/* DARIKO — bosh sahifa asosiy skripti (v12: index.html dagi inline <script> va on*="" atributlaridan ko'chirildi,
   CSP script-src'dan 'unsafe-inline' olib tashlanishi uchun). Funksiyalar global qoladi (features.js
   window.openConsultationModal'dan foydalanadi). Tugma/formalar data-action / data-submit atributlari orqali ulanadi. */
function toggleMobileMenu() {
  const menu = document.getElementById('mobileMenu');
  const icon = document.getElementById('menuIcon');
  const opened = menu.classList.toggle('hidden') === false;
  icon.classList.toggle('fa-bars', !opened);
  icon.classList.toggle('fa-xmark', opened);
}
function toggleTgPopup() { document.getElementById('tgPopup')?.classList.toggle('hidden'); }
function selectServiceInForm(serviceName) {
  const select = document.getElementById('formService');
  if (select && Array.from(select.options).some(o => o.value === serviceName)) select.value = serviceName;
}
function openConsultationModal(serviceName) {
  const modal = document.getElementById('consultationModal');
  const select = document.getElementById('modalFormService');
  if (serviceName && select && Array.from(select.options).some(o => o.value === serviceName)) select.value = serviceName;
  modal.classList.remove('hidden'); modal.classList.add('flex');
  document.body.style.overflow = 'hidden';
  modal.querySelector('input')?.focus();
}
function closeConsultationModal() {
  const modal = document.getElementById('consultationModal');
  modal.classList.add('hidden'); modal.classList.remove('flex');
  document.body.style.overflow = '';
}
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeConsultationModal(); });
function getLead(prefix) {
  return {
    name: document.getElementById(prefix+'Name').value.trim(),
    phone: document.getElementById(prefix+'Phone').value.trim(),
    tg: document.getElementById(prefix+'Tg').value.trim(),
    company: document.getElementById(prefix+'Company').value.trim(),
    service: document.getElementById(prefix+'Service').value,
    message: document.getElementById(prefix+'Message').value.trim()
  };
}
// v7: ariza avval serverga (CRM) yuboriladi; server ishlamasa — avvalgidek email ilovasi ochiladi (zaxira).
async function sendLead(e, prefix) {
  e.preventDefault();
  const lead = getLead(prefix);
  const digits = lead.phone.replace(/\D/g, '');
  if (digits.length !== 12 || !digits.startsWith('998')) {
    document.getElementById(prefix+'Phone').setCustomValidity(window.DARIKO_LANG === 'ru' ? 'Введите номер в формате +998 XX XXX XX XX' : 'Raqamni +998 XX XXX XX XX shaklida kiriting');
    document.getElementById(prefix+'Phone').reportValidity(); return;
  }
  const status = document.getElementById(prefix+'Status');
  const btn = e.target.querySelector('button[type="submit"]');
  const ru = window.DARIKO_LANG === 'ru';
  if (btn) btn.disabled = true;
  try {
    const r = await fetch('api.php?route=lead/submit', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ ...lead, website: (document.getElementById(prefix+'Website') || {}).value || '', lang: ru ? 'ru' : 'uz' }) });
    const d = await r.json().catch(() => ({}));
    if (r.ok && d.ok) {
      status.hidden = false;
      status.textContent = ru ? 'Спасибо! Заявка принята — мы свяжемся с вами в ближайшее время.' : 'Rahmat! Arizangiz qabul qilindi — tez orada siz bilan bog‘lanamiz.';
      e.target.reset(); if (window.DARIKO_CONVERSION) window.DARIKO_CONVERSION('lead');
      return;
    }
    if (r.status === 422 || r.status === 429) { status.hidden = false; status.textContent = d.error || 'Xatolik'; return; }
    throw new Error('server');
  } catch (err) {
    const subject = ru ? 'DARIKO — запрос на консультацию' : 'DARIKO — konsultatsiya so‘rovi';
    const body = `Ism: ${lead.name}\nTelefon: ${lead.phone}\nTelegram: ${lead.tg || '—'}\nKompaniya: ${lead.company || '—'}\nXizmat: ${lead.service}\nXabar: ${lead.message || '—'}`;
    status.hidden = false;
    status.textContent = ru ? 'Откроется почтовая программа. Для отправки заявки нажмите «Отправить». Если почта недоступна, свяжитесь по телефону или в Telegram.' : 'Email ilovangizda xabar ochiladi. Ariza yetib borishi uchun “Yuborish” tugmasini bosing. Email ishlamasa, telefon yoki Telegram orqali bog‘laning.';
    window.location.href = `mailto:${window.DARIKO_EMAIL || 'khikmatulloturaev@gmail.com'}?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(body)}`;
  } finally { if (btn) btn.disabled = false; }
}
function handleContactSubmit(e) { sendLead(e, 'form'); }
function handleModalSubmit(e) { sendLead(e, 'modalForm'); }
document.addEventListener('input', e => { if (e.target?.type === 'tel') e.target.setCustomValidity(''); });

// v12: inline onclick/onsubmit o'rniga hodisa delegatsiyasi (element skriptdan keyin yaratilsa ham ishlaydi).
document.addEventListener('click', e => {
  const el = e.target.closest && e.target.closest('[data-action]');
  if (!el) return;
  switch (el.dataset.action) {
    case 'toggle-menu': toggleMobileMenu(); break;
    case 'toggle-tg': toggleTgPopup(); break;
    case 'open-consult': openConsultationModal(); break;
    case 'close-consult': closeConsultationModal(); break;
    case 'select-service': selectServiceInForm(el.dataset.service || ''); break;
  }
});
document.addEventListener('submit', e => {
  const f = e.target.dataset && e.target.dataset.submit;
  if (f === 'contact') handleContactSubmit(e);
  else if (f === 'modal') handleModalSubmit(e);
});
