<?php
// Theme Version & System Requirements
if (!defined('FMB_THEME_VERSION')) {
    define('FMB_THEME_VERSION', '1.3.4');
}
if (!defined('FMB_MIN_PHP_VERSION')) {
    define('FMB_MIN_PHP_VERSION', '7.4');
}

// PHP Version Notice Check
if (version_compare(PHP_VERSION, FMB_MIN_PHP_VERSION, '<')) {
    add_action('admin_notices', function() {
        printf(
            '<div class="error"><p>%s</p></div>',
            sprintf(__('FMB E-Com Store theme requires PHP version %s or higher. Your server is currently running PHP %s.', 'fmb-store'), FMB_MIN_PHP_VERSION, PHP_VERSION)
        );
    });
}

// Fix client IP for Cloudflare / Proxy (Solves Meta Conversions API IP Mismatch)
if (isset($_SERVER["HTTP_CF_CONNECTING_IP"])) {
    $_SERVER['REMOTE_ADDR'] = sanitize_text_field($_SERVER["HTTP_CF_CONNECTING_IP"]);
} elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    $ip_list = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
    $first_ip = trim($ip_list[0]);
    if (filter_var($first_ip, FILTER_VALIDATE_IP)) {
        $_SERVER['REMOTE_ADDR'] = $first_ip;
    }
}

require_once get_template_directory() . '/inc/customizer.php';
require_once get_template_directory() . '/inc/product-meta.php';
require_once get_template_directory() . '/inc/admin-panel.php';
require_once get_template_directory() . '/inc/combo-manager.php';
require_once get_template_directory() . '/inc/sales-funnel-manager.php';
require_once get_template_directory() . '/inc/live-orders.php';
require_once get_template_directory() . '/inc/admin-order-ui.php';
require_once get_template_directory() . '/inc/visitor-tracker.php';
require_once get_template_directory() . '/inc/visitor-tracker-admin.php';
require_once get_template_directory() . '/inc/employee-manager.php';
require_once get_template_directory() . '/inc/employee-admin.php';
require_once get_template_directory() . '/inc/ai-product-generator.php';
require_once get_template_directory() . '/inc/fmb-engine/init.php';
require_once get_template_directory() . '/inc/class-door-admin.php';
require_once get_template_directory() . '/inc/admin-pages/courier-dashboard.php';
require_once get_template_directory() . '/inc/purchase-stock-db.php';
require_once get_template_directory() . '/inc/admin-pages/purchase-stock.php';
require_once get_template_directory() . '/inc/admin-pages/expenses.php';
require_once get_template_directory() . '/inc/theme-setup-wizard.php';

/**
 * Note: Theme setup process is now handled by the Theme Setup Wizard 
 * defined in inc/theme-setup-wizard.php.
 */


/**
 * FMB Theme Mods Persistence
 * Prevents losing customizer settings (Logo, Colors, Info) when updating the theme via a new folder name (e.g. 1.2 to 1.3).
 */
add_action('after_setup_theme', 'fmb_restore_theme_mods_on_update');
function fmb_restore_theme_mods_on_update() {
    $current_slug = get_option('stylesheet');
    $current_mods_key = 'theme_mods_' . $current_slug;
    $current_mods = get_option($current_mods_key);
    
    // If current mods are basically empty (fresh install of new version)
    if ( empty($current_mods) || (is_array($current_mods) && count($current_mods) <= 1) ) {
        global $wpdb;
        // Search for any previous FMB theme mods
        $old_mods = $wpdb->get_results($wpdb->prepare(
            "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s AND option_name != %s ORDER BY option_id DESC LIMIT 1",
            'theme_mods_fmb-ecom-store%',
            $current_mods_key
        ));
        
        if ( !empty($old_mods) ) {
            $latest_old_mods_value = maybe_unserialize($old_mods[0]->option_value);
            if ( is_array($latest_old_mods_value) ) {
                update_option($current_mods_key, $latest_old_mods_value);
            }
        }
    }
}



function fmb_setup() {
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('woocommerce');
    register_nav_menus(array('primary' => __('Main Menu', 'fmb-store')));
}
add_action('after_setup_theme', 'fmb_setup');

// Register Contact Messages CPT (Hidden from main UI, accessed via our custom panel)
function fmb_register_contact_cpt() {
    register_post_type('fmb_contact_msg', array(
        'labels' => array(
            'name'          => 'Contact Messages',
            'singular_name' => 'Contact Message',
        ),
        'public'             => false,
        'publicly_queryable' => false,
        'show_ui'            => false, // We will show it in our custom admin page
        'show_in_menu'       => false,
        'query_var'          => false,
        'rewrite'            => false,
        'capability_type'    => 'post',
        'has_archive'        => false,
        'hierarchical'       => false,
        'supports'           => array('title', 'editor', 'custom-fields'),
    ));
}
add_action('init', 'fmb_register_contact_cpt');


// ১. ডাইনামিক Google Fonts লোড করা
function fmb_enqueue_google_fonts() {
    $font = get_theme_mod('fmb_font_family', 'Inter');
    $font_url = '';

    // ফন্ট সিলেক্ট অনুযায়ী URL সেট করা
    switch ($font) {
        case 'Roboto':
            $font_url = 'https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap';
            break;
        case 'Poppins':
            $font_url = 'https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap';
            break;
        case 'Lato':
            $font_url = 'https://fonts.googleapis.com/css2?family=Lato:wght@300;400;700&display=swap';
            break;
        case 'Hind Siliguri': // বাংলার জন্য সেরা
            $font_url = 'https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@300;400;500;600;700&display=swap';
            break;
        default: // Inter (Default)
            $font_url = 'https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap';
    }

    wp_enqueue_style('fmb-google-font', $font_url, array(), null);
}
add_action('wp_enqueue_scripts', 'fmb_enqueue_google_fonts');


