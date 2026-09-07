<?php
$file = 'c:/xampp/htdocs/fmbecstore/wp-content/themes/fmb-ecom-store/functions.php';
$content = file_get_contents($file);

$sep = "// ==========================================================================\n// === FEATURES BROUGHT FROM 2027 THEME ===\n// ==========================================================================";

$parts = explode($sep, $content);
$base = trim($parts[0]);

$new_block = <<<'PHP_CODE'

// ==========================================================================
// === FEATURES BROUGHT FROM 2027 THEME ===
// ==========================================================================

// 1. Allow .webp mime type in Media Library uploads
function fmb_enable_webp_upload_mimes($mimes) {
    $mimes['webp'] = 'image/webp';
    return $mimes;
}
add_filter('upload_mimes', 'fmb_enable_webp_upload_mimes');

// 2. Fix file extension & mime type checking for .webp files
function fmb_check_webp_filetype_and_ext($filetype, $file, $filename, $mimes) {
    if (!$filetype['type']) {
        $check_filetype = wp_check_filetype($filename, $mimes);
        if ($check_filetype['ext'] === 'webp') {
            $filetype['ext']  = 'webp';
            $filetype['type'] = 'image/webp';
        }
    }
    return $filetype;
}
add_filter('wp_check_filetype_and_ext', 'fmb_check_webp_filetype_and_ext', 10, 4);

// 3. Ensure WebP images are displayed and previewable in Media Library
function fmb_displayable_image_webp($result, $path) {
    if ($result === true) {
        return true;
    }
    $file_type = wp_check_filetype($path);
    if ($file_type['ext'] === 'webp') {
        return true;
    }
    return $result;
}
add_filter('file_is_displayable_image', 'fmb_displayable_image_webp', 10, 2);

// 4. Force Classic Cart & Checkout Shortcodes for High Conversion Checkout Page
function fmb_force_classic_cart_checkout() {
    if (get_option('fmb_cart_checkout_fixed')) return;

    $cart_id = get_option('woocommerce_cart_page_id');
    if ($cart_id) {
        wp_update_post([
            'ID' => $cart_id,
            'post_content' => '[woocommerce_cart]'
        ]);
    }

    $checkout_id = get_option('woocommerce_checkout_page_id');
    if ($checkout_id) {
        wp_update_post([
            'ID' => $checkout_id,
            'post_content' => '[woocommerce_checkout]'
        ]);
    }

    update_option('fmb_cart_checkout_fixed', '1');
}
add_action('init', 'fmb_force_classic_cart_checkout');

// 5. Checkout Fields Customization (Bangla placeholders, removed clutter)
add_filter( 'woocommerce_checkout_fields' , 'fmb_custom_checkout_fields', 999 );
function fmb_custom_checkout_fields( $fields ) {
    // Unset Billing fields
    unset($fields['billing']['billing_last_name']);
    unset($fields['billing']['billing_company']);
    unset($fields['billing']['billing_country']);
    unset($fields['billing']['billing_address_2']);
    unset($fields['billing']['billing_postcode']);
    unset($fields['billing']['billing_email']);
    
    // Unset Shipping fields
    unset($fields['shipping']['shipping_last_name']);
    unset($fields['shipping']['shipping_company']);
    unset($fields['shipping']['shipping_country']);
    unset($fields['shipping']['shipping_address_2']);
    unset($fields['shipping']['shipping_city']);
    unset($fields['shipping']['shipping_state']);
    unset($fields['shipping']['shipping_postcode']);

    // Rename existing fields and set order (priority)
    if (isset($fields['billing']['billing_first_name'])) {
        $fields['billing']['billing_first_name']['label'] = false;
        $fields['billing']['billing_first_name']['placeholder'] = 'আপনার নাম *';
        $fields['billing']['billing_first_name']['class'] = array('form-row-first');
        $fields['billing']['billing_first_name']['priority'] = 10;
    }
    
    if (isset($fields['billing']['billing_phone'])) {
        $fields['billing']['billing_phone']['label'] = false;
        $fields['billing']['billing_phone']['placeholder'] = 'আপনার মোবাইল নম্বর *';
        $fields['billing']['billing_phone']['class'] = array('form-row-last');
        $fields['billing']['billing_phone']['priority'] = 20;
        $fields['billing']['billing_phone']['required'] = true;
    }
    
    if (isset($fields['billing']['billing_address_1'])) {
        $fields['billing']['billing_address_1']['label'] = false;
        $fields['billing']['billing_address_1']['placeholder'] = 'জেলা, থানা, বাড়ি/ফ্ল্যাট নম্বর, রোড, এলাকা *';
        $fields['billing']['billing_address_1']['class'] = array('form-row-wide');
        $fields['billing']['billing_address_1']['priority'] = 30;
    }

    if (isset($fields['billing']['billing_state'])) {
        $fields['billing']['billing_state']['label'] = false;
        $fields['billing']['billing_state']['placeholder'] = 'জেলা সিলেক্ট করুন';
        $fields['billing']['billing_state']['class'] = array('form-row-first');
        $fields['billing']['billing_state']['priority'] = 40;
        $fields['billing']['billing_state']['required'] = false;
    }

    if (isset($fields['billing']['billing_city'])) {
        $fields['billing']['billing_city']['label'] = false;
        $fields['billing']['billing_city']['placeholder'] = 'থানা সিলেক্ট করুন (ঐচ্ছিক)';
        $fields['billing']['billing_city']['class'] = array('form-row-last');
        $fields['billing']['billing_city']['priority'] = 50;
        $fields['billing']['billing_city']['required'] = false;
    }

    if (isset($fields['order']['order_comments'])) {
        $fields['order']['order_comments']['label'] = '<span style="color:#5750d7;font-weight:800;border-left:3px solid #5750d7;padding-left:10px;">বিশেষ নির্দেশনা</span>';
        $fields['order']['order_comments']['placeholder'] = '';
        $fields['order']['order_comments']['class'] = array('form-row-wide');
    }
    
    return $fields;
}

