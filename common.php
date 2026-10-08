<?php
declare(strict_types=1);
if (!defined('DARIKO_ENTRY')) { http_response_code(404); exit; }
const DATA_DIR = __DIR__ . '/../storage';
const CONTENT_FILE = DATA_DIR . '/content.json';
const INITIAL_FILE = DATA_DIR . '/initial_content.json';
const AUTH_FILE = DATA_DIR . '/auth.json';

function json_reply(int $status, array $body): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}
// v13 (bonus): yagona PHP HTML-escape (site_lib.php va ru.php avval har biri o'zinikini ishlatardi).
function esc(mixed $s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function reject(int $status, string $message): never { json_reply($status, ['error' => $message]); }
function secure_transport(): bool {
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
        // Faqat lokal test: PHP built-in server (php -S) + loopback manzil. Real hostingda (Apache/LiteSpeed)
        // PHP_SAPI hech qachon 'cli-server' bo'lmaydi, shuning uchun proxy orqali aldab bo'lmaydi.
        (PHP_SAPI === 'cli-server' && in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true));
}
function session_open(): void {
    if (!secure_transport()) { reject(403, 'HTTPS required'); }
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('dariko_session');
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', 'httponly' => true, 'samesite' => 'Strict']);
    ini_set('session.cookie_httponly', '1');
    session_start();
    $now = time();
    // Mutlaq muddat (8 soat) + harakatsizlik muddati (60 daqiqa).
    if ((isset($_SESSION['expires']) && (int)$_SESSION['expires'] < $now) ||
        (!empty($_SESSION['authenticated']) && isset($_SESSION['last_active']) && (int)$_SESSION['last_active'] < $now - 3600)) {
        $_SESSION = []; session_regenerate_id(true);
    }
    if (!empty($_SESSION['authenticated'])) { $_SESSION['last_active'] = $now; }
}
// Admin GET marshrutlari uchun ham: login + CSRF sarlavhasi talab qilinadi (brauzer tashqi saytdan
// maxsus sarlavhali so'rov yubora olmaydi, shuning uchun ma'lumot o'qish ham himoyalangan).
function require_admin(): void { csrf_check(); }