// ২. স্ক্রিপ্ট এবং Tailwind কনফিগ
function fmb_scripts() {
    // Tailwind CDN
    wp_enqueue_script('tailwindcss', 'https://cdn.tailwindcss.com', array(), '3.3.0', false);
    
    // Alpine JS
    wp_enqueue_script('alpinejs', 'https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js', array(), '3.0', false);
    
    // Tailwind Configuration (Design System Integration)
    wp_add_inline_script('tailwindcss', "
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: 'var(--primary)',     // ডাইনামিক প্রাইমারি
                        secondary: 'var(--secondary)', // ডাইনামিক সেকেন্ডারি
                        dark: '#111827',
                    },
                    fontFamily: {
                        sans: ['var(--font-main)', 'sans-serif'], // ডাইনামিক ফন্ট
                    },
                    borderRadius: {
                        DEFAULT: 'var(--radius)',      // ডাইনামিক রেডিয়াস
                        'lg': 'calc(var(--radius) + 2px)',
                        'xl': 'calc(var(--radius) + 4px)',
                    }
                }
            }
        }
    ");

    wp_enqueue_style('fmb-style', get_stylesheet_uri());
    wp_enqueue_style('fmb-main-css', get_template_directory_uri() . '/assets/css/main.css', array(), fmb_theme_asset_ver('assets/css/main.css'));
    $theme_version = wp_get_theme()->get('Version');
    wp_enqueue_script('fmb-script', get_template_directory_uri() . '/assets/js/scripts.js', array('jquery'), $theme_version, true);
    wp_localize_script('fmb-script', 'fmb_vars', array('ajax_url' => admin_url('admin-ajax.php')));
    
    // Visitor Tracker JS
    wp_enqueue_script('fmb-tracker', get_template_directory_uri() . '/assets/js/tracker.js', array('jquery'), $theme_version, true);
    wp_localize_script('fmb-tracker', 'fmb_tracker_vars', array('ajax_url' => admin_url('admin-ajax.php')));
}
add_action('wp_enqueue_scripts', 'fmb_scripts');

// ফুটার উইজেট রেজিস্টার
function fmb_widgets_init() {
    register_sidebar( array(
        'name'          => __( 'Footer Column 1', 'fmb-store' ),
        'id'            => 'footer-1',
        'description'   => __( 'Add widgets here.', 'fmb-store' ),
        'before_widget' => '<section id="%1$s" class="widget %2$s mb-6">',
        'after_widget'  => '</section>',
        'before_title'  => '<h2 class="widget-title text-lg font-bold text-white mb-4">',
        'after_title'   => '</h2>',
    ) );

    register_sidebar( array(
        'name'          => __( 'Footer Column 2', 'fmb-store' ),
        'id'            => 'footer-2',
        'before_widget' => '<section id="%1$s" class="widget %2$s mb-6">',
        'after_widget'  => '</section>',
        'before_title'  => '<h2 class="widget-title text-lg font-bold text-white mb-4">',
        'after_title'   => '</h2>',
    ) );

    register_sidebar( array(
        'name'          => __( 'Footer Column 3', 'fmb-store' ),
        'id'            => 'footer-3',
        'before_widget' => '<section id="%1$s" class="widget %2$s mb-6">',
        'after_widget'  => '</section>',
        'before_title'  => '<h2 class="widget-title text-lg font-bold text-white mb-4">',
        'after_title'   => '</h2>',
    ) );
}
add_action( 'widgets_init', 'fmb_widgets_init' );

/**
 * Stable asset versions from filemtime — avoids busting browser/CDN cache every request (uses time()).
 */
function fmb_theme_asset_ver( $relative_path ) {
    $path = get_template_directory() . '/' . ltrim( $relative_path, '/' );
    return is_readable( $path ) ? (string) filemtime( $path ) : (string) wp_get_theme()->get( 'Version' );
}

/**
 * One WooCommerce shipping zone scan per request; reused by banners + checkout + footer popup.
 *
 * @return array{methods:array<int,array{cost:float,label:string}>,has:bool,max_cost:float|null}
 */
function fmb_wc_cached_zone_delivery_rates() {
    static $cached = null;
    if ( null !== $cached ) {
        return $cached;
    }
    $methods_out = array();
    if ( ! class_exists( 'WC_Shipping_Zone' ) ) {
        $cached = array(
            'methods'  => array(),
            'has'      => false,
            'max_cost' => null,
        );
        return $cached;
    }
    $zones = WC_Shipping_Zones::get_zones();
    $zones[0] = new WC_Shipping_Zone( 0 );
    foreach ( $zones as $zone_id => $zone_data ) {
        $zone = ( 0 === $zone_id ) ? $zone_data : new WC_Shipping_Zone( $zone_id );
        foreach ( $zone->get_shipping_methods( true ) as $method ) {
            if ( 'yes' !== $method->enabled ) {
                continue;
            }
            $cost_raw = isset( $method->cost ) ? $method->cost : ( isset( $method->instance_settings['cost'] ) ? $method->instance_settings['cost'] : 0 );
            $cost     = floatval( $cost_raw );
            $label    = $method->get_title();
            if ( '' === (string) $label ) {
                $label = $zone->get_zone_name();
            }
            $methods_out[] = array(
                'cost'  => $cost,
                'label' => $label,
            );
        }
    }
    $has = ! empty( $methods_out );
    $mx  = $has ? max( array_column( $methods_out, 'cost' ) ) : null;
    $cached = array(
        'methods'  => $methods_out,
        'has'      => $has,
        'max_cost' => $mx,
    );
    return $cached;
}

/** ফ্রি ডেলিভারি ব্যানার লজিক (পণ্যে কাস্টম ০ চার্জ অথবা সব এনেবলড শিপিং মেথডের ম্যাক্স কস্ট ০)। */
function fmb_is_effectively_free_delivery_catalog( $product_id ) {
    $product_id = absint( $product_id );
    $charges    = get_option( 'cdc_product_delivery_charges', array() );
    if ( isset( $charges[ $product_id ] ) && floatval( $charges[ $product_id ] ) === 0.0 ) {
        return true;
    }
    $rates = fmb_wc_cached_zone_delivery_rates();
    return $rates['has'] && floatval( $rates['max_cost'] ) === 0.0;
}

