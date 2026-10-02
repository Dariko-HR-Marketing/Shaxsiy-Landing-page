# DARIKO — SEO va xarita tizimlari qo‘llanmasi

## Muhim: nima avtomatik, nima qo‘lda

**Saytda texnik poydevor tayyor** (bu paketda qilingan):
- `robots.txt` — Google, Yandex, Bing, DuckDuckGo, Baidu va AI botlari (GPTBot, ChatGPT, Google-Extended, ClaudeBot, PerplexityBot, CCBot va boshqalar) uchun ruxsat.
- `sitemap.xml` — barcha sahifalar ro‘yxati.
- `llms.txt` — sun’iy intellekt tizimlari uchun sayt xulosasi.
- Har sahifada `canonical`, Open Graph, Twitter Card, `robots` meta.
- JSON-LD tuzilgan ma’lumotlar: `LocalBusiness`/`ProfessionalService` (nom, manzil, telefon, email, ish vaqti, xizmat hududi, xizmatlar), `WebSite`, `Person`, `BreadcrumbList`.
- NAP (Nom / Manzil / Telefon) — saytda hamma joyda bir xil: **DARIKO HR & MARKETING · Vobkent tumani, Buxoro viloyati, O‘zbekiston · +998 91 405 35 08**.

**Qo‘lda qilinishi SHART bo‘lgan ishlar** — Google Business Profile, Yandex Business va 2GIS'da ro‘yxatdan o‘tish **faqat biznes egasi tomonidan** amalga oshiriladi: akkaunt yaratish, pochta/telefon/video orqali tasdiqlash. Buni hech qanday dastur yoki sun’iy intellekt siz uchun avtomatik bajara olmaydi. Quyida bosqichma-bosqich yo‘riqnoma.

> Qoida №1: har bir platformada nom, manzil, telefon **harfma-harf** saytdagidek yozilsin. Admin panel → Sozlamalar → Aloqa'dagi qiymatlar bilan solishtiring. Manzilni o‘zgartirsangiz — hamma joyda o‘zgartiring.
>
> Eslatma: sahifa pastida "Buxoro shahri, O‘zbekiston" ham, "Vobkent tumani, Buxoro viloyati" ham yozilgan. Haqiqiy ofis manzilini aniqlab, ikkalasini bitta variantga keltirish tavsiya etiladi (admin panel → Matnlar, `t202` va `t167`).

---

## 0. Birinchi qadam: domenni ulash
1. Sayt `https://dariko.uz` da ochilishini tekshiring (boshqa domen bo‘lsa — `ARXITEKTURA_UZ.md` 6-bo‘limdagi buyruq bilan almashtiring).
2. `https://dariko.uz/robots.txt`, `/sitemap.xml`, `/llms.txt` ochilishini tekshiring.