// Audit jurnali: storage/audit.log (JSON Lines). IP xom holda yozilmaydi.
function audit_log(string $event, array $detail = []): void {
    $file = DATA_DIR.'/audit.log';
    if (is_file($file) && filesize($file) > 1000000) { @rename($file, $file.'.1'); }
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    $line = json_encode(['ts' => time(), 'event' => $event, 'ip' => substr(hash('sha256', 'audit|'.$ip), 0, 12),
        'ua' => mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 160, 'UTF-8'), 'detail' => $detail],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    @file_put_contents($file, $line."\n", FILE_APPEND | LOCK_EX);
    @chmod($file, 0600);
}
function audit_read(int $limit = 200): array {
    $out = [];
    foreach (['/audit.log.1', '/audit.log'] as $f) {
        if (!is_file(DATA_DIR.$f)) { continue; }
        foreach (file(DATA_DIR.$f, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $l) {
            $r = json_decode($l, true); if (is_array($r)) { $out[] = $r; }
        }
    }
    return array_slice(array_reverse($out), 0, $limit);
}
function send_api_security_headers(): void {
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-Content-Type-Options: nosniff');
    header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'; base-uri 'none'");
    header('Cross-Origin-Resource-Policy: same-origin');
    header('X-Robots-Tag: noindex, nofollow');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=()');
}
function csrf_check(): void {
    if (empty($_SESSION['authenticated'])) { reject(401, 'Login required'); }
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin !== '') {
        $site = parse_url($origin);
        if (($site['host'] ?? '') !== ($_SERVER['HTTP_HOST'] ?? '') &&
            ($site['host'] ?? '') . (isset($site['port']) ? ':'.$site['port'] : '') !== ($_SERVER['HTTP_HOST'] ?? '')) { reject(403, 'Origin mismatch'); }
    }
    $actual = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!is_string($actual) || !hash_equals($_SESSION['csrf'] ?? '', $actual)) { reject(403, 'CSRF token required'); }
}
function body(int $limit = 2000000): array {
    $length = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
    if ($length < 1 || $length > $limit) { reject(413, 'Invalid request size'); }
    $raw = file_get_contents('php://input');
    if ($raw === false || strlen($raw) !== $length) { reject(400, 'Incomplete request body'); }
    $data = json_decode($raw, true);
    if (!is_array($data) || json_last_error() !== JSON_ERROR_NONE) { reject(400, 'Invalid JSON'); }
    return $data;
}
function content_read(): array {
    $file = is_file(CONTENT_FILE) ? CONTENT_FILE : INITIAL_FILE;
    $data = json_decode((string)file_get_contents($file), true);
    if (!is_array($data)) { reject(500, 'Content unavailable'); }
    // v4: eski content.json fayllarida yangi bloklar bo'lmasligi mumkin — standart qiymatlar bilan to'ldiriladi.
    $data['assets'] = is_array($data['assets'] ?? null) ? $data['assets'] : [];
    $flags = is_array($data['flags'] ?? null) ? $data['flags'] : [];
    foreach (FLAG_KEYS as $k) { $flags[$k] = (bool)($flags[$k] ?? false); }
    $data['flags'] = array_intersect_key($flags, array_flip(FLAG_KEYS));
    // v9: «Shaxsiy sahifa» tashqi havolasi — eski content.json da kalit bo'lmasa bo'sh qiymat bilan qo'shiladi.
    $data['settings'] = is_array($data['settings'] ?? null) ? $data['settings'] : [];
    $data['settings']['personal_page_url'] = is_string($data['settings']['personal_page_url'] ?? null) ? $data['settings']['personal_page_url'] : '';
    return $data;
}
const FLAG_KEYS = ['hero_photo_visible', 'resume_visible_1', 'resume_visible_2', 'resume_visible_3', 'resume_visible_4', 'hero_resume_visible', 'personal_page_visible'];
const IMAGE_SLOTS = ['logo', 'favicon', 'hero', 'expert-1', 'expert-2', 'expert-3', 'expert-4'];
const PDF_SLOTS = ['resume-1', 'resume-2', 'resume-3', 'resume-4', 'hero-resume'];
/**
 * v9: tashqi havola tekshiruvi (masalan «Shaxsiy sahifa»). Faqat mutlaq http/https URL, 2048 belgigacha,
 * bo'sh joy/boshqaruv belgilarisiz, login:parol@ qismisiz. javascript:/data:/vbscript:/file: va h.k. rad etiladi.
 */
function external_url_valid(string $url): bool {
    if ($url === '' || strlen($url) > 2048 || preg_match('/[\x00-\x20\x7f<>"\'`\\\\]/', $url)) { return false; }
    if (!preg_match('#^https?://#iD', $url) || filter_var($url, FILTER_VALIDATE_URL) === false) { return false; }
    $p = parse_url($url);
    if (!is_array($p) || !in_array(strtolower($p['scheme'] ?? ''), ['http', 'https'], true)) { return false; }
    if (($p['host'] ?? '') === '' || isset($p['user']) || isset($p['pass'])) { return false; }
    return true;
}
function raw_body(int $limit, string $what): string {
    $length = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
    if ($length < 1 || $length > $limit) { reject(413, $what.' must be under '.intdiv($limit, 1000000).' MB'); }
    $raw = file_get_contents('php://input');
    if (!is_string($raw) || strlen($raw) !== $length) { reject(400, 'Invalid '.$what.' body'); }
    return $raw;
}
/**
 * PDF tekshiruvi: mijoz yuborgan MIME/kengaytmaga ishonilmaydi — faqat baytlar tekshiriladi.
 * - boshida (birinchi 1024 bayt ichida) "%PDF-1.x"/"%PDF-2.x" sarlavhasi; oxirida "%%EOF" belgisi;
 * - faol kontent (JavaScript, fayl ishga tushirish, ichki fayllar, XFA/formalar yuborish, RichMedia) rad etiladi.
 * v13 (audit 1-masala): tekshiruv endi quyidagilarni ham qamraydi:
 *   (a) FlateDecode bilan siqilgan oqimlar (xususan /ObjStm obyekt oqimlari) — ochiladi va xuddi shu qidiruvdan o'tadi;
 *   (b) PDF nomlaridagi #XX hex-escape (/J#61vaScript == /JavaScript) — qidiruvdan oldin normallashtiriladi;
 *   (c) avtomatik harakatlar: /OpenAction harakat lug'ati (<< /S ... >>) va bo'sh bo'lmagan /AA rad etiladi.
 *       /OpenAction [ sahifa /XYZ ... ] (oddiy "ochilganda shu sahifa" manzili, Word/LibreOffice qo'yadi) ruxsat etiladi.
 * Bu naqshga asoslangan aniqlash, to'liq sanitizatsiya EMAS (ARXITEKTURA_UZ.md, 11-bo'lim — qoldiq xavf).
 */