// Global Phone Normalization Helper
if (!function_exists('fmb_clean_and_normalize_phone')) {
    function fmb_clean_and_normalize_phone($phone) {
        $bn_digits = ['০', '১', '২', '৩', '৪', '৫', '৬', '৭', '৮', '৯'];
        $en_digits = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        $phone = str_replace($bn_digits, $en_digits, (string)$phone);

        // Remove all non-digits: spaces, dashes (-), plus (+), brackets, dots, etc.
        $digits = preg_replace('/[^\d]/', '', $phone);

        // Strip international prefixes
        if (strpos($digits, '0088') === 0) {
            $digits = substr($digits, 4);
        } elseif (strpos($digits, '88') === 0) {
            $digits = substr($digits, 2);
        }

        // If 10 digits starting with 1 (e.g. 1712345678), add leading 0
        if (strlen($digits) === 10 && strpos($digits, '1') === 0) {
            $digits = '0' . $digits;
        }

        return $digits;
    }
}

/**
 * Resolves Client IP Address with IPv6 Priority for Meta Conversions API & Pixel Matching
 */
if (!function_exists('fmb_get_client_ip_address')) {
    function fmb_get_client_ip_address($prioritize_ipv6 = true) {
        $headers = array(
            'HTTP_CF_CONNECTING_IP', // Cloudflare
            'HTTP_TRUE_CLIENT_IP',   // Akamai / Enterprise Cloudflare
            'HTTP_X_REAL_IP',        // Nginx reverse proxy
            'HTTP_X_FORWARDED_FOR',  // Standard proxy chain
            'HTTP_CLIENT_IP',
            'REMOTE_ADDR'
        );

        $found_ipv4 = null;
        $found_ipv6 = null;

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ips = explode(',', $_SERVER[$header]);
                foreach ($ips as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                            $found_ipv6 = $ip;
                            break 2;
                        } elseif (!$found_ipv6) {
                            $found_ipv6 = $ip;
                        }
                    } elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                            if (!$found_ipv4) $found_ipv4 = $ip;
                        } elseif (!$found_ipv4) {
                            $found_ipv4 = $ip;
                        }
                    }
                }
            }
        }

        if ($prioritize_ipv6 && !empty($found_ipv6)) {
            return $found_ipv6;
        }
        if (!empty($found_ipv4)) {
            return $found_ipv4;
        }
        if (!empty($found_ipv6)) {
            return $found_ipv6;
        }
        return isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field($_SERVER['REMOTE_ADDR']) : '127.0.0.1';
    }
}

/**
 * Normalizes customer data & enriches WooCommerce Order for Maximum Meta Event Match Quality (EMQ)
 */
if (!function_exists('fmb_enrich_order_customer_data')) {
    function fmb_enrich_order_customer_data($order, $raw_name, $raw_phone, $address_text, $email = '', $form_data = array()) {
        if (!$order) return;

        // 1. Name splitting (first_name and last_name)
        $raw_name = trim((string)$raw_name);
        $name_parts = array_filter(explode(' ', $raw_name));
        $first_name = !empty($name_parts) ? array_shift($name_parts) : '';
        $last_name  = !empty($name_parts) ? implode(' ', $name_parts) : $first_name;

        // 2. Phone normalization
        $phone = fmb_clean_and_normalize_phone($raw_phone);

        // 3. Email resolution
        $email = sanitize_email($email);
        if (empty($email) && is_user_logged_in()) {
            $email = wp_get_current_user()->user_email;
        }

        // 4. City & State detection from address
        $city = 'Dhaka';
        $state = 'Dhaka';
        $postcode = '1200';
        $addr_lower = strtolower((string)$address_text);
        if (strpos($addr_lower, 'chittagong') !== false || strpos($address_text, 'চট্টগ্রাম') !== false) {
            $city = 'Chittagong'; $state = 'Chittagong'; $postcode = '4000';
        } elseif (strpos($addr_lower, 'sylhet') !== false || strpos($address_text, 'সিলেট') !== false) {
            $city = 'Sylhet'; $state = 'Sylhet'; $postcode = '3100';
        } elseif (strpos($addr_lower, 'rajshahi') !== false || strpos($address_text, 'রাজশাহী') !== false) {
            $city = 'Rajshahi'; $state = 'Rajshahi'; $postcode = '6000';
        } elseif (strpos($addr_lower, 'khulna') !== false || strpos($address_text, 'খুলনা') !== false) {
            $city = 'Khulna'; $state = 'Khulna'; $postcode = '9000';
        } elseif (strpos($addr_lower, 'barishal') !== false || strpos($address_text, 'বরিশাল') !== false) {
            $city = 'Barishal'; $state = 'Barishal'; $postcode = '8200';
        } elseif (strpos($addr_lower, 'rangpur') !== false || strpos($address_text, 'রংপুর') !== false) {
            $city = 'Rangpur'; $state = 'Rangpur'; $postcode = '5400';
        } elseif (strpos($addr_lower, 'mymensingh') !== false || strpos($address_text, 'ময়মনসিংহ') !== false) {
            $city = 'Mymensingh'; $state = 'Mymensingh'; $postcode = '2200';
        } elseif (strpos($addr_lower, 'cumilla') !== false || strpos($addr_lower, 'comilla') !== false || strpos($address_text, 'কুমিল্লা') !== false) {
            $city = 'Cumilla'; $state = 'Chittagong'; $postcode = '3500';
        } elseif (strpos($addr_lower, 'gazipur') !== false || strpos($address_text, 'গাজীপুর') !== false) {
            $city = 'Gazipur'; $state = 'Dhaka'; $postcode = '1700';
        } elseif (strpos($addr_lower, 'narayanganj') !== false || strpos($address_text, 'নারায়ণগঞ্জ') !== false) {
            $city = 'Narayanganj'; $state = 'Dhaka'; $postcode = '1400';
        }

        $address = array(
            'first_name' => $first_name,
            'last_name'  => $last_name,
            'phone'      => $phone,
            'email'      => $email,
            'address_1'  => $address_text,
            'city'       => $city,
            'state'      => $state,
            'postcode'   => $postcode,
            'country'    => 'BD'
        );

        $order->set_address($address, 'billing');
        $order->set_address($address, 'shipping');

        // 5. Client IP with IPv6 priority
        $client_ip = fmb_get_client_ip_address(true);
        $order->set_customer_ip_address($client_ip);
        $order->set_customer_user_agent(isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : '');

        // 6. Meta Cookies & External ID
        $fbp = !empty($_COOKIE['_fbp']) ? sanitize_text_field($_COOKIE['_fbp']) : (isset($form_data['_fbp']) ? sanitize_text_field($form_data['_fbp']) : '');
        $fbc = !empty($_COOKIE['_fbc']) ? sanitize_text_field($_COOKIE['_fbc']) : (isset($form_data['_fbc']) ? sanitize_text_field($form_data['_fbc']) : '');
        $ext_id = !empty($_COOKIE['_fmb_external_id']) ? sanitize_text_field($_COOKIE['_fmb_external_id']) : (!empty($_COOKIE['orderflow_id']) ? sanitize_text_field($_COOKIE['orderflow_id']) : '');
        if (!$ext_id && !empty($phone)) {
            $ext_id = hash('sha256', 'fmb_' . $phone);
        }

        if ($fbp) {
            $order->update_meta_data('_fbp', $fbp);
            $order->update_meta_data('_customer_fbp', $fbp);
        }
        if ($fbc) {
            $order->update_meta_data('_fbc', $fbc);
            $order->update_meta_data('_customer_fbc', $fbc);
        }
        if ($ext_id) {
            $order->update_meta_data('external_id', $ext_id);
            $order->update_meta_data('_ads_device_hash', $ext_id);
        }
        $order->save();
    }
}

