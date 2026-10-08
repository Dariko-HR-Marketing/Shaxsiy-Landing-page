<?php
declare(strict_types=1);
// DARIKO v7 — biznes ma'lumotlari kutubxonasi (storage/dariko.sqlite):
// CRM (leads, lead_messages), kanal sozlamalari/maxfiy kalitlar, Telegram Bot API, Meta (Instagram/Facebook)
// Messenger API, email (SMTP/mail()), bron kalendari, newsletter.
// Faqat api.php / sahifa skriptlari / storage/*.php CLI orqali include qilinadi; to'g'ridan-to'g'ri ochilsa 404.
// Qoida: BARCHA SQL — PDO prepared statement. Maxfiy kalitlar hech qachon audit.log'ga yoki brauzerga (niqobsiz) chiqmaydi.
if (!defined('DARIKO_ENTRY')) { http_response_code(404); exit; }

const APP_DB = DATA_DIR . '/dariko.sqlite';
const APP_TZ = 'Asia/Tashkent';
const LEAD_SOURCES = ['web_form', 'booking', 'telegram', 'instagram', 'facebook', 'manual'];
const LEAD_STATUSES = ['yangi', 'aloqada', 'muvaffaqiyatli', 'yopilgan'];
const META_GRAPH_VERSION = 'v21.0';
// Admin paneldan saqlanadigan kalitlar (oq ro'yxat). secret=true -> brauzerga faqat niqoblangan holda qaytadi.
const CHANNEL_KEYS = [
    'tg_bot_token'      => ['secret' => true,  'max' => 200],
    'tg_webhook_secret' => ['secret' => true,  'max' => 256],
    'tg_admin_chat_id'  => ['secret' => false, 'max' => 32],
    'meta_app_secret'   => ['secret' => true,  'max' => 128],
    'meta_verify_token' => ['secret' => true,  'max' => 128],
    'meta_page_token'   => ['secret' => true,  'max' => 1024],
    'smtp_host'         => ['secret' => false, 'max' => 190],
    'smtp_port'         => ['secret' => false, 'max' => 5],
    'smtp_secure'       => ['secret' => false, 'max' => 5],
    'smtp_user'         => ['secret' => false, 'max' => 190],
    'smtp_pass'         => ['secret' => true,  'max' => 256],
    'smtp_from'         => ['secret' => false, 'max' => 190],
    'smtp_from_name'    => ['secret' => false, 'max' => 80],
    'notify_email'      => ['secret' => false, 'max' => 190],
];

function app_db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) { return $pdo; }
    if (!extension_loaded('pdo_sqlite')) { reject(503, 'pdo_sqlite PHP extension required'); }
    $pdo = sqlite_open(APP_DB); // v12 (C1): umumiy ulanish sozlamasi (common.php)
    $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS settings (
    key   TEXT PRIMARY KEY,
    value TEXT NOT NULL DEFAULT ''
);
CREATE TABLE IF NOT EXISTS leads (
    id               INTEGER PRIMARY KEY AUTOINCREMENT,
    created_at       INTEGER NOT NULL,
    updated_at       INTEGER NOT NULL,
    name             TEXT NOT NULL DEFAULT '',
    phone            TEXT NOT NULL DEFAULT '',
    email            TEXT NOT NULL DEFAULT '',
    company          TEXT NOT NULL DEFAULT '',
    tg_username      TEXT NOT NULL DEFAULT '',
    service_interest TEXT NOT NULL DEFAULT '',
    message          TEXT NOT NULL DEFAULT '',
    source           TEXT NOT NULL CHECK (source IN ('web_form','booking','telegram','instagram','facebook','manual')),
    status           TEXT NOT NULL DEFAULT 'yangi' CHECK (status IN ('yangi','aloqada','muvaffaqiyatli','yopilgan')),
    assigned_note    TEXT NOT NULL DEFAULT '',
    last_contact_at  INTEGER,
    last_inbound_at  INTEGER,
    ext_key          TEXT UNIQUE,
    lang             TEXT NOT NULL DEFAULT ''
);
CREATE INDEX IF NOT EXISTS idx_leads_updated ON leads(updated_at);
CREATE INDEX IF NOT EXISTS idx_leads_status ON leads(status);
CREATE TABLE IF NOT EXISTS lead_messages (
    id                  INTEGER PRIMARY KEY AUTOINCREMENT,
    lead_id             INTEGER NOT NULL REFERENCES leads(id) ON DELETE CASCADE,
    channel             TEXT NOT NULL,
    direction           TEXT NOT NULL CHECK (direction IN ('in','out')),
    body                TEXT NOT NULL,
    external_message_id TEXT,
    created_at          INTEGER NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_msg_lead ON lead_messages(lead_id, created_at);
CREATE UNIQUE INDEX IF NOT EXISTS uq_msg_ext ON lead_messages(channel, external_message_id) WHERE external_message_id IS NOT NULL;
CREATE TABLE IF NOT EXISTS bookings (
    id                  INTEGER PRIMARY KEY AUTOINCREMENT,
    lead_id             INTEGER NOT NULL REFERENCES leads(id) ON DELETE CASCADE,
    requested_date      TEXT NOT NULL,
    requested_time_slot TEXT NOT NULL,
    status              TEXT NOT NULL DEFAULT 'pending' CHECK (status IN ('pending','confirmed','cancelled')),
    service             TEXT NOT NULL DEFAULT '',
    created_at          INTEGER NOT NULL,
    updated_at          INTEGER NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_bookings_date ON bookings(requested_date);
-- Ikki marta band qilishning oldini olish: bir vaqt oralig'ida faqat bitta faol (pending/confirmed) bron.
CREATE UNIQUE INDEX IF NOT EXISTS uq_booking_active ON bookings(requested_date, requested_time_slot) WHERE status IN ('pending','confirmed');
CREATE TABLE IF NOT EXISTS availability_overrides (
    date   TEXT PRIMARY KEY,
    closed INTEGER NOT NULL DEFAULT 1,
    note   TEXT NOT NULL DEFAULT ''
);
CREATE TABLE IF NOT EXISTS subscribers (
    id                INTEGER PRIMARY KEY AUTOINCREMENT,
    email             TEXT NOT NULL UNIQUE,
    name              TEXT NOT NULL DEFAULT '',
    subscribed_at     INTEGER NOT NULL,
    confirmed         INTEGER NOT NULL DEFAULT 0,
    confirmed_at      INTEGER,
    confirm_token     TEXT NOT NULL,
    unsubscribe_token TEXT NOT NULL UNIQUE,
    unsubscribed_at   INTEGER,
    last_campaign_id  INTEGER NOT NULL DEFAULT 0,
    lang              TEXT NOT NULL DEFAULT 'uz'
);
CREATE TABLE IF NOT EXISTS campaigns (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    created_at INTEGER NOT NULL,
    subject    TEXT NOT NULL,
    body       TEXT NOT NULL,
    recipients INTEGER NOT NULL DEFAULT 0,
    sent       INTEGER NOT NULL DEFAULT 0,
    failed     INTEGER NOT NULL DEFAULT 0,
    finished_at INTEGER
);
CREATE TABLE IF NOT EXISTS blog_posts (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    slug            TEXT NOT NULL UNIQUE,
    title           TEXT NOT NULL,
    excerpt         TEXT NOT NULL DEFAULT '',
    body            TEXT NOT NULL DEFAULT '',
    cover_image     TEXT NOT NULL DEFAULT '',
    author          TEXT NOT NULL DEFAULT '',
    published_at    INTEGER,
    status          TEXT NOT NULL DEFAULT 'draft' CHECK (status IN ('draft','published')),
    seo_title       TEXT NOT NULL DEFAULT '',
    seo_description TEXT NOT NULL DEFAULT '',
    tags            TEXT NOT NULL DEFAULT '[]',
    lang            TEXT NOT NULL DEFAULT 'uz' CHECK (lang IN ('uz','ru')),
    created_at      INTEGER NOT NULL,
    updated_at      INTEGER NOT NULL
);
-- v12 (B6): «nashr qilingan maqolalar, sana bo'yicha» so'rovi uchun (blog_published). IF NOT EXISTS — eski bazaga ham qo'shiladi.
CREATE INDEX IF NOT EXISTS idx_blog_status_pub ON blog_posts(status, published_at);
CREATE TABLE IF NOT EXISTS case_studies (
    id                INTEGER PRIMARY KEY AUTOINCREMENT,
    slug              TEXT NOT NULL UNIQUE,
    title             TEXT NOT NULL,
    client_name       TEXT NOT NULL DEFAULT '',
    industry          TEXT NOT NULL DEFAULT '',
    challenge         TEXT NOT NULL DEFAULT '',
    approach          TEXT NOT NULL DEFAULT '',
    result_summary    TEXT NOT NULL DEFAULT '',
    result_metrics    TEXT NOT NULL DEFAULT '[]',
    testimonial_quote TEXT NOT NULL DEFAULT '',
    cover_image       TEXT NOT NULL DEFAULT '',
    published_at      INTEGER,
    status            TEXT NOT NULL DEFAULT 'draft' CHECK (status IN ('draft','published')),
    is_sample         INTEGER NOT NULL DEFAULT 0,
    sort_order        INTEGER NOT NULL DEFAULT 0,
    created_at        INTEGER NOT NULL,
    updated_at        INTEGER NOT NULL
);
CREATE TABLE IF NOT EXISTS testimonials (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    client_name TEXT NOT NULL,
    company     TEXT NOT NULL DEFAULT '',
    role        TEXT NOT NULL DEFAULT '',
    quote       TEXT NOT NULL,
    rating      INTEGER CHECK (rating IS NULL OR rating BETWEEN 1 AND 5),
    photo       TEXT NOT NULL DEFAULT '',
    source      TEXT NOT NULL DEFAULT 'manual' CHECK (source IN ('google','yandex','manual')),
    published   INTEGER NOT NULL DEFAULT 0,
    sort_order  INTEGER NOT NULL DEFAULT 0,
    created_at  INTEGER NOT NULL,
    updated_at  INTEGER NOT NULL
);
SQL);
    app_seed($pdo);
    return $pdo;
}

