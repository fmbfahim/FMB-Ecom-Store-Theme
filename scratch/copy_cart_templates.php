<?php
$f2027_cart = 'c:/xampp/htdocs/fmbecstore/wp-content/themes/fmb-ecom-store 2027/woocommerce/cart/cart.php';
$fTarget_cart = 'c:/xampp/htdocs/fmbecstore/wp-content/themes/fmb-ecom-store/woocommerce/cart/cart.php';

$f2027_totals = 'c:/xampp/htdocs/fmbecstore/wp-content/themes/fmb-ecom-store 2027/woocommerce/cart/cart-totals.php';
$fTarget_totals = 'c:/xampp/htdocs/fmbecstore/wp-content/themes/fmb-ecom-store/woocommerce/cart/cart-totals.php';

copy($f2027_cart, $fTarget_cart);
copy($f2027_totals, $fTarget_totals);

echo "Copied cart.php and cart-totals.php successfully!\n";
