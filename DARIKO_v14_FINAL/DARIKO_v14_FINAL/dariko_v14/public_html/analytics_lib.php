<?php
declare(strict_types=1);
// DARIKO — SQLite asosidagi anonim analitika kutubxonasi.
// Faqat api.php va storage/cleanup_analytics.php orqali include qilinadi; to'g'ridan-to'g'ri
// HTTP so'rovi 404 qaytaradi (+ .htaccess ham taqiqlaydi).
// Xavfsizlik qoidasi: BARCHA SQL so'rovlar PDO prepared statement orqali; string birlashtirish yo'q.
if (!defined('DARIKO_ENTRY')) { http_response_code(404); exit; }

const ANALYTICS_DB = DATA_DIR . '/analytics.sqlite';
const ANALYTICS_SECRET_FILE = DATA_DIR . '/analytics_secret.key';
const ANALYTICS_RETENTION_DAYS = 395;   // ~13 oy
const ANALYTICS_ONLINE_WINDOW = 120;    // oxirgi 2 daqiqada heartbeat = onlayn
const ANALYTICS_TZ = 'Asia/Tashkent';

function analytics_available(): bool {
    return extension_loaded('pdo_sqlite');
}

function analytics_db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) { return $pdo; }
    if (!analytics_available()) { reject(503, 'pdo_sqlite PHP extension required'); }
    $pdo = sqlite_open(ANALYTICS_DB); // v12 (C1): umumiy ulanish sozlamasi (common.php)
    $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS visits (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    ts            INTEGER NOT NULL,
    day           TEXT    NOT NULL,
    path          TEXT    NOT NULL,
    session_id    TEXT    NOT NULL,
    ip_hash       TEXT    NOT NULL,
    user_agent    TEXT    NOT NULL DEFAULT '',
    device_type   TEXT    NOT NULL CHECK (device_type IN ('mobile','tablet','desktop')),
    browser       TEXT    NOT NULL,
    os            TEXT    NOT NULL,
    referrer_host TEXT    NOT NULL DEFAULT '',
    lang          TEXT    NOT NULL DEFAULT ''
);
CREATE INDEX IF NOT EXISTS idx_visits_ts ON visits(ts);
CREATE INDEX IF NOT EXISTS idx_visits_day ON visits(day);
CREATE INDEX IF NOT EXISTS idx_visits_ip_ts ON visits(ip_hash, ts);
CREATE TABLE IF NOT EXISTS live_sessions (
    session_id  TEXT PRIMARY KEY,
    ip_hash     TEXT    NOT NULL,
    first_seen  INTEGER NOT NULL,
    last_seen   INTEGER NOT NULL,
    path        TEXT    NOT NULL,
    device_type TEXT    NOT NULL,
    browser     TEXT    NOT NULL,
    os          TEXT    NOT NULL,
    lang        TEXT    NOT NULL DEFAULT '',
    hits        INTEGER NOT NULL DEFAULT 1
);
CREATE INDEX IF NOT EXISTS idx_live_last_seen ON live_sessions(last_seen);
CREATE TABLE IF NOT EXISTS rate_hits (
    ip_hash TEXT NOT NULL,
    ts      INTEGER NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_rate_ip_ts ON rate_hits(ip_hash, ts);
-- v7: A/B testlar. Tayinlash deterministik: HMAC(maxfiy kalit, session_id|exp_id) mod variantlar soni.
CREATE TABLE IF NOT EXISTS ab_experiments (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    name       TEXT NOT NULL,
    slot       TEXT NOT NULL CHECK (slot IN ('hero_title_accent','hero_title_rest','hero_cta')),
    goal       TEXT NOT NULL DEFAULT 'lead' CHECK (goal IN ('cta_click','lead')),
    status     TEXT NOT NULL DEFAULT 'draft' CHECK (status IN ('draft','running','stopped')),
    variants   TEXT NOT NULL,
    created_at INTEGER NOT NULL,
    started_at INTEGER
);
CREATE TABLE IF NOT EXISTS ab_exposures (
    exp_id     INTEGER NOT NULL,
    session_id TEXT NOT NULL,
    variant    INTEGER NOT NULL,
    ts         INTEGER NOT NULL,
    views      INTEGER NOT NULL DEFAULT 1,
    PRIMARY KEY (exp_id, session_id)
);
CREATE TABLE IF NOT EXISTS ab_conversions (
    exp_id     INTEGER NOT NULL,
    session_id TEXT NOT NULL,
    variant    INTEGER NOT NULL,
    ts         INTEGER NOT NULL,
    PRIMARY KEY (exp_id, session_id)
);
-- v12 (B6): ab_list() hisoboti (WHERE exp_id = ? GROUP BY variant) uchun. PK (exp_id, session_id) exp_id bo'yicha
-- qidiruvni allaqachon qoplaydi, lekin (exp_id, variant) GROUP BY'ni vaqtinchalik saralashsiz bajaradi.
CREATE INDEX IF NOT EXISTS idx_ab_exposures_exp ON ab_exposures(exp_id, variant);
CREATE INDEX IF NOT EXISTS idx_ab_conversions_exp ON ab_conversions(exp_id, variant);
SQL);
    return $pdo;
}

