<?php
declare(strict_types=1);
// DARIKO v7 — kontent-marketing kutubxonasi: blog, keyslar (case studies), mijozlar fikrlari, xavfsiz Markdown,
// sitemap.xml qayta yaratish va ommaviy sahifa "qobig'i" (header/footer). crm_lib.php'dagi app_db() dan foydalanadi.
if (!defined('DARIKO_ENTRY')) { http_response_code(404); exit; }

// esc() — v13: common.php ga ko'chirildi (ru.php bilan umumiy yagona PHP HTML-escape funksiyasi).

/* ============================== Xavfsiz Markdown ============================== */
// Admin matni HECH QACHON xom HTML sifatida saqlanmaydi/chiqarilmaydi: butun matn avval to'liq escape qilinadi,
// keyin faqat quyidagi cheklangan sintaksis o'zimiz yaratadigan teglarga aylantiriladi (oq ro'yxat "konstruksiya bo'yicha"):
// ## / ### sarlavha, paragraf, **qalin**, *kursiv*, `kod`, - / 1. ro'yxat, > iqtibos, [matn](https://... | /... | #... | mailto:...).
// v13 (audit 7-masala): bosqichlar tartibi tuzatildi — avval `kod` va [matn](url) PLACEHOLDER'larga ajratiladi,
// keyin qolgan matnga **qalin**/*kursiv* qo'llanadi, oxirida placeholder'lar tiklanadi. Shu sababli URL ichidagi * va _
// endi kursiv/qalin bosqichi tomonidan buzilmaydi va yopilmagan <em> hosil bo'lmaydi.
// Tashqi havola: http(s):// VA protokolga nisbiy //host (hamda brauzer / deb tushunadigan /\host) — rel/target bilan.
function md_emphasis(string $s): string {
    $s = preg_replace('/\*\*(?=\S)(.+?)(?<=\S)\*\*/u', '<strong>$1</strong>', $s) ?? $s;
    return preg_replace('/(?<![\*\w])\*(?=\S)([^*\n]+?)(?<=\S)\*(?![\*\w])/u', '<em>$1</em>', $s) ?? $s;
}
function md_inline(string $escaped): string {
    $tokens = [];
    $hold = static function (string $html) use (&$tokens): string { $tokens[] = $html; return "\x01" . (count($tokens) - 1) . "\x02"; };
    $s = str_replace(["\x01", "\x02"], '', $escaped);
    $s = preg_replace_callback('/`([^`\n]{1,200})`/', static fn(array $m): string => $hold('<code>' . $m[1] . '</code>'), $s) ?? $s;
    $s = preg_replace_callback('/\[([^\]\n]{1,200})\]\(([^)\s]{1,500})\)/u', static function (array $m) use ($hold): string {
        $url = html_entity_decode($m[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (!preg_match('#^(https?://[^\s<>"\']+|/[^\s<>"\']*|\#[A-Za-z0-9_-]*|mailto:[^\s<>"\']+)$#iD', $url)) { return $m[1]; }
        $ext = preg_match('#^(https?:)?[/\\\\]{2}#i', $url) === 1 || str_starts_with($url, '/\\');
        return $hold('<a href="' . esc($url) . '"' . ($ext ? ' rel="noopener nofollow" target="_blank"' : '') . '>' . md_emphasis($m[1]) . '</a>');
    }, $s) ?? $s;
    $s = md_emphasis($s);
    // Tiklash (havola matni ichida kod placeholder'i bo'lishi mumkin — ikki marta aylanadi)
    for ($i = 0; $i < 2 && str_contains($s, "\x01"); $i++) {
        $s = preg_replace_callback('/\x01(\d+)\x02/', static fn(array $m): string => $tokens[(int)$m[1]] ?? '', $s) ?? $s;
    }
    return $s;
}
function md_render(string $md): string {
    $md = str_replace(["\r\n", "\r"], "\n", $md);
    $blocks = preg_split('/\n{2,}/', trim($md)) ?: [];
    $html = '';
    foreach ($blocks as $block) {
        $lines = explode("\n", $block);
        $first = $lines[0];
        if (preg_match('/^(#{1,3})\s+(.+)$/u', $first, $m) && count($lines) === 1) {
            $lvl = strlen($m[1]) === 3 ? 3 : 2;
            $html .= "<h$lvl>" . md_inline(esc(trim($m[2]))) . "</h$lvl>\n";
            continue;
        }
        if (preg_match('/^[-*]\s+/', $first) && count(array_filter($lines, fn($l) => !preg_match('/^[-*]\s+/', $l))) === 0) {
            $html .= "<ul>\n" . implode('', array_map(fn($l) => '<li>' . md_inline(esc(preg_replace('/^[-*]\s+/', '', $l))) . "</li>\n", $lines)) . "</ul>\n";
            continue;
        }
        if (preg_match('/^\d+[.)]\s+/', $first) && count(array_filter($lines, fn($l) => !preg_match('/^\d+[.)]\s+/', $l))) === 0) {
            $html .= "<ol>\n" . implode('', array_map(fn($l) => '<li>' . md_inline(esc(preg_replace('/^\d+[.)]\s+/', '', $l))) . "</li>\n", $lines)) . "</ol>\n";
            continue;
        }
        if (str_starts_with($first, '>')) {
            $html .= '<blockquote><p>' . implode('<br>', array_map(fn($l) => md_inline(esc(ltrim(substr($l, 1)))), $lines)) . "</p></blockquote>\n";
            continue;
        }
        $html .= '<p>' . implode('<br>', array_map(fn($l) => md_inline(esc($l)), $lines)) . "</p>\n";
    }
    return $html;
}
function md_plain(string $md, int $max = 300): string {
    $t = preg_replace(['/\[([^\]]*)\]\([^)]*\)/', '/[#*>`_]+/', '/\s+/'], ['$1', '', ' '], $md) ?? '';
    return mb_substr(trim($t), 0, $max, 'UTF-8');
}
function slugify(string $s): string {
    $map = ['o‘' => 'o', 'g‘' => 'g', 'oʻ' => 'o', 'gʻ' => 'g', "o'" => 'o', "g'" => 'g', 'sh' => 'sh', 'ch' => 'ch', '’' => '', 'ʼ' => '', "'" => ''];
    $s = mb_strtolower(strtr(mb_strtolower($s, 'UTF-8'), $map), 'UTF-8');
    $cyr = ['а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','ё'=>'yo','ж'=>'zh','з'=>'z','и'=>'i','й'=>'y','к'=>'k','л'=>'l','м'=>'m','н'=>'n','о'=>'o','п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u','ф'=>'f','х'=>'h','ц'=>'ts','ч'=>'ch','ш'=>'sh','щ'=>'sch','ъ'=>'','ы'=>'y','ь'=>'','э'=>'e','ю'=>'yu','я'=>'ya','қ'=>'q','ғ'=>'g','ҳ'=>'h','ў'=>'o'];
    $s = strtr($s, $cyr);
    $s = preg_replace('/[^a-z0-9]+/', '-', $s) ?? '';
    return trim(substr($s, 0, 80), '-');
}
function valid_upload_url(string $u): bool {
    return $u === '' || (bool)preg_match('#^uploads/cms-(media|hero|logo|favicon|expert-[1-4])-[0-9a-f]{32}\.png(\?v=\d+)?$#D', $u);
}

/* ============================== Blog ============================== */
function blog_row_out(array $r, bool $full = true): array {
    $r['tags'] = json_decode((string)$r['tags'], true) ?: [];
    $r['id'] = (int)$r['id']; $r['published_at'] = $r['published_at'] === null ? null : (int)$r['published_at'];
    if (!$full) { unset($r['body']); }
    return $r;
}
function blog_published(int $limit = 100): array {
    $q = app_db()->prepare("SELECT * FROM blog_posts WHERE status = 'published' AND published_at <= :now ORDER BY published_at DESC LIMIT :n");
    $q->bindValue(':now', time(), PDO::PARAM_INT); $q->bindValue(':n', $limit, PDO::PARAM_INT); $q->execute();
    return array_map(fn($r) => blog_row_out($r, false), $q->fetchAll());
}
function blog_by_slug(string $slug): ?array {
    if (!preg_match('/^[a-z0-9-]{1,80}$/D', $slug)) { return null; }
    $q = app_db()->prepare("SELECT * FROM blog_posts WHERE slug = :s AND status = 'published' AND published_at <= :now");
    $q->execute([':s' => $slug, ':now' => time()]);
    $r = $q->fetch();
    return $r ? blog_row_out($r) : null;
}
function blog_admin_list(): array {
    return array_map(fn($r) => blog_row_out($r), app_db()->query('SELECT * FROM blog_posts ORDER BY COALESCE(published_at, created_at) DESC, id DESC')->fetchAll());
}
function blog_save(array $in): array {
    $db = app_db(); $now = time();
    $id = (int)($in['id'] ?? 0);
    $title = clean_text($in['title'] ?? '', 200);
    if (mb_strlen($title, 'UTF-8') < 3) { reject(422, 'Sarlavha kamida 3 belgi'); }
    $slug = slugify(clean_text($in['slug'] ?? '', 80) ?: $title);
    if ($slug === '') { reject(422, 'Slug noto‘g‘ri'); }
    $tags = array_values(array_unique(array_filter(array_map(fn($t) => clean_text($t, 40), is_array($in['tags'] ?? null) ? $in['tags'] : explode(',', (string)($in['tags'] ?? '')))))) ;
    $cover = clean_text($in['cover_image'] ?? '', 200);
    if (!valid_upload_url($cover)) { reject(422, 'Muqova rasmi manzili noto‘g‘ri'); }
    $status = ($in['status'] ?? 'draft') === 'published' ? 'published' : 'draft';
    $pubAt = null;
    if (!empty($in['published_at']) && preg_match('/^\d{4}-\d{2}-\d{2}$/D', (string)$in['published_at'])) {
        $pubAt = (new DateTimeImmutable((string)$in['published_at'] . ' 09:00', new DateTimeZone(APP_TZ)))->getTimestamp();
    }
    $f = [':slug' => $slug, ':title' => $title, ':excerpt' => clean_text($in['excerpt'] ?? '', 500, true), ':body' => clean_text($in['body'] ?? '', 60000, true),
          ':cover' => $cover, ':author' => clean_text($in['author'] ?? '', 120) ?: 'Khikmatullo Turaev', ':st' => clean_text($in['seo_title'] ?? '', 180),
          ':sd' => clean_text($in['seo_description'] ?? '', 300), ':tags' => json_encode(array_slice($tags, 0, 10), JSON_UNESCAPED_UNICODE),
          ':lang' => ($in['lang'] ?? 'uz') === 'ru' ? 'ru' : 'uz', ':status' => $status, ':now' => $now];
    try {
        if ($id > 0) {
            $old = $db->prepare('SELECT status, published_at FROM blog_posts WHERE id = :id'); $old->execute([':id' => $id]);
            $o = $old->fetch();
            if (!$o) { reject(404, 'Post not found'); }
            $f[':pub'] = $pubAt ?? ($status === 'published' ? ($o['published_at'] ?? $now) : $o['published_at']);
            $db->prepare('UPDATE blog_posts SET slug=:slug, title=:title, excerpt=:excerpt, body=:body, cover_image=:cover, author=:author, seo_title=:st,
                          seo_description=:sd, tags=:tags, lang=:lang, status=:status, published_at=:pub, updated_at=:now WHERE id=:id')->execute($f + [':id' => $id]);
            if ($o['status'] !== $status) { audit_log($status === 'published' ? 'blog_publish' : 'blog_unpublish', ['id' => $id, 'slug' => $slug]); }
            else { audit_log('blog_save', ['id' => $id, 'slug' => $slug]); }
        } else {
            $f[':pub'] = $pubAt ?? ($status === 'published' ? $now : null);
            $db->prepare('INSERT INTO blog_posts (slug,title,excerpt,body,cover_image,author,seo_title,seo_description,tags,lang,status,published_at,created_at,updated_at)
                          VALUES (:slug,:title,:excerpt,:body,:cover,:author,:st,:sd,:tags,:lang,:status,:pub,:now,:now)')->execute($f);
            $id = (int)$db->lastInsertId();
            audit_log($status === 'published' ? 'blog_publish' : 'blog_create', ['id' => $id, 'slug' => $slug]);
        }
    } catch (PDOException $e) {
        if (pdo_is_constraint_violation($e)) { reject(409, 'Bunday slug allaqachon mavjud'); }
        throw $e;
    }
    $sm = sitemap_regenerate();
    return ['ok' => true, 'id' => $id, 'slug' => $slug, 'sitemap' => $sm];
}
function blog_delete(int $id): array {
    $st = app_db()->prepare('DELETE FROM blog_posts WHERE id = :id'); $st->execute([':id' => $id]);
    if ($st->rowCount() === 0) { reject(404, 'Post not found'); }
    audit_log('blog_delete', ['id' => $id]);
    return ['ok' => true, 'sitemap' => sitemap_regenerate()];
}

/* ============================== Keyslar ============================== */
function case_row_out(array $r): array {
    $r['id'] = (int)$r['id']; $r['result_metrics'] = json_decode((string)$r['result_metrics'], true) ?: [];
    $r['is_sample'] = (int)$r['is_sample'] === 1;
    return $r;
}
function cases_published(): array {
    return array_map('case_row_out', app_db()->query("SELECT * FROM case_studies WHERE status = 'published' ORDER BY sort_order ASC, published_at DESC")->fetchAll());
}
function case_by_slug(string $slug): ?array {
    if (!preg_match('/^[a-z0-9-]{1,80}$/D', $slug)) { return null; }
    $q = app_db()->prepare("SELECT * FROM case_studies WHERE slug = :s AND status = 'published'"); $q->execute([':s' => $slug]);
    $r = $q->fetch();
    return $r ? case_row_out($r) : null;
}
function cases_admin_list(): array {
    return array_map('case_row_out', app_db()->query('SELECT * FROM case_studies ORDER BY sort_order ASC, id DESC')->fetchAll());
}
function case_save(array $in): array {
    $db = app_db(); $now = time(); $id = (int)($in['id'] ?? 0);
    $title = clean_text($in['title'] ?? '', 200);
    if (mb_strlen($title, 'UTF-8') < 3) { reject(422, 'Sarlavha kamida 3 belgi'); }
    $slug = slugify(clean_text($in['slug'] ?? '', 80) ?: $title);
    $metrics = [];
    foreach (array_slice(is_array($in['result_metrics'] ?? null) ? $in['result_metrics'] : [], 0, 8) as $m) {
        if (!is_array($m)) { continue; }
        $row = ['label' => clean_text($m['label'] ?? '', 80), 'before' => clean_text($m['before'] ?? '', 40), 'after' => clean_text($m['after'] ?? '', 40)];
        if ($row['label'] !== '') { $metrics[] = $row; }
    }
    $cover = clean_text($in['cover_image'] ?? '', 200);
    if (!valid_upload_url($cover)) { reject(422, 'Muqova rasmi manzili noto‘g‘ri'); }
    $status = ($in['status'] ?? 'draft') === 'published' ? 'published' : 'draft';
    $f = [':slug' => $slug, ':title' => $title, ':client' => clean_text($in['client_name'] ?? '', 160), ':industry' => clean_text($in['industry'] ?? '', 120),
          ':challenge' => clean_text($in['challenge'] ?? '', 8000, true), ':approach' => clean_text($in['approach'] ?? '', 8000, true),
          ':result' => clean_text($in['result_summary'] ?? '', 8000, true), ':metrics' => json_encode($metrics, JSON_UNESCAPED_UNICODE),
          ':quote' => clean_text($in['testimonial_quote'] ?? '', 1000, true), ':cover' => $cover, ':status' => $status,
          ':sort' => (int)($in['sort_order'] ?? 0), ':sample' => !empty($in['is_sample']) ? 1 : 0, ':now' => $now];
    try {
        if ($id > 0) {
            $o = $db->prepare('SELECT status, published_at FROM case_studies WHERE id = :id'); $o->execute([':id' => $id]); $old = $o->fetch();
            if (!$old) { reject(404, 'Case not found'); }
            $f[':pub'] = $status === 'published' ? ($old['published_at'] ?? $now) : $old['published_at'];
            $db->prepare('UPDATE case_studies SET slug=:slug,title=:title,client_name=:client,industry=:industry,challenge=:challenge,approach=:approach,result_summary=:result,
                          result_metrics=:metrics,testimonial_quote=:quote,cover_image=:cover,status=:status,sort_order=:sort,is_sample=:sample,published_at=:pub,updated_at=:now WHERE id=:id')
               ->execute($f + [':id' => $id]);
        } else {
            $f[':pub'] = $status === 'published' ? $now : null;
            $db->prepare('INSERT INTO case_studies (slug,title,client_name,industry,challenge,approach,result_summary,result_metrics,testimonial_quote,cover_image,status,sort_order,is_sample,published_at,created_at,updated_at)
                          VALUES (:slug,:title,:client,:industry,:challenge,:approach,:result,:metrics,:quote,:cover,:status,:sort,:sample,:pub,:now,:now)')->execute($f);
            $id = (int)$db->lastInsertId();
        }
    } catch (PDOException $e) {
        if (pdo_is_constraint_violation($e)) { reject(409, 'Bunday slug allaqachon mavjud'); }
        throw $e;
    }
    audit_log('case_save', ['id' => $id, 'status' => $status]);
    return ['ok' => true, 'id' => $id, 'sitemap' => sitemap_regenerate()];
}
function case_delete(int $id): array {
    $st = app_db()->prepare('DELETE FROM case_studies WHERE id = :id'); $st->execute([':id' => $id]);
    if ($st->rowCount() === 0) { reject(404, 'Case not found'); }
    audit_log('case_delete', ['id' => $id]);
    return ['ok' => true, 'sitemap' => sitemap_regenerate()];
}

/* ============================== Mijozlar fikrlari ============================== */
function testimonials_published(): array {
    $rows = app_db()->query('SELECT id, client_name, company, role, quote, rating, photo, source FROM testimonials WHERE published = 1 ORDER BY sort_order ASC, id DESC LIMIT 30')->fetchAll();
    foreach ($rows as &$r) { $r['id'] = (int)$r['id']; $r['rating'] = $r['rating'] === null ? null : (int)$r['rating']; }
    return $rows;
}
function testimonials_admin_list(): array {
    return app_db()->query('SELECT * FROM testimonials ORDER BY sort_order ASC, id DESC')->fetchAll();
}
function testimonial_save(array $in): array {
    $db = app_db(); $now = time(); $id = (int)($in['id'] ?? 0);
    $name = clean_text($in['client_name'] ?? '', 120); $quote = clean_text($in['quote'] ?? '', 1500, true);
    if ($name === '' || mb_strlen($quote, 'UTF-8') < 5) { reject(422, 'Ism va fikr matni majburiy'); }
    $rating = $in['rating'] ?? null;
    $rating = ($rating === null || $rating === '' || (int)$rating === 0) ? null : max(1, min(5, (int)$rating));
    $photo = clean_text($in['photo'] ?? '', 200);
    if (!valid_upload_url($photo)) { reject(422, 'Surat manzili noto‘g‘ri'); }
    $source = in_array($in['source'] ?? '', ['google', 'yandex', 'manual'], true) ? $in['source'] : 'manual';
    $f = [':n' => $name, ':c' => clean_text($in['company'] ?? '', 160), ':r' => clean_text($in['role'] ?? '', 120), ':q' => $quote, ':rt' => $rating,
          ':p' => $photo, ':s' => $source, ':pub' => !empty($in['published']) ? 1 : 0, ':now' => $now];
    if ($id > 0) {
        $st = $db->prepare('UPDATE testimonials SET client_name=:n, company=:c, role=:r, quote=:q, rating=:rt, photo=:p, source=:s, published=:pub, updated_at=:now WHERE id=:id');
        $st->execute($f + [':id' => $id]);
        if ($st->rowCount() === 0) { reject(404, 'Not found'); }
    } else {
        $max = (int)$db->query('SELECT COALESCE(MAX(sort_order), 0) FROM testimonials')->fetchColumn();
        $db->prepare('INSERT INTO testimonials (client_name, company, role, quote, rating, photo, source, published, sort_order, created_at, updated_at)
                      VALUES (:n,:c,:r,:q,:rt,:p,:s,:pub,:so,:now,:now)')->execute($f + [':so' => $max + 1]);
        $id = (int)$db->lastInsertId();
    }
    audit_log('testimonial_save', ['id' => $id, 'published' => (bool)$f[':pub']]);
    return ['ok' => true, 'id' => $id];
}
function testimonial_delete(int $id): array {
    $st = app_db()->prepare('DELETE FROM testimonials WHERE id = :id'); $st->execute([':id' => $id]);
    if ($st->rowCount() === 0) { reject(404, 'Not found'); }
    audit_log('testimonial_delete', ['id' => $id]);
    return ['ok' => true];
}
function testimonials_reorder(array $ids): array {
    $db = app_db(); $db->beginTransaction();
    $st = $db->prepare('UPDATE testimonials SET sort_order = :o WHERE id = :id');
    foreach (array_values($ids) as $i => $id) { $st->execute([':o' => $i + 1, ':id' => (int)$id]); }
    $db->commit();
    audit_log('testimonial_reorder', ['count' => count($ids)]);
    return ['ok' => true];
}

/* ============================== Sitemap ============================== */
// SEO kanonik domeni — v13: common.php dagi yagona site_base_url() (Host'ga ishonmaydi).
function seo_base(): string { return site_base_url(); }
// Nashr/o'chirishda public_html/sitemap.xml qayta yoziladi (statik fayl — qidiruv botlari uchun eng ishonchli).
function sitemap_regenerate(): array {
    $base = seo_base();
    $today = date('Y-m-d');
    $u = fn(string $loc, string $lastmod, string $freq, string $prio, string $extra = '') =>
        "  <url>\n    <loc>" . esc($loc) . "</loc>\n$extra    <lastmod>$lastmod</lastmod>\n    <changefreq>$freq</changefreq>\n    <priority>$prio</priority>\n  </url>\n";
    $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\" xmlns:xhtml=\"http://www.w3.org/1999/xhtml\">\n";
    // v12 (C6): UZ (/) va RU (/?lang=ru) — ikkita alohida URL, bir-biriga hreflang bilan bog'langan.
    $alt = "    <xhtml:link rel=\"alternate\" hreflang=\"uz\" href=\"$base/\"/>\n    <xhtml:link rel=\"alternate\" hreflang=\"ru\" href=\"$base/?lang=ru\"/>\n    <xhtml:link rel=\"alternate\" hreflang=\"x-default\" href=\"$base/\"/>\n";
    $xml .= $u("$base/", $today, 'weekly', '1.0', $alt);
    $xml .= $u("$base/?lang=ru", $today, 'weekly', '0.9', $alt);
    $posts = blog_published(1000);
    $xml .= $u("$base/blog/", $posts ? date('Y-m-d', max(array_map(fn($p) => (int)$p['updated_at'], $posts))) : $today, 'weekly', '0.8');
    foreach ($posts as $p) { $xml .= $u("$base/blog/" . $p['slug'], date('Y-m-d', (int)$p['updated_at']), 'monthly', '0.7'); }
    $cases = cases_published();
    $xml .= $u("$base/case-studies/", $today, 'monthly', '0.7');
    foreach ($cases as $c) { $xml .= $u("$base/case-studies/" . $c['slug'], date('Y-m-d', (int)$c['updated_at']), 'monthly', '0.6'); }
    $xml .= $u("$base/resume.html", $today, 'monthly', '0.8');
    $xml .= $u("$base/privacy.html", $today, 'yearly', '0.3');
    $xml .= "</urlset>\n";
    $file = __DIR__ . '/sitemap.xml';
    $tmp = __DIR__ . '/.sitemap-' . bin2hex(random_bytes(6)) . '.tmp';
    if (@file_put_contents($tmp, $xml, LOCK_EX) === false || !@rename($tmp, $file)) {
        @unlink($tmp);
        audit_log('sitemap_write_failed');
        return ['ok' => false, 'warning' => 'sitemap.xml yozib bo‘lmadi (public_html yozish huquqini tekshiring)'];
    }
    @chmod($file, 0644);
    return ['ok' => true, 'urls' => substr_count($xml, '<url>')];
}

/* ============================== Ommaviy sahifa qobig'i ============================== */
function page_security_headers(): void {
    header('Content-Type: text/html; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    // Inline skript/stil YO'Q — faqat o'z fayllarimiz + Google Fonts.
    header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data:; connect-src 'self'; object-src 'none'; base-uri 'none'; form-action 'self'; frame-ancestors 'none'");
}
function page_head(array $o): string {
    $title = esc($o['title']); $desc = esc($o['description']); $canon = esc($o['canonical']);
    $img = esc($o['image'] ?? 'https://dariko.uz/dariko-icon-dark-clean.png');
    $ld = isset($o['jsonld']) ? '<script type="application/ld+json">' . json_encode($o['jsonld'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) . '</script>' : '';
    $robots = !empty($o['noindex']) ? 'noindex, follow' : 'index, follow, max-image-preview:large, max-snippet:-1';
    $lang = esc($o['lang'] ?? 'uz');
    return <<<HTML
<!doctype html>
<html lang="$lang">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>$title</title>
<meta name="description" content="$desc">
<link rel="canonical" href="$canon">
<meta name="robots" content="$robots">
<meta property="og:type" content="{$o['og_type']}">
<meta property="og:site_name" content="DARIKO HR &amp; MARKETING">
<meta property="og:title" content="$title">
<meta property="og:description" content="$desc">
<meta property="og:url" content="$canon">
<meta property="og:image" content="$img">
<meta property="og:locale" content="uz_UZ">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="$title">
<meta name="twitter:description" content="$desc">
<meta name="twitter:image" content="$img">
<meta name="theme-color" content="#0B1727">
<link rel="icon" type="image/png" href="/dariko-icon-dark-clean.png">
<link rel="alternate" type="application/rss+xml" title="DARIKO blog" href="/blog.php?feed=rss">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Outfit:wght@700;800;900&display=swap">
<link rel="stylesheet" href="/assets/css/pages.css">
$ld
<script defer src="/assets/js/analytics.js"></script>
</head>
<body>
<a class="skip" href="#main">Asosiy mazmunga o‘tish</a>
<header class="site-head"><div class="wrap head-row">
  <a class="logo" href="/"><img src="/dariko-logo-white.png" alt="DARIKO HR &amp; MARKETING" width="160" height="48"></a>
  <nav aria-label="Asosiy menyu"><a href="/#services">Xizmatlar</a><a href="/blog/"{$o['nav_blog']}>Blog</a><a href="/case-studies/"{$o['nav_cases']}>Keyslar</a><a href="/#contact">Aloqa</a></nav>
  <a class="btn-cta" href="/#booking">Konsultatsiya</a>
</div></header>
<main id="main">
HTML;
}
function page_foot(): string {
    $y = date('Y');
    return <<<HTML
</main>
<footer class="site-foot"><div class="wrap foot-row">
  <div><img src="/dariko-logo-white.png" alt="DARIKO" width="120" height="36"><p>“DARIKO” — HR &amp; Marketing konsalting agentligi. Vobkent tumani, Buxoro viloyati.</p></div>
  <div class="foot-links"><a href="/">Bosh sahifa</a><a href="/blog/">Blog</a><a href="/case-studies/">Keyslar</a><a href="/privacy.html">Maxfiylik siyosati</a></div>
  <p class="copy">© $y DARIKO HR &amp; MARKETING</p>
</div></footer>
</body>
</html>
HTML;
}
function page_cta(): string {
    return '<section class="cta-box"><h2>Biznesingizni tizimlashtirishga tayyormisiz?</h2><p>Qulay vaqtni tanlang — 15–20 daqiqalik dastlabki suhbatda ehtiyojingizni aniqlaymiz.</p><a class="btn-cta big" href="/#booking">Konsultatsiyaga yozilish</a></section>';
}
function fmt_date_uz(int $ts): string {
    $m = ['yanvar', 'fevral', 'mart', 'aprel', 'may', 'iyun', 'iyul', 'avgust', 'sentabr', 'oktabr', 'noyabr', 'dekabr'];
    $d = (new DateTimeImmutable('@' . $ts))->setTimezone(new DateTimeZone(APP_TZ));
    return $d->format('j') . '-' . $m[(int)$d->format('n') - 1] . ', ' . $d->format('Y');
}
function page_404(string $what): never {
    http_response_code(404);
    echo page_head(['title' => 'Sahifa topilmadi — DARIKO', 'description' => 'Sahifa topilmadi', 'canonical' => seo_base() . '/', 'og_type' => 'website', 'noindex' => true, 'nav_blog' => '', 'nav_cases' => '']);
    echo '<section class="wrap narrow page-pad"><h1>Topilmadi</h1><p>' . esc($what) . ' topilmadi yoki hali nashr qilinmagan.</p><p><a href="/">Bosh sahifaga qaytish</a></p></section>';
    echo page_foot();
    exit;
}
