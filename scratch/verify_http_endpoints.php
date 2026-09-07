<?php
$urls = [
    'Home' => 'http://localhost/fmbecstore/',
    'Shop' => 'http://localhost/fmbecstore/shop/',
    'Cart' => 'http://localhost/fmbecstore/cart/',
    'Checkout' => 'http://localhost/fmbecstore/checkout/'
];

foreach ($urls as $name => $url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    $html = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $has_warning = preg_match('/(Warning:|Fatal error:|Parse error:|Notice:)/i', $html, $m);
    echo sprintf("%-10s | HTTP: %d | %s\n", $name, $code, $has_warning ? "PHP ISSUE DETECTED: {$m[0]}" : "100% CLEAN");
}
