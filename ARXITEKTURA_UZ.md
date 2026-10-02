# DARIKO — sayt arxitekturasi (v3)

Ushbu hujjat DARIKO HR & MARKETING saytining texnik tuzilishini tushuntiradi: qaysi fayl nima qiladi, so‘rovlar qanday oqadi, ma’lumotlar qayerda va qanday himoyalangan.

---

## 1. Umumiy sxema

```
                 Internet (brauzer, Googlebot, YandexBot, GPTBot, ClaudeBot ...)
                                   │  HTTPS
                                   ▼
┌─────────────────────── Apache / LiteSpeed + PHP 8.2+ ───────────────────────┐
│                                                                              │
│  public_html/   (DOCUMENT ROOT — internetga ochiq)                           │
│  ├─ index.html, resume.html, privacy.html   ← statik ommaviy sahifalar       │
│  ├─ cms.js                ← sahifaga matn/rasm/rang/aloqani API'dan qo‘yadi   │
│  ├─ assets/js/analytics.js← anonim tashrif hisoblagichi (beacon)            │
│  ├─ robots.txt, sitemap.xml, llms.txt, .well-known/security.txt             │
│  ├─ admin.html/.css/.js   ← boshqaruv paneli (SPA, vanilla JS)               │
│  ├─ api.php               ← YAGONA backend kirish nuqtasi (JSON API)         │
│  ├─ common.php            ← umumiy funksiyalar (to‘g‘ridan-to‘g‘ri ochilmaydi)│
│  ├─ analytics_lib.php     ← SQLite analitika (to‘g‘ridan-to‘g‘ri ochilmaydi) │
│  ├─ setup.php             ← bir martalik admin yaratish (keyin O‘CHIRILADI)  │
│  └─ uploads/              ← admin yuklagan rasmlar (PHP bajarilmaydi)        │
│                                                                              │
│  storage/       (DOCUMENT ROOT'DAN TASHQARIDA — internetdan ko‘rinmaydi)     │
│  ├─ initial_content.json  ← boshlang‘ich kontent (faqat o‘qiladi)            │
│  ├─ content.json          ← joriy kontent (birinchi saqlashda yaratiladi)    │
│  ├─ auth.json             ← admin parol xeshi (bcrypt/argon, 0600)            │
│  ├─ analytics.sqlite      ← tashriflar DB (birinchi beacon'da yaratiladi)    │
│  ├─ analytics_secret.key  ← IP xeshlash uchun maxfiy kalit (0600)            │
│  ├─ audit.log             ← admin hodisalari jurnali (JSON Lines)            │
│  ├─ rate-*.json           ← login urinishlari hisoblagichlari                │
│  └─ cleanup_uploads.php, cleanup_analytics.php  ← faqat CLI/cron skriptlar   │
└──────────────────────────────────────────────────────────────────────────────┘
```

**Nega ikki papka?** `storage/` ichidagi hamma narsa maxfiy (parol xeshi, kontent qoralamalari, statistika, kalit). U veb-server ildizidan tashqarida bo‘lgani uchun hatto `.htaccess` xato sozlansa ham URL orqali yuklab olinmaydi. `public_html/` da faqat brauzerga berilishi kerak bo‘lgan fayllar turadi. Qo‘shimcha himoya sifatida `storage/.htaccess` ham `Require all denied`.

**Ma’lumotlar bazasi:** kontent — JSON fayllarda (`flock()` bilan qulflanadi). Analitika — SQLite (`pdo_sqlite`), chunki tashriflar ko‘p, tez-tez yoziladi va guruhlash/hisoblash so‘rovlari kerak. Alohida MySQL server talab qilinmaydi.

---

## 2. So‘rovlar oqimi

### 2.1. Sahifa ko‘rish
1. Brauzer `GET /` → Apache `index.html` ni beradi (+ `.htaccess` xavfsizlik sarlavhalari).
2. HTML ichida `cms-bootstrap` JSON bor — sahifa JS'siz ham to‘liq matn bilan ko‘rinadi (SEO uchun muhim: botlar matnni darhol ko‘radi).
3. `cms.js` → `GET api.php?route=public` → joriy matn/rasm/rang/aloqa → `textContent` orqali sahifaga qo‘yiladi (HTML injeksiya yo‘q).
4. `analytics.js` → `POST api.php?route=track` `{t:"pv"}` (pastda 2.5).

### 2.2. Admin kirishi (login)
1. `admin.html` → `GET api.php?route=me` → sessiya holati.
2. `POST api.php?route=login {password}`:
   - so‘rov hajmi ≤ 4 KB, parol ≤ 256 belgi;
   - **1-daraja:** IP bo‘yicha 15 daqiqada 8 xato → 429;
   - **2-daraja (global):** barcha IP'lardan 15 daqiqada 50 xato (v13; avval 30) → hamma uchun blok (admin: Telegram `/unlock`, 11.4-bo‘lim);
   - `password_verify()`; xato bo‘lsa 0,3–0,8 s tasodifiy kechikish + audit yozuvi;
   - muvaffaqiyatli: `session_regenerate_id(true)` (session fixation himoyasi), yangi 256-bit CSRF token, 8 soat mutlaq + 60 daqiqa harakatsizlik muddati.
