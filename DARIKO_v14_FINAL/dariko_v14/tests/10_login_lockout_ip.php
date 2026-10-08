<?php
// Login brute-force: bitta IP'dan 15 daqiqada 8 xato -> 429 (to'g'ri parol ham), muvaffaqiyatli kirish sanalmaydi.
require __DIR__ . '/lib.php';
$L = fn(string $p) => http('POST', 'login', ['password' => $p])['status'];
t_eq(200, $L(ADMIN_PASSWORD), 'to‘g‘ri parol');
t_eq(200, $L(ADMIN_PASSWORD), 'muvaffaqiyatli kirish hisoblagichni oshirmaydi');
for ($i = 1; $i <= 8; $i++) { t_eq(401, $L('wrong-' . $i), "$i-xato urinish -> 401"); }
t_eq(429, $L('wrong-9'), '9-urinish -> 429');
t_eq(429, $L(ADMIN_PASSWORD), 'blokda to‘g‘ri parol ham -> 429');
$hits = json_decode((string)file_get_contents(storage('rate-' . hash('sha256', '127.0.0.1') . '.json')), true);
t_eq(8, count($hits), 'IP faylida 8 ta xato');
// Oyna tugagach (15 daq) blok ochiladi
file_put_contents(storage('rate-' . hash('sha256', '127.0.0.1') . '.json'), json_encode(array_fill(0, 8, time() - 901)));
t_eq(200, $L(ADMIN_PASSWORD), '15 daqiqadan keyin -> 200');
t_done('IP darajasidagi login blok');