// Birinchi ishga tushishda storage/seed/*.json dan boshlang'ich blog maqolalari va keys namunalarini import qiladi (bir marta).
function app_seed(PDO $db): void {
    $q = $db->prepare('SELECT value FROM settings WHERE key = :k');
    $q->execute([':k' => 'seeded_v7']);
    if ($q->fetchColumn() !== false) { return; }
    $now = time();
    $db->beginTransaction();
    $posts = json_decode((string)@file_get_contents(DATA_DIR . '/seed/blog_posts.json'), true);
    if (is_array($posts)) {
        $ins = $db->prepare('INSERT OR IGNORE INTO blog_posts (slug,title,excerpt,body,cover_image,author,published_at,status,seo_title,seo_description,tags,lang,created_at,updated_at)
                             VALUES (:slug,:title,:excerpt,:body,\'\',:author,:pub,:status,:st,:sd,:tags,:lang,:now,:now)');
        foreach ($posts as $i => $p) {
            $ins->execute([':slug' => $p['slug'], ':title' => $p['title'], ':excerpt' => $p['excerpt'], ':body' => $p['body'],
                ':author' => $p['author'] ?? 'Khikmatullo Turaev', ':pub' => $now - (count($posts) - $i) * 86400 * 3,
                ':status' => $p['status'] ?? 'published', ':st' => $p['seo_title'] ?? '', ':sd' => $p['seo_description'] ?? '',
                ':tags' => json_encode($p['tags'] ?? [], JSON_UNESCAPED_UNICODE), ':lang' => $p['lang'] ?? 'uz', ':now' => $now]);
        }
    }
    $cases = json_decode((string)@file_get_contents(DATA_DIR . '/seed/case_studies.json'), true);
    if (is_array($cases)) {
        $ins = $db->prepare('INSERT OR IGNORE INTO case_studies (slug,title,client_name,industry,challenge,approach,result_summary,result_metrics,testimonial_quote,status,is_sample,sort_order,created_at,updated_at)
                             VALUES (:slug,:title,:client,:industry,:challenge,:approach,:result,:metrics,\'\',\'draft\',1,:sort,:now,:now)');
        foreach ($cases as $i => $c) {
            $ins->execute([':slug' => $c['slug'], ':title' => $c['title'], ':client' => $c['client_name'], ':industry' => $c['industry'],
                ':challenge' => $c['challenge'], ':approach' => $c['approach'], ':result' => $c['result_summary'],
                ':metrics' => json_encode($c['result_metrics'] ?? [], JSON_UNESCAPED_UNICODE), ':sort' => $i, ':now' => $now]);
        }
    }
    $db->prepare('INSERT OR REPLACE INTO settings (key, value) VALUES (:k, :v)')->execute([':k' => 'seeded_v7', ':v' => (string)$now]);
    $db->commit();
}

function setting_get(string $key, string $default = ''): string {
    $q = app_db()->prepare('SELECT value FROM settings WHERE key = :k');
    $q->execute([':k' => $key]);
    $v = $q->fetchColumn();
    return $v === false ? $default : (string)$v;
}
function setting_set(string $key, string $value): void {
    app_db()->prepare('INSERT INTO settings (key, value) VALUES (:k, :v) ON CONFLICT(key) DO UPDATE SET value = excluded.value')
        ->execute([':k' => $key, ':v' => $value]);
}

function clean_text(mixed $v, int $max, bool $multiline = false): string {
    if (!is_string($v)) { return ''; }
    $v = mb_convert_encoding($v, 'UTF-8', 'UTF-8');
    $v = str_replace("\r\n", "\n", $v);
    $v = preg_replace($multiline ? '/[\x00-\x08\x0B-\x1F\x7F]/u' : '/[\x00-\x1F\x7F]/u', '', $v) ?? '';
    return mb_substr(trim($v), 0, $max, 'UTF-8');
}

/* ============================== HTTP (server -> tashqi API) ============================== */
// Faqat server tomonida; brauzer tashqi API'ga murojaat qilmaydi (CSP connect-src o'zgarmaydi).
// DARIKO_TG_API / DARIKO_GRAPH_API env o'zgaruvchilari — FAQAT lokal test uchun (mock server); prod'da o'rnatmang.
function http_json_post(string $url, array $payload, array $headers = [], int $timeout = 8, bool $form = false): array {
    if (!extension_loaded('curl')) { return ['ok' => false, 'status' => 0, 'error' => 'curl extension missing', 'body' => null]; }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $form ? http_build_query($payload) : json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER => array_merge([$form ? 'Content-Type: application/x-www-form-urlencoded' : 'Content-Type: application/json'], $headers),
        CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => $timeout, CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_FOLLOWLOCATION => false, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS | CURLPROTO_HTTP,
    ]);
    $res = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    $body = is_string($res) && strlen($res) < 1000000 ? json_decode($res, true) : null;
    return ['ok' => $status >= 200 && $status < 300, 'status' => $status, 'error' => $err, 'body' => is_array($body) ? $body : null];
}
function http_get_json(string $url, int $timeout = 5, array $headers = []): ?array {
    if (!extension_loaded('curl')) { return null; }
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_HTTPHEADER => $headers, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => $timeout, CURLOPT_CONNECTTIMEOUT => 4, CURLOPT_FOLLOWLOCATION => false]);
    $res = curl_exec($ch); $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    if (!is_string($res) || $status !== 200) { return null; }
    $d = json_decode($res, true);
    return is_array($d) ? $d : null;
}

/* ============================== Telegram Bot API ============================== */
function tg_api_base(): string {
    $env = getenv('DARIKO_TG_API');
    return (is_string($env) && $env !== '') ? rtrim($env, '/') : 'https://api.telegram.org';
}
function tg_call(string $method, array $params, ?string $token = null): array {
    $token ??= setting_get('tg_bot_token');
    if ($token === '' || !preg_match('/^\d{5,16}:[A-Za-z0-9_-]{30,64}$/D', $token)) { return ['ok' => false, 'error' => 'Telegram bot token sozlanmagan', 'status' => 0, 'body' => null]; }
    $r = http_json_post(tg_api_base() . '/bot' . $token . '/' . $method, $params);
    if (!$r['ok'] || !($r['body']['ok'] ?? false)) {
        $r['ok'] = false;
        $r['error'] = (string)($r['body']['description'] ?? $r['error'] ?: ('HTTP ' . $r['status']));
    }
    return $r;
}
function tg_send(string $chatId, string $text): array {
    return tg_call('sendMessage', ['chat_id' => $chatId, 'text' => mb_substr($text, 0, 4000, 'UTF-8'), 'disable_web_page_preview' => true]);
}
function tg_webhook_url(): string { return site_base_url() . '/api.php?route=telegram/webhook'; }

