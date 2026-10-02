<?php
// Login: global (taqsimlangan hujum) chegarasi — v13: 15 daqiqada jami 50 xato (avval 30) -> barcha kirishlar 429.
require __DIR__ . '/lib.php';
$L = fn(string $p) => http('POST', 'login', ['password' => $p])['status'];
// v12 dagi 30 ta xato endi blok qilmaydi (audit 4: 4 IP bilan admin DoS)
file_put_contents(storage('rate-global.json'), json_encode(array_fill(0, 30, time())));
t_eq(200, $L(ADMIN_PASSWORD), '30 ta global xato -> endi blok yo‘q (200)');
file_put_contents(storage('rate-global.json'), json_encode(array_fill(0, 49, time())));
t_eq(401, $L('wrong'), '50-xato -> 401');
t_eq(50, count(json_decode((string)file_get_contents(storage('rate-global.json')), true)), 'global hisoblagich 50');
t_eq(429, $L(ADMIN_PASSWORD), 'global blok: to‘g‘ri parol ham -> 429');
file_put_contents(storage('rate-global.json'), json_encode(array_fill(0, 50, time() - 901)));
t_eq(200, $L(ADMIN_PASSWORD), 'eskirgan yozuvlar hisobga olinmaydi -> 200');
t_done('global login blok');
