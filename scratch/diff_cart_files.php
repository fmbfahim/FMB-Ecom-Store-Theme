<?php
$f2027_cart = 'c:/xampp/htdocs/fmbecstore/wp-content/themes/fmb-ecom-store 2027/woocommerce/cart/cart.php';
$fTarget_cart = 'c:/xampp/htdocs/fmbecstore/wp-content/themes/fmb-ecom-store/woocommerce/cart/cart.php';

$c2027 = file_get_contents($f2027_cart);
$cTarget = file_get_contents($fTarget_cart);

// Check headings / text
echo "Cart 2027 first 10 lines:\n" . implode("\n", array_slice(explode("\n", $c2027), 0, 40)) . "\n";
echo "Cart Target first 10 lines:\n" . implode("\n", array_slice(explode("\n", $cTarget), 0, 40)) . "\n";