// Maxfiy "tuz" (salt): birinchi ishga tushishda storage/ ichida tasodifiy yaratiladi.
function analytics_secret(): string {
    if (is_file(ANALYTICS_SECRET_FILE)) {
        $s = trim((string)file_get_contents(ANALYTICS_SECRET_FILE));
        if (strlen($s) >= 32) { return $s; }
    }
    $s = bin2hex(random_bytes(32));
    $h = @fopen(ANALYTICS_SECRET_FILE, 'x');
    if ($h !== false) { fwrite($h, $s); fclose($h); @chmod(ANALYTICS_SECRET_FILE, 0600); return $s; }
    $existing = trim((string)@file_get_contents(ANALYTICS_SECRET_FILE));
    if (strlen($existing) >= 32) { return $existing; }
    reject(500, 'Analytics storage not writable');
}

// IP hech qachon xom holda saqlanmaydi. Kunlik aylanadigan hash: bir kunda bir foydalanuvchini
// sanash mumkin, lekin kunlar orasida bog'lab bo'lmaydi.
function analytics_ip_hash(string $ip): string {
    return hash_hmac('sha256', $ip . '|' . date('Y-m-d'), analytics_secret());
}

function analytics_clean_text(string $s, int $max): string {
    $s = mb_convert_encoding($s, 'UTF-8', 'UTF-8');
    $s = preg_replace('/[\x00-\x1F\x7F]/u', '', $s) ?? '';
    return mb_substr(trim($s), 0, $max, 'UTF-8');
}

function analytics_is_bot(string $ua): bool {
    return $ua === '' || (bool)preg_match('/bot|crawl|spider|slurp|facebookexternalhit|headless|lighthouse|preview|curl\/|wget|python-requests|httpclient|monitor/i', $ua);
}

