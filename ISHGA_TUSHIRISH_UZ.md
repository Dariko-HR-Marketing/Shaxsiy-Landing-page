# DARIKO — saytni ishga tushirish: bosqichma-bosqich ro‘yxat

**Shu yerdan boshlang.** Bu hujjat — barcha qo‘lda bajariladigan ishlarning **yagona tartiblangan ro‘yxati**. Har bir bandni bajargach `[ ]` ni `[x]` ga o‘zgartiring (yoki chop etib, qalam bilan belgilang). Batafsil tushuntirish kerak bo‘lsa — band oxiridagi «→» havolasi tegishli qo‘llanmaga olib boradi:

- `README_UZ.md` — o‘rnatish va o‘zgarishlar tarixi
- `ARXITEKTURA_UZ.md` — texnik tuzilma, hosting talablari, cron
- `CRM_QOLLANMA_UZ.md` — Telegram, Instagram/Facebook, Email ulash
- `SEO_QOLLANMA_UZ.md` — Google, Yandex, 2GIS, Bing

Bosqichlarni **tartib bilan** bajaring: keyingi bosqich odatda oldingisiga tayanadi (masalan, Telegram va Meta HTTPS’siz ishlamaydi).

---

## 1-bosqich: Hosting va domen

- [ ] Hosting PHP **8.2+** va kengaytmalar: `gd`, `mbstring`, `pdo_sqlite`, `curl` yoqilgan. → README_UZ.md, «Hosting talablari»
- [ ] Apache `mod_rewrite` (yoki LiteSpeed rewrite) yoqilgan — `/blog/` va `/case-studies/` shunga bog‘liq. → README_UZ.md, v8 bo‘limi
- [ ] `memory_limit` ≥ 256M, `post_max_size` ≥ 6M (cPanel → «Select PHP Version» / «MultiPHP INI Editor»).
- [ ] Arxivni chiqaring: `dariko_php/public_html/` va yonida `dariko_php/storage/`. → README_UZ.md, «O‘rnatish» 1
- [ ] Domen **document root** = `dariko_php/public_html` (storage ochiq web papkada BO‘LMASIN). → README_UZ.md, «Hosting talablari»
- [ ] PHP foydalanuvchisi `storage/`, `public_html/uploads/` va `public_html/sitemap.xml` ga yoza oladi. → ARXITEKTURA_UZ.md, 6-bo‘lim
- [ ] HTTPS sertifikati o‘rnatilgan (cPanel → SSL/TLS yoki AutoSSL / Let’s Encrypt) va `https://` ochiladi.
- [ ] Domen `dariko.uz` bo‘lmasa — SEO fayllaridagi manzilni almashtiring (bitta buyruq). → ARXITEKTURA_UZ.md, 6-bo‘lim «Domen»
- [ ] **`DARIKO_SITE_URL`** env o‘zgaruvchisini qo‘ying (masalan `DARIKO_SITE_URL=https://dariko.uz`; cPanel → «Environment Variables» yoki `.htaccess` da `SetEnv DARIKO_SITE_URL https://dariko.uz`). v13 dan boshlab sayt so‘rovdagi `Host` sarlavhasiga **ishonmaydi**: tasdiqlash xatlari, webhook manzillari va canonical shu qiymatdan (bo‘lmasa standart `https://dariko.uz` dan) olinadi. Sozlanmasa Admin → Kanallar’da qizil ogohlantirish chiqadi. → ARXITEKTURA_UZ.md, 11.2
- [ ] `https://DOMEN/robots.txt`, `/sitemap.xml`, `/llms.txt`, `/blog/` ochilishini tekshiring. → SEO_QOLLANMA_UZ.md, 0-bo‘lim

## 2-bosqich: Xavfsizlik (admin parol, setup.php)

- [ ] `https://DOMEN/setup.php` ni oching; File Manager’da `storage/setup_token.txt` dagi kodni o‘qing. → README_UZ.md, «O‘rnatish» 3
- [ ] **Kamida 16 belgili, noyob** admin parolini o‘rnating (standart parol yo‘q). → README_UZ.md, «O‘rnatish» 4
- [ ] `public_html/setup.php` faylini hostingdan **o‘chiring** (File Manager → Delete). Admin → Xavfsizlik bo‘limida «setup.php yo‘q» ko‘rinishi kerak.
- [ ] `https://DOMEN/admin.html` ga kiring; Admin → **Xavfsizlik** → server tekshiruvida hamma band yashil (HTTPS, pdo_sqlite, gd, curl, storage yozish).
- [ ] Parolni parol menejerida saqlang; boshqa hech kimga yubormang.

## 3-bosqich: Kontent va asosiy ma’lumotlar

