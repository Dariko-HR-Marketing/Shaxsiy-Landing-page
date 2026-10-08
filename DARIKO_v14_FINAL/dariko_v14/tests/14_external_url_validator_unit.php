<?php
// v9 «Shaxsiy sahifa» havolasi validatori: faqat mutlaq http/https.
require __DIR__ . '/lib.php';
libs();
foreach (['https://example.com', 'http://example.com/path?q=1#x', 'https://sub.domain.uz/sahifa'] as $ok) { t_assert(external_url_valid($ok), "qabul: $ok"); }
foreach (['javascript:alert(1)', 'JaVaScRiPt:alert(1)', ' javascript:alert(1)', 'data:text/html,<script>alert(1)</script>', 'data:text/html;base64,PHNjcmlwdD4=',
          'vbscript:msgbox(1)', 'file:///etc/passwd', '//evil.com', '/relative', 'https://user:pass@evil.com', 'https://evil.com/"onmouseover="x',
          "https://evil.com/\nx", 'https://exa mple.com', 'https://', 'ftp://example.com', 'https://' . str_repeat('a', 2050) . '.com', ''] as $bad) {
    t_assert(!external_url_valid($bad), 'rad: ' . substr(json_encode($bad), 0, 60));
}
t_done('external_url_valid()');
