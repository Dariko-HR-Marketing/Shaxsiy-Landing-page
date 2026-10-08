<?php
declare(strict_types=1);
define('DARIKO_ENTRY', true);
require __DIR__.'/common.php';
if (!secure_transport()) { http_response_code(403); exit('HTTPS required'); }
header('Cache-Control: no-store');
header('X-Frame-Options: DENY');
header('X-Robots-Tag: noindex, nofollow');
header('Content-Security-Policy: default-src \'none\'; style-src \'unsafe-inline\'; form-action \'self\'; base-uri \'none\'');
if (is_file(AUTH_FILE)) {
    // Defense-in-depth: setup.php cannot delete itself mid-request, so it hard-refuses to
    // render once an admin exists, and logs each post-setup hit so admins scanning PHP error
    // logs notice the file is still deployed and should be removed.
    error_log('DARIKO setup.php was requested after admin setup was already completed; it should be deleted from the server (rm public_html/setup.php).');
    http_response_code(410);
    exit('410 Gone: Admin account already configured. This file (setup.php) must now be deleted from the server for security — ask your hosting admin to run: rm public_html/setup.php. Meanwhile, open admin.html to manage the site.');
}
$tokenFile = DATA_DIR.'/setup_token.txt';
if (!is_file($tokenFile)) {
    $newToken = bin2hex(random_bytes(24));
    $handle = @fopen($tokenFile, 'x');
    if ($handle !== false) { fwrite($handle, $newToken); fclose($handle); @chmod($tokenFile, 0600); }
}
if (!is_file($tokenFile)) { http_response_code(500); exit('Storage is not writable.'); }

// flock() self-check: file storage relies entirely on flock() for concurrency-safety, which is
// not honored reliably on some shared-hosting/NFS filesystems. This is a documented architecture
// constraint (see README_UZ.md), not something fixable without moving to a database — this is
// only detection, done once here at setup time, so the admin finds out before going live.
$flockWarning = '';
$flockTestFile = DATA_DIR.'/.flock_selftest';
$fh = @fopen($flockTestFile, 'c');
if ($fh === false) {
    $flockWarning = 'storage/ papkasiga yozib bo‘lmadi — flock() sinovi o‘tkazilmadi.';
} else {
    if (!flock($fh, LOCK_EX)) {
        $flockWarning = 'flock() ushbu serverda ishlamayapti (masalan, ba’zi NFS/umumiy hosting muhitlarida). Fayl-asoslangan saqlash bir vaqtning o‘zida bir nechta yozuvda buzilishi mumkin — hostingni almashtiring yoki ma’lumotlar bazasiga o‘tishni ko‘rib chiqing.';
    }
    @flock($fh, LOCK_UN);
    fclose($fh);
    @unlink($flockTestFile);
}
$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $given = $_POST['token'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm'] ?? '';
    $expected = trim((string)file_get_contents($tokenFile));
    if (!is_string($given) || !hash_equals($expected, $given)) { $error = 'Bir martalik kod xato.'; }
    elseif (!is_string($password) || strlen($password) < 16 || strlen($password) > 256) { $error = 'Parol 16–256 belgidan iborat bo‘lsin.'; }
    elseif (!is_string($confirm) || !hash_equals($password, $confirm)) { $error = 'Parollar mos emas.'; }
    else {
        $auth = json_encode(['hash' => password_hash($password, PASSWORD_DEFAULT)], JSON_THROW_ON_ERROR);
        $tmp = DATA_DIR.'/auth-'.bin2hex(random_bytes(8)).'.tmp';
        if (file_put_contents($tmp, $auth, LOCK_EX) === false || !rename($tmp, AUTH_FILE)) {
            @unlink($tmp); http_response_code(500); exit('Parol saqlanmadi.');
        }
        @chmod(AUTH_FILE, 0600);
        @unlink($tokenFile);
        audit_log('setup_admin_created');
        header('Location: admin.html', true, 303);
        exit;
    }
}
?><!doctype html><html lang="uz"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>DARIKO — Administratorni yaratish</title><style>body{margin:0;min-height:100vh;display:grid;place-items:center;background:#0b1d30;color:#f3f8fa;font:16px/1.6 system-ui}.card{width:min(430px,90vw);background:#183147;padding:30px;border:1px solid #34546b;border-radius:18px}h1{font-size:27px;margin:0 0 15px}label{display:block;margin:15px 0}input{display:block;box-sizing:border-box;width:100%;padding:12px;background:#0c2437;border:1px solid #648094;border-radius:9px;color:#fff;font:inherit}button{border:0;border-radius:9px;background:#05a85c;color:#051c11;font-weight:750;padding:12px 20px;cursor:pointer}.error{color:#ffacac}.note{color:#bfd1d8}</style></head><body><main class="card"><h1>Administratorni yaratish</h1><p class="note">Bir martalik kod <strong>storage/setup_token.txt</strong> faylida. Uni hostingning File Manager oynasida ko‘ring. Kodni va parolni hech kimga yubormang.</p><?php if ($flockWarning !== ''): ?><p class="error" role="alert"><?=esc($flockWarning)?></p><?php endif; ?><?php if ($error !== ''): ?><p class="error" role="alert"><?=esc($error)?></p><?php endif; ?><form method="post" autocomplete="off"><label>Bir martalik kod<input name="token" required autocomplete="off"></label><label>Yangi parol (kamida 16 belgi)<input name="password" type="password" minlength="16" maxlength="256" required autocomplete="new-password"></label><label>Parolni takrorlang<input name="confirm" type="password" minlength="16" maxlength="256" required autocomplete="new-password"></label><button type="submit">Administratorni yaratish</button></form></main></body></html>