const PDF_DANGEROUS_RE = '/\/(JavaScript|JS|Launch|EmbeddedFiles?|RichMedia|XFA|SubmitForm|ImportData|GoToE)\b/';
const PDF_INFLATE_LIMIT = 50000000; // barcha ochilgan oqimlar uchun jami bayt chegarasi (zip-bomb himoyasi)
/** PDF nom tokenlaridagi #XX ketma-ketliklarini ochadi: /J#61vaScript -> /JavaScript. */
function pdf_normalize_names(string $s): string {
    if (!str_contains($s, '#')) { return $s; }
    return (string)preg_replace_callback('~/[^\s/\[\]<>(){}%]+~', static fn(array $m): string =>
        str_contains($m[0], '#') ? (string)preg_replace_callback('/#([0-9A-Fa-f]{2})/', static fn(array $h): string => chr((int)hexdec($h[1])), $m[0]) : $m[0], $s);
}
/** zlib oqimini $limit baytgacha ochadi. Buzuq oqim -> null; chegaradan oshsa -> false. */
function pdf_inflate(string $data, int $limit): string|false|null {
    // zlib yo'q bo'lsa siqilgan PDF'ni tekshirib bo'lmaydi -> xavfsiz tomonga (rad), jim o'tkazib yuborilmaydi.
    if (!function_exists('inflate_init')) { reject(503, 'PHP zlib extension required to verify compressed PDF'); }
    $ctx = @inflate_init(ZLIB_ENCODING_DEFLATE);
    if ($ctx === false) { return null; }
    $out = '';
    foreach (str_split($data, 65536) as $chunk) {
        $part = @inflate_add($ctx, $chunk, ZLIB_SYNC_FLUSH);
        if ($part === false) { return $out === '' ? null : $out; } // oxiri buzuq: ochilgan qismini baribir tekshiramiz
        $out .= $part;
        if (strlen($out) > $limit) { return false; }
        if (inflate_get_status($ctx) === ZLIB_STREAM_END) { break; }
    }
    return $out === '' ? null : $out;
}
/** Tekshiriladigan matnlar: xom baytlar + ochilgan FlateDecode oqimlari. Obyektlar xaritasi: raqam => tana. */
function pdf_collect(string $raw): array {
    $texts = [$raw]; $objects = []; $budget = PDF_INFLATE_LIMIT;
    if (preg_match_all('/(?<![0-9])(\d{1,10})\s+\d{1,5}\s+obj\b(.*?)\bendobj\b/s', $raw, $mm, PREG_SET_ORDER)) {
        foreach ($mm as $m) { $objects[(int)$m[1]] = $m[2]; }
    }
    $off = 0;
    while (($p = strpos($raw, 'stream', $off)) !== false) {
        $off = $p + 6;
        if ($p >= 3 && substr($raw, $p - 3, 3) === 'end') { continue; }
        $dictStart = strrpos(substr($raw, max(0, $p - 4096), min($p, 4096)), 'obj');
        $dict = pdf_normalize_names($dictStart === false ? substr($raw, max(0, $p - 512), min($p, 512)) : substr($raw, max(0, $p - 4096) + $dictStart, min($p, 4096) - $dictStart));
        $dataStart = $p + 6;
        if (($raw[$dataStart] ?? '') === "\r") { $dataStart++; }
        if (($raw[$dataStart] ?? '') === "\n") { $dataStart++; }
        $end = strpos($raw, 'endstream', $dataStart);
        if ($end === false) { break; }
        $off = $end + 9;
        if (!str_contains($dict, '/FlateDecode') && !str_contains($dict, '/Fl ') && !str_contains($dict, '/Fl]')) { continue; }
        $dec = pdf_inflate(substr($raw, $dataStart, $end - $dataStart), $budget);
        if ($dec === false) { reject(400, 'PDF is too complex to verify (compressed data too large)'); }
        if ($dec === null) { continue; } // ochilmadi (rasm, boshqa filtr zanjiri, buzuq) — o'tkazib yuboriladi
        $budget -= strlen($dec);
        $texts[] = $dec;
        // /ObjStm: sarlavha "raqam ofset raqam ofset ..." + /First dan boshlab obyekt tanalari.
        if (str_contains($dict, '/ObjStm') && preg_match('~/First\s+(\d+)~', $dict, $fm)) {
            $first = (int)$fm[1];
            preg_match_all('/\d+/', substr($dec, 0, $first), $nums);
            $pairs = array_chunk(array_map('intval', $nums[0]), 2);
            foreach ($pairs as $i => $pair) {
                if (count($pair) !== 2) { break; }
                $next = isset($pairs[$i + 1][1]) ? $pairs[$i + 1][1] : strlen($dec) - $first;
                $objects[$pair[0]] = substr($dec, $first + $pair[1], max(0, $next - $pair[1]));
            }
        }
    }
    return [array_map('pdf_normalize_names', $texts), array_map('pdf_normalize_names', $objects)];
}
/** "<<" dan boshlab mos ">>" gacha bo'lgan lug'at ichini qaytaradi (ichma-ich lug'atlar hisobga olinadi). */
function pdf_dict_body(string $s, int $at): string {
    $depth = 0; $n = strlen($s);
    for ($i = $at; $i < $n - 1; $i++) {
        if ($s[$i] === '<' && $s[$i + 1] === '<') { $depth++; $i++; continue; }
        if ($s[$i] === '>' && $s[$i + 1] === '>') { $depth--; $i++; if ($depth === 0) { return substr($s, $at + 2, $i - 1 - $at - 2); } }
    }
    return substr($s, $at + 2);
}
function pdf_validate(string $raw): void {
    $head = substr($raw, 0, 1024);
    $pos = strpos($head, '%PDF-');
    if ($pos === false || !preg_match('/^%PDF-[12]\.[0-9]/', substr($raw, $pos, 8))) { reject(400, 'Not a PDF file'); }
    if (!str_contains(substr($raw, -2048), '%%EOF')) { reject(400, 'Incomplete PDF file'); }
    if (preg_match('/<\?php|<script/i', $raw)) { reject(400, 'PDF contains suspicious content'); }
    [$texts, $objects] = pdf_collect($raw);
    $active = 'PDF contains active content (JavaScript/actions/attachments); export a plain PDF';
    foreach ($texts as $t) {
        if (preg_match(PDF_DANGEROUS_RE, $t)) { reject(400, $active); }
        if (preg_match('/<\?php|<script/i', $t)) { reject(400, 'PDF contains suspicious content'); }
        // /AA (qo'shimcha harakatlar: sahifa ochilganda/yopilganda, maydon fokusida ...) — rezyumeda kerak emas.
        if (preg_match_all('~/AA\s*(<<|\d+\s+\d+\s+R)~', $t, $aa, PREG_OFFSET_CAPTURE)) {
            foreach ($aa[1] as [$tok, $o]) {
                if ($tok !== '<<' || trim(pdf_dict_body($t, $o)) !== '') { reject(400, $active.' (/AA auto-action)'); }
            }
        }
        // /OpenAction: [manzil massivi] ruxsat; harakat lug'ati yoki unga havola bo'lsa rad etiladi.
        if (preg_match_all('~/OpenAction\s*(<<|\[|(\d+)\s+\d+\s+R)~', $t, $oa, PREG_SET_ORDER)) {
            foreach ($oa as $m) {
                if ($m[1] === '[') { continue; }
                if ($m[1] === '<<') { reject(400, $active.' (/OpenAction)'); }
                $target = ltrim($objects[(int)$m[2]] ?? '<<'); // topilmasa — xavfsiz tomonga (rad)
                if (!str_starts_with($target, '[')) { reject(400, $active.' (/OpenAction)'); }
            }
        }
    }
}
function locked(callable $callback): never {
    $lock = fopen(DATA_DIR.'/content.lock', 'c');
    if (!$lock) { reject(500, 'Storage unavailable'); }
    // reject()/json_reply() call exit(), which does NOT run try/finally blocks in PHP,
    // so a shutdown function is the only reliable way to guarantee the lock is released
    // even when a callback (or content_read()) exits mid-critical-section.
    $released = false;
    register_shutdown_function(function () use ($lock, &$released) {
        if (!$released) { @flock($lock, LOCK_UN); @fclose($lock); $released = true; }
    });
    if (!flock($lock, LOCK_EX)) { reject(500, 'Storage unavailable'); }
    $data = content_read();
    $result = $callback($data);
    if (!is_array($result)) { reject(500, 'Invalid update'); }
    $data['revision'] = (int)$data['revision'] + 1;
    $encoded = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $tmp = DATA_DIR.'/content-'.bin2hex(random_bytes(8)).'.tmp';
    if (file_put_contents($tmp, $encoded, LOCK_EX) === false || !rename($tmp, CONTENT_FILE)) {
        @unlink($tmp); reject(500, 'Could not save content');
    }
    flock($lock, LOCK_UN); fclose($lock); $released = true;
    json_reply(200, ['ok' => true, 'revision' => $data['revision']] + $result);
}