add_filter(
    'script_loader_tag',
    function ( $tag, $handle ) {
        if ( 'alpinejs' === $handle && false === strpos( $tag, 'defer' ) ) {
            return str_replace( '<script ', '<script defer ', $tag );
        }
        return $tag;
    },
    10,
    2
);


/**
 * Fast order status updater: Decouples blocking transactional emails to Action Scheduler
 * to ensure ultra-fast AJAX checkout responses (< 0.2s instead of 4+s delay).
 */
function fmb_fast_update_order_status($order, $status = 'processing', $note = '') {
    if (!$order instanceof WC_Order) {
        $order = wc_get_order($order);
    }
    if (!$order) {
        return false;
    }

    $mailer = WC()->mailer();
    $emails = $mailer ? $mailer->get_emails() : array();

    $current_status = $order->get_status();
    $from = $current_status ? $current_status : 'pending';
    $to   = $status;

    $hooks_to_unhook = array(
        "woocommerce_order_status_{$from}_to_{$to}_notification",
        "woocommerce_order_status_pending_to_{$to}_notification",
        "woocommerce_order_status_failed_to_{$to}_notification",
        "woocommerce_order_status_{$to}_notification",
    );

    // Unhook blocking emails during checkout status update
    foreach ($emails as $email_key => $email_obj) {
        if (is_object($email_obj) && method_exists($email_obj, 'trigger')) {
            foreach ($hooks_to_unhook as $hook) {
                remove_action($hook, array($email_obj, 'trigger'));
            }
        }
    }

    $result = $order->update_status($status, $note);

    // Queue asynchronous background email dispatch via Action Scheduler
    $order_id = $order->get_id();
    if (function_exists('as_enqueue_async_action')) {
        as_enqueue_async_action('fmb_send_async_order_emails', array($order_id), 'fmb-order-emails');
    } else {
        wp_schedule_single_event(time(), 'fmb_send_async_order_emails', array($order_id));
    }

    return $result;
}

/**
 * Background action handler for asynchronous order transactional emails
 */
function fmb_handle_async_order_emails($order_id) {
    if (!$order_id) return;
    $order = wc_get_order($order_id);
    if (!$order) return;

    $mailer = WC()->mailer();
    if (!$mailer) return;
    $emails = $mailer->get_emails();

    // 1. Admin notification email
    if (!empty($emails['WC_Email_New_Order']) && $emails['WC_Email_New_Order']->is_enabled()) {
        $emails['WC_Email_New_Order']->trigger($order_id, $order);
    }

    // 2. Customer processing email (if customer email provided)
    if (!empty($emails['WC_Email_Customer_Processing_Order']) && $emails['WC_Email_Customer_Processing_Order']->is_enabled()) {
        if ($order->get_billing_email()) {
            $emails['WC_Email_Customer_Processing_Order']->trigger($order_id, $order);
        }
    }
}
add_action('fmb_send_async_order_emails', 'fmb_handle_async_order_emails');

// Also defer core WooCommerce transactional emails to Action Scheduler site-wide
add_filter('woocommerce_defer_transactional_emails', '__return_true');



// functions.php এর fmb_process_order ফাংশনটি আপডেট করুন:

function fmb_process_order() {
    // 1. Nonce Verification
    parse_str($_POST['data'], $form_data);
    
    if (!isset($form_data['fmb_nonce']) || !wp_verify_nonce($form_data['fmb_nonce'], 'fmb_quick_order_nonce')) {
        wp_send_json_error(array('message' => 'Security Error: Invalid Nonce'));
    }
    
    // 2. Data Parsing & Validation (Popup Form)
    $billing_first_name = sanitize_text_field($form_data['popup_billing_first_name'] ?? '');
    $billing_phone      = fmb_clean_and_normalize_phone($form_data['popup_billing_phone'] ?? '');
    $billing_address_1  = sanitize_textarea_field($form_data['popup_billing_address_1'] ?? '');
    $billing_email      = sanitize_email($form_data['popup_billing_email'] ?? ($form_data['landing_billing_email'] ?? ''));
    
    // Prepare data for validation hooks
    $product_id = intval($form_data['product_id']);
    $shipping_cost = isset($form_data['shipping_cost']) ? max(0, floatval($form_data['shipping_cost'])) : 0; 

    $order = wc_create_order();
    $order->add_product(wc_get_product($product_id), 1);
    
    // Enrich order with rich customer match data (IPv6, Names, City, Country, Cookies)
    fmb_enrich_order_customer_data($order, $billing_first_name, $billing_phone, $billing_address_1, $billing_email, $form_data);
    
    // ডেলিভারি চার্জ যোগ করা
    $item = new WC_Order_Item_Shipping();
    $item->set_method_title('Delivery Charge');
    $item->set_total($shipping_cost);
    $order->add_item($item);

    $order->set_payment_method('cod');
    $order->calculate_totals();
    fmb_fast_update_order_status($order, 'processing', 'Landing Page Order');
    
    wp_send_json_success(array(
        'redirect_url' => $order->get_checkout_order_received_url()
    ));
}
add_action('wp_ajax_fmb_quick_order', 'fmb_process_order');
add_action('wp_ajax_nopriv_fmb_quick_order', 'fmb_process_order');


