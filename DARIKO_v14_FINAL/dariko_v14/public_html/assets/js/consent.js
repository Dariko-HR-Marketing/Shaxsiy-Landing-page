/* DARIKO v8 — cookie / tashqi kontent roziligi.
   Audit natijasi: saytning o'zi ommaviy tashrifchiga HECH QANDAY cookie yozmaydi (analitika sessionStorage,
   til tanlovi localStorage; PHP sessiya cookie'si faqat admin kirishida). Yagona cookie manbai — Google Maps
   iframe'i (uchinchi tomon, google.com cookie'lari). Shuning uchun xarita rozilikka qadar YUKLANMAYDI:
   <iframe data-consent-src="..."> — src faqat "Qabul qilish" yoki "Xaritani ko'rsatish" bosilgandan so'ng qo'yiladi.
   Tanlov cookie'da emas, localStorage'da saqlanadi (12 oy), so'ng qayta so'raladi. Tashqi kutubxona yo'q. */
(function () {
  'use strict';
  var KEY = 'dariko_consent_v1', TTL = 365 * 24 * 3600 * 1000;
  var T = {
    title: ['Cookie va maxfiylik', 'Cookie и конфиденциальность'],
    text: ['Saytimiz o‘zi cookie yozmaydi: statistika anonim va cookie’siz. Faqat «Aloqa» bo‘limidagi Google Maps xaritasi Google cookie’larini o‘rnatishi mumkin — u faqat roziligingizdan keyin yuklanadi.',
           'Сам сайт не использует cookie: статистика анонимная и без cookie. Только карта Google Maps в разделе «Контакты» может установить cookie Google — она загружается только после вашего согласия.'],
    more: ['Batafsil', 'Подробнее'],
    accept: ['Qabul qilish', 'Принять'],
    reject: ['Faqat zarurlari', 'Только необходимые'],
    map_text: ['Xarita Google Maps orqali yuklanadi va Google cookie o‘rnatishi mumkin.', 'Карта загружается через Google Maps и может установить cookie Google.'],
    map_show: ['Xaritani ko‘rsatish', 'Показать карту'],
    map_open: ['Google Maps’da ochish', 'Открыть в Google Maps'],
    settings: ['Cookie sozlamalari', 'Настройки cookie']
  };
  var lang = function () { return document.documentElement.lang === 'ru' ? 1 : 0; };
  var t = function (k) { return T[k][lang()]; };

  function read() {
    try {
      var v = JSON.parse(localStorage.getItem(KEY) || 'null');
      if (v && (v.choice === 'all' || v.choice === 'essential') && typeof v.ts === 'number' && Date.now() - v.ts < TTL) return v.choice;
    } catch (e) {}
    return null;
  }
  function save(choice) { try { localStorage.setItem(KEY, JSON.stringify({ choice: choice, ts: Date.now() })); } catch (e) {} }

  /* ---- xarita darvozasi ---- */
  var frames = [].slice.call(document.querySelectorAll('iframe[data-consent-src]'));
  function loadFrame(f) {
    if (f.getAttribute('src')) return;
    f.setAttribute('src', f.getAttribute('data-consent-src'));
    f.hidden = false;
    var ph = f.parentNode && f.parentNode.querySelector('.cc-map-ph'); if (ph) ph.remove();
  }
  function placeholders() {
    frames.forEach(function (f) {
      if (f.getAttribute('src')) return;
      f.hidden = true;
      var ph = f.parentNode.querySelector('.cc-map-ph');
      if (!ph) { ph = document.createElement('div'); ph.className = 'cc-map-ph'; f.parentNode.insertBefore(ph, f); }
      ph.textContent = '';
      var p = document.createElement('p'); p.textContent = t('map_text');
      var b = document.createElement('button'); b.type = 'button'; b.className = 'cc-btn cc-primary'; b.textContent = t('map_show');
      b.addEventListener('click', function () { loadFrame(f); }); // faqat shu safar; saqlanmaydi
      var a = document.createElement('a'); a.className = 'cc-btn cc-ghost'; a.target = '_blank'; a.rel = 'noopener noreferrer';
      var mapHref = function () { return f.getAttribute('data-consent-src').replace('&output=embed', ''); };
      a.href = mapHref(); a.textContent = t('map_open');
      a.addEventListener('click', function () { a.href = mapHref(); }); // cms.js manzilni keyin yangilashi mumkin
      var row = document.createElement('div'); row.className = 'cc-row'; row.append(b, a);
      ph.append(p, row);
    });
  }

  /* ---- banner ---- */
  var banner = null;
  function lift() {
    if (!banner) { document.documentElement.style.removeProperty('--cc-h'); document.body.classList.remove('cc-open'); return; }
    document.documentElement.style.setProperty('--cc-h', banner.offsetHeight + 'px');
    document.body.classList.add('cc-open');
  }
  function closeBanner() { if (banner) { banner.remove(); banner = null; } lift(); }
  function showBanner() {
    if (banner) banner.remove();
    banner = document.createElement('section');
    banner.className = 'cc-banner'; banner.setAttribute('role', 'region'); banner.setAttribute('aria-label', t('title'));
    var h = document.createElement('p'); h.className = 'cc-title'; h.textContent = t('title');
    var p = document.createElement('p'); p.className = 'cc-text'; p.textContent = t('text') + ' ';
    var a = document.createElement('a'); a.href = 'privacy.html#cookies'; a.textContent = t('more'); p.append(a);
    var ok = document.createElement('button'); ok.type = 'button'; ok.className = 'cc-btn cc-primary'; ok.textContent = t('accept');
    var no = document.createElement('button'); no.type = 'button'; no.className = 'cc-btn cc-ghost'; no.textContent = t('reject');
    ok.addEventListener('click', function () { save('all'); frames.forEach(loadFrame); closeBanner(); });
    no.addEventListener('click', function () { save('essential'); closeBanner(); });
    var row = document.createElement('div'); row.className = 'cc-row'; row.append(ok, no);
    banner.append(h, p, row);
    document.body.append(banner);
    lift();
  }

  function apply() {
    var c = read();
    if (c === 'all') frames.forEach(loadFrame); else placeholders();
    if (!c) showBanner();
  }
  window.addEventListener('resize', function () { if (banner) lift(); });
  // Til almashganda matnlarni yangilash (cms.js <html lang> ni o'zgartiradi).
  new MutationObserver(function () { placeholders(); if (banner) showBanner(); [].forEach.call(document.querySelectorAll('[data-cc-settings]'), function (x) { x.textContent = t('settings'); }); })
    .observe(document.documentElement, { attributes: true, attributeFilter: ['lang'] });
  document.addEventListener('click', function (e) {
    var s = e.target.closest && e.target.closest('[data-cc-settings]');
    if (s) { e.preventDefault(); showBanner(); }
  });
  apply();
})();
