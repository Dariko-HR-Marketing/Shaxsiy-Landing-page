<?php
// Haqiqiy PNG/JPEG qabul qilinadi va GD orqali qayta kodlangan PNG sifatida saqlanadi.
require __DIR__ . '/lib.php';
$csrf = login();
foreach (['png' => 'imagepng', 'jpeg' => 'imagejpeg'] as $type => $fn) {
    $im = imagecreatetruecolor(40, 30); imagefill($im, 0, 0, imagecolorallocate($im, 10, 160, 90));
    ob_start(); $fn($im); $bin = ob_get_clean();
    $r = admin('POST', 'admin/media/upload', $bin, $csrf, ['Content-Type: application/octet-stream']);
    t_eq(200, $r['status'], "$type qabul qilinadi");
    $saved = file_get_contents(storage('../public_html/' . $r['json']['url']));
    t_eq("\x89PNG", substr($saved, 0, 4), "$type -> PNG sifatida qayta kodlangan");
}
$r = admin('POST', 'admin/upload', (function () { $im = imagecreatetruecolor(64, 64); ob_start(); imagepng($im); return ob_get_clean(); })(), $csrf, ['Content-Type: application/octet-stream', 'X-Asset-Slot: logo']);
t_eq(200, $r['status'], 'logo slotiga PNG');
t_done('to‘g‘ri rasm yuklash');