// ৪. WooCommerce ফর্ম ফিল্ড স্টাইল (Tailwind CSS)
add_filter('woocommerce_form_field_args', 'fmb_wc_form_field_args', 10, 3);
function fmb_wc_form_field_args($args, $key, $value) {
    // Ensure 'class' and 'input_class' are arrays
    $args['class'] = isset($args['class']) && is_array($args['class']) ? $args['class'] : array();
    $args['input_class'] = isset($args['input_class']) && is_array($args['input_class']) ? $args['input_class'] : array();

    // Wrapper classes
    $args['class'][] = 'mb-4';

    // Input classes - explode the string to avoid sanitation issues
    $input_classes = explode(' ', 'w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent transition shadow-sm');
    $args['input_class'] = array_merge($args['input_class'], $input_classes);
    
    // Label classes
    $args['label_class'] = isset($args['label_class']) && is_array($args['label_class']) ? $args['label_class'] : array();
    $args['label_class'] = array_merge($args['label_class'], explode(' ', 'block text-sm font-bold text-gray-700 mb-2'));
    
    return $args;
}


// মাল্টি প্রডাক্ট অর্ডার হ্যান্ডলার
add_action('wp_ajax_fmb_multi_product_order', 'fmb_process_multi_order');
add_action('wp_ajax_nopriv_fmb_multi_product_order', 'fmb_process_multi_order');
function fmb_process_multi_order() {
    parse_str($_POST['data'], $form_data);
    // 1. Nonce Verification
    if (!isset($form_data['fmb_multi_nonce']) || !wp_verify_nonce($form_data['fmb_multi_nonce'], 'fmb_multi_product_order_nonce')) {
        wp_send_json_error(array('message' => 'Security Error: Invalid Nonce'));
    }
    
    // JSON ডিকোড করা (নিরাপদভাবে)
    $items = json_decode(stripslashes($form_data['order_items'] ?? '[]'), true);
    $shipping_cost = isset($form_data['delivery_area']) ? max(0, floatval($form_data['delivery_area'])) : 0;
    if (empty($items)) {
        wp_send_json_error(['message' => 'Cart is empty']);
    }
    // 2. Data Parsing & Validation
    $billing_first_name = !empty($form_data['popup_billing_first_name']) ? sanitize_text_field($form_data['popup_billing_first_name']) : sanitize_text_field($form_data['landing_billing_first_name'] ?? '');
    $raw_phone          = !empty($form_data['popup_billing_phone']) ? sanitize_text_field($form_data['popup_billing_phone']) : sanitize_text_field($form_data['landing_billing_phone'] ?? '');
    $billing_phone      = fmb_clean_and_normalize_phone($raw_phone);
    $billing_address_1  = !empty($form_data['popup_billing_address_1']) ? sanitize_textarea_field($form_data['popup_billing_address_1']) : sanitize_textarea_field($form_data['landing_billing_address_1'] ?? '');
    $billing_email      = sanitize_email($form_data['popup_billing_email'] ?? ($form_data['landing_billing_email'] ?? ''));
    
    $data = array(
        'billing_first_name' => $billing_first_name,
        'billing_phone'      => $billing_phone,
        'billing_email'      => $billing_email, 
        'billing_address_1'  => $billing_address_1,
        'billing_country'    => 'BD', 
        'shipping_country'   => 'BD',
    );
    
    // Transient Lock to prevent double-click race conditions
    $lock_key = '';
    if (!empty($billing_phone)) {
        $lock_key = 'fmb_order_lock_' . md5($billing_phone);
        if (get_transient($lock_key)) {
            wp_send_json_error(array('message' => 'দয়া করে একটু অপেক্ষা করুন, আপনার আগের অর্ডারটি প্রসেস হচ্ছে।'));
        }
        set_transient($lock_key, true, 10); // 10 seconds lock

        // Prevent duplicate orders for the SAME product within 1 minute
        $recent_orders = wc_get_orders(array(
            'billing_phone' => $billing_phone,
            'date_created'  => '>=' . (time() - 60),
            'limit'         => 5,
        ));
        
        if (!empty($recent_orders)) {
            $current_product_ids = array_map(function($item) { return intval($item['id']); }, $items);
            foreach ($recent_orders as $recent_order) {
                foreach ($recent_order->get_items() as $order_item) {
                    if (in_array($order_item->get_product_id(), $current_product_ids)) {
                        delete_transient($lock_key);
                        wp_send_json_error(array('message' => 'আপনি ইতিমধ্যে এই প্রডাক্টটি অর্ডার করেছেন। অনুগ্রহ করে ১ মিনিট পর আবার চেষ্টা করুন।'));
                    }
                }
            }
        }
    }
    
    $errors = new WP_Error();
    do_action('woocommerce_after_checkout_validation', $data, $errors);
    
    if ($errors->get_error_messages()) {
        if ($lock_key) delete_transient($lock_key);
        $messages = $errors->get_error_messages();
        $message_string = implode("\n", $messages);
        wp_send_json_error(array('message' => $message_string));
    }

    $order = wc_create_order();

    // লুপ চালিয়ে পণ্য যোগ করা
    foreach ($items as $item) {
        $product_id = intval($item['id']);
        $qty = intval($item['qty']);
        if ($product_id > 0 && $qty > 0) {
            $order->add_product(wc_get_product($product_id), $qty);
        }
    }

    // কাস্টমার ডাটা ও Meta EMQ ট্র্যাকিং এনরিচমেন্ট
    fmb_enrich_order_customer_data($order, $billing_first_name, $billing_phone, $billing_address_1, $billing_email, $form_data);
    
    // শিপিং
    $shipping = new WC_Order_Item_Shipping();
    $shipping->set_method_title('Delivery Charge');
    $shipping->set_total($shipping_cost);
    $order->add_item($shipping);
    $order->set_payment_method('cod');
    $order->calculate_totals();
    
    // ফাস্ট অর্ডার স্ট্যাটাস আপডেট (ইমেইল ব্যাকগ্রাউন্ডে কিউ হবে)
    fmb_fast_update_order_status($order, 'processing', 'Combo Order');

    // অর্ডার সফল হলে লক মুছে দেওয়া
    if ($lock_key) {
        delete_transient($lock_key);
    }

    // ========================================
    // GSOL: অর্ডার সফল — record করো
    // ========================================
    if ( function_exists('gsol_record_order') ) {
        gsol_record_order( $billing_phone, $order->get_id() );
    }
    
    wp_send_json_success(array(
        'order_id'     => $order->get_id(),
        'redirect_url' => $order->get_checkout_order_received_url()
    ));
}

