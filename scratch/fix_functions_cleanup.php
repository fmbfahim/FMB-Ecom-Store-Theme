<?php
$file = 'c:/xampp/htdocs/fmbecstore/wp-content/themes/fmb-ecom-store/functions.php';
$content = file_get_contents($file);

// 1. Fix fmb_fix_missing_order_attribution
$bad_part = <<<'BAD'
    // If WooCommerce didn't capture the source (common with custom AJAX checkouts)
                    $order->update_meta_data( '_wc_order_attribution_source_type', isset($sbjs['typ']) && $sbjs['typ'] === 'typein' ? 'typein' : 'organic' );
BAD;

$good_part = <<<'GOOD'
    // If WooCommerce didn't capture the source (common with custom AJAX checkouts)
    if ( empty( $source_type ) || $source_type === 'unknown' ) {
        $is_fb = false;
        
        // Check FMB Traffic Session
        $fmb_source = isset($_SESSION['TrafficSource']) ? $_SESSION['TrafficSource'] : (isset($_COOKIE['orderflowTrafficSource']) ? $_COOKIE['orderflowTrafficSource'] : '');
        if (strpos($fmb_source, 'facebook') !== false || strpos($fmb_source, 'fb') !== false || strpos($fmb_source, 'instagram') !== false || isset($_GET['fbclid']) || (isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], 'fbclid') !== false)) {
            $is_fb = true;
        }

        // Check Sourcebuster Cookie (sbjs_current)
        if ( isset( $_COOKIE['sbjs_current'] ) ) {
            parse_str( str_replace( '||', '&', $_COOKIE['sbjs_current'] ), $sbjs );
            if ( ! empty( $sbjs['src'] ) ) {
                if (strpos($sbjs['src'], 'facebook') !== false || strpos($sbjs['src'], 'fb') !== false || strpos($sbjs['src'], 'instagram') !== false) {
                    $is_fb = true;
                } else if (!$is_fb) {
                    // Update with whatever sbjs caught if not facebook
                    $order->update_meta_data( '_wc_order_attribution_source_type', isset($sbjs['typ']) && $sbjs['typ'] === 'typein' ? 'typein' : 'organic' );
GOOD;

if (strpos($content, $bad_part) !== false) {
    $content = str_replace($bad_part, $good_part, $content);
    echo "Fixed fmb_fix_missing_order_attribution\n";
} else {
    echo "Bad part not found\n";
}

// 2. Remove the '=='
$bad_equals = "\n==\n\n// 1. Allow .webp";
$good_equals = "\n\n// 1. Allow .webp";
if (strpos($content, $bad_equals) !== false) {
    $content = str_replace($bad_equals, $good_equals, $content);
    echo "Fixed errant ==\n";
} else {
    // try regex for == between separator and webp
    $content = preg_replace('/(\/\/\s*={10,}\s*\n)\s*==\s*\n/', "$1\n", $content);
    echo "Regex tried for ==\n";
}

file_put_contents($file, $content);
echo "Saved functions.php\n";
