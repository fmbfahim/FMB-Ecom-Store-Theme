<?php
require_once '../../../wp-load.php';

$cart_page_id = get_option('woocommerce_cart_page_id');
$checkout_page_id = get_option('woocommerce_checkout_page_id');

if ($cart_page_id) {
    wp_update_post([
        'ID' => $cart_page_id,
        'post_content' => '[woocommerce_cart]'
    ]);
    echo "Cart updated!\n";
}

if ($checkout_page_id) {
    wp_update_post([
        'ID' => $checkout_page_id,
        'post_content' => '[woocommerce_checkout]'
    ]);
    echo "Checkout updated!\n";
}
