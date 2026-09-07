<?php
$urls = [
    'Homepage' => 'http://localhost/fmbecstore/',
    'Shop' => 'http://localhost/fmbecstore/shop/',
    'Combo Archive' => 'http://localhost/fmbecstore/?fmb_all_combos=1',
    'Single Combo' => 'http://localhost/fmbecstore/?fmb_combo_id=33',
    'Cart' => 'http://localhost/fmbecstore/cart/',
];

foreach ($urls as $name => $url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $has_warning = (strpos($res, 'Warning:') !== false || strpos($res, 'Fatal error:') !== false);
    echo "$name ($url): HTTP $code | Errors: " . ($has_warning ? "YES" : "CLEAN (0 errors)") . "\n";
    if ($has_warning) {
        preg_match_all('/(Warning|Fatal error):[^\n<]+/i', $res, $m);
        foreach ($m[0] as $err) {
            echo "  ! $err\n";
        }
    }
}
