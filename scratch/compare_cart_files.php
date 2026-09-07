<?php
$f2027_cart = 'c:/xampp/htdocs/fmbecstore/wp-content/themes/fmb-ecom-store 2027/woocommerce/cart/cart.php';
$fTarget_cart = 'c:/xampp/htdocs/fmbecstore/wp-content/themes/fmb-ecom-store/woocommerce/cart/cart.php';

$f2027_totals = 'c:/xampp/htdocs/fmbecstore/wp-content/themes/fmb-ecom-store 2027/woocommerce/cart/cart-totals.php';
$fTarget_totals = 'c:/xampp/htdocs/fmbecstore/wp-content/themes/fmb-ecom-store/woocommerce/cart/cart-totals.php';

echo "cart.php 2027 exists: " . (file_exists($f2027_cart) ? 'yes (' . filesize($f2027_cart) . 'b)' : 'no') . "\n";
echo "cart.php Target exists: " . (file_exists($fTarget_cart) ? 'yes (' . filesize($fTarget_cart) . 'b)' : 'no') . "\n";

echo "cart-totals.php 2027 exists: " . (file_exists($f2027_totals) ? 'yes (' . filesize($f2027_totals) . 'b)' : 'no') . "\n";
echo "cart-totals.php Target exists: " . (file_exists($fTarget_totals) ? 'yes (' . filesize($fTarget_totals) . 'b)' : 'no') . "\n";