// Telegram webhook: sarlavhadagi maxfiy token tekshiriladi (setWebhook paytida berilgan secret_token).
function tg_handle_webhook(): never {
    $secret = setting_get('tg_webhook_secret');
    $given = (string)($_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '');
    if ($secret === '' || !hash_equals($secret, $given)) { audit_log('webhook_rejected', ['channel' => 'telegram']); reject(401, 'Invalid secret'); }
    $update = body(1000000);
    $msg = $update['message'] ?? $update['edited_message'] ?? null;
    if (!is_array($msg) || !is_array($msg['chat'] ?? null)) { json_reply(200, ['ok' => true, 'ignored' => 'unsupported update']); }
    if (($msg['chat']['type'] ?? '') !== 'private') { json_reply(200, ['ok' => true, 'ignored' => 'non-private chat']); }
    $chatId = (string)(int)($msg['chat']['id'] ?? 0);
    if ($chatId === '0') { json_reply(200, ['ok' => true, 'ignored' => 'no chat']); }
    $text = clean_text($msg['text'] ?? $msg['caption'] ?? '', 4000, true);
    if ($text === '') { $text = '[' . (isset($msg['photo']) ? 'rasm' : (isset($msg['document']) ? 'fayl' : (isset($msg['voice']) ? 'ovozli xabar' : (isset($msg['contact']) ? 'kontakt' : 'media')))) . ']'; }
    // Admin chatini ulash: admin paneldagi bir martalik kod bilan "/admin KOD" yuboriladi.
    if (preg_match('/^\/admin\s+([A-Z0-9]{8})$/D', $text, $m)) {
        $code = setting_get('tg_link_code'); $exp = (int)setting_get('tg_link_code_exp', '0');
        if ($code !== '' && $exp > time() && hash_equals($code, $m[1])) {
            setting_set('tg_admin_chat_id', $chatId); setting_set('tg_link_code', '');
            audit_log('channel_admin_chat_linked', ['channel' => 'telegram']);
            tg_send($chatId, "✅ Ushbu chat DARIKO admin xabarnomalari uchun ulandi.");
        } else { tg_send($chatId, 'Kod noto‘g‘ri yoki muddati o‘tgan.'); }
        json_reply(200, ['ok' => true]);
    }
    if ($chatId === setting_get('tg_admin_chat_id')) {
        // v13 (audit 4): global login blokida admin o'z (oldindan ulangan) Telegram chatidan /unlock yuboradi ->
        // 10 daqiqalik, bir martalik kod (faqat global blokni chetlab o'tadi; parol baribir kerak).
        if (preg_match('/^\/unlock(@\w+)?$/iD', trim($text))) {
            $code = login_unlock_issue();
            tg_send($chatId, "🔓 Bir martalik kirish havolasi (10 daqiqa, faqat global blokni chetlab o‘tadi — parol baribir kerak):\n"
                . site_base_url() . '/admin.html#unlock=' . $code . "\n\nAgar bu siz bo‘lmasangiz — e’tiborsiz qoldiring; parolsiz bu havola hech narsa bermaydi.");
        }
        json_reply(200, ['ok' => true, 'ignored' => 'admin chat']);
    }
    $from = is_array($msg['from'] ?? null) ? $msg['from'] : [];
    $name = clean_text(trim(($from['first_name'] ?? '') . ' ' . ($from['last_name'] ?? '')), 120);
    $username = clean_text($from['username'] ?? '', 64);
    $phone = is_array($msg['contact'] ?? null) ? clean_text($msg['contact']['phone_number'] ?? '', 32) : '';
    $isNew = false;
    $leadId = lead_upsert_external('telegram', 'tg:' . $chatId, ['name' => $name, 'tg_username' => $username, 'phone' => $phone], $isNew);
    $stored = lead_add_message($leadId, 'telegram', 'in', $text, 'tg:' . $chatId . ':' . (int)($msg['message_id'] ?? 0));
    if ($isNew && $stored) {
        notify_admin("🆕 Telegram orqali yangi murojaat: " . ($name ?: 'nomsiz') . ($username ? ' (@' . $username . ')' : '') . "\n" . mb_substr($text, 0, 300, 'UTF-8'));
    }
    json_reply(200, ['ok' => true]);
}

/* ============================== v13: login global blokidan Telegram orqali chiqish ============================== */
// Kod: 128-bit tasodifiy (32 hex). Bazada faqat sha256 xeshi va muddati saqlanadi. Bir martalik: muvaffaqiyatli kirishda o'chiriladi.
// Yangi /unlock oldingi kodni bekor qiladi. Global blok boshlanganda admin chatiga (15 daqiqada 1 marta) xabar yuboriladi.
const LOGIN_UNLOCK_TTL = 600;
function login_unlock_issue(): string {
    $code = bin2hex(random_bytes(16));
    setting_set('login_unlock_hash', hash('sha256', $code));
    setting_set('login_unlock_exp', (string)(time() + LOGIN_UNLOCK_TTL));
    audit_log('login_unlock_issued');
    return $code;
}
function login_unlock_valid(string $code): bool {
    if (!extension_loaded('pdo_sqlite')) { return false; }
    $h = setting_get('login_unlock_hash'); $exp = (int)setting_get('login_unlock_exp', '0');
    return $h !== '' && $exp > time() && hash_equals($h, hash('sha256', $code));
}
function login_unlock_consume(): void { setting_set('login_unlock_hash', ''); setting_set('login_unlock_exp', '0'); }
function login_global_lock_notify(): void {
    try {
        if (!extension_loaded('pdo_sqlite') || (int)setting_get('login_lock_notified_at', '0') > time() - 900) { return; }
        setting_set('login_lock_notified_at', (string)time());
        notify_admin("⚠️ Admin panelga kirish GLOBAL bloklandi (15 daqiqada " . LOGIN_GLOBAL_MAX . "+ noto‘g‘ri parol, turli IP’lardan). "
            . "Agar kirishingiz kerak bo‘lsa, shu chatga /unlock yuboring — bir martalik havola keladi.");
    } catch (Throwable $e) { error_log('DARIKO lock notify failed: ' . $e->getMessage()); }
}

/* ============================== Meta (Instagram + Facebook Messenger) ============================== */
function meta_api_base(): string {
    $env = getenv('DARIKO_GRAPH_API');
    return (is_string($env) && $env !== '') ? rtrim($env, '/') : 'https://graph.facebook.com/' . META_GRAPH_VERSION;
}
function meta_webhook_url(): string { return site_base_url() . '/api.php?route=meta/webhook'; }

// GET tasdiqlash (hub.challenge). PHP nuqtalarni pastki chiziqqa aylantiradi: hub.mode -> hub_mode.
function meta_handle_verify(): never {
    $verify = setting_get('meta_verify_token');
    $mode = (string)($_GET['hub_mode'] ?? '');
    $token = (string)($_GET['hub_verify_token'] ?? '');
    $challenge = (string)($_GET['hub_challenge'] ?? '');
    if ($verify === '' || $mode !== 'subscribe' || !hash_equals($verify, $token) || !preg_match('/^[A-Za-z0-9_-]{1,128}$/D', $challenge)) {
        audit_log('webhook_rejected', ['channel' => 'meta', 'stage' => 'verify']);
        reject(403, 'Verification failed');
    }
    http_response_code(200);
    header('Content-Type: text/plain; charset=utf-8');
    echo $challenge;
    exit;
}

// POST: X-Hub-Signature-256 = "sha256=" + HMAC-SHA256(xom tana, App Secret). JSON parse'dan OLDIN tekshiriladi.
function meta_handle_event(): never {
    $appSecret = setting_get('meta_app_secret');
    $raw = raw_body(1000000, 'Webhook');
    $sig = (string)($_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '');
    if ($appSecret === '' || !str_starts_with($sig, 'sha256=') || !hash_equals('sha256=' . hash_hmac('sha256', $raw, $appSecret), $sig)) {
        audit_log('webhook_rejected', ['channel' => 'meta', 'stage' => 'signature']);
        reject(401, 'Invalid signature');
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) { reject(400, 'Invalid JSON'); }
    $object = (string)($data['object'] ?? '');
    if (!in_array($object, ['page', 'instagram'], true)) { json_reply(200, ['ok' => true, 'ignored' => 'object']); }
    $source = $object === 'instagram' ? 'instagram' : 'facebook';
    $prefix = $object === 'instagram' ? 'ig:' : 'fb:';
    $n = 0;
    foreach ((array)($data['entry'] ?? []) as $entry) {
        if (!is_array($entry)) { continue; }
        $pageId = (string)($entry['id'] ?? '');
        foreach ((array)($entry['messaging'] ?? []) as $ev) {
            if (!is_array($ev) || !is_array($ev['message'] ?? null)) { continue; }
            $m = $ev['message'];
            $isEcho = !empty($m['is_echo']);
            $sender = (string)($ev['sender']['id'] ?? ''); $recipient = (string)($ev['recipient']['id'] ?? '');
            $userId = $isEcho ? $recipient : $sender;          // echo = sahifa/akkaunt nomidan yuborilgan xabar
            if (!preg_match('/^\d{3,32}$/D', $userId) || $userId === $pageId) { continue; }
            $text = clean_text($m['text'] ?? '', 4000, true);
            if ($text === '') { $text = isset($m['attachments']) ? '[ilova/media]' : '[xabar]'; }
            $mid = clean_text($m['mid'] ?? '', 200);
            $isNew = false;
            $leadId = lead_upsert_external($source, $prefix . $userId, [], $isNew, !$isEcho);
            if ($isNew && !$isEcho) { meta_fill_profile($leadId, $userId, $source); }
            $stored = lead_add_message($leadId, $source, $isEcho ? 'out' : 'in', $text, $mid !== '' ? $mid : null);
            if ($stored) { $n++; }
            if ($isNew && !$isEcho && $stored) { notify_admin('🆕 ' . ($source === 'instagram' ? 'Instagram' : 'Facebook') . " orqali yangi murojaat:\n" . mb_substr($text, 0, 300, 'UTF-8')); }
        }
    }
    json_reply(200, ['ok' => true, 'stored' => $n]);
}

