# DARIKO — PHP 8.2+ hosting paketi

> **Saytni ishga tushiryapsizmi? Shu yerdan boshlang: [`ISHGA_TUSHIRISH_UZ.md`](ISHGA_TUSHIRISH_UZ.md)** — barcha qo‘lda bajariladigan ishlar (hosting, parol, SMTP, Telegram, Meta, cron, SEO) bitta tartiblangan ro‘yxatda. Quyidagi va boshqa `.md` hujjatlar — batafsil ma’lumotnoma.

Bu paket cPanel/Apache yoki LiteSpeed hosting uchun tuzilgan. Backend PHP 8.4 CLI + built-in server (`php -S`) bilan lokal sinovdan o‘tkazilgan (`php -l`, avtomatik testlar `php tests/run.php`, headless Chromium’da sahifa va admin panel). Real hostingdagi HTTPS, Apache/LiteSpeed `.htaccess`, cron va pochta sozlamalari esa faqat joylashtirilgandan keyin tekshiriladi (`ISHGA_TUSHIRISH_UZ.md`, 9-bosqich).

## Hosting talablari

- PHP 8.2+ va kengaytmalar: `gd` (rasm yuklash), `mbstring`, **`pdo_sqlite`** (majburiy: statistika, CRM, bron kalendari, blog/keyslar), **`curl`** (Telegram bot, Instagram/Facebook (Meta) integratsiyasi va avtomatik UZ→RU tarjima uchun).
- To‘liq arxitektura: `ARXITEKTURA_UZ.md`; SEO va xaritalarda ro‘yxatdan o‘tish: `SEO_QOLLANMA_UZ.md`.
- HTTPS sertifikati va domen; PHP foydalanuvchisi `storage` va `public_html/uploads` papkalariga yozishi kerak.
- **Apache `mod_rewrite`** (yoki LiteSpeed rewrite) yoqilgan bo‘lishi kerak — `/blog/` va `/case-studies/` shunga bog‘liq. Saytni `file://` orqali ochib bo‘lmaydi (v8 bo‘limiga qarang).
- `memory_limit` kamida 256M, `post_max_size` kamida 6M tavsiya qilinadi.
- Domen document root manzili **faqat** `dariko_php/public_html` papkasiga ko‘rsatilishi kerak. `storage` albatta document rootdan tashqarida qoladi. Hosting bu tuzilmani qo‘llamasa, ishga tushirmang.

## O‘rnatish

1. Arxivni serverga chiqaring: `dariko_php/public_html/` va uning yonida `dariko_php/storage/` bo‘lsin. Hostingdagi domen document root’ini `dariko_php/public_html` ga sozlang.
2. Hosting PHP sozlamalarida `gd`, `mbstring`, `pdo_sqlite`, `curl` kengaytmalari yoqilganini va HTTPS borligini tekshiring (kirgandan keyin Admin → Xavfsizlik bo‘limi ham shularni ko‘rsatadi). `storage` va `public_html/uploads` ga faqat hosting foydalanuvchisiga yozish huquqi bering; `storage`ni ochiq web papkasiga ko‘chirmang.
3. `https://SIZNING-DOMENINGIZ/setup.php` ni oching. Sahifa tasodifiy bir martalik kod yaratadi. Hosting File Manager orqali `dariko_php/storage/setup_token.txt` faylidagi kodni o‘qing. Uni boshqalarga yubormang.
4. Shu kod yordamida **kamida 16 belgili, noyob** admin parolini o‘rnating. Yaratilgach `setup_token.txt` o‘chadi. `setup.php` faylini hostingdan ham o‘chirib qo‘yish tavsiya etiladi.
5. `https://SIZNING-DOMENINGIZ/admin.html` da kirib, o‘zbekcha va ruscha matnni, logotiplar va ranglarni sinang. Kontaktlarni tekshiring.
6. `storage/content.json` (birinchi tahrirdan keyin hosil bo‘ladi), `storage/auth.json` va `public_html/uploads/` fayllarining zaxira nusxasini oling.