/** @return array{device:string,browser:string,os:string} */
function analytics_parse_ua(string $ua): array {
    // Qurilma
    if (preg_match('/iPad|Tablet|PlayBook|Silk|Kindle|(Android(?!.*Mobile))/i', $ua)) { $device = 'tablet'; }
    elseif (preg_match('/Mobi|iPhone|iPod|Android.*Mobile|Windows Phone|Opera Mini|IEMobile/i', $ua)) { $device = 'mobile'; }
    else { $device = 'desktop'; }
    // OT (tartib muhim: iOS/Android Linux'dan oldin)
    if (preg_match('/iPhone|iPad|iPod/i', $ua)) { $os = 'iOS'; }
    elseif (preg_match('/Android/i', $ua)) { $os = 'Android'; }
    elseif (preg_match('/Windows/i', $ua)) { $os = 'Windows'; }
    elseif (preg_match('/Mac OS X|Macintosh/i', $ua)) { $os = 'macOS'; }
    elseif (preg_match('/CrOS/i', $ua)) { $os = 'ChromeOS'; }
    elseif (preg_match('/Linux|X11/i', $ua)) { $os = 'Linux'; }
    else { $os = 'Boshqa'; }
    // Brauzer (tartib muhim: in-app va Chromium-asoslilar Chrome'dan oldin)
    if (preg_match('/Telegram/i', $ua)) { $browser = 'Telegram'; }
    elseif (preg_match('/Instagram/i', $ua)) { $browser = 'Instagram'; }
    elseif (preg_match('/FBAN|FBAV/i', $ua)) { $browser = 'Facebook'; }
    elseif (preg_match('/YaBrowser/i', $ua)) { $browser = 'Yandex'; }
    elseif (preg_match('/Edg(e|A|iOS)?\//i', $ua)) { $browser = 'Edge'; }
    elseif (preg_match('/OPR\/|Opera/i', $ua)) { $browser = 'Opera'; }
    elseif (preg_match('/SamsungBrowser/i', $ua)) { $browser = 'Samsung'; }
    elseif (preg_match('/Firefox|FxiOS/i', $ua)) { $browser = 'Firefox'; }
    elseif (preg_match('/Chrome|CriOS|Chromium/i', $ua)) { $browser = 'Chrome'; }
    elseif (preg_match('/Safari/i', $ua)) { $browser = 'Safari'; }
    else { $browser = 'Boshqa'; }
    return ['device' => $device, 'browser' => $browser, 'os' => $os];
}

// Beacon faqat saytning o'z sahifalaridan kelishi kerak (Origin yoki Referer hosti = Host).
function analytics_same_origin(): bool {
    $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
    if ($host === '') { return false; }
    foreach (['HTTP_ORIGIN', 'HTTP_REFERER'] as $h) {
        $v = (string)($_SERVER[$h] ?? '');
        if ($v === '' || $v === 'null') { continue; }
        $p = parse_url($v);
        if (!is_array($p) || !isset($p['host'])) { return false; }
        $got = strtolower($p['host'] . (isset($p['port']) ? ':' . $p['port'] : ''));
        return $got === $host;
    }
    return false;
}

const ANALYTICS_ALLOWED_PATHS = ['/', '/index.html', '/resume.html', '/privacy.html', '/blog', '/case-studies'];

