<?php
// CSRF: himoyalangan admin marshrutlari tokensiz/noto'g'ri token bilan rad etiladi.
require __DIR__ . '/lib.php';
t_eq(401, http('POST', 'admin/lead/create', ['name' => 'X'])['status'], 'kirmasdan admin POST -> 401');
$csrf = login();
t_eq(403, http('POST', 'admin/lead/create', ['name' => 'CSRF hujum'])['status'], 'tokensiz admin POST -> 403');
t_eq(403, http('POST', 'admin/lead/create', ['name' => 'CSRF hujum'], ['X-CSRF-Token: ' . str_repeat('a', 64)])['status'], 'noto‘g‘ri token -> 403');
t_eq(403, http('GET', 'admin/leads')['status'], 'admin GET ham token talab qiladi -> 403');
t_eq(403, http('POST', 'logout')['status'], 'logout ham CSRF bilan');
$ok = admin('POST', 'admin/lead/create', ['name' => 'To‘g‘ri token'], $csrf);
t_eq(200, $ok['status'], 'to‘g‘ri token -> 200');
t_done('CSRF rad etish');