- [ ] Admin → **Sozlamalar**: telefon, email, Telegram, Instagram — to‘g‘ri. → SEO_QOLLANMA_UZ.md, «Qoida №1» (NAP)
- [ ] Manzil bitta variantga keltirilgan (Matnlar: `t202` va `t167`). → SEO_QOLLANMA_UZ.md, «Eslatma»
- [ ] Admin → **Matnlar**: o‘zbekcha va ruscha matnlarni ko‘rib chiqing (ruscha maydonlar tekshirilgan). → README_UZ.md, «Tarjima»
- [ ] Faqat `storage/content.json` oldingi versiyadan olib kelingan bo‘lsa: forma tugmasi matnlari t188, t221, t241 ni yangilang. → README_UZ.md, v7 bo‘limi
- [ ] Admin → **Kengash va bosh surat**: suratlar, PDF rezyumelar, ko‘rinish belgilari (bosh sahifa surati belgisi — o‘zingiz xohlagancha). → README_UZ.md, v4/v5/v9
- [ ] Admin → **Keyslar**: 3 ta NAMUNA qoralamani haqiqiy ma’lumot bilan to‘ldirib nashr qiling yoki o‘chiring. → README_UZ.md, v7/v8
- [ ] Admin → **Mijozlar fikri**: faqat haqiqiy fikrlar qo‘shing (ixtiyoriy).
- [ ] (Ixtiyoriy) Avtomatik UZ→RU tarjima: server environment’da `DARIKO_TRANSLATE_URL` va `DARIKO_TRANSLATE_KEY`. → README_UZ.md, «Tarjima»

## 4-bosqich: Email (SMTP)

- [ ] Hosting pochtasini yarating (masalan `info@DOMEN`). → CRM_QOLLANMA_UZ.md, 3-bo‘lim
- [ ] Admin → **Kanallar sozlamalari → Email (SMTP)**: server, port, shifrlash, login, parol, jo‘natuvchi. → CRM_QOLLANMA_UZ.md, 3.1
- [ ] «Shifrlash» = `tls` (587) yoki `ssl` (465). v13: `none` + login/parol bo‘lsa xat **yuborilmaydi** (parol ochiq matnda ketmasligi uchun) — Kanallar’da ogohlantirish ko‘rinadi. → ARXITEKTURA_UZ.md, 11.5
- [ ] «Xabarnoma emaili»ni kiriting. → CRM_QOLLANMA_UZ.md, 3.2
- [ ] **Test xat yuborish** — xat keldi (spam papkasini ham tekshiring). → CRM_QOLLANMA_UZ.md, 3.3
- [ ] Domen DNS’ida SPF, DKIM, DMARC yozuvlari. → CRM_QOLLANMA_UZ.md, 3.4

## 5-bosqich: Telegram bot

- [ ] @BotFather’da bot yarating, tokenni oling (hech kimga yubormang). → CRM_QOLLANMA_UZ.md, 1.1
- [ ] Admin → Kanallar → Telegram: token → **Saqlash** → **Webhookni o‘rnatish** («Bot: @…» chiqadi). → CRM_QOLLANMA_UZ.md, 1.2
- [ ] **Admin chatni ulash** → botga `/admin KOD` yuboring → **Test xabar**. → CRM_QOLLANMA_UZ.md, 1.3
- [ ] Eslab qoling: admin kirishi **global bloklansa** (ko‘p IP’dan parol tanlash hujumi), shu admin chatdan botga `/unlock` yuboring — 10 daqiqalik bir martalik havola keladi (parol baribir kerak). → ARXITEKTURA_UZ.md, 11.4
- [ ] Sinov: boshqa akkauntdan botga yozing → CRM’da «Telegram» murojaati chiqadi.
- [ ] Sinov: saytdagi aloqa formasidan ariza yuboring → CRM’da paydo bo‘ladi va Telegram’ga xabar keladi.

## 6-bosqich: Meta (Instagram / Facebook) — ixtiyoriy

Bir necha kundan bir necha haftagacha davom etadi (Meta tekshiruvi). Sayt usiz ham to‘liq ishlaydi.

- [ ] Facebook sahifasi + unga ulangan Instagram professional akkaunti; Instagram’da «Allow access to messages». → CRM_QOLLANMA_UZ.md, 2.1
- [ ] Meta Business portfolio va biznes verifikatsiyasi. → CRM_QOLLANMA_UZ.md, 2.1
- [ ] Meta Developer ilovasi, Messenger + Instagram mahsulotlari, Privacy URL = `/privacy.html`, App Secret nusxalandi. → CRM_QOLLANMA_UZ.md, 2.2
- [ ] Admin → Kanallar → Meta: Verify token + App Secret saqlandi; Meta’da Callback URL/Verify token → «Verify and save»; `messages` obunasi. → CRM_QOLLANMA_UZ.md, 2.3
- [ ] Muddatsiz sahifa tokeni → Admin → «Page access token». → CRM_QOLLANMA_UZ.md, 2.4
- [ ] Development rejimida tester akkaunt bilan sinov + ekran videosi. → CRM_QOLLANMA_UZ.md, 2.5
- [ ] App Review (Advanced Access) → tasdiqdan keyin **Live** rejim. → CRM_QOLLANMA_UZ.md, 2.6

