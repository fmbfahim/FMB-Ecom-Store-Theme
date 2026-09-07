<?php
$f2027 = 'c:/xampp/htdocs/fmbecstore/wp-content/themes/fmb-ecom-store 2027/woocommerce/checkout/form-checkout.php';
$fTarget = 'c:/xampp/htdocs/fmbecstore/wp-content/themes/fmb-ecom-store/woocommerce/checkout/form-checkout.php';

echo "=== Target Checkout Content ===\n";
echo file_get_contents($fTarget);

echo "\n=== 2027 Checkout Overview ===\n";
$lines = file($f2027);
echo "Total lines: " . count($lines) . "\n";
for ($i = 0; $i < 60; $i++) {
    if (isset($lines[$i])) echo ($i+1) . ": " . $lines[$i];
}
