<?php
$file = 'c:/xampp/htdocs/fmbecstore/wp-content/themes/fmb-ecom-store/functions.php';
$content = file_get_contents($file);

$start_needle = "\$hour  = (int) wp_date( 'G', \$ts );";
$end_needle = "add_action( 'woocommerce_checkout_update_order_meta', 'fmb_fix_missing_order_attribution', 99, 1 );";

$p1 = strpos($content, $start_needle);
$p2 = strpos($content, $end_needle);

if ($p1 === false || $p2 === false || $p2 <= $p1) {
    die("Could not find start or end needle!\n");
}

$replacement = <<<'CODE'
$hour  = (int) wp_date( 'G', $ts );

    // প্রতিদিন + প্রোডাক্ট অনুযায়ী শুরুর উচ্চ রেঞ্জ (একই দিনে স্থিতিশীল)।
    $peak = 96 + ( ( $product_id * 2654435761 + $day_z * 1597334677 ) % 165 ); // ~৯৬–২৬০

    // একই দিনে ঘণ্টায় ঘণ্টায় কমতে থাকে।
    $hour_step = 3 + ( $product_id % 4 ); // ৩–৬
    $decline   = $hour * $hour_step;

    // প্রতি ~১৫ মিনিটে ছোট ওঠানামা।
    $bucket15 = (int) floor( $ts / 900 );
    $nibble   = ( $bucket15 + $product_id * 13 ) % 4;

    // ঘণ্টা ভিত্তিক অতিরিক্ত হালকা ট্রেন্ড (বারবার কমার অনুভূতি)।
    $pulse = (int) floor( $ts / HOUR_IN_SECONDS ) % 7;

    $n = $peak - $decline - $nibble - $pulse;

    return max( 79, min( 310, $n ) );
}

/**
 * Fix: Retain customizer settings when theme is updated via zip upload.
 * Copies theme_mods from the previous theme folder if it belongs to fmb-ecom-store.
 */
function fmb_migrate_theme_mods_on_switch($old_name, $old_theme = false) {
    if ( $old_theme ) {
        $old_stylesheet = $old_theme->get_stylesheet();
        if ( strpos($old_stylesheet, 'fmb-ecom-store') !== false ) {
            $old_mods = get_option( 'theme_mods_' . $old_stylesheet );
            if ( ! empty( $old_mods ) ) {
                $current_stylesheet = get_stylesheet();
                $current_mods = get_option( 'theme_mods_' . $current_stylesheet );
                if ( empty( $current_mods ) ) {
                    update_option( 'theme_mods_' . $current_stylesheet, $old_mods );
                }
            }
        }
    }
}
add_action('after_switch_theme', 'fmb_migrate_theme_mods_on_switch', 10, 2);

/**
 * Fix: Prevent WooCommerce Order Attribution showing "Unknown" for Facebook Ads
 * Captures sbjs cookie or FMB traffic session and forces Facebook as source if detected.
 */
function fmb_fix_missing_order_attribution( $order_id ) {
    $order = wc_get_order( $order_id );
    if ( ! $order ) return;
    
    $source_type = $order->get_meta( '_wc_order_attribution_source_type' );
    
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
                    $order->update_meta_data( '_wc_order_attribution_utm_source', $sbjs['src'] );
                    $order->update_meta_data( '_wc_order_attribution_utm_medium', $sbjs['mdm'] ?? '' );
                    $order->update_meta_data( '_wc_order_attribution_utm_campaign', $sbjs['cmp'] ?? '' );
                    $order->save_meta_data();
                }
            }
        }

        if ( $is_fb ) {
            $order->update_meta_data( '_wc_order_attribution_source_type', 'social' );
            $order->update_meta_data( '_wc_order_attribution_utm_source', 'facebook' );
            $order->update_meta_data( '_wc_order_attribution_utm_medium', 'cpc' );
            $order->save_meta_data();
        }
    }
}
add_action( 'woocommerce_checkout_update_order_meta', 'fmb_fix_missing_order_attribution', 99, 1 );
CODE;

$new_content = substr($content, 0, $p1) . $replacement . substr($content, $p2 + strlen($end_needle));
file_put_contents($file, $new_content);
echo "Cleanly replaced and restored functions!\n";
