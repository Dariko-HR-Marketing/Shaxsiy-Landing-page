<?php
// C2: index.html/resume.html CSP'da script-src 'unsafe-inline' yo'q va inline skript/hodisa atributlari qolmagan.
require __DIR__ . '/lib.php';
foreach (['index.html', 'resume.html'] as $p) {
    $h = (string)file_get_contents(storage("../public_html/$p"));
    t_assert((bool)preg_match('/<meta http-equiv="Content-Security-Policy" content="([^"]*)"/', $h, $csp), "$p: CSP meta bor");
    t_assert(str_contains($csp[1], "script-src 'self';"), "$p: script-src 'self'");
    t_assert(!preg_match('/script-src[^;]*unsafe-inline/', $csp[1]), "$p: script-src da unsafe-inline yo‘q");
    t_assert(!preg_match('/<script(?![^>]*\b(src=|type="application\/(ld\+)?json"))[^>]*>/i', $h), "$p: bajariladigan inline <script> yo‘q");
    t_assert(!preg_match('/\son[a-z]+\s*=\s*"/i', $h), "$p: on*=\"\" hodisa atributlari yo‘q");
}
$api = http('GET', 'public');
t_eq(200, $api['status'], 'public API');
t_done('CSP / inline skript yo‘qligi');