// Opportunistic cleanup of stale storage/rate-*.json files (item: no cleanup for rate limit files).
// Called with low probability from rate_limit_check() so it doesn't add cost to every request.
function cleanup_rate_limit_files(int $maxAgeSeconds = 3600): void {
    $files = glob(DATA_DIR.'/rate-*.json');
    if (!$files) { return; }
    $now = time();
    foreach ($files as $f) {
        $mtime = @filemtime($f);
        if ($mtime !== false && ($now - $mtime) > $maxAgeSeconds) { @unlink($f); }
    }
}
// SSRF hardening for admin/translate: DARIKO_TRANSLATE_URL is a trusted env var, but this
// resolves and rejects private/loopback/link-local/metadata targets defensively (admin typo,
// or a compromised env value) before curl ever touches the resolved host.
// v13 (audit 9-masala): DNS-rebinding TOCTOU yopildi — xost BIR MARTA resolve qilinadi, IP'lar tekshiriladi va
// curl'ga CURLOPT_RESOLVE ("host:port:ip") orqali aynan shu IP'lar beriladi. curl DNS'ni qayta so'ramaydi;
// TLS SNI va sertifikat tekshiruvi asl xost nomi bo'yicha qoladi (URL o'zgarmaydi).
// Qaytaradi: CURLOPT_RESOLVE qatorlari ([] = IP literal, qadash kerak emas) yoki null (xavfli/resolve bo'lmadi).
function translate_resolve_pinned(string $url): ?array {
    $p = parse_url($url);
    $host = is_array($p) ? ($p['host'] ?? '') : '';
    if (!is_string($host) || $host === '' || strtolower((string)($p['scheme'] ?? '')) !== 'https') { return null; }
    $port = (int)($p['port'] ?? 443);
    $bare = trim($host, '[]');
    $ips = [];
    if (filter_var($bare, FILTER_VALIDATE_IP)) {
        // IP literal: DNS yo'q -> rebinding ham yo'q; faqat diapazon tekshiriladi, qadash shart emas ([]).
        return filter_var($bare, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) ? [] : null;
    } else {
        $ipv4 = gethostbynamel($bare);
        if (is_array($ipv4)) { $ips = array_merge($ips, $ipv4); }
        if (function_exists('dns_get_record')) {
            foreach (@dns_get_record($bare, DNS_AAAA) ?: [] as $rec) { if (!empty($rec['ipv6'])) { $ips[] = $rec['ipv6']; } }
        }
    }
    if (!$ips) { return null; }
    foreach ($ips as $ip) {
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) { return null; }
    }
    $list = implode(',', array_map(static fn(string $ip): string => str_contains($ip, ':') ? "[$ip]" : $ip, array_unique($ips)));
    return ["$bare:$port:$list"];
}
function translate_host_is_safe(string $url): bool { return translate_resolve_pinned($url) !== null; }
function image_body(): string { return raw_body(5000000, 'Image'); }