// 6. Non-mandatory BD address defaults
add_filter( 'woocommerce_default_address_fields', 'fmb_custom_default_address_fields', 999 );
function fmb_custom_default_address_fields( $fields ) {
    if (isset($fields['state'])) {
        $fields['state']['required'] = false;
    }
    if (isset($fields['city'])) {
        $fields['city']['required'] = false;
    }
    return $fields;
}

// 7. Remove billing details heading
add_filter( 'woocommerce_checkout_before_customer_details', 'fmb_remove_billing_details_heading' );
function fmb_remove_billing_details_heading() {
    echo '<style>.woocommerce-billing-fields h3 { display: none; } .woocommerce-additional-fields h3 { display: none; }</style>';
}

// 8. Checkout page interactive quantity stepper AJAX
add_action('wp_ajax_fmb_update_checkout_cart', 'fmb_update_checkout_cart');
add_action('wp_ajax_nopriv_fmb_update_checkout_cart', 'fmb_update_checkout_cart');
function fmb_update_checkout_cart() {
    if (isset($_POST['cart_item_key']) && isset($_POST['qty'])) {
        $cart_item_key = sanitize_text_field($_POST['cart_item_key']);
        $qty = intval($_POST['qty']);
        if ($qty > 0) {
            WC()->cart->set_quantity($cart_item_key, $qty);
        } else {
            WC()->cart->remove_cart_item($cart_item_key);
        }
        wp_send_json_success();
    }
    wp_send_json_error();
}

// 9. Floating Rich Cart Bar Data AJAX
add_action('wp_ajax_fmb_get_rich_cart_data', 'fmb_get_rich_cart_data');
add_action('wp_ajax_nopriv_fmb_get_rich_cart_data', 'fmb_get_rich_cart_data');
function fmb_get_rich_cart_data() {
    WC()->cart->calculate_totals();
    
    $items = array();
    $total_savings = 0;
    $item_count = WC()->cart->get_cart_contents_count();
    $total = WC()->cart->get_cart_contents_total(); // Without tax/shipping
    
    foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
        $_product = apply_filters('woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key);
        if ($_product && $_product->exists() && $cart_item['quantity'] > 0) {
            $reg_price = floatval($_product->get_regular_price());
            $sale_price = floatval($_product->get_sale_price());
            if ($reg_price && $sale_price && $reg_price > $sale_price) {
                $total_savings += ($reg_price - $sale_price) * $cart_item['quantity'];
            }
            
            $img = wp_get_attachment_image_url($_product->get_image_id(), 'thumbnail');
            if (!$img) $img = wc_placeholder_img_src();
            
            $items[] = array(
                'key' => $cart_item_key,
                'name' => $_product->get_name(),
                'qty' => $cart_item['quantity'],
                'price' => wc_price($_product->get_price()),
                'img' => $img
            );
        }
    }
    
    wp_send_json_success(array(
        'count' => $item_count,
        'total' => $total,
        'savings' => $total_savings,
        'items' => $items
    ));
}

// 10. Live Search Header AJAX Handler
function fmb_ajax_live_search() {
    $term = isset( $_REQUEST['term'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['term'] ) ) : '';

    if ( empty( $term ) || mb_strlen( $term ) < 2 ) {
        wp_send_json_success( array( 'products' => array() ) );
    }

    $args = array(
        'post_type'      => 'product',
        'post_status'    => 'publish',
        'posts_per_page' => 6,
        's'              => $term,
    );

    $query = new WP_Query( $args );
    $results = array();

    if ( $query->have_posts() ) {
        while ( $query->have_posts() ) {
            $query->the_post();
            global $product;
            if ( ! $product ) continue;

            $img_id  = $product->get_image_id();
            $img_url = $img_id ? wp_get_attachment_image_url( $img_id, 'thumbnail' ) : wc_placeholder_img_src();

            $results[] = array(
                'id'         => $product->get_id(),
                'title'      => get_the_title(),
                'price_html' => $product->get_price_html(),
                'price'      => $product->get_price(),
                'thumbnail'  => $img_url,
                'url'        => get_permalink(),
            );
        }
        wp_reset_postdata();
    }

    wp_send_json_success( array( 'products' => $results ) );
}
add_action( 'wp_ajax_fmb_live_search', 'fmb_ajax_live_search' );
add_action( 'wp_ajax_nopriv_fmb_live_search', 'fmb_ajax_live_search' );
PHP_CODE;

file_put_contents($file, $base . "\n" . $new_block . "\n");
echo "Successfully written clean unique features to functions.php!\n";