// 5. Popup Upsell Fetcher
add_action('wp_ajax_fmb_get_popup_upsells', 'fmb_ajax_get_popup_upsells');
add_action('wp_ajax_nopriv_fmb_get_popup_upsells', 'fmb_ajax_get_popup_upsells');

function fmb_ajax_get_popup_upsells() {
    $product_id = intval($_POST['product_id']);
    if(!$product_id) wp_send_json_error();

    $upsell_ids_str = get_post_meta($product_id, '_fmb_upsell_ids', true);
    if(empty($upsell_ids_str)) wp_send_json_error();

    $upsell_ids = array_map('trim', explode(',', $upsell_ids_str));
    $data = array();

    foreach($upsell_ids as $uid) {
        $p = wc_get_product($uid);
        if(!$p || !$p->is_visible()) continue;

        $price = $p->get_price();
        $regular_price = $p->get_regular_price();
        $discount = 0;
        if($regular_price > $price) {
            $discount = $regular_price - $price;
        }

        $data[] = array(
            'id' => $uid,
            'name' => $p->get_name(),
            'price' => $price,
            'img' => wp_get_attachment_image_url($p->get_image_id(), 'thumbnail'),
            'discount' => $discount,
            'freeDelivery' => fmb_is_effectively_free_delivery_catalog($uid)
        );
    }

    if(empty($data)) wp_send_json_error();

    wp_send_json_success($data);
}



// 6. Quick View Fetcher
add_action('wp_ajax_fmb_quick_view', 'fmb_ajax_quick_view');
add_action('wp_ajax_nopriv_fmb_quick_view', 'fmb_ajax_quick_view');

function fmb_ajax_quick_view() {
    $product_id = intval($_POST['product_id']);
    if (!$product_id) {
        wp_send_json_error();
    }

    $product = wc_get_product($product_id);
    if (!$product) {
        wp_send_json_error();
    }

    $active_price = (float) $product->get_price();
    $regular_price = (float) $product->get_regular_price();
    $discount_amount = 0;
    if ($product->is_on_sale() && $regular_price > $active_price) {
        $discount_amount = $regular_price - $active_price;
    }

    $custom_charges = get_option('cdc_product_delivery_charges', array());
    $custom_amt = isset($custom_charges[$product_id]) ? floatval($custom_charges[$product_id]) : -1;
    
    $link = get_permalink($product_id);
    $redirect_url = get_post_meta($product_id, '_landing_redirect_url', true);
    $is_redirect = get_post_meta($product_id, '_is_landing_redirect', true);
    if ( $is_redirect === 'yes' && !empty($redirect_url) ) {
        $link = $redirect_url;
    }

    // Start output buffer
    ob_start();
    ?>
    <div class="flex flex-col md:flex-row bg-white w-full">
        <!-- Product Image -->
        <div class="md:w-1/2 p-6 flex justify-center items-center bg-gray-50 border-b md:border-b-0 md:border-r border-gray-100">
            <?php 
            if ( has_post_thumbnail( $product_id ) ) {
                echo $product->get_image('large', array('class' => 'max-w-full h-auto object-contain max-h-[400px] rounded'));
            } else {
                echo '<img src="' . wc_placeholder_img_src() . '" alt="Placeholder" class="max-w-full h-auto object-contain max-h-[400px] rounded">';
            }
            ?>
        </div>
        
        <!-- Product Details -->
        <div class="md:w-1/2 p-6 md:p-8 flex flex-col justify-center">
            <h2 class="text-2xl font-bold text-gray-900 mb-2 leading-tight"><?php echo esc_html($product->get_name()); ?></h2>
            <div class="text-2xl font-extrabold text-primary mb-3">
                <?php echo $product->get_price_html(); ?>
            </div>
            
            <?php fmb_render_sales_proof_badge( $product_id, '', 'margin-bottom: 14px; width: fit-content;' ); ?>
            
            <div class="text-gray-600 text-sm mb-6 leading-relaxed max-h-32 overflow-y-auto pr-2 custom-scrollbar">
                <?php echo apply_filters('woocommerce_short_description', $product->get_short_description()); ?>
            </div>

            <div class="flex gap-4 mt-auto pt-4 border-t border-gray-100">
                <button type="button" class="qs-order-popup-trigger flex-1 bg-secondary hover:bg-primary text-white font-bold py-3 px-4 rounded transition-colors flex items-center justify-center gap-2"
                    data-product-id="<?php echo $product->get_id(); ?>"
                    data-price="<?php echo $active_price; ?>" 
                    data-discount="<?php echo $discount_amount; ?>"
                    data-custom-delivery="<?php echo esc_attr($custom_amt); ?>"
                    data-free-delivery="<?php echo fmb_is_effectively_free_delivery_catalog($product->get_id()) ? '1' : '0'; ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                    অর্ডার করুন
                </button>
                <a href="<?php echo esc_url($link); ?>" class="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-800 font-bold py-3 px-4 rounded transition-colors text-center flex items-center justify-center">
                    বিস্তারিত দেখুন
                </a>
            </div>
        </div>
    </div>
    <?php
    $html = ob_get_clean();

    wp_send_json_success(array('html' => $html));
}


remove_action('wp_head', 'wp_site_icon', 99);