Admin uchun **standart parol ham, bir bosishda kirish ham yo‘q**. Oldin suhbatda yozilgan demo parolni bu paketda ishlatmang. Oldingi lokal tahrirlar kerak bo‘lsa, eski `private/content.json` faylini server o‘chirilgan paytda `storage/content.json` manziliga joylang. Parol fayllari boshqa tizimdan mos kelmaydi.

## Tarjima

Admin oynasi UZ matni o‘zgarganda RU tarjima so‘rovini yuboradi. Serverdagi `DARIKO_TRANSLATE_URL` va `DARIKO_TRANSLATE_KEY` environment variables bilan **o‘zingiz tanlagan LibreTranslate mos HTTPS provayderini** sozlang. Kalitni brauzer JS fayliga yoki sayt papkasiga yozmang. Provayder ulanmaguncha ruscha maydonni qo‘lda to‘ldiring; tarjima kelgach mazmunini tekshirib saqlang.

## v14 — 2-audit tuzatishi (1 ta masala) — YAKUNIY PAKET
Batafsil: `ARXITEKTURA_UZ.md`, 12-bo‘lim.
- **Bron yaratishda baza band bo‘lsa (yuqori yuk, 40+ bir vaqtdagi so‘rov):** `BEGIN IMMEDIATE` endi `try` ichida — SQLite `busy_timeout` (5s) tugab "database is locked" chiqsa, xom PHP fatal xato/500 o‘rniga toza `503` (`code: db_busy`, qayta urinishga undovchi xabar) qaytadi. Ma’lumotlar yaxlitligi (kunlik chegara, dublikatsiz bron) bu holatda ham avvalgidek mustahkam edi — faqat xato-javob tuzatildi.
- **Testlar:** `php tests/run.php` — 28 ta (yangi `28_booking_db_busy`).
- 2-mustaqil auditning qolgan 9 ta tekshiruvi (v13 tuzatishlari) yangi, mustaqil «aylanib o‘tish» urinishlari bilan qayta tasdiqlandi — hech qanday yangi muammo topilmadi.
- **Bu — ikki mustaqil adversarial xavfsizlik auditidan (1-audit: v13, 10 ta masala; 2-audit: v14, 1 ta masala) o‘tgan yakuniy, ishga tushirishga tayyor paket.** Audit tarixi: `ARXITEKTURA_UZ.md` 11–13-bo‘limlar. Joylashtirish ro‘yxati: `ISHGA_TUSHIRISH_UZ.md`.

## v13 — xavfsizlik auditi tuzatishlari (10 ta masala)
Batafsil: `ARXITEKTURA_UZ.md`, 11-bo‘lim.
- **PDF skaneri:** siqilgan (`FlateDecode`/`ObjStm`) oqimlar ochib tekshiriladi, `/J#61vaScript` kabi hex-escape normallashtiriladi, `/OpenAction` harakati va `/AA` rad etiladi (oddiy PDF’lar, havolalar bilan ham, o‘tadi).
- **Host sarlavhasiga ishonilmaydi:** email/webhook/canonical manzillari faqat `DARIKO_SITE_URL` yoki `https://dariko.uz` dan. **`DARIKO_SITE_URL` ni hostingda qo‘ying** (ISHGA_TUSHIRISH_UZ.md, 1-bosqich).
- **Bron flood himoyasi:** bitta telefonda bitta ochiq bron; sutkalik tasdiqlanmagan bron chegarasi (standart 60, Bron → Ish vaqti); IP: 10 ta/24 soat.
- **Login:** global blok 30 → 50; bloklanganda admin Telegram chatidan `/unlock` → bir martalik havola (parol baribir kerak).
- **SMTP:** `none` + login/parol bo‘lsa xat yuborilmaydi (parol ochiq matnda ketmaydi).
- **Admin API:** noto‘g‘ri turdagi maydonlar 400 (lead ismini jim o‘chirib yuborish xatosi tuzatildi).
- **Blog Markdown:** URL ichidagi `*`/`_` buzilmaydi, yopilmagan `<em>` yo‘q, `//host` tashqi havola.
- **Admin panel:** sekin tarmoqda boshlang‘ich bo‘lim bo‘sh qolish poygasi tuzatildi.
- **Tarjima SSRF:** tekshirilgan IP’ga qadalgan (DNS-rebinding yo‘q). `resume.html` CSP izohi yangilandi.
- **Testlar:** `php tests/run.php` — 27 ta (yangi 21–27).