// v7: umumiy rasm qayta ishlash (admin/upload va admin/media/upload ikkalasi ham shu funksiyadan foydalanadi).
// Baytlar tekshiriladi, GD'da qayta kodlanadi (PNG), eng katta tomoni $maxSide px gacha kichraytiriladi.
function image_process_upload(int $maxSide = 2000): string {
    if (!extension_loaded('gd')) { reject(503, 'GD image extension required'); }
    $raw = image_body();
    $info = @getimagesizefromstring($raw);
    if (!is_array($info) || !in_array($info[2], [IMAGETYPE_PNG,IMAGETYPE_JPEG,IMAGETYPE_WEBP], true) ||
        $info[0] > 4000 || $info[1] > 4000 || $info[0] * $info[1] > 12000000) { reject(400, 'Invalid image'); }
    $source = @imagecreatefromstring($raw);
    if ($source === false) { reject(400, 'Image cannot be decoded'); }
    $scale = min(1, $maxSide / imagesx($source), $maxSide / imagesy($source));
    $w = max(1, (int)round(imagesx($source) * $scale));
    $h = max(1, (int)round(imagesy($source) * $scale));
    $target = imagecreatetruecolor($w, $h);
    imagealphablending($target, false); imagesavealpha($target, true);
    $transparent = imagecolorallocatealpha($target, 0, 0, 0, 127);
    imagefill($target, 0, 0, $transparent);
    imagecopyresampled($target, $source, 0, 0, 0, 0, $w, $h, imagesx($source), imagesy($source));
    ob_start(); $converted = imagepng($target, null, 6); $image = (string)ob_get_clean();
    imagedestroy($source); imagedestroy($target);
    if (!$converted || $image === '') { reject(400, 'Image conversion failed'); }
    if (strlen($image) > 5000000) { reject(413, 'Processed image too large'); }
    return $image;
}