3. Cookie: `HttpOnly`, `Secure` (HTTPS'da), `SameSite=Strict`, `use_strict_mode`.

### 2.3. Kontentni saqlash
1. Admin → `POST api.php?route=admin/save` + `X-CSRF-Token` sarlavhasi + JSON `{revision, field, key, value}`.
2. `csrf_check()`: sessiya + `Origin` hosti + token `hash_equals`.
3. `locked()`: `storage/content.lock` ga `flock(LOCK_EX)` → `content.json` o‘qiladi → `revision` mos kelmasa 409 (optimistik qulf) → maydon oq ro‘yxat bo‘yicha tekshiriladi (telefon regex, email filter, rang `#RRGGBB`, username regex, uzunlik chegaralari) → vaqtinchalik faylga yozilib `rename()` (atomar) → audit jurnaliga yoziladi.

### 2.4. Tarjima (UZ → RU)
1. Admin → `POST admin/translate` (CSRF bilan), matn ≤ 3000 belgi.
2. Server `DARIKO_TRANSLATE_URL` (faqat `https://`) hostini DNS orqali aniqlaydi, private/loopback/metadata IP bo‘lsa rad etadi (SSRF himoyasi), redirect'larga amal qilmaydi, 8 s timeout, javob ≤ 64 KB.
3. Kalit (`DARIKO_TRANSLATE_KEY`) faqat server muhit o‘zgaruvchisida — brauzerga hech qachon chiqmaydi.

### 2.5. Analitika beacon'i
```
brauzer (analytics.js)                      api.php?route=track                     analytics.sqlite
 │ sahifa ochildi: {t:"pv",sid,p,l,r} ───▶  Origin/Referer == Host ?  ──yo‘q──▶ 403
 │                                          body ≤ 2 KB, t∈{pv,hb}, sid=32 hex,
 │                                          p ∈ oq ro‘yxat, l∈{uz,ru}, r → faqat domen
 │                                          User-Agent → bot bo‘lsa e’tiborsiz
 │                                          UA → qurilma/brauzer/OT (regex)
 │                                          IP → HMAC-SHA256(kalit, IP|sana)
 │                                          ip-hash bo‘yicha ≤ 30 hodisa/daqiqa ─▶ INSERT visits (prepared)
 │ har 45 s (tab ko‘rinib tursa): {t:"hb"} ─▶  xuddi shunday tekshiruv  ────────▶ UPSERT live_sessions
```
- Cookie yo‘q; sessiya ID faqat shu brauzer oynasining `sessionStorage` ida.
- `Do Not Track` / `Global Privacy Control` yoqilgan bo‘lsa hech narsa yuborilmaydi.
- Tab yashirin bo‘lsa (Page Visibility API) heartbeat to‘xtaydi.
- "Hozir onlayn" = `live_sessions.last_seen` oxirgi 120 soniyada bo‘lganlar.

### 2.6. Admin dashboard
- `GET admin/analytics/live` — har 15 s (tab ochiq bo‘lsa).
- `GET admin/analytics/summary?period=today|7d|30d|90d` — har 60 s va davr o‘zgarganda.
- `GET admin/security` — server tekshiruvlari + audit jurnali.
- Barcha `admin/*` GET marshrutlari ham login **va** `X-CSRF-Token` talab qiladi.

---

## 3. Ma’lumotlar modeli

### 3.1. `content.json` / `initial_content.json`
```json
{
  "revision": 12,
  "texts":   { "t001": { "uz": "Xizmatlar", "ru": "Услуги", "status": "reviewed|draft", "context": "..." }, ... },
  "assets":  { "logo": "uploads/cms-logo-<rand>.png?v=12", "hero": "...", "expert-1..4": "...", "partner-1..4": "...", "favicon": "..." },
  "theme":   { "navy": "#0B1727", "green": "#05A85C" },
  "settings":{ "phone": "+998914053508", "email": "...", "telegram": "...", "instagram": "...", "map": "Vobkent tumani, Buxoro viloyati",
               "seo_title_uz": "...", "seo_title_ru": "...", "seo_description_uz": "...", "seo_description_ru": "..." }
}
```
`tNNN` — ko‘rinadigan matnlar (`data-cms-text`), `aNNN` — atributlar (alt, aria-label, placeholder).

### 3.2. `auth.json`
`{"hash": "<password_hash() natijasi>"}` — 0600 ruxsat. Algoritm yangilansa login paytida avtomatik qayta xeshlanadi (`password_needs_rehash`).

### 3.3. `analytics.sqlite`
| Jadval | Ustun | Tavsif |
|---|---|---|
| `visits` | `id` | avtoinkrement |
| | `ts` | Unix vaqti |
| | `day` | `YYYY-MM-DD` (Asia/Tashkent) |
| | `path` | `/index.html`, `/resume.html`, `/privacy.html` (oq ro‘yxat) |
| | `session_id` | 32 hex, brauzer oynasi uchun tasodifiy |
| | `ip_hash` | HMAC-SHA256(maxfiy kalit, IP + sana) — xom IP saqlanmaydi, kunlar orasida bog‘lanmaydi |
| | `user_agent` | tozalangan, ≤ 300 belgi (faqat ma’lumot sifatida; admin panelda `esc()` bilan) |
| | `device_type` | `mobile` / `tablet` / `desktop` (CHECK cheklovi) |
| | `browser`, `os` | aniqlangan nom (Chrome, Safari, Edge, Firefox, Telegram, Instagram, Yandex, …; iOS, Android, Windows, macOS, Linux, …) |
| | `referrer_host` | faqat tashqi domen (to‘liq URL emas) |
| | `lang` | `uz` / `ru` / bo‘sh |
| `live_sessions` | `session_id` (PK), `ip_hash`, `first_seen`, `last_seen`, `path`, `device_type`, `browser`, `os`, `lang`, `hits` | onlayn hisoblash uchun; 1 kundan eskisi o‘chiriladi |
| `rate_hits` | `ip_hash`, `ts` | beacon rate-limit; 5 daqiqadan eskisi o‘chiriladi |

Saqlash muddati: **13 oy (395 kun)** — `storage/cleanup_analytics.php` (cron) o‘chiradi.

### 3.4. `audit.log`
Har qator: `{"ts", "event", "ip" (12 belgili xesh), "ua" (≤160), "detail"}`. Hodisalar: `login_success`, `login_failed`, `login_blocked_ip`, `login_blocked_global`, `logout`, `content_save`, `asset_upload`, `setup_admin_created`. 1 MB dan oshsa `audit.log.1` ga aylantiriladi.

---

## 4. Xavfsizlik modeli (qisqacha)

| Qatlam | Chora |
|---|---|
| Transport | HTTPS majburiy (admin/API); HSTS (`.htaccess`) |
| Sessiya | HttpOnly + Secure + SameSite=Strict, strict mode, login'da ID yangilanadi, 8 soat + 60 daq harakatsizlik |
| CSRF | Har bir holat o‘zgartiruvchi va har bir admin GET marshrutida `X-CSRF-Token` + `Origin` tekshiruvi |
| Brute-force | IP bo‘yicha 8/15 daq + global 50/15 daq blok (v13; admin uchun Telegram `/unlock` bir martalik chiqish) + tasodifiy kechikish + audit |
| XSS | Ommaviy sahifada `textContent`; admin panelda har qanday server matni `esc()`; admin CSP: `script-src 'self'; style-src 'self'` (inline yo‘q) |
| Clickjacking | `X-Frame-Options: DENY` + `frame-ancestors 'none'` |
| Sarlavhalar | nosniff, Referrer-Policy, Permissions-Policy, COOP, CORP (API), X-Robots-Tag (admin/API) |
| PDF | magic bytes + `%%EOF` + faol kontent skaneri (v13: FlateDecode oqimlari ochiladi, `#XX` nom escape normallashtiriladi, `/OpenAction` harakati va `/AA` rad) — naqshga asoslangan, kafolat emas (11.1) |
| Host sarlavhasi | hech qachon ishonilmaydi: barcha mutlaq URL’lar `site_base_url()` (env `DARIKO_SITE_URL` yoki kanonik domen) dan (v13) |
| Yuklash | slot oq ro‘yxat, ≤5 MB, `getimagesizefromstring` + GD'da qayta kodlash (PNG), tasodifiy nom, `uploads/` da PHP o‘chirilgan, sandbox CSP |
| SSRF | tarjima hosti bir marta resolve + IP diapazon tekshiruvi + curl shu IP’ga qadalgan (`CURLOPT_RESOLVE`, v13), redirect yo‘q |
| SQL | Faqat PDO prepared statement; `ATTR_EMULATE_PREPARES=false`; guruhlash ustunlari qat’iy oq ro‘yxatdan |
| Maxfiylik | IP xeshlanadi (kunlik aylanuvchi HMAC), cookie yo‘q, DNT/GPC hurmat qilinadi, 13 oy retention |
| Fayllar | `storage/` web-root'dan tashqarida; `.htaccess` nuqtali fayllar, `.sqlite/.log/.key/.md` va ichki PHP'ni taqiqlaydi |
| Oshkor qilish | `/.well-known/security.txt` (RFC 9116) |

---

## 5. Fayllar ro‘yxati

| Fayl | Vazifasi |
|---|---|
| `README_UZ.md` | O‘rnatish qo‘llanmasi va o‘zgarishlar tarixi |
| `ARXITEKTURA_UZ.md` | Ushbu hujjat |
| `SEO_QOLLANMA_UZ.md` | SEO va xarita tizimlarida ro‘yxatdan o‘tish qo‘llanmasi |
| `public_html/.htaccess` | Xavfsizlik sarlavhalari, CSP, taqiqlangan fayllar, kesh, siqish |
| `public_html/index.html` | Asosiy landing sahifa (+ JSON-LD, OG, canonical) |
| `public_html/resume.html` | Khikmatullo Turaev web-rezyumesi |
| `public_html/privacy.html` | Maxfiylik siyosati (analitika bo‘limi bilan) |
| `public_html/cms.js` | Kontentni API'dan olib sahifaga qo‘yish, til almashtirish |
| `public_html/assets/js/analytics.js` | Anonim tashrif/heartbeat beacon |
| `public_html/assets/css/tailwind*.build.css` | Oldindan kompilyatsiya qilingan Tailwind CSS |
| `public_html/admin.html/.css/.js` | Boshqaruv paneli: dashboard, matnlar, rasmlar, sozlamalar, xavfsizlik |
| `public_html/api.php` | JSON API marshrutlari |
| `public_html/common.php` | Sessiya, CSRF, JSON, fayl qulfi, audit, SSRF tekshiruvi |
| `public_html/analytics_lib.php` | SQLite sxemasi, UA tahlili, track/live/summary/cleanup |
| `public_html/setup.php` | Birinchi admin parolini yaratish (keyin o‘chiring) |
| `public_html/robots.txt` | Qidiruv va AI botlarga ruxsatlar, sitemap manzili |
| `public_html/sitemap.xml` | Sahifalar xaritasi |
| `public_html/llms.txt` | AI/LLM tizimlari uchun sayt xulosasi |
| `public_html/.well-known/security.txt` | Zaiflik haqida xabar berish kontakti |
| `public_html/uploads/.htaccess` | Yuklangan fayllarda skript bajarilishini taqiqlash |
| `public_html/*.png` | Logo, ikonka, hamkorlar rasmi |
| `storage/.htaccess` | Ehtiyot chorasi: hammasini taqiqlash |
| `storage/initial_content.json` | Boshlang‘ich kontent |
| `storage/cleanup_uploads.php` | Ishlatilmayotgan rasmlarni tozalash (CLI) |
| `storage/cleanup_analytics.php` | 13 oydan eski statistikani o‘chirish (CLI) |

---

## 6. Hosting talablari

- PHP **8.2+** kengaytmalar: `gd`, `mbstring`, **`pdo_sqlite`** (analitika + v7 dan CRM, bron, blog — majburiy), **`curl`** (Telegram bot, Meta/Instagram/Facebook, avtomatik tarjima).
- Apache yoki LiteSpeed, `.htaccess` qo‘llab-quvvatlashi (`mod_headers`, `mod_rewrite` tavsiya etiladi).
- HTTPS sertifikati.
- Document root = `public_html`; `storage` undan tashqarida, PHP foydalanuvchisi yoza olishi kerak.
- `memory_limit` ≥ 256M, `post_max_size` ≥ 6M.
- Cron (tavsiya):
  ```
  17 3 * * *  php /yo‘l/storage/cleanup_analytics.php
  30 3 * * 0  php /yo‘l/storage/cleanup_uploads.php --delete
  ```
- `pdo_sqlite` bo‘lmasa sayt ishlayveradi, faqat analitika o‘chiq bo‘ladi (beacon 202 `disabled` qaytaradi, dashboard ogohlantiradi).
- **Domen:** barcha SEO fayllarida `https://dariko.uz` yozilgan. Boshqa domen bo‘lsa, `index.html`, `resume.html`, `privacy.html`, `robots.txt`, `sitemap.xml`, `llms.txt`, `.well-known/security.txt` da almashtiring:
  `grep -rl "https://dariko.uz" public_html | xargs sed -i 's#https://dariko.uz#https://YANGI-DOMEN#g'`

---

## 7. Kelajakda nima qilish mumkin

1. ~~**Server tomonida murojaatlar (leads)**~~ — v7 da bajarildi (8-bo‘lim). Asl reja: forma `mailto:`; SQLite'da `leads` jadvali + Telegram bot xabarnomasi + spam himoyasi (honeypot/Turnstile).
2. **Alohida `/ru/` URL** — ruscha versiya uchun server tomonda render; hreflang to‘liq ishlaydi.
3. **Inline skript/stillarni chiqarish** — skriptlar v12 da bajarildi (10.7); stillar qoldi — ommaviy sahifalardagi `'unsafe-inline'` ni olib tashlab, CSP nonce/hash.
4. **Font Awesome va Google Fonts'ni lokal joylash** — tezlik va CSP soddaligi.
5. **2FA (TOTP)** admin kirishiga.
6. **Kontentni ham SQLite'ga ko‘chirish** — `flock()` cheklovini butunlay yo‘qotish.
7. **Analitikaga voqealar (events)** — "Telegramda yozish", "Qo‘ng‘iroq" tugmalari bosilishini sanash; UTM kampaniyalar.
8. **Avtomatik zaxira nusxa** — `storage/` ni kunlik arxivlash.
9. **Real koordinatalar** — ofisning aniq nuqtasi tasdiqlansa JSON-LD'ga `geo` (lat/long) qo‘shish.
10. ~~**Blog/maqolalar bo‘limi**~~ — v7 da bajarildi. Asl reja: organik trafik va AI manbalarida ko‘rinish uchun eng kuchli omil.

---

## 8. v7 — CRM, bron, blog, keyslar, fikrlar, newsletter, A/B, holat tekshiruvi

### 8.1. Yangi fayllar
| Fayl | Vazifasi |
|---|---|
| `public_html/crm_lib.php` | `storage/dariko.sqlite` sxemasi; CRM; Telegram Bot API; Meta Messenger/Instagram webhook va Send API; SMTP (fsockopen) / `mail()`; bron kalendari; newsletter; kanal sozlamalari (to‘g‘ridan-to‘g‘ri ochilmaydi) |
| `public_html/site_lib.php` | Xavfsiz Markdown, blog/keys/fikr CRUD, `sitemap.xml` qayta yaratish, ommaviy sahifa qobig‘i (to‘g‘ridan-to‘g‘ri ochilmaydi) |
| `public_html/blog.php` | `/blog/`, `/blog/<slug>`, `/blog.php?feed=rss` (server-render, SEO) |
| `public_html/case-studies.php` | `/case-studies/`, `/case-studies/<slug>` + mijozlar fikrlari |
| `public_html/newsletter.php` | Obunani tasdiqlash / bekor qilish (token, POST tugma, RFC 8058 one-click) |
| `public_html/assets/js/admin/*.js` (v12 gacha `admin-ext.js`) | Admin: CRM, kalendar, blog, keyslar, fikrlar, newsletter, A/B, kanallar |
| `public_html/assets/js/features.js`, `assets/css/features.css` | Bosh sahifa: bron vidjeti (modal), fikrlar, newsletter, WhatsApp |
| `public_html/assets/css/pages.css` | Blog/keys sahifalari dizayni |
| `storage/seed/*.json` | Birinchi ishga tushishda import qilinadigan 5 ta blog maqola va 3 ta keys NAMUNASI (qoralama) |
| `storage/healthcheck.php` | Cron uchun ichki holat tekshiruvi + Telegram/email ogohlantirish |
| `CRM_QOLLANMA_UZ.md` | Telegram / Meta / SMTP ni qo‘lda ulash qo‘llanmasi |

Toza URL’lar `.htaccess` dagi `RewriteRule` lar orqali (`mod_rewrite` kerak): `/blog/…` → `blog.php`, `/case-studies/…` → `case-studies.php`.

### 8.2. `storage/dariko.sqlite` sxemasi (birinchi so‘rovda o‘zi yaratiladi, 0600)
| Jadval | Asosiy ustunlar | Izoh |
|---|---|---|
| `settings` | `key` PK, `value` | Kanal kalitlari (bot token, webhook secret, Meta app secret / verify token / page token, SMTP), bron qoidalari (`booking_rules` JSON), xizmat belgilari |
| `leads` | `id, created_at, updated_at, name, phone, email, company, tg_username, service_interest, message, source, status, assigned_note, last_contact_at, last_inbound_at, ext_key UNIQUE, lang` | `source` ∈ web_form, booking, telegram, instagram, facebook, manual; `status` ∈ yangi, aloqada, muvaffaqiyatli, yopilgan (CHECK). `ext_key` = `tg:<chat_id>` / `ig:<IGSID>` / `fb:<PSID>` — bir foydalanuvchi = bitta lead |
| `lead_messages` | `id, lead_id FK (CASCADE), channel, direction (in/out), body, external_message_id, created_at` | `UNIQUE(channel, external_message_id)` — webhook qayta yuborilsa takrorlanmaydi |
| `bookings` | `id, lead_id FK, requested_date, requested_time_slot, status (pending/confirmed/cancelled), service, created_at, updated_at` | **Qisman UNIQUE indeks** `(date, slot) WHERE status IN ('pending','confirmed')` — ikki marta band qilish DB darajasida imkonsiz |
| `availability_overrides` | `date` PK, `closed`, `note` | Bayram/dam olish kunlari. Haftalik ish vaqti `settings.booking_rules` da (standart: Du–Sha 09:00–19:00, 60 daqiqalik slot) |
| `subscribers` | `email UNIQUE, name, subscribed_at, confirmed, confirmed_at, confirm_token, unsubscribe_token UNIQUE, unsubscribed_at, last_campaign_id, lang` | Double opt-in |
| `campaigns` | `id, subject, body, recipients, sent, failed, finished_at` | Partiyalab yuborish (25 ta / so‘rov, 250 ms pauza) |
| `blog_posts` | `slug UNIQUE, title, excerpt, body (Markdown), cover_image, author, published_at, status (draft/published), seo_title, seo_description, tags (JSON), lang` | |
| `case_studies` | `slug, title, client_name, industry, challenge, approach, result_summary, result_metrics (JSON [{label,before,after}]), testimonial_quote, cover_image, status, is_sample, sort_order` | `is_sample=1` — rezyumedan olingan namuna, nashr qilinmaydi |
| `testimonials` | `client_name, company, role, quote, rating (1–5/NULL), photo, source (google/yandex/manual), published, sort_order` | |

`storage/analytics.sqlite` ga qo‘shildi: `ab_experiments(id, name, slot, goal, status, variants JSON, started_at)`, `ab_exposures(exp_id, session_id, variant, views)` PK(exp,session), `ab_conversions(exp_id, session_id, variant)` PK(exp,session).

### 8.3. Oqimlar
- **Sayt formasi:** `POST api.php?route=lead/submit` — Origin/Referer tekshiruvi, honeypot (`website`), IP bo‘yicha 5/10 daq limit, maydon validatsiyasi → `leads`+`lead_messages` → admin’ga Telegram (yoki email) xabarnoma. Server ishlamasa, brauzer avvalgidek `mailto:` ga qaytadi.
- **Bron:** `GET booking/availability` (bo‘sh slotlar) → `POST booking/create` (4/10 daq limit) — `BEGIN IMMEDIATE` tranzaksiyada slot qayta tekshiriladi, UNIQUE indeks poygani ushlaydi (409). Admin: oy kalendari, tasdiqlash/bekor qilish, haftalik ish vaqti va yopiq kunlar.
- **Telegram:** `POST api.php?route=telegram/webhook` — `X-Telegram-Bot-Api-Secret-Token` `hash_equals` bilan; faqat shaxsiy chatlar; `/admin KOD` — admin chatini ulash. Javob: `sendMessage`.
- **Meta:** `GET …meta/webhook` — `hub.verify_token` tekshiruvi, `hub.challenge` qaytariladi; `POST` — xom tana ustidan `X-Hub-Signature-256` HMAC-SHA256 (App Secret), JSON parse’dan OLDIN. `object=instagram` → instagram, `page` → facebook. Javob: `POST graph.facebook.com/v21.0/me/messages` (`messaging_type=RESPONSE`, `appsecret_proof`). Graph versiyasi `crm_lib.php` dagi `META_GRAPH_VERSION` konstantasida.
- **Blog/keys:** admin saqlaganda `public_html/sitemap.xml` qayta yoziladi (PHP foydalanuvchisi bu faylga yoza olishi kerak; bo‘lmasa admin ogohlantiriladi va audit’ga yoziladi).
- **A/B:** `analytics.js` pv beacon’i javobida variantlar keladi; variant = `HMAC(analytics_secret, session_id|exp_id) mod n` — bir sessiyada o‘zgarmaydi. Konversiya: `t=cv` (`lead` — ariza/bron yuborildi, `cta_click` — konsultatsiya tugmasi), sessiyaga 1 marta. DNT/GPC foydalanuvchilar testga kirmaydi. Admin: sessiya, ko‘rish, konversiya, % — statistik ahamiyatlilik hisoblanmaydi (ogohlantirish bor).

### 8.4. Xavfsizlik qo‘shimchalari
- Maxfiy kalitlar faqat `storage/dariko.sqlite` da; admin GET javobida niqoblangan (`••••••••abcd`); `audit.log` ga niqoblangan holda.
- Tashqi API chaqiruvlari (Telegram, Graph, SMTP) faqat server tomonida — ommaviy sahifalar CSP `connect-src 'self'` o‘zgarmadi. Graph token GET so‘rovlarda URL’da emas, `Authorization` sarlavhasida.
- Blog/keys matni: xom HTML hech qachon saqlanmaydi/chiqmaydi — butun matn escape qilinadi, keyin faqat cheklangan Markdown konstruksiyalari o‘z teglarimizga aylanadi; havolalar faqat `https?://`, `/`, `#`, `mailto:`.
- `blog.php`/`case-studies.php`/`newsletter.php` qat’iy CSP bilan (inline skript/stil yo‘q).
- `.htaccess`: `*_lib.php`, `*.sqlite-wal/-shm` ham taqiqlandi; `assets/js/admin/admin-*.js` (v12 gacha `admin-ext.js`) admin CSP/no-store ostida (`.htaccess` FilesMatch `^admin(-[a-z]+)?\.js`).
- Yangi audit hodisalari: `channel_setting_save` (niqoblangan), `telegram_webhook_set`, `webhook_rejected`, `lead_reply/update/create/delete`, `booking_confirmed/cancelled`, `availability_save/override`, `blog_publish/unpublish/save/delete`, `case_save/delete`, `testimonial_save/delete/reorder`, `media_upload`, `newsletter_campaign_created/finished` (faqat son), `mail_test`, `ab_*`.

### 8.5. Cron (to‘liq ro‘yxat)
```
17 3 * * *                 php /yo‘l/storage/cleanup_analytics.php
30 3 * * 0                 php /yo‘l/storage/cleanup_uploads.php --delete
0,10,20,30,40,50 * * * *   php /yo‘l/storage/healthcheck.php --url=https://dariko.uz/
```
`healthcheck.php`: storage/uploads yozish huquqi, maxfiy fayllar 0600, ikkala SQLite `quick_check` + yozish sinovi, bo‘sh disk (<200 MB — ogohlantirish), `--url` bilan sayt va API javobi. Holat o‘zgarganda darhol, muammo davom etsa 6 soatda bir marta ogohlantiradi (Telegram admin chat va/yoki `notify_email`). `--dry-run`, `--test-alert` rejimlari bor.

### 8.6. Monitoring cheklovi (halol)
Bu **ichki** tekshiruv: server yoki hosting butunlay o‘chsa, skript ham ishlamaydi va xabar yubora olmaydi. To‘liq **tashqi** kuzatuv uchun bepul **UptimeRobot** (yoki Better Stack, Freshping) da akkaunt ochib, `https://dariko.uz/` uchun HTTP(S) monitor qo‘shing (5 daqiqalik interval, Telegram/email ogohlantirish) — bu qo‘lda sozlanadigan tashqi xizmat.

### 8.7. Test-only muhit o‘zgaruvchilari
`DARIKO_TG_API`, `DARIKO_GRAPH_API` — faqat lokal mock server bilan sinash uchun; prod’da o‘rnatmang. `DARIKO_SITE_URL=https://domen` — webhook/email havolalari va sitemap uchun domenni majburan belgilash (ixtiyoriy).

## v8 qo‘shimchalari
- **Bron kalendari oqimi:** `assets/js/features.js` → `GET <API_BASE>?route=booking/availability` (ommaviy, sessiya/cookie ochilmaydi) → `booking_availability()` (ish vaqti qoidalari `booking_rules`, standart Du–Sh 09:00–19:00) → kunlar/slotlar. `API_BASE = new URL('../../api.php', document.currentScript.src)` — sub-papka o‘rnatishga chidamli. `location.protocol === 'file:'` bo‘lsa so‘rov yuborilmaydi, tushuntiruvchi xabar chiqadi.
- **Toza URL’lar:** `.htaccess` RewriteRule → `blog.php` / `case-studies.php`. Lokal `php -S` uchun `dev/router.php` shu qoidalarni takrorlaydi (production’da ishlatilmaydi). `file://` da hech qachon ishlamaydi.
- **Cookie/rozilik:** ommaviy tashrifchiga birinchi tomon cookie’si yo‘q (tekshirildi: Set-Cookie sarlavhasi yo‘q). Brauzer xotirasi: `sessionStorage.dariko_sid` (analitika, DNT/GPC hurmat qilinadi), `localStorage.dariko_lang`, `localStorage.dariko_consent_v1` (`{choice:"all"|"essential", ts}`, 365 kun). Google Maps iframe’i `data-consent-src` bilan keladi va `consent.js` faqat rozilikdan keyin `src` qo‘yadi; `cms.js` admin manzilini ham `data-consent-src` ga yozadi (rozilikni chetlab o‘tmaydi). PHP sessiya cookie’si faqat admin kirishida (`common.php`).
- **Admin xabarnomasi:** `lead/submit` (aloqa formasi va modal «Ariza qoldirish») va `booking/create` → `notify_admin()` → Telegram `sendMessage` (admin chat `/admin KOD` bilan ulangan) → muvaffaqiyatsiz bo‘lsa `notify_email`. Har bir natija `audit.log` ga: `notify_sent|notify_failed|notify_skipped`. Xabarnoma xatosi mijoz so‘rovini buzmaydi.
- **Newsletter:** ommaviy forma olib tashlangan; backend marshrutlari va admin vositasi saqlangan (faol emas).

## 10. v12 qo‘shimchalari (audit tuzatishlari)

### 10.1. `newsletter/subscribe` — sozlama bilan yopilgan (B1)
- v8 da ommaviy obuna formasi olib tashlangan, lekin API marshruti ochiq qolgan edi (istalgan kishi begona manzillarga tasdiqlash xati yubortirishi mumkin edi).
- Endi marshrut faqat `dariko.sqlite` → `settings.newsletter_enabled = '1'` bo‘lganda ishlaydi. **Standart: o‘chiq** → `404 Not found` (rate-limit fayli ham yaratilmaydi, email yuborilmaydi).
- Boshqaruv: Admin → **Newsletter** → «Ommaviy obuna marshruti» belgisi (`POST admin/newsletter/enabled {enabled: bool}`, CSRF + audit `newsletter_enabled`). Backend (jadval, kampaniya vositasi, `newsletter.php` tasdiqlash/bekor qilish sahifasi) o‘zgarmagan — formani qaytarsangiz belgini yoqasiz.

### 10.2. Rate-limit storage xatosi: fail-closed + jurnal (B2)
- `rate_limit_hit()` (common.php) `storage/rate-*.json` faylini ochib/qulflay olmasa, avval **jim o‘tkazib yuborardi** (fail-open). Endi har doim `audit.log` ga `rate_limit_storage_error` (`bucket`, `fail_closed`) va PHP `error_log` ga yoziladi (audit.log ham yozilmaydigan holat uchun).
- Qaror (qaysi marshrut qanday):

| Marshrut | Xulq storage buzilganda | Sabab |
|---|---|---|
| `login` | **503** (yopiq) | brute-force himoyasisiz parol tekshiruvi mumkin emas |
| `lead/submit` | **503** (yopiq) | spam-oqim; brauzer formasi 503 da avtomatik `mailto:` zaxirasiga o‘tadi — ariza yo‘qolmaydi |
| `booking/create` | **503** (yopiq) | slotlarni ommaviy band qilish (DoS) xavfi |
| `newsletter/subscribe` (yoqilsa) | **503** (yopiq) | begona manzillarga xat yuborish xavfi |
| `track` (analitika) | ta’sir yo‘q | o‘z SQLite `rate_hits` jadvalidan foydalanadi, fayl limitiga bog‘liq emas |
| sahifalar, `public`, blog, keyslar | ta’sir yo‘q | rate-limit ishlatmaydi — storage nosozligi butun saytni o‘chirmaydi |

- `rate_limit_hit(..., $failClosed = false)` parametri kelajakdagi kam xavfli marshrutlar uchun saqlangan (hozir hech bir marshrut ishlatmaydi).

### 10.3. Bron to‘qnashuvi: xato kodi bo‘yicha aniqlash (B3)
- Avval `PDOException` xabar matnida `"UNIQUE"` so‘zi qidirilardi (drayver/til o‘zgarsa jim buzilishi mumkin edi). Endi `pdo_is_constraint_violation()` (crm_lib.php): SQLSTATE `23000` + SQLite drayver kodi `19` (SQLITE_CONSTRAINT).
- PDO SQLite kengaytirilgan kodni (2067 = UNIQUE) bermaydi, shuning uchun `booking_create()` 409 dan oldin slot haqiqatan band ekanini SQL bilan tasdiqlaydi (`booking_slot_taken()`); boshqa cheklov xatosi 500 bo‘lib qoladi. `booking_set_status()` da status oldindan tekshirilgani uchun yagona mumkin bo‘lgan cheklov `uq_booking_active`. Xuddi shu matn-qidiruv `site_lib.php` dagi blog/keys slug takrori tekshiruvida ham almashtirildi.

### 10.4. Rasm og‘irligi (B4)
- `dariko-logo-white.png`: 871×255 / 135 KB → **520×152 / 8.8 KB** (eng katta ko‘rinish 219×64 CSS px; 520 px = 2x retina uchun yetarli), 256 rangli palitra PNG, shaffoflik saqlangan.
- `dariko-icon-dark-clean.png`: 218 KB → **21 KB** (o‘lcham 628×630 o‘zgarmadi — `og:image:width/height` meta teglari shunga mos), 256 rangli palitra.
- `partner-reference.png` (223 KB): hech qayerda (HTML/JS/PHP/CSS/JSON) ishlatilmagan v4 qoldig‘i edi — **o‘chirildi**. Jami ~576 KB → ~30 KB.

### 10.5. SQLite indekslari (B6)
- `dariko.sqlite`: `idx_blog_status_pub ON blog_posts(status, published_at)`.
- `analytics.sqlite`: `idx_ab_exposures_exp ON ab_exposures(exp_id, variant)`, `idx_ab_conversions_exp ON ab_conversions(exp_id, variant)` (qoplovchi indeks: `WHERE exp_id=? GROUP BY variant`).
- Hammasi mavjud sxema-init yo‘lida `CREATE INDEX IF NOT EXISTS` — yangi o‘rnatishda ham, v11 bazasi ustida ham birinchi so‘rovda avtomatik qo‘shiladi (v11 bazasi bilan sinalgan, ma’lumot saqlanib qoldi).

### 10.6. Takrorlangan kod birlashtirildi (C1)
- `sqlite_open($path)` (common.php): PDO + `busy_timeout` + WAL + `foreign_keys` + yangi fayl uchun 0600. `analytics_db()` va `app_db()` shuni chaqiradi. `busy_timeout` avval 3000 (analitika) va 5000 (CRM) edi; farq uchun hujjatli sabab topilmadi, beacon sahifani kutdirmaydi — ikkalasi ham `SQLITE_BUSY_TIMEOUT_MS = 5000`.
- `rate_file_open() / rate_file_read() / rate_file_close()` (common.php) — fayl+flock oyna primitivlari. `rate_limit_hit()` va login ikkalasi ham shulardan foydalanadi. Login mantiqi AYNAN saqlangan (faqat xato urinishlar sanaladi; IP: 8 xato / 15 daq, `rate-sha256(ip).json`; global: 30 xato / 15 daq, `rate-global.json`) — v11 bilan yonma-yon bir xil sinov natijasi. Yagona farq: storage ishlamasa 500 o‘rniga jurnal + 503 (10.2).
- `rate_limit_hit()` to‘g‘ridan-to‘g‘ri login uchun ishlatilmadi: u har bir *urinishni* (muvaffaqiyatlini ham) sanaydi va ikki darajali (IP + global) chegarani qo‘llamaydi — shunday almashtirish brute-force xulqini o‘zgartirgan bo‘lardi.

### 10.7. CSP: `script-src 'self'` (inline JS'siz) — index.html va resume.html (C2)
- `index.html` dagi inline `<script>` → `assets/js/main.js`; `resume.html` dagi → `assets/js/resume.js` (oddiy `<script src>`, avvalgi joyida — bajarilish tartibi o‘zgarmagan).
- Barcha `onclick`/`onsubmit`/`onchange`/`onerror` atributlari `data-action="…"` / `data-submit="…"` ga almashtirildi, hodisalar `document` darajasida delegatsiya qilinadi. Global funksiyalar (`openConsultationModal` va h.k.) saqlangan — `features.js` ulardan foydalanadi. Rezyume suratining «KT» fallback’i endi JS’da (skriptdan oldin sodir bo‘lgan xato ham `img.complete && naturalWidth === 0` bilan ushlanadi).
- Ikkala sahifaning CSP meta tegida `script-src 'self'` — `'unsafe-inline'` **olib tashlandi**. `<script type="application/ld+json">` va `type="application/json"` (cms-bootstrap) bajarilmaydigan ma’lumot bloklari — CSP ularni to‘smaydi.
- `style-src` da `'unsafe-inline'` **qoldi**: sahifalarda inline `<style>` bloklari va `style=""` atributlari bor (hamda `cms.js` rang o‘zgaruvchilarini `style.setProperty` bilan o‘rnatadi) — uslub injeksiyasi skript injeksiyasiga qaraganda ancha kam xavfli; ko‘chirish dizaynni qayta qurishni talab qiladi.

### 10.8. `admin-ext.js` bo‘lindi → `assets/js/admin/` (C3)
| Fayl | Mazmun |
|---|---|
| `admin-core.js` | umumiy yordamchilar (`$`, `esc`, `get`/`post`, `dt`, manba/holat teglari, `uploadMedia`), taymerlar + `DARIKO_ADMIN_STOP`, `DARIKO_ADMIN_TABS` registri, CRM nishonini yangilash; `window.DARIKO_ADMIN_EXT` orqali eksport |
| `admin-crm.js` | CRM · Murojaatlar |
| `admin-booking.js` | Bron kalendari va ish vaqti |
| `admin-content.js` | Blog, Keyslar, Mijozlar fikri (bir xil muharrir naqshi, `uploadMedia`) |
| `admin-marketing.js` | Newsletter (+ v12 obuna belgisi) va A/B testlar |
| `admin-channels.js` | Kanallar sozlamalari va holat tekshiruvi |

- `admin.html` da `defer` tartibi: `admin.js` → `admin-core.js` → qolganlari. Har bo‘lim o‘zini `Object.assign(window.DARIKO_ADMIN_TABS, …)` bilan ro‘yxatdan o‘tkazadi.
- Yagona bo‘limlararo bog‘liqlik (kalendardagi «murojaatni ochish» → CRM’ning `currentLead`) endi `DARIKO_ADMIN_EXT.shared.openLeadId` orqali. Kod mazmuni o‘zgartirilmagan (faqat ko‘chirildi); ESLint `no-undef` bilan har fayl alohida tekshirildi.

### 10.9. Avtomatik testlar — `tests/` (C4)
- **Ishga tushirish (paket ildizidan): `php tests/run.php`** (yoki faqat ba’zilari: `php tests/run.php 08 13`). Talab: PHP 8.2+ CLI, `pdo_sqlite`, `curl`, `gd`, `mbstring`. Chiqish kodi 0 = hammasi o‘tdi.
- Tanlov: **PHPUnit ishlatilmadi** — Composer/vendor bog‘liqligi «build step yo‘q» falsafasiga zid va hostingga keraksiz. `tests/lib.php` — ~60 qatorli minimal yordamchi (`t_assert`, `t_eq`, HTTP mijoz, admin login).
- Izolyatsiya: `run.php` paketni vaqtinchalik papkaga nusxalaydi (runtime fayllarsiz), test paroli bilan `auth.json` yozadi, `php -S` + `dev/router.php` ni bo‘sh portda ishga tushiradi; haqiqiy `storage/` ga tegilmaydi. Tashqi API (Telegram/Meta) chaqiruvlari `DARIKO_TG_API/DARIKO_GRAPH_API=http://127.0.0.1:9` bilan darhol muvaffaqiyatsiz bo‘ladi (internet kerak emas).
- 20 ta test: CSRF (tokensiz/noto‘g‘ri token/kirmasdan, begona Origin), Telegram secret-header, Meta verify + HMAC (to‘g‘ri/noto‘g‘ri/o‘zgartirilgan tana), rasm yuklash (to‘g‘ri PNG/JPEG; PHP-as-PNG, GIF+PHP polyglot, SVG, buzilgan PNG, HTML, 4100px, JPEG+PHP dumi), PDF (JS/Launch/EmbeddedFiles/XFA/script/EOF’siz/soxta), ikki marta bron → 409, cheklov kodi birlik testi, login IP (8/15) va global (30/15) bloklari, ariza rate-limit, storage fail-closed (503 + audit), URL validatori (javascript:/data:/vbscript:/file:/…), personal_page_url API, newsletter belgisi, honeypot/Origin, indeks migratsiyasi, CSP, `/?lang=ru` URL va hreflang.
- Test topgan xato (tuzatildi): `pdf_validate()` regexi `/EmbeddedFile\b` edi — PDF ilova daraxti nomi `/EmbeddedFiles` («s» bilan) `\b` sababli o‘tib ketardi. Endi `EmbeddedFiles?`.

### 10.10. Ruscha versiya uchun alohida URL: `/?lang=ru` (C6)
- Avval `hreflang="uz"` va `hreflang="ru"` ikkalasi ham bitta `https://dariko.uz/` ga ko‘rsatardi (til faqat JS bilan almashardi) — qidiruv tizimlari uchun noto‘g‘ri da’vo edi.
- Endi: **UZ = `https://dariko.uz/`**, **RU = `https://dariko.uz/?lang=ru`**, `x-default` = UZ. `.htaccess` (`RewriteCond %{QUERY_STRING} lang=ru` → `ru.php`) va lokal `dev/router.php` shu so‘rovni `ru.php` ga beradi.
- `ru.php` `index.html` ni o‘qiydi va **serverda** almashtiradi: `<html lang="ru">`, `<title>`/description/OG/Twitter (`seo_*_ru` sozlamalaridan), `canonical` va `og:url` = `/?lang=ru`, `og:locale` = `ru_RU`, 238 ta `data-cms-text` matni va `data-cms-placeholder/aria-label/title/alt` atributlari `content.json` dagi RU qiymatlar bilan. JS o‘chiq bo‘lsa ham ruscha matn ko‘rinadi (sinalgan). Sessiya/cookie ochilmaydi, faqat GET/HEAD.
- `cms.js`: URL’dagi `?lang=ru|uz` `localStorage` dan ustun; UZ/RU tugmasi `history.replaceState` bilan URL’ni moslaydi (RU → `?lang=ru`, UZ → parametrsiz). `/` ga qaytgan RU foydalanuvchi uchun avvalgi eslab qolish saqlangan.
- `sitemap.xml` (statik fayl va `sitemap_regenerate()`): ikkala URL alohida `<url>` sifatida, har biri `xhtml:link` uz/ru/x-default bilan.
- Qolgan cheklov: `features.js` ning kichik lug‘ati (`data-v7-i18n`: «Blog», «Keyslar», bron oynasi yozuvlari) va JSON-LD serverda tarjima qilinmaydi — ular JS ishlagach ruschaga o‘tadi (Google sahifani JS bilan render qiladi). Alohida `/ru/` yo‘li tanlanmadi: nisbiy yo‘llar va `#anchor` havolalari buzilardi, yangi papka/shablon talab qilinardi; `?lang=ru` Google tomonidan to‘liq qo‘llab-quvvatlanadigan alohida URL.


## 11. v13 — xavfsizlik auditi (v12) tuzatishlari

Mustaqil audit 10 ta masala topdi; har biri avval v12 ga qarshi jonli qayta ishlab chiqarildi, keyin tuzatildi va doimiy regressiya testi qo‘shildi (`tests/21`–`27`).

### 11.1. PDF faol kontent skaneri (1-masala)
- **Muammo:** `pdf_validate()` xom baytlarda oddiy regex qidirardi — (a) `FlateDecode` bilan siqilgan `/ObjStm` ichidagi `/JavaScript`, (b) `/J#61vaScript` (PDF nomidagi hex-escape, o‘quvchilar `/JavaScript` deb tushunadi), (c) `/OpenAction << /S /URI ... >>` (avtomatik tashqi havola) — uchalasi ham qabul qilinardi.
- **Tuzatish (common.php):** `pdf_collect()` har bir `stream … endstream` ni topadi, lug‘atida `/FlateDecode` (hex-escape ochilgandan keyin) bo‘lsa `inflate_*` bilan ochadi (jami 50 MB chegara — zip-bomb; oshsa 400; buzuq oqim o‘tkazib yuboriladi, 500 yo‘q), `/ObjStm` bo‘lsa ichidagi obyektlarni raqam bo‘yicha xaritaga qo‘shadi. Barcha matnlar (xom + ochilgan) `pdf_normalize_names()` (`#XX` → bayt) dan o‘tib, xavfli kalit so‘zlar (`JavaScript|JS|Launch|EmbeddedFiles?|RichMedia|XFA|SubmitForm|ImportData|GoToE`) bo‘yicha tekshiriladi.
- **Avtomatik harakatlar:** `/AA` (bo‘sh bo‘lmasa yoki havola bo‘lsa) va `/OpenAction` harakat lug‘ati (`<< /S … >>`, to‘g‘ridan-to‘g‘ri yoki `N 0 R` havola orqali — obyekt oddiy yoki `/ObjStm` ichida qidiriladi, topilmasa xavfsiz tomonga rad) rad etiladi. `/OpenAction [sahifa /XYZ …]` (oddiy manzil — Word/LibreOffice qo‘yadi) **ruxsat**. Oddiy havola annotatsiyalari (`/Annot /Link /A << /S /URI >>`, foydalanuvchi bosganda) ruxsat — rezyumedagi portfolio havolalari ishlaydi.
- **Sinov:** auditning `p_plain/p_hex/p_aa/p_flate` + 5 ta qo‘shimcha variant → 400; Chromium «Print to PDF» (7 ta Flate oqim, 4 ta `/URI` havola), PyMuPDF (`/ObjStm` + deflate), OpenAction-manzilli PDF → 200.
- **Xizmat ko‘rsatish:** `uploads/.htaccess` o‘zgarmadi — PDF’lar ataylab `inline` (brauzerda ochiladi; v4 dizayn qarori), `Content-Security-Policy: default-src 'none'; …` (PDF’ni HTML sifatida talqin qilib bo‘lmaydi, `nosniff`, `ForceType application/pdf`).
- **Qoldiq xavf (halol):** bu **naqshga asoslangan aniqlash**, to‘liq PDF parser yoki sanitizatsiya emas — antivirus skanerlari kabi yetarlicha chalkashtirilgan fayl o‘tib ketishi mumkin: shifrlangan PDF (`/Encrypt` — oqimlar RC4/AES bilan, ochib bo‘lmaydi), boshqa filtrlar (`/ASCIIHexDecode`, `/LZWDecode`, filtrlar zanjiri), PDF satrlaridagi oktal escape, buzuq xref’ni o‘quvchi «tiklashi». Auditor ko‘rsatgan barcha shakllar endi ushlanadi. PDF’larni faqat admin yuklaydi (login + CSRF), bu xavfni sezilarli kamaytiradi. To‘liq yechim — serverda `qpdf`/Ghostscript bilan qayta yozish (shared hostingda odatda yo‘q).

### 11.2. Host-header poisoning (2-masala) + bazaviy URL birlashtirildi
- **Muammo:** `site_base_url()` `DARIKO_SITE_URL` bo‘lmasa `HTTP_HOST` ni ishlatardi → `Host: evil.example` bilan obuna tasdiqlash xatidagi havola `http://evil.example/...` ga ishora qilardi (audit jonli isbotlagan). `seo_base()` va `ru.php` esa Host’ga ishonmasdi — nomuvofiqlik, port uchun uch xil regex.
- **Tuzatish:** yagona manba `site_url_configured()` / `site_base_url()` (common.php): env `DARIKO_SITE_URL` (`https://domen[:port]`, yo‘l/so‘rovsiz) yoki kanonik `SITE_URL_DEFAULT = https://dariko.uz`. **Host sarlavhasi hech qachon ishlatilmaydi.** `seo_base()` endi shunchaki `site_base_url()`; `ru.php` ham shu. Tanlangan yondashuv — «xavfsiz standart + aniq ogohlantirish»: env sozlanmagan bo‘lsa havolalar hujumchi domeniga emas, kanonik domenga ketadi; Admin → Kanallar’da «DARIKO_SITE_URL sozlanmagan, bu yerda ko‘rsatilgan manzil ishonchli emas» qizil ogohlantirishi; newsletter tasdiqlashida `error_log` yozuvi.
- **Sinov:** auditning reproduksiyasi (`Host: evil.example` + obuna) — v12: `http://evil.example/newsletter.php?a=confirm&t=…`; v13: `https://dariko.uz/newsletter.php?a=confirm&t=…` (sendmail capture orqali). Env `https://staging.dariko.uz:8443` bilan: xat, canonical (`/?lang=ru`, `/blog/`) — hammasi shu manzil (port endi hamma joyda bir xil qo‘llab-quvvatlanadi).

### 11.3. Bron kalendarini to‘ldirib tashlash (3-masala)
- **Muammo:** faqat IP bo‘yicha 4 ta / 10 daqiqa — ~10 ta IP bilan ~1 soatda butun kalendar (≈300 slot) soxta `pending` bronlar bilan to‘lardi; bitta telefon cheksiz bron qila olardi.
- **Tuzatish (`booking_create()`, bitta `BEGIN IMMEDIATE` tranzaksiyasi ichida):**
  1. **Telefon dedup:** raqam `public_lead_fields()` dagi bilan bir xil normallashtiriladi (`+998XXXXXXXXX`, `leads.phone` ham shu shaklda). Shu raqamda bugundan keyingi `pending`/`confirmed` bron bo‘lsa → **409** `code: existing_booking`, xabarda mavjud bron sanasi/vaqti, holati va aloqa yo‘li (`settings.phone` + Telegram). Mijoz o‘zi bekor qila oladigan oqim yo‘q — bekor qilish admin orqali (Bron kalendari), shundan keyin yangi bron mumkin.
  2. **Sayt bo‘yicha kunlik circuit-breaker:** oxirgi 24 soatda yaratilgan va hali `pending` bronlar soni `booking_rules.daily_cap` ga yetsa → **503** `code: daily_cap` («qo‘ng‘iroq qiling / ariza qoldiring»), admin Telegram’ga (24 soatda 1 marta) ogohlantirish. Standart **60**, admin sozlaydi (Bron → Ish vaqti → «Kunlik bron chegarasi», 10…500). Admin bronlarni tasdiqlasa/bekor qilsa hisob kamayadi — haqiqiy gavjum kunda admin tasdiqlab borsa to‘xtamaydi.
  3. **IP:** 4 ta / 10 daqiqa (o‘zgarmadi) + yangi **10 ta / 24 soat** (`booking_day`).
- **Natija:** IP’lar sonidan qat’i nazar sutkada ko‘pi bilan 60 ta soxta `pending` (kalendarning ~20%), har biri alohida haqiqiy ko‘rinishdagi +998 raqam talab qiladi.

### 11.4. Global login blokidan admin DoS (4-masala)
- **Muammo:** global 30 xato / 15 daqiqa, IP bo‘yicha 8 → 4 ta IP (4×8=32) haqiqiy adminni cheksiz bloklay olardi (sirpanuvchi oyna — hujum davom etsa blok ham davom etadi). v12 da jonli qayta ishlab chiqarildi: 4 IP → admin to‘g‘ri parol bilan 429.
- **Tanlangan yechim (a + c):**
  - Global chegara **30 → 50** (`LOGIN_GLOBAL_MAX`): endi kamida **7 ta IP** kerak (7×8=56); 4 IP bilan admin kira oladi (sinalgan). Chegarani yanada oshirish taqsimlangan parol tanlashga qarshi himoyani susaytirardi.
  - **Telegram orqali bir martalik chiqish:** v7 da ulangan admin Telegram chatidan botga `/unlock` → bot 10 daqiqalik havola yuboradi `https://DOMEN/admin.html#unlock=<128-bit kod>`. Kod bazada faqat sha256 xeshi bilan saqlanadi, **bir martalik** (muvaffaqiyatli kirishda o‘chadi), yangi `/unlock` eskisini bekor qiladi. U **faqat global blokni** chetlab o‘tadi — parol, IP bloki, sessiya/CSRF modeli o‘zgarmaydi. Faqat `tg_admin_chat_id` ga teng private chat va Telegram secret-header tekshiruvidan o‘tgan webhook qabul qilinadi; begona chatdagi `/unlock` oddiy murojaat sifatida qoladi. Kod URL **fragmentida** (serverga/loglarga bormaydi), `admin.js` uni darhol manzil satridan o‘chiradi. Global blok boshlanganda admin chatiga (15 daqiqada 1 marta) «`/unlock` yuboring» xabari boradi. Audit: `login_unlock_issued`, `login_unlock_used`.
  - (b) varianti (to‘g‘ri login bilan farqlash) qo‘llanilmadi: tizimda login yo‘q (faqat parol), va javob farqi parol haqida signal berardi.
- **Qoldiq xavf:** Telegram admin chati ulanmagan bo‘lsa yoki Telegram ishlamasa, ≥7 IP’li hujumchi adminni hujum davom etguncha bloklay oladi (hujum to‘xtagach 15 daqiqada o‘zi ochiladi). Telegram akkaunti o‘g‘irlansa ham parolsiz kirib bo‘lmaydi.

### 11.5. SMTP: shifrlanmagan ulanishda AUTH yo‘q (5-masala)
- v12: `smtp_secure=none` + login bo‘lsa `AUTH LOGIN` login/parolni base64 (ochiq matn) bilan yuborardi — soxta SMTP serverda jonli ushlandi.
- v13: `tls`/`ssl` bo‘lmasa va login bor bo‘lsa — AUTH yuborilmaydi, xat yuborilmaydi, aniq xato («tls (587) yoki ssl (465) tanlang, yoki login/parolni o‘chiring») + audit `smtp_plaintext_auth_refused`. Kanallar → Email’da qizil ogohlantirish (`smtp_plaintext_auth`). Loginsiz `none` (ichki relay) avvalgidek ishlaydi.

### 11.6. Admin kiritish turlari (6-masala)
- v12: `clean_text()` massivni `''` ga aylantirardi → `{"id":1,"name":["x"]}` lead ismini jim o‘chirardi; `admin/channels/save` da `(string)` massivga qo‘llanib «Array to string conversion» ogohlantirishi.
- v13 (api.php): admin POST marshrutlarida yuqori darajadagi har qanday massiv/obyekt → **400** («Invalid field type: …»), faqat tuzilmaviy kalitlar bundan mustasno (`ADMIN_STRUCTURED_KEYS`: `rules`, `ids`, `tags`, `result_metrics`, `variants`). Bu blog/keys/fikr/A-B/lead/kanal marshrutlarining hammasini qamraydi. `admin/lead/update` matn maydonlari faqat string (son/null ham 400), `name` bo‘sh bo‘lsa 422. `admin/channels/save` `key`/`value` string bo‘lishi shart.

### 11.7. Blog Markdown (7-masala)
- Bosqichlar tartibi: `kod` → `[matn](url)` placeholder’larga ajratiladi → qolgan matnga qalin/kursiv → tiklash. Havola matni ichida qalin/kursiv avvalgidek ishlaydi. `//host` va `/\host` (brauzer protokolga nisbiy deb tushunadi) — tashqi havola: `rel="noopener nofollow" target="_blank"` (mavjud `https://` konvensiyasi bilan bir xil).
- Regressiya: seed blog/keys kontenti va 14 ta oddiy holat v12 bilan bayt-bayt bir xil; yagona ataylab farq — `` `kod **x**` `` ichida endi qalin qo‘llanmaydi (to‘g‘ri Markdown xulqi). `tests/26` (100 tekshiruv, teglar muvozanati bilan).

### 11.8. Admin bo‘limi yuklanish poygasi (8-masala)
- `boot()` boshlang‘ich `#hash` bo‘limini endi `DOMContentLoaded` dan keyin ochadi (barcha `defer` modullar ro‘yxatdan o‘tgan). Muhim nozik joy: `defer` skript bajarilayotganda `readyState` allaqachon `interactive`, shuning uchun `readyState` ga qaramay DCL (zaxira: `load`) kutiladi. `me`/`admin/content` so‘rovlari kutilmaydi (parallel) — sezilarli kechikish yo‘q. Isbot: Playwright’da `admin-channels.js` 2.5 s kechiktirilganda v12 `#channels` bo‘sh (innerHTML 0), v13 to‘ldirilgan.

### 11.9. Tarjima SSRF: DNS-rebinding TOCTOU (9-masala)
- `translate_resolve_pinned()` xostni bir marta resolve qiladi, barcha IP’lar ochiq diapazonda bo‘lishi shart, so‘ng curl’ga `CURLOPT_RESOLVE => ["host:port:ip1,ip2"]` beriladi — curl DNS’ni qayta so‘ramaydi, TLS SNI va sertifikat asl xost nomi bo‘yicha tekshiriladi. `CURLOPT_PROTOCOLS = HTTPS`. IP literal URL — qadash kerak emas.
- Qoldiq: server tizim darajasidagi HTTP(S) proxy (`https_proxy` env) orqali chiqsa, nomni proxy resolve qiladi — bunda qadash (va umuman IP tekshiruvi) proxy tomonida; shared hostingda odatda proxy yo‘q.

### 11.10. `resume.html` CSP izohi (10-masala)
- Eskirgan izoh («onclick uchun `'unsafe-inline'` kerak») haqiqiy holatga moslandi: `script-src 'self'`, inline ishlovchilar yo‘q; `'unsafe-inline'` faqat `style-src` da (inline `<style>` va bitta `style=""`).

### 11.11. Kod sifati (bonus)
- **Login global fayli** endi `rate_file_open()` orqali (qo‘lda `fopen`+`flock` takrori olib tashlandi); chegaralar konstantalarda (`LOGIN_WINDOW=900`, `LOGIN_IP_MAX=8`, `LOGIN_GLOBAL_MAX=50`). IP blok xulqi `tests/10` bilan o‘zgarmagani tasdiqlangan.
- **Bazaviy URL:** 3 ta implementatsiya → bitta `site_base_url()` (11.2).
- **PHP `esc()`:** `site_lib.php` va `ru.php` (hamda `setup.php` dagi `htmlspecialchars`) → common.php dagi yagona `esc()`. `ru.php` natijasida `'` endi `&#039;` (avval `&apos;`) — brauzer uchun bir xil.
- **JS `esc()` (admin.js va cms.js) birlashtirilmadi:** ular alohida kontekstlar (admin panel / ommaviy sayt, alohida CSP va yuklanish), umumiy fayl qo‘shish ommaviy sahifaga admin kodini yoki admin’ga ommaviy skriptni tortardi. Admin modullari allaqachon `admin.js` dagi bitta `esc` ni (`DARIKO_ADMIN_EXT`) ishlatadi. Farq: `cms.js` dagi `esc` `s||''` ishlatadi (`0` → `''`) — hozirgi chaqiruvlarda son uzatilmaydi, shuning uchun o‘zgartirilmadi.

### 11.12. Testlar
- `tests/21_pdf_active_content_bypass`, `22_host_header_poisoning`, `23_booking_flood_protection`, `24_login_global_unlock`, `25_admin_input_type_validation`, `26_markdown_renderer_unit`, `27_translate_ip_pinning_unit`; `11_login_lockout_global` yangi chegaraga (50) moslandi. `run.php` endi `mail()` chiqishini `$ROOT/mail.out` ga yozadi (email havolalarini tekshirish uchun) va `DARIKO_SITE_URL` ni test serveriga o‘tkazmaydi.

## 12. v14 — 2-audit tuzatishi: `booking_create()` da baza band bo‘lganda uncaught exception (1 ta masala)

**Topilma (2-mustaqil audit, 40 ta bir vaqtdagi `booking_create()` chaqiruvi, kunlik chegara=10):** `crm_lib.php` da `$db->exec('BEGIN IMMEDIATE');` tranzaksiyaning qolgan qismini o‘rab turgan `try` blokidan **tashqarida** turardi. Og‘ir yuk ostida (ko‘p jarayon bir vaqtda yozishga uringanda) SQLite'ning `busy_timeout`'i (5000 ms, `common.php`) tugasa, `BEGIN IMMEDIATE`'ning o‘zi `SQLSTATE[HY000]: General error: 5 database is locked` xatosini tashlardi — bu `try` dan tashqarida bo‘lgani uchun xom, tutilmagan PHP exception sifatida tarqalardi. `api.php` da yuqori darajadagi `try/catch`/`set_exception_handler` yo‘qligi sababli bu stack-trace sizib chiqishi mumkin bo‘lgan xom PHP fatal xatosi / 500 javobi sifatida chiqib ketardi — loyihaning boshqa joylaridagi (masalan, v12 B2: rate-limit storage xatosi) «fail-closed» (tushunarli 503 bilan yopiq) falsafasiga mos kelmasdi. **Ma’lumotlar yaxlitligi buzilmagan edi** — poyga ostida ham kunlik chegara aniq ushlanib turardi (40 urinishdan aniq 10 ta `pending` bron, 0 ta dublikat); bu sof xato-javob/mavjudlik (availability) masalasi edi.

**Tuzatish** (`public_html/crm_lib.php`):
- `$db->exec('BEGIN IMMEDIATE');` `try` bloki **ichiga** ko‘chirildi.
- Yangi `pdo_is_locked(PDOException $e): bool` — SQLite band/qulflangan xatoni (SQLSTATE `HY000`, drayver kodi 5 = `SQLITE_BUSY` yoki 6 = `SQLITE_LOCKED`) mavjud `pdo_is_constraint_violation()` uslubida aniqlaydi (kod bo‘yicha, matn/til bog‘liq emas).
- `booking_create()` dagi `catch (PDOException $e)` bloki endi (mavjud `existing_booking`/409 va `daily_cap`/503 konvensiyasiga mos) `pdo_is_locked($e)` bo‘lsa: `audit_log('booking_db_busy')`, `Retry-After: 5`, toza `json_reply(503, [...'code' => 'db_busy'])` qaytaradi — boshqa (kutilmagan) PDOException turlari avvalgidek qayta tashlanadi (`throw $e`), ya’ni haqiqiy kutilmagan xatolar hamon ko‘rinadi, faqat band-bazadek kutilgan holat jim yutilmaydi.
- Qulflash/tranzaksiya mantig‘i, kunlik chegara va dublikat tekshiruvi **o‘zgartirilmadi** — faqat xato-javob yo‘li tuzatildi.

**Tasdiqlash:** o‘zgarishsiz nusxada (v13) qulfni 2-ulanish orqali 7 soniya ushlab turib `booking_create()` chaqirilganda aynan `crm_lib.php:763` (`BEGIN IMMEDIATE`) da `Uncaught PDOException ... database is locked` + stack-trace tasdiqlandi (exit kod 255). Tuzatilgandan keyin xuddi shu stsenariy toza `{"error":"...","code":"db_busy"}` (503) qaytaradi, `audit.log` ga faqat `booking_db_busy` yoziladi, stack-trace yo‘q. 40 ta bir vaqtdagi chaqiruv (kunlik chegara=10) qayta yurgizildi: 10 ta `ok`, 30 ta `daily_cap` (503) — barchasi toza JSON, 0 ta xom exception. Doimiy regressiya testi: `tests/28_booking_db_busy.php` (ikkinchi ulanish orqali qulfni sun’iy ushlab turadi, server javobi 503/`db_busy` ekanini, javobda stack-trace yo‘qligini va `audit.log`da `booking_db_busy` borligini tekshiradi).

## 13. Yakuniy holat (2 ta mustaqil audit)

Ushbu paket **ikki mustaqil, run-run (qayta tekshiruvchan) ravishda o‘tkazilgan adversarial xavfsizlik auditidan** o‘tgan: 1-audit (v13, 10 ta masala — 11-bo‘lim) va 2-audit (v14, yuqoridagi 1 ta masala — 12-bo‘lim, qolgan 9 tasi yangi, mustaqil «aylanib o‘tish» urinishlari bilan qayta tekshirilib, mustahkam deb topildi). Barcha topilmalar hal qilingan va avtomatik testlar bilan mustahkamlangan (`tests/run.php`, 28 ta test). Joylashtirish oldidan: `ISHGA_TUSHIRISH_UZ.md` — yagona tartiblangan ishga tushirish ro‘yxati.