function analytics_track(array $in): void {
    date_default_timezone_set(ANALYTICS_TZ);
    $type = $in['t'] ?? '';
    $sid = $in['sid'] ?? '';
    $path = $in['p'] ?? '';
    if (!in_array($type, ['pv', 'hb', 'cv'], true)) { reject(400, 'Invalid event'); }
    if (!is_string($sid) || !preg_match('/^[a-f0-9]{32}$/D', $sid)) { reject(400, 'Invalid session'); }
    if (!is_string($path) || !in_array($path, ANALYTICS_ALLOWED_PATHS, true)) { reject(400, 'Invalid path'); }
    if ($path === '/') { $path = '/index.html'; }
    $lang = $in['l'] ?? '';
    $lang = (is_string($lang) && in_array($lang, ['uz', 'ru'], true)) ? $lang : '';
    $refHost = '';
    $ref = $in['r'] ?? '';
    if (is_string($ref) && $ref !== '' && strlen($ref) < 2000) {
        $h = parse_url($ref, PHP_URL_HOST);
        if (is_string($h) && preg_match('/^[a-z0-9.-]{1,253}$/iD', $h) &&
            strcasecmp($h, preg_replace('/:\d+$/', '', (string)($_SERVER['HTTP_HOST'] ?? ''))) !== 0) {
            $refHost = strtolower($h);
        }
    }
    $uaRaw = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
    if (analytics_is_bot($uaRaw)) { json_reply(202, ['ok' => true, 'ignored' => 'bot']); }
    $ua = analytics_clean_text($uaRaw, 300);
    $parsed = analytics_parse_ua($ua);
    $ipHash = analytics_ip_hash((string)($_SERVER['REMOTE_ADDR'] ?? ''));
    $now = time();
    $db = analytics_db();

    // Rate limit: bitta IP-hash uchun daqiqasiga 30 hodisa.
    $q = $db->prepare('SELECT COUNT(*) FROM rate_hits WHERE ip_hash = :h AND ts > :since');
    $q->execute([':h' => $ipHash, ':since' => $now - 60]);
    if ((int)$q->fetchColumn() >= 30) { reject(429, 'Too many events'); }
    $db->prepare('INSERT INTO rate_hits (ip_hash, ts) VALUES (:h, :ts)')->execute([':h' => $ipHash, ':ts' => $now]);

    // v7: konversiya hodisasi (A/B) — faqat shu sessiya ko'rgan, maqsadi mos keladigan faol eksperimentlar uchun (sessiyaga 1 marta).
    if ($type === 'cv') {
        $goal = $in['g'] ?? '';
        if (!in_array($goal, ['cta_click', 'lead'], true)) { reject(400, 'Invalid goal'); }
        $db->prepare("INSERT OR IGNORE INTO ab_conversions (exp_id, session_id, variant, ts)
                      SELECT e.exp_id, e.session_id, e.variant, :ts FROM ab_exposures e JOIN ab_experiments x ON x.id = e.exp_id
                      WHERE e.session_id = :sid AND x.status = 'running' AND x.goal = :g")
           ->execute([':ts' => $now, ':sid' => $sid, ':g' => $goal]);
        json_reply(202, ['ok' => true]);
    }
    $db->beginTransaction();
    $ab = [];
    if ($type === 'pv' && $path === '/index.html') { $ab = ab_assign($db, $sid, $now); }
    if ($type === 'pv') {
        $db->prepare('INSERT INTO visits (ts, day, path, session_id, ip_hash, user_agent, device_type, browser, os, referrer_host, lang)
                      VALUES (:ts, :day, :path, :sid, :ip, :ua, :dev, :br, :os, :ref, :lang)')
           ->execute([':ts' => $now, ':day' => date('Y-m-d', $now), ':path' => $path, ':sid' => $sid, ':ip' => $ipHash,
                      ':ua' => $ua, ':dev' => $parsed['device'], ':br' => $parsed['browser'], ':os' => $parsed['os'],
                      ':ref' => $refHost, ':lang' => $lang]);
    }
    $db->prepare('INSERT INTO live_sessions (session_id, ip_hash, first_seen, last_seen, path, device_type, browser, os, lang, hits)
                  VALUES (:sid, :ip, :now, :now, :path, :dev, :br, :os, :lang, 1)
                  ON CONFLICT(session_id) DO UPDATE SET last_seen = excluded.last_seen, path = excluded.path,
                      lang = excluded.lang, hits = live_sessions.hits + 1
                  WHERE live_sessions.ip_hash = excluded.ip_hash')
       ->execute([':sid' => $sid, ':ip' => $ipHash, ':now' => $now, ':path' => $path, ':dev' => $parsed['device'],
                  ':br' => $parsed['browser'], ':os' => $parsed['os'], ':lang' => $lang]);
    $db->commit();

    // Past ehtimollik bilan kichik tozalash (asosiy retention — cleanup_analytics.php).
    if (random_int(1, 100) === 1) {
        $db->prepare('DELETE FROM rate_hits WHERE ts < :t')->execute([':t' => $now - 300]);
        $db->prepare('DELETE FROM live_sessions WHERE last_seen < :t')->execute([':t' => $now - 86400]);
    }
    json_reply(202, $ab ? ['ok' => true, 'ab' => $ab] : ['ok' => true]);
}

/* ---------------- v7: A/B testlar ---------------- */
function ab_variant_index(string $sid, int $expId, int $n): int {
    // Deterministik va "yopishqoq": bir xil sessiya har doim bir xil variantni oladi; tasodifiy kalit tufayli oldindan bilib bo'lmaydi.
    return (int)(hexdec(substr(hash_hmac('sha256', $sid . '|' . $expId, analytics_secret()), 0, 7)) % $n);
}
function ab_assign(PDO $db, string $sid, int $now): array {
    $out = [];
    foreach ($db->query("SELECT id, slot, goal, variants FROM ab_experiments WHERE status = 'running' ORDER BY id")->fetchAll() as $x) {
        $vars = json_decode((string)$x['variants'], true);
        if (!is_array($vars) || count($vars) < 2) { continue; }
        $i = ab_variant_index($sid, (int)$x['id'], count($vars));
        $db->prepare('INSERT INTO ab_exposures (exp_id, session_id, variant, ts) VALUES (:e, :s, :v, :t)
                      ON CONFLICT(exp_id, session_id) DO UPDATE SET views = views + 1')
           ->execute([':e' => $x['id'], ':s' => $sid, ':v' => $i, ':t' => $now]);
        $v = $vars[$i];
        $out[] = ['exp' => (int)$x['id'], 'slot' => $x['slot'], 'goal' => $x['goal'], 'variant' => $i,
                  'uz' => (string)($v['uz'] ?? ''), 'ru' => (string)($v['ru'] ?? '')];
    }
    return $out;
}
function ab_list(): array {
    $db = analytics_db();
    $rows = $db->query('SELECT * FROM ab_experiments ORDER BY id DESC')->fetchAll();
    $ex = $db->prepare('SELECT variant, COUNT(*) AS sessions, SUM(views) AS views FROM ab_exposures WHERE exp_id = :id GROUP BY variant');
    $cv = $db->prepare('SELECT variant, COUNT(*) AS n FROM ab_conversions WHERE exp_id = :id GROUP BY variant');
    foreach ($rows as &$r) {
        $r['variants'] = json_decode((string)$r['variants'], true) ?: [];
        $ex->execute([':id' => $r['id']]); $e = []; foreach ($ex->fetchAll() as $x) { $e[(int)$x['variant']] = $x; }
        $cv->execute([':id' => $r['id']]); $c = []; foreach ($cv->fetchAll() as $x) { $c[(int)$x['variant']] = (int)$x['n']; }
        $stats = [];
        foreach ($r['variants'] as $i => $v) {
            $sess = (int)($e[$i]['sessions'] ?? 0); $conv = $c[$i] ?? 0;
            $stats[] = ['variant' => $i, 'label' => $v['label'] ?? chr(65 + $i), 'sessions' => $sess, 'views' => (int)($e[$i]['views'] ?? 0),
                        'conversions' => $conv, 'rate' => $sess > 0 ? round($conv / $sess * 100, 2) : 0.0];
        }
        $r['stats'] = $stats;
    }
    return ['experiments' => $rows];
}
function ab_save(array $in): array {
    $db = analytics_db();
    $name = mb_substr(trim((string)($in['name'] ?? '')), 0, 120, 'UTF-8');
    $slot = (string)($in['slot'] ?? ''); $goal = (string)($in['goal'] ?? 'lead');
    if ($name === '' || !in_array($slot, ['hero_title_accent', 'hero_title_rest', 'hero_cta'], true) || !in_array($goal, ['cta_click', 'lead'], true)) { reject(422, 'Nom, element va maqsadni tanlang'); }
    $vars = [];
    foreach (array_slice(is_array($in['variants'] ?? null) ? $in['variants'] : [], 0, 4) as $i => $v) {
        if (!is_array($v)) { continue; }
        $uz = mb_substr(trim((string)($v['uz'] ?? '')), 0, 160, 'UTF-8'); $ru = mb_substr(trim((string)($v['ru'] ?? '')), 0, 160, 'UTF-8');
        // A (nazorat) varianti bo'sh bo'lishi mumkin = saytdagi joriy matn o'zgarmaydi.
        if ($i > 0 && $uz === '') { reject(422, 'Har bir variant uchun o‘zbekcha matn kerak'); }
        $vars[] = ['label' => chr(65 + count($vars)), 'uz' => $uz, 'ru' => $ru];
    }
    if (count($vars) < 2) { reject(422, 'Kamida 2 ta variant kerak'); }
    $id = (int)($in['id'] ?? 0);
    if ($id > 0) {
        $q = $db->prepare('SELECT status FROM ab_experiments WHERE id = :id'); $q->execute([':id' => $id]);
        $st = $q->fetchColumn();
        if ($st === false) { reject(404, 'Not found'); }
        if ($st !== 'draft') { reject(409, 'Boshlangan testni tahrirlab bo‘lmaydi (natijalar buziladi) — yangisini yarating'); }
        $db->prepare('UPDATE ab_experiments SET name = :n, slot = :s, goal = :g, variants = :v WHERE id = :id')
           ->execute([':n' => $name, ':s' => $slot, ':g' => $goal, ':v' => json_encode($vars, JSON_UNESCAPED_UNICODE), ':id' => $id]);
    } else {
        $db->prepare('INSERT INTO ab_experiments (name, slot, goal, status, variants, created_at) VALUES (:n, :s, :g, \'draft\', :v, :t)')
           ->execute([':n' => $name, ':s' => $slot, ':g' => $goal, ':v' => json_encode($vars, JSON_UNESCAPED_UNICODE), ':t' => time()]);
        $id = (int)$db->lastInsertId();
    }
    audit_log('ab_save', ['id' => $id]);
    return ['ok' => true, 'id' => $id];
}
function ab_set_status(int $id, string $status): array {
    if (!in_array($status, ['running', 'stopped'], true)) { reject(400, 'Invalid status'); }
    $db = analytics_db();
    if ($status === 'running') {
        // Bir elementda bir vaqtda faqat bitta test (aks holda natijalar aralashadi).
        $q = $db->prepare("SELECT COUNT(*) FROM ab_experiments WHERE status = 'running' AND id <> :id AND slot = (SELECT slot FROM ab_experiments WHERE id = :id)");
        $q->execute([':id' => $id]);
        if ((int)$q->fetchColumn() > 0) { reject(409, 'Bu element uchun boshqa test ishlayapti — avval uni to‘xtating'); }
    }
    $st = $db->prepare('UPDATE ab_experiments SET status = :s, started_at = COALESCE(started_at, CASE WHEN :s = \'running\' THEN :t END) WHERE id = :id');
    $st->execute([':s' => $status, ':t' => time(), ':id' => $id]);
    if ($st->rowCount() === 0) { reject(404, 'Not found'); }
    audit_log('ab_' . $status, ['id' => $id]);
    return ['ok' => true];
}
function ab_delete(int $id): array {
    $db = analytics_db();
    $db->beginTransaction();
    foreach (['ab_exposures', 'ab_conversions'] as $t) { $db->prepare("DELETE FROM $t WHERE exp_id = :id")->execute([':id' => $id]); }
    $db->prepare('DELETE FROM ab_experiments WHERE id = :id')->execute([':id' => $id]);
    $db->commit();
    audit_log('ab_delete', ['id' => $id]);
    return ['ok' => true];
}

function analytics_live(): array {
    $db = analytics_db();
    $q = $db->prepare('SELECT session_id, first_seen, last_seen, path, device_type, browser, os, lang, hits
                       FROM live_sessions WHERE last_seen >= :since ORDER BY last_seen DESC LIMIT 200');
    $q->execute([':since' => time() - ANALYTICS_ONLINE_WINDOW]);
    $rows = $q->fetchAll();
    foreach ($rows as &$r) { $r['session_id'] = substr((string)$r['session_id'], 0, 8); }
    return ['online' => count($rows), 'window_seconds' => ANALYTICS_ONLINE_WINDOW, 'now' => time(), 'sessions' => $rows];
}

function analytics_summary(string $period): array {
    date_default_timezone_set(ANALYTICS_TZ);
    $days = ['today' => 1, '7d' => 7, '30d' => 30, '90d' => 90][$period] ?? null;
    if ($days === null) { reject(400, 'Invalid period'); }
    $since = strtotime('today') - ($days - 1) * 86400;
    $db = analytics_db();
    $one = function (string $sql) use ($db, $since) { $q = $db->prepare($sql); $q->execute([':since' => $since]); return $q; };
    $totals = $one('SELECT COUNT(*) AS views, COUNT(DISTINCT session_id) AS sessions, COUNT(DISTINCT ip_hash || day) AS visitors
                    FROM visits WHERE ts >= :since')->fetch();
    $group = fn(string $col, int $limit) => $one(
        // $col faqat quyidagi qat'iy oq ro'yxatdan keladi (foydalanuvchi kiritmaydi).
        'SELECT ' . $col . ' AS label, COUNT(*) AS n FROM visits WHERE ts >= :since GROUP BY ' . $col . ' ORDER BY n DESC LIMIT ' . $limit
    )->fetchAll();
    $allowed = ['device_type', 'browser', 'os', 'path', 'lang'];
    $out = ['period' => $period, 'since' => $since, 'totals' => $totals];
    foreach ($allowed as $col) { $out[$col] = $group($col, 10); }
    $q = $db->prepare("SELECT referrer_host AS label, COUNT(*) AS n FROM visits WHERE ts >= :since AND referrer_host <> ''
                       GROUP BY referrer_host ORDER BY n DESC LIMIT 10");
    $q->execute([':since' => $since]);
    $out['referrers'] = $q->fetchAll();
    // Kunlik trend: har doim oxirgi 30 kun, bo'sh kunlar 0 bilan to'ldiriladi.
    $trendSince = strtotime('today') - 29 * 86400;
    $q = $db->prepare('SELECT day, COUNT(*) AS views, COUNT(DISTINCT session_id) AS sessions FROM visits WHERE ts >= :since GROUP BY day');
    $q->execute([':since' => $trendSince]);
    $byDay = [];
    foreach ($q->fetchAll() as $r) { $byDay[$r['day']] = $r; }
    $trend = [];
    for ($i = 0; $i < 30; $i++) {
        $d = date('Y-m-d', $trendSince + $i * 86400 + 3600);
        $trend[] = ['day' => $d, 'views' => (int)($byDay[$d]['views'] ?? 0), 'sessions' => (int)($byDay[$d]['sessions'] ?? 0)];
    }
    $out['daily'] = $trend;
    $q = $db->query('SELECT COUNT(*) FROM visits');
    $out['all_time_views'] = (int)$q->fetchColumn();
    return $out;
}

function analytics_cleanup(int $retentionDays = ANALYTICS_RETENTION_DAYS): array {
    $db = analytics_db();
    $cut = time() - $retentionDays * 86400;
    $a = $db->prepare('DELETE FROM visits WHERE ts < :t'); $a->execute([':t' => $cut]);
    $b = $db->prepare('DELETE FROM live_sessions WHERE last_seen < :t'); $b->execute([':t' => time() - 86400]);
    $c = $db->prepare('DELETE FROM rate_hits WHERE ts < :t'); $c->execute([':t' => time() - 300]);
    return ['visits' => $a->rowCount(), 'live_sessions' => $b->rowCount(), 'rate_hits' => $c->rowCount()];
}
