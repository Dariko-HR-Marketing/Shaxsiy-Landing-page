<?php
// v13 (audit 9-masala): admin/translate — bir marta resolve, xususiy IP rad, curl CURLOPT_RESOLVE bilan shu IP'ga qadaladi.
require __DIR__ . '/lib.php';
libs();
foreach (['https://127.0.0.1/t', 'https://10.0.0.5/t', 'https://169.254.169.254/latest', 'https://[::1]/t', 'https://localhost/t', 'http://8.8.8.8/t', 'ftp://8.8.8.8/t', 'https:///t'] as $u) {
    t_eq(null, translate_resolve_pinned($u), "xavfli/noto‘g‘ri: $u");
}
t_eq([], translate_resolve_pinned('https://8.8.8.8/translate'), 'ochiq IP literal -> ruxsat (DNS yo‘q, qadash shart emas)');
t_eq([], translate_resolve_pinned('https://[2001:4860:4860::8888]/t'), 'ochiq IPv6 literal');
// Xost nomi: DNS mavjud bo'lsa — "host:port:ip,..." shaklida qadaladi (tarmoqsiz muhitda null bo'lishi mumkin)
$r = translate_resolve_pinned('https://dns.google:8443/translate');
if ($r !== null) { t_assert((bool)preg_match('/^dns\.google:8443:[0-9a-f.:\[\],]+$/D', $r[0]), 'xost qadalgan: ' . $r[0]); }
global $ROOT;
t_assert(str_contains((string)file_get_contents("$ROOT/public_html/api.php"), 'CURLOPT_RESOLVE => $pinned'), 'api.php curl so‘rovi qadalgan IP bilan');
t_done('translate IP pinning');
