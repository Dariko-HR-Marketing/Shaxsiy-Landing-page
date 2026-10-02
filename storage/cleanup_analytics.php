<?php
declare(strict_types=1);
/**
 * DARIKO — analitika ma'lumotlarini saqlash muddati (retention) bo'yicha tozalash.
 * 13 oydan (395 kun) eski `visits` yozuvlari, 1 kundan eski `live_sessions` va eski rate-limit
 * yozuvlarini o'chiradi. Faqat CLI'dan (cron) ishlaydi:
 *   php storage/cleanup_analytics.php            -> tozalaydi
 *   php storage/cleanup_analytics.php --vacuum   -> tozalab, fayl hajmini ham kichraytiradi
 * Cron misoli (har kuni 03:17): 17 3 * * * php /path/storage/cleanup_analytics.php
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Bu skript faqat buyruqlar qatoridan (php-cli) ishga tushiriladi.\n");
}
define('DARIKO_ENTRY', true);
require __DIR__ . '/../public_html/common.php';
require __DIR__ . '/../public_html/analytics_lib.php';
if (!analytics_available()) { fwrite(STDERR, "pdo_sqlite kengaytmasi yo'q.\n"); exit(1); }
if (!is_file(ANALYTICS_DB)) { echo "analytics.sqlite hali yaratilmagan — tozalash kerak emas.\n"; exit(0); }
$r = analytics_cleanup();
echo "O'chirildi: visits={$r['visits']}, live_sessions={$r['live_sessions']}, rate_hits={$r['rate_hits']}\n";
if (in_array('--vacuum', $argv, true)) { analytics_db()->exec('VACUUM'); echo "VACUUM bajarildi.\n"; }
