<?php
$f2027 = 'c:/xampp/htdocs/fmbecstore/wp-content/themes/fmb-ecom-store 2027/';
$fTarget = 'c:/xampp/htdocs/fmbecstore/wp-content/themes/fmb-ecom-store/';

$files = [
    'assets/css/main.css',
    'woocommerce/cart/cart.php',
    'woocommerce/cart/cart-totals.php',
    'inc/fmb-engine/init.php'
];

foreach ($files as $file) {
    echo "========================================\n";
    echo "FILE: $file\n";
    echo "========================================\n";
    $c2027 = file_get_contents($f2027 . $file);
    $cTarget = file_get_contents($fTarget . $file);
    echo "2027 len: " . strlen($c2027) . " | Target len: " . strlen($cTarget) . "\n";
    
    // Check if 2027 has things target doesn't
    $lines2027 = file($f2027 . $file);
    $linesTarget = file($fTarget . $file);
    
    $diff = array_diff($lines2027, $linesTarget);
    echo "Lines in 2027 not in Target count: " . count($diff) . "\n";
    $sample = array_slice($diff, 0, 15);
    echo "Sample lines in 2027 not in Target:\n";
    foreach ($sample as $line) {
        echo "  " . trim($line) . "\n";
    }
    echo "\n";
}
