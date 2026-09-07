<?php
$f2027 = 'c:/xampp/htdocs/fmbecstore/wp-content/themes/fmb-ecom-store 2027/functions.php';
$content = file_get_contents($f2027);

$funcs = [
    'fmb_force_classic_cart_checkout',
    'fmb_enable_webp_upload_mimes',
    'fmb_custom_checkout_fields',
    'fmb_update_checkout_cart',
    'fmb_get_rich_cart_data',
    'fmb_ajax_live_search'
];

foreach ($funcs as $fn) {
    echo "=== FUNCTION: $fn ===\n";
    $pos = strpos($content, "function $fn");
    if ($pos !== false) {
        echo substr($content, $pos, 600) . "\n...\n\n";
    }
}