// =============================================
// 7. Auto-Create Default Pages on Theme Setup
// =============================================
function fmb_create_default_pages() {
    $pages = array(
        array(
            'title'    => 'Privacy Policy',
            'slug'     => 'privacy-policy',
            'template' => 'page-privacy-policy.php',
            'content'  => '<!-- Privacy Policy page is handled by the theme template -->',
        ),
        array(
            'title'    => 'Track Order',
            'slug'     => 'track-order',
            'template' => 'page-track-order.php',
            'content'  => '<!-- Track Order page is handled by the theme template -->',
        ),
        array(
            'title'    => 'Contact Us',
            'slug'     => 'contact',
            'template' => 'page-contact.php',
            'content'  => '<!-- Contact page is handled by the theme template -->',
        ),
        array(
            'title'    => 'About Us',
            'slug'     => 'about',
            'template' => 'page-about.php',
            'content'  => '<!-- About page is handled by the theme template -->',
        ),
    );

    foreach ($pages as $page_data) {
        // Check if page already exists by slug
        $existing = get_page_by_path($page_data['slug'], OBJECT, 'page');
        if (!$existing) {
            $page_id = wp_insert_post(array(
                'post_title'   => $page_data['title'],
                'post_name'    => $page_data['slug'],
                'post_content' => $page_data['content'],
                'post_status'  => 'publish',
                'post_type'    => 'page',
            ));
            if ($page_id && !is_wp_error($page_id)) {
                // Assign page template
                update_post_meta($page_id, '_wp_page_template', $page_data['template']);
                // Set WP Privacy Policy page
                if ($page_data['slug'] === 'privacy-policy') {
                    update_option('wp_page_for_privacy_policy', $page_id);
                }
            }
        } else {
            // Ensure template is set even if page already exists
            $current_template = get_post_meta($existing->ID, '_wp_page_template', true);
            if (empty($current_template) || $current_template === 'default') {
                update_post_meta($existing->ID, '_wp_page_template', $page_data['template']);
            }
        }
    }
}
add_action('after_switch_theme', 'fmb_create_default_pages');

// Run once if pages not yet created (for already-active theme)
function fmb_maybe_create_pages() {
    if (!get_option('fmb_default_pages_created')) {
        fmb_create_default_pages();
        update_option('fmb_default_pages_created', '1');
    }
}
add_action('init', 'fmb_maybe_create_pages');

// =============================================
// 8. Customizer: Contact Email & Store Address
// =============================================
function fmb_customizer_extra_fields($wp_customize) {
    // Contact Email
    $wp_customize->add_setting('fmb_contact_email', array(
        'default'           => get_option('admin_email'),
        'sanitize_callback' => 'sanitize_email',
    ));
    $wp_customize->add_control('fmb_contact_email', array(
        'label'    => 'Contact Email',
        'section'  => 'fmb_general_section',
        'type'     => 'email',
        'priority' => 25,
    ));

    // Store Address
    $wp_customize->add_setting('fmb_store_address', array(
        'default'           => '',
        'sanitize_callback' => 'sanitize_text_field',
    ));
    $wp_customize->add_control('fmb_store_address', array(
        'label'    => 'Store Address',
        'section'  => 'fmb_general_section',
        'type'     => 'text',
        'priority' => 26,
    ));
}
add_action('customize_register', 'fmb_customizer_extra_fields');

/**
 * ব্যানারে দেখানো সোশ্যাল প্রুফ সংখ্যা (ডাটাবেজের রিয়েল অর্ডার নয়)।
 * ন্যূনতম ৭৯; দিন/ঘণ্টা/১৫ মিনিট ভিত্তিতে ধীরে ধীরে কমে ও নড়াচড়া করে যাতে জীবন্ত লাগে।
 *
 * @param int $product_id Product ID.
 * @return int
 */
