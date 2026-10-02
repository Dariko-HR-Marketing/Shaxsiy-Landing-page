<?php
// B2: rate-limit storage ishlamasa — jim o'tkazilmaydi: audit.log + 503 (login, ariza, bron).
require __DIR__ . '/lib.php';
$t = time();
$f = storage('rate-' . hash('sha256', 'lead|127.0.0.1') . '.json');
mkdir($f); // katalog -> fopen('c+') muvaffaqiyatsiz (root sifatida ham ishlaydi)
$r = http('POST', 'lead/submit', ['name' => 'Storage Test', 'phone' => '+998901234567']);
rmdir($f);
t_eq(503, $r['status'], 'ariza: storage xatosi -> 503');
t_assert(audit_has('rate_limit_storage_error', $t), 'audit.log ga yozildi');
$g = storage('rate-' . hash('sha256', '127.0.0.1') . '.json'); mkdir($g);
$r = http('POST', 'login', ['password' => ADMIN_PASSWORD]);
rmdir($g);
t_eq(503, $r['status'], 'login: storage xatosi -> 503 (parol tekshirilmaydi)');
t_eq(200, http('POST', 'lead/submit', ['name' => 'Storage Test', 'phone' => '+998901234567'])['status'], 'tiklangandan keyin -> 200');
t_done('fail-closed rate-limit');
