<?php
declare(strict_types=1);
/**
 * DARIKO v7 — ichki holat tekshiruvi (self-check) va ogohlantirish. Faqat CLI (cron) orqali:
 *   php storage/healthcheck.php                         -> tekshiradi; muammo bo'lsa Telegram/email ogohlantirish
 *   php storage/healthcheck.php --url=https://dariko.uz/ -> qo'shimcha: saytning o'z URL'i 200 qaytaradimi
 *   php storage/healthcheck.php --dry-run               -> faqat natijani chiqaradi, ogohlantirish yubormaydi
 *   php storage/healthcheck.php --test-alert            -> ogohlantirish kanalini sinash uchun test xabar yuboradi
 * Cron misoli (har 10 daqiqada):  0,10,20,30,40,50 * * * * php /yo'l/storage/healthcheck.php --url=https://dariko.uz/
 *
 * MUHIM CHEKLOV: bu — server ICHIDAN tekshiruv. Server butunlay o'chsa yoki internetdan uzilsa, skript ham
 * ishlamaydi va xabar yubora olmaydi. To'liq tashqi monitoring uchun UptimeRobot kabi bepul tashqi xizmatni
 * qo'shimcha ulang (ARXITEKTURA_UZ.md, 9-bo'lim).
 * Spamga qarshi: holat o'zgarganda (OK->FAIL, FAIL->OK) darhol, muammo davom etsa har 6 soatda eslatma.
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("Faqat CLI.\n"); }
define('DARIKO_ENTRY', true);
require __DIR__ . '/../public_html/common.php';
require __DIR__ . '/../public_html/crm_lib.php';

$args = $argv ?? [];
$dry = in_array('--dry-run', $args, true);
$url = null;
foreach ($args as $a) { if (str_starts_with($a, '--url=')) { $url = substr($a, 6); } }
$minFreeMb = 200;

$checks = [];
$add = function (string $name, bool $ok, string $detail = '') use (&$checks) { $checks[] = ['name' => $name, 'ok' => $ok, 'detail' => $detail]; };

// 1) storage/ va uploads/ yozish huquqi
$add('storage yoziladi', is_dir(DATA_DIR) && is_writable(DATA_DIR));
$uploads = __DIR__ . '/../public_html/uploads';
$add('uploads yoziladi', is_dir($uploads) && is_writable($uploads));
// 2) Maxfiy fayllar ruxsati (0600)
foreach (['auth.json', 'dariko.sqlite', 'analytics.sqlite'] as $f) {
    if (is_file(DATA_DIR . "/$f")) {
        $mode = substr(sprintf('%o', fileperms(DATA_DIR . "/$f")), -3);
        $add("$f ruxsati", in_array($mode, ['600', '640'], true), $mode);
    }
}
// 3) SQLite bazalari: ochiladi, yoziladi, integrity
if (extension_loaded('pdo_sqlite')) {
    foreach (['dariko.sqlite', 'analytics.sqlite'] as $f) {
        $path = DATA_DIR . "/$f";
        if (!is_file($path)) { $add("$f", true, 'hali yaratilmagan'); continue; }
        try {
            $db = new PDO('sqlite:' . $path, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $db->exec('PRAGMA busy_timeout = 3000');
            $ic = (string)$db->query('PRAGMA quick_check')->fetchColumn();
            $db->exec('CREATE TABLE IF NOT EXISTS _health (ts INTEGER)'); $db->exec('DELETE FROM _health');
            $db->prepare('INSERT INTO _health (ts) VALUES (:t)')->execute([':t' => time()]);
            $add("$f yoziladi", $ic === 'ok', $ic);
        } catch (Throwable $e) { $add("$f yoziladi", false, $e->getMessage()); }
    }
} else { $add('pdo_sqlite', false, 'kengaytma yo‘q'); }
// 4) Disk joyi
$free = @disk_free_space(DATA_DIR);
$add('bo‘sh disk joyi', $free === false || $free > $minFreeMb * 1048576, $free === false ? 'noma’lum' : round($free / 1048576) . ' MB');
// 5) Ixtiyoriy: saytning o'z URL'i
if ($url !== null) {
    if (!preg_match('#^https?://[^\s]+$#', $url)) { fwrite(STDERR, "--url noto'g'ri\n"); exit(2); }
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_NOBODY => false, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 3, CURLOPT_USERAGENT => 'DARIKO-healthcheck/1.0 (monitor)']);
    $body = curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); $err = curl_error($ch); curl_close($ch);
    $add('sayt javobi (' . $url . ')', $code === 200 && is_string($body) && str_contains($body, 'DARIKO'), $code ? "HTTP $code" : $err);
    $api = rtrim(preg_replace('#/[^/]*$#', '/', $url), '/') . '/api.php?route=public';
    $ch = curl_init($api);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_USERAGENT => 'DARIKO-healthcheck/1.0 (monitor)']);
    $b2 = curl_exec($ch); $c2 = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    $add('API javobi', $c2 === 200 && is_array(json_decode((string)$b2, true)), "HTTP $c2");
}

$failed = array_values(array_filter($checks, fn($c) => !$c['ok']));
foreach ($checks as $c) { printf("[%s] %s %s\n", $c['ok'] ? ' OK ' : 'FAIL', $c['name'], $c['detail'] !== '' ? '(' . $c['detail'] . ')' : ''); }

function health_alert(string $text): array {
    $sent = [];
    try {
        $chat = setting_get('tg_admin_chat_id');
        if ($chat !== '' && setting_get('tg_bot_token') !== '') { $r = tg_send($chat, $text); $sent[] = 'telegram:' . ($r['ok'] ? 'ok' : $r['error']); }
        $to = setting_get('notify_email');
        if ($to !== '') { $r = mail_send($to, 'DARIKO — server holati', $text); $sent[] = 'email:' . ($r['ok'] ? 'ok' : $r['error']); }
    } catch (Throwable $e) { $sent[] = 'error:' . $e->getMessage(); }
    if (!$sent) { $sent[] = 'kanal sozlanmagan (Admin → Kanallar: Telegram admin chat yoki xabarnoma emaili)'; }
    return $sent;
}

if (in_array('--test-alert', $args, true)) {
    echo 'Test ogohlantirish: ' . implode(', ', health_alert("🧪 DARIKO healthcheck: test ogohlantirish.")) . "\n";
    exit(0);
}

$stateFile = DATA_DIR . '/health_state.json';
$prev = json_decode((string)@file_get_contents($stateFile), true) ?: ['ok' => true, 'last_alert' => 0];
$ok = !$failed; $now = time();
$needAlert = (!$ok && ($prev['ok'] ?? true)) || (!$ok && $now - (int)($prev['last_alert'] ?? 0) > 21600) || ($ok && !($prev['ok'] ?? true));
$state = ['ok' => $ok, 'checked_at' => $now, 'failed' => array_map(fn($c) => $c['name'], $failed), 'last_alert' => (int)($prev['last_alert'] ?? 0)];
if ($needAlert) {
    $host = gethostname() ?: 'server';
    $text = $ok ? "✅ DARIKO ($host): barcha tekshiruvlar yana OK."
                : "⚠️ DARIKO ($host): muammo aniqlandi\n" . implode("\n", array_map(fn($c) => '• ' . $c['name'] . ($c['detail'] !== '' ? ' — ' . $c['detail'] : ''), $failed));
    if ($dry) { echo "[dry-run] Ogohlantirish yuborilardi:\n$text\n"; }
    else { echo 'Ogohlantirish: ' . implode(', ', health_alert($text)) . "\n"; $state['last_alert'] = $now; }
}
if (!$dry) { @file_put_contents($stateFile, json_encode($state), LOCK_EX); @chmod($stateFile, 0600); }
exit($ok ? 0 : 1);
