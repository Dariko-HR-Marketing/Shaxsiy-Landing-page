<?php
declare(strict_types=1);
// DARIKO v7 — blog: /blog/ (ro'yxat), /blog/<slug> (maqola), /blog.php?feed=rss (RSS).
// Toza URL'lar .htaccess'dagi RewriteRule orqali shu faylga yo'naltiriladi; blog.php?slug=... ham ishlaydi.
define('DARIKO_ENTRY', true);
require __DIR__ . '/common.php';
require __DIR__ . '/crm_lib.php';
require __DIR__ . '/site_lib.php';
date_default_timezone_set(APP_TZ);
$base = seo_base();

if (($_GET['feed'] ?? '') === 'rss') {
    header('Content-Type: application/rss+xml; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<rss version=\"2.0\"><channel><title>DARIKO blog</title><link>$base/blog/</link><description>HR, boshqaruv va marketing bo‘yicha amaliy maqolalar</description><language>uz</language>\n";
    foreach (blog_published(30) as $p) {
        echo '<item><title>' . esc($p['title']) . '</title><link>' . esc("$base/blog/{$p['slug']}") . '</link><guid>' . esc("$base/blog/{$p['slug']}") . '</guid><pubDate>' . date(DATE_RSS, (int)$p['published_at']) . '</pubDate><description>' . esc($p['excerpt']) . "</description></item>\n";
    }
    echo "</channel></rss>\n";
    exit;
}

page_security_headers();
$slug = (string)($_GET['slug'] ?? '');

if ($slug === '') {
    $posts = blog_published();
    $tag = clean_text($_GET['tag'] ?? '', 40);
    if ($tag !== '') { $posts = array_values(array_filter($posts, fn($p) => in_array($tag, $p['tags'], true))); }
    $items = array_map(fn($p) => ['@type' => 'ListItem', 'position' => 0, 'url' => "$base/blog/{$p['slug']}", 'name' => $p['title']], $posts);
    foreach ($items as $i => &$it) { $it['position'] = $i + 1; }
    echo page_head([
        'title' => 'Blog — HR, boshqaruv va marketing bo‘yicha maqolalar | DARIKO',
        'description' => 'SOP, KPI, C&B, xodimlarni ushlab qolish va strategik marketing bo‘yicha amaliy maqolalar. DARIKO HR & MARKETING konsalting agentligi blogi.',
        'canonical' => "$base/blog/" . ($tag !== '' ? '?tag=' . rawurlencode($tag) : ''), 'og_type' => 'website', 'noindex' => $tag !== '',
        'nav_blog' => ' aria-current="page"', 'nav_cases' => '',
        'jsonld' => ['@context' => 'https://schema.org', '@graph' => [
            ['@type' => 'Blog', '@id' => "$base/blog/#blog", 'name' => 'DARIKO blog', 'url' => "$base/blog/", 'inLanguage' => 'uz',
             'publisher' => ['@id' => "$base/#business"]],
            ['@type' => 'ItemList', 'itemListElement' => $items],
            ['@type' => 'BreadcrumbList', 'itemListElement' => [
                ['@type' => 'ListItem', 'position' => 1, 'name' => 'Bosh sahifa', 'item' => "$base/"],
                ['@type' => 'ListItem', 'position' => 2, 'name' => 'Blog', 'item' => "$base/blog/"]]]]],
    ]);
    echo '<section class="page-hero"><div class="wrap"><p class="eyebrow">DARIKO BLOG</p><h1>HR, boshqaruv va marketing bo‘yicha amaliy maqolalar</h1><p class="lead">Biznesni tizimlashtirish, adolatli oylik tizimi, KPI, SOP va mijozlar oqimi haqida — amaliyotdan olingan qo‘llanmalar.</p>'
        . ($tag !== '' ? '<p class="filter">Teg: <b>' . esc($tag) . '</b> · <a href="/blog/">barchasi</a></p>' : '') . '</div></section>';
    echo '<section class="wrap page-pad">';
    if (!$posts) {
        echo '<div class="empty-state"><h2>Maqolalar tez orada</h2><p>Hozircha nashr qilingan maqola yo‘q.</p></div>';
    } else {
        echo '<div class="card-grid">';
        foreach ($posts as $p) {
            $url = '/blog/' . esc($p['slug']);
            echo '<article class="post-card">'
                . ($p['cover_image'] ? '<a class="cover" href="' . $url . '" tabindex="-1" aria-hidden="true"><img src="/' . esc($p['cover_image']) . '" alt="" loading="lazy"></a>' : '<a class="cover cover-fallback" href="' . $url . '" tabindex="-1" aria-hidden="true"><span>' . esc(mb_substr($p['title'], 0, 1)) . '</span></a>')
                . '<div class="post-body"><div class="meta"><time datetime="' . date('Y-m-d', (int)$p['published_at']) . '">' . esc(fmt_date_uz((int)$p['published_at'])) . '</time>'
                . ($p['tags'] ? ' · ' . implode(', ', array_map(fn($t) => '<a href="/blog/?tag=' . esc(rawurlencode($t)) . '">' . esc($t) . '</a>', array_slice($p['tags'], 0, 3))) : '') . '</div>'
                . '<h2><a href="' . $url . '">' . esc($p['title']) . '</a></h2><p>' . esc($p['excerpt']) . '</p><a class="more" href="' . $url . '">O‘qish →</a></div></article>';
        }
        echo '</div>';
    }
    echo page_cta() . '</section>';
    echo page_foot();
    exit;
}

$post = blog_by_slug($slug);
if (!$post) { page_404('Maqola'); }
$url = "$base/blog/{$post['slug']}";
$title = $post['seo_title'] ?: ($post['title'] . ' | DARIKO blog');
$desc = $post['seo_description'] ?: ($post['excerpt'] ?: md_plain($post['body'], 160));
$img = $post['cover_image'] ? "$base/" . preg_replace('/\?.*$/', '', $post['cover_image']) : "$base/dariko-icon-dark-clean.png";
$words = count(preg_split('/\s+/u', md_plain($post['body'], 100000)) ?: []);
echo page_head([
    'title' => $title, 'description' => $desc, 'canonical' => $url, 'og_type' => 'article', 'image' => $img, 'lang' => $post['lang'],
    'nav_blog' => ' aria-current="page"', 'nav_cases' => '',
    'jsonld' => ['@context' => 'https://schema.org', '@graph' => [
        ['@type' => 'BlogPosting', '@id' => "$url#article", 'headline' => mb_substr($post['title'], 0, 110), 'description' => $desc,
         'image' => [$img], 'datePublished' => date('c', (int)$post['published_at']), 'dateModified' => date('c', (int)$post['updated_at']),
         'author' => ['@type' => 'Person', 'name' => $post['author'], 'url' => "$base/resume.html"],
         'publisher' => ['@type' => 'Organization', '@id' => "$base/#business", 'name' => 'DARIKO HR & MARKETING', 'logo' => ['@type' => 'ImageObject', 'url' => "$base/dariko-logo-white.png"]],
         'mainEntityOfPage' => $url, 'inLanguage' => $post['lang'], 'keywords' => implode(', ', $post['tags']), 'wordCount' => $words],
        ['@type' => 'BreadcrumbList', 'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Bosh sahifa', 'item' => "$base/"],
            ['@type' => 'ListItem', 'position' => 2, 'name' => 'Blog', 'item' => "$base/blog/"],
            ['@type' => 'ListItem', 'position' => 3, 'name' => $post['title'], 'item' => $url]]]]],
]);
echo '<article class="article"><header class="page-hero"><div class="wrap narrow"><nav class="crumbs" aria-label="Breadcrumb"><a href="/">Bosh sahifa</a> / <a href="/blog/">Blog</a></nav>'
    . '<h1>' . esc($post['title']) . '</h1><p class="meta">' . esc($post['author']) . ' · <time datetime="' . date('Y-m-d', (int)$post['published_at']) . '">' . esc(fmt_date_uz((int)$post['published_at'])) . '</time> · ' . max(1, (int)round($words / 200)) . ' daqiqa o‘qish</p></div></header>';
if ($post['cover_image']) { echo '<div class="wrap narrow"><img class="article-cover" src="/' . esc($post['cover_image']) . '" alt="' . esc($post['title']) . '"></div>'; }
echo '<div class="wrap narrow prose">' . md_render($post['body']) . '</div>';
if ($post['tags']) { echo '<div class="wrap narrow tags">' . implode('', array_map(fn($t) => '<a href="/blog/?tag=' . esc(rawurlencode($t)) . '">#' . esc($t) . '</a>', $post['tags'])) . '</div>'; }
echo '<div class="wrap narrow">' . page_cta() . '</div>';
$others = array_slice(array_values(array_filter(blog_published(10), fn($p) => $p['slug'] !== $post['slug'])), 0, 3);
if ($others) {
    echo '<section class="wrap page-pad"><h2 class="sec-title">Boshqa maqolalar</h2><div class="card-grid">';
    foreach ($others as $p) { echo '<article class="post-card compact"><div class="post-body"><h3><a href="/blog/' . esc($p['slug']) . '">' . esc($p['title']) . '</a></h3><p>' . esc($p['excerpt']) . '</p></div></article>'; }
    echo '</div></section>';
}
echo '</article>' . page_foot();
