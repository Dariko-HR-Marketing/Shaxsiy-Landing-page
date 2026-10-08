<?php
declare(strict_types=1);
// Testlar uchun minimal yordamchilar. Har bir test fayli: require __DIR__.'/lib.php'; ... t_done('izoh');
$BASE = getenv('DARIKO_TEST_BASE') ?: exit("run.php orqali ishga tushiring: php tests/run.php\n");
$ROOT = getenv('DARIKO_TEST_ROOT');
$JAR = tempnam(sys_get_temp_dir(), 'dariko-jar');
register_shutdown_function(fn() => @unlink($JAR));
const ADMIN_PASSWORD = 'Test-Admin-Password-2026';
$CHECKS = 0;
function t_assert(bool $cond, string $msg): void {
    global $CHECKS; $CHECKS++;
    if (!$cond) { $bt = debug_backtrace()[0]; fwrite(STDERR, "ASSERT FAILED ({$bt['file']}:{$bt['line']}): $msg\n"); exit(1); }
}
function t_eq(mixed $want, mixed $got, string $msg): void { t_assert($want === $got, "$msg — kutilgan: " . var_export($want, true) . ', olingan: ' . var_export($got, true)); }
function t_done(string $what): never { global $CHECKS; echo "$CHECKS tekshiruv — $what\n"; exit(0); }
/** HTTP so'rov. $body: array -> JSON, string -> xom. Qaytaradi: ['status'=>int, 'json'=>?array, 'body'=>string] */
function http(string $method, string $pathOrRoute, array|string|null $body = null, array $headers = [], bool $sameOrigin = true): array {
    global $BASE, $JAR;
    $url = str_starts_with($pathOrRoute, '/') ? $BASE . $pathOrRoute : $BASE . '/api.php?route=' . $pathOrRoute;
    $ch = curl_init($url);
    $h = $headers;
    if ($sameOrigin) { $h[] = 'Origin: ' . $BASE; }
    if (is_array($body)) { $body = json_encode($body); $h[] = 'Content-Type: application/json'; }
    curl_setopt_array($ch, [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => $h,
        CURLOPT_COOKIEJAR => $JAR, CURLOPT_COOKIEFILE => $JAR, CURLOPT_TIMEOUT => 30]);
    if ($body !== null) { curl_setopt($ch, CURLOPT_POSTFIELDS, $body); }
    $res = (string)curl_exec($ch); $st = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    $j = json_decode($res, true);
    return ['status' => $st, 'json' => is_array($j) ? $j : null, 'body' => $res];
}
/** Admin sifatida kiradi, CSRF tokenini qaytaradi. */
function login(): string {
    $r = http('POST', 'login', ['password' => ADMIN_PASSWORD]);
    t_eq(200, $r['status'], 'admin login');
    return (string)$r['json']['csrf'];
}
function admin(string $method, string $route, array|string|null $body, string $csrf, array $extra = []): array {
    return http($method, $route, $body, array_merge(['X-CSRF-Token: ' . $csrf], $extra));
}
/** Vaqtinchalik nusxadagi kutubxonalarni shu jarayonga yuklaydi (DATA_DIR = test storage). */
function libs(): void {
    global $ROOT;
    if (!defined('DARIKO_ENTRY')) { define('DARIKO_ENTRY', true); }
    require_once "$ROOT/public_html/common.php";
    require_once "$ROOT/public_html/analytics_lib.php";
    require_once "$ROOT/public_html/crm_lib.php";
}
function storage(string $f = ''): string { global $ROOT; return "$ROOT/storage" . ($f !== '' ? "/$f" : ''); }
function audit_has(string $event, int $sinceTs): bool {
    foreach (file(storage('audit.log'), FILE_IGNORE_NEW_LINES) ?: [] as $l) { $r = json_decode($l, true); if (($r['event'] ?? '') === $event && ($r['ts'] ?? 0) >= $sinceTs) { return true; } }
    return false;
}
function first_free_slot(): array {
    $a = http('GET', 'booking/availability', null, [], false);
    t_eq(200, $a['status'], 'availability');
    t_assert(!empty($a['json']['days']), 'kamida bitta bo‘sh kun bo‘lishi kerak');
    return [$a['json']['days'][0]['date'], $a['json']['days'][0]['slots'][0]];
}
