/* DARIKO v7 — bosh sahifa qo'shimchalari: bron kalendari (modal ichida), mijozlar fikrlari, newsletter, WhatsApp.
   Qoidalar: serverdan kelgan har qanday matn faqat textContent orqali qo'yiladi (innerHTML'ga foydalanuvchi matni tushmaydi);
   inline handler yo'q; barcha so'rovlar faqat o'z domenimizga (CSP connect-src 'self'). */
(function () {
  'use strict';
  var $ = function (s, r) { return (r || document).querySelector(s); };
  var lang = function () { return document.documentElement.lang === 'ru' ? 'ru' : 'uz'; };
  var I18N = {
    nav_blog: ['Blog', 'Блог'], nav_cases: ['Keyslar', 'Кейсы'],
    testi_eyebrow: ['MIJOZLAR FIKRI', 'ОТЗЫВЫ КЛИЕНТОВ'], testi_title: ['Biz bilan ishlaganlar nima deydi', 'Что говорят наши клиенты'], testi_more: ['Keyslar va natijalar →', 'Кейсы и результаты →'],
    wa_contact: ['Yoki WhatsApp orqali yozing', 'Или напишите в WhatsApp'],
    tab_booking: ['Vaqtni tanlash', 'Выбрать время'], tab_request: ['Ariza qoldirish', 'Оставить заявку'],
    bk_step1: ['1. Kunni tanlang', '1. Выберите день'], bk_step2: ['2. Vaqtni tanlang', '2. Выберите время'], bk_step3: ['3. Ma’lumotlaringiz', '3. Ваши данные'],
    bk_loading: ['Yuklanmoqda…', 'Загрузка…'], bk_pick_day: ['Avval kunni tanlang.', 'Сначала выберите день.'],
    f_name: ['Ismingiz *', 'Ваше имя *'], f_phone: ['Telefon *', 'Телефон *'], f_service: ['Xizmat', 'Услуга'], f_msg: ['Qisqacha savolingiz', 'Коротко о задаче'],
    bk_submit: ['Vaqtni band qilish', 'Забронировать время'],
    bk_note: ['Vaqt Toshkent vaqti bo‘yicha. Bron tasdiqlangach siz bilan bog‘lanamiz.', 'Время ташкентское. После подтверждения брони мы свяжемся с вами.'],
    nl_eyebrow: ['YANGILIKLAR', 'РАССЫЛКА'], nl_title: ['Foydali maqolalar va amaliy qo‘llanmalar — emailingizga', 'Полезные статьи и практические руководства — на вашу почту'],
    nl_text: ['Oyiga 1–2 marta. Istalgan vaqtda obunani bekor qilishingiz mumkin.', '1–2 раза в месяц. Отписаться можно в любой момент.'],
    nl_name: ['Ismingiz', 'Ваше имя'], nl_name_ph: ['Ismingiz (ixtiyoriy)', 'Имя (необязательно)'], nl_submit: ['Obuna bo‘lish', 'Подписаться']
  };
  var MSG = {
    no_days: ['Yaqin kunlarda bo‘sh vaqt yo‘q. «Ariza qoldirish» orqali yozing — o‘zimiz bog‘lanamiz.', 'В ближайшие дни свободного времени нет. Оставьте заявку — мы свяжемся с вами.'],
    load_err: ['Kalendarni yuklab bo‘lmadi. «Ariza qoldirish» tabidan foydalaning.', 'Не удалось загрузить календарь. Воспользуйтесь вкладкой «Оставить заявку».'],
    file_err: ['Sahifa fayl sifatida (file://) ochilgan — kalendar faqat server (Apache/LiteSpeed yoki php -S) orqali ishlaydi.', 'Страница открыта как файл (file://) — календарь работает только через сервер (Apache/LiteSpeed или php -S).'],
    need_slot: ['Kun va vaqtni tanlang.', 'Выберите день и время.'],
    bad_phone: ['Raqamni +998 XX XXX XX XX shaklida kiriting', 'Введите номер в формате +998 XX XXX XX XX'],
    need_name: ['Ismingizni kiriting', 'Введите имя'],
    booked: ['Rahmat! {d}, soat {t} ga bron so‘rovingiz qabul qilindi. Tasdiqlash uchun siz bilan bog‘lanamiz.', 'Спасибо! Запрос на {d} в {t} принят. Мы свяжемся с вами для подтверждения.'],
    nl_ok: ['Rahmat! Obunani tasdiqlash uchun emailingizga havola yubordik.', 'Спасибо! Мы отправили ссылку для подтверждения на вашу почту.'],
    nl_bad: ['Email manzilni to‘g‘ri kiriting.', 'Введите корректный email.'],
    err: ['Xatolik yuz berdi. Birozdan keyin qayta urinib ko‘ring.', 'Произошла ошибка. Попробуйте позже.'],
    wa_text: ['Assalomu alaykum! DARIKO konsultatsiyasi bo‘yicha yozyapman.', 'Здравствуйте! Пишу по поводу консультации DARIKO.']
  };
  var t = function (k) { return (MSG[k] || I18N[k] || ['', ''])[lang() === 'ru' ? 1 : 0]; };
  var settings = {};

  function applyI18n() {
    var i = lang() === 'ru' ? 1 : 0;
    document.querySelectorAll('[data-v7-i18n]').forEach(function (el) { var v = I18N[el.getAttribute('data-v7-i18n')]; if (v) el.textContent = v[i]; });
    document.querySelectorAll('[data-v7-ph]').forEach(function (el) { var v = I18N[el.getAttribute('data-v7-ph')]; if (v) el.setAttribute('placeholder', v[i]); });
    var phone = String(settings.phone || '+998914053508').replace(/\D/g, '');
    document.querySelectorAll('a[data-wa]').forEach(function (a) { a.href = 'https://wa.me/' + phone + '?text=' + encodeURIComponent(t('wa_text')); });
    if (days) renderDays();
    renderTestimonials();
  }
  document.addEventListener('dariko:render', function (e) { settings = (e.detail && e.detail.settings) || settings; applyI18n(); });

  // v8: API manzili skript joylashuvidan hisoblanadi (assets/js/ -> ../../api.php),
  // shunda sayt sub-papkada o'rnatilsa ham ishlaydi (avval qattiq '/api.php' edi).
  var API_BASE = (function () {
    try { var src = (document.currentScript && document.currentScript.src) || ''; if (src) return new URL('../../api.php', src).href; } catch (e) {}
    return 'api.php';
  })();
  var IS_FILE = location.protocol === 'file:';
  async function api(route, body) {
    if (IS_FILE) { var fe = new Error('file'); fe.file = true; throw fe; }
    var r = await fetch(API_BASE + '?route=' + route, body ? { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) } : { credentials: 'same-origin' });
    var d = {}; try { d = await r.json(); } catch (e) {}
    if (!r.ok) { var er = new Error(d.error || 'HTTP ' + r.status); er.status = r.status; throw er; }
    return d;
  }
  function status(el, text, ok) { el.hidden = false; el.textContent = text; el.classList.toggle('err', !ok); }

  /* ---------------- modal tablari ---------------- */
  var tabB = $('#tabBooking'), tabR = $('#tabRequest'), panelB = $('#bookingPanel'), panelR = $('#modalRequestForm');
  function selectTab(which) {
    if (!tabB) return;
    var b = which === 'booking';
    tabB.setAttribute('aria-selected', String(b)); tabR.setAttribute('aria-selected', String(!b));
    panelB.hidden = !b; panelR.hidden = b;
    if (b) loadDays();
  }
  if (tabB) {
    tabB.addEventListener('click', function () { selectTab('booking'); });
    tabR.addEventListener('click', function () { selectTab('request'); });
    [tabB, tabR].forEach(function (tb) {
      tb.addEventListener('keydown', function (e) { if (e.key === 'ArrowRight' || e.key === 'ArrowLeft') { e.preventDefault(); var o = tb === tabB ? tabR : tabB; o.focus(); o.click(); } });
    });
  }
  // Mavjud openConsultationModal() funksiyasi saqlanadi; modal ochilganda bron kalendari yuklanadi.
  var modal = $('#consultationModal');
  if (modal && window.MutationObserver) {
    new MutationObserver(function () { if (!modal.classList.contains('hidden') && tabB && tabB.getAttribute('aria-selected') === 'true') loadDays(); })
      .observe(modal, { attributes: true, attributeFilter: ['class'] });
  }
  function openBooking() { if (typeof window.openConsultationModal === 'function') { window.openConsultationModal(); selectTab('booking'); } }
  document.addEventListener('click', function (e) {
    var a = e.target.closest && e.target.closest('a[href="#booking"],a[href="/#booking"]');
    if (a) { e.preventDefault(); openBooking(); }
  });
  if (location.hash === '#booking') setTimeout(openBooking, 300);
  window.addEventListener('hashchange', function () { if (location.hash === '#booking') openBooking(); });

  /* ---------------- bron kalendari ---------------- */
  var days = null, selDay = null, selTime = null, loading = false, loadedAt = 0;
  var fmtDay = function (iso) {
    var d = new Date(iso + 'T12:00:00');
    var wd = lang() === 'ru' ? ['Вс', 'Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб'] : ['Ya', 'Du', 'Se', 'Ch', 'Pa', 'Ju', 'Sh'];
    var mo = lang() === 'ru' ? ['янв', 'фев', 'мар', 'апр', 'мая', 'июн', 'июл', 'авг', 'сен', 'окт', 'ноя', 'дек'] : ['yan', 'fev', 'mar', 'apr', 'may', 'iyn', 'iyl', 'avg', 'sen', 'okt', 'noy', 'dek'];
    return { wd: wd[d.getDay()], d: d.getDate(), m: mo[d.getMonth()] };
  };
  async function loadDays(force) {
    if (!panelB || loading || (!force && days && Date.now() - loadedAt < 60000)) return;
    loading = true;
    try { var d = await api('booking/availability'); days = d.days || []; loadedAt = Date.now(); renderDays(); }
    catch (e) { showDaysMsg(t(e && e.file ? 'file_err' : 'load_err'), true); }
    finally { loading = false; }
  }
  function showDaysMsg(text, isErr) {
    var box = $('#bkDays'); if (!box) return;
    box.textContent = ''; box.classList.add('is-msg');
    var p = document.createElement('p'); p.className = isErr ? 'v7-days-msg err' : 'v7-days-msg'; p.textContent = text;
    box.append(p);
  }
  function renderDays() {
    var box = $('#bkDays'); if (!box || !days) return;
    box.textContent = ''; box.classList.remove('is-msg');
    if (!days.length) { showDaysMsg(t('no_days'), false); $('#bkSlots').textContent = ''; return; }
    if (!days.some(function (x) { return x.date === selDay; })) { selDay = null; selTime = null; }
    days.forEach(function (x) {
      var f = fmtDay(x.date), b = document.createElement('button');
      b.type = 'button'; b.className = 'v7-day'; b.setAttribute('aria-pressed', String(x.date === selDay));
      b.setAttribute('aria-label', x.date + ' (' + x.slots.length + ')');
      var s1 = document.createElement('small'); s1.textContent = f.wd;
      var s2 = document.createElement('b'); s2.textContent = f.d;
      var s3 = document.createElement('small'); s3.textContent = f.m;
      b.append(s1, s2, s3);
      b.addEventListener('click', function () { selDay = x.date; selTime = null; renderDays(); });
      box.append(b);
    });
    renderSlots();
  }
  function renderSlots() {
    var box = $('#bkSlots'); box.textContent = '';
    var day = (days || []).find(function (x) { return x.date === selDay; });
    if (!day) { var sp = document.createElement('span'); sp.className = 'v7-muted'; sp.textContent = t('bk_pick_day'); box.append(sp); }
    else day.slots.forEach(function (s) {
      var b = document.createElement('button'); b.type = 'button'; b.className = 'v7-slot'; b.textContent = s;
      b.setAttribute('aria-pressed', String(s === selTime));
      b.addEventListener('click', function () { selTime = s; renderSlots(); });
      box.append(b);
    });
    $('#bkSubmit').disabled = !(selDay && selTime);
  }
  var svc = $('#bkService'), src = $('#modalFormService');
  if (svc && src) {
    // Xizmatlar ro'yxati mavjud modal formasidan olinadi (bitta manba).
    Array.prototype.forEach.call(src.options, function (o) { var n = document.createElement('option'); n.value = o.value; n.textContent = o.textContent; svc.append(n); });
    new MutationObserver(function () { Array.prototype.forEach.call(src.options, function (o, i) { if (svc.options[i]) svc.options[i].textContent = o.textContent; }); })
      .observe(src, { subtree: true, characterData: true, childList: true });
  }
  var bkForm = $('#bookingForm');
  if (bkForm) bkForm.addEventListener('submit', async function (e) {
    e.preventDefault();
    var st = $('#bkStatus'), btn = $('#bkSubmit');
    var name = $('#bkName').value.trim(), phone = $('#bkPhone').value.trim(), digits = phone.replace(/\D/g, '');
    if (!selDay || !selTime) return status(st, t('need_slot'), false);
    if (name.length < 2) { $('#bkName').focus(); return status(st, t('need_name'), false); }
    if (digits.length !== 12 || digits.indexOf('998') !== 0) { $('#bkPhone').focus(); return status(st, t('bad_phone'), false); }
    btn.disabled = true;
    try {
      await api('booking/create', { name: name, phone: phone, service: svc ? svc.value : '', message: $('#bkMessage').value.trim(),
        date: selDay, time: selTime, website: $('#bkWebsite').value, lang: lang() });
      status(st, t('booked').replace('{d}', selDay).replace('{t}', selTime), true);
      bkForm.reset(); selDay = null; selTime = null;
      if (window.DARIKO_CONVERSION) window.DARIKO_CONVERSION('lead');
      loadDays(true);
    } catch (err) {
      status(st, err.message || t('err'), false);
      if (err.status === 409) { selTime = null; loadDays(true); }
    } finally { btn.disabled = !(selDay && selTime); }
  });

  /* v8: newsletter formasi sahifadan olib tashlandi (backend/admin saqlangan). */

  /* ---------------- mijozlar fikrlari ---------------- */
  var testi = null;
  function renderTestimonials() {
    var sec = $('#testimonials'), box = $('#testiList');
    if (!sec || !testi) return;
    sec.hidden = testi.length === 0;
    box.textContent = '';
    testi.forEach(function (x) {
      var f = document.createElement('figure'); f.className = 'v7-testi';
      if (x.rating) { var s = document.createElement('div'); s.className = 'v7-stars'; s.setAttribute('aria-label', x.rating + ' / 5'); s.textContent = '★★★★★'.slice(0, x.rating); var g = document.createElement('span'); g.textContent = '★★★★★'.slice(x.rating); s.append(g); f.append(s); }
      var q = document.createElement('blockquote'); q.textContent = '“' + x.quote + '”'; f.append(q);
      var c = document.createElement('figcaption');
      if (x.photo && /^uploads\/cms-media-[0-9a-f]{32}\.png$/.test(x.photo)) { var im = document.createElement('img'); im.src = '/' + x.photo; im.alt = ''; im.width = 44; im.height = 44; im.loading = 'lazy'; c.append(im); }
      else { var av = document.createElement('span'); av.className = 'v7-av'; av.textContent = (x.client_name || '?').slice(0, 1); c.append(av); }
      var w = document.createElement('span'), b = document.createElement('b'), sm = document.createElement('small');
      b.textContent = x.client_name; sm.textContent = [x.role, x.company].filter(Boolean).join(', ');
      w.append(b, sm); c.append(w); f.append(c); box.append(f);
    });
  }
  if ($('#testimonials')) api('public/testimonials').then(function (d) { testi = d.testimonials || []; renderTestimonials(); }).catch(function () {});
  applyI18n();
})();
