<?php
// Ikki marta band qilish: band slotga bron -> 409; admin to'qnashuvchi bronni tasdiqlasa -> 409 (500 emas).
require __DIR__ . '/lib.php';
[$date, $time] = first_free_slot();
$b = fn() => http('POST', 'booking/create', ['name' => 'Bron Test', 'phone' => '+998901112233', 'date' => $date, 'time' => $time]);
t_eq(200, $b()['status'], 'birinchi bron -> 200');
$second = $b();
t_eq(409, $second['status'], 'xuddi shu slot -> 409');
$csrf = login();
$m = admin('GET', 'admin/bookings&month=' . substr($date, 0, 7), null, $csrf);
$ids = array_column(array_filter($m['json']['bookings'], fn($x) => $x['requested_date'] === $date && $x['requested_time_slot'] === $time), 'id');
$first = (int)$ids[0];
t_eq(200, admin('POST', 'admin/booking/status', ['id' => $first, 'status' => 'confirmed'], $csrf)['status'], 'tasdiqlash');
t_eq(409, $b()['status'], 'tasdiqlangan slotga bron -> 409');
t_eq(200, admin('POST', 'admin/booking/status', ['id' => $first, 'status' => 'cancelled'], $csrf)['status'], 'bekor qilish');
t_eq(200, $b()['status'], 'bo‘shagan slotga yangi bron -> 200');
$r = admin('POST', 'admin/booking/status', ['id' => $first, 'status' => 'confirmed'], $csrf);
t_eq(409, $r['status'], 'eski bronni qayta tasdiqlash (UNIQUE to‘qnashuv) -> 409, 500 emas');
t_done('bron to‘qnashuvi 409');
