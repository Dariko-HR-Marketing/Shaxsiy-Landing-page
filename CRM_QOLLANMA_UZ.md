# DARIKO — CRM kanallarini ulash qo‘llanmasi (Telegram, Instagram, Facebook, Email)

Sayt tomonidagi texnik qism (webhook qabul qiluvchilar, maxfiy kalitlarni saqlash, xabarlarni CRM ga yozish, admin paneldan javob yuborish) **tayyor**. Quyidagi bosqichlar faqat akkaunt egasi bajarishi mumkin bo‘lgan **qo‘lda** sozlashlardir.

| Kanal | Qancha vaqt | Tashqi tekshiruv kerakmi |
|---|---|---|
| Telegram bot | 5–10 daqiqa | Yo‘q — token qo‘yilgach darhol ishlaydi |
| Email (SMTP) | 10–20 daqiqa | Yo‘q (hosting/pochta ma’lumotlari kerak) |
| Instagram + Facebook Messenger | bir necha kundan bir necha haftagacha | **Ha** — Meta biznes verifikatsiyasi va App Review |

**Muhim shart:** sayt **HTTPS** orqali ochilishi kerak (Telegram ham, Meta ham faqat `https://` webhookni qabul qiladi).

---

## 1. Telegram bot (eng tez yo‘l)

### 1.1. Bot yaratish
1. Telegram’da **@BotFather** ni oching (ko‘k belgili rasmiy bot).
2. `/newbot` yuboring.
3. Bot nomini yozing, masalan: `DARIKO Konsalting`.
4. Username yozing — `bot` bilan tugashi shart, masalan: `dariko_uz_bot`.
5. BotFather token beradi: `1234567890:AAH...` ko‘rinishida. **Uni hech kimga yubormang** — token bilan bot ustidan to‘liq nazorat olinadi.
6. (Ixtiyoriy) `/setdescription`, `/setuserpic` — bot tavsifi va rasmi.

### 1.2. Admin panelga ulash
1. `admin.html` → **Kanallar sozlamalari** → **Telegram bot**.
2. «Bot token» maydoniga tokenni qo‘yib **Saqlash**.
3. **Webhookni o‘rnatish** tugmasini bosing. Tizim:
   - tokenni tekshiradi (`getMe`),
   - maxfiy webhook kalitini avtomatik yaratadi,
   - Telegram’ga `https://SIZNING-DOMEN/api.php?route=telegram/webhook` manzilini ro‘yxatdan o‘tkazadi (`setWebhook`).
   Muvaffaqiyatli bo‘lsa, yonida `Bot: @dariko_uz_bot` chiqadi.
4. Sinov: boshqa Telegram akkauntdan botga «Salom» yozing → **CRM** bo‘limida yangi «Telegram» murojaati paydo bo‘ladi (ro‘yxat 20 soniyada yangilanadi). Murojaatni ochib javob yozing — mijozga bot nomidan boradi.

> Eslatma: bot mijozga faqat mijoz botga **birinchi bo‘lib yozgandan keyin** xabar yubora oladi (Telegram qoidasi). Saytdagi mavjud suzuvchi Telegram vidjeti (shaxsiy akkauntingizga havola) o‘zgarmagan — bu alohida narsa. Xohlasangiz, keyinchalik uning havolasini `t.me/dariko_uz_bot` ga almashtirishingiz mumkin (Admin → Sozlamalar → Telegram username).

### 1.3. Admin xabarnomalari (yangi murojaat / bron / server muammosi)
1. **Admin chatni ulash** tugmasini bosing — 8 belgili kod chiqadi (15 daqiqa amal qiladi).
2. O‘z shaxsiy Telegram’ingizdan botga `/admin KOD` yuboring. Bot «✅ ulandi» deb javob beradi.
3. **Test xabar** tugmasi bilan tekshiring.
Endi har bir yangi ariza, bron, Instagram/Facebook murojaati va `healthcheck.php` ogohlantirishi shu chatga keladi.

---

## 2. Instagram va Facebook Messenger (Meta)

**Oldindan halol ogohlantirish:** bu kanal kod tayyor bo‘lsa ham darhol ishlamaydi. Meta boshqa odamlarning xabarlarini o‘qish/javob berish ruxsatlarini (`pages_messaging`, `instagram_manage_messages`) faqat **App Review** dan keyin beradi. Review odatda bir necha ish kunidan bir necha haftagacha davom etadi, rad etilishi va qayta topshirish talab qilinishi mumkin. Meta talablari va menyu nomlari vaqt o‘tishi bilan o‘zgaradi — quyidagi bosqichlar umumiy yo‘nalish; har bir qadamda Meta’ning o‘z yo‘riqnomasini ham tekshiring.