// v7: ommaviy formalar (ariza, bron, obuna) uchun umumiy rate-limit — login bilan bir xil fayl+flock usuli.
// $bucket nomi + IP xeshlanadi; oynada $max dan ko'p urinish bo'lsa false qaytaradi.
// v12 (B2): storage (rate-*.json) ochilmasa/qulflanmasa endi JIM o'tkazib yuborilmaydi:
//  - har doim audit.log ('rate_limit_storage_error') + PHP error_log ga yoziladi (audit.log ham yozilmasa);
//  - $failClosed=true (standart; login, ariza, bron, newsletter) -> 503 «birozdan keyin urinib ko'ring»;
//  - $failClosed=false -> eski xulq (so'rov o'tkaziladi) — faqat kam xavfli marshrutlar uchun.
// ARXITEKTURA_UZ.md, 10-bo'limga qarang.
function rate_limit_storage_failed(string $bucket, bool $failClosed): void {
    audit_log('rate_limit_storage_error', ['bucket' => $bucket, 'fail_closed' => $failClosed]);
    error_log('DARIKO: rate-limit storage unavailable (bucket='.$bucket.', fail_closed='.($failClosed ? '1' : '0').') — check storage/ permissions/disk');
    if ($failClosed) { header('Retry-After: 30'); reject(503, 'Service temporarily unavailable; try again shortly'); }
}
// v12 (C1): fayl+flock oyna primitivlari — rate_limit_hit() va login (api.php) ikkalasi ham shulardan foydalanadi.
// rate_file_open() qulflangan handle qaytaradi; ocholmasa rate_limit_storage_failed() (jurnal + fail-closed 503).
function rate_file_open(string $file, string $bucket, bool $failClosed = true) {
    $h = @fopen($file, 'c+');
    if (!$h || !flock($h, LOCK_EX)) { if ($h) { fclose($h); } rate_limit_storage_failed($bucket, $failClosed); return null; }
    return $h;
}
/** Oynadagi (oxirgi $windowSeconds) vaqt belgilarini o'qiydi. */
function rate_file_read($h, int $windowSeconds): array {
    $hits = json_decode((string)stream_get_contents($h), true);
    $now = time();
    return array_values(array_filter(is_array($hits) ? $hits : [], static fn($t) => is_int($t) && $t > $now - $windowSeconds));
}
/** Yozadi, qulfni bo'shatadi va yopadi. $hits === null -> yozmasdan yopadi. */
function rate_file_close($h, ?array $hits): void {
    if ($hits !== null) { rewind($h); ftruncate($h, 0); fwrite($h, json_encode($hits)); fflush($h); }
    flock($h, LOCK_UN); fclose($h);
}
// Login chegaralari (api.php). v13: global 30 -> 50 (audit 4-masala; ARXITEKTURA_UZ.md, 11-bo'lim).
const LOGIN_WINDOW = 900;
const LOGIN_IP_MAX = 8;
const LOGIN_GLOBAL_MAX = 50;
function rate_limit_hit(string $bucket, int $max, int $windowSeconds, bool $failClosed = true): bool {
    if (random_int(1, 50) === 1) { cleanup_rate_limit_files(max(3600, $windowSeconds)); }
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $h = rate_file_open(DATA_DIR.'/rate-'.hash('sha256', $bucket.'|'.$ip).'.json', $bucket, $failClosed);
    if ($h === null) { return true; } // faqat $failClosed=false bo'lsa shu yerga keladi
    $hits = rate_file_read($h, $windowSeconds);
    $ok = count($hits) < $max;
    if ($ok) { $hits[] = time(); }
    rate_file_close($h, $hits);
    return $ok;
}