function fmb_get_fake_sales_banner_count( $product_id ) {
    $product_id = absint( $product_id );
    if ( ! $product_id ) {
        return 79;
    }

    $ts = current_time( 'timestamp' );

    $day_z = (int) wp_date( 'z', $ts );
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
 * Renders the High-Converting Social Proof Badge: "🔥 গত ২৪ ঘণ্টায় 159 জন এটি কিনেছেন!"
 */
function fmb_render_sales_proof_badge( $product_id, $extra_classes = '', $custom_style = '', $is_dark = false ) {
    $product_id = absint( $product_id );
    if ( ! $product_id ) {
        return;
    }
    $sales_banner_count = fmb_get_fake_sales_banner_count( $product_id );
    $count_formatted    = number_format_i18n( $sales_banner_count );
    if ( $is_dark ) {
        $default_style = 'display: inline-flex; align-items: center; gap: 8px; font-size: 13.5px; color: #fda4af; background: rgba(225, 29, 72, 0.15); border: 1px solid rgba(225, 29, 72, 0.45); padding: 8px 16px; border-radius: 12px; line-height: 1.4;';
        $strong_color  = '#fb7185';
    } else {
        $default_style = 'display: inline-flex; align-items: center; gap: 8px; font-size: 13.5px; color: #374151; background: #fef2f2; border: 1px solid #fecaca; padding: 8px 14px; border-radius: 10px; line-height: 1.4;';
        $strong_color  = '#dc2626';
    }
    $style = $custom_style ? $default_style . ' ' . $custom_style : $default_style;
    ?>
    <div class="fmb-sales-proof-badge <?php echo esc_attr( $extra_classes ); ?>" style="<?php echo esc_attr( $style ); ?>">
        <span style="font-size: 16px; line-height: 1; flex-shrink: 0;">🔥</span>
        <span>গত ২৪ ঘণ্টায় <strong style="color: <?php echo esc_attr( $strong_color ); ?>; font-weight: 800;"><?php echo esc_html( $count_formatted ); ?> জন</strong> এটি কিনেছেন!</span>
    </div>
    <?php
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
            $sbjs = array();
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
        $fields['billing']['billing_first_name']['class'] = array('form-row-wide');
        $fields['billing']['billing_first_name']['priority'] = 10;
    }
    
    if (isset($fields['billing']['billing_phone'])) {
        $fields['billing']['billing_phone']['label'] = false;
        $fields['billing']['billing_phone']['placeholder'] = 'আপনার মোবাইল নম্বর *';
        $fields['billing']['billing_phone']['class'] = array('form-row-wide');
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

// 8.1 Custom Order Button Text (Bangla High-Converting CTA)
add_filter( 'woocommerce_order_button_text', 'fmb_custom_order_button_text' );
function fmb_custom_order_button_text() {
    return 'অর্ডার নিশ্চিত করুন (ক্যাশ অন ডেলিভারি) ➔';
}

// 8.2 Bengali Translations for Cart & Checkout Terms
add_filter('gettext', 'fmb_bangla_checkout_translations', 20, 3);
function fmb_bangla_checkout_translations($translation, $text, $domain) {
    if ($domain === 'woocommerce') {
        if ($text === 'Subtotal') return 'সাবটোটাল';
        if ($text === 'Shipping') return 'ডেলিভারি চার্জ';
        if ($text === 'Shipment') return 'ডেলিভারি চার্জ';
        if ($text === 'Total') return 'সর্বমোট';
        if ($text === 'Cash on delivery') return 'ক্যাশ অন ডেলিভারি';
        if ($text === 'View cart') return 'কার্ট দেখুন →';
        if (strpos($text, 'has been added to your cart') !== false) return 'আপনার কার্টে সফলভাবে যুক্ত হয়েছে।';
        if (strpos($text, 'removed.') !== false) return 'কার্ট থেকে সফলভাবে সরানো হয়েছে।';
        if ($text === 'Undo?') return 'পূর্বাবস্থায় আনুন';
        if ($text === 'Place order') return 'অর্ডার নিশ্চিত করুন (ক্যাশ অন ডেলিভারি) ➔';
    }
    return $translation;
}

// 8.3 AJAX Add to Cart Handler (Instant add without redirect or jump)
add_action('wp_ajax_fmb_ajax_add_to_cart', 'fmb_ajax_add_to_cart');
add_action('wp_ajax_nopriv_fmb_ajax_add_to_cart', 'fmb_ajax_add_to_cart');
function fmb_ajax_add_to_cart() {
    $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
    $quantity   = isset($_POST['quantity']) ? max(1, intval($_POST['quantity'])) : 1;

    if ( ! $product_id ) {
        wp_send_json_error(array('message' => 'প্রোডাক্ট পাওয়া যায়নি।'));
    }

    $product = wc_get_product($product_id);
    if ( ! $product ) {
        wp_send_json_error(array('message' => 'প্রোডাক্ট খুঁজে পাওয়া যায়নি।'));
    }

    $passed_validation = apply_filters('woocommerce_add_to_cart_validation', true, $product_id, $quantity);
    if ($passed_validation && WC()->cart->add_to_cart($product_id, $quantity)) {
        do_action('woocommerce_ajax_added_to_cart', $product_id);

        WC()->cart->calculate_totals();
        wp_send_json_success(array(
            'count'     => WC()->cart->get_cart_contents_count(),
            'total'     => WC()->cart->get_cart_contents_total(),
            'item_name' => $product->get_name(),
        ));
    } else {
        wp_send_json_error(array('message' => 'কার্টে যোগ করা সম্ভব হয়নি।'));
    }
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
    $term_len = function_exists( 'mb_strlen' ) ? mb_strlen( $term ) : strlen( $term );

    if ( empty( $term ) || $term_len < 2 ) {
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


// ========================================================
// 11. Combo Offer & Sales Funnel Template Redirect Handler
// ========================================================
function fmb_combo_offer_landing_redirect() {
    if ( isset( $_GET['fmb_all_combos'] ) && $_GET['fmb_all_combos'] == '1' ) {
        $archive_template = get_template_directory() . '/archive-fmb_combo_offer.php';
        if ( file_exists( $archive_template ) ) {
            include $archive_template;
            exit;
        }
    }
    if ( isset( $_GET['fmb_combo_id'] ) ) {
        $combo_id = intval( $_GET['fmb_combo_id'] );
        if ( $combo_id > 0 ) {
            $combo_post = get_post( $combo_id );
            if ( $combo_post && $combo_post->post_type === 'fmb_combo_offer' ) {
                global $post;
                $post = $combo_post;
                setup_postdata( $post );
                $single_template = get_template_directory() . '/single-fmb_combo_offer.php';
                if ( file_exists( $single_template ) ) {
                    include $single_template;
                    exit;
                }
            }
        }
    }
    if ( isset( $_GET['fmb_sales_page_id'] ) ) {
        $sp_id = intval( $_GET['fmb_sales_page_id'] );
        if ( $sp_id > 0 ) {
            $sp_post = get_post( $sp_id );
            if ( $sp_post && $sp_post->post_type === 'fmb_sales_page' ) {
                global $post;
                $post = $sp_post;
                setup_postdata( $post );
                $sp_template = get_template_directory() . '/single-fmb_sales_page.php';
                if ( file_exists( $sp_template ) ) {
                    include $sp_template;
                    exit;
                }
            }
        }
    }
}
add_action( 'template_redirect', 'fmb_combo_offer_landing_redirect' );

// 11. WooCommerce Notices Translation & Beautification
add_filter('woocommerce_add_to_cart_message_html', function($message, $products) {
    $message = str_ireplace('View cart', 'কার্ট দেখুন →', $message);
    $message = str_ireplace('has been added to your cart.', 'আপনার কার্টে সফলভাবে যুক্ত হয়েছে।', $message);
    $message = str_ireplace('have been added to your cart.', 'আপনার কার্টে সফলভাবে যুক্ত হয়েছে।', $message);
    return $message;
}, 10, 2);

add_filter('gettext', function($translated_text, $text, $domain) {
    if ($domain === 'woocommerce' || empty($domain)) {
        if ($text === 'View cart') {
            return 'কার্ট দেখুন →';
        }
        if ($text === 'Undo?') {
            return 'পূর্বাবস্থায় আনুন';
        }
        if (strpos($text, 'has been added to your cart.') !== false) {
            return str_replace('has been added to your cart.', 'আপনার কার্টে সফলভাবে যুক্ত হয়েছে।', $text);
        }
        if (strpos($text, 'removed.') !== false) {
            return str_replace('removed.', 'কার্ট থেকে সরানো হয়েছে।', $text);
        }
    }
    return $translated_text;
}, 20, 3);
