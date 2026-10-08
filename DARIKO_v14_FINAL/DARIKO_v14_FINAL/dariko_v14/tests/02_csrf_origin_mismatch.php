<?php
// To'g'ri token bo'lsa ham boshqa Origin'dan kelgan admin so'rovi rad etiladi.
require __DIR__ . '/lib.php';
$csrf = login();
$r = http('POST', 'admin/lead/create', ['name' => 'Evil'], ['X-CSRF-Token: ' . $csrf, 'Origin: https://evil.example'], false);
t_eq(403, $r['status'], 'begona Origin -> 403');
t_eq('Origin mismatch', $r['json']['error'] ?? null, 'xato matni');
t_done('Origin tekshiruvi');
