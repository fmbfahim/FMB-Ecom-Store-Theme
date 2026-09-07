<?php
$dir_active = 'c:/xampp/htdocs/fmbecstore/wp-content/themes/fmb-ecom-store';
$dir_2027 = 'c:/xampp/htdocs/fmbecstore/wp-content/themes/fmb-ecom-store 2027';

$files_to_check = [
    'woocommerce/content-product.php',
    'woocommerce/archive-product.php',
    'woocommerce/cart/cart.php',
    'woocommerce/cart/cart-empty.php',
    'woocommerce/cart/cart-item-data.php',
    'woocommerce/cart/cart-shipping.php',
    'woocommerce/cart/cart-totals.php',
    'woocommerce/cart/mini-cart.php',
    'woocommerce/cart/proceed-to-checkout-button.php',
    'woocommerce/cart/cross-sells.php',
    'woocommerce/checkout/form-checkout.php',
    'woocommerce/checkout/form-billing.php',
    'woocommerce/checkout/form-shipping.php',
    'woocommerce/checkout/order-receipt.php',
    'woocommerce/checkout/payment.php',
    'woocommerce/checkout/payment-method.php',
    'woocommerce/checkout/review-order.php',
    'woocommerce/checkout/thankyou.php',
    'woocommerce/single-product.php',
    'woocommerce/loop/loop-start.php',
    'woocommerce/loop/loop-end.php',
    'woocommerce/loop/pagination.php',
    'woocommerce/loop/orderby.php',
    'woocommerce/loop/result-count.php',
    'woocommerce/loop/sale-flash.php',
    'woocommerce/loop/add-to-cart.php',
    'woocommerce/loop/price.php',
    'woocommerce/loop/rating.php',
    'woocommerce/loop/title.php'
];

foreach ($files_to_check as $rel) {
    $p_active = "$dir_active/$rel";
    $p_2027 = "$dir_2027/$rel";
    
    $act_exists = file_exists($p_active);
    $z_exists = file_exists($p_2027);
    
    if (!$act_exists && !$z_exists) {
        continue;
    }
    
    $size_act = $act_exists ? filesize($p_active) : 'MISSING';
    $size_2027 = $z_exists ? filesize($p_2027) : 'MISSING';
    
    $same = false;
    if ($act_exists && $z_exists) {
        $same = (md5_file($p_active) === md5_file($p_2027));
    }
    
    echo sprintf("%-45s | Active: %-8s | 2027: %-8s | %s\n", $rel, $size_act, $size_2027, $same ? 'IDENTICAL' : 'DIFFERENT');
}
