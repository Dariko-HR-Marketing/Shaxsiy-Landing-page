<?php
declare(strict_types=1);
// DARIKO v7 — newsletter: obunani tasdiqlash va bekor qilish (login talab qilinmaydi, tasodifiy 160-bit token bilan).
// GET faqat tasdiqlash tugmasini ko'rsatadi; holat POST bilan o'zgaradi (pochta skanerlari/prefetch havolani "bosib" yubormasligi uchun).
// RFC 8058 "One-Click" bekor qilish: pochta mijozi POST List-Unsubscribe=One-Click yuboradi — u ham qo'llab-quvvatlanadi.
define('DARIKO_ENTRY', true);
require __DIR__ . '/common.php';
require __DIR__ . '/crm_lib.php';
require __DIR__ . '/site_lib.php';
page_security_headers();
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');
$a = (string)($_GET['a'] ?? '');
$t = (string)($_GET['t'] ?? '');
$validToken = preg_match('/^[a-f0-9]{40}$/D', $t) === 1;
$db = app_db();
$msg = ''; $done = false;
if (!in_array($a, ['confirm', 'unsubscribe'], true) || !$validToken) {
    $msg = 'Havola noto‘g‘ri yoki eskirgan.';
} else {
    $col = $a === 'confirm' ? 'confirm_token' : 'unsubscribe_token';
    $q = $db->prepare("SELECT id, email, confirmed, unsubscribed_at FROM subscribers WHERE $col = :t"); // $col — qat'iy 2 qiymatdan biri
    $q->execute([':t' => $t]);
    $sub = $q->fetch();
    if (!$sub) { $msg = 'Havola noto‘g‘ri yoki eskirgan.'; }
    elseif (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        if ($a === 'confirm') {
            $db->prepare('UPDATE subscribers SET confirmed = 1, confirmed_at = :n, unsubscribed_at = NULL WHERE id = :id')->execute([':n' => time(), ':id' => $sub['id']]);
            $msg = 'Rahmat! Obunangiz tasdiqlandi. / Спасибо! Подписка подтверждена.';
        } else {
            $db->prepare('UPDATE subscribers SET unsubscribed_at = :n WHERE id = :id')->execute([':n' => time(), ':id' => $sub['id']]);
            $msg = 'Obuna bekor qilindi. Sizga boshqa xat yuborilmaydi. / Вы отписались от рассылки.';
        }
        $done = true;
    }
}
echo page_head(['title' => 'Obuna — DARIKO', 'description' => 'DARIKO newsletter', 'canonical' => seo_base() . '/', 'og_type' => 'website', 'noindex' => true, 'nav_blog' => '', 'nav_cases' => '']);
echo '<section class="wrap narrow page-pad"><div class="empty-state">';
if ($msg !== '') {
    echo '<h1>' . ($done ? '✓' : 'Xatolik') . '</h1><p>' . esc($msg) . '</p><p><a href="/">Bosh sahifaga qaytish</a></p>';
} else {
    $label = $a === 'confirm' ? 'Obunani tasdiqlash / Подтвердить подписку' : 'Obunani bekor qilish / Отписаться';
    echo '<h1>' . ($a === 'confirm' ? 'Obunani tasdiqlang' : 'Obunani bekor qilish') . '</h1><p>' . esc($sub['email']) . '</p>'
        . '<form method="post" action="/newsletter.php?a=' . esc($a) . '&amp;t=' . esc($t) . '"><button class="btn-cta big" type="submit">' . esc($label) . '</button></form>';
}
echo '</div></section>' . page_foot();
