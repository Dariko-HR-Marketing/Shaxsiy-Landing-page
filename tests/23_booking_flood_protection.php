<?php
// v13 (audit 3-masala): bron kalendarini to'ldirib tashlash (flood) himoyasi — telefon dedup, kunlik chegara, IP kunlik limit.
require __DIR__ . '/lib.php';
$clearRate = fn() => array_map('unlink', glob(storage('rate-*.json')) ?: []);
$a = http('GET', 'booking/availability', null, [], false)['json'];
$slots = [];
foreach ($a['days'] as $d) { foreach ($d['slots'] as $s) { $slots[] = [$d['date'], $s]; } }
t_assert(count($slots) >= 15, 'kamida 15 bo‘sh slot');
$b = fn(string $phone, array $slot) => http('POST', 'booking/create', ['name' => 'Mijoz', 'phone' => $phone, 'date' => $slot[0], 'time' => $slot[1]]);
// 1) Bir xil telefon (turli yozilishda ham) — ikkinchi ochiq bron 409 + tushunarli xabar
t_eq(200, $b('+998901234567', $slots[0])['status'], 'birinchi bron');
$r = $b('+998 90 123-45-67', $slots[1]);
t_eq(409, $r['status'], 'xuddi shu raqam (boshqacha yozilgan) ikkinchi slot -> 409');
t_eq('existing_booking', $r['json']['code'] ?? null, 'kod: existing_booking');
t_assert(str_contains($r['json']['error'], $slots[0][0]) && str_contains($r['json']['error'], 'bog‘laning'), 'xabarda mavjud bron sanasi va aloqa yo‘li bor');
// 2) Boshqa raqam — ta'sir qilmaydi
t_eq(200, $b('+998907777777', $slots[1])['status'], 'boshqa raqam -> 200');
// 3) Admin eski bronni bekor qiladi -> mijoz yangi vaqt band qila oladi
$csrf = login();
$m = admin('GET', 'admin/bookings&month=' . substr($slots[0][0], 0, 7), null, $csrf)['json']['bookings'];
$id = (int)array_values(array_filter($m, fn($x) => $x['phone'] === '+998901234567'))[0]['id'];
t_eq(200, admin('POST', 'admin/booking/status', ['id' => $id, 'status' => 'cancelled'], $csrf)['status'], 'admin bekor qildi');
t_eq(200, $b('+998901234567', $slots[2])['status'], 'bekor qilingandan keyin yangi bron -> 200');
// 4) Kunlik sayt chegarasi (admin sozlaydi, min 10)
$rules = admin('GET', 'admin/availability', null, $csrf)['json']['rules'];
t_eq(60, $rules['daily_cap'], 'standart chegara 60');
$rules['daily_cap'] = 3; // min 10 ga ko'tariladi
$sv = admin('POST', 'admin/availability/save', ['rules' => $rules], $csrf);
t_eq(10, $sv['json']['rules']['daily_cap'], 'chegara min 10 gacha qisiladi');
// Oldingi testlar ham bron yaratgan bo'lishi mumkin — joriy 'pending' soni bazadan olinadi.
$pending = (int)(new PDO('sqlite:' . storage('dariko.sqlite')))->query("SELECT COUNT(*) FROM bookings WHERE status = 'pending' AND created_at >= " . (time() - 86400))->fetchColumn();
t_assert($pending < 10, "joriy pending ($pending) < 10");
for ($i = 0; $i < 10 - $pending; $i++) { $clearRate(); t_eq(200, $b('+99893000000' . $i, $slots[3 + $i])['status'], "flood bron $i"); }
$clearRate();
$over = $b('+998939999999', $slots[12]);
t_eq(503, $over['status'], 'kunlik chegaradan oshdi -> 503');
t_eq('daily_cap', $over['json']['code'] ?? null, 'kod: daily_cap');
// admin bittasini tasdiqlasa — yana joy ochiladi
$m = admin('GET', 'admin/bookings&month=' . substr($slots[3][0], 0, 7), null, $csrf)['json']['bookings'];
$pid = (int)array_values(array_filter($m, fn($x) => $x['phone'] === '+998930000000'))[0]['id'];
t_eq(200, admin('POST', 'admin/booking/status', ['id' => $pid, 'status' => 'confirmed'], $csrf)['status'], 'admin tasdiqladi');
$clearRate();
t_eq(200, $b('+998939999999', $slots[12])['status'], 'tasdiqlangandan keyin yana bron mumkin');
// 5) IP kunlik limit: 10 ta / 24 soat (10 daqiqalik 4 ta limitdan tashqari)
$clearRate();
$codes = [];
for ($i = 0; $i < 12; $i++) { foreach (glob(storage('rate-' . hash('sha256', 'booking|127.0.0.1') . '.json')) ?: [] as $f) { unlink($f); } $codes[] = $b('+99894000000' . ($i % 10), ['2000-01-01', '09:00'])['status']; }
t_eq(429, $codes[10], '11-urinish (24 soat ichida, bitta IP) -> 429');
t_done('bron flood himoyasi');
