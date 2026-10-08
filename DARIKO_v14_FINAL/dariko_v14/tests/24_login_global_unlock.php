<?php
// v13 (audit 4-masala): global blok paytida admin Telegram /unlock bir martalik kodi bilan kira oladi; kod xavfsiz.
require __DIR__ . '/lib.php';
$L = fn(array $b) => http('POST', 'login', $b);
http('GET', 'booking/availability', null, [], false); // dariko.sqlite yaratiladi
libs();
$lock = fn() => file_put_contents(storage('rate-global.json'), json_encode(array_fill(0, LOGIN_GLOBAL_MAX, time())));
$lock();
t_eq(429, $L(['password' => ADMIN_PASSWORD])['status'], 'global blok -> 429');
t_eq(400, $L(['password' => ADMIN_PASSWORD, 'unlock' => 'abc'])['status'], 'noto‘g‘ri formatdagi kod -> 400');
t_eq(429, $L(['password' => ADMIN_PASSWORD, 'unlock' => str_repeat('0', 32)])['status'], 'soxta kod -> 429');
// Telegram webhook: admin chat /unlock (mock) — kod faqat admin chatiga; bu yerda to'g'ridan-to'g'ri kutubxona orqali
setting_set('tg_webhook_secret', 'TestSecretTestSecret');
setting_set('tg_admin_chat_id', '777');
$tg = fn(int $chat) => http('POST', 'telegram/webhook', ['message' => ['message_id' => random_int(1, 99999), 'chat' => ['id' => $chat, 'type' => 'private'], 'from' => ['first_name' => 'X'], 'text' => '/unlock']], ['X-Telegram-Bot-Api-Secret-Token: TestSecretTestSecret'], false);
t_eq('', setting_get('login_unlock_hash'), 'hali kod yo‘q');
$tg(12345);
t_eq('', setting_get('login_unlock_hash'), 'begona chatdan /unlock -> kod yaratilmaydi');
$tg(777);
t_assert(setting_get('login_unlock_hash') !== '', 'admin chatidan /unlock -> kod xeshi saqlandi');
$code = login_unlock_issue(); // testda kodni bilish uchun qayta chiqaramiz (oldingisini bekor qiladi)
t_eq(401, $L(['password' => 'wrong', 'unlock' => $code])['status'], 'to‘g‘ri kod + noto‘g‘ri parol -> 401 (parol baribir kerak)');
t_eq(200, $L(['password' => ADMIN_PASSWORD, 'unlock' => $code])['status'], 'to‘g‘ri kod + to‘g‘ri parol -> 200');
t_eq(429, $L(['password' => ADMIN_PASSWORD, 'unlock' => $code])['status'], 'kod bir martalik -> qayta 429');
$code2 = login_unlock_issue();
setting_set('login_unlock_exp', (string)(time() - 1));
t_eq(429, $L(['password' => ADMIN_PASSWORD, 'unlock' => $code2])['status'], 'muddati o‘tgan kod -> 429');
t_assert(audit_has('login_unlock_used', time() - 60), 'audit: login_unlock_used');
t_done('global blokdan Telegram unlock');
