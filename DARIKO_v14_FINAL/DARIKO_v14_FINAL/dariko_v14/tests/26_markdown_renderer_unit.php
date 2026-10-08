<?php
// v13 (audit 7-masala): blog Markdown — havolalar kursiv/qalin bosqichidan oldin himoyalanadi; // tashqi havola sifatida.
require __DIR__ . '/lib.php';
global $ROOT;
libs();
require_once "$ROOT/public_html/site_lib.php";
$ext = ' rel="noopener nofollow" target="_blank"';
$cases = [
    // Auditning aynan reproduksiyalari
    '*[x](https://a.com/_*)*' => "<p><em><a href=\"https://a.com/_*\"$ext>x</a></em></p>\n",
    '[a](https://x.com/*y*)z*' => "<p><a href=\"https://x.com/*y*\"$ext>a</a>z*</p>\n",
    '[x](//evil.com)' => "<p><a href=\"//evil.com\"$ext>x</a></p>\n",
    '[x](/\\evil.com)' => "<p><a href=\"/\\evil.com\"$ext>x</a></p>\n",
    '[x](https://a.com/a_b_c)' => "<p><a href=\"https://a.com/a_b_c\"$ext>x</a></p>\n",
    '**[x](https://a.com/**)**' => "<p><strong><a href=\"https://a.com/**\"$ext>x</a></strong></p>\n",
    // Oddiy (regressiya yo'q — v12 bilan aynan bir xil natija)
    '**qalin** matn' => "<p><strong>qalin</strong> matn</p>\n",
    '*kursiv* matn' => "<p><em>kursiv</em> matn</p>\n",
    'oddiy [havola](https://example.com) matn' => "<p>oddiy <a href=\"https://example.com\"$ext>havola</a> matn</p>\n",
    '[ichki](/blog/) va [anchor](#booking)' => "<p><a href=\"/blog/\">ichki</a> va <a href=\"#booking\">anchor</a></p>\n",
    '[**qalin havola**](https://a.com)' => "<p><a href=\"https://a.com\"$ext><strong>qalin havola</strong></a></p>\n",
    '**qalin *ichida kursiv* bilan**' => "<p><strong>qalin <em>ichida kursiv</em> bilan</strong></p>\n",
    '2 * 3 * 4 = 24' => "<p>2 * 3 * 4 = 24</p>\n",
    '[x](mailto:a@b.uz)' => "<p><a href=\"mailto:a@b.uz\">x</a></p>\n",
    // Xavfsizlik (v12 dagidek)
    '[x](javascript:alert(1))' => "<p>x)</p>\n",
    '[x](data:text/html,<script>)' => "<p>x</p>\n",
    '<script>alert(1)</script>' => "<p>&lt;script&gt;alert(1)&lt;/script&gt;</p>\n",
    '[<img src=x onerror=alert(1)>](https://a.com)' => "<p><a href=\"https://a.com\"$ext>&lt;img src=x onerror=alert(1)&gt;</a></p>\n",
    "[x](https://a.com)\"onclick=\"a" => "<p><a href=\"https://a.com\"$ext>x</a>&quot;onclick=&quot;a</p>\n",
    "a\x01" . "0\x02b" => "<p>a0b</p>\n", // placeholder belgilari kiritmadan tozalanadi
];
foreach ($cases as $in => $want) { t_eq($want, md_render($in), 'md: ' . json_encode($in)); }
// Teglar muvozanati: har bir natijada ochilgan/yopilgan em/strong/a soni teng
foreach (array_keys($cases) as $in) {
    $h = md_render($in);
    foreach (['em', 'strong', 'a', 'code'] as $tag) { t_eq(preg_match_all("#<$tag\\b#", $h), substr_count($h, "</$tag>"), "<$tag> muvozanati: " . json_encode($in)); }
}
t_done('Markdown renderer');