## 7-bosqich: Cron vazifalari

cPanel → **Cron Jobs**. `/yo‘l/` o‘rniga hostingdagi haqiqiy yo‘lni yozing (masalan `/home/USER/dariko_php`). → ARXITEKTURA_UZ.md, 8.5

- [ ] Analitika tozalash: `17 3 * * *  php /yo‘l/storage/cleanup_analytics.php`
- [ ] Eski yuklamalarni tozalash: `30 3 * * 0  php /yo‘l/storage/cleanup_uploads.php --delete` (avval `--delete`siz qo‘lda ishga tushirib ro‘yxatni ko‘ring). → README_UZ.md, texnik tuzatishlar 5
- [ ] Holat tekshiruvi: `0,10,20,30,40,50 * * * *  php /yo‘l/storage/healthcheck.php --url=https://DOMEN/`
- [ ] Bir marta `php /yo‘l/storage/healthcheck.php --test-alert` — Telegram/email ogohlantirishi keldi. Admin → Kanallar → «Holat tekshiruvi»da oxirgi natija ko‘rinadi.
- [ ] Tashqi kuzatuv: UptimeRobot (yoki shunga o‘xshash) da `https://DOMEN/` uchun monitor. → ARXITEKTURA_UZ.md, 8.6

## 8-bosqich: SEO va biznes profillar

- [ ] Google Search Console: domen tasdiqlash (DNS TXT), `sitemap.xml` yuborish, bosh sahifa uchun «Request indexing». → SEO_QOLLANMA_UZ.md, 1
- [ ] Google Business Profile: yaratish, **tasdiqlash** (faqat egasi), ish vaqti, logo, foto, xizmatlar. → SEO_QOLLANMA_UZ.md, 2
- [ ] Yandex Webmaster (sitemap, regionallik) va Yandex Business. → SEO_QOLLANMA_UZ.md, 3
- [ ] 2GIS. → SEO_QOLLANMA_UZ.md, 4
- [ ] Bing Webmaster Tools (Search Console’dan import). → SEO_QOLLANMA_UZ.md, 5
- [ ] Barcha profillarda NAP saytdagidek harfma-harf bir xil. → SEO_QOLLANMA_UZ.md, «Qoida №1»
- [ ] Rich Results Test’da `LocalBusiness` topildi. → SEO_QOLLANMA_UZ.md, 1.5

## 9-bosqich: Yakuniy tekshiruv

- [ ] Bosh sahifa telefonda va kompyuterda: menyu, UZ/RU almashtirish, «Konsultatsiya» oynasi, Telegram tugmasi ishlaydi.
- [ ] «Konsultatsiya» → «Vaqtni tanlash»: bo‘sh vaqtlar ko‘rinadi; sinov broni → Admin → **Bron kalendari**da paydo bo‘ladi (keyin bekor qiling).
- [ ] Aloqa formasi → CRM’ga tushdi + Telegram/email xabarnomasi keldi.
- [ ] `/blog/`, bitta maqola, `/case-studies/`, `/privacy.html`, cookie banneri («Qabul qilish» → xarita yuklanadi).
- [ ] `/resume.html` ochiladi, «Chop etish / PDF» ishlaydi (ixtiyoriy).
- [ ] Admin → Umumiy ko‘rinish: tashrif statistikasi yozila boshladi.
- [ ] Admin → Newsletter: «Ommaviy obuna marshruti» **o‘chiq** (saytda obuna formasi yo‘q — o‘chiq qolsin). → ARXITEKTURA_UZ.md, 10.1
- [ ] Zaxira nusxa: `storage/` (ayniqsa `content.json`, `auth.json`, `dariko.sqlite`, `analytics.sqlite`) va `public_html/uploads/` — hosting zaxirasi yoki qo‘lda, muntazam. → README_UZ.md, «O‘rnatish» 6
- [ ] (Texnik xodim uchun, ixtiyoriy) PHP CLI bor kompyuterda paket testlari: `php tests/run.php` → «0 xato». → ARXITEKTURA_UZ.md, 10.9

## Keyinchalik muntazam

- [ ] Har oy: Search Console / Yandex Webmaster xatolari, admin statistika. → SEO_QOLLANMA_UZ.md, 7
- [ ] Har yili: `.well-known/security.txt` dagi `Expires` sanasini yangilash. → SEO_QOLLANMA_UZ.md, 7
- [ ] Manzil/telefon o‘zgarsa: admin + `index.html` JSON-LD + `llms.txt` + barcha xarita profillari. → SEO_QOLLANMA_UZ.md, 7
