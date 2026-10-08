<?php
// B6: yangi indekslar ham yangi bazada, ham eski (v11) bazaga birinchi ulanishda qo'shiladi; ma'lumot saqlanadi.
require __DIR__ . '/lib.php';
libs();
$idx = fn(PDO $db) => $db->query("SELECT name FROM sqlite_master WHERE type='index'")->fetchAll(PDO::FETCH_COLUMN);
// Ishlayotgan (server yaratgan) bazalar
t_assert(in_array('idx_blog_status_pub', $idx(app_db()), true), 'dariko.sqlite: idx_blog_status_pub');
foreach (['idx_ab_exposures_exp', 'idx_ab_conversions_exp'] as $n) { t_assert(in_array($n, $idx(analytics_db()), true), "analytics.sqlite: $n"); }
// v11 shaklidagi bazani simulyatsiya: indekslarni o'chirib, ma'lumot qo'shib, qayta ochish (sxema-init yo'li)
$db = app_db();
$db->exec('DROP INDEX idx_blog_status_pub');
$posts = (int)$db->query('SELECT COUNT(*) FROM blog_posts')->fetchColumn();
t_assert(!in_array('idx_blog_status_pub', $idx($db), true), 'indeks olib tashlandi (v11 holati)');
$code = 'define("DARIKO_ENTRY",1); require getenv("R")."/public_html/common.php"; require getenv("R")."/public_html/crm_lib.php"; $d=app_db(); echo in_array("idx_blog_status_pub",$d->query("SELECT name FROM sqlite_master WHERE type=\'index\'")->fetchAll(PDO::FETCH_COLUMN))?"yes":"no", ",", $d->query("SELECT COUNT(*) FROM blog_posts")->fetchColumn();';
$out = shell_exec('R=' . escapeshellarg($ROOT) . ' ' . escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($code));
t_eq("yes,$posts", trim((string)$out), 'qayta ulanishda indeks tiklandi, maqolalar soni o‘zgarmadi');
$fresh = new PDO('sqlite:' . storage('dariko.sqlite')); // yangi ulanish (eski ulanishda sxema keshi eskirgan bo'ladi)
$plan = implode(' ', array_column($fresh->query("EXPLAIN QUERY PLAN SELECT * FROM blog_posts WHERE status='published' AND published_at <= 1 ORDER BY published_at DESC")->fetchAll(PDO::FETCH_ASSOC), 'detail'));
t_assert(str_contains($plan, 'idx_blog_status_pub'), "so‘rov rejasi indeksdan foydalanadi: $plan");
t_done('indeks migratsiyasi');
