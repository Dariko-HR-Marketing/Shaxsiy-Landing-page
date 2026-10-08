<?php
declare(strict_types=1);
// v12 (C6): ruscha versiya uchun alohida, indekslanadigan URL: https://dariko.uz/?lang=ru
// .htaccess (va lokal dev/router.php) `/?lang=ru` va `/index.html?lang=ru` ni shu faylga yo'naltiradi.
// index.html ni o'qib, CMS matnlarining RU qiymatlarini SERVERDA qo'yadi (JS'siz ham ruscha matn), <html lang>,
// title/description/OG/canonical ni ruschaga almashtiradi. Keyin cms.js ?lang=ru ni ko'rib ruscha rejimda davom etadi.
// Faqat o'qish: sessiya/cookie ochilmaydi, POST qabul qilinmaydi.
define('DARIKO_ENTRY', true);
require __DIR__ . '/common.php';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET' && ($_SERVER['REQUEST_METHOD'] ?? '') !== 'HEAD') { http_response_code(405); exit; }
$html = (string)file_get_contents(__DIR__ . '/index.html');
$d = content_read();
$texts = $d['texts'];
$settings = $d['settings'];
$base = site_base_url(); // v13: yagona manba (common.php)
$h = static fn(string $s): string => esc($s); // v13: common.php dagi yagona esc()
$ru = static function (string $key) use ($texts): ?string {
    $t = $texts[$key] ?? null;
    return is_array($t) && is_string($t['ru'] ?? null) && trim($t['ru']) !== '' ? $t['ru'] : null;
};
// 1) Faqat matndan iborat (ichki tegsiz) data-cms-text elementlari — cms.js ham ularni textContent bilan almashtiradi.
$html = (string)preg_replace_callback('#<([a-z][a-z0-9]*)(\s[^>]*?\bdata-cms-text="(t\d+)"[^>]*)>([^<]*)</\1>#i',
    static function (array $m) use ($ru, $h): string { $v = $ru($m[3]); return $v === null ? $m[0] : "<{$m[1]}{$m[2]}>" . $h($v) . "</{$m[1]}>"; }, $html);
// 2) Atribut matnlari: data-cms-placeholder / aria-label / title / alt.
$html = (string)preg_replace_callback('#<[a-z][a-z0-9]*\s[^>]*\bdata-cms-(?:placeholder|aria-label|title|alt)="[^"]*"[^>]*>#i',
    static function (array $m) use ($ru, $h): string {
        $tag = $m[0];
        preg_match_all('#\bdata-cms-(placeholder|aria-label|title|alt)="([a-z]\d+)"#i', $tag, $mm, PREG_SET_ORDER);
        foreach ($mm as [, $attr, $key]) {
            $v = $ru($key); if ($v === null) { continue; }
            $tag = (string)preg_replace('#(\s' . preg_quote($attr, '#') . '=")[^"]*(")#', '${1}' . str_replace(['\\', '$'], ['\\\\', '\\$'], $h($v)) . '${2}', $tag, 1);
        }
        return $tag;
    }, $html);
// 3) <head>: til, sarlavha, tavsif, OG/Twitter, canonical.
$title = is_string($settings['seo_title_ru'] ?? null) && $settings['seo_title_ru'] !== '' ? $settings['seo_title_ru'] : 'DARIKO HR & MARKETING — Хикматулло Тураев | HR и маркетинг для бизнеса';
$desc = is_string($settings['seo_description_ru'] ?? null) ? $settings['seo_description_ru'] : '';
$hv = static fn(string $s): string => str_replace(['\\', '$'], ['\\\\', '\\$'], $h($s)); // preg_replace almashtirishi uchun xavfsiz
$rep = [
    '#<html lang="uz"#' => '<html lang="ru"',
    '#<title>[^<]*</title>#' => '<title>' . $hv($title) . '</title>',
    '#<link rel="canonical" href="[^"]*">#' => '<link rel="canonical" href="' . $hv("$base/?lang=ru") . '">',
    '#<meta property="og:url" content="[^"]*">#' => '<meta property="og:url" content="' . $hv("$base/?lang=ru") . '">',
    '#<meta property="og:locale" content="uz_UZ">#' => '<meta property="og:locale" content="ru_RU">',
    '#<meta property="og:locale:alternate" content="ru_RU">#' => '<meta property="og:locale:alternate" content="uz_UZ">',
    '#<meta (property="og:title"|name="twitter:title") content="[^"]*">#' => '<meta $1 content="' . $hv($title) . '">',
];
if ($desc !== '') { $rep['#<meta (name="description"|property="og:description"|name="twitter:description") content="[^"]*">#'] = '<meta $1 content="' . $hv($desc) . '">'; }
foreach ($rep as $re => $to) { $html = (string)preg_replace($re, $to, $html); }
header('Content-Type: text/html; charset=utf-8');
header('Content-Language: ru');
header('Cache-Control: no-cache');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Content-Security-Policy: frame-ancestors 'none'; object-src 'none'; base-uri 'none'");
echo $html;
