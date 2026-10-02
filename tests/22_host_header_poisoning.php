<?php
// v13 (audit 2-masala): Host sarlavhasi email havolalari / webhook manzillari / kanonik URL'larga ta'sir qilmasligi.
require __DIR__ . '/lib.php';
global $BASE, $ROOT;
$csrf = login();
t_eq(200, admin('POST', 'admin/newsletter/enabled', ['enabled' => true], $csrf)['status'], 'newsletter yoqildi');
// Auditning aynan reproduksiyasi: Host: evil.example + obuna
$r = http('POST', 'newsletter/subscribe', ['email' => 'victim@example.com'], ['Host: evil.example', 'Origin: http://evil.example'], false);
t_eq(200, $r['status'], 'obuna (Host: evil.example)');
$mail = (string)@file_get_contents("$ROOT/mail.out");
t_assert($mail !== '', 'tasdiqlash xati (sendmail capture) yozilgan');
$links = [];
foreach (preg_split('/\r?\n\r?\n/', $mail) as $blk) { $d = base64_decode(preg_replace('/\s+/', '', $blk), true); if ($d && preg_match_all('#https?://\S+#', $d, $mm)) { $links = array_merge($links, $mm[0]); } }
t_assert(count($links) >= 1, 'xatda havola bor');
foreach ($links as $l) { t_assert(!str_contains($l, 'evil.example'), "havola hujumchi domeniga ishora qilmaydi: $l"); t_assert(str_starts_with($l, 'https://dariko.uz/newsletter.php?a=confirm&t='), "havola ishonchli bazadan: $l"); }
// Admin panel: webhook manzillari Host'dan olinmaydi + "sozlanmagan" belgisi
// curl custom Host bilan cookie'ni o'zi yubormaydi — sessiya cookie'si qo'lda qo'shiladi.
global $JAR; preg_match('/dariko_session\t(\S+)/', (string)file_get_contents($JAR), $cm);
$ch = http('GET', 'admin/channels', null, ['X-CSRF-Token: ' . $csrf, 'Host: evil.example', 'Cookie: dariko_session=' . ($cm[1] ?? '')], false);
t_eq(200, $ch['status'], 'admin/channels (Host: evil.example)');
t_assert(!str_contains($ch['body'], 'evil.example'), 'webhook manzillarida evil.example yo‘q');
t_eq(false, $ch['json']['site_url_configured'], 'DARIKO_SITE_URL sozlanmaganligi ko‘rsatiladi');
// ru.php va blog kanonik manzillari ham
foreach (['/?lang=ru', '/blog/'] as $p) {
    $h = http('GET', $p, null, ['Host: evil.example'], false);
    t_assert(!str_contains($h['body'], 'evil.example'), "$p: HTML ichida evil.example yo‘q");
}
// Birlik: yagona funksiya, env qiymati tekshiruvi (port bilan/portsiz), Host e'tiborsiz
libs();
$_SERVER['HTTP_HOST'] = 'evil.example';
putenv('DARIKO_SITE_URL'); t_eq('https://dariko.uz', site_base_url(), 'env yo‘q -> kanonik domen');
putenv('DARIKO_SITE_URL=https://example.uz/'); t_eq('https://example.uz', site_base_url(), 'env (oxirgi / siz)');
putenv('DARIKO_SITE_URL=https://example.uz:8443'); t_eq('https://example.uz:8443', site_base_url(), 'env port bilan');
putenv('DARIKO_SITE_URL=http://example.uz'); t_eq('https://dariko.uz', site_base_url(), 'http:// env rad etiladi');
putenv('DARIKO_SITE_URL=https://evil.example/x?y'); t_eq('https://dariko.uz', site_base_url(), 'yo‘lli/so‘rovli env rad etiladi');
require_once "$ROOT/public_html/site_lib.php";
putenv('DARIKO_SITE_URL=https://example.uz:8443'); t_eq(site_base_url(), seo_base(), 'seo_base == site_base_url');
t_done('Host-header poisoning yopilgan');
