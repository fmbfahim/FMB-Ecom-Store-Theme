<?php
$f_act = 'c:/xampp/htdocs/fmbecstore/wp-content/themes/fmb-ecom-store/woocommerce/single-product.php';
$f_2027 = 'c:/xampp/htdocs/fmbecstore/wp-content/themes/fmb-ecom-store 2027/woocommerce/single-product.php';

$c_act = file_get_contents($f_act);
$c_2027 = file_get_contents($f_2027);

echo "Active length: " . strlen($c_act) . " bytes\n";
echo "2027 length: " . strlen($c_2027) . " bytes\n";

// Find sections in 2027 that are not in active
$lines_act = explode("\n", $c_act);
$lines_2027 = explode("\n", $c_2027);

echo "Active lines: " . count($lines_act) . "\n";
echo "2027 lines: " . count($lines_2027) . "\n";

// Let's inspect first 100 lines and last 100 lines of 2027 single-product.php
echo "\n=== 2027 FIRST 60 LINES ===\n";
echo implode("\n", array_slice($lines_2027, 0, 60));
