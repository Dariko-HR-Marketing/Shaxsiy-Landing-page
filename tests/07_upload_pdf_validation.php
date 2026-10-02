<?php
// PDF rezyume yuklash: magic bytes, %%EOF va faol kontent (JS/Launch/EmbeddedFile/...) tekshiruvi.
require __DIR__ . '/lib.php';
$csrf = login();
$pdf = fn(string $extra = '') => "%PDF-1.4\n1 0 obj << /Type /Catalog /Pages 2 0 R $extra >> endobj\n2 0 obj << /Type /Pages /Kids [] /Count 0 >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n";
$up = fn(string $b) => admin('POST', 'admin/upload', $b, $csrf, ['Content-Type: application/pdf', 'X-Asset-Slot: resume-1']);
$ok = $up($pdf());
t_eq(200, $ok['status'], 'oddiy PDF qabul qilinadi');
t_assert(str_ends_with(explode('?', $ok['json']['url'])[0], '.pdf'), 'tasodifiy nom .pdf');
t_eq(400, $up($pdf('/OpenAction << /S /JavaScript /JS (app.alert(1)) >>'))['status'], 'JavaScript -> 400');
t_eq(400, $up($pdf('/OpenAction << /S /Launch /F (cmd.exe) >>'))['status'], 'Launch -> 400');
t_eq(400, $up($pdf('/Names << /EmbeddedFiles 3 0 R >>'))['status'], 'EmbeddedFile -> 400');
t_eq(400, $up($pdf('/AcroForm << /XFA 4 0 R >>'))['status'], 'XFA forma -> 400');
t_eq(400, $up($pdf() . '<script>alert(1)</script>')['status'], '<script> -> 400 (yoki EOF yo‘q)');
t_eq(400, $up(str_replace('%%EOF', '', $pdf()))['status'], '%%EOF yo‘q -> 400');
t_eq(400, $up("<html>%PDF-1.4 fake</html>")['status'], 'soxta PDF (HTML) -> 400');
t_eq(400, $up('<?php echo 1; ?>' . $pdf())['status'], 'PHP bilan boshlangan -> 400');
t_done('PDF yuklash tekshiruvi');
