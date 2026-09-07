<?php
$dir1 = 'c:/xampp/htdocs/fmbecstore/wp-content/themes/fmb-ecom-store/';
$dir2 = 'c:/xampp/htdocs/fmbecstore/wp-content/themes/fmb-ecom-store 2027/';

$paths = [
    'woocommerce/content-product.php',
    'woocommerce/archive-product.php',
    'woocommerce/cart/cart.php',
    'woocommerce/checkout/form-checkout.php',
    'woocommerce/checkout/review-order.php',
    'woocommerce/checkout/payment.php',
    'woocommerce/single-product.php',
    'woocommerce/content-single-product.php',
    'woocommerce/cart/cart-empty.php',
    'woocommerce/cart/cart-totals.php',
    'woocommerce/cart/mini-cart.php',
    'woocommerce/checkout/thankyou.php'
];

foreach ($paths as $p) {
    $f1 = $dir1 . $p;
    $f2 = $dir2 . $p;
    echo "=== $p ===\n";
    $ex1 = file_exists($f1);
    $ex2 = file_exists($f2);
    echo "fmb-ecom-store: " . ($ex1 ? "EXISTS (" . filesize($f1) . " bytes)" : "MISSING") . "\n";
    echo "2027 theme:     " . ($ex2 ? "EXISTS (" . filesize($f2) . " bytes)" : "MISSING") . "\n";
    if ($ex1 && $ex2) {
        $c1 = file_get_contents($f1);
        $c2 = file_get_contents($f2);
        if ($c1 === $c2) {
            echo "STATUS: IDENTICAL\n\n";
        } else {
            echo "STATUS: DIFFERENT!\n";
            echo "Diff size: " . (strlen($c2) - strlen($c1)) . " bytes\n\n";
        }
    } else {
        echo "\n";
    }
}