## 1. Google Search Console
1. https://search.google.com/search-console → "Add property" → **Domain** → `dariko.uz`.
2. Ko‘rsatilgan TXT yozuvni domen DNS sozlamalariga qo‘shing (domen sotib olingan joyda) → "Verify".
3. Chap menyu → **Sitemaps** → `sitemap.xml` kiriting → Submit.
4. **URL Inspection** → `https://dariko.uz/` → "Request indexing".
5. **Rich Results Test** (https://search.google.com/test/rich-results) — `LocalBusiness` topilganini tekshiring.

## 2. Google Business Profile (Google Xaritalar)
1. https://business.google.com → Google akkaunt bilan kiring → "Add business".
2. Nom: **DARIKO HR & MARKETING**.
3. Kategoriya: "Business management consultant" (asosiy), qo‘shimcha: "Marketing consultant", "Human resource consulting".
4. Joylashuv: agar mijozlar ofisga keladigan bo‘lsa — aniq manzil va xaritada nuqta. Aks holda "Service area business" tanlang va xizmat hududini kiriting: Toshkent, Buxoro viloyati, O‘zbekiston.
5. Telefon: `+998 91 405 35 08`, sayt: `https://dariko.uz/`.
6. **Tasdiqlash** — Google tanlaydi: SMS/qo‘ng‘iroq, email, video yozuv yoki pochta kartasi. Bu bosqichni faqat egasi bajaradi.
7. Tasdiqlangandan keyin: ish vaqti (Du–Sha 09:00–19:00), logotip, 5–10 ta sifatli foto, xizmatlar ro‘yxati (saytdagi 4 xizmat va 3 paket), tavsif.
8. Mijozlardan sharh (otziv) so‘rang va har biriga javob yozing — mahalliy reytingga eng kuchli ta’sir.

## 3. Yandex Webmaster va Yandex Business (Yandex Xaritalar)
1. https://webmaster.yandex.ru → sayt qo‘shish → `https://dariko.uz` → tasdiqlash (meta-teg, HTML fayl yoki DNS). HTML fayl usulida faylni `public_html/` ga joylang.
2. "Индексирование → Файлы Sitemap" → `https://dariko.uz/sitemap.xml`.
3. "Региональность" → Buxoro / O‘zbekiston.
4. https://yandex.ru/sprav (Yandex Business) → "Добавить организацию" → nom, manzil, telefon, sayt, ish vaqti, kategoriya ("Консалтинг", "Маркетинговые услуги", "Кадровое агентство").
5. Tasdiqlash — telefon qo‘ng‘irog‘i/SMS orqali, faqat egasi.
6. Foto, tavsif (rus tilida ham), xizmatlar.

## 4. 2GIS
1. https://account.2gis.com (yoki 2GIS ilovasi → "Добавить организацию").
2. Shahar: Buxoro (agar Vobkent xaritada bo‘lmasa — eng yaqin aholi punkti).
3. Nom, manzil, telefon, sayt, Telegram/Instagram, ish vaqti, rubrikalar (Консалтинговые услуги, Маркетинговые услуги, Кадровые агентства).
4. 2GIS moderatori telefon orqali tasdiqlaydi (1–7 kun).

## 5. Bing Webmaster Tools
https://www.bing.com/webmasters → "Import from Google Search Console" (eng oson) yoki qo‘lda qo‘shish → sitemap yuborish. Bing indeksi ChatGPT qidiruvida ham ishlatiladi.

## 6. Sun’iy intellekt (ChatGPT, Claude, Perplexity, Gemini) tizimlarida ko‘rinish
- `robots.txt` barcha AI botlarga ruxsat beradi, `llms.txt` ularga qisqa faktik xulosa beradi, JSON-LD ma’lumotlari aniq.
- AI tizimlari ko‘pincha **boshqa ishonchli manbalarga** ham tayanadi. Shuning uchun: Google/Yandex/2GIS profillari, LinkedIn sahifasi, Telegram kanal tavsifi, ommaviy maqolalar va intervyularda bir xil NAP va xizmatlar tavsifi bo‘lsin.
- Yangiliklar yoki maqolalar (blog) qo‘shish — AI manbalarida iqtibos keltirilish ehtimolini eng ko‘p oshiradigan omil.

## 7. Muntazam ishlar
| Qachon | Ish |
|---|---|
| Har oy | Search Console va Yandex Webmaster xatolarini ko‘rish; admin panel → statistika |
| Sahifa matni o‘zgarganda | `sitemap.xml` dagi `<lastmod>` sanasini yangilash |
| Manzil/telefon o‘zgarganda | Admin panel + `index.html` JSON-LD + `llms.txt` + barcha xarita profillari |
| Yiliga bir marta | `.well-known/security.txt` dagi `Expires` sanasini yangilash |

## 8. v7: Blog va keyslar
- Har bir nashr qilingan maqola `/blog/<slug>` manzilida (canonical, OG `article`, `BlogPosting` JSON-LD); `sitemap.xml` nashr/o‘chirishda avtomatik yangilanadi. Search Console → Sitemaps’da qayta yuborish shart emas, lekin yangi maqoladan keyin «URL Inspection → Request indexing» tezlashtiradi.
- RSS: `/blog.php?feed=rss`.
- Mijozlar fikrlari uchun Review/AggregateRating sxemasi ATAYLAB qo‘shilmagan (Google o‘z saytidagi o‘zi haqidagi sharhlarni rich-result sifatida ko‘rsatmaydi). Haqiqiy sharhlarni Google Business Profile’da to‘plang (2-bo‘lim).

## 9. v12: ruscha sahifa alohida URL’da
- O‘zbekcha: `https://dariko.uz/` · Ruscha: `https://dariko.uz/?lang=ru` (server ruscha matn bilan qaytaradi; `hreflang` va `sitemap.xml` ikkalasini bir-biriga bog‘laydi, `x-default` = o‘zbekcha).
- Search Console va Yandex Webmaster’da qo‘shimcha ish shart emas — sitemap orqali topiladi. Tezlashtirish uchun: Search Console → URL Inspection → `https://dariko.uz/?lang=ru` → «Request indexing»; Yandex Webmaster → «Переобход страниц».
- Tekshiruv: `https://dariko.uz/?lang=ru` sahifa kodida `<html lang="ru">` va `<link rel="canonical" href="https://dariko.uz/?lang=ru">` bo‘lishi kerak.
- Cheklov (halol): bron oynasi va «Blog/Keyslar» menyu yozuvlari hamda JSON-LD ruschaga faqat JavaScript ishlagach o‘tadi. Blog maqolalari va keyslar o‘z tilida (maqolaning `lang` maydoni) bitta URL’da. Texnik tafsilot: `ARXITEKTURA_UZ.md`, 10.10.
