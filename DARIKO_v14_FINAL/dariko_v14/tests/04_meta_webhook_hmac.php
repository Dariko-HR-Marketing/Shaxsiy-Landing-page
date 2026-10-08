<?php
// Meta (Instagram/Facebook) webhook: hub.challenge tasdiqlash va X-Hub-Signature-256 HMAC tekshiruvi.
require __DIR__ . '/lib.php';
libs();
setting_set('meta_verify_token', 'verify-token-123456');
setting_set('meta_app_secret', 'app-secret-abcdef0123456789');
$ok = http('GET', '/api.php?route=meta/webhook&hub_mode=subscribe&hub_verify_token=verify-token-123456&hub_challenge=abc123', null, [], false);
t_eq(200, $ok['status'], 'to‘g‘ri verify token -> 200'); t_eq('abc123', $ok['body'], 'challenge qaytarildi');
t_eq(403, http('GET', '/api.php?route=meta/webhook&hub_mode=subscribe&hub_verify_token=WRONG&hub_challenge=abc123', null, [], false)['status'], 'noto‘g‘ri verify token -> 403');
t_eq(403, http('GET', '/api.php?route=meta/webhook&hub_mode=subscribe&hub_verify_token=verify-token-123456&hub_challenge=%3Cscript%3E', null, [], false)['status'], 'xavfli challenge -> 403');
$raw = json_encode(['object' => 'page', 'entry' => [['id' => '1', 'time' => time(), 'messaging' => [['sender' => ['id' => '99887766'], 'recipient' => ['id' => '1'], 'timestamp' => time() * 1000, 'message' => ['mid' => 'm_test_' . bin2hex(random_bytes(4)), 'text' => 'Salom FB']]]]]]);
$hdr = ['Content-Type: application/json'];
t_eq(401, http('POST', 'meta/webhook', $raw, $hdr, false)['status'], 'imzosiz -> 401');
t_eq(401, http('POST', 'meta/webhook', $raw, array_merge($hdr, ['X-Hub-Signature-256: sha256=' . hash_hmac('sha256', $raw, 'wrong-secret')]), false)['status'], 'noto‘g‘ri kalit bilan imzo -> 401');
t_eq(401, http('POST', 'meta/webhook', $raw . ' ', array_merge($hdr, ['X-Hub-Signature-256: sha256=' . hash_hmac('sha256', $raw, 'app-secret-abcdef0123456789')]), false)['status'], 'tana o‘zgartirilgan -> 401');
t_eq(401, http('POST', 'meta/webhook', $raw, array_merge($hdr, ['X-Hub-Signature-256: ' . hash_hmac('sha256', $raw, 'app-secret-abcdef0123456789')]), false)['status'], '"sha256=" prefiksisiz -> 401');
$good = http('POST', 'meta/webhook', $raw, array_merge($hdr, ['X-Hub-Signature-256: sha256=' . hash_hmac('sha256', $raw, 'app-secret-abcdef0123456789')]), false);
t_eq(200, $good['status'], 'to‘g‘ri HMAC -> 200');
t_done('Meta verify + HMAC');