### 2.1. Oldindan kerak bo‘ladiganlar
- Shaxsiy Facebook akkaunti (ikki bosqichli himoya yoqilgan).
- **Facebook sahifasi** (Page) — DARIKO nomida. Siz uning admini bo‘lishingiz kerak.
- **Instagram professional akkaunti** (Business yoki Creator) va u Facebook sahifasiga ulangan bo‘lishi (Instagram → Sozlamalar → Akkaunt turi / Bog‘langan akkauntlar yoki Meta Business Suite orqali).
- Instagram ilovasida: Sozlamalar → Xabarlar → **«Ulangan vositalarga xabarlarga kirishga ruxsat berish»** (Allow access to messages) yoqilgan bo‘lsin.
- **Meta Business portfolio** (business.facebook.com) va, Advanced Access uchun, **biznes verifikatsiyasi** (kompaniya hujjatlari: guvohnoma, manzil, domen yoki telefon tasdig‘i).
- Saytda ochiq **Maxfiylik siyosati** (`https://SIZNING-DOMEN/privacy.html` — tayyor) — App Review uchun majburiy.

### 2.2. Meta Developer ilovasini yaratish
1. https://developers.facebook.com → **My Apps** → **Create App**.
2. Foydalanish maqsadi sifatida biznes/Messenger bilan bog‘liq turini tanlang (interfeysda «Business» yoki «Other → Business»); ilovani Business portfoliongizga bog‘lang.
3. Ilovaga **Messenger** va **Instagram** mahsulotlarini qo‘shing (Add product).
4. **App settings → Basic**: Privacy Policy URL (`/privacy.html`), App icon, kategoriya, kontakt email kiriting. Shu sahifadagi **App Secret** ni («Show») nusxalang.

### 2.3. Webhook sozlash
1. DARIKO admin → **Kanallar sozlamalari → Meta**:
   - «Verify token» — **Tasodifiy** tugmasini bosing yoki o‘zingiz 12+ belgili maxfiy so‘z yozing → **Saqlash**. Shu so‘zni eslab qoling (keyingi qadamda kerak). Saqlangandan keyin u faqat niqoblangan holda ko‘rinadi — shuning uchun avval nusxalab oling.
   - «App Secret» — 2.2-dagi qiymat → **Saqlash**.
   - «Callback URL» ni nusxalang: `https://SIZNING-DOMEN/api.php?route=meta/webhook`.
2. Meta Developer → **Messenger → Settings → Webhooks** (va **Instagram → Webhooks**): Callback URL va Verify token ni kiriting → **Verify and save**. Sayt Meta’ning tekshiruv so‘roviga (`hub.challenge`) avtomatik javob beradi.
3. Obuna maydonlari: kamida **`messages`** (tavsiya: `messaging_postbacks` ham). Instagram uchun ham `messages` ga obuna bo‘ling.
4. Sahifani ilovaga ulang (Messenger Settings → Access Tokens → sahifani qo‘shish / «Add or remove Pages») va sahifa uchun webhook obunasini yoqing.

### 2.4. Muddatsiz sahifa tokeni (Page access token)
Javob yuborish uchun **sahifa tokeni** kerak. Qisqa muddatli token 1–2 soatda eskiradi, shuning uchun muddatsizini oling:
1. **Graph API Explorer** (developers.facebook.com/tools/explorer) → ilovangizni tanlang → foydalanuvchi tokeni oling, ruxsatlar: `pages_show_list`, `pages_messaging`, `pages_manage_metadata`, `instagram_basic`, `instagram_manage_messages`, `business_management`.
2. Uni **uzoq muddatli foydalanuvchi tokeniga** almashtiring (Access Token Debugger → «Extend Access Token», yoki `oauth/access_token?grant_type=fb_exchange_token&...`).
3. Uzoq muddatli foydalanuvchi tokeni bilan `GET /me/accounts` so‘rovini bajaring — javobdagi sahifangizning `access_token` qiymati **muddatsiz sahifa tokeni** bo‘ladi (Access Token Debugger’da «Expires: Never» ko‘rinadi).
   Muqobil ishonchli yo‘l: Business Settings → **System users** → admin system user yarating → ilova va sahifani biriktiring → **Generate token** (muddatsiz).
4. DARIKO admin → «Page access token» maydoniga qo‘ying → **Saqlash**.

### 2.5. Test rejimi (Review’dan oldin)
- Ilova **Development** rejimida bo‘lganda faqat ilovada **rol**i bor odamlar (Admin/Developer/Tester — App Roles bo‘limi) sahifaga/Instagramga yozganda webhook keladi va ularga javob yuborish mumkin.
- Shu rejimda sinab ko‘ring: tester akkauntdan sahifaga yoki Instagram’ga yozing → DARIKO CRM’da «Facebook»/«Instagram» murojaati chiqishi kerak → admin paneldan javob bering.
- App Review uchun aynan shu jarayonning **ekran videosi** kerak bo‘ladi — hozir yozib oling.

