<?php
define('WP_USE_THEMES', false);
require_once 'c:/xampp/htdocs/fmbecstore/wp-load.php';

$cart_id = get_option('woocommerce_cart_page_id');
$checkout_id = get_option('woocommerce_checkout_page_id');
$shop_id = get_option('woocommerce_shop_page_id');

echo "CART ID: {$cart_id}\n";
$cp = get_post($cart_id);
echo "CART CONTENT:\n" . ($cp ? $cp->post_content : 'NULL') . "\n\n";

echo "CHECKOUT ID: {$checkout_id}\n";
$ckp = get_post($checkout_id);
echo "CHECKOUT CONTENT:\n" . ($ckp ? $ckp->post_content : 'NULL') . "\n\n";

echo "SHOP ID: {$shop_id}\n";
$sp = get_post($shop_id);
echo "SHOP CONTENT:\n" . ($sp ? $sp->post_content : 'NULL') . "\n\n";
