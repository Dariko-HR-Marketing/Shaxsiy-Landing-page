<?php
// B1: newsletter/subscribe standart holatda o'chiq (404), admin belgisi bilan yoqiladi.
require __DIR__ . '/lib.php';
$sub = fn() => http('POST', 'newsletter/subscribe', ['email' => 'victim@example.com'])['status'];
t_eq(404, $sub(), 'standart: 404');
t_assert(!glob(storage('rate-' . hash('sha256', 'newsletter|127.0.0.1') . '.json')), 'o‘chiq holatda rate fayli ham yaratilmaydi');
$csrf = login();
t_eq(400, admin('POST', 'admin/newsletter/enabled', ['enabled' => 'yes'], $csrf)['status'], 'bool bo‘lmagan qiymat -> 400');
t_eq(200, admin('POST', 'admin/newsletter/enabled', ['enabled' => true], $csrf)['status'], 'yoqish');
t_eq(true, admin('GET', 'admin/subscribers', null, $csrf)['json']['enabled'], 'holat ko‘rinadi');
t_eq(200, $sub(), 'yoqilgan: 200');
t_eq(200, admin('POST', 'admin/newsletter/enabled', ['enabled' => false], $csrf)['status'], 'o‘chirish');
t_eq(404, $sub(), 'yana 404');
t_done('newsletter marshruti');