### 2.6. App Review
1. **App Review → Permissions and Features**: `pages_messaging`, `instagram_manage_messages` (va ular talab qiladigan `pages_show_list`, `instagram_basic`, `pages_manage_metadata`, `business_management`) uchun **Advanced Access** so‘rang.
2. Har bir ruxsat uchun: nima uchun kerakligini yozing (masalan: «Mijozlar sahifamiz/Instagramimizga yozgan xabarlarni ichki CRM’da ko‘rish va ularga javob berish uchun»), ekran videosini yuklang (mijoz yozadi → CRM’da ko‘rinadi → admin javob beradi → mijoz javobni oladi).
3. Biznes verifikatsiyasi yakunlanmagan bo‘lsa, Advanced Access berilmaydi — avval uni tugating.
4. Tasdiqdan keyin ilovani **Live** rejimiga o‘tkazing (App Mode: Live).

### 2.7. Cheklovlar (Meta qoidalari)
- **24 soat qoidasi:** mijozning oxirgi xabaridan keyin 24 soat ichida oddiy javob yuborish mumkin. Undan keyin Meta javobni rad etishi mumkin — CRM bunda ogohlantiradi. Bunday holda telefon yoki boshqa kanal orqali bog‘laning.
- Instagram’da mijoz xabari «Requests» papkasida bo‘lsa ham webhook keladi.
- Siz Meta Business Suite’dan to‘g‘ridan-to‘g‘ri yozgan javoblar ham («echo») CRM tarixiga «chiquvchi» sifatida tushadi.
- Rasm/ovozli xabarlar CRM’da `[ilova/media]` deb ko‘rinadi — asl faylni Meta ilovasida oching.

---

## 3. Email (SMTP) — xabarnomalar va newsletter
1. Admin → **Kanallar sozlamalari → Email (SMTP)**. Hosting pochtasi (cPanel → Email Accounts) ma’lumotlari:
   - SMTP server: odatda `mail.SIZNING-DOMEN` ; Port: `465` + `ssl` yoki `587` + `tls`;
   - Login: to‘liq email (masalan `info@dariko.uz`), Parol: shu pochta paroli;
   - Jo‘natuvchi email: odatda login bilan bir xil.
   Gmail ishlatilsa: `smtp.gmail.com`, `587`, `tls`, parol o‘rniga Google akkauntdagi **App password** (2FA yoqilgan bo‘lishi shart). Gmail kuniga yuborish limitlari bor — ommaviy newsletter uchun hosting pochtasi yoki maxsus xizmat afzal.
2. «Xabarnoma emaili» — Telegram admin chat ulanmagan bo‘lsa, yangi murojaatlar shu manzilga keladi.
3. **Test xat yuborish** bilan tekshiring. SMTP bo‘sh qolsa, PHP `mail()` ishlatiladi (ko‘p hostinglarda spamga tushadi).
4. Yetkazish ishonchliligi uchun domen DNS’ida **SPF**, **DKIM**, **DMARC** yozuvlarini sozlang (hosting yordam bo‘limida ko‘rsatilgan).

---

## 4. Qaysi ma’lumot qayerga qo‘yiladi (qisqa jadval)

| Qayerdan oldingiz | DARIKO admin → Kanallar sozlamalari |
|---|---|
| @BotFather tokeni | Telegram → Bot token → Saqlash → **Webhookni o‘rnatish** |
| `/admin KOD` (botga yuboriladi) | Telegram → **Admin chatni ulash** |
| O‘zingiz o‘ylab topgan so‘z (Meta’ga ham xuddi shu kiritiladi) | Meta → Verify token |
| developers.facebook.com → App settings → Basic → App Secret | Meta → App Secret |
| `/me/accounts` yoki System user tokeni | Meta → Page access token |
| Hosting pochtasi | Email (SMTP) maydonlari |

## 5. Xavfsizlik
- Barcha kalitlar `storage/dariko.sqlite` da (web-root’dan tashqarida, 0600), brauzerga faqat oxirgi 4 belgisi bilan qaytadi, `audit.log`ga faqat niqoblangan holda yoziladi.
- Telegram webhook maxfiy sarlavha (`X-Telegram-Bot-Api-Secret-Token`), Meta webhook esa `X-Hub-Signature-256` HMAC imzo bilan tekshiriladi; noto‘g‘ri so‘rovlar rad etilib, audit jurnalida «Webhook rad etildi» deb qayd qilinadi.
- Token sizib chiqdi deb gumon qilsangiz: Telegram — @BotFather → `/revoke`; Meta — App Secret’ni «Reset» qiling va sahifa tokenini qayta yarating; so‘ng yangi qiymatlarni admin panelga kiriting.
