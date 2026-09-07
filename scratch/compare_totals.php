<?php
$f2027_totals = 'c:/xampp/htdocs/fmbecstore/wp-content/themes/fmb-ecom-store 2027/woocommerce/cart/cart-totals.php';
$fTarget_totals = 'c:/xampp/htdocs/fmbecstore/wp-content/themes/fmb-ecom-store/woocommerce/cart/cart-totals.php';

echo "Totals 2027 first 25 lines:\n" . implode("\n", array_slice(file($f2027_totals), 0, 25)) . "\n";
echo "Totals Target first 25 lines:\n" . implode("\n", array_slice(file($fTarget_totals), 0, 25)) . "\n";