function meta_proof(string $token): array {
    $secret = setting_get('meta_app_secret');
    return $secret !== '' ? ['appsecret_proof' => hash_hmac('sha256', $token, $secret)] : [];
}
// Yangi lead uchun ism/username (ixtiyoriy, xatoda jim o'tadi).
function meta_fill_profile(int $leadId, string $userId, string $source): void {
    $token = setting_get('meta_page_token');
    if ($token === '') { return; }
    $fields = $source === 'instagram' ? 'name,username' : 'first_name,last_name';
    // Token URL'da emas, Authorization sarlavhasida (server/proxy loglariga tushmasligi uchun).
    $url = meta_api_base() . '/' . rawurlencode($userId) . '?' . http_build_query(['fields' => $fields] + meta_proof($token));
    $d = http_get_json($url, 3, ['Authorization: Bearer ' . $token]);
    if (!$d) { return; }
    $name = clean_text($d['name'] ?? trim(($d['first_name'] ?? '') . ' ' . ($d['last_name'] ?? '')), 120);
    if (!empty($d['username'])) { $name = trim($name . ' (@' . clean_text($d['username'], 64) . ')'); }
    if ($name !== '') { app_db()->prepare('UPDATE leads SET name = :n WHERE id = :id AND name = \'\'')->execute([':n' => $name, ':id' => $leadId]); }
}
// Send API: POST /me/messages (Facebook Page va unga ulangan Instagram professional akkaunt uchun bir xil endpoint).
function meta_send(string $recipientId, string $text): array {
    $token = setting_get('meta_page_token');
    if ($token === '') { return ['ok' => false, 'error' => 'Meta Page access token sozlanmagan']; }
    $payload = ['recipient' => json_encode(['id' => $recipientId]), 'messaging_type' => 'RESPONSE',
                'message' => json_encode(['text' => mb_substr($text, 0, 1900, 'UTF-8')], JSON_UNESCAPED_UNICODE),
                'access_token' => $token] + meta_proof($token);
    $r = http_json_post(meta_api_base() . '/me/messages', $payload, [], 10, true);
    if (!$r['ok']) { $r['error'] = (string)($r['body']['error']['message'] ?? $r['error'] ?: ('HTTP ' . $r['status'])); }
    $r['message_id'] = (string)($r['body']['message_id'] ?? '');
    return $r;
}

