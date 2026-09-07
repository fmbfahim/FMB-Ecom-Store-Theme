<?php
$dirs = [
    'c:/xampp/htdocs/fmbecstore/wp-content/themes/fmb-ecom-store',
    'c:/xampp/htdocs/fmbecstore/wp-content/themes/fmb-ecom-store 2027',
    'D:/FMB/FMB-Theme/fmb-ecom-store1.3.0'
];
$files = [
    'woocommerce/content-product.php',
    'woocommerce/archive-product.php',
    'woocommerce/cart/cart-empty.php',
    'woocommerce/cart/cart.php',
    'woocommerce/cart/cart-totals.php',
    'woocommerce/checkout/form-checkout.php',
    'functions.php'
];

$all_ok = true;
foreach ($dirs as $d) {
    foreach ($files as $f) {
        $path = "$d/$f";
        $out = [];
        $ret = 0;
        exec("C:\\xampp\\php\\php.exe -l \"$path\"", $out, $ret);
        if ($ret !== 0) {
            echo "ERROR in $path: " . implode(' ', $out) . "\n";
            $all_ok = false;
        }
    }
}
if ($all_ok) {
    echo "ALL SYNTAX CHECKS 100% CLEAN!\n";
}
