<?php
// v14 (audit-2, 1-masala): BEGIN IMMEDIATE endi try ichida — baza band (SQLITE_BUSY/LOCKED, busy_timeout tugaganda)
// xom PDOException/500 o'rniga toza 503 (code: db_busy) qaytarishi kerak, uncaught exception EMAS.
// Qulfni shu test jarayonidan alohida ulanish orqali ushlab turib, haqiqiy "database is locked" holatini hosil qilamiz
// (php -S server alohida jarayon bo'lgani uchun bu ikkinchi ulanish bilan haqiqatan ham raqobatlashadi).
require __DIR__ . '/lib.php';
libs();
$since = time();
$slot = first_free_slot();

$holder = new PDO('sqlite:' . storage('dariko.sqlite'));
$holder->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$holder->exec('PRAGMA busy_timeout = 200');
$holder->exec('BEGIN EXCLUSIVE');
$holder->exec('CREATE TABLE IF NOT EXISTS _t28(x INTEGER)'); // yozishni majburlaydi (jadval allaqachon bor bo'lishi mumkin)

// Qulfni ~6s dan keyin bo'shatuvchi background jarayon (server'ning busy_timeout'i 5000ms, common.php).
$dbPath = storage('dariko.sqlite');
$releaser = <<<PHP
<?php
usleep(6200000);
\$h = new PDO('sqlite:$dbPath');
try { \$h->exec('BEGIN IMMEDIATE'); \$h->exec('ROLLBACK'); } catch (Throwable \$e) {}
PHP;
$tmp = tempnam(sys_get_temp_dir(), 'dariko-rel') . '.php';
file_put_contents($tmp, $releaser);
$bg = proc_open([PHP_BINARY, $tmp], [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $p2);

$t0 = microtime(true);
$r = http('POST', 'booking/create', ['name' => 'Band test', 'phone' => '+998998887766', 'date' => $slot[0], 'time' => $slot[1]]);
$elapsed = microtime(true) - $t0;

// Qulfni ushlagan ulanishimizni ham tozalaymiz (serverning yozishi endi o'tgandan keyin xavfsiz).
try { $holder->exec('ROLLBACK'); } catch (Throwable $e) {}
if (is_resource($bg)) { proc_close($bg); }
@unlink($tmp);

t_eq(503, $r['status'], 'baza band bo‘lganda — 503 (xom 500/exception EMAS)');
t_eq('db_busy', $r['json']['code'] ?? null, 'kod: db_busy');
t_assert(is_string($r['json']['error'] ?? null) && ($r['json']['error'] ?? '') !== '', 'tushunarli xato matni bor');
t_assert(!str_contains($r['body'], 'Stack trace') && !str_contains($r['body'], 'Fatal error'), 'javobda PHP stack-trace/fatal-error chiqib ketmagan');
t_assert($elapsed >= 4.5, "server haqiqatan ham busy_timeout kutgan (elapsed={$elapsed}s)");
t_assert(audit_has('booking_db_busy', $since), 'audit.log: booking_db_busy yozilgan');
t_done('bron — baza band bo‘lganda toza 503 (xom exception yo‘q)');
