<?php
declare(strict_types=1);
// DARIKO v7 — keyslar (case studies): /case-studies/ va /case-studies/<slug>. Mijozlar fikrlari ham shu sahifada.
define('DARIKO_ENTRY', true);
require __DIR__ . '/common.php';
require __DIR__ . '/crm_lib.php';
require __DIR__ . '/site_lib.php';
date_default_timezone_set(APP_TZ);
page_security_headers();
$base = seo_base();
$slug = (string)($_GET['slug'] ?? '');

function metrics_html(array $metrics): string {
    if (!$metrics) { return ''; }
    $h = '<div class="metrics">';
    foreach ($metrics as $m) {
        $h .= '<div class="metric"><span class="m-label">' . esc($m['label']) . '</span><span class="m-vals">'
            . ($m['before'] !== '' ? '<span class="m-before">' . esc($m['before']) . '</span><span class="m-arrow" aria-hidden="true">→</span>' : '')
            . '<strong class="m-after">' . esc($m['after']) . '</strong></span></div>';
    }
    return $h . '</div>';
}
function testimonials_html(array $list): string {
    if (!$list) { return ''; }
    $h = '<section class="wrap page-pad"><h2 class="sec-title">Mijozlarimiz fikri</h2><div class="card-grid testi-grid">';
    foreach ($list as $t) {
        $h .= '<figure class="testi">' . ($t['rating'] ? '<div class="stars" aria-label="' . (int)$t['rating'] . ' / 5">' . str_repeat('★', (int)$t['rating']) . '<span>' . str_repeat('★', 5 - (int)$t['rating']) . '</span></div>' : '')
            . '<blockquote>“' . esc($t['quote']) . '”</blockquote><figcaption>'
            . ($t['photo'] ? '<img src="/' . esc($t['photo']) . '" alt="" width="44" height="44" loading="lazy">' : '<span class="avatar">' . esc(mb_substr($t['client_name'], 0, 1)) . '</span>')
            . '<span><b>' . esc($t['client_name']) . '</b><small>' . esc(trim($t['role'] . ($t['role'] && $t['company'] ? ', ' : '') . $t['company'])) . '</small></span></figcaption></figure>';
    }
    return $h . '</div></section>';
}

if ($slug === '') {
    $cases = cases_published();
    $testi = testimonials_published();
    $graph = [['@type' => 'CollectionPage', '@id' => "$base/case-studies/#page", 'name' => 'Keyslar — DARIKO', 'url' => "$base/case-studies/", 'isPartOf' => ['@id' => "$base/#website"],
               'about' => ['@id' => "$base/#business"]],
              ['@type' => 'BreadcrumbList', 'itemListElement' => [['@type' => 'ListItem', 'position' => 1, 'name' => 'Bosh sahifa', 'item' => "$base/"],
                ['@type' => 'ListItem', 'position' => 2, 'name' => 'Keyslar', 'item' => "$base/case-studies/"]]]];
    // Mijozlar fikrlari uchun Review/AggregateRating sxemasi ATAYLAB qo'shilmagan: Google biznesning o'z saytidagi
    // o'zi haqidagi ("self-serving") sharhlarini rich-result sifatida ko'rsatmaydi va buni qoidabuzarlik deb hisoblashi mumkin.
    echo page_head(['title' => 'Keyslar va natijalar — DARIKO HR & MARKETING', 'description' => 'DARIKO loyihalari: muammo, yondashuv va o‘lchanadigan natijalar. Operatsion boshqaruv, HR (C&B, KPI, SOP) va marketing bo‘yicha keyslar.',
        'canonical' => "$base/case-studies/", 'og_type' => 'website', 'nav_blog' => '', 'nav_cases' => ' aria-current="page"', 'jsonld' => ['@context' => 'https://schema.org', '@graph' => $graph]]);
    echo '<section class="page-hero"><div class="wrap"><p class="eyebrow">KEYSLAR</p><h1>Muammo → yondashuv → natija</h1><p class="lead">Har bir loyiha o‘lchanadigan ko‘rsatkichlar bilan baholanadi: avval qanday edi, keyin nima o‘zgardi.</p></div></section>';
    echo '<section class="wrap page-pad">';
    if (!$cases) {
        echo '<div class="empty-state"><h2>Keyslar tayyorlanmoqda</h2><p>Mijozlarimiz roziligi bilan yakunlangan loyihalar natijalari shu yerda e’lon qilinadi.</p></div>';
    } else {
        echo '<div class="card-grid">';
        foreach ($cases as $c) {
            $u = '/case-studies/' . esc($c['slug']);
            echo '<article class="post-card case-card">' . ($c['cover_image'] ? '<a class="cover" href="' . $u . '" tabindex="-1" aria-hidden="true"><img src="/' . esc($c['cover_image']) . '" alt="" loading="lazy"></a>' : '')
                . '<div class="post-body"><div class="meta">' . esc($c['industry']) . ($c['client_name'] ? ' · ' . esc($c['client_name']) : '') . '</div><h2><a href="' . $u . '">' . esc($c['title']) . '</a></h2>'
                . '<p>' . esc(md_plain($c['challenge'], 180)) . '</p>' . metrics_html(array_slice($c['result_metrics'], 0, 3)) . '<a class="more" href="' . $u . '">Batafsil →</a></div></article>';
        }
        echo '</div>';
    }
    echo '</section>' . testimonials_html($testi) . '<div class="wrap">' . page_cta() . '</div>' . page_foot();
    exit;
}

