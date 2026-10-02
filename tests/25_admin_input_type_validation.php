<?php
// v13 (audit 6-masala): admin yangilash marshrutlarida noto'g'ri turdagi qiymat jim '' ga aylanmasligi (lead ismini o'chirib yubormasligi).
require __DIR__ . '/lib.php';
$csrf = login();
$A = fn(string $r, array $b) => admin('POST', $r, $b, $csrf);
$id = (int)$A('admin/lead/create', ['name' => 'Haqiqiy Mijoz', 'phone' => '+998901112233'])['json']['id'];
$name = fn() => admin('GET', 'admin/lead&id=' . $id, null, $csrf)['json']['lead']['name'] ?? admin('GET', 'admin/lead&id=' . $id, null, $csrf)['json']['name'] ?? null;
t_eq('Haqiqiy Mijoz', $name(), 'boshlang‘ich ism');
t_eq(400, $A('admin/lead/update', ['id' => $id, 'name' => ['x']])['status'], 'auditning reproduksiyasi {"name":["x"]} -> 400');
t_eq(400, $A('admin/lead/update', ['id' => $id, 'name' => ['a' => 1]])['status'], 'obyekt -> 400');
t_eq(400, $A('admin/lead/update', ['id' => $id, 'name' => 123])['status'], 'son -> 400');
t_eq(400, $A('admin/lead/update', ['id' => $id, 'name' => null])['status'], 'null -> 400');
t_eq(422, $A('admin/lead/update', ['id' => $id, 'name' => '   '])['status'], 'bo‘sh ism -> 422');
t_eq('Haqiqiy Mijoz', $name(), 'ism o‘zgarmadi');
t_eq(200, $A('admin/lead/update', ['id' => $id, 'name' => 'Yangi Ism', 'phone' => '+998907654321'])['status'], 'to‘g‘ri yangilash -> 200');
t_eq('Yangi Ism', $name(), 'ism yangilandi');
t_eq(200, $A('admin/lead/update', ['id' => $id, 'status' => 'aloqada'])['status'], 'faqat status -> 200');
// Shu sinfdagi boshqa marshrutlar
t_eq(400, $A('admin/channels/save', ['key' => ['smtp_host'], 'value' => ['x']])['status'], 'channels/save massiv -> 400 (Array to string ogohlantirishi yo‘q)');
t_eq(400, $A('admin/channels/save', ['key' => 'smtp_host', 'value' => ['x']])['status'], 'channels/save value massiv -> 400');
t_eq(200, $A('admin/channels/save', ['key' => 'smtp_host', 'value' => 'mail.dariko.uz'])['status'], 'channels/save to‘g‘ri -> 200');
t_eq(400, $A('admin/blog/save', ['title' => ['x'], 'body' => 'b'])['status'], 'blog/save massiv sarlavha -> 400');
t_eq(400, $A('admin/case/save', ['title' => 'Keys nomi', 'challenge' => ['x']])['status'], 'case/save massiv matn -> 400');
t_eq(400, $A('admin/testimonial/save', ['client_name' => ['a' => 1], 'quote' => 'ajoyib xizmat'])['status'], 'testimonial/save obyekt -> 400');
t_eq(400, $A('admin/lead/note', ['id' => $id, 'text' => ['x']])['status'], 'lead/note massiv -> 400');
t_eq(200, $A('admin/testimonial/reorder', ['ids' => []])['status'], 'ruxsat etilgan tuzilmaviy kalit (ids) ishlaydi');
global $ROOT;
t_assert(!str_contains((string)@file_get_contents("$ROOT/server.out"), 'Array to string conversion'), 'server jurnalida «Array to string conversion» yo‘q');
t_done('admin kiritish turi tekshiruvi');
