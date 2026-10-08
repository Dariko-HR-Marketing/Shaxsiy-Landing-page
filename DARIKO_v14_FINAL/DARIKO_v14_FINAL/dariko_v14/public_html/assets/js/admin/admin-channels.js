/* DARIKO admin panel — Kanallar sozlamalari va holat tekshiruvi (v12: admin-ext.js dan ajratildi). Bog'liqlik: admin.js, admin-core.js. */
(() => {
  'use strict';
  const { A, $, esc, toast, get, post, dt, day, SRC, ST, srcTag, stTag, fail, uploadMedia, addTimer, shared } = window.DARIKO_ADMIN_EXT;
  /* ============================== Kanallar ============================== */
  function chField(f, key, label, help, opts = {}) {
    const v = f[key] || { set: false, value: '' };
    const type = opts.type || (v.secret ? 'password' : 'text');
    return `<div class="setting ch" data-key="${key}"><label for="ch-${key}">${esc(label)} ${v.set ? '<span class="tag ok">saqlangan</span>' : '<span class="tag warn">yo‘q</span>'}</label>
      <input id="ch-${key}" type="${type}" autocomplete="off" spellcheck="false" ${v.secret ? `placeholder="${esc(v.set ? v.value + ' (yangisini kiriting)' : '')}" value=""` : `value="${esc(v.value)}"`}>
      <button type="button" class="btn primary">Saqlash</button>${opts.gen ? '<button type="button" class="btn ghost" data-gen>Tasodifiy</button>' : ''}${help ? `<p class="hint">${help}</p>` : ''}</div>`;
  }
  const copyRow = (label, url) => `<div class="copy-row"><span class="small"><b>${esc(label)}</b></span><code class="url">${esc(url)}</code><button type="button" class="btn ghost sm" data-copy="${esc(url)}">Nusxa</button></div>`;
  async function loadChannels() {
    let d; try { d = await get('admin/channels'); } catch (e) { return fail(e); }
    const f = d.fields;
    const siteWarn = d.site_url_configured ? '' : `<p class="error">DARIKO_SITE_URL sozlanmagan, bu yerda ko‘rsatilgan manzil ishonchli emas: standart <code>${esc(d.site_url)}</code> ishlatilmoqda (so‘rovdagi Host sarlavhasiga xavfsizlik uchun ishonilmaydi). Sayt boshqa domenda bo‘lsa, hostingda <code>DARIKO_SITE_URL=https://sizning-domen</code> env o‘zgaruvchisini qo‘ying (ISHGA_TUSHIRISH_UZ.md).</p>`;
    $('#chTelegram').innerHTML = `${siteWarn}${d.curl ? '' : '<p class="error">Serverda cURL kengaytmasi yo‘q — tashqi API chaqiruvlari ishlamaydi.</p>'}
      ${chField(f, 'tg_bot_token', 'Bot token', 'Telegram’da <b>@BotFather</b> → <code>/newbot</code> → berilgan <code>123456:ABC…</code> tokenni shu yerga qo‘ying.')}
      <div class="row"><button type="button" class="btn primary" id="tgSetup">Webhookni o‘rnatish</button><span class="small">${d.tg_bot_username ? 'Bot: <b>@' + esc(d.tg_bot_username) + '</b> · webhook: ' + esc(dt(d.tg_webhook_set_at)) : '<span class="muted">Webhook hali o‘rnatilmagan</span>'}</span></div>
      <p class="hint">Tugma tokenni tekshiradi, maxfiy webhook kalitini avtomatik yaratadi va Telegram’ga ushbu manzilni ro‘yxatdan o‘tkazadi (sayt HTTPS’da ishlashi shart):</p>${copyRow('Webhook URL', d.telegram_webhook_url)}
      <hr><h3 class="h3">Admin xabarnomalari</h3>
      <p class="hint">Yangi murojaat, bron va server muammolari haqida xabarlar sizning shaxsiy Telegram chatingizga keladi.</p>
      <div class="row"><button type="button" class="btn ghost" id="tgLink">Admin chatni ulash</button><button type="button" class="btn ghost" id="tgTest">Test xabar</button><span class="small">${f.tg_admin_chat_id.set ? 'Ulangan chat ID: <code>' + esc(f.tg_admin_chat_id.value) + '</code>' : '<span class="muted">ulanmagan</span>'}</span></div>
      <p id="tgLinkInfo" class="hint" hidden></p>`;
    $('#chMeta').innerHTML = `${siteWarn}<p class="hint warn-text">Meta ulanishi uchun Meta Developer ilovasi, Facebook sahifasi (va unga ulangan Instagram professional akkaunt), biznes verifikatsiyasi va <b>App Review</b> kerak — bu Meta tomonida bir necha kundan bir necha haftagacha davom etishi mumkin. Bosqichlar: <b>CRM_QOLLANMA_UZ.md</b>, 2-bo‘lim.</p>
      ${copyRow('Callback URL', d.meta_webhook_url)}
      ${chField(f, 'meta_verify_token', 'Verify token', 'O‘zingiz o‘ylab topadigan maxfiy so‘z (12+ belgi). Meta Developer → Webhooks → «Verify token» maydoniga AYNAN shuni yozasiz.', { gen: true })}
      ${chField(f, 'meta_app_secret', 'App Secret', 'Meta Developer → ilovangiz → App settings → Basic → «App secret» (32 belgili). Kiruvchi xabarlar imzosini tekshirish uchun.')}
      ${chField(f, 'meta_page_token', 'Page access token', 'Muddatsiz (long-lived) sahifa tokeni. Javob yuborish uchun. Qanday olinishi — qo‘llanmada.')}`;
    $('#chMail').innerHTML = `${d.smtp_plaintext_auth ? '<p class="error">⚠️ «Shifrlash» = <code>none</code>, lekin login/parol kiritilgan: parol tarmoqda ochiq matnda ketardi, shuning uchun xat YUBORILMAYDI. <code>tls</code> (port 587) yoki <code>ssl</code> (port 465) tanlang.</p>' : ''}<p class="hint">SMTP bo‘sh qolsa, hostingning PHP <code>mail()</code> funksiyasi ishlatiladi (ko‘p hostinglarda spamga tushadi). Tavsiya: hosting pochtasi yoki domen pochtasi SMTP.</p>
      ${chField(f, 'smtp_host', 'SMTP server', 'Masalan: <code>mail.dariko.uz</code> yoki <code>smtp.gmail.com</code>')}
      <div class="grid2 tight">${chField(f, 'smtp_port', 'Port', '587 (TLS) yoki 465 (SSL)')}${chField(f, 'smtp_secure', 'Shifrlash', '<code>tls</code>, <code>ssl</code> yoki <code>none</code>')}</div>
      ${chField(f, 'smtp_user', 'Login', '')}${chField(f, 'smtp_pass', 'Parol', 'Gmail uchun oddiy parol emas — «App password» kerak.')}
      ${chField(f, 'smtp_from', 'Jo‘natuvchi email', 'Odatda login bilan bir xil.')}${chField(f, 'smtp_from_name', 'Jo‘natuvchi nomi', '')}
      ${chField(f, 'notify_email', 'Xabarnoma emaili', 'Telegram admin chat ulanmagan bo‘lsa, yangi murojaatlar shu emailga yuboriladi.')}
      <div class="row"><input id="mailTestTo" type="email" placeholder="test@example.com" aria-label="Test xat manzili"><button type="button" class="btn ghost" id="mailTest">Test xat yuborish</button></div>`;
    document.querySelectorAll('#tab-channels .setting.ch').forEach(el => {
      el.querySelector('.btn.primary').addEventListener('click', async () => {
        const inp = el.querySelector('input');
        if (inp.type === 'password' && !inp.value && !confirm('Maydon bo‘sh — saqlangan qiymat o‘chirilsinmi?')) return;
        try { await post('admin/channels/save', { key: el.dataset.key, value: inp.value.trim() }); toast('Saqlandi'); loadChannels(); } catch (e) { fail(e); }
      });
      const g = el.querySelector('[data-gen]');
      if (g) g.addEventListener('click', () => { const b = new Uint8Array(18); crypto.getRandomValues(b); const inp = el.querySelector('input'); inp.type = 'text'; inp.value = [...b].map(x => x.toString(16).padStart(2, '0')).join(''); });
    });
    document.querySelectorAll('#tab-channels [data-copy]').forEach(b => b.addEventListener('click', async () => { try { await navigator.clipboard.writeText(b.dataset.copy); toast('Nusxalandi'); } catch (e) { toast('Nusxalab bo‘lmadi — qo‘lda belgilang', true); } }));
    $('#tgSetup').addEventListener('click', async () => { try { const r = await post('admin/channels/telegram/setup'); toast('Webhook o‘rnatildi: @' + r.bot_username); loadChannels(); } catch (e) { fail(e); } });
    $('#tgLink').addEventListener('click', async () => {
      try { const r = await post('admin/channels/telegram/link'); const info = $('#tgLinkInfo'); info.hidden = false;
        info.innerHTML = `Telegram’da ${r.bot_username ? '<b>@' + esc(r.bot_username) + '</b>' : 'botingiz'}ga quyidagi xabarni yuboring (15 daqiqa amal qiladi): <code>/admin ${esc(r.code)}</code>. So‘ng bu sahifani yangilang.`; } catch (e) { fail(e); }
    });
    $('#tgTest').addEventListener('click', async () => { try { await post('admin/channels/telegram/test'); toast('Test xabar yuborildi'); } catch (e) { fail(e); } });
    $('#mailTest').addEventListener('click', async () => { try { await post('admin/mail/test', { to: $('#mailTestTo').value }); toast('Test xat yuborildi'); } catch (e) { fail(e); } });
    try {
      const s = await get('admin/security'); const h = s.checks.health;
      $('#chHealth').innerHTML = `<p class="hint">Server ichidagi tekshiruv skripti <code>storage/healthcheck.php</code> cron orqali (masalan har 10 daqiqada) ishga tushiriladi: SQLite, papka ruxsatlari, disk joyi va saytning o‘z URL’i. Muammo bo‘lsa — Telegram admin chat va/yoki xabarnoma emailiga ogohlantirish.</p>
        ${h ? `<p>Oxirgi tekshiruv: <b>${esc(dt(h.checked_at))}</b> — ${h.ok ? '<span class="tag ok">Hammasi OK</span>' : '<span class="tag bad">Muammo: ' + esc((h.failed || []).join(', ')) + '</span>'}</p>` : '<p class="tag warn">Tekshiruv hali ishga tushirilmagan (cron sozlanmagan).</p>'}
        <p class="hint">Cron: <code>0,10,20,30,40,50 * * * * php /yo‘l/storage/healthcheck.php --url=https://dariko.uz/</code></p>
        <p class="hint">⚠️ Bu ichki tekshiruv: server butunlay o‘chsa, xabar yuborolmaydi. Tashqi kuzatuv uchun bepul <b>UptimeRobot</b> (yoki shunga o‘xshash) xizmatida saytingiz URL’ini qo‘shing.</p>`;
    } catch (e) { /* ixtiyoriy */ }
  }

  Object.assign(window.DARIKO_ADMIN_TABS, { channels: loadChannels });
})();
