<?php
// v9: admin/save orqali personal_page_url — xavfli sxemalar 400, to'g'ri havola/bo'sh qiymat 200.
require __DIR__ . '/lib.php';
$csrf = login();
$rev = fn() => admin('GET', 'admin/content', null, $csrf)['json']['revision'];
$save = fn(string $v) => admin('POST', 'admin/save', ['revision' => $rev(), 'field' => 'settings', 'key' => 'personal_page_url', 'value' => $v], $csrf)['status'];
t_eq(400, $save('javascript:alert(document.cookie)'), 'javascript: -> 400');
t_eq(400, $save('data:text/html,<b>x</b>'), 'data: -> 400');
t_eq(200, $save('https://example.com/me'), 'https -> 200');
t_eq('https://example.com/me', http('GET', 'public')['json']['settings']['personal_page_url'], 'saqlandi');
t_eq(200, $save(''), 'bo‘sh qiymat (o‘chirish) -> 200');
t_done('personal_page_url API');
