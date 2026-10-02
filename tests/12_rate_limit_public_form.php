<?php
// Ommaviy ariza formasi: 10 daqiqada 5 ta so'rov, 6-si 429.
require __DIR__ . '/lib.php';
$lead = ['name' => 'Rate Test', 'phone' => '+998907654321', 'message' => 'rl'];
for ($i = 1; $i <= 5; $i++) { t_eq(200, http('POST', 'lead/submit', $lead)['status'], "$i-ariza -> 200"); }
t_eq(429, http('POST', 'lead/submit', $lead)['status'], '6-ariza -> 429');
t_done('ariza rate-limit');