/* ============================== CRM ============================== */
function lead_upsert_external(string $source, string $extKey, array $fields, bool &$isNew, bool $inbound = true): int {
    $db = app_db(); $now = time();
    $q = $db->prepare('SELECT id FROM leads WHERE ext_key = :k');
    $q->execute([':k' => $extKey]);
    $id = $q->fetchColumn();
    if ($id !== false) {
        $isNew = false;
        $set = 'updated_at = :now' . ($inbound ? ', last_inbound_at = :now' : '');
        $params = [':now' => $now, ':id' => (int)$id];
        foreach (['name', 'tg_username', 'phone'] as $f) {
            if (($fields[$f] ?? '') !== '') { $set .= ", $f = CASE WHEN $f = '' THEN :$f ELSE $f END"; $params[":$f"] = $fields[$f]; }
        }
        // Yopilgan lead qayta yozsa — yana "yangi" holatiga qaytadi.
        if ($inbound) { $set .= ", status = CASE WHEN status = 'yopilgan' THEN 'yangi' ELSE status END"; }
        $db->prepare("UPDATE leads SET $set WHERE id = :id")->execute($params);
        return (int)$id;
    }
    $isNew = true;
    $db->prepare('INSERT INTO leads (created_at, updated_at, name, phone, tg_username, source, ext_key, last_inbound_at)
                  VALUES (:now, :now, :name, :phone, :tgu, :src, :k, :inb)')
       ->execute([':now' => $now, ':name' => $fields['name'] ?? '', ':phone' => $fields['phone'] ?? '', ':tgu' => $fields['tg_username'] ?? '',
                  ':src' => $source, ':k' => $extKey, ':inb' => $inbound ? $now : null]);
    return (int)$db->lastInsertId();
}
function lead_add_message(int $leadId, string $channel, string $direction, string $body, ?string $extId = null): bool {
    $db = app_db();
    $st = $db->prepare('INSERT OR IGNORE INTO lead_messages (lead_id, channel, direction, body, external_message_id, created_at) VALUES (:l, :c, :d, :b, :e, :t)');
    $st->execute([':l' => $leadId, ':c' => $channel, ':d' => $direction, ':b' => $body, ':e' => $extId, ':t' => time()]);
    if ($direction === 'out') { $db->prepare('UPDATE leads SET last_contact_at = :t, updated_at = :t WHERE id = :id')->execute([':t' => time(), ':id' => $leadId]); }
    return $st->rowCount() > 0;
}
function lead_create(array $f, string $source): int {
    $db = app_db(); $now = time();
    $db->prepare('INSERT INTO leads (created_at, updated_at, name, phone, email, company, tg_username, service_interest, message, source, lang, last_inbound_at)
                  VALUES (:now, :now, :name, :phone, :email, :company, :tgu, :svc, :msg, :src, :lang, :inb)')
       ->execute([':now' => $now, ':name' => $f['name'] ?? '', ':phone' => $f['phone'] ?? '', ':email' => $f['email'] ?? '', ':company' => $f['company'] ?? '',
                  ':tgu' => $f['tg_username'] ?? '', ':svc' => $f['service'] ?? '', ':msg' => $f['message'] ?? '', ':src' => $source,
                  ':lang' => $f['lang'] ?? '', ':inb' => $source === 'manual' ? null : $now]);
    return (int)$db->lastInsertId();
}
// Ommaviy formalar uchun umumiy maydon tekshiruvi.
function public_lead_fields(array $in): array {
    if (($in['website'] ?? '') !== '') { json_reply(200, ['ok' => true]); } // honeypot: bot'ga "muvaffaqiyat" ko'rsatiladi, hech narsa saqlanmaydi
    $name = clean_text($in['name'] ?? '', 120);
    $phoneRaw = clean_text($in['phone'] ?? '', 32);
    $digits = preg_replace('/\D/', '', $phoneRaw) ?? '';
    if (mb_strlen($name, 'UTF-8') < 2) { reject(422, 'Ismni kiriting'); }
    if (!preg_match('/^998\d{9}$/D', $digits)) { reject(422, 'Telefon +998 XX XXX XX XX shaklida bo‘lsin'); }
    $tg = ltrim(clean_text($in['tg'] ?? '', 40), '@');
    if ($tg !== '' && !preg_match('/^[A-Za-z0-9_]{3,32}$/D', $tg)) { $tg = ''; }
    $email = clean_text($in['email'] ?? '', 190);
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) { $email = ''; }
    $lang = ($in['lang'] ?? '') === 'ru' ? 'ru' : 'uz';
    return ['name' => $name, 'phone' => '+' . $digits, 'tg_username' => $tg, 'email' => $email,
            'company' => clean_text($in['company'] ?? '', 160), 'service' => clean_text($in['service'] ?? '', 160),
            'message' => clean_text($in['message'] ?? '', 3000, true), 'lang' => $lang];
}
function public_form_guard(string $bucket, int $max, int $window): void {
    if (!function_exists('analytics_same_origin') || !analytics_same_origin()) { reject(403, 'Origin mismatch'); }
    if (!extension_loaded('pdo_sqlite')) { reject(503, 'Storage unavailable'); }
    if (!rate_limit_hit($bucket, $max, $window)) { reject(429, 'Juda ko‘p urinish. Birozdan keyin qayta urinib ko‘ring.'); }
}
function lead_summary_text(array $f): string {
    return "Ism: {$f['name']}\nTelefon: {$f['phone']}" . ($f['tg_username'] ? "\nTelegram: @{$f['tg_username']}" : '') .
        ($f['company'] ? "\nKompaniya: {$f['company']}" : '') . ($f['service'] ? "\nXizmat: {$f['service']}" : '') . ($f['message'] ? "\nXabar: {$f['message']}" : '');
}

function leads_list(array $filter): array {
    $where = []; $p = [];
    if (in_array($filter['status'] ?? '', LEAD_STATUSES, true)) { $where[] = 'l.status = :st'; $p[':st'] = $filter['status']; }
    if (in_array($filter['source'] ?? '', LEAD_SOURCES, true)) { $where[] = 'l.source = :src'; $p[':src'] = $filter['source']; }
    $qtext = clean_text($filter['q'] ?? '', 80);
    if ($qtext !== '') {
        $where[] = "(l.name LIKE :q ESCAPE '\\' OR l.phone LIKE :q ESCAPE '\\' OR l.email LIKE :q ESCAPE '\\' OR l.tg_username LIKE :q ESCAPE '\\' OR l.company LIKE :q ESCAPE '\\' OR l.assigned_note LIKE :q ESCAPE '\\')";
        $p[':q'] = '%' . addcslashes($qtext, '%_\\') . '%';
    }
    $sql = 'SELECT l.id, l.created_at, l.updated_at, l.name, l.phone, l.email, l.tg_username, l.source, l.status, l.service_interest,
                   (SELECT body FROM lead_messages m WHERE m.lead_id = l.id ORDER BY m.id DESC LIMIT 1) AS last_message,
                   (SELECT direction FROM lead_messages m WHERE m.lead_id = l.id ORDER BY m.id DESC LIMIT 1) AS last_direction
            FROM leads l' . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY l.updated_at DESC, l.id DESC LIMIT 300';
    $q = app_db()->prepare($sql); $q->execute($p);
    $rows = $q->fetchAll();
    foreach ($rows as &$r) { $r['last_message'] = mb_substr((string)$r['last_message'], 0, 140, 'UTF-8'); }
    $counts = app_db()->query('SELECT status, COUNT(*) AS n FROM leads GROUP BY status')->fetchAll();
    return ['leads' => $rows, 'counts' => $counts];
}
function lead_get(int $id): array {
    $db = app_db();
    $q = $db->prepare('SELECT * FROM leads WHERE id = :id'); $q->execute([':id' => $id]);
    $lead = $q->fetch();
    if (!$lead) { reject(404, 'Lead not found'); }
    unset($lead['ext_key']);
    $m = $db->prepare('SELECT id, channel, direction, body, created_at FROM lead_messages WHERE lead_id = :id ORDER BY id ASC LIMIT 1000');
    $m->execute([':id' => $id]);
    $b = $db->prepare('SELECT id, requested_date, requested_time_slot, status, service FROM bookings WHERE lead_id = :id ORDER BY requested_date DESC');
    $b->execute([':id' => $id]);
    $lead['can_reply'] = in_array($lead['source'], ['telegram', 'instagram', 'facebook'], true);
    // Meta: standart 24 soatlik javob oynasi (RESPONSE messaging_type).
    $lead['reply_window_open'] = $lead['source'] === 'telegram' || ((int)$lead['last_inbound_at'] > time() - 86400);
    return ['lead' => $lead, 'messages' => $m->fetchAll(), 'bookings' => $b->fetchAll()];
}
function lead_reply(int $id, string $text): array {
    $db = app_db();
    $q = $db->prepare('SELECT source, ext_key FROM leads WHERE id = :id'); $q->execute([':id' => $id]);
    $lead = $q->fetch();
    if (!$lead) { reject(404, 'Lead not found'); }
    $ext = (string)$lead['ext_key'];
    if ($lead['source'] === 'telegram' && str_starts_with($ext, 'tg:')) {
        $r = tg_send(substr($ext, 3), $text);
        if (!$r['ok']) { reject(502, 'Telegram: ' . $r['error']); }
        $mid = 'tg:' . substr($ext, 3) . ':' . (int)($r['body']['result']['message_id'] ?? 0);
    } elseif (in_array($lead['source'], ['instagram', 'facebook'], true) && preg_match('/^(ig|fb):(\d+)$/D', $ext, $m)) {
        $r = meta_send($m[2], $text);
        if (!$r['ok']) { reject(502, 'Meta: ' . $r['error']); }
        $mid = $r['message_id'] !== '' ? $r['message_id'] : null;
    } else { reject(400, 'Bu lead uchun kanal orqali javob yuborib bo‘lmaydi (telefon/email orqali bog‘laning)'); }
    lead_add_message($id, $lead['source'], 'out', $text, $mid);
    $db->prepare("UPDATE leads SET status = CASE WHEN status = 'yangi' THEN 'aloqada' ELSE status END WHERE id = :id")->execute([':id' => $id]);
    audit_log('lead_reply', ['lead_id' => $id, 'channel' => $lead['source']]);
    return ['ok' => true];
}

/* ============================== Xabarnoma (admin) ============================== */
function notify_admin(string $text): void {
    try {
        // v8: har bir natija audit.log'ga yoziladi (avval sozlanmagan/xato holat jimgina o'tkazib yuborilardi).
        // Foydalanuvchi so'rovi hech qachon buzilmaydi — xato faqat log qilinadi.
        $chat = setting_get('tg_admin_chat_id');
        if ($chat !== '' && setting_get('tg_bot_token') !== '') {
            $r = tg_send($chat, $text . "\n\n" . site_base_url() . '/admin.html#crm');
            if ($r['ok']) { audit_log('notify_sent', ['channel' => 'telegram']); return; }
            audit_log('notify_failed', ['channel' => 'telegram', 'error' => mb_substr((string)($r['error'] ?? ''), 0, 200, 'UTF-8')]);
        } else {
            audit_log('notify_skipped', ['channel' => 'telegram', 'reason' => $chat === '' ? 'admin chat ulanmagan' : 'bot token yo‘q']);
        }
        $to = setting_get('notify_email');
        if ($to !== '') {
            $m = mail_send($to, 'DARIKO — yangi murojaat', $text);
            audit_log(($m['ok'] ?? false) ? 'notify_sent' : 'notify_failed', ['channel' => 'email']);
        }
    } catch (Throwable $e) { error_log('DARIKO notify failed: ' . $e->getMessage()); }
}

/* ============================== Email: SMTP (fsockopen) yoki mail() ============================== */
function mail_header_safe(string $v): string { return trim(str_replace(["\r", "\n", "\0"], '', $v)); }
function mail_send(string $to, string $subject, string $body, array $extraHeaders = []): array {
    $to = mail_header_safe($to);
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) { return ['ok' => false, 'error' => 'Invalid recipient']; }
    $from = mail_header_safe(setting_get('smtp_from'));
    if (!filter_var($from, FILTER_VALIDATE_EMAIL)) { $from = 'no-reply@' . preg_replace('/:\d+$/', '', parse_url(site_base_url(), PHP_URL_HOST) ?: 'dariko.uz'); }
    $fromName = mail_header_safe(setting_get('smtp_from_name', 'DARIKO HR & MARKETING'));
    $enc = fn(string $s) => '=?UTF-8?B?' . base64_encode($s) . '?=';
    $headers = [
        'From' => $enc($fromName) . ' <' . $from . '>',
        'MIME-Version' => '1.0',
        'Content-Type' => 'text/plain; charset=UTF-8',
        'Content-Transfer-Encoding' => 'base64',
        'Date' => date(DATE_RFC2822),
        'Message-ID' => '<' . bin2hex(random_bytes(12)) . '@' . substr(strrchr($from, '@'), 1) . '>',
    ];
    foreach ($extraHeaders as $k => $v) { $headers[mail_header_safe((string)$k)] = mail_header_safe((string)$v); }
    $b64 = rtrim(chunk_split(base64_encode(str_replace("\r\n", "\n", $body)), 76, "\r\n"));
    $host = setting_get('smtp_host');
    if ($host === '') {
        $h = ''; foreach ($headers as $k => $v) { $h .= "$k: $v\r\n"; }
        $ok = @mail($to, $enc(mail_header_safe($subject)), $b64, rtrim($h));
        return ['ok' => $ok, 'error' => $ok ? '' : 'mail() failed (hostingda sendmail sozlanmagan bo‘lishi mumkin)'];
    }
    return smtp_send($host, (int)(setting_get('smtp_port') ?: '587'), setting_get('smtp_secure', 'tls'), setting_get('smtp_user'), setting_get('smtp_pass'),
        $from, $to, $headers + ['To' => '<' . $to . '>', 'Subject' => $enc(mail_header_safe($subject))], $b64);
}
function smtp_send(string $host, int $port, string $secure, string $user, string $pass, string $from, string $to, array $headers, string $b64body): array {
    if (!preg_match('/^[a-z0-9.-]{1,190}$/iD', $host) || $port < 1 || $port > 65535) { return ['ok' => false, 'error' => 'Invalid SMTP host/port']; }
    $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'SNI_enabled' => true, 'peer_name' => $host]]);
    $fp = @stream_socket_client(($secure === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port, $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) { return ['ok' => false, 'error' => "SMTP ulanish xatosi: $errstr"]; }
    stream_set_timeout($fp, 15);
    $read = function () use ($fp): array {
        $data = '';
        while (($line = fgets($fp, 1024)) !== false) { $data .= $line; if (strlen($line) < 4 || $line[3] === ' ') { break; } }
        return [(int)substr($data, 0, 3), $data];
    };
    $cmd = function (string $c, array $okCodes) use ($fp, $read): array {
        fwrite($fp, $c . "\r\n"); [$code, $data] = $read();
        if (!in_array($code, $okCodes, true)) { throw new RuntimeException('SMTP: ' . preg_replace('/\s+/', ' ', trim(substr($data, 0, 200)))); }
        return [$code, $data];
    };
    try {
        [$code] = $read(); if ($code !== 220) { throw new RuntimeException('SMTP salomlashuv xatosi'); }
        $ehloHost = preg_replace('/[^a-z0-9.-]/i', '', (string)(parse_url(site_base_url(), PHP_URL_HOST) ?: 'localhost'));
        [, $caps] = $cmd('EHLO ' . $ehloHost, [250]);
        if ($secure === 'tls') {
            $cmd('STARTTLS', [220]);
            if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT)) { throw new RuntimeException('STARTTLS muvaffaqiyatsiz'); }
            [, $caps] = $cmd('EHLO ' . $ehloHost, [250]);
        }
        if ($user !== '') {
            // v13 (audit 5-masala): shifrlanmagan ulanishda login/parol HECH QACHON yuborilmaydi (ochiq matnda tarmoqda ketadi).
            // Xat yuborilmaydi, aniq konfiguratsiya xatosi qaytariladi va jurnalga yoziladi.
            if (!in_array($secure, ['tls', 'ssl'], true)) {
                audit_log('smtp_plaintext_auth_refused', ['host' => $host]);
                throw new RuntimeException('SMTP sozlama xatosi: «Shifrlash» = none bo‘lganda login/parol shifrlanmagan holda yuborilardi — xavfsizlik uchun rad etildi. «tls» (587) yoki «ssl» (465) tanlang, yoki login/parolni o‘chiring (autentifikatsiyasiz relay).');
            }
            $cmd('AUTH LOGIN', [334]); $cmd(base64_encode($user), [334]); $cmd(base64_encode($pass), [235]);
        }
        $cmd('MAIL FROM:<' . $from . '>', [250]);
        $cmd('RCPT TO:<' . $to . '>', [250, 251]);
        $cmd('DATA', [354]);
        $msg = ''; foreach ($headers as $k => $v) { $msg .= "$k: $v\r\n"; }
        $msg .= "\r\n" . $b64body;  // base64 qatorlari hech qachon "." bilan boshlanmaydi — dot-stuffing kerak emas
        $cmd($msg . "\r\n.", [250]);
        @fwrite($fp, "QUIT\r\n"); fclose($fp);
        return ['ok' => true, 'error' => ''];
    } catch (Throwable $e) {
        @fclose($fp);
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

/* ============================== Bron kalendari ============================== */
function booking_rules(): array {
    $d = json_decode(setting_get('booking_rules'), true);
    $def = ['slot_minutes' => 60, 'lead_hours' => 3, 'horizon_days' => 30, 'daily_cap' => BOOKING_DAILY_CAP_DEFAULT,
            // Standart: Du–Sha 09:00–19:00 (JSON-LD openingHoursSpecification va llms.txt dagi ish vaqti bilan bir xil).
            'week' => ['1' => [['09:00', '19:00']], '2' => [['09:00', '19:00']], '3' => [['09:00', '19:00']], '4' => [['09:00', '19:00']],
                       '5' => [['09:00', '19:00']], '6' => [['09:00', '19:00']], '7' => []]];
    if (!is_array($d)) { return $def; }
    return array_replace($def, array_intersect_key($d, $def));
}
function booking_rules_validate(array $in): array {
    $slot = (int)($in['slot_minutes'] ?? 60);
    if (!in_array($slot, [15, 20, 30, 45, 60, 90, 120], true)) { reject(400, 'Slot davomiyligi noto‘g‘ri'); }
    $lead = max(0, min(72, (int)($in['lead_hours'] ?? 3)));
    $hor = max(1, min(120, (int)($in['horizon_days'] ?? 30)));
    $week = [];
    for ($i = 1; $i <= 7; $i++) {
        $w = [];
        foreach ((array)($in['week'][(string)$i] ?? []) as $win) {
            if (!is_array($win) || count($win) !== 2) { reject(400, 'Vaqt oralig‘i noto‘g‘ri'); }
            [$a, $b] = $win;
            if (!is_string($a) || !is_string($b) || !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/D', $a) || !preg_match('/^([01]\d|2[0-3]):[0-5]\d$|^24:00$/D', $b) || $a >= $b) { reject(400, "Vaqt oralig‘i noto‘g‘ri: $a–$b"); }
            $w[] = [$a, $b];
        }
        if (count($w) > 6) { reject(400, 'Juda ko‘p oraliq'); }
        $week[(string)$i] = $w;
    }
    $cap = max(BOOKING_DAILY_CAP_MIN, min(BOOKING_DAILY_CAP_MAX, (int)($in['daily_cap'] ?? BOOKING_DAILY_CAP_DEFAULT)));
    return ['slot_minutes' => $slot, 'lead_hours' => $lead, 'horizon_days' => $hor, 'daily_cap' => $cap, 'week' => $week];
}
function booking_slots_for(string $date, array $rules, array $overrides, array $taken, int $now): array {
    if (isset($overrides[$date])) { return []; }
    $tz = new DateTimeZone(APP_TZ);
    $day = DateTimeImmutable::createFromFormat('!Y-m-d', $date, $tz);
    if (!$day) { return []; }
    $out = [];
    foreach ($rules['week'][$day->format('N')] ?? [] as [$a, $b]) {
        $t = (int)substr($a, 0, 2) * 60 + (int)substr($a, 3, 2);
        $end = (int)substr($b, 0, 2) * 60 + (int)substr($b, 3, 2);
        for (; $t + $rules['slot_minutes'] <= $end; $t += $rules['slot_minutes']) {
            $slot = sprintf('%02d:%02d', intdiv($t, 60), $t % 60);
            if ($day->getTimestamp() + $t * 60 < $now + $rules['lead_hours'] * 3600) { continue; }
            if (isset($taken[$date . ' ' . $slot])) { continue; }
            $out[] = $slot;
        }
    }
    return $out;
}
function booking_availability(): array {
    $rules = booking_rules(); $db = app_db(); $now = time();
    $tz = new DateTimeZone(APP_TZ);
    $start = new DateTimeImmutable('today', $tz);
    $last = $start->modify('+' . $rules['horizon_days'] . ' days')->format('Y-m-d');
    $ov = [];
    $q = $db->prepare('SELECT date FROM availability_overrides WHERE closed = 1 AND date >= :a AND date <= :b');
    $q->execute([':a' => $start->format('Y-m-d'), ':b' => $last]);
    foreach ($q->fetchAll() as $r) { $ov[$r['date']] = true; }
    $taken = [];
    $q = $db->prepare("SELECT requested_date, requested_time_slot FROM bookings WHERE status IN ('pending','confirmed') AND requested_date >= :a AND requested_date <= :b");
    $q->execute([':a' => $start->format('Y-m-d'), ':b' => $last]);
    foreach ($q->fetchAll() as $r) { $taken[$r['requested_date'] . ' ' . $r['requested_time_slot']] = true; }
    $days = [];
    for ($i = 0; $i <= $rules['horizon_days']; $i++) {
        $d = $start->modify("+$i days")->format('Y-m-d');
        $slots = booking_slots_for($d, $rules, $ov, $taken, $now);
        if ($slots) { $days[] = ['date' => $d, 'slots' => $slots]; }
    }
    return ['timezone' => APP_TZ, 'slot_minutes' => $rules['slot_minutes'], 'days' => $days];
}
// v12 (B3): cheklov buzilishini xabar MATNI ("UNIQUE") bo'yicha emas, standart kodlar bo'yicha aniqlash.
// PDO SQLite: SQLSTATE '23000' (integrity constraint violation) + drayver kodi 19 (SQLITE_CONSTRAINT).
// PDO kengaytirilgan kodni (2067 = UNIQUE) bermaydi, shuning uchun bron uchun semantik tasdiq ham qilinadi
// (booking_slot_taken) — matn/tilga bog'liqlik yo'q.
function pdo_is_constraint_violation(PDOException $e): bool {
    $info = $e->errorInfo ?? null;
    if (is_array($info) && ($info[0] ?? null) === '23000') { return !isset($info[1]) || (int)$info[1] === 19; }
    return (string)$e->getCode() === '23000';
}
// v14 (audit-2, 1-masala): og'ir bir vaqtda ko'p so'rov ostida busy_timeout (common.php, 5000ms) tugasa,
// SQLite "database is locked" bilan xato beradi (SQLSTATE HY000, drayver kodi 5 = SQLITE_BUSY, ba'zan 6 = SQLITE_LOCKED).
// Bu — yuqoridagi UNIQUE cheklov buzilishidan farqli, kutilgan vaziyat (resurs band), shuning uchun alohida aniqlanadi
// va boshqa joylardagi (masalan daily_cap) kabi toza 503 javobga aylantiriladi — xom PDOException chiqib ketmasligi uchun.
function pdo_is_locked(PDOException $e): bool {
    $info = $e->errorInfo ?? null;
    if (is_array($info) && ($info[0] ?? null) === 'HY000') { return in_array((int)($info[1] ?? 0), [5, 6], true); }
    return (string)$e->getCode() === 'HY000' && str_contains($e->getMessage(), 'database is locked');
}
function booking_slot_taken(string $date, string $time): bool {
    $q = app_db()->prepare("SELECT 1 FROM bookings WHERE requested_date = :d AND requested_time_slot = :t AND status IN ('pending','confirmed') LIMIT 1");
    $q->execute([':d' => $date, ':t' => $time]);
    return $q->fetchColumn() !== false;
}
// v13 (audit 3-masala): bron kalendarini to'ldirib tashlashga (flood) qarshi.
//  1) Telefon bo'yicha: bitta raqamda bir vaqtning o'zida faqat BITTA ochiq (pending/confirmed, bugundan keyingi) bron.
//     Raqam public_lead_fields() dagi bilan bir xil normallashtiriladi ('+998XXXXXXXXX', leads.phone shu shaklda saqlanadi).
//  2) Sayt bo'yicha kunlik «circuit-breaker»: oxirgi 24 soatda yaratilgan va hali 'pending' bronlar soni
//     booking_rules.daily_cap (admin: Bron -> Ish vaqti; standart 60, 10..500) ga yetsa yangi onlayn bron vaqtincha yopiladi
//     (mijozga telefon raqami ko'rsatiladi, admin Telegram'da ogohlantiriladi). Admin bronlarni tasdiqlasa/bekor qilsa, hisob kamayadi.
//  3) IP bo'yicha (api.php): 4 ta / 10 daqiqa (avvalgidek) + v13: 10 ta / 24 soat.
const BOOKING_DAILY_CAP_DEFAULT = 60;
const BOOKING_DAILY_CAP_MIN = 10;
const BOOKING_DAILY_CAP_MAX = 500;
function booking_contact_hint(): string {
    $s = content_read()['settings'] ?? [];
    $phone = is_string($s['phone'] ?? null) ? $s['phone'] : '';
    $tg = is_string($s['telegram'] ?? null) && $s['telegram'] !== '' ? ' yoki Telegram @' . $s['telegram'] : '';
    return $phone !== '' ? "$phone$tg" : ($tg !== '' ? ltrim($tg, ' yoki') : 'sayt kontaktlari');
}
function booking_create(array $in): array {
    $f = public_lead_fields($in);
    $date = (string)($in['date'] ?? ''); $time = (string)($in['time'] ?? '');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/D', $date) || !preg_match('/^\d{2}:\d{2}$/D', $time)) { reject(422, 'Sana/vaqt noto‘g‘ri'); }
    $db = app_db();
    // v14 (audit-2, 1-masala): BEGIN IMMEDIATE endi try ICHIDA — og'ir yuk ostida busy_timeout tugab
    // "database is locked" chiqsa (hali tranzaksiya ochilmagan bo'lsa ham), quyidagi catch uni tutib,
    // xom PDOException o'rniga toza 503 javob beradi (pastdagi pdo_is_locked tekshiruvi orqali).
    try {
        $db->exec('BEGIN IMMEDIATE');
        $today = (new DateTimeImmutable('now', new DateTimeZone(APP_TZ)))->format('Y-m-d');
        $ex = $db->prepare("SELECT b.requested_date, b.requested_time_slot, b.status FROM bookings b JOIN leads l ON l.id = b.lead_id
                            WHERE l.phone = :p AND b.status IN ('pending','confirmed') AND b.requested_date >= :today ORDER BY b.requested_date, b.requested_time_slot LIMIT 1");
        $ex->execute([':p' => $f['phone'], ':today' => $today]);
        if ($open = $ex->fetch()) {
            $db->exec('ROLLBACK');
            audit_log('booking_rejected_existing', ['date' => $date]);
            json_reply(409, ['error' => "Bu telefon raqamida allaqachon faol bron bor: {$open['requested_date']} {$open['requested_time_slot']} ("
                . ($open['status'] === 'confirmed' ? 'tasdiqlangan' : 'tasdiq kutilmoqda') . '). Bir vaqtda faqat bitta bron mumkin. '
                . 'Vaqtni o‘zgartirish yoki bekor qilish uchun biz bilan bog‘laning: ' . booking_contact_hint() . '.', 'code' => 'existing_booking']);
        }
        $cap = (int)booking_rules()['daily_cap'];
        $cnt = $db->prepare("SELECT COUNT(*) FROM bookings WHERE status = 'pending' AND created_at >= :since");
        $cnt->execute([':since' => time() - 86400]);
        if ((int)$cnt->fetchColumn() >= $cap) {
            $db->exec('ROLLBACK');
            audit_log('booking_daily_cap_reached', ['cap' => $cap]);
            if ((int)setting_get('booking_cap_notified_at', '0') < time() - 86400) {
                setting_set('booking_cap_notified_at', (string)time());
                notify_admin("⚠️ Onlayn bron vaqtincha yopildi: 24 soatda $cap ta tasdiqlanmagan bron (chegara). Kalendarni tekshiring — spam bo‘lishi mumkin. Chegara: Bron → Ish vaqti.");
            }
            header('Retry-After: 3600');
            json_reply(503, ['error' => 'Onlayn bron hozircha vaqtincha yopiq (so‘rovlar juda ko‘p). Iltimos, qo‘ng‘iroq qiling: ' . booking_contact_hint() . ' yoki «Ariza qoldirish» formasidan foydalaning.', 'code' => 'daily_cap']);
        }
        $avail = booking_availability();
        $ok = false;
        foreach ($avail['days'] as $d) { if ($d['date'] === $date && in_array($time, $d['slots'], true)) { $ok = true; break; } }
        if (!$ok) { $db->exec('ROLLBACK'); reject(409, 'Bu vaqt band yoki mavjud emas. Boshqa vaqtni tanlang.'); }
        $leadId = lead_create($f, 'booking');
        lead_add_message($leadId, 'web_form', 'in', "Konsultatsiya broni: $date $time\n" . lead_summary_text($f));
        $db->prepare('INSERT INTO bookings (lead_id, requested_date, requested_time_slot, status, service, created_at, updated_at) VALUES (:l, :d, :t, \'pending\', :s, :now, :now)')
           ->execute([':l' => $leadId, ':d' => $date, ':t' => $time, ':s' => $f['service'], ':now' => time()]);
        $db->exec('COMMIT');
    } catch (PDOException $e) {
        if ($db->inTransaction()) { $db->exec('ROLLBACK'); }
        if (pdo_is_constraint_violation($e) && booking_slot_taken($date, $time)) { reject(409, 'Bu vaqt hozirgina band qilindi. Boshqa vaqtni tanlang.'); }
        // v14 (audit-2, 1-masala): baza band (lock contention) — kutilmagan xato emas, boshqa joylardagi
        // (daily_cap) kabi toza, qayta urinishga undovchi 503 javob; stack-trace chiqib ketmaydi.
        if (pdo_is_locked($e)) {
            audit_log('booking_db_busy');
            header('Retry-After: 5');
            json_reply(503, ['error' => 'Server hozir band, birozdan so‘ng qayta urinib ko‘ring. Agar takrorlansa, qo‘ng‘iroq qiling: ' . booking_contact_hint() . '.', 'code' => 'db_busy']);
        }
        throw $e;
    }
    notify_admin("📅 Yangi konsultatsiya broni: $date $time\n" . lead_summary_text($f));
    return ['ok' => true, 'date' => $date, 'time' => $time];
}
function bookings_month(string $month): array {
    if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/D', $month)) { reject(400, 'Invalid month'); }
    $q = app_db()->prepare('SELECT b.id, b.lead_id, b.requested_date, b.requested_time_slot, b.status, b.service, b.created_at, l.name, l.phone
                            FROM bookings b JOIN leads l ON l.id = b.lead_id WHERE b.requested_date LIKE :m ORDER BY b.requested_date, b.requested_time_slot');
    $q->execute([':m' => $month . '-%']);
    $o = app_db()->prepare('SELECT date, note FROM availability_overrides WHERE closed = 1 AND date LIKE :m');
    $o->execute([':m' => $month . '-%']);
    return ['month' => $month, 'bookings' => $q->fetchAll(), 'closed' => $o->fetchAll(), 'rules' => booking_rules()];
}
function booking_set_status(int $id, string $status): array {
    if (!in_array($status, ['pending', 'confirmed', 'cancelled'], true)) { reject(400, 'Invalid status'); }
    $db = app_db();
    try {
        $st = $db->prepare('UPDATE bookings SET status = :s, updated_at = :t WHERE id = :id');
        $st->execute([':s' => $status, ':t' => time(), ':id' => $id]);
    } catch (PDOException $e) {
        // Status CHECK yuqorida oldindan tekshirilgan — bu UPDATE'da yagona mumkin bo'lgan cheklov uq_booking_active.
        if (pdo_is_constraint_violation($e)) { reject(409, 'Bu vaqtda boshqa faol bron bor — avval uni bekor qiling'); }
        throw $e;
    }
    if ($st->rowCount() === 0) { reject(404, 'Booking not found'); }
    if ($status === 'confirmed') {
        $db->prepare("UPDATE leads SET status = CASE WHEN status = 'yangi' THEN 'aloqada' ELSE status END, updated_at = :t WHERE id = (SELECT lead_id FROM bookings WHERE id = :id)")
           ->execute([':t' => time(), ':id' => $id]);
    }
    audit_log('booking_' . $status, ['booking_id' => $id]);
    return ['ok' => true];
}

/* ============================== Newsletter ============================== */
function newsletter_subscribe(array $in): array {
    if (($in['website'] ?? '') !== '') { return ['ok' => true]; }
    $email = mb_strtolower(clean_text($in['email'] ?? '', 190), 'UTF-8');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { reject(422, 'Email manzil noto‘g‘ri'); }
    $name = clean_text($in['name'] ?? '', 80);
    $lang = ($in['lang'] ?? '') === 'ru' ? 'ru' : 'uz';
    $db = app_db();
    $q = $db->prepare('SELECT id, confirmed, unsubscribed_at, subscribed_at FROM subscribers WHERE email = :e'); $q->execute([':e' => $email]);
    $row = $q->fetch();
    $confirm = bin2hex(random_bytes(20));
    if ($row) {
        // Mavjud email: holat oshkor qilinmaydi (enumeratsiyaga qarshi) — tasdiqlanmagan bo'lsa havola qayta yuboriladi (10 daqiqada 1 marta).
        if ((int)$row['confirmed'] === 1 && $row['unsubscribed_at'] === null) { return ['ok' => true]; }
        if ((int)$row['subscribed_at'] > time() - 600) { return ['ok' => true]; }
        $db->prepare('UPDATE subscribers SET confirm_token = :c, confirmed = 0, unsubscribed_at = NULL, subscribed_at = :t, name = CASE WHEN :n <> \'\' THEN :n ELSE name END, lang = :l WHERE id = :id')
           ->execute([':c' => $confirm, ':t' => time(), ':n' => $name, ':l' => $lang, ':id' => $row['id']]);
    } else {
        $db->prepare('INSERT INTO subscribers (email, name, subscribed_at, confirm_token, unsubscribe_token, lang) VALUES (:e, :n, :t, :c, :u, :l)')
           ->execute([':e' => $email, ':n' => $name, ':t' => time(), ':c' => $confirm, ':u' => bin2hex(random_bytes(20)), ':l' => $lang]);
    }
    // v13: havola faqat ishonchli bazadan (Host sarlavhasidan emas). Env sozlanmagan bo'lsa kanonik domen ishlatiladi va jurnalga yoziladi.
    if (site_url_configured() === null) { error_log('DARIKO: DARIKO_SITE_URL sozlanmagan — newsletter havolasi ' . SITE_URL_DEFAULT . ' ga yo‘naltirildi'); }
    $link = site_base_url() . '/newsletter.php?a=confirm&t=' . $confirm;
    $body = $lang === 'ru'
        ? "Здравствуйте" . ($name ? ", $name" : '') . "!\n\nВы подписались на рассылку DARIKO HR & MARKETING. Чтобы подтвердить подписку, откройте ссылку:\n$link\n\nЕсли вы не подписывались, просто проигнорируйте это письмо."
        : "Assalomu alaykum" . ($name ? ", $name" : '') . "!\n\nSiz DARIKO HR & MARKETING yangiliklariga obuna bo‘ldingiz. Obunani tasdiqlash uchun havolani oching:\n$link\n\nAgar siz obuna bo‘lmagan bo‘lsangiz, bu xatni e’tiborsiz qoldiring.";
    $r = mail_send($email, $lang === 'ru' ? 'Подтвердите подписку — DARIKO' : 'Obunani tasdiqlang — DARIKO', $body);
    if (!$r['ok']) { error_log('DARIKO newsletter confirm mail failed: ' . $r['error']); }
    return ['ok' => true];
}
function newsletter_unsub_link(string $token): string { return site_base_url() . '/newsletter.php?a=unsubscribe&t=' . $token; }
function newsletter_send_batch(int $campaignId, int $batch = 25): array {
    $db = app_db();
    $c = $db->prepare('SELECT * FROM campaigns WHERE id = :id'); $c->execute([':id' => $campaignId]);
    $camp = $c->fetch();
    if (!$camp) { reject(404, 'Campaign not found'); }
    $q = $db->prepare('SELECT id, email, unsubscribe_token FROM subscribers WHERE confirmed = 1 AND unsubscribed_at IS NULL AND last_campaign_id < :c ORDER BY id LIMIT :n');
    $q->bindValue(':c', $campaignId, PDO::PARAM_INT); $q->bindValue(':n', $batch, PDO::PARAM_INT); $q->execute();
    $sent = 0; $failed = 0;
    foreach ($q->fetchAll() as $s) {
        $unsub = newsletter_unsub_link($s['unsubscribe_token']);
        $r = mail_send($s['email'], $camp['subject'], $camp['body'] . "\n\n—\nObunani bekor qilish / Отписаться: $unsub",
            ['List-Unsubscribe' => '<' . $unsub . '>', 'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click']);
        $db->prepare('UPDATE subscribers SET last_campaign_id = :c WHERE id = :id')->execute([':c' => $campaignId, ':id' => $s['id']]);
        $r['ok'] ? $sent++ : $failed++;
        usleep(250000); // hosting pochta limitlariga urilmaslik uchun
    }
    $db->prepare('UPDATE campaigns SET sent = sent + :s, failed = failed + :f WHERE id = :id')->execute([':s' => $sent, ':f' => $failed, ':id' => $campaignId]);
    $left = $db->prepare('SELECT COUNT(*) FROM subscribers WHERE confirmed = 1 AND unsubscribed_at IS NULL AND last_campaign_id < :c');
    $left->execute([':c' => $campaignId]);
    $remaining = (int)$left->fetchColumn();
    if ($remaining === 0) {
        $db->prepare('UPDATE campaigns SET finished_at = :t WHERE id = :id AND finished_at IS NULL')->execute([':t' => time(), ':id' => $campaignId]);
        $cc = $db->prepare('SELECT sent, failed FROM campaigns WHERE id = :id'); $cc->execute([':id' => $campaignId]); $tot = $cc->fetch();
        audit_log('newsletter_campaign_finished', ['campaign_id' => $campaignId, 'sent' => (int)$tot['sent'], 'failed' => (int)$tot['failed']]);
    }
    return ['ok' => true, 'sent' => $sent, 'failed' => $failed, 'remaining' => $remaining];
}

/* ============================== Kanal sozlamalari (admin) ============================== */
function channels_public_view(): array {
    $out = [];
    foreach (CHANNEL_KEYS as $k => $spec) {
        $v = setting_get($k);
        $out[$k] = ['set' => $v !== '', 'value' => $spec['secret'] ? mask_secret($v) : $v, 'secret' => $spec['secret']];
    }
    return ['fields' => $out, 'telegram_webhook_url' => tg_webhook_url(), 'meta_webhook_url' => meta_webhook_url(),
            'site_url_configured' => site_url_configured() !== null, 'site_url' => site_base_url(),
            'smtp_plaintext_auth' => setting_get('smtp_host') !== '' && setting_get('smtp_user') !== '' && !in_array(setting_get('smtp_secure', 'tls'), ['tls', 'ssl'], true),
            'tg_bot_username' => setting_get('tg_bot_username'), 'tg_webhook_set_at' => (int)setting_get('tg_webhook_set_at', '0'),
            'curl' => extension_loaded('curl')];
}
function channels_save(string $key, string $value): array {
    if (!isset(CHANNEL_KEYS[$key])) { reject(400, 'Unknown setting'); }
    $value = clean_text($value, CHANNEL_KEYS[$key]['max']);
    if ($value !== '') {
        $valid = match ($key) {
            'tg_bot_token' => (bool)preg_match('/^\d{5,16}:[A-Za-z0-9_-]{30,64}$/D', $value),
            'tg_webhook_secret' => (bool)preg_match('/^[A-Za-z0-9_-]{16,256}$/D', $value),
            'tg_admin_chat_id' => (bool)preg_match('/^-?\d{3,20}$/D', $value),
            'meta_app_secret' => (bool)preg_match('/^[a-f0-9]{32}$/iD', $value),
            'meta_verify_token' => (bool)preg_match('/^[A-Za-z0-9_-]{12,128}$/D', $value),
            'meta_page_token' => (bool)preg_match('/^[A-Za-z0-9_-]{40,1024}$/D', $value),
            'smtp_host' => (bool)preg_match('/^[a-z0-9.-]{3,190}$/iD', $value),
            'smtp_port' => ctype_digit($value) && (int)$value > 0 && (int)$value < 65536,
            'smtp_secure' => in_array($value, ['tls', 'ssl', 'none'], true),
            'smtp_from', 'notify_email' => (bool)filter_var($value, FILTER_VALIDATE_EMAIL),
            default => true,
        };
        if (!$valid) { reject(400, 'Qiymat formati noto‘g‘ri: ' . $key); }
    }
    setting_set($key, $value);
    if ($key === 'tg_bot_token') { setting_set('tg_bot_username', ''); setting_set('tg_webhook_set_at', '0'); }
    audit_log('channel_setting_save', ['key' => $key, 'value' => CHANNEL_KEYS[$key]['secret'] ? mask_secret($value) : $value]);
    return ['ok' => true, 'masked' => CHANNEL_KEYS[$key]['secret'] ? mask_secret($value) : $value];
}
function telegram_setup(): array {
    $token = setting_get('tg_bot_token');
    if ($token === '') { reject(400, 'Avval bot tokenini saqlang'); }
    $me = tg_call('getMe', []);
    if (!$me['ok']) { reject(502, 'Token tekshiruvi: ' . $me['error']); }
    $secret = setting_get('tg_webhook_secret');
    if ($secret === '') { $secret = bin2hex(random_bytes(24)); setting_set('tg_webhook_secret', $secret); }
    $url = tg_webhook_url();
    if (!str_starts_with($url, 'https://')) { reject(400, 'Telegram webhook faqat HTTPS manzilga o‘rnatiladi: ' . $url); }
    $r = tg_call('setWebhook', ['url' => $url, 'secret_token' => $secret, 'allowed_updates' => ['message', 'edited_message'], 'drop_pending_updates' => false, 'max_connections' => 10]);
    if (!$r['ok']) { reject(502, 'setWebhook: ' . $r['error']); }
    $username = clean_text($me['body']['result']['username'] ?? '', 64);
    setting_set('tg_bot_username', $username); setting_set('tg_webhook_set_at', (string)time());
    audit_log('telegram_webhook_set', ['bot' => $username]);
    return ['ok' => true, 'bot_username' => $username, 'webhook_url' => $url];
}