## v12 — audit tuzatishlari (texnik; dizayn/matn o‘zgarmagan)
Batafsil: `ARXITEKTURA_UZ.md`, 10-bo‘lim.
- **Yangi: `ISHGA_TUSHIRISH_UZ.md`** — ishga tushirishning yagona tartiblangan ro‘yxati.
- **Newsletter API yopildi:** `newsletter/subscribe` standart holatda 404; Admin → Newsletter → «Ommaviy obuna marshruti» belgisi bilan yoqiladi (backend saqlangan).
- **Rate-limit endi jim o‘tkazmaydi:** storage ishlamasa login/ariza/bron/newsletter 503 qaytaradi va `audit.log` ga `rate_limit_storage_error` yoziladi.
- **Bron to‘qnashuvi** xabar matni emas, SQLSTATE `23000`/SQLite kod `19` bo‘yicha aniqlanadi (409 saqlangan).
- **Rasmlar yengillashdi:** logotip 135→9 KB, ikonka 218→21 KB; ishlatilmagan `partner-reference.png` (223 KB) o‘chirildi.
- **Mobil menyu** tartibi desktop bilan bir xil (… Blog, Keyslar, Aloqa); Blog/Keyslar bosilganda menyu yopiladi.
- **SQLite indekslari:** blog (`status, published_at`), A/B (`exp_id, variant`) — eski bazaga avtomatik qo‘shiladi.
- **Kod birlashtirildi:** yagona `sqlite_open()`, umumiy rate-limit primitivlari (login chegaralari o‘zgarmagan).
- **CSP:** `index.html`/`resume.html` da `script-src 'self'` (inline JS → `assets/js/main.js`, `assets/js/resume.js`).
- **Admin JS** `admin-ext.js` → `assets/js/admin/` dagi 6 ta faylga bo‘lindi.
- **Testlar:** `php tests/run.php` (20 ta). Topilgan xato tuzatildi: PDF tekshiruvi `/EmbeddedFiles` ni o‘tkazib yuborardi.
- **Ruscha URL:** `https://dariko.uz/?lang=ru` — serverda ruscha, hreflang/sitemap halol (`SEO_QOLLANMA_UZ.md`, 9-bo‘lim).

## v10 — Rezyume surati (2x)