// v12 (C1): analytics.sqlite va dariko.sqlite uchun yagona PDO ulanish sozlamasi.
// busy_timeout: avval analitika 3000 ms, CRM 5000 ms edi — farq uchun hujjatlashtirilgan sabab yo'q edi;
// beacon navigator.sendBeacon orqali (sahifani kutdirmaydi), shuning uchun ikkalasi uchun ham 5000 ms.
const SQLITE_BUSY_TIMEOUT_MS = 5000;
function sqlite_open(string $path): PDO {
    if (!extension_loaded('pdo_sqlite')) { reject(503, 'pdo_sqlite PHP extension required'); }
    $fresh = !is_file($path);
    $pdo = new PDO('sqlite:' . $path, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $pdo->exec('PRAGMA busy_timeout = ' . SQLITE_BUSY_TIMEOUT_MS);
    $pdo->exec('PRAGMA journal_mode = WAL');
    $pdo->exec('PRAGMA foreign_keys = ON');
    if ($fresh) { @chmod($path, 0600); }
    return $pdo;
}

// v7: maxfiy qiymatni brauzerga faqat niqoblangan holda qaytarish (oxirgi 4 belgi).
function mask_secret(string $v): string {
    if ($v === '') { return ''; }
    return str_repeat('•', 8).(mb_strlen($v, 'UTF-8') > 8 ? mb_substr($v, -4, null, 'UTF-8') : '');
}
// v13 (audit 2-masala + bonus): sayt bazaviy URL uchun YAGONA manba (site_base_url, seo_base, ru.php — hammasi shu).
// HTTP Host sarlavhasiga HECH QACHON ishonilmaydi (Host-header poisoning: tasdiqlash xatlari, webhook manzillari).
// Manba: DARIKO_SITE_URL env (https://domen yoki https://domen:port), bo'lmasa — kanonik prodakshn domeni.
// Sozlanmaganligi admin panelida (Kanallar) ogohlantirish sifatida ko'rsatiladi.
const SITE_URL_DEFAULT = 'https://dariko.uz';
function site_url_configured(): ?string {
    $env = getenv('DARIKO_SITE_URL');
    if (!is_string($env)) { return null; }
    $env = rtrim(trim($env), '/');
    return preg_match('#^https://[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)*(:\d{1,5})?$#iD', $env) ? strtolower($env) : null;
}
function site_base_url(): string { return site_url_configured() ?? SITE_URL_DEFAULT; }
