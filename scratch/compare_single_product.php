<?php
$f2027 = 'c:/xampp/htdocs/fmbecstore/wp-content/themes/fmb-ecom-store 2027/woocommerce/single-product.php';
$fTarget = 'c:/xampp/htdocs/fmbecstore/wp-content/themes/fmb-ecom-store/woocommerce/single-product.php';

$c2027 = file_get_contents($f2027);
$cTarget = file_get_contents($fTarget);

echo "2027 lines: " . count(explode("\n", $c2027)) . "\n";
echo "Target lines: " . count(explode("\n", $cTarget)) . "\n";

// Target has template loader for multiple layouts:
if (strpos($cTarget, 'fmb_get_single_product_template') !== false || strpos($cTarget, 'single-product-templates') !== false) {
    echo "Target has custom single product template loader: YES\n";
} else {
    echo "Target has custom single product template loader: NO\n";
}

if (strpos($c2027, 'single-product-templates') !== false) {
    echo "2027 has custom single product template loader: YES\n";
} else {
    echo "2027 has custom single product template loader: NO\n";
}

// Let's check what 2027 has that Target doesn't have in single-product.php
// Check features in 2027
$checks = [
    'fmb_single_checkout_ajax',
    'fmb_direct_checkout',
    'fmb_product_video',
    'fmb_delivery_guarantee',
    'fmb_stock_scarcity',
    'fmb_visitor_counter',
    'fmb_sales_countdown',
    'fmb_size_chart_modal',
    'fmb_product_faqs',
    'fmb_sticky_mobile_bar',
    'fmb_shipping_accordion'
];

foreach ($checks as $chk) {
    echo "Check '$chk': 2027=" . (strpos($c2027, $chk) !== false ? 'YES' : 'no') . ", Target=" . (strpos($cTarget, $chk) !== false ? 'YES' : 'no') . "\n";
}
