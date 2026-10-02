<?php
// v13 (audit 1-masala): PDF faol kontent skaneri siqilgan oqim / #XX hex-escape / avtomatik harakatlar orqali aylanib o'tilmasligi.
require __DIR__ . '/lib.php';
$csrf = login();
$up = fn(string $b) => admin('POST', 'admin/upload', $b, $csrf, ['Content-Type: application/pdf', 'X-Asset-Slot: resume-2']);
$objstm = function (string $objs, string $tail, string $filter = '/FlateDecode'): string {
    $z = gzcompress($objs);
    return "%PDF-1.7\n5 0 obj << /Type /ObjStm /N 1 /First 4 /Filter $filter /Length " . strlen($z) . " >> stream\n$z\nendstream endobj\n$tail\ntrailer << /Root 1 0 R >>\n%%EOF\n";
};
// Auditning aynan o'sha fayllari (p_plain / p_hex / p_aa / p_flate) qayta tuzilgan:
t_eq(400, $up("%PDF-1.7\n1 0 obj << /OpenAction << /S /JavaScript /JS (x) >> >> endobj\n%%EOF\n")['status'], 'p_plain -> 400');
t_eq(400, $up("%PDF-1.7\n1 0 obj << /Type /Catalog /OpenAction << /S /J#61vaScript /J#53 (app.alert(1)) >> >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n")['status'], 'p_hex (/J#61vaScript) -> 400');
t_eq(400, $up("%PDF-1.7\n1 0 obj << /Type /Catalog /OpenAction << /S /URI /URI (https://evil.example/phish) >> /AA << >> >> endobj\n%%EOF\n")['status'], 'p_aa (OpenAction URI) -> 400');
$z = gzcompress('1 0 obj << /Type /Action /S /JavaScript /JS (app.alert(1)) >> endobj');
t_eq(400, $up("%PDF-1.7\n5 0 obj << /Type /ObjStm /N 1 /First 8 /Filter /FlateDecode /Length " . strlen($z) . " >> stream\n$z\nendstream endobj\n1 0 obj << /Type /Catalog /OpenAction 1 0 R >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n")['status'], 'p_flate -> 400');
// Qo'shimcha variantlar
t_eq(400, $up($objstm('6 0 << /S /JavaScript /JS (app.alert(1)) >>', '1 0 obj << /Type /Catalog >> endobj'))['status'], 'siqilgan JS (OpenAction siz) -> 400');
t_eq(400, $up($objstm('6 0 << /S /J#61vaScript /JS (1) >>', '1 0 obj << /Type /Catalog >> endobj', '/Fl#61teDecode'))['status'], 'siqilgan + hex filtr nomi + hex JS -> 400');
t_eq(400, $up($objstm('2 0 << /S /URI /URI (https://evil.example) >>', '1 0 obj << /Type /Catalog /OpenAction 2 0 R >> endobj'))['status'], 'OpenAction -> siqilgan URI harakati -> 400');
t_eq(400, $up("%PDF-1.7\n1 0 obj << /Type /Page /#41A << /O << /S /U#52I /URI (https://evil.example) >> >> >> endobj\n%%EOF\n")['status'], '/AA (hex) sahifa ochilganda URI -> 400');
t_eq(400, $up($objstm('6 0 << /Type /Filespec /EF << /F 7 0 R >> >> /Names << /EmbeddedFiles 8 0 R >>', '1 0 obj << /Type /Catalog >> endobj'))['status'], 'siqilgan EmbeddedFiles -> 400');
// Buzuq siqilgan oqim 500 bermasligi kerak (o'tkazib yuboriladi)
$bad = "%PDF-1.7\n5 0 obj << /Filter /FlateDecode /Length 10 >> stream\nnotzlib!!!\nendstream endobj\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n";
t_eq(200, $up($bad)['status'], 'buzuq Flate oqimi -> 500 emas, qabul (faol kontent yo‘q)');
// Toza, siqilgan (odatiy Word/LibreOffice/Chrome shakli): kontent oqimi + oddiy havola annotatsiyasi + OpenAction manzil massivi
$content = gzcompress("BT /F1 12 Tf 72 720 Td (Rezyume - HR menejer) Tj ET");
$clean = "%PDF-1.7\n1 0 obj << /Type /Catalog /Pages 2 0 R /OpenAction [3 0 R /XYZ null null 0] >> endobj\n"
    . "2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj\n"
    . "3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Annots [6 0 R] >> endobj\n"
    . "4 0 obj << /Length " . strlen($content) . " /Filter /FlateDecode >> stream\n$content\nendstream endobj\n"
    . "6 0 obj << /Type /Annot /Subtype /Link /Rect [72 700 200 720] /A << /S /URI /URI (https://example.com/portfolio) >> >> endobj\n"
    . "trailer << /Root 1 0 R >>\n%%EOF\n";
$r = $up($clean);
t_eq(200, $r['status'], 'toza siqilgan PDF (havola + OpenAction manzil) qabul qilinadi');
$f = http('GET', '/' . explode('?', $r['json']['url'])[0]);
t_eq(200, $f['status'], 'yuklangan PDF beriladi');
t_done('PDF faol kontent: Flate/hex/OpenAction/AA aylanib o‘tishlari yopilgan, toza PDF o‘tadi');
