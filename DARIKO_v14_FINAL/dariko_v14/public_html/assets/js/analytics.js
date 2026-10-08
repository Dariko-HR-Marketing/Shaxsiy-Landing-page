/* DARIKO — anonim, cookie'siz tashriflar hisoblagichi.
   Cookie yozmaydi; faqat shu brauzer oynasi uchun sessionStorage'da tasodifiy ID saqlanadi.
   "Do Not Track" / "Global Privacy Control" yoqilgan bo'lsa hech narsa yubormaydi (A/B test ham qo'llanmaydi —
   bunday tashrifchilar doim asl matnni ko'radi).
   v7: A/B testlar — server pv javobida variantni qaytaradi (sessiya ID bo'yicha deterministik, "yopishqoq");
   konversiya: window.DARIKO_CONVERSION('lead' | 'cta_click'). */
(function () {
  'use strict';
  window.DARIKO_CONVERSION = function () {};
  try {
    if (navigator.doNotTrack === '1' || window.doNotTrack === '1' || navigator.globalPrivacyControl === true) return;
    var ENDPOINT = '/api.php?route=track';
    var sid;
    try { sid = sessionStorage.getItem('dariko_sid'); } catch (e) { sid = null; }
    if (!sid || !/^[a-f0-9]{32}$/.test(sid)) {
      var b = new Uint8Array(16); crypto.getRandomValues(b);
      sid = Array.prototype.map.call(b, function (x) { return ('0' + x.toString(16)).slice(-2); }).join('');
      try { sessionStorage.setItem('dariko_sid', sid); } catch (e) {}
    }
    var p = location.pathname;
    var path = /^\/blog(\/|\.php|$)/.test(p) ? '/blog' : /^\/case-studies(\/|\.php|$)/.test(p) ? '/case-studies' : (p.replace(/^.*\//, '/') || '/');
    function lang() { var l = (document.documentElement.lang || '').slice(0, 2); return l === 'ru' ? 'ru' : 'uz'; }
    function post(obj) {
      return fetch(ENDPOINT, { method: 'POST', body: JSON.stringify(obj), keepalive: true, credentials: 'omit',
        headers: { 'Content-Type': 'application/json' } });
    }
    function send(type) { post({ t: type, sid: sid, p: path, l: lang(), r: type === 'pv' ? document.referrer : '' }).catch(function () {}); }

    /* ---- A/B ---- */
    var SLOTS = { hero_title_accent: '#heroTitle [data-cms-text="t015"]', hero_title_rest: '#heroTitle [data-cms-text="t016"]', hero_cta: '[data-cms-text="t023"]' };
    var ab = [];
    function applyAb() {
      ab.forEach(function (x) {
        var text = lang() === 'ru' && x.ru ? x.ru : x.uz;
        if (!text) return; // A (nazorat) = asl matn
        var el = document.querySelector(SLOTS[x.slot] || '#none');
        if (el) el.textContent = text;
      });
    }
    document.addEventListener('dariko:render', applyAb);
    var converted = {};
    window.DARIKO_CONVERSION = function (goal) {
      if ((goal !== 'lead' && goal !== 'cta_click') || converted[goal]) return;
      converted[goal] = true;
      post({ t: 'cv', sid: sid, p: path, g: goal }).catch(function () {});
    };
    post({ t: 'pv', sid: sid, p: path, l: lang(), r: document.referrer })
      .then(function (r) { return r.json(); })
      .then(function (d) { if (d && Array.isArray(d.ab)) { ab = d.ab; applyAb(); } })
      .catch(function () {});
    // Asosiy CTA ("Konsultatsiyaga yozilish") bosilishi — cta_click maqsadi uchun.
    document.addEventListener('click', function (e) {
      var t = e.target && e.target.closest ? e.target.closest('button,a') : null;
      if (t && t.querySelector && t.querySelector('[data-cms-text="t023"],[data-cms-text="t007"]')) window.DARIKO_CONVERSION('cta_click');
    }, true);

    var timer = null;
    function start() { if (!timer) timer = setInterval(function () { send('hb'); }, 45000); }
    function stop() { if (timer) { clearInterval(timer); timer = null; } }
    document.addEventListener('visibilitychange', function () {
      if (document.visibilityState === 'visible') { send('hb'); start(); } else { stop(); }
    });
    if (document.visibilityState === 'visible') start();
  } catch (e) { /* analitika hech qachon saytni buzmasligi kerak */ }
})();
