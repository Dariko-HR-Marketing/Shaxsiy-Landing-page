<?php
declare(strict_types=1);
/**
 * DARIKO — public_html/uploads/ tozalash skripti.
 *
 * Har bir yangi rasm yuklanganda eski fayl content.json'dan uzib qo'yiladi, lekin diskdan
 * o'chirilmaydi (README_UZ.md'da ma'lum cheklov sifatida qayd etilgan). Bu skript
 * content.json'dagi assets bilan bog'lanmagan (orphan) fayllarni topadi va, so'ralsa, o'chiradi.
 *
 * Xavfsiz ishlatish:
 *   php storage/cleanup_uploads.php            -> faqat ro'yxatni ko'rsatadi (dry-run)
 *   php storage/cleanup_uploads.php --delete   -> orphan fayllarni haqiqatan o'chiradi
 *
 * CLI orqali (masalan cron bilan) ishga tushirish uchun mo'ljallangan; veb-serverdan HTTP
 * so'rovi bilan chaqirilishini oldini olish uchun faqat php-cli SAPI'da ishlaydi.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Bu skript faqat buyruqlar qatoridan (php-cli) ishga tushiriladi.\n");
}

$dataDir = __DIR__;
$uploadsDir = __DIR__.'/../public_html/uploads';
$contentFile = $dataDir.'/content.json';
$initialFile = $dataDir.'/initial_content.json';

$file = is_file($contentFile) ? $contentFile : $initialFile;
$data = json_decode((string)@file_get_contents($file), true);
if (!is_array($data)) {
    fwrite(STDERR, "content.json o'qib bo'lmadi: $file\n");
    exit(1);
}

$referenced = [];
foreach ((array)($data['assets'] ?? []) as $url) {
    if (!is_string($url)) { continue; }
    $path = parse_url($url, PHP_URL_PATH) ?: $url;
    $referenced[basename($path)] = true;
}

// v7: blog/keys/fikrlar rasmlari (cms-media-*) dariko.sqlite'da saqlanadi — ular ham "bog'langan" hisoblanadi.
$appDb = $dataDir.'/dariko.sqlite';
if (is_file($appDb) && extension_loaded('pdo_sqlite')) {
    try {
        $pdo = new PDO('sqlite:'.$appDb, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        foreach (['SELECT cover_image FROM blog_posts', 'SELECT cover_image FROM case_studies', 'SELECT photo FROM testimonials'] as $sql) {
            foreach ($pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN) as $url) {
                if (is_string($url) && $url !== '') { $referenced[basename(parse_url($url, PHP_URL_PATH) ?: $url)] = true; }
            }
        }
    } catch (Throwable $e) { fwrite(STDERR, "dariko.sqlite o'qilmadi — xavfsizlik uchun hech narsa o'chirilmaydi.\n"); exit(1); }
} elseif (is_file($appDb)) { fwrite(STDERR, "pdo_sqlite yo'q — media fayllarni tekshirib bo'lmaydi, to'xtatildi.\n"); exit(1); }

if (!is_dir($uploadsDir)) {
    fwrite(STDERR, "uploads papkasi topilmadi: $uploadsDir\n");
    exit(1);
}

$delete = in_array('--delete', $argv ?? [], true);
$orphans = [];
foreach (scandir($uploadsDir) ?: [] as $name) {
    if ($name === '.' || $name === '..' || $name === '.htaccess') { continue; }
    if (!preg_match('/^cms-[a-z0-9-]+-[0-9a-f]{32}\.(png|pdf)$/', $name)) { continue; } // only our own generated files
    if (isset($referenced[$name])) { continue; }
    $orphans[] = $name;
}

if (!$orphans) {
    echo "Orphan fayllar topilmadi.\n";
    exit(0);
}

foreach ($orphans as $name) {
    if ($delete) {
        if (@unlink($uploadsDir.'/'.$name)) { echo "O'chirildi: $name\n"; }
        else { echo "O'chirib bo'lmadi: $name\n"; }
    } else {
        echo "Orphan (o'chirish uchun --delete bilan qayta ishga tushiring): $name\n";
    }
}
