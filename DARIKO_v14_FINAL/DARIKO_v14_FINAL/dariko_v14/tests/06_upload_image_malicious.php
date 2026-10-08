<?php
// Soxta/zararli "rasm" yuklamalari rad etiladi yoki zararsizlantiriladi.
require __DIR__ . '/lib.php';
$csrf = login();
$up = fn(string $bin, string $slot = '') => $slot === ''
    ? admin('POST', 'admin/media/upload', $bin, $csrf, ['Content-Type: application/octet-stream'])
    : admin('POST', 'admin/upload', $bin, $csrf, ['Content-Type: application/octet-stream', 'X-Asset-Slot: ' . $slot]);
t_eq(400, $up('<?php system($_GET["c"]); ?>')['status'], 'PHP kodi (.png deb) -> 400');
t_eq(400, $up("GIF89a<?php echo 'pwn'; ?>")['status'], 'GIF+PHP polyglot -> 400');
t_eq(400, $up('<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"/>')['status'], 'SVG (skript bilan) -> 400');
t_eq(400, $up("\x89PNG\r\n\x1a\n" . str_repeat("\0", 64))['status'], 'buzilgan PNG sarlavhasi -> 400');
t_eq(400, $up("<html><script>alert(1)</script></html>")['status'], 'HTML -> 400');
// Katta o'lchamli (4000px dan ortiq) rasm — dekompressiya bombasiga qarshi
$im = imagecreate(4100, 10); imagecolorallocate($im, 0, 0, 0); ob_start(); imagepng($im); $big = ob_get_clean();
t_eq(400, $up($big)['status'], '4100px keng rasm -> 400');
// Haqiqiy JPEG oxiriga PHP qo'shilgan: qabul qilinadi, lekin qayta kodlashda PHP qismi yo'qoladi.
$im = imagecreatetruecolor(20, 20); ob_start(); imagejpeg($im); $jpg = ob_get_clean() . '<?php echo "pwn"; ?>';
$r = $up($jpg);
t_eq(200, $r['status'], 'JPEG+PHP dumi qayta kodlanadi');
$saved = file_get_contents(storage('../public_html/' . $r['json']['url']));
t_assert(!str_contains($saved, '<?php'), 'saqlangan faylda PHP kodi qolmagan');
t_assert(str_ends_with($r['json']['url'], '.png'), 'nom serverda tanlanadi (.png)');
t_eq(400, $up("\x89PNG", 'evil-slot')['status'], 'noma’lum slot -> 400');
// uploads/.htaccess PHP'ni o'chiradi (Apache himoyasining mavjudligini tekshirish)
t_assert(str_contains((string)file_get_contents(storage('../public_html/uploads/.htaccess')), 'php'), 'uploads/.htaccess PHP’ni cheklaydi');
t_done('zararli rasm yuklamalari');
