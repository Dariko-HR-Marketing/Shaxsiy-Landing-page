<?php
// DARIKO — FAQAT lokal test uchun router (PHP built-in server .htaccess'ni o'qimaydi).
// Ishga tushirish (dariko_v8/ ichidan):  php -S 127.0.0.1:8080 -t public_html dev/router.php
// Production (Apache/LiteSpeed) bu faylni ishlatmaydi va u public_html'dan tashqarida — yuklash shart emas.
// public_html/.htaccess dagi rewrite qoidalarini takrorlaydi.
if (PHP_SAPI !== 'cli-server') { http_response_code(404); exit; }
$docroot = $_SERVER['DOCUMENT_ROOT'];
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');
// .htaccess: yashirin fayllar (.well-known dan tashqari) -> 403
if (preg_match('#(^|/)\.(?!well-known(/|$))#', $path)) { http_response_code(403); exit('Forbidden'); }
$routes = [
    '#^/blog/?$#' => ['blog.php', null],
    '#^/blog/([a-z0-9-]{1,80})/?$#' => ['blog.php', 'slug'],
    '#^/case-studies/?$#' => ['case-studies.php', null],
    '#^/case-studies/([a-z0-9-]{1,80})/?$#' => ['case-studies.php', 'slug'],
];
// v12: .htaccess dagi /?lang=ru -> ru.php qoidasi
if (preg_match('#^/(index\.html)?$#', $path) && ($_GET['lang'] ?? '') === 'ru') {
    $_SERVER['SCRIPT_NAME'] = '/ru.php'; $_SERVER['SCRIPT_FILENAME'] = $docroot . '/ru.php';
    chdir($docroot); require $docroot . '/ru.php'; return true;
}
foreach ($routes as $re => [$script, $param]) {
    if (preg_match($re, $path, $m)) {
        if ($param) { $_GET[$param] = $m[1]; }
        $_SERVER['SCRIPT_NAME'] = '/' . $script;
        $_SERVER['SCRIPT_FILENAME'] = $docroot . '/' . $script;
        chdir($docroot);
        require $docroot . '/' . $script;
        return true;
    }
}
return false; // qolganini built-in server o'zi beradi (statik fayllar, api.php, ...)