$c = case_by_slug($slug);
if (!$c) { page_404('Keys'); }
$url = "$base/case-studies/{$c['slug']}";
$desc = md_plain($c['challenge'] . ' ' . $c['result_summary'], 160);
$img = $c['cover_image'] ? "$base/" . preg_replace('/\?.*$/', '', $c['cover_image']) : "$base/dariko-icon-dark-clean.png";
echo page_head(['title' => $c['title'] . ' — keys | DARIKO', 'description' => $desc, 'canonical' => $url, 'og_type' => 'article', 'image' => $img,
    'nav_blog' => '', 'nav_cases' => ' aria-current="page"',
    'jsonld' => ['@context' => 'https://schema.org', '@graph' => [
        ['@type' => 'Article', '@id' => "$url#article", 'headline' => mb_substr($c['title'], 0, 110), 'description' => $desc, 'image' => [$img],
         'datePublished' => date('c', (int)($c['published_at'] ?? $c['created_at'])), 'dateModified' => date('c', (int)$c['updated_at']),
         'author' => ['@type' => 'Organization', '@id' => "$base/#business", 'name' => 'DARIKO HR & MARKETING'], 'publisher' => ['@id' => "$base/#business"],
         'about' => $c['industry'], 'mainEntityOfPage' => $url],
        ['@type' => 'BreadcrumbList', 'itemListElement' => [['@type' => 'ListItem', 'position' => 1, 'name' => 'Bosh sahifa', 'item' => "$base/"],
            ['@type' => 'ListItem', 'position' => 2, 'name' => 'Keyslar', 'item' => "$base/case-studies/"], ['@type' => 'ListItem', 'position' => 3, 'name' => $c['title'], 'item' => $url]]]]]]);
echo '<article><header class="page-hero"><div class="wrap narrow"><nav class="crumbs" aria-label="Breadcrumb"><a href="/">Bosh sahifa</a> / <a href="/case-studies/">Keyslar</a></nav><h1>' . esc($c['title']) . '</h1>'
    . '<p class="meta">' . esc($c['industry']) . ($c['client_name'] ? ' · ' . esc($c['client_name']) : '') . '</p></div></header>';
if ($c['cover_image']) { echo '<div class="wrap narrow"><img class="article-cover" src="/' . esc($c['cover_image']) . '" alt="' . esc($c['title']) . '"></div>'; }
echo '<div class="wrap narrow">' . metrics_html($c['result_metrics']) . '</div><div class="wrap narrow prose">';
foreach (['challenge' => 'Vazifa / muammo', 'approach' => 'Yondashuv', 'result_summary' => 'Natija'] as $k => $h) {
    if (trim($c[$k]) !== '') { echo '<h2>' . $h . '</h2>' . md_render($c[$k]); }
}
if ($c['testimonial_quote'] !== '') { echo '<blockquote><p>“' . esc($c['testimonial_quote']) . '”</p></blockquote>'; }
echo '</div><div class="wrap narrow">' . page_cta() . '</div></article>' . page_foot();
