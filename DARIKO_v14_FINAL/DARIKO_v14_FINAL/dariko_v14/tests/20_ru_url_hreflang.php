<?php
// C6: /?lang=ru — alohida, serverda ruscha render qilingan URL; hreflang halol (uz ≠ ru).
require __DIR__ . '/lib.php';
$ru = http('GET', '/?lang=ru', null, [], false);
t_eq(200, $ru['status'], '/?lang=ru -> 200');
t_assert(str_contains($ru['body'], '<html lang="ru"'), 'html lang=ru');
t_assert(str_contains($ru['body'], '<link rel="canonical" href="https://dariko.uz/?lang=ru">'), 'canonical o‘ziga');
t_assert(str_contains($ru['body'], '<span data-cms-text="t001">Услуги</span>'), 'CMS matni serverda ruscha');
t_assert(str_contains($ru['body'], 'hreflang="ru" href="https://dariko.uz/?lang=ru"'), 'hreflang ru -> ?lang=ru');
$uz = http('GET', '/', null, [], false);
t_assert(str_contains($uz['body'], '<html lang="uz"'), '/ o‘zbekcha qoladi');
t_assert(!preg_match('#hreflang="ru" href="https://dariko\.uz/"#', $uz['body']), 'ru hreflang endi / ga ko‘rsatmaydi');
t_eq(405, http('POST', '/ru.php', ['x' => 1], [], false)['status'], 'ru.php faqat GET');
$sm = (string)file_get_contents(storage('../public_html/sitemap.xml'));
t_assert(str_contains($sm, '<loc>https://dariko.uz/?lang=ru</loc>'), 'sitemap’da RU URL');
t_done('RU URL va hreflang');
