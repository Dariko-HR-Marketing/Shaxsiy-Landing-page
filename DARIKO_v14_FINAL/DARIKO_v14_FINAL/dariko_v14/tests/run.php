<?php
declare(strict_types=1);
/**
 * DARIKO v13 — avtomatik testlar yurgizuvchisi (PHPUnit'siz, "build step yo'q" falsafasiga mos).
 * Ishga tushirish (paket ildizidan):   php tests/run.php            (hammasi)
 *                                      php tests/run.php 08 13       (faqat nomi shu bilan boshlanganlar)
 * Nima qiladi: paketni vaqtinchalik papkaga nusxalaydi (haqiqiy storage/ ga TEGMAYDI), test admin parolini yozadi,
 * `php -S` + dev/router.php ni bo'sh portda ishga tushiradi, har bir tests/NN_*.php ni alohida PHP jarayonida
 * yurgizadi (har biridan oldin rate-limit fayllari tozalanadi), oxirida serverni to'xtatib, vaqtinchalik papkani o'chiradi.
 * Chiqish kodi: 0 = hammasi o'tdi, 1 = kamida bitta xato.
 */
if (PHP_SAPI !== 'cli') { exit(1); }
foreach (['pdo_sqlite', 'curl', 'gd', 'mbstring'] as $ext) {
    if (!extension_loaded($ext)) { fwrite(STDERR, "PHP kengaytmasi kerak: $ext\n"); exit(1); }
}
$pkg = dirname(__DIR__);
$tmp = sys_get_temp_dir() . '/dariko-tests-' . bin2hex(random_bytes(4));
function rcopy(string $src, string $dst): void {
    @mkdir($dst, 0700, true);
    foreach (scandir($src) as $f) {
        if ($f === '.' || $f === '..') { continue; }
        $s = "$src/$f"; $d = "$dst/$f";
        if (is_dir($s)) { rcopy($s, $d); continue; }
        // Ish muhitidagi runtime fayllari nusxalanmaydi — testlar toza o'rnatishdan boshlanadi.
        if (preg_match('/\.(sqlite(-wal|-shm)?|log(\.1)?|lock|tmp)$|^(auth|content|rate-.*|health_state|setup_token)\.(json|txt)$|^analytics_secret\.key$/', $f)) { continue; }
        if (str_contains($d, '/public_html/uploads/') && $f !== '.htaccess') { continue; }
        copy($s, $d);
    }
}
function rrm(string $p): void {
    if (is_dir($p) && !is_link($p)) { foreach (scandir($p) as $f) { if ($f !== '.' && $f !== '..') { rrm("$p/$f"); } } @rmdir($p); } else { @unlink($p); }
}
foreach (['public_html', 'storage', 'dev'] as $d) { rcopy("$pkg/$d", "$tmp/$d"); }
file_put_contents("$tmp/storage/auth.json", json_encode(['hash' => password_hash('Test-Admin-Password-2026', PASSWORD_DEFAULT)]));
// Bo'sh port
$sock = stream_socket_server('tcp://127.0.0.1:0'); $port = (int)explode(':', stream_socket_get_name($sock, false))[1]; fclose($sock);
$env = array_merge(array_diff_key(getenv(), ['DARIKO_SITE_URL' => 1]), ['DARIKO_TG_API' => 'http://127.0.0.1:9', 'DARIKO_GRAPH_API' => 'http://127.0.0.1:9']);
// v13: mail() chiqishi faylga yoziladi (tests: $ROOT/mail.out) — email havolalarini tekshirish uchun, haqiqiy xat yuborilmaydi.
$srv = proc_open([PHP_BINARY, '-d', 'sendmail_path=cat >> ' . escapeshellarg("$tmp/mail.out"), '-S', "127.0.0.1:$port", '-t', "$tmp/public_html", "$tmp/dev/router.php"], [0 => ['pipe', 'r'], 1 => ['file', "$tmp/server.out", 'a'], 2 => ['file', "$tmp/server.out", 'a']], $pipes, $tmp, $env);
$base = "http://127.0.0.1:$port";
for ($i = 0; $i < 50; $i++) { if (@file_get_contents("$base/robots.txt") !== false) { break; } usleep(100000); }
$filter = array_slice($argv, 1);
$files = glob(__DIR__ . '/[0-9][0-9]_*.php'); sort($files);
$pass = 0; $fail = 0; $failed = [];
foreach ($files as $f) {
    $name = basename($f, '.php');
    if ($filter && !array_filter($filter, fn($p) => str_starts_with($name, $p))) { continue; }
    foreach (glob("$tmp/storage/rate-*.json") ?: [] as $r) { @unlink($r); }
    $t0 = microtime(true);
    $p = proc_open([PHP_BINARY, $f], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pp, __DIR__, array_merge(getenv(), ['DARIKO_TEST_BASE' => $base, 'DARIKO_TEST_ROOT' => $tmp]));
    $out = stream_get_contents($pp[1]) . stream_get_contents($pp[2]); $code = proc_close($p);
    $ms = (int)((microtime(true) - $t0) * 1000);
    if ($code === 0) { $pass++; printf("  OK   %-45s %5d ms  %s\n", $name, $ms, trim(explode("\n", trim($out))[count(explode("\n", trim($out))) - 1] ?? '')); }
    else { $fail++; $failed[] = $name; printf("  FAIL %-45s %5d ms\n%s\n", $name, $ms, preg_replace('/^/m', '       | ', trim($out))); }
}
proc_terminate($srv); proc_close($srv);
rrm($tmp);
printf("\n%d o'tdi, %d xato%s\n", $pass, $fail, $failed ? ' (' . implode(', ', $failed) . ')' : '');
exit($fail ? 1 : 0);
