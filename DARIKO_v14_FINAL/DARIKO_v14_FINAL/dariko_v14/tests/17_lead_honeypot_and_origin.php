<?php
// Ommaviy forma: begona Origin -> 403; honeypot to'lgan bo'lsa "ok" qaytadi, lekin lead saqlanmaydi.
require __DIR__ . '/lib.php';
libs();
t_eq(403, http('POST', 'lead/submit', ['name' => 'Evil', 'phone' => '+998901234567'], ['Origin: https://evil.example'], false)['status'], 'begona Origin -> 403');
$before = (int)app_db()->query('SELECT COUNT(*) FROM leads')->fetchColumn();
$r = http('POST', 'lead/submit', ['name' => 'Bot', 'phone' => '+998901234567', 'website' => 'http://spam']);
t_eq(200, $r['status'], 'honeypot -> 200');
t_eq($before, (int)app_db()->query('SELECT COUNT(*) FROM leads')->fetchColumn(), 'honeypot lead saqlanmadi');
t_eq(422, http('POST', 'lead/submit', ['name' => 'Ali', 'phone' => '12345'])['status'], 'noto‘g‘ri telefon -> 422');
t_done('forma himoyasi');
