<?php
// Telegram webhook: X-Telegram-Bot-Api-Secret-Token sarlavhasi tekshiruvi.
require __DIR__ . '/lib.php';
libs();
$upd = ['update_id' => 1, 'message' => ['message_id' => 77, 'date' => time(), 'chat' => ['id' => 5550001, 'type' => 'private'], 'from' => ['id' => 5550001, 'first_name' => 'Test', 'username' => 'test_user'], 'text' => 'Salom, test']];
setting_set('tg_webhook_secret', '');
t_eq(401, http('POST', 'telegram/webhook', $upd, [], false)['status'], 'secret sozlanmagan -> 401 (fail-closed)');
$secret = bin2hex(random_bytes(24));
setting_set('tg_webhook_secret', $secret);
t_eq(401, http('POST', 'telegram/webhook', $upd, [], false)['status'], 'sarlavhasiz -> 401');
t_eq(401, http('POST', 'telegram/webhook', $upd, ['X-Telegram-Bot-Api-Secret-Token: wrong-' . $secret], false)['status'], 'noto‘g‘ri secret -> 401');
$ok = http('POST', 'telegram/webhook', $upd, ['X-Telegram-Bot-Api-Secret-Token: ' . $secret], false);
t_eq(200, $ok['status'], 'to‘g‘ri secret -> 200');
$n = (int)app_db()->query("SELECT COUNT(*) FROM leads WHERE source = 'telegram'")->fetchColumn();
t_assert($n >= 1, 'telegram xabari CRM’ga lead bo‘lib tushdi');
t_done('Telegram secret-header');
