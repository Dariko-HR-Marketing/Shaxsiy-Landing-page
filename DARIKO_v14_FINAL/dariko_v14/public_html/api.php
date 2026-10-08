<?php
declare(strict_types=1);
define('DARIKO_ENTRY', true);
require __DIR__.'/common.php';
require __DIR__.'/analytics_lib.php';
require __DIR__.'/crm_lib.php';
require __DIR__.'/site_lib.php';
send_api_security_headers();
$route = (string)($_GET['route'] ?? '');
$method = (string)($_SERVER['REQUEST_METHOD'] ?? 'GET');
if ($route === 'public' && $method === 'GET') {
    $d = content_read();
    json_reply(200, ['revision' => $d['revision'], 'texts' => $d['texts'], 'assets' => $d['assets'], 'theme' => $d['theme'], 'settings' => $d['settings'], 'flags' => $d['flags']]);
}
// Ommaviy analitika beacon'i: autentifikatsiyasiz, sessiya/cookie ochmaydi; Origin/Referer tekshiriladi,
// barcha maydonlar oq ro'yxat/regex bilan tekshiriladi, SQLite'ga faqat prepared statement bilan yoziladi.
if ($route === 'track' && $method === 'POST') {
    if (!analytics_same_origin()) { reject(403, 'Origin mismatch'); }
    if (!analytics_available()) { json_reply(202, ['ok' => false, 'disabled' => true]); }
    analytics_track(body(2048));
}
// ---------------- v7: ommaviy marshrutlar (sessiya/cookie ochilmaydi) ----------------
// Webhook'lar: Telegram (maxfiy sarlavha) va Meta (hub.challenge + HMAC imzo). Sessiyasiz, faqat server-server.
if ($route === 'telegram/webhook' && $method === 'POST') { tg_handle_webhook(); }
if ($route === 'meta/webhook') {
    if ($method === 'GET') { meta_handle_verify(); }
    if ($method === 'POST') { meta_handle_event(); }
    reject(405, 'Method not allowed');
}
// Aloqa formasi -> CRM lead. Ommaviy forma: CSRF o'rniga Origin/Referer tekshiruvi + rate-limit + honeypot.
if ($route === 'lead/submit' && $method === 'POST') {
    public_form_guard('lead', 5, 600);
    $f = public_lead_fields(body(16000));
    $id = lead_create($f, 'web_form');
    lead_add_message($id, 'web_form', 'in', lead_summary_text($f));
    notify_admin("🆕 Saytdan yangi ariza:\n" . lead_summary_text($f));
    json_reply(200, ['ok' => true]);
}
if ($route === 'booking/availability' && $method === 'GET') {
    if (!extension_loaded('pdo_sqlite')) { reject(503, 'Storage unavailable'); }
    header('Cache-Control: no-store');
    json_reply(200, booking_availability());
}
if ($route === 'booking/create' && $method === 'POST') {
    public_form_guard('booking', 4, 600);
    // v13 (audit 3): qo'shimcha kunlik IP chegarasi (10 ta / 24 soat); telefon dedup va sayt bo'yicha kunlik chegara — booking_create().
    if (!rate_limit_hit('booking_day', 10, 86400)) { reject(429, 'Juda ko‘p urinish. Birozdan keyin qayta urinib ko‘ring.'); }
    json_reply(200, booking_create(body(16000)));
}
if ($route === 'newsletter/subscribe' && $method === 'POST') {
    // v12 (B1): v8 da ommaviy forma olib tashlangan; marshrut faqat admin «Newsletter» bo'limida
    // `newsletter_enabled` sozlamasi (dariko.sqlite settings, standart: o'chiq) yoqilganda ishlaydi.
    // O'chiq bo'lsa — hech qanday ishlov, email yuborish yoki rate-limit yozuvisiz toza 404.
    if (!extension_loaded('pdo_sqlite') || setting_get('newsletter_enabled', '0') !== '1') { reject(404, 'Not found'); }
    public_form_guard('newsletter', 5, 3600);
    json_reply(200, newsletter_subscribe(body(4000)));
}
if ($route === 'public/testimonials' && $method === 'GET') {
    if (!extension_loaded('pdo_sqlite')) { json_reply(200, ['testimonials' => []]); }
    json_reply(200, ['testimonials' => testimonials_published()]);
}
if ($route === 'public/blog' && $method === 'GET') {
    if (!extension_loaded('pdo_sqlite')) { json_reply(200, ['posts' => []]); }
    json_reply(200, ['posts' => array_map(fn($p) => ['slug' => $p['slug'], 'title' => $p['title'], 'excerpt' => $p['excerpt'], 'published_at' => $p['published_at']], blog_published(3))]);
}
session_open();
if ($route === 'me' && $method === 'GET') {
    json_reply(200, ['authenticated' => !empty($_SESSION['authenticated']), 'csrf' => $_SESSION['csrf'] ?? null, 'local_demo' => false]);
}
if ($method === 'GET' && str_starts_with($route, 'admin/')) {
    require_admin();
    if ($route === 'admin/content') { json_reply(200, content_read()); }
    if ($route === 'admin/analytics/live') {
        if (!analytics_available()) { reject(503, 'pdo_sqlite PHP extension required'); }
        json_reply(200, analytics_live());
    }
    if ($route === 'admin/analytics/summary') {
        if (!analytics_available()) { reject(503, 'pdo_sqlite PHP extension required'); }
        json_reply(200, analytics_summary((string)($_GET['period'] ?? '7d')));
    }
    // ---------------- v7: admin GET ----------------
    if ($route === 'admin/leads') { json_reply(200, leads_list($_GET)); }
    if ($route === 'admin/lead') { json_reply(200, lead_get((int)($_GET['id'] ?? 0))); }
    if ($route === 'admin/channels') { json_reply(200, channels_public_view()); }
    if ($route === 'admin/bookings') { json_reply(200, bookings_month((string)($_GET['month'] ?? date('Y-m')))); }
    if ($route === 'admin/availability') {
        $o = app_db()->query("SELECT date, note FROM availability_overrides WHERE closed = 1 AND date >= date('now','-1 day') ORDER BY date")->fetchAll();
        json_reply(200, ['rules' => booking_rules(), 'closed' => $o]);
    }
    if ($route === 'admin/blog') { json_reply(200, ['posts' => blog_admin_list()]); }
    if ($route === 'admin/cases') { json_reply(200, ['cases' => cases_admin_list()]); }
    if ($route === 'admin/testimonials') { json_reply(200, ['testimonials' => testimonials_admin_list()]); }
    if ($route === 'admin/subscribers') {
        $db = app_db();
        json_reply(200, [
            'subscribers' => $db->query('SELECT id, email, name, subscribed_at, confirmed, confirmed_at, unsubscribed_at, lang FROM subscribers ORDER BY id DESC LIMIT 1000')->fetchAll(),
            'campaigns' => $db->query('SELECT id, created_at, subject, recipients, sent, failed, finished_at FROM campaigns ORDER BY id DESC LIMIT 50')->fetchAll(),
            'mail_mode' => setting_get('smtp_host') !== '' ? 'smtp' : 'mail()',
            'enabled' => setting_get('newsletter_enabled', '0') === '1',
        ]);
    }
    if ($route === 'admin/ab') {
        if (!analytics_available()) { reject(503, 'pdo_sqlite PHP extension required'); }
        json_reply(200, ab_list());
    }
    if ($route === 'admin/security') {
        json_reply(200, [
            'audit' => audit_read(200),
            'checks' => [
                'https' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
                'setup_php_present' => is_file(__DIR__.'/setup.php'),
                'pdo_sqlite' => analytics_available(),
                'gd' => extension_loaded('gd'),
                'curl' => extension_loaded('curl'),
                'php_version' => PHP_VERSION,
                'storage_writable' => is_writable(DATA_DIR),
                'auth_file_mode' => is_file(AUTH_FILE) ? substr(sprintf('%o', fileperms(AUTH_FILE)), -3) : null,
                'app_db_mode' => is_file(APP_DB) ? substr(sprintf('%o', fileperms(APP_DB)), -3) : null,
                'sitemap_writable' => is_writable(__DIR__.'/sitemap.xml'),
                'health' => json_decode((string)@file_get_contents(DATA_DIR.'/health_state.json'), true),
            ],
        ]);
    }
    reject(404, 'Not found');
}
if ($method !== 'POST') { reject(404, 'Not found'); }
if ($route === 'login') {
    $payload = body(4096);
    $password = $payload['password'] ?? null;
    if (!is_string($password) || strlen($password) > 256) { reject(400, 'Invalid password'); }
    if (!is_file(AUTH_FILE)) { reject(503, 'Admin setup required'); }
    $auth = json_decode((string)file_get_contents(AUTH_FILE), true);
    if (!is_array($auth) || !is_string($auth['hash'] ?? null)) { reject(500, 'Admin configuration error'); }
    // Opportunistic cleanup of stale rate-limit files so storage/ doesn't grow unbounded (low probability, cheap).
    if (random_int(1, 50) === 1) { cleanup_rate_limit_files(); }
    // v12 (C1) + v13: umumiy rate_file_* primitivlari (common.php) — IP va global hisoblagich IKKALASI ham rate_file_open() orqali.
    // IP: 8 xato/15 daq (rate-sha256(ip).json). Global: v13 da 30 -> 50 xato/15 daq (rate-global.json); faqat XATO urinishlar sanaladi.
    // v13 (audit 4-masala): global blok ochiq qolgan admin uchun Telegram orqali bir martalik «unlock» kodi (login_unlock_valid()) —
    // u faqat GLOBAL blokni chetlab o'tadi; parol va IP darajasidagi blok baribir tekshiriladi. ARXITEKTURA_UZ.md, 11-bo'lim.
    $unlock = $payload['unlock'] ?? null;
    if ($unlock !== null && (!is_string($unlock) || !preg_match('/^[a-f0-9]{32}$/D', $unlock))) { reject(400, 'Invalid unlock code'); }
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $handle = rate_file_open(DATA_DIR.'/rate-'.hash('sha256', $ip).'.json', 'login');
    $hits = rate_file_read($handle, LOGIN_WINDOW);
    if (count($hits) >= LOGIN_IP_MAX) { rate_file_close($handle, null); audit_log('login_blocked_ip'); reject(429, 'Too many attempts; retry later'); }
    // Eskalatsiya 2-daraja: taqsimlangan (ko'p IP'dan) hujumga qarshi global hisoblagich.
    $gh = rate_file_open(DATA_DIR.'/rate-global.json', 'login_global');
    if ($gh === null) { rate_file_close($handle, null); reject(503, 'Service temporarily unavailable; try again shortly'); }
    $global = rate_file_read($gh, LOGIN_WINDOW);
    $unlockOk = is_string($unlock) && login_unlock_valid($unlock);
    if (count($global) >= LOGIN_GLOBAL_MAX && !$unlockOk) {
        rate_file_close($gh, null); rate_file_close($handle, null);
        audit_log('login_blocked_global');
        login_global_lock_notify();
        reject(429, 'Login temporarily locked; retry later');
    }
    $valid = password_verify($password, $auth['hash']);
    if (!$valid) { $hits[] = time(); $global[] = time(); }
    rate_file_close($handle, $hits);
    rate_file_close($gh, $global);
    if (!$valid) {
        audit_log('login_failed', ['ip_failures_15m' => count($hits)]);
        usleep(random_int(300000, 800000)); // brute-force'ni vaqt bo'yicha sekinlashtirish
        reject(401, 'Invalid credentials');
    }
    if (password_needs_rehash($auth['hash'], PASSWORD_DEFAULT)) {
        $tmp = DATA_DIR.'/auth-'.bin2hex(random_bytes(8)).'.tmp';
        if (file_put_contents($tmp, json_encode(['hash' => password_hash($password, PASSWORD_DEFAULT)]), LOCK_EX) !== false) { @rename($tmp, AUTH_FILE); @chmod(AUTH_FILE, 0600); }
    }
    if ($unlockOk) { login_unlock_consume(); audit_log('login_unlock_used'); }
    audit_log('login_success');
    // Session fixation himoyasi: muvaffaqiyatli kirishdan keyin sessiya ID har doim yangilanadi.
    session_regenerate_id(true);
    $_SESSION['authenticated'] = true;
    $_SESSION['expires'] = time() + 28800;
    $_SESSION['last_active'] = time();
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
    json_reply(200, ['ok' => true, 'csrf' => $_SESSION['csrf']]);
}
if ($route === 'local-login') { reject(404, 'Not found'); }
if ($route === 'logout') {
    csrf_check();
    audit_log('logout');
    $_SESSION = [];
    session_regenerate_id(true);
    json_reply(200, ['ok' => true]);
}
csrf_check();
if ($route === 'admin/save') {
    $input = body();
    locked(static function (array &$d) use ($input): array {
        if (($input['revision'] ?? null) !== $d['revision']) { reject(409, 'Content changed; reload before saving'); }
        $key = $input['key'] ?? null;
        $field = $input['field'] ?? null;
        $value = $input['value'] ?? null;
        if (!is_string($key)) { reject(400, 'Invalid key'); }
        if ($field === 'text') {
            if (!isset($d['texts'][$key]) || !is_array($value)) { reject(400, 'Unknown text field'); }
            foreach (['uz','ru'] as $language) {
                if (!is_string($value[$language] ?? null) || mb_strlen($value[$language], 'UTF-8') > 5000) { reject(400, 'Invalid text'); }
            }
            if (!in_array($value['status'] ?? null, ['draft','reviewed'], true)) { reject(400, 'Invalid status'); }
            foreach (['uz','ru','status'] as $entry) { $d['texts'][$key][$entry] = $value[$entry]; }
        } elseif ($field === 'theme') {
            if (!in_array($key, ['navy','green'], true) || !is_string($value) || !preg_match('/^#[0-9a-fA-F]{6}$/D', $value)) { reject(400, 'Invalid color'); }
            $d['theme'][$key] = $value;
        } elseif ($field === 'settings') {
            if (!array_key_exists($key, $d['settings']) || !is_string($value) || mb_strlen($value, 'UTF-8') > ($key === 'personal_page_url' ? 2048 : 300)) { reject(400, 'Invalid setting'); }
            if ($key === 'phone' && !preg_match('/^\+[0-9]{9,15}$/D', $value)) { reject(400, 'Invalid phone'); }
            if ($key === 'email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) { reject(400, 'Invalid email'); }
            if (in_array($key, ['telegram','instagram'], true) && !preg_match('/^[A-Za-z0-9_.]{2,50}$/D', $value)) { reject(400, 'Invalid username'); }
            if (str_starts_with($key, 'seo_') && (trim($value) === '' || mb_strlen($value, 'UTF-8') > (str_contains($key, 'title') ? 180 : 300))) { reject(400, 'Invalid SEO text'); }
            if ($key === 'personal_page_url' && $value !== '' && !external_url_valid($value)) { reject(400, 'Invalid URL: faqat http:// yoki https:// bilan boshlanuvchi to‘liq havola (2048 belgigacha)'); }
            $d['settings'][$key] = $value;
        } elseif ($field === 'flags') {
            if (!in_array($key, FLAG_KEYS, true) || !is_bool($value)) { reject(400, 'Invalid flag'); }
            $d['flags'][$key] = $value;
        } elseif ($field === 'asset_clear') {
            if (!in_array($key, array_merge(IMAGE_SLOTS, PDF_SLOTS), true)) { reject(400, 'Invalid slot'); }
            unset($d['assets'][$key]);
            audit_log('asset_clear', ['slot' => $key]);
            return [];
        } else { reject(400, 'Invalid field'); }
        audit_log('content_save', ['field' => $field, 'key' => $key]);
        return [];
    });
}
if ($route === 'admin/upload') {
    $slot = (string)($_SERVER['HTTP_X_ASSET_SLOT'] ?? '');
    if (in_array($slot, PDF_SLOTS, true)) {
        // PDF rezyume: 10 MB gacha, magic-byte + faol kontent tekshiruvi, tasodifiy nom, faqat .pdf.
        $pdf = raw_body(10000000, 'PDF');
        pdf_validate($pdf);
        locked(static function (array &$d) use ($slot, $pdf): array {
            $name = 'cms-'.$slot.'-'.bin2hex(random_bytes(16)).'.pdf';
            if (file_put_contents(__DIR__.'/uploads/'.$name, $pdf, LOCK_EX) === false) { reject(500, 'PDF storage failed'); }
            @chmod(__DIR__.'/uploads/'.$name, 0644);
            $url = 'uploads/'.$name.'?v='.((int)$d['revision'] + 1);
            $d['assets'][$slot] = $url;
            audit_log('asset_upload', ['slot' => $slot, 'type' => 'pdf', 'bytes' => strlen($pdf)]);
            return ['url' => $url];
        });
    }
    if (!in_array($slot, IMAGE_SLOTS, true)) { reject(400, 'Invalid slot'); }
    $image = image_process_upload();
    locked(static function (array &$d) use ($slot, $image): array {
        $name = 'cms-'.$slot.'-'.bin2hex(random_bytes(16)).'.png';
        $path = __DIR__.'/uploads/'.$name;
        if (file_put_contents($path, $image, LOCK_EX) === false) { reject(500, 'Image storage failed'); }
        $url = 'uploads/'.$name.'?v='.((int)$d['revision'] + 1);
        $d['assets'][$slot] = $url;
        audit_log('asset_upload', ['slot' => $slot]);
        return ['url' => $url];
    });
}
// ---------------- v7: admin POST ----------------
if ($route === 'admin/media/upload') {
    // Blog/keys/fikr rasmlari: slotga bog'lanmagan umumiy media; xuddi shu tekshiruv (image_process_upload).
    $image = image_process_upload(1600);
    $name = 'cms-media-'.bin2hex(random_bytes(16)).'.png';
    if (file_put_contents(__DIR__.'/uploads/'.$name, $image, LOCK_EX) === false) { reject(500, 'Image storage failed'); }
    @chmod(__DIR__.'/uploads/'.$name, 0644);
    audit_log('media_upload', ['file' => $name]);
    json_reply(200, ['ok' => true, 'url' => 'uploads/'.$name]);
}
const ADMIN_STRUCTURED_KEYS = ['rules', 'ids', 'tags', 'result_metrics', 'variants'];
if (str_starts_with($route, 'admin/') && $route !== 'admin/translate') {
    $in = body(200000);
    // v13 (audit 6-masala): turi noto'g'ri maydonlar (masalan {"name":["x"]}) endi jim '' ga aylanmaydi — 400.
    // Faqat quyidagi kalitlar massiv/obyekt bo'lishi mumkin; qolgan barcha yuqori darajadagi qiymatlar skalyar (string/son/bool) yoki null.
    foreach ($in as $k => $v) {
        if ((is_array($v) && !in_array($k, ADMIN_STRUCTURED_KEYS, true)) || is_object($v)) { reject(400, 'Invalid field type: '.mb_substr((string)$k, 0, 40, 'UTF-8')); }
    }
    $id = (int)($in['id'] ?? 0);
    switch ($route) {
        case 'admin/lead/update':
            $sets = []; $p = [':id' => $id, ':t' => time()];
            if (isset($in['status'])) { if (!in_array($in['status'], LEAD_STATUSES, true)) { reject(400, 'Invalid status'); } $sets[] = 'status = :st'; $p[':st'] = $in['status']; }
            foreach (['assigned_note' => 4000, 'name' => 120, 'phone' => 32, 'email' => 190, 'company' => 160, 'service_interest' => 160] as $k => $max) {
                if (array_key_exists($k, $in)) {
                    if (!is_string($in[$k])) { reject(400, "Invalid field type: $k (matn bo‘lishi kerak)"); }
                    $sets[] = "$k = :$k"; $p[":$k"] = clean_text($in[$k], $max, $k === 'assigned_note');
                }
            }
            if (array_key_exists('name', $in) && $p[':name'] === '') { reject(422, 'Ism bo‘sh bo‘lishi mumkin emas'); }
            if (!$sets) { reject(400, 'Nothing to update'); }
            $st = app_db()->prepare('UPDATE leads SET '.implode(', ', $sets).', updated_at = :t WHERE id = :id'); $st->execute($p);
            if ($st->rowCount() === 0) { reject(404, 'Lead not found'); }
            audit_log('lead_update', ['lead_id' => $id, 'fields' => array_values(array_diff(array_keys($in), ['id']))]);
            json_reply(200, ['ok' => true]);
        case 'admin/lead/reply':
            $text = clean_text($in['text'] ?? '', 3500, true);
            if ($text === '') { reject(422, 'Xabar bo‘sh'); }
            json_reply(200, lead_reply($id, $text));
        case 'admin/lead/note':
            $text = clean_text($in['text'] ?? '', 3500, true);
            if ($text === '') { reject(422, 'Matn bo‘sh'); }
            $q = app_db()->prepare('SELECT source FROM leads WHERE id = :id'); $q->execute([':id' => $id]);
            if ($q->fetchColumn() === false) { reject(404, 'Lead not found'); }
            lead_add_message($id, 'manual', 'out', $text); // qo'ng'iroq/uchrashuv natijasini tarixga yozish
            json_reply(200, ['ok' => true]);
        case 'admin/lead/create':
            $name = clean_text($in['name'] ?? '', 120);
            if ($name === '') { reject(422, 'Ism majburiy'); }
            $nid = lead_create(['name' => $name, 'phone' => clean_text($in['phone'] ?? '', 32), 'email' => clean_text($in['email'] ?? '', 190),
                'company' => clean_text($in['company'] ?? '', 160), 'service' => clean_text($in['service'] ?? '', 160), 'message' => clean_text($in['message'] ?? '', 3000, true)], 'manual');
            audit_log('lead_create', ['lead_id' => $nid]);
            json_reply(200, ['ok' => true, 'id' => $nid]);
        case 'admin/lead/delete':
            $st = app_db()->prepare('DELETE FROM leads WHERE id = :id'); $st->execute([':id' => $id]);
            if ($st->rowCount() === 0) { reject(404, 'Lead not found'); }
            audit_log('lead_delete', ['lead_id' => $id]);
            json_reply(200, ['ok' => true]);
        case 'admin/channels/save':
            // v13: avval (string) massivga qo'llanib «Array to string conversion» ogohlantirishi chiqardi (audit, api.php:297).
            if (!is_string($in['key'] ?? null) || !is_string($in['value'] ?? '')) { reject(400, 'Invalid field type: key/value matn bo‘lishi kerak'); }
            json_reply(200, channels_save($in['key'], $in['value'] ?? ''));
        case 'admin/channels/telegram/setup':
            json_reply(200, telegram_setup());
        case 'admin/channels/telegram/link':
            $code = strtoupper(substr(bin2hex(random_bytes(8)), 0, 8));
            setting_set('tg_link_code', $code); setting_set('tg_link_code_exp', (string)(time() + 900));
            audit_log('telegram_link_code_created');
            json_reply(200, ['ok' => true, 'code' => $code, 'bot_username' => setting_get('tg_bot_username'), 'expires_in' => 900]);
        case 'admin/channels/telegram/test':
            $chat = setting_get('tg_admin_chat_id');
            if ($chat === '') { reject(400, 'Admin chat ID ulanmagan'); }
            $r = tg_send($chat, '✅ DARIKO: test xabari. Xabarnomalar ishlayapti.');
            if (!$r['ok']) { reject(502, 'Telegram: '.$r['error']); }
            json_reply(200, ['ok' => true]);
        case 'admin/mail/test':
            $to = clean_text($in['to'] ?? '', 190);
            if (!filter_var($to, FILTER_VALIDATE_EMAIL)) { reject(422, 'Email noto‘g‘ri'); }
            $r = mail_send($to, 'DARIKO — test xat', "Bu DARIKO admin panelidan yuborilgan test xati.\nPochta sozlamalari ishlayapti.");
            audit_log('mail_test', ['ok' => $r['ok']]);
            if (!$r['ok']) { reject(502, $r['error']); }
            json_reply(200, ['ok' => true]);
        case 'admin/booking/status':
            json_reply(200, booking_set_status($id, (string)($in['status'] ?? '')));
        case 'admin/availability/save':
            $rules = booking_rules_validate($in['rules'] ?? []);
            setting_set('booking_rules', json_encode($rules));
            audit_log('availability_save');
            json_reply(200, ['ok' => true, 'rules' => $rules]);
        case 'admin/availability/override':
            $date = (string)($in['date'] ?? '');
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/D', $date) || !checkdate((int)substr($date, 5, 2), (int)substr($date, 8, 2), (int)substr($date, 0, 4))) { reject(400, 'Invalid date'); }
            if (!empty($in['closed'])) {
                app_db()->prepare('INSERT INTO availability_overrides (date, closed, note) VALUES (:d, 1, :n) ON CONFLICT(date) DO UPDATE SET closed = 1, note = excluded.note')
                    ->execute([':d' => $date, ':n' => clean_text($in['note'] ?? '', 120)]);
            } else { app_db()->prepare('DELETE FROM availability_overrides WHERE date = :d')->execute([':d' => $date]); }
            audit_log('availability_override', ['date' => $date, 'closed' => !empty($in['closed'])]);
            json_reply(200, ['ok' => true]);
        case 'admin/blog/save': json_reply(200, blog_save($in));
        case 'admin/blog/delete': json_reply(200, blog_delete($id));
        case 'admin/case/save': json_reply(200, case_save($in));
        case 'admin/case/delete': json_reply(200, case_delete($id));
        case 'admin/testimonial/save': json_reply(200, testimonial_save($in));
        case 'admin/testimonial/delete': json_reply(200, testimonial_delete($id));
        case 'admin/testimonial/reorder': json_reply(200, testimonials_reorder(array_map('intval', (array)($in['ids'] ?? []))));
        case 'admin/subscriber/delete':
            $st = app_db()->prepare('DELETE FROM subscribers WHERE id = :id'); $st->execute([':id' => $id]);
            audit_log('subscriber_delete', ['id' => $id]);
            json_reply(200, ['ok' => $st->rowCount() > 0]);
        case 'admin/newsletter/create':
            $subject = clean_text($in['subject'] ?? '', 150); $text = clean_text($in['body'] ?? '', 20000, true);
            if ($subject === '' || mb_strlen($text, 'UTF-8') < 10) { reject(422, 'Mavzu va matn majburiy'); }
            $db = app_db();
            $n = (int)$db->query('SELECT COUNT(*) FROM subscribers WHERE confirmed = 1 AND unsubscribed_at IS NULL')->fetchColumn();
            if ($n === 0) { reject(409, 'Tasdiqlangan obunachi yo‘q'); }
            $db->prepare('INSERT INTO campaigns (created_at, subject, body, recipients) VALUES (:t, :s, :b, :n)')->execute([':t' => time(), ':s' => $subject, ':b' => $text, ':n' => $n]);
            $cid = (int)$db->lastInsertId();
            audit_log('newsletter_campaign_created', ['campaign_id' => $cid, 'recipients' => $n]);
            json_reply(200, ['ok' => true, 'id' => $cid, 'recipients' => $n]);
        case 'admin/newsletter/enabled':
            if (!is_bool($in['enabled'] ?? null)) { reject(400, 'Invalid flag'); }
            setting_set('newsletter_enabled', $in['enabled'] ? '1' : '0');
            audit_log('newsletter_enabled', ['enabled' => $in['enabled']]);
            json_reply(200, ['ok' => true, 'enabled' => $in['enabled']]);
        case 'admin/newsletter/send-batch':
            json_reply(200, newsletter_send_batch($id));
        case 'admin/ab/save':
            if (!analytics_available()) { reject(503, 'pdo_sqlite PHP extension required'); }
            json_reply(200, ab_save($in));
        case 'admin/ab/status': json_reply(200, ab_set_status($id, (string)($in['status'] ?? '')));
        case 'admin/ab/delete': json_reply(200, ab_delete($id));
    }
    reject(404, 'Not found');
}
if ($route === 'admin/translate') {
    $input = body(12000);
    $text = $input['text'] ?? null;
    if (!is_string($text) || trim($text) === '' || mb_strlen($text, 'UTF-8') > 3000) { reject(400, 'Invalid text'); }
    $endpoint = getenv('DARIKO_TRANSLATE_URL') ?: '';
    if (!str_starts_with($endpoint, 'https://') || !extension_loaded('curl')) { reject(503, 'Translation service not configured'); }
    $pinned = translate_resolve_pinned($endpoint); // v13: bir marta resolve + tekshiruv, keyin shu IP'ga qadaladi
    if ($pinned === null) { reject(503, 'Translation service not configured'); }
    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [CURLOPT_RESOLVE => $pinned, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_POST => true, CURLOPT_POSTFIELDS => json_encode(['q'=>$text,'source'=>'uz','target'=>'ru','format'=>'text','api_key'=>getenv('DARIKO_TRANSLATE_KEY') ?: '']), CURLOPT_HTTPHEADER => ['Content-Type: application/json'], CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8, CURLOPT_FOLLOWLOCATION => false, CURLOPT_MAXREDIRS => 0]);
    $result = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if (!is_string($result) || strlen($result) > 65536 || $status !== 200) { reject(502, 'Translation failed'); }
    $decoded = json_decode($result, true);
    if (!is_string($decoded['translatedText'] ?? null) || trim($decoded['translatedText']) === '') { reject(502, 'Invalid translation response'); }
    json_reply(200, ['translation' => $decoded['translatedText']]);
}
reject(404, 'Not found');