- `resume.html` dagi "KT" o‘rniga haqiqiy foto: `public_html/assets/img/khikmatullo-turaev.jpg` (600×600 JPEG, statik — rezyume CMS'ga ulanmagan). Rasm yuklanmasa "KT" fallback ko‘rinadi.
- Avatar 2 barobar kattalashtirildi: ekran 96px → 192px, chop (A4) 26mm → 52mm; telefonda (≤820px) 160px. Hoshiya 2px → 3px.
- A4 muvozanati uchun: sidebar "Kasbiy ko‘nikmalar" bloki kartalar orasida sahifaga bo‘linishi mumkin (kartalar o‘zi bo‘linmaydi). PDF avvalgidek 2 sahifa.

## v9 — «Shaxsiy sahifa» tashqi havolasi

- Admin → «Kuzatuv kengashi va bosh sahifa surati» → «Bosh sahifa doirasi»: yangi «Shaxsiy sahifa havolasi» maydoni (`settings.personal_page_url`) va «Saytda … ko‘rsatish» belgisi (`flags.personal_page_visible`).
- Faqat tashqi havola (fayl/HTML/zip yuklash YO‘Q). Server `external_url_valid()` (common.php): faqat mutlaq `http://`/`https://`, 2048 belgigacha, bo‘sh joy/`<>"'\``/login:parol yo‘q; `javascript:`, `data:` va h.k. 400 xato bilan rad etiladi. Bo‘sh qiymat = havolani o‘chirish.
- Saytda kartochka doiraning pastki-chap burchagida («17+ Yil Tajriba» ga ko‘zgu); <768px da doira ostida, «Rezyume (PDF)» kartochkasi ostida. Belgi o‘chiq yoki havola yo‘q bo‘lsa element DOMda umuman bo‘lmaydi. Yangi oynada ochiladi (`rel="noopener noreferrer"`).
- Tuzatish: 640–767px oralig‘ida v5 «Rezyume (PDF)» kartochkasi DARIKO logotip kartochkasi ustiga tushardi — doira ostiga tushirish chegarasi 639px → 767px.

## O‘zgarishlar tarixi (texnik tuzatishlar, 2026-09)

Quyidagi muammolar aniqlanib tuzatildi (dizayn va matn mazmuniga tegilmadi):

1. **Tailwind CDN o‘rniga statik build.** `<script src="https://cdn.tailwindcss.com">` olib tashlandi; `assets/css/tailwind.build.css` (index.html) va `assets/css/tailwind.resume.build.css` (resume.html) — `npx tailwindcss` bilan oldindan kompilyatsiya qilingan statik CSS fayllar. Vizual natija aynan bir xil (bir xil klass va ranglar konfiguratsiyasi ishlatildi, spot-check bilan tekshirildi).
2. **Content-Security-Policy qo‘shildi.** *(v12: `script-src` dan `'unsafe-inline'` olib tashlandi — pastdagi v12 bo‘limi.)* `index.html`/`resume.html`'da `<meta http-equiv="Content-Security-Policy">`, `admin.html`/`privacy.html` uchun `.htaccess` orqali qat’iyroq header. Google Fonts va cdnjs (Font Awesome, QR-kod skripti) — qolgan ikkita CDN — aniq ruxsat etilgan; boshqa hamma narsa taqiqlangan. Inline `onclick`/`onsubmit` va inline `<style>` sabab `'unsafe-inline'` hali kerak (izohlarda ko‘rsatilgan); bularni olib tashlash HTML strukturasini qayta qurishni talab qiladi — bu ish doirasidan tashqarida.
3. **`common.php`'dagi `locked()` fayl-qulfi endi har doim bo‘shatiladi** — `register_shutdown_function` orqali, `reject()`/`exit()` istalgan joyda chaqirilsa ham.
4. **`storage/rate-*.json` fayllari uchun tozalash** — `api.php`'dagi login marshrutida past ehtimol bilan (~1/50 so‘rov) eskirgan rate-limit fayllari o‘chiriladi (`common.php`'dagi `cleanup_rate_limit_files()`).
5. **`public_html/uploads/` uchun tozalash skripti** — `storage/cleanup_uploads.php` (faqat CLI'dan, masalan cron orqali): `content.json`'dagi `assets`'ga bog‘lanmagan orphan rasmlarni topadi; standart holatda faqat ro‘yxatlaydi (`php storage/cleanup_uploads.php`), `--delete` bilan haqiqatan o‘chiradi.
6. **`admin/translate` uchun SSRF himoyasi kuchaytirildi** — endi `DARIKO_TRANSLATE_URL` hosti DNS orqali aniqlanadi va IP oraliqlari (loopback, private, link-local/metadata, `::1`, `fc00::/7`) tekshiriladi (`common.php`'dagi `translate_host_is_safe()`); mos kelmasa so‘rov yuborilmaydi.
7. **Fayl-asoslangan saqlash (`flock()`) — arxitektura darajasidagi cheklov, HUJJATLASHTIRILDI, TUZATILMADI.** Ma’lumotlar bazasiga o‘tish alohida qaror talab qiladi va bu ishga kiritilmagan. Buning o‘rniga: `setup.php` endi bir martalik `flock()` o‘z-o‘zini tekshiruvini bajaradi va muammo bo‘lsa ogohlantiradi (quyida batafsil).
8. **`setup.php` — 410 Gone va error_log ogohlantirishi.** Admin akkaunt yaratilgandan keyin sahifa forma o‘rniga aniq HTTP 410 xatosini qaytaradi va faylni o‘chirishni so‘raydi; har safar shu holatda ochilganda `error_log()` orqali serverga yozadi. **Faylni real o‘chirish hali ham qo‘lda/deploy skript bosqichi:** `rm public_html/setup.php`.
9. **OG/Twitter meta va JSON-LD qo‘shildi** — `index.html` (ProfessionalService) va `resume.html` (Person) `<head>`'iga, faqat sahifada mavjud bo‘lgan matn/aloqa ma’lumotlaridan foydalanilgan holda. Ko‘rinadigan sahifa mazmuni o‘zgarmagan.
10. **`cms.js`'dagi bo‘sh `alt=""` tuzatildi** — ekspert va hero rasmlari endi mavjud `aria-label`'dan olingan mazmunli `alt` matniga ega.
11. **`hreflang` teglari qo‘shildi.** *(v12 da tuzatildi: endi RU alohida URL’da — `/?lang=ru`, pastdagi v12 bo‘limi.)*
12. **Forma o‘sha paytda faqat `mailto:` ishlatardi.** *(v7 dan ariza serverga/CRM’ga saqlanadi; `mailto:` faqat zaxira.)* Serverda lead/CRM saqlash — yangi funksiya (mahsulot qarori), buzilish emas; alohida rozilik, spamdan himoya va ma’lumotlarni saqlash siyosati talab qiladi.

## v3 o‘zgarishlari (xavfsizlik, SEO, analitika, yangi admin panel)

**Xavfsizlik:** barcha `admin/*` GET marshrutlari ham CSRF talab qiladi; ikki darajali brute-force blok (IP 8/15 daq + global 30/15 daq; v13 da 50/15 daq) + tasodifiy kechikish; 60 daqiqalik harakatsizlik muddati; `password_needs_rehash`; `storage/audit.log` audit jurnali; API javoblarida qat’iy CSP/CORP/X-Robots-Tag; `.htaccess` da HSTS, COOP, kengaytirilgan Permissions-Policy, nuqtali fayllar va `.sqlite/.log/.key/.md` taqiqi, `frame-ancestors`; admin CSP inline'siz (`style-src 'self'`); `uploads/` da PHP dvigateli o‘chirildi; lokal test rejimi endi faqat `php -S` + loopback (proxy orqali aldab bo‘lmaydi); `/.well-known/security.txt` (RFC 9116); `privacy.html` dagi buzilgan `landing.html` havolasi `index.html` ga tuzatildi.

**SEO:** `robots.txt` (qidiruv + AI botlariga ruxsat), `sitemap.xml`, `llms.txt`, canonical, to‘liq OG (absolyut rasm URL, o‘lchamlar, alt, og:url), `LocalBusiness`+`ProfessionalService`+`WebSite`+`Person`+`BreadcrumbList` JSON-LD (ish vaqti, xizmat hududi, xizmatlar katalogi — faqat sahifadagi mavjud matndan). FAQ sxemasi QO‘SHILMADI — saytda haqiqiy FAQ bo‘limi yo‘q. Domen `https://dariko.uz` deb olingan — boshqacha bo‘lsa almashtiring (ARXITEKTURA_UZ.md, 6-bo‘lim).

**Analitika:** SQLite (`storage/analytics.sqlite`, birinchi so‘rovda o‘zi yaratiladi), cookie'siz beacon (`assets/js/analytics.js`), IP xeshlanadi, DNT/GPC hurmat qilinadi, 13 oy saqlanadi (`storage/cleanup_analytics.php` — cron). Admin panelda: hozir onlayn, ko‘rishlar, sessiyalar, 30 kunlik grafik, qurilma/brauzer/OT/sahifa/manba/til taqsimoti, jonli sessiyalar jadvali.

**Admin panel** to‘liq qayta ishlandi: Umumiy ko‘rinish (dashboard), Matnlar, Rasmlar, Sozlamalar, Xavfsizlik (server tekshiruvi + audit jurnali).

## Muhim cheklovlar va tekshiruv

- Hozirgi landing Google Fonts va Font Awesome (cdnjs) CDN'laridan foydalanadi; internet uzilsa font/ikonka ko‘rinishi farq qilishi mumkin (bu ataylab saqlab qolindi — dizaynni o‘zgartirmaslik uchun). Tailwind endi CDN emas, statik build.
- Lokal sinov (PHP built-in server, headless Chromium, `php tests/run.php`) production’ni to‘liq almashtirmaydi: hostingdagi HTTPS, `.htaccess` sarlavhalari/rewrite, sessiya cookie `Secure` bayrog‘i, cron va pochta yetkazilishi joylashtirilgandan keyin tekshirilishi kerak.
- PHP orqali tahrir qilingan o‘zbekcha/ruscha matnlar ishlaydi; v12 dan ruscha sahifa `/?lang=ru` alohida URL’ida serverda render qilinadi.
- Forma arizani serverga (CRM) saqlaydi; server javob bermasa yoki storage vaqtincha ishlamasa (503) — brauzer `mailto:` zaxirasini ochadi.
- **Fayl-asoslangan saqlash `flock()`ga to‘liq tayanadi — ba’zi umumiy hosting/NFS muhitlarida `flock()` ishonchli ishlamasligi mumkin.** Bu paketning "ma’lumotlar bazasisiz, fayl saqlash" arxitekturasidan kelib chiqadigan tabiiy cheklov bo‘lib, ma’lumotlar bazasiga o‘tmasdan to‘liq bartaraf etib bo‘lmaydi. Agar `setup.php` orqali (yoki qo‘lda `storage/`da yozish+`flock()` sinovi bilan) flock() ishlamayotgani aniqlansa — **hostingni almashtiring yoki ma’lumotlar bazasiga o‘tishni ko‘rib chiqing.**
- `public_html/uploads/` ichidagi eski almashtirilgan rasmlar avtomatik o‘chirilmaydi; `storage/cleanup_uploads.php`'ni qo‘lda yoki cron orqali ishga tushiring (yuqoridagi 5-band).
- `setup.php` faylini birinchi sozlashdan keyin serverdan albatta o‘chiring: `rm public_html/setup.php`.

PHP mavjud kompyuterda dastlabki tekshiruv: `php -v`; `for f in public_html/*.php storage/*.php; do php -l "$f"; done`; **avtomatik testlar: `php tests/run.php`** (20 ta test, haqiqiy `storage/` ga tegmaydi — `ARXITEKTURA_UZ.md`, 10.9). Lokal ishga tushirish: `php -S 127.0.0.1:8080 -t public_html dev/router.php` (router `/blog/`, `/case-studies/`, `/?lang=ru` qoidalarini takrorlaydi). Jonli domen uchun PHP built-in serverni ishlatmang.

---

## v4 o‘zgarishlari (qisqa)

- Hamkorlar logotip karuseli olib tashlandi (`partner-logos-transparent.png` va `partner-*` slotlari ham).
- Admin → **«Kengash va bosh surat»** bo‘limi: har bir kengash a’zosi uchun surat, PDF rezyume va «Saytda rezyume tugmasini ko‘rsatish» belgisi; bosh sahifa doirasi uchun surat va «Suratni ko‘rsatish» belgisi (o‘chiq bo‘lsa «KT»).
- Yangi `flags` bloki (`content.json`): `hero_photo_visible`, `resume_visible_1..4` — faqat `true/false`.
- PDF yuklash: `admin/upload`, `X-Asset-Slot: resume-1..4`, 10 MB gacha, `%PDF-` sarlavhasi + `%%EOF` tekshiriladi, JavaScript/Launch/ilova/forma bo‘lgan PDF rad etiladi, tasodifiy nom bilan `uploads/` ga saqlanadi, audit jurnaliga yoziladi.
- Har qanday yuklangan faylni «O‘chirish» (standartga qaytarish) mumkin (`field: asset_clear`).
- `uploads/.htaccess`: faqat `.png` va `.pdf` beriladi; PDF `application/pdf`, alohida CSP bilan.
- resume.html A4 chop etish uchun qayta ishlandi (sahifa raqami, kesilmaydigan bloklar, pt shriftlar, rang chop etiladi, mahalliy QR).

## v5 o‘zgarishlari
- Header, mobil menyu va footerdagi «Web Resume (CV)» hamda hero’dagi «To‘liq Rezyume (CV)» tugmalari olib tashlandi; resume.html faqat to‘g‘ridan-to‘g‘ri URL orqali ochiladi.
- Yangi: bosh sahifa doirasi yonida «Rezyume (PDF)» kartochkasi. Admin → «Kengash va bosh surat»: `hero-resume` PDF slot (v4 dagi `pdf_validate()` bilan bir xil tekshiruv) + `hero_resume_visible` belgisi. Belgi o‘chiq yoki PDF yo‘q bo‘lsa, element DOMda umuman bo‘lmaydi. Telefonda (<640px) kartochka doira ostiga tushadi.
- Bosh sahifa «KT» doirasi / surat mantig‘i (hero_photo_visible) o‘zgartirilmadi.
- «Kuzatuv kengashi»: raqamli nishon (01–04), ikonkali ma’lumot qatorlari, hover (faqat `hover:hover` qurilmalarda) — ko‘tarilish, yashil chegara, surat zoom, pastki aksent chizig‘i.
- Adaptivlik: karusel ustunlari xatosi tuzatildi (telefonda 4 ta tor ustun o‘rniga 1 ta), kengash ≥1024px da 4 ustun; iOS zoom’ga qarshi input 16px; ≥1920px da konteyner 88rem; resume.html telefonda 1 ustunli ekran ko‘rinishi (print CSS o‘zgarmagan); admin planshetda gorizontal skroll tuzatildi.

## v7 o‘zgarishlari (CRM, bron, kontent-marketing)
To‘liq texnik tavsif: `ARXITEKTURA_UZ.md`, 8-bo‘lim. Kanallarni ulash: **`CRM_QOLLANMA_UZ.md`**.
- **CRM** (Admin → «CRM · Murojaatlar»): sayt formasi, bron, Telegram, Instagram, Facebook murojaatlari bitta ro‘yxatda; holat, izoh, yozishmalar tarixi, Telegram/Meta orqali javob. Sayt formasi endi arizani serverga saqlaydi (server ishlamasa — avvalgi `mailto:` zaxira). Forma tugmasi matnlari (t188, t221, t241) shunga moslab yangilandi — **agar serverda `storage/content.json` allaqachon bo‘lsa**, bu matnlarni Admin → Matnlar’da qo‘lda yangilang.
- **Bron kalendari:** «Konsultatsiyaga yozilish» oynasida «Vaqtni tanlash» tabi (tashqi servis yo‘q). Admin → «Bron kalendari».
- **Blog** (`/blog/`) — 5 ta boshlang‘ich maqola; **Keyslar** (`/case-studies/`) — 3 ta rezyumedan olingan NAMUNA qoralama (nashr qilinmagan); **Mijozlar fikri** — bo‘sh (faqat haqiqiy fikrlar qo‘shiladi).
- **Newsletter** (footer, double opt-in, SMTP yoki `mail()`; *v8 da forma olib tashlandi, v12 da API ham standart holatda o‘chiq*), **WhatsApp** click-to-chat (aloqa bo‘limi va footer), **A/B testlar**, **healthcheck.php** (cron).
- Yangi talab: `mod_rewrite` (toza blog URL’lari), `curl` (Telegram/Meta), PHP foydalanuvchisi `public_html/sitemap.xml` ga yoza olishi.
- Yangi cron: `0,10,20,30,40,50 * * * * php /yo‘l/storage/healthcheck.php --url=https://dariko.uz/`

## v8 o‘zgarishlari
- **Newsletter formasi olib tashlandi** (footer’dagi «Yangiliklar» kartochkasi va uning JS’i). Backend (`newsletter.php`, `subscribers`/`campaigns` jadvallari, admin kampaniya vositasi) **o‘chirilmagan** — keyin qayta yoqish mumkin, hozir ommaviy sahifada forma yo‘q. Blog/Keyslar havolalari header va mobil menyuda qoldi.
- **Kuzatuv kengashi kartochkalaridagi 01–04 raqamli belgilar olib tashlandi** (CSS `counter`/`:before`). Qolgan dizayn o‘zgarmagan.
- **Bron kalendari:** «Kalendarni yuklab bo‘lmadi» xabarining sababi — sahifa `file://` (Explorer’da ikki marta bosish) orqali ochilgan edi: brauzer `fetch()` ni `file://` sxemasida bajarmaydi, PHP esa ishlamaydi. Server orqali (Apache/LiteSpeed yoki `php -S`) API to‘g‘ri ishlaydi. Tuzatishlar: API manzili endi skript joylashuvidan hisoblanadi (sayt sub-papkada ham ishlaydi, avval qattiq `/api.php` edi); `file://` holatida aniq tushuntirish chiqadi; xato/bo‘sh xabarlar endi 4.1rem’li ustunlarga siqilib «sinib» ko‘rinmaydi.
- **Cookie roziligi banneri** (`assets/js/consent.js`, `assets/css/consent.css`). Sayt o‘zi ommaviy tashrifchiga cookie yozmaydi; yagona cookie manbai — Google Maps iframe’i, u endi faqat «Qabul qilish» yoki «Xaritani ko‘rsatish» dan keyin yuklanadi. Tanlov `localStorage` (`dariko_consent_v1`) da 12 oy saqlanadi. Footer’da «Cookie sozlamalari» havolasi. `privacy.html#cookies` bo‘limi qo‘shildi.
- **Telegram xabarnomasi:** aloqa formasi, modal «Ariza qoldirish» va bron — uchalasi ham admin chatiga yuboriladi (tekshirildi). Endi har bir natija `storage/audit.log` ga yoziladi: `notify_sent`, `notify_failed` (xato matni bilan), `notify_skipped` (bot/chat ulanmagan). Xabar yuborilmasa ham mijozning arizasi saqlanadi.

### Blog va keyslar: mod_rewrite shart, file:// hech qachon ishlamaydi
- `/blog/`, `/blog/<slug>`, `/case-studies/` toza URL’lari `public_html/.htaccess` dagi `RewriteRule` orqali `blog.php` / `case-studies.php` ga yo‘naltiriladi. Hostingda **Apache `mod_rewrite`** yoki LiteSpeed’ning Apache-mos rewrite qo‘llovi yoqilgan bo‘lishi kerak (odatiy cPanel hostingda yoqilgan).
- Saytni **hech qachon fayl sifatida** (`file:///C:/...`, Explorer’da ikki marta bosish) ochib tekshirib bo‘lmaydi: PHP faqat server jarayoni orqali ishlaydi, `file://` da blog, bron kalendari, formalar va admin panel doim ishlamaydi — bu tuzatib bo‘lmaydigan, kutilgan holat.
- To‘g‘ridan-to‘g‘ri (rewrite’siz) tekshiruv yo‘li: `blog.php`, `blog.php?slug=<slug>`, `case-studies.php`.
- Lokal test: `php -S` `.htaccess` ni o‘qimaydi, shuning uchun router bilan ishga tushiring (paket ildizidan):
  `php -S 127.0.0.1:8080 -t public_html dev/router.php` → `http://127.0.0.1:8080/blog/`.
  `dev/router.php` faqat lokal test uchun; hostingga yuklanmaydi (u `public_html` dan tashqarida va `cli-server` dan boshqa joyda 404 qaytaradi).
- Keyslar sahifasida «Keyslar tayyorlanmoqda» ko‘rinishi — kutilgan holat: 3 ta keys NAMUNA qoralama sifatida nashr qilinmagan (admin paneldan to‘ldirib nashr qiling).
