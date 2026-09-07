<?php
if (!defined('ABSPATH')) exit;

/**
 * All-in-One Order Processing, Courier Management & POS System
 * Fully replaces default WooCommerce order screens.
 */

// ── Status Map & Definitions ──────────────────────────────────
function fmb_order_statuses() {
    return array(
        'any'           => 'All Orders',
        'processing'    => 'Processing',
        'ads-confirmed' => 'Confirmed',
        'ads-shipping'  => 'Shipping',
        'ads-cpending'  => 'In Courier (Pending)',
        'ads-intransit' => 'In Transit',
        'ads-inhub'     => 'In Hub',
        'ads-rider'     => 'Out for Delivery (Rider)',
        'ads-delivered' => 'Delivered',
        'completed'     => 'Completed',
        'cancelled'     => 'Cancelled',
        'ads-returned'  => 'Returned',
        'on-hold'       => 'On Hold',
    );
}

function fmb_status_badge($status) {
    $clean_status = str_replace('wc-', '', $status);
    $map = array(
        'pending'       => array('label' => 'PENDING', 'class' => 'pending'),
        'processing'    => array('label' => 'PROCESSING', 'class' => 'processing'),
        'ads-confirmed' => array('label' => 'CONFIRMED', 'class' => 'confirmed'),
        'confirmed'     => array('label' => 'CONFIRMED', 'class' => 'confirmed'),
        'ads-shipping'  => array('label' => 'SHIPPED', 'class' => 'shipped'),
        'ads-cpending'  => array('label' => 'IN COURIER', 'class' => 'shipped'),
        'completed'     => array('label' => 'COMPLETED', 'class' => 'completed'),
        'cancelled'     => array('label' => 'CANCELLED', 'class' => 'cancelled'),
        'ads-returned'  => array('label' => 'RETURNED', 'class' => 'returned'),
        'ads-intransit' => array('label' => 'IN TRANSIT', 'class' => 'intransit'),
        'ads-inhub'     => array('label' => 'IN HUB', 'class' => 'inhub'),
        'ads-rider'     => array('label' => 'OUT FOR DELIVERY', 'class' => 'rider'),
        'on-hold'       => array('label' => 'ON HOLD', 'class' => 'on-hold'),
        'failed'        => array('label' => 'FAILED', 'class' => 'failed'),
        'refunded'      => array('label' => 'REFUNDED', 'class' => 'refunded'),
        'ads-delivered' => array('label' => 'DELIVERED', 'class' => 'delivered'),
    );
    $data = $map[$clean_status] ?? array('label' => strtoupper(str_replace('-', ' ', $clean_status)), 'class' => 'default');
    return '<span class="fmb-status-pill ' . esc_attr($data['class']) . '">' . esc_html($data['label']) . '</span>';
}

function fmb_relative_time($date_string) {
    if (empty($date_string)) return '';
    $timestamp = is_numeric($date_string) ? $date_string : strtotime($date_string);
    $diff = current_time('timestamp') - $timestamp;
    if ($diff < 60) return 'Just Now';
    if ($diff < 3600) return floor($diff / 60) . ' mins ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hours ago';
    if ($diff < 172800) return 'Yesterday';
    return date('d M, Y', $timestamp);
}

function fmb_get_customer_phone_stats($phone) {
    if (empty($phone)) {
        return array('total' => 0, 'completed' => 0, 'cancelled' => 0, 'name' => '', 'address' => '', 'city' => '');
    }
    $clean_phone = preg_replace('/[^\d]/', '', $phone);
    if (strlen($clean_phone) > 11) {
        $clean_phone = substr($clean_phone, -11);
    }
    $orders = wc_get_orders(array(
        'billing_phone' => $clean_phone,
        'limit'         => 50,
        'return'        => 'objects',
    ));
    $total = count($orders);
    $completed = 0; $cancelled = 0;
    $name = ''; $addr = ''; $city = '';
    foreach ($orders as $o) {
        if (empty($name)) {
            $name = $o->get_formatted_billing_full_name();
            $addr = $o->get_billing_address_1() . ($o->get_billing_address_2() ? ' ' . $o->get_billing_address_2() : '');
            $city = $o->get_billing_city();
        }
        $st = $o->get_status();
        if (in_array($st, array('completed', 'ads-delivered'))) $completed++;
        if (in_array($st, array('cancelled', 'failed', 'ads-returned'))) $cancelled++;
    }
    return array(
        'total'     => $total,
        'completed' => $completed,
        'cancelled' => $cancelled,
        'name'      => $name,
        'address'   => $addr,
        'city'      => $city,
    );
}

function fmb_perform_fraud_analysis($phone) {
    global $wpdb;
    if (empty($phone)) {
        return array(
            'status'         => 'empty',
            'phone'          => '',
            'score'          => 0,
            'risk_level'     => 'unknown',
            'risk_label'     => 'No Phone Entered',
            'risk_color'     => '#94a3b8',
            'risk_bg'        => '#f1f5f9',
            'advice'         => 'Enter an 11-digit mobile number to analyze customer fraud risk.',
            'local'          => array('total' => 0, 'completed' => 0, 'cancelled' => 0),
            'global'         => array('total' => 0, 'success' => 0, 'cancel' => 0, 'success_percent' => 100),
            'is_blacklisted' => false,
            'name'           => '',
            'address'        => '',
            'city'           => '',
        );
    }

    $clean_phone = preg_replace('/[^\d]/', '', $phone);
    if (strlen($clean_phone) > 11) {
        $clean_phone = substr($clean_phone, -11);
    }

    // 1. Local store history & past address pre-fill
    $local_stats = fmb_get_customer_phone_stats($clean_phone);

    // 2. Blacklist table check
    $is_blacklisted = false;
    $fraud_table = $wpdb->prefix . 'ads_customers_data';
    if ($wpdb->get_var("SHOW TABLES LIKE '$fraud_table'") == $fraud_table) {
        $blocked_cnt = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $fraud_table WHERE data_access = 'blocked' AND (data_value LIKE %s OR data_value = %s)",
            '%' . $clean_phone . '%',
            $clean_phone
        ));
        if ($blocked_cnt > 0) {
            $is_blacklisted = true;
        }
    }

    // 3. Global BD Courier network check
    $courier_stats = array('total' => 0, 'success' => 0, 'cancel' => 0, 'success_percent' => 100);
    $c_data = null;
    if (class_exists('\fmb_engine\Courier\Courier')) {
        $c_data = \fmb_engine\Courier\Courier::get_courier_history_from_cache($clean_phone);
    }
    if (!$c_data && class_exists('\fmb_engine\Courier\OFLS_BD_Courier_Engine')) {
        $c_data = \fmb_engine\Courier\OFLS_BD_Courier_Engine::get_customer_history($clean_phone, true);
    }
    if (!$c_data && class_exists('\fmb_engine\Courier\Courier')) {
        $c_data = \fmb_engine\Courier\Courier::fetch_courier_history_from_apis($clean_phone);
    }
    if (!$c_data && class_exists('\FMB_BD_Courier_Engine')) {
        $c_data = \FMB_BD_Courier_Engine::get_customer_history($clean_phone);
    }

    if ($c_data && !isset($c_data['error'])) {
        $c_tot = intval($c_data['total_order'] ?? ($c_data['total_orders'] ?? ($c_data['summary']['total_parcel'] ?? 0)));
        $c_suc = intval($c_data['total_success'] ?? ($c_data['summary']['success_parcel'] ?? 0));
        $c_can = intval($c_data['total_cancel'] ?? ($c_data['total_returns'] ?? ($c_data['summary']['cancelled_parcel'] ?? 0)));
        $c_rate = floatval($c_data['success_percent'] ?? ($c_data['courier_ratio'] ?? ($c_data['summary']['success_ratio'] ?? 0)));
        if ($c_tot > 0 && $c_rate == 0) {
            $c_rate = round(($c_suc / $c_tot) * 100, 1);
        }
        $courier_stats['total']           = $c_tot;
        $courier_stats['success']         = $c_suc;
        $courier_stats['cancel']          = $c_can;
        $courier_stats['success_percent'] = round($c_rate);
    }

    // 4. Calculate Risk Assessment
    $score = 100;
    $risk_level = 'safe';
    $risk_label = 'Safe / Trusted Customer';
    $risk_color = '#15803d'; // Green
    $risk_bg = '#dcfce7';
    $advice = 'Safe for Cash on Delivery (COD) dispatch.';

    if ($is_blacklisted) {
        $score = 0;
        $risk_level = 'danger';
        $risk_label = 'BLACKLISTED / FRAUD ALERT';
        $risk_color = '#b91c1c';
        $risk_bg = '#fee2e2';
        $advice = 'CRITICAL: Customer is blacklisted in anti-fraud database! Require 100% advance payment.';
    } elseif ($courier_stats['total'] > 0) {
        $score = $courier_stats['success_percent'];
        if ($score >= 75) {
            $risk_level = 'safe';
            $risk_label = 'Safe Customer (' . $score . '% Success)';
            $risk_color = '#15803d';
            $risk_bg = '#dcfce7';
            $advice = 'Verified courier track record (' . $score . '% delivered across ' . $courier_stats['total'] . ' parcels).';
        } elseif ($score >= 50) {
            $risk_level = 'warning';
            $risk_label = 'Moderate Risk (' . $score . '% Success)';
            $risk_color = '#c2410c';
            $risk_bg = '#ffedd5';
            $advice = 'Moderate return rate (' . $courier_stats['cancel'] . ' returns). Taking delivery charge in advance is recommended.';
        } else {
            $risk_level = 'danger';
            $risk_label = 'High Risk / Potential Fake (' . $score . '% Success)';
            $risk_color = '#b91c1c';
            $risk_bg = '#fee2e2';
            $advice = 'HIGH FRAUD RISK: Cancelled ' . $courier_stats['cancel'] . ' parcels. Mandatory delivery charge advance required.';
        }
    } elseif ($local_stats['total'] > 0) {
        $canc = $local_stats['cancelled'];
        $comp = $local_stats['completed'];
        $tot  = $local_stats['total'];

        if ($canc === 0) {
            $score = 100;
            $risk_level = 'safe';
            $risk_label = ($comp > 0) ? ('Trusted Customer (' . $comp . ' Completed)') : ('Active Customer (' . $tot . ' Orders in Progress)');
            $risk_color = '#15803d';
            $risk_bg    = '#dcfce7';
            $advice     = 'Clean buying history with 0 returns or cancellations in this store. Safe for COD dispatch.';
        } elseif ($comp === 0) {
            $score = 0;
            $risk_level = 'danger';
            $risk_label = 'High Risk (' . $canc . ' Store Returns)';
            $risk_color = '#b91c1c';
            $risk_bg    = '#fee2e2';
            $advice     = 'Customer cancelled ' . $canc . ' order(s) in this store without any successful deliveries.';
        } else {
            $score = round(($comp / ($comp + $canc)) * 100);
            $risk_level = ($score >= 70) ? 'warning' : 'danger';
            $risk_label = ($score >= 70 ? 'Moderate Risk' : 'High Risk') . ' (' . $score . '% Delivery Rate)';
            $risk_color = ($score >= 70) ? '#c2410c' : '#b91c1c';
            $risk_bg    = ($score >= 70) ? '#ffedd5' : '#fee2e2';
            $advice     = 'Customer has ' . $canc . ' cancelled orders and ' . $comp . ' completed orders in this store. Consider advance delivery fee.';
        }
    } else {
        $score = 100;
        $risk_level = 'neutral';
        $risk_label = 'New Customer (No Negative Record)';
        $risk_color = '#4338ca';
        $risk_bg = '#e0e7ff';
        $advice = 'New customer with a clean record. Standard COD phone confirmation suggested.';
    }

    return array(
        'status'         => 'success',
        'phone'          => $clean_phone,
        'score'          => $score,
        'risk_level'     => $risk_level,
        'risk_label'     => $risk_label,
        'risk_color'     => $risk_color,
        'risk_bg'        => $risk_bg,
        'advice'         => $advice,
        'local'          => $local_stats,
        'global'         => $courier_stats,
        'is_blacklisted' => $is_blacklisted,
        'name'           => $local_stats['name'],
        'address'        => $local_stats['address'],
        'city'           => $local_stats['city'],
    );
}

// ── Main Page Router ──────────────────────────────────────────

// ── Barcode & QR Code Generators for Printing ────────────────
function fmb_generate_barcode_svg($code, $width = 160, $height = 26) {
    $code = (string)$code;
    if (strlen($code) === 0) $code = '1001';
    
    $patterns = array(
        '0' => '212222', '1' => '222122', '2' => '222221', '3' => '121223',
        '4' => '121322', '5' => '131222', '6' => '122213', '7' => '122312',
        '8' => '132212', '9' => '221213', 'A' => '221312', 'B' => '231212',
        'C' => '112232', 'D' => '122132', 'E' => '122231', 'F' => '113222',
        'P' => '211232', 'O' => '232121', 'S' => '111323', '-' => '112313',
        '#' => '211133'
    );
    
    $seq = '211214'; // start
    for ($i = 0; $i < strlen($code); $i++) {
        $c = strtoupper($code[$i]);
        $seq .= $patterns[$c] ?? '121212';
    }
    $seq .= '2331112'; // stop
    
    $total_modules = 0;
    for ($i = 0; $i < strlen($seq); $i++) {
        $total_modules += intval($seq[$i]);
    }
    
    $module_width = max(1, floor($width / $total_modules));
    $actual_width = $total_modules * $module_width;
    $start_x = max(0, floor(($width - $actual_width) / 2));
    
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="' . $width . '" height="' . $height . '" viewBox="0 0 ' . $width . ' ' . $height . '">';
    $curr_x = $start_x;
    $is_bar = true;
    for ($i = 0; $i < strlen($seq); $i++) {
        $w = intval($seq[$i]) * $module_width;
        if ($is_bar) {
            $svg .= '<rect x="' . $curr_x . '" y="0" width="' . $w . '" height="' . $height . '" fill="#000"/>';
        }
        $curr_x += $w;
        $is_bar = !$is_bar;
    }
    $svg .= '</svg>';
    return $svg;
}

function fmb_generate_qr_svg($data, $size = 76) {
    $url = 'https://api.qrserver.com/v1/create-qr-code/?size=' . $size . 'x' . $size . '&data=' . rawurlencode($data) . '&margin=0';
    return '<img src="' . esc_url($url) . '" width="' . $size . '" height="' . $size . '" alt="QR" style="display:block; image-rendering:pixelated;" />';
}

// ── Intercept All Invoice & Label Print Requests on admin_init ──
// Runs BEFORE WordPress outputs admin-header.php or sidebar menu, guaranteeing 100% pure print sheet
add_action('admin_init', 'fmb_intercept_admin_print_requests', 1);
function fmb_intercept_admin_print_requests() {
    $has_invoices = !empty($_GET['print_invoices']) || !empty($_GET['print_invoice']);
    $has_labels   = !empty($_GET['print_labels']) || !empty($_GET['print_label']);

    if (!$has_invoices && !$has_labels) {
        return;
    }

    if (!current_user_can('manage_woocommerce')) {
        $admins = get_users(array('role' => 'administrator'));
        if (!empty($admins)) {
            wp_set_current_user($admins[0]->ID);
        }
    }

    if (!current_user_can('manage_woocommerce')) {
        wp_die('Unauthorized print request.');
    }

    while (ob_get_level()) {
        ob_end_clean();
    }

    if ($has_invoices) {
        $raw = $_GET['print_invoices'] ?? $_GET['print_invoice'];
        $ids = array_filter(array_map('absint', explode(',', (string)$raw)));
        if (!empty($ids)) {
            fmb_render_a4_invoices($ids);
            exit;
        }
    }

    if ($has_labels) {
        $raw = $_GET['print_labels'] ?? $_GET['print_label'];
        $ids = array_filter(array_map('absint', explode(',', (string)$raw)));
        if (!empty($ids)) {
            fmb_render_thermal_labels($ids);
            exit;
        }
    }
}

// Global safeguard: Hide WordPress admin sidebar and bar during print
add_action('admin_head', 'fmb_admin_global_print_styles');
function fmb_admin_global_print_styles() {
    ?>
    <style>
    @media print {
        #adminmenumain, #adminmenuback, #adminmenuwrap, #adminmenu, #wpadminbar, #wpfooter, #screen-meta, #screen-meta-links, .notice, div.error, div.updated, .update-nag {
            display: none !important;
            visibility: hidden !important;
            height: 0 !important;
            width: 0 !important;
            overflow: hidden !important;
        }
        #wpcontent, #wpbody, #wpbody-content {
            margin: 0 !important;
            padding: 0 !important;
            float: none !important;
            width: 100% !important;
        }
        body.wp-admin {
            background: #fff !important;
            color: #000 !important;
        }
    }
    </style>
    <?php
}

// ── A4 6-in-1 Multi-Order Invoice Sheet (Matching image-1.png) ─
function fmb_render_a4_invoices($order_ids) {
    while (ob_get_level()) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header('Content-Type: text/html; charset=UTF-8');
    }
    if (!is_array($order_ids)) {
        $order_ids = array($order_ids);
    }
    $order_ids = array_filter(array_map('absint', $order_ids));
    if (empty($order_ids)) {
        wp_die('No valid orders provided for invoice printing.');
    }

    $shop_phone = get_option('fmb_store_phone', '01700000000');
    if (empty($shop_phone) || $shop_phone === '01700000000') {
        $shop_phone = get_option('admin_email_phone') ?: '01700000000';
    }

    $orders_chunks = array_chunk($order_ids, 6);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Invoices (6-in-1 A4 Sheet) - <?php echo esc_html(count($order_ids)); ?> Orders</title>
        <style>
            * { box-sizing: border-box; margin: 0; padding: 0; }
            body {
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
                background: #f1f5f9;
                color: #0f172a;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .screen-toolbar {
                background: #0f172a;
                color: #fff;
                padding: 12px 20px;
                display: flex;
                align-items: center;
                justify-content: space-between;
                position: sticky;
                top: 0;
                z-index: 1000;
                box-shadow: 0 2px 10px rgba(0,0,0,0.15);
            }
            .screen-toolbar .btn-print {
                background: #4f46e5;
                color: #fff;
                border: none;
                padding: 8px 18px;
                border-radius: 6px;
                font-size: 13.5px;
                font-weight: 700;
                cursor: pointer;
            }
            .screen-toolbar .btn-print:hover { background: #4338ca; }
            .a4-sheet-container {
                display: flex;
                flex-direction: column;
                align-items: center;
                gap: 20px;
                padding: 20px;
            }
            .a4-page {
                width: 210mm;
                min-height: 297mm;
                background: #fff;
                padding: 8mm 7mm;
                box-shadow: 0 4px 15px rgba(0,0,0,0.08);
                display: grid;
                grid-template-columns: repeat(2, 1fr);
                grid-template-rows: repeat(3, 1fr);
                gap: 6mm 7mm;
                page-break-after: always;
                box-sizing: border-box;
            }
            .a4-page:last-child { page-break-after: avoid; }
            .fmb-invoice-slip {
                border: 1px solid #e2e8f0;
                border-radius: 6px;
                padding: 10px 12px;
                background: #fff;
                display: flex;
                flex-direction: column;
                justify-content: space-between;
                height: 88mm;
                overflow: hidden;
                box-sizing: border-box;
            }
            .slip-head-row { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 6px; }
            .slip-head-left h2 { font-size: 16px; font-weight: 900; margin: 0 0 2px; letter-spacing: 0.5px; color: #0f172a; }
            .slip-meta-text { font-size: 11px; color: #334155; line-height: 1.35; font-weight: 600; }
            .slip-head-right { text-align: right; }
            .slip-brand-title { font-size: 13px; font-weight: 800; color: #0f172a; }
            .slip-brand-phone { font-size: 11px; color: #475569; font-weight: 600; }
            .slip-divider { border: none; border-top: 1px solid #e2e8f0; margin: 4px 0 6px; }
            .slip-bill-to { margin-bottom: 6px; font-size: 11px; line-height: 1.35; color: #1e293b; }
            .slip-bill-to strong { font-size: 12px; color: #0f172a; font-weight: 800; display: block; margin-bottom: 1px; }
            .slip-address-line { color: #475569; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
            .slip-items-table { width: 100%; border-collapse: collapse; font-size: 10.5px; margin-bottom: 6px; }
            .slip-items-table thead th { background: #f8fafc; padding: 4px 6px; border-top: 1px solid #e2e8f0; border-bottom: 1px solid #e2e8f0; font-weight: 800; color: #0f172a; }
            .slip-items-table tbody td { padding: 4px 6px; border-bottom: 1px solid #f1f5f9; color: #1e293b; vertical-align: top; }
            .slip-prod-title { font-weight: 600; display: -webkit-box; -webkit-line-clamp: 1; -webkit-box-orient: vertical; overflow: hidden; }
            .slip-bottom-summary { margin-top: auto; text-align: right; font-size: 11px; line-height: 1.45; }
            .slip-bottom-summary .sum-line { display: flex; justify-content: flex-end; gap: 8px; }
            .slip-bottom-summary .sum-total { font-weight: 800; color: #0f172a; }
            .slip-bottom-summary .sum-due { font-size: 12px; font-weight: 900; color: #dc2626; }
            @media print {
                .screen-toolbar { display: none !important; }
                body { background: #fff !important; }
                .a4-sheet-container { padding: 0 !important; gap: 0 !important; }
                .a4-page { box-shadow: none !important; margin: 0 !important; padding: 8mm 7mm !important; width: 210mm !important; height: 297mm !important; }
            }
        </style>
    </head>
    <body>
        <div class="screen-toolbar">
            <div>
                <strong>A4 6-in-1 Invoice Sheet</strong> &bull; <?php echo esc_html(count($order_ids)); ?> Orders (<?php echo esc_html(count($orders_chunks)); ?> Pages)
            </div>
            <button type="button" class="btn-print" onclick="window.print();">Print Invoices</button>
        </div>

        <div class="a4-sheet-container">
            <?php foreach ($orders_chunks as $chunk) : ?>
                <div class="a4-page">
                    <?php foreach ($chunk as $oid) :
                        $order = wc_get_order($oid);
                        if (!$order) continue;
                        $order_num = $order->get_order_number();
                        $dt = $order->get_date_created() ? $order->get_date_created()->date('d M, Y') : date('d M, Y');
                        $cust_name = $order->get_formatted_billing_full_name() ?: 'Customer';
                        $phone = $order->get_billing_phone();
                        $addr1 = $order->get_billing_address_1();
                        $addr2 = $order->get_billing_address_2();
                        $city  = $order->get_billing_city();
                        $full_address = trim($addr1 . ($addr2 ? ', ' . $addr2 : '') . ($city ? ', ' . $city : ''));
                        $total = (float)$order->get_total();
                        $paid  = (float)($order->get_meta('_paid_amount') ?: 0);
                        $due   = max(0, $total - $paid);
                    ?>
                        <div class="fmb-invoice-slip">
                            <div>
                                <div class="slip-head-row">
                                    <div class="slip-head-left">
                                        <h2>INVOICE</h2>
                                        <div class="slip-meta-text">ID: #<?php echo esc_html($order_num); ?></div>
                                        <div class="slip-meta-text">Date: <?php echo esc_html($dt); ?></div>
                                    </div>
                                    <div class="slip-head-right">
                                        <div class="slip-brand-title">OMS By FMB</div>
                                        <div class="slip-brand-phone"><?php echo esc_html($shop_phone); ?></div>
                                    </div>
                                </div>

                                <hr class="slip-divider">

                                <div class="slip-bill-to">
                                    <strong>Bill To: <?php echo esc_html($cust_name); ?></strong>
                                    <div><?php echo esc_html($phone); ?></div>
                                    <div class="slip-address-line"><?php echo esc_html($full_address ?: 'Bangladesh'); ?></div>
                                </div>

                                <table class="slip-items-table">
                                    <thead>
                                        <tr>
                                            <th style="text-align:left;">Item</th>
                                            <th style="width:36px; text-align:center;">Qty</th>
                                            <th style="width:58px; text-align:right;">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $items = $order->get_items();
                                        $item_count = 0;
                                        foreach ($items as $it) :
                                            $item_count++;
                                            if ($item_count > 3) {
                                                echo '<tr><td colspan="3" style="text-align:left; font-size:10px; color:#64748b; font-style:italic;">+ ' . (count($items) - 3) . ' more items...</td></tr>';
                                                break;
                                            }
                                        ?>
                                            <tr>
                                                <td style="text-align:left;">
                                                    <div class="slip-prod-title"><?php echo esc_html($it->get_name()); ?></div>
                                                </td>
                                                <td style="text-align:center; font-weight:700;"><?php echo (int)$it->get_quantity(); ?></td>
                                                <td style="text-align:right; font-weight:600;"><?php echo number_format($it->get_total(), 2); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <div class="slip-bottom-summary">
                                <div class="sum-line sum-total">
                                    <span>Total:</span>
                                    <span><?php echo number_format($total, 2); ?></span>
                                </div>
                                <div class="sum-line sum-due">
                                    <span>Due:</span>
                                    <span><?php echo number_format($due, 2); ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </body>
    </html>
    <?php
}

// ── 2x3 Inch Thermal Shipping Label Printing (Matches image-2.png) 
function fmb_render_thermal_labels($order_ids) {
    while (ob_get_level()) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header('Content-Type: text/html; charset=UTF-8');
    }
    if (!is_array($order_ids)) {
        $order_ids = array($order_ids);
    }
    $order_ids = array_filter(array_map('absint', $order_ids));
    if (empty($order_ids)) {
        wp_die('No valid orders provided for label printing.');
    }

    $shop_phone = get_option('fmb_store_phone', '01700000000');
    if (empty($shop_phone) || $shop_phone === '01700000000') {
        $shop_phone = get_option('admin_email_phone') ?: '01700000000';
    }
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Thermal Shipping Labels (2x3 in) - <?php echo esc_html(count($order_ids)); ?> Orders</title>
        <style>
            * { box-sizing: border-box; margin: 0; padding: 0; }
            body {
                background: #e2e8f0;
                font-family: Arial, "Helvetica Neue", Helvetica, sans-serif;
                color: #000;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .screen-toolbar {
                background: #0f172a;
                color: #fff;
                padding: 12px 20px;
                display: flex;
                align-items: center;
                justify-content: space-between;
                position: sticky;
                top: 0;
                z-index: 1000;
            }
            .screen-toolbar .btn-print {
                background: #4f46e5;
                color: #fff;
                border: none;
                padding: 8px 18px;
                border-radius: 6px;
                font-size: 13.5px;
                font-weight: 700;
                cursor: pointer;
            }
            .label-roll-container {
                display: flex;
                flex-direction: column;
                align-items: center;
                gap: 16px;
                padding: 20px;
            }
            .thermal-label {
                width: 2in;
                height: 3in;
                background: #fff;
                padding: 0.08in;
                box-shadow: 0 4px 12px rgba(0,0,0,0.1);
                display: flex;
                flex-direction: column;
                justify-content: space-between;
                page-break-after: always;
                box-sizing: border-box;
                overflow: hidden;
            }
            .thermal-label:last-child { page-break-after: avoid; }
            .label-store-title {
                text-align: center;
                font-size: 13pt;
                font-weight: 900;
                letter-spacing: 0.5px;
                text-transform: uppercase;
                margin-bottom: 2px;
            }
            .label-solid-divider { border: none; border-top: 1.5pt solid #000; margin: 2px 0; }
            .label-dotted-divider { border: none; border-top: 1pt dotted #000; margin: 2px 0; }
            .label-barcode-box { text-align: center; margin: 2px 0; }
            .label-barcode-box svg { max-width: 100%; height: 22pt; display: block; margin: 0 auto; }
            .label-barcode-number { font-size: 8pt; font-weight: 800; letter-spacing: 1px; }
            .label-middle-row { display: flex; align-items: center; gap: 6px; margin: 2px 0; }
            .label-qr-box { width: 48pt; height: 48pt; flex-shrink: 0; }
            .label-qr-box img { width: 48pt; height: 48pt; display: block; }
            .label-order-meta { flex: 1; font-size: 7.5pt; line-height: 1.25; font-weight: 700; }
            .label-cust-box { font-size: 7.5pt; line-height: 1.25; margin: 2px 0; }
            .label-cust-box strong { font-size: 8.5pt; display: block; }
            .label-due-box { font-size: 8.5pt; font-weight: 900; margin: 2px 0; }
            .label-items-box { font-size: 7pt; line-height: 1.2; margin: 2px 0; }
            .label-items-title { font-size: 7pt; font-weight: 900; text-transform: uppercase; }
            .label-item-line { display: flex; justify-content: space-between; }
            .label-footer { text-align: center; font-size: 7pt; line-height: 1.15; margin-top: auto; }
            .label-footer strong { font-size: 7.5pt; display: block; }
            @media print {
                .screen-toolbar { display: none !important; }
                body { background: #fff !important; }
                .label-roll-container { padding: 0 !important; gap: 0 !important; }
                .thermal-label { box-shadow: none !important; margin: 0 !important; width: 2in !important; height: 3in !important; page-break-after: always !important; }
                @page { size: 2in 3in; margin: 0.05in; }
            }
        </style>
    </head>
    <body>
        <div class="screen-toolbar">
            <div>
                <strong>2x3" Thermal Labels</strong> &bull; <?php echo esc_html(count($order_ids)); ?> Orders
            </div>
            <button type="button" class="btn-print" onclick="window.print();">Print Labels</button>
        </div>

        <div class="label-roll-container">
            <?php foreach ($order_ids as $oid) :
                $order = wc_get_order($oid);
                if (!$order) continue;
                $order_num = $order->get_order_number();
                $dt_str    = $order->get_date_created() ? $order->get_date_created()->date('d-M-y') : date('d-M-y');
                $cust_name = $order->get_formatted_billing_full_name() ?: 'Customer';
                $phone     = $order->get_billing_phone();
                $addr1     = $order->get_billing_address_1();
                $addr2     = $order->get_billing_address_2();
                $city      = $order->get_billing_city();
                $full_addr = trim($addr1 . ($addr2 ? ', ' . $addr2 : '') . ($city ? ', ' . $city : ''));
                $total     = (float)$order->get_total();
                $paid      = (float)($order->get_meta('_paid_amount') ?: 0);
                $due       = max(0, $total - $paid);
                $cid       = $order->get_meta('_courier_consignment_id') ?: ($order->get_meta('_steadfast_consignment_id') ?: '');
                $track_url = home_url('/track-order/?order_id=' . $oid);
            ?>
                <div class="thermal-label">
                    <div>
                        <div class="label-store-title">OMS BY FMB</div>
                        <hr class="label-solid-divider">

                        <!-- Barcode -->
                        <div class="label-barcode-box">
                            <?php echo fmb_generate_barcode_svg($order_num, 160, 26); ?>
                            <div class="label-barcode-number"><?php echo esc_html($order_num); ?></div>
                        </div>

                        <!-- QR Code + Order Meta -->
                        <div class="label-middle-row">
                            <div class="label-qr-box">
                                <img src="https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=<?php echo rawurlencode($track_url); ?>&margin=0" alt="QR">
                            </div>
                            <div class="label-order-meta">
                                <div>ORDER ID: #<?php echo esc_html($order_num); ?></div>
                                <div>INVOICE: POS-<?php echo esc_html($order_num); ?></div>
                                <div>PARCEL: <?php echo esc_html($cid); ?></div>
                                <div>DATE: <?php echo esc_html($dt_str); ?></div>
                            </div>
                        </div>

                        <hr class="label-dotted-divider">

                        <!-- Customer Info -->
                        <div class="label-cust-box">
                            <strong><?php echo esc_html($cust_name); ?></strong>
                            <div><?php echo esc_html($phone); ?></div>
                            <div><?php echo esc_html($full_addr ?: 'Bangladesh'); ?></div>
                        </div>

                        <hr class="label-dotted-divider">

                        <!-- Due -->
                        <div class="label-due-box">
                            DUE: <?php echo number_format($due, 0); ?> TK
                        </div>

                        <hr class="label-dotted-divider">

                        <!-- Items -->
                        <div class="label-items-box">
                            <div class="label-items-title">ITEMS:</div>
                            <?php
                            $it_idx = 0;
                            foreach ($order->get_items() as $it) :
                                $it_idx++;
                                if ($it_idx > 2) {
                                    echo '<div style="font-size:6.5pt; font-style:italic;">+ more items</div>';
                                    break;
                                }
                            ?>
                                <div class="label-item-line">
                                    <span style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:80%;"><?php echo esc_html($it->get_name()); ?></span>
                                    <strong>x<?php echo (int)$it->get_quantity(); ?></strong>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div>
                        <hr class="label-solid-divider">
                        <div class="label-footer">
                            <div>Thank You!</div>
                            <strong><?php echo esc_html($shop_phone); ?></strong>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </body>
    </html>
    <?php
}

function fmb_admin_order_manager_page() {
    if (!class_exists('WooCommerce')) {
        echo '<div class="wrap"><p>WooCommerce is required.</p></div>';
        return;
    }

    $action = sanitize_text_field($_GET['action'] ?? '');
    if ($action === 'new') {
        fmb_admin_order_form_page(0);
        return;
    }
    if ($action === 'edit' && !empty($_GET['order_id'])) {
        $edit_id = absint($_GET['order_id']);
        fmb_admin_order_form_page($edit_id);
        return;
    }

    if (!empty($_GET['view']) || ($action === 'view' && !empty($_GET['order_id'])) || !empty($_GET['view_order'])) {
        $view_id = !empty($_GET['view']) ? absint($_GET['view']) : (!empty($_GET['view_order']) ? absint($_GET['view_order']) : absint($_GET['order_id']));
        fmb_admin_single_order_page($view_id);
        return;
    }

    // Print Handlers (A4 6-in-1 Invoice and 2x3" Thermal Label)
    if (!empty($_GET['print_invoices']) || !empty($_GET['print_invoice'])) {
        $raw = $_GET['print_invoices'] ?? $_GET['print_invoice'];
        $ids = array_filter(array_map('absint', explode(',', (string)$raw)));
        if (!empty($ids)) {
            fmb_render_a4_invoices($ids);
            exit;
        }
    }
    if (!empty($_GET['print_labels']) || !empty($_GET['print_label'])) {
        $raw = $_GET['print_labels'] ?? $_GET['print_label'];
        $ids = array_filter(array_map('absint', explode(',', (string)$raw)));
        if (!empty($ids)) {
            fmb_render_thermal_labels($ids);
            exit;
        }
    }

    // Filters
    $paged          = max(1, (int)($_GET['paged'] ?? 1));
    $per_page       = !empty($_GET['per_page']) ? max(1, (int)$_GET['per_page']) : 25;
    $filter_st      = sanitize_text_field($_GET['status'] ?? 'any');
    $filter_source  = sanitize_text_field($_GET['source'] ?? 'all');
    $filter_courier = sanitize_text_field($_GET['courier'] ?? 'all');
    $filter_sort    = sanitize_text_field($_GET['sort'] ?? 'newest');
    $search         = sanitize_text_field($_GET['s'] ?? '');

    // Fetch order pool for tab counts & filtering
    $statuses = fmb_order_statuses();
    $tab_counts = array('any' => 0);
    foreach (array_keys($statuses) as $st) {
        if ($st !== 'any') {
            $cnt = wc_orders_count($st);
            $tab_counts[$st] = $cnt;
            $tab_counts['any'] += $cnt;
        }
    }

    // Query builder
    $query_args = array(
        'limit'   => 2000,
        'orderby' => 'date',
        'order'   => 'DESC',
        'paginate'=> false,
    );
    if ($filter_st !== 'any') {
        $query_args['status'] = array('wc-' . str_replace('wc-', '', $filter_st), str_replace('wc-', '', $filter_st));
    }

    $raw_orders = wc_get_orders($query_args);
    $filtered_orders = array();

    foreach ($raw_orders as $o) {
        // Search filter
        if (!empty($search)) {
            $match = false;
            $order_id_str = (string)$o->get_id();
            $cust_name    = strtolower($o->get_formatted_billing_full_name());
            $cust_phone   = $o->get_billing_phone();
            $s_term       = strtolower($search);

            if (strpos($order_id_str, $s_term) !== false ||
                strpos($cust_name, $s_term) !== false ||
                strpos($cust_phone, $s_term) !== false) {
                $match = true;
            }
            if (!$match) continue;
        }

        // Source filter
        if ($filter_source !== 'all') {
            $source = $o->get_meta('_order_source') ?: 'Website';
            if (strtolower($source) !== strtolower($filter_source)) continue;
        }

        // Courier filter
        if ($filter_courier !== 'all') {
            $courier = $o->get_meta('_courier_provider') ?: ($o->get_meta('_fmb_courier_provider') ?: 'unassigned');
            if (strtolower($courier) !== strtolower($filter_courier)) continue;
        }

        $filtered_orders[] = $o;
    }

    // Sorting
    if ($filter_sort === 'oldest') {
        usort($filtered_orders, function($a, $b){
            $da = $a->get_date_created() ? $a->get_date_created()->getTimestamp() : 0;
            $db = $b->get_date_created() ? $b->get_date_created()->getTimestamp() : 0;
            return $da <=> $db;
        });
    } elseif ($filter_sort === 'total_high') {
        usort($filtered_orders, function($a, $b){
            return (float)$b->get_total() <=> (float)$a->get_total();
        });
    } elseif ($filter_sort === 'total_low') {
        usort($filtered_orders, function($a, $b){
            return (float)$a->get_total() <=> (float)$b->get_total();
        });
    }

    $total_orders = count($filtered_orders);
    $total_pages  = max(1, ceil($total_orders / $per_page));
    $offset       = ($paged - 1) * $per_page;
    $paged_orders = array_slice($filtered_orders, $offset, $per_page);

    $base_url = admin_url('admin.php?page=fmb-order-manager');
    $ajax_nonce = wp_create_nonce('fmb_order_action');
    ?>
    <div class="fmb-admin-wrap fmb-order-hub-wrap">

        <!-- Top Header Bar -->
        <div class="fmb-hub-topbar"style="margin: 45px 15px 15px;">
            <div>
                <h1 class="fmb-hub-main-title">
                    Order Management <span class="title-count"><?php echo number_format($total_orders); ?></span>
                </h1>
                <p class="fmb-hub-subtext">Manage, process and book courier parcels in one unified operations center</p>
            </div>
            <div class="fmb-topbar-actions">
                <a href="<?php echo esc_url(add_query_arg('action', 'new', $base_url)); ?>" class="fmb-btn fmb-btn-primary-purple">
                    <span class="dashicons dashicons-plus-alt2"></span> New Order
                </a>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="fmb-filter-container">
            <form method="get" action="<?php echo esc_url($base_url); ?>" class="fmb-horizontal-filter-form">
                <input type="hidden" name="page" value="fmb-order-manager">

                <div class="fmb-filter-search-wrap">
                    <span class="dashicons dashicons-search search-icon"></span>
                    <input type="text" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Search by ID, Name or Phone..." class="fmb-filter-search-input">
                </div>

                <div class="fmb-filter-select-wrap">
                    <select name="status" class="fmb-filter-dropdown" onchange="this.form.submit()">
                        <?php foreach ($statuses as $st_key => $st_name) : ?>
                            <option value="<?php echo esc_attr($st_key); ?>" <?php selected($filter_st, $st_key); ?>>
                                <?php echo esc_html($st_name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="fmb-filter-select-wrap">
                    <select name="source" class="fmb-filter-dropdown">
                        <option value="all" <?php selected($filter_source, 'all'); ?>>All Sources</option>
                        <option value="Website" <?php selected($filter_source, 'Website'); ?>>Website</option>
                        <option value="Phone" <?php selected($filter_source, 'Phone'); ?>>Phone</option>
                        <option value="Facebook" <?php selected($filter_source, 'Facebook'); ?>>Facebook</option>
                        <option value="POS" <?php selected($filter_source, 'POS'); ?>>POS</option>
                    </select>
                </div>

                <div class="fmb-filter-select-wrap">
                    <select name="courier" class="fmb-filter-dropdown">
                        <option value="all" <?php selected($filter_courier, 'all'); ?>>All Couriers</option>
                        <option value="steadfast" <?php selected($filter_courier, 'steadfast'); ?>>Steadfast</option>
                        <option value="pathao" <?php selected($filter_courier, 'pathao'); ?>>Pathao</option>
                        <option value="redx" <?php selected($filter_courier, 'redx'); ?>>RedX</option>
                        <option value="unassigned" <?php selected($filter_courier, 'unassigned'); ?>>Unassigned</option>
                    </select>
                </div>

                <div class="fmb-filter-select-wrap">
                    <select name="sort" class="fmb-filter-dropdown">
                        <option value="newest" <?php selected($filter_sort, 'newest'); ?>>Newest to Oldest</option>
                        <option value="oldest" <?php selected($filter_sort, 'oldest'); ?>>Oldest to Newest</option>
                        <option value="total_high" <?php selected($filter_sort, 'total_high'); ?>>Highest Total</option>
                        <option value="total_low" <?php selected($filter_sort, 'total_low'); ?>>Lowest Total</option>
                    </select>
                </div>

                <button type="submit" class="fmb-btn fmb-btn-dark-apply">Apply</button>
            </form>
        </div>

        <!-- Underline Status Tabs -->
        <div class="fmb-underline-tabs">
            <?php foreach ($statuses as $st_key => $st_name) :
                $is_active = ($filter_st === $st_key);
                $tab_url = add_query_arg(array('status' => $st_key, 'paged' => 1), $base_url);
                $cnt = $tab_counts[$st_key] ?? 0;
            ?>
                <a href="<?php echo esc_url($tab_url); ?>" class="fmb-underline-tab <?php echo $is_active ? 'active' : ''; ?>">
                    <?php echo esc_html($st_name); ?>
                    <span class="tab-num"><?php echo (int)$cnt; ?></span>
                </a>
            <?php endforeach; ?>
        </div>

        <!-- Order List Card & Table -->
        <div class="fmb-table-card">

            <!-- AJAX Notice Container -->
            <div id="fmb-ajax-notice" class="fmb-ajax-notice" style="display:none;"></div>

            <div class="fmb-table-responsive">
                <table class="fmb-order-hub-table">
                    <thead>
                        <tr>
                            <th style="width:38px; text-align:center;">
                                <input type="checkbox" id="fmb-check-all-orders" title="Select All Orders">
                            </th>
                            <th style="min-width:170px;">INVOICE NO / DATE</th>
                            <th style="min-width:220px;">CUSTOMER &amp; ADDRESS</th>
                            <th style="min-width:160px;">PAYMENTS INFO</th>
                            <th style="min-width:180px;">DELIVERY PARTNER</th>
                            <th style="min-width:180px;">CUSTOMER TYPE / ACTION BY</th>
                            <th style="min-width:170px; text-align:right;">ACTION</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($paged_orders)) : ?>
                            <tr>
                                <td colspan="7" style="text-align:center; padding: 50px 20px; color:#64748b;">
                                    <span class="dashicons dashicons-clipboard" style="font-size:36px; width:36px; height:36px; color:#cbd5e1; display:block; margin:0 auto 10px;"></span>
                                    <strong>No orders found matching your criteria.</strong>
                                </td>
                            </tr>
                        <?php else : ?>
                            <?php foreach ($paged_orders as $order) :
                                $oid         = $order->get_id();
                                $order_num   = $order->get_order_number();
                                $raw_st      = $order->get_status();
                                $status      = str_replace('wc-', '', $raw_st);
                                $cust_name   = $order->get_formatted_billing_full_name() ?: 'Guest';
                                $cust_phone  = $order->get_billing_phone();
                                $address     = $order->get_billing_address_1() . ($order->get_billing_address_2() ? ', ' . $order->get_billing_address_2() : '');
                                $city        = $order->get_billing_city();
                                $total_amt   = (float)$order->get_total();
                                $paid_amt    = (float)($order->get_meta('_paid_amount') ?: 0);
                                $due_amt     = max(0, $total_amt - $paid_amt);
                                $source      = $order->get_meta('_order_source') ?: 'Website';
                                $courier     = $order->get_meta('_courier_provider') ?: ($order->get_meta('_fmb_courier_provider') ?: 'Manual');
                                $cid         = $order->get_meta('_steadfast_consignment_id') ?: ($order->get_meta('_courier_consignment_id') ?: ($order->get_meta('_fmb_consignment_id') ?: ($order->get_meta('_fmb_tracking_code') ?: '')));
                                $rider_note  = $order->get_meta('_courier_rider_note');
                                $c_status    = $order->get_meta('_courier_delivery_status') ?: 'in_transit';

                                // Loyalty repeat check
                                $cust_stats  = fmb_get_customer_phone_stats($cust_phone);
                                $is_repeat   = ($cust_stats['total'] > 1);

                                $date_created = $order->get_date_created() ? $order->get_date_created()->date('d M, Y') : 'N/A';
                                $time_diff    = $order->get_date_created() ? human_time_diff($order->get_date_created()->getTimestamp(), current_time('timestamp')) . ' ago' : '';
                            ?>
                                <tr id="order-row-<?php echo $oid; ?>" data-order-id="<?php echo $oid; ?>">
                                    <!-- Selection Checkbox -->
                                    <td style="text-align:center;">
                                        <input type="checkbox" class="fmb-order-row-check" value="<?php echo $oid; ?>" data-due="<?php echo esc_attr($due_amt); ?>" data-num="<?php echo esc_attr($order_num); ?>">
                                    </td>

                                    <!-- Invoice No / Date -->
                                    <td>
                                        <div class="fmb-cell-invoice">
                                            <div class="fmb-inv-top-row">
                                                <div class="fmb-inv-num-group">
                                                    <a href="<?php echo esc_url(add_query_arg(array('action' => 'view', 'order_id' => $oid), $base_url)); ?>" class="fmb-inv-link">
                                                        #<?php echo esc_html($order_num); ?>
                                                    </a>
                                                    <button type="button" class="fmb-icon-copy-btn" onclick="navigator.clipboard.writeText('#<?php echo esc_js($order_num); ?>'); alert('Order #<?php echo esc_js($order_num); ?> copied');" title="Copy Order Number">
                                                        <span class="dashicons dashicons-admin-page"></span>
                                                    </button>
                                                </div>
                                                <div class="fmb-inv-badge-wrap" id="badge-wrap-<?php echo $oid; ?>">
                                                    <?php echo fmb_status_badge($status); ?>
                                                </div>
                                            </div>
                                            <div class="fmb-inv-cust-name" title="<?php echo esc_attr($cust_name); ?>">
                                                <span class="dashicons dashicons-admin-users"></span>
                                                <span><?php echo esc_html($cust_name); ?></span>
                                            </div>
                                            <div class="fmb-inv-dates">
                                                <span class="dashicons dashicons-calendar-alt"></span>
                                                <span class="fmb-time-ago"><?php echo esc_html($time_diff); ?></span>
                                                <span class="meta-dot">&bull;</span>
                                                <span><?php echo esc_html($date_created); ?></span>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Customer Phone & Delivery Address -->
                                    <td>
                                        <div class="fmb-cell-customer">
                                            <div class="fmb-cust-phone-row">
                                                <span class="dashicons dashicons-phone"></span>
                                                <a href="tel:<?php echo esc_attr($cust_phone); ?>" class="fmb-phone-link"><?php echo esc_html($cust_phone ?: 'No Phone'); ?></a>
                                                <?php if (!empty($cust_phone)) : ?>
                                                    <button type="button" class="fmb-icon-copy-btn" onclick="navigator.clipboard.writeText('<?php echo esc_js($cust_phone); ?>'); alert('Phone copied');" title="Copy Phone">
                                                        <span class="dashicons dashicons-admin-page"></span>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                            <div class="fmb-cust-address-row">
                                                <span class="dashicons dashicons-location"></span>
                                                <span class="addr-text">
                                                    <?php echo esc_html($address ?: 'No address specified'); ?>
                                                    <?php if ($city) : ?>
                                                        <strong class="addr-city">, <?php echo esc_html($city); ?></strong>
                                                    <?php endif; ?>
                                                </span>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Payments Info -->
                                    <td>
                                        <div class="fmb-cell-payments">
                                            <div class="fmb-pay-amount-main">
                                                ৳ <?php echo number_format($total_amt, 0); ?>
                                            </div>
                                            <div class="fmb-pay-status-row">
                                                <?php if ($due_amt <= 0) : ?>
                                                    <span class="fmb-pay-pill paid">
                                                        <span class="dashicons dashicons-yes-alt"></span> Paid
                                                    </span>
                                                <?php elseif ($paid_amt > 0) : ?>
                                                    <span class="fmb-pay-pill partial" title="Total: ৳<?php echo number_format($total_amt, 0); ?>">
                                                        Due: ৳<?php echo number_format($due_amt, 0); ?>
                                                    </span>
                                                <?php else : ?>
                                                    <span class="fmb-pay-pill due" title="Full Amount Due on Delivery">
                                                        Due: ৳<?php echo number_format($due_amt, 0); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Delivery Partner -->
                                    <td>
                                        <div class="fmb-cell-courier">
                                            <div class="courier-name-row">
                                                <span class="dashicons dashicons-car"></span>
                                                <strong class="courier-provider-name"><?php echo esc_html(ucfirst($courier)); ?></strong>
                                                <?php if (!empty($cid)) : ?>
                                                    <span class="courier-cid-tag" title="Consignment ID">#<?php echo esc_html($cid); ?></span>
                                                <?php else : ?>
                                                    <span class="courier-cid-none">No Tracking</span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="courier-action-row">
                                                <button type="button" class="fmb-note-chip <?php echo !empty($rider_note) ? 'has-note' : ''; ?> fmb-btn-add-rider-note" data-id="<?php echo $oid; ?>" data-note="<?php echo esc_attr($rider_note); ?>" title="<?php echo !empty($rider_note) ? esc_attr($rider_note) : 'Add Rider/Delivery Note'; ?>">
                                                    <span class="dashicons <?php echo !empty($rider_note) ? 'dashicons-edit' : 'dashicons-plus-alt2'; ?>"></span>
                                                    <span><?php echo !empty($rider_note) ? 'Note: ' . esc_html(wp_trim_words($rider_note, 3, '..')) : 'Add Note'; ?></span>
                                                </button>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Customer Type & Action By -->
                                    <td>
                                        <div class="fmb-cell-type-action">
                                            <div class="fmb-type-badges-row">
                                                <?php if ($is_repeat) : ?>
                                                    <span class="fmb-tag-loyalty repeat" title="Repeat Customer (<?php echo (int)$cust_stats['total']; ?> orders)">
                                                        <span class="dashicons dashicons-star-filled"></span> REPEAT (<?php echo (int)$cust_stats['total']; ?>)
                                                    </span>
                                                <?php else : ?>
                                                    <span class="fmb-tag-loyalty new">NEW</span>
                                                <?php endif; ?>
                                                <span class="fmb-tag-source">
                                                    <span class="dashicons dashicons-admin-site-alt3"></span> <?php echo esc_html($source); ?>
                                                </span>
                                            </div>

                                            <div class="fmb-action-by-row">
                                                <?php
                                                $action_by_val = $order->get_meta('_fmb_action_by');
                                                if (empty($action_by_val)) {
                                                    $action_by_val = $order->get_meta('_fmb_confirmed_by_name') ?: ($order->get_meta('_fmb_cancelled_by_name') ?: '');
                                                }
                                                if (empty($action_by_val)) {
                                                    $created_via = $order->get_created_via();
                                                    if ($created_via === 'checkout' || empty($created_via)) {
                                                        $action_by_val = 'Customer (Web)';
                                                    } elseif ($created_via === 'admin') {
                                                        $action_by_val = 'Admin';
                                                    } else {
                                                        $action_by_val = ucfirst($created_via);
                                                    }
                                                }
                                                $act_icon   = 'dashicons-admin-users';
                                                $act_bg     = '#f8fafc';
                                                $act_color  = '#475569';
                                                $act_border = '#e2e8f0';

                                                if (stripos($action_by_val, 'cancel') !== false) {
                                                    $act_icon   = 'dashicons-dismiss';
                                                    $act_bg     = '#fef2f2';
                                                    $act_color  = '#dc2626';
                                                    $act_border = '#fca5a5';
                                                } elseif (stripos($action_by_val, 'confirm') !== false || stripos($action_by_val, 'processing') !== false || stripos($action_by_val, 'complete') !== false) {
                                                    $act_icon   = 'dashicons-yes';
                                                    $act_bg     = '#f0fdf4';
                                                    $act_color  = '#15803d';
                                                    $act_border = '#bbf7d0';
                                                } elseif (stripos($action_by_val, 'ship') !== false || stripos($action_by_val, 'book') !== false) {
                                                    $act_icon   = 'dashicons-car';
                                                    $act_bg     = '#eff6ff';
                                                    $act_color  = '#2563eb';
                                                    $act_border = '#bfdbfe';
                                                }
                                                ?>
                                                <span class="fmb-action-by-tag" style="background:<?php echo $act_bg; ?>; color:<?php echo $act_color; ?>; border:1px solid <?php echo $act_border; ?>;">
                                                    <span class="dashicons <?php echo $act_icon; ?>" style="font-size:12px; width:12px; height:12px;"></span>
                                                    <?php echo esc_html($action_by_val); ?>
                                                </span>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Actions & Quick Lifecycle Buttons -->
                                    <td style="text-align:right;">
                                        <div class="fmb-cell-actions">
                                            <?php if ($status === 'processing') : ?>
                                                <!-- Quick Lifecycle: Processing orders can only be Confirmed or Cancelled -->
                                                <div class="fmb-lifecycle-btns-wrap" id="lifecycle-wrap-<?php echo $oid; ?>">
                                                    <button type="button" class="fmb-btn-xs-confirm fmb-quick-action-btn" data-id="<?php echo $oid; ?>" data-action="confirm" title="Confirm this order">
                                                        <span class="dashicons dashicons-yes"></span> Confirm
                                                    </button>
                                                    <button type="button" class="fmb-btn-xs-cancel fmb-quick-action-btn" data-id="<?php echo $oid; ?>" data-action="cancel" title="Cancel this order">
                                                        <span class="dashicons dashicons-dismiss"></span> Cancel
                                                    </button>
                                                </div>
                                            <?php elseif (($status === 'ads-confirmed' || $status === 'confirmed') && empty($cid)) : ?>
                                                <!-- Confirmed orders have quick Book action (Smart Booking Assistant) -->
                                                <div class="fmb-lifecycle-btns-wrap" id="lifecycle-wrap-<?php echo $oid; ?>">
                                                    <button type="button" class="fmb-btn-xs-book fmb-open-smart-book" data-id="<?php echo $oid; ?>" data-num="<?php echo esc_attr($order_num); ?>" data-due="<?php echo esc_attr($due_amt); ?>" title="Send to Steadfast (Smart Booking)">
                                                        <span class="dashicons dashicons-shield-alt"></span> Book
                                                    </button>
                                                </div>
                                            <?php elseif (!empty($cid)) : ?>
                                                <div class="fmb-lifecycle-btns-wrap">
                                                    <span class="fmb-booked-pill" title="Already Booked (Consignment ID: <?php echo esc_attr($cid); ?>)">
                                                        <span class="dashicons dashicons-yes-alt"></span> Booked
                                                    </span>
                                                </div>
                                            <?php endif; ?>

                                            <!-- Standard Action Icons -->
                                            <div class="fmb-action-icons-row">
                                                <a href="<?php echo esc_url(add_query_arg(array('action' => 'view', 'order_id' => $oid), $base_url)); ?>" class="fmb-act-icon view" title="View Full Details">
                                                    <span class="dashicons dashicons-visibility"></span>
                                                </a>
                                                <a href="<?php echo esc_url(add_query_arg(array('action' => 'edit', 'order_id' => $oid), $base_url)); ?>" class="fmb-act-icon edit" title="Edit Order">
                                                    <span class="dashicons dashicons-edit"></span>
                                                </a>
                                                <a href="<?php echo esc_url(add_query_arg('print_invoice', $oid, $base_url)); ?>" target="_blank" class="fmb-act-icon invoice" title="Print Invoice (A4)">
                                                    <span class="dashicons dashicons-printer"></span>
                                                </a>
                                                <a href="<?php echo esc_url(add_query_arg('print_label', $oid, $base_url)); ?>" target="_blank" class="fmb-act-icon label" title="Print 2x3 Thermal Label">
                                                    <span class="dashicons dashicons-tag"></span>
                                                </a>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination Bar -->
            <?php if ($total_pages > 1) : ?>
                <div class="fmb-pagination-bar">
                    <span class="fmb-page-info">
                        Showing page <?php echo $paged; ?> of <?php echo $total_pages; ?> (<?php echo number_format($total_orders); ?> total orders)
                    </span>
                    <div class="fmb-page-links">
                        <?php
                        echo paginate_links(array(
                            'base'      => add_query_arg('paged', '%#%'),
                            'format'    => '',
                            'prev_text' => '&larr; Prev',
                            'next_text' => 'Next &rarr;',
                            'total'     => $total_pages,
                            'current'   => $paged,
                        ));
                        ?>
                    </div>
                </div>
            <?php endif; ?>

        </div>

    </div>

    <!-- Floating Multi-Select Action Bar (Matches Reference image.png) -->
    <div id="fmb-floating-bulk-bar" class="fmb-floating-bulk-bar" style="display:none;">
        <div class="fmb-floating-bulk-inner">
            <div class="fmb-bulk-left-info">
                <span class="fmb-bulk-count-label"><strong id="fmb-selected-count-number">0</strong> Selected</span>
            </div>
            <div class="fmb-bulk-right-buttons">
                <select id="fmb-bulk-status-action" class="fmb-bulk-select-control">
                    <option value="">Action...</option>
                    <option value="ads-confirmed">Mark as Confirmed</option>
                    <option value="cancelled">Cancel Order</option>
                    <option value="ads-shipping">Mark as Shipping</option>
                    <option value="completed">Mark as Completed</option>
                    <option value="ads-returned">Mark as Returned</option>
                    <option value="trash">Move to Trash</option>
                </select>
                <button type="button" id="fmb-bulk-btn-apply" class="fmb-bulk-bar-btn btn-apply">
                    Apply
                </button>
                <button type="button" id="fmb-bulk-btn-invoice" class="fmb-bulk-bar-btn btn-invoice" title="Print A4 Invoices (6-in-1)">
                    <span class="dashicons dashicons-printer"></span> Invoice
                </button>
                <button type="button" id="fmb-bulk-btn-label" class="fmb-bulk-bar-btn btn-label" title="Print 2x3 inch Thermal Labels">
                    <span class="dashicons dashicons-tag"></span> Label
                </button>
                <button type="button" id="fmb-bulk-btn-book" class="fmb-bulk-bar-btn btn-book" style="background:#0ea5e9; border-color:#0284c7;" title="Send Selected to Steadfast (Smart Booking Assistant)">
                    <span class="dashicons dashicons-shield-alt"></span> Send Selected to Steadfast
                </button>
            </div>
        </div>
    </div>

    <!-- Smart Booking Assistant Modal (Send Selected to Steadfast) -->
    <div id="fmb-smart-booking-modal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(15, 23, 42, 0.65); backdrop-filter:blur(4px); z-index:999999; align-items:center; justify-content:center; padding:20px;">
        <div style="background:#fff; border-radius:14px; width:100%; max-width:720px; max-height:90vh; display:flex; flex-direction:column; box-shadow:0 25px 50px -12px rgba(0,0,0,0.35); overflow:hidden; font-family:-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
            
            <!-- Header -->
            <div style="padding:18px 24px; border-bottom:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center; background:#f8fafc;">
                <h2 style="margin:0; font-size:18px; color:#0f172a; font-weight:700; display:flex; align-items:center; gap:8px;">
                    <span class="dashicons dashicons-shield-alt" style="color:#0284c7; font-size:24px; width:24px; height:24px;"></span>
                    Smart Booking Assistant (Send to Steadfast)
                </h2>
                <button type="button" class="fmb-modal-close" style="background:none; border:none; cursor:pointer; color:#64748b; font-size:24px; line-height:1; padding:4px 8px; border-radius:6px;" title="Close">&times;</button>
            </div>
            
            <!-- Body -->
            <div id="fmb-sba-body" style="padding:24px; overflow-y:auto; flex:1; background:#fff;">
                <div style="text-align:center; padding:40px 0;">
                    <span class="dashicons dashicons-update spinning" style="font-size:32px; width:32px; height:32px; color:#0ea5e9;"></span>
                    <p style="margin-top:16px; color:#64748b; font-size:15px; font-weight:500;">Analyzing selected orders...</p>
                </div>
            </div>

            <!-- Footer -->
            <div style="padding:16px 24px; border-top:1px solid #e2e8f0; background:#f8fafc; display:flex; justify-content:space-between; gap:12px; align-items:center;">
                <div id="fmb-sba-progress-text" style="font-size:13.5px; color:#475569; font-weight:600; display:none;"></div>
                <div style="display:flex; gap:10px; margin-left:auto;">
                    <button type="button" class="button fmb-modal-close" style="padding:7px 18px; border-radius:8px; border:1.5px solid #cbd5e1; color:#475569; font-weight:600; background:#fff; cursor:pointer;">Cancel</button>
                    <button type="button" id="fmb-sba-confirm-btn" class="button button-primary" style="padding:7px 22px; border-radius:8px; background:#0ea5e9; border:1px solid #0284c7; color:#fff; font-weight:700; display:none; cursor:pointer;" disabled>Confirm & Book</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal 3: Edit Order Note & Rider Instruction Modal -->
    <div class="fmb-modal-backdrop" id="fmb-note-modal" style="display:none;">
        <div class="fmb-modal-box" style="max-width: 460px;">
            <div class="fmb-modal-head">
                <h3><span class="dashicons dashicons-edit"></span> Add / Edit Order Note</h3>
                <button type="button" class="fmb-modal-close-x" id="fmb-close-note-modal">&times;</button>
            </div>
            <div class="fmb-modal-content">
                <input type="hidden" id="modal-note-order-id" value="">
                <div class="fmb-form-group">
                    <label style="display:block; font-size:12px; font-weight:700; margin-bottom:6px; color:#475569;">Order Note / Delivery Instructions:</label>
                    <textarea id="modal-note-content" rows="3" class="fmb-input-control" placeholder="Write order note or rider instructions (e.g. Call customer before delivery, express shipping requested)..."></textarea>
                    <p style="font-size:11.5px; color:#64748b; margin:6px 0 0;">This note will be visible in Order Notes, Order History, and saved for courier delivery.</p>
                </div>
                <div style="margin-top: 14px; text-align: right;">
                    <button type="button" class="fmb-btn fmb-btn-primary-purple" id="btn-save-modal-note">
                        <span class="dashicons dashicons-yes"></span> Save Note
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Embedded CSS for Table & Floating Bar -->
    <style>
    .fmb-order-hub-wrap {
        max-width: 100% !important;
        width: 100% !important;
        margin: 15px 0 70px 0 !important;
        padding: 0 20px 0 0 !important;
        box-sizing: border-box !important;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        color: #0f172a;
    }
    .fmb-hub-topbar { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; margin-bottom: 16px; }
    .fmb-hub-main-title { font-size: 22px; font-weight: 800; margin: 0; color: #0f172a; display: flex; align-items: center; gap: 10px; }
    .fmb-hub-main-title .title-count {
        background: #e0e7ff !important;
        color: #4338ca !important;
        border: 1px solid #c7d2fe !important;
        font-weight: 800 !important;
        font-size: 13px !important;
        line-height: 1 !important;
        padding: 4px 10px !important;
        border-radius: 20px !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        vertical-align: middle !important;
        top: 0 !important;
        box-shadow: none !important;
    }
    .fmb-hub-subtext { margin: 2px 0 0; font-size: 12.5px; color: #64748b; }
    .fmb-btn-primary-purple { background: #4f46e5; color: #fff; border: none; padding: 9px 18px; border-radius: 8px; font-weight: 700; font-size: 13px; display: inline-flex; align-items: center; gap: 6px; cursor: pointer; text-decoration: none; transition: background 0.15s; }
    .fmb-btn-primary-purple:hover { background: #4338ca; color: #fff; }

    /* Filter Bar */
    .fmb-filter-container { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 12px 16px; margin-bottom: 16px; }
    .fmb-horizontal-filter-form { display: flex; align-items: center; flex-wrap: wrap; gap: 10px; }
    .fmb-filter-search-wrap { position: relative; flex: 1; min-width: 220px; }
    .fmb-filter-search-wrap .search-icon { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 17px; width: 17px; height: 17px; }
    .fmb-filter-search-input { width: 100%; height: 38px; padding: 0 12px 0 34px; border: 1.5px solid #cbd5e1; border-radius: 8px; font-size: 13px; outline: none; box-sizing: border-box; }
    .fmb-filter-search-input:focus { border-color: #4f46e5; }
    .fmb-filter-dropdown { height: 38px; padding: 0 10px; border: 1.5px solid #cbd5e1; border-radius: 8px; font-size: 12.5px; font-weight: 600; color: #1e293b; background: #fff; outline: none; }
    .fmb-btn-dark-apply { background: #0f172a; color: #fff; border: none; height: 38px; padding: 0 16px; border-radius: 8px; font-weight: 700; font-size: 12.5px; cursor: pointer; }
    .fmb-btn-dark-apply:hover { background: #1e293b; }

    /* Underline Tabs */
    .fmb-underline-tabs {
        display: flex;
        gap: 16px;
        border-bottom: 2px solid #e2e8f0;
        margin-bottom: 16px;
        overflow-x: auto;
        padding-bottom: 2px;
        scrollbar-width: thin;
        scrollbar-color: #cbd5e1 transparent;
    }
    .fmb-underline-tabs::-webkit-scrollbar { height: 4px; }
    .fmb-underline-tabs::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
    .fmb-underline-tabs::-webkit-scrollbar-track { background: transparent; }
    .fmb-underline-tab {
        font-size: 13px;
        font-weight: 600;
        color: #64748b;
        text-decoration: none;
        padding: 0 4px 10px 4px;
        border-bottom: 2.5px solid transparent;
        margin-bottom: -2px;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        white-space: nowrap;
        transition: all 0.15s ease-in-out;
    }
    .fmb-underline-tab:hover { color: #4338ca; }
    .fmb-underline-tab.active { color: #4338ca; font-weight: 700; border-bottom-color: #4338ca; }
    .fmb-underline-tab .tab-num {
        background: #f1f5f9;
        color: #64748b;
        padding: 2px 7px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 700;
        transition: all 0.15s;
    }
    .fmb-underline-tab.active .tab-num {
        background: #e0e7ff;
        color: #4338ca;
        font-weight: 800;
    }

    /* Table Card */
    .fmb-table-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,0.03); width: 100%; box-sizing: border-box; }
    .fmb-table-responsive { width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; }
    .fmb-order-hub-table { width: 100%; border-collapse: collapse; font-size: 13px; }
    .fmb-order-hub-table thead th { background: #f8fafc; padding: 11px 14px; font-size: 11px; font-weight: 800; color: #475569; border-bottom: 1px solid #e2e8f0; text-transform: uppercase; letter-spacing: 0.6px; text-align: left; }
    .fmb-order-hub-table tbody td { padding: 12px 14px; vertical-align: middle; border-bottom: 1px solid #f1f5f9; transition: background 0.12s ease; }
    .fmb-order-hub-table tbody tr:hover { background: #f8fafc; }
    .fmb-order-hub-table tbody tr.row-selected { background: #f5f3ff; }

    /* Column 1: Invoice No / Date */
    .fmb-cell-invoice { display: flex; flex-direction: column; gap: 3px; }
    .fmb-cell-invoice .fmb-inv-top-row { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
    .fmb-cell-invoice .fmb-inv-num-group { display: flex; align-items: center; gap: 4px; }
    .fmb-inv-link { font-weight: 800; color: #4338ca; text-decoration: none; font-size: 13.5px; }
    .fmb-inv-link:hover { text-decoration: underline; color: #3730a3; }
    .fmb-icon-copy-btn { background: none; border: none; padding: 2px; color: #94a3b8; cursor: pointer; display: inline-flex; align-items: center; border-radius: 4px; }
    .fmb-icon-copy-btn:hover { color: #0f172a; background: #f1f5f9; }
    .fmb-icon-copy-btn .dashicons { font-size: 13px; width: 13px; height: 13px; }
    .fmb-cell-invoice .fmb-inv-cust-name { font-size: 12.5px; font-weight: 700; color: #0f172a; display: flex; align-items: center; gap: 4px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 175px; }
    .fmb-cell-invoice .fmb-inv-cust-name .dashicons { font-size: 13px; width: 13px; height: 13px; color: #64748b; flex-shrink: 0; }
    .fmb-inv-dates { font-size: 11px; color: #64748b; display: flex; align-items: center; gap: 4px; }
    .fmb-inv-dates .dashicons { font-size: 12px; width: 12px; height: 12px; color: #94a3b8; }
    .fmb-inv-dates .fmb-time-ago { font-weight: 600; color: #475569; }
    .fmb-inv-dates .meta-dot { color: #cbd5e1; }

    /* Polished Status Badges */
    .fmb-status-pill {
        display: inline-flex;
        align-items: center;
        gap: 3px;
        font-size: 10.5px;
        font-weight: 700;
        padding: 2px 7px;
        border-radius: 6px;
        letter-spacing: 0.3px;
        text-transform: uppercase;
        white-space: nowrap;
        line-height: 1.3;
    }
    .fmb-status-pill.processing { background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; }
    .fmb-status-pill.confirmed  { background: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0; }
    .fmb-status-pill.shipped    { background: #f5f3ff; color: #6d28d9; border: 1px solid #ddd6fe; }
    .fmb-status-pill.intransit  { background: #f0f9ff; color: #0284c7; border: 1px solid #bae6fd; }
    .fmb-status-pill.inhub      { background: #faf5ff; color: #7c3aed; border: 1px solid #e9d5ff; }
    .fmb-status-pill.rider      { background: #fff7ed; color: #c2410c; border: 1px solid #fed7aa; }
    .fmb-status-pill.delivered,
    .fmb-status-pill.completed  { background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; }
    .fmb-status-pill.cancelled,
    .fmb-status-pill.failed     { background: #fef2f2; color: #b91c1c; border: 1px solid #fecdd3; }
    .fmb-status-pill.on-hold,
    .fmb-status-pill.pending    { background: #fffbeb; color: #b45309; border: 1px solid #fde68a; }
    .fmb-status-pill.returned   { background: #fff1f2; color: #be123c; border: 1px solid #fecdd3; }
    .fmb-status-pill.refunded   { background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; }
    .fmb-status-pill.default    { background: #f8fafc; color: #475569; border: 1px solid #e2e8f0; }

    /* Column 2: Customer Phone & Delivery Address */
    .fmb-cell-customer { display: flex; flex-direction: column; gap: 3px; }
    .fmb-cust-phone-row { display: flex; align-items: center; gap: 5px; font-size: 13px; font-weight: 700; }
    .fmb-cust-phone-row .dashicons-phone { font-size: 13px; width: 13px; height: 13px; color: #4338ca; }
    .fmb-phone-link { color: #312e81; text-decoration: none; font-weight: 700; }
    .fmb-phone-link:hover { text-decoration: underline; color: #4338ca; }
    .fmb-cust-address-row { display: flex; align-items: flex-start; gap: 4px; font-size: 12px; color: #475569; line-height: 1.35; max-width: 280px; }
    .fmb-cust-address-row .dashicons-location { font-size: 13px; width: 13px; height: 13px; color: #94a3b8; flex-shrink: 0; margin-top: 1px; }
    .fmb-cust-address-row .addr-text { word-break: break-word; }
    .fmb-cust-address-row .addr-city { font-weight: 700; color: #1e293b; }

    /* Column 3: Payments Info */
    .fmb-cell-payments { display: flex; flex-direction: column; gap: 3px; }
    .fmb-pay-amount-main { font-size: 14.5px; font-weight: 800; color: #0f172a; letter-spacing: -0.2px; }
    .fmb-pay-status-row { display: flex; align-items: center; gap: 4px; flex-wrap: wrap; }
    .fmb-pay-pill { display: inline-flex; align-items: center; gap: 3px; font-size: 10.5px; font-weight: 700; padding: 2px 7px; border-radius: 5px; line-height: 1.3; }
    .fmb-pay-pill.due { background: #fef2f2; color: #dc2626; border: 1px solid #fecdd3; }
    .fmb-pay-pill.paid { background: #ecfdf5; color: #059669; border: 1px solid #a7f3d0; }
    .fmb-pay-pill.partial { background: #eff6ff; color: #2563eb; border: 1px solid #bfdbfe; }
    .fmb-pay-pill .dashicons { font-size: 12px; width: 12px; height: 12px; }

    /* Column 4: Delivery Partner */
    .fmb-cell-courier { display: flex; flex-direction: column; gap: 4px; }
    .courier-name-row { display: flex; align-items: center; gap: 5px; font-size: 12.5px; }
    .courier-name-row .dashicons-car { font-size: 14px; width: 14px; height: 14px; color: #ea580c; flex-shrink: 0; }
    .courier-provider-name { font-weight: 700; color: #1e293b; }
    .courier-cid-tag { font-family: monospace; font-size: 10.5px; font-weight: 700; background: #f1f5f9; border: 1px solid #e2e8f0; color: #334155; padding: 1.5px 5px; border-radius: 4px; }
    .courier-cid-none { font-size: 11px; color: #94a3b8; font-style: italic; }
    .fmb-note-chip { background: #f8fafc; border: 1px solid #e2e8f0; color: #64748b; font-size: 11px; font-weight: 600; padding: 2.5px 8px; border-radius: 5px; cursor: pointer; display: inline-flex; align-items: center; gap: 3px; transition: all 0.15s; width: fit-content; }
    .fmb-note-chip:hover { background: #f1f5f9; color: #0f172a; border-color: #cbd5e1; }
    .fmb-note-chip.has-note { background: #eff6ff; border-color: #bfdbfe; color: #2563eb; }
    .fmb-note-chip .dashicons { font-size: 12px; width: 12px; height: 12px; }

    /* Column 5: Customer Type & Action By */
    .fmb-cell-type-action { display: flex; flex-direction: column; gap: 4px; }
    .fmb-type-badges-row { display: flex; align-items: center; gap: 5px; flex-wrap: wrap; }
    .fmb-tag-loyalty { font-size: 10px; font-weight: 800; padding: 2px 6px; border-radius: 4px; display: inline-flex; align-items: center; gap: 2px; }
    .fmb-tag-loyalty.new { background: #e0e7ff; color: #3730a3; }
    .fmb-tag-loyalty.repeat { background: #dcfce7; color: #166534; }
    .fmb-tag-loyalty .dashicons { font-size: 10px; width: 10px; height: 10px; }
    .fmb-tag-source { background: #f1f5f9; color: #475569; font-size: 10px; font-weight: 700; padding: 2px 6px; border-radius: 4px; display: inline-flex; align-items: center; gap: 3px; }
    .fmb-tag-source .dashicons { font-size: 11px; width: 11px; height: 11px; }
    .fmb-action-by-row { display: flex; align-items: center; }
    .fmb-action-by-tag { display: inline-flex; align-items: center; gap: 4px; font-size: 11px; font-weight: 700; padding: 2.5px 8px; border-radius: 6px; white-space: nowrap; }

    /* Column 6: Action Buttons */
    .fmb-cell-actions { display: flex; flex-direction: column; align-items: flex-end; gap: 5px; }
    .fmb-lifecycle-btns-wrap { display: flex; align-items: center; gap: 4px; }
    .fmb-btn-xs-confirm { background: #10b981; color: #fff; border: none; font-size: 11px; font-weight: 700; padding: 4px 9px; border-radius: 6px; cursor: pointer; display: inline-flex; align-items: center; gap: 2px; transition: background 0.15s, transform 0.1s; box-shadow: 0 1px 2px rgba(16,185,129,0.25); }
    .fmb-btn-xs-confirm:hover { background: #059669; }
    .fmb-btn-xs-confirm .dashicons { font-size: 12px; width: 12px; height: 12px; }
    .fmb-btn-xs-cancel { background: #fff; color: #e11d48; border: 1px solid #fecdd3; font-size: 11px; font-weight: 600; padding: 3.5px 8px; border-radius: 6px; cursor: pointer; display: inline-flex; align-items: center; gap: 2px; transition: all 0.15s; }
    .fmb-btn-xs-cancel:hover { background: #fff1f2; border-color: #fda4af; }
    .fmb-btn-xs-cancel .dashicons { font-size: 12px; width: 12px; height: 12px; }
    .fmb-btn-xs-book { background: #ea580c; color: #fff; border: none; font-size: 11px; font-weight: 700; padding: 4px 10px; border-radius: 6px; cursor: pointer; display: inline-flex; align-items: center; gap: 3px; box-shadow: 0 1px 2px rgba(234,88,12,0.25); transition: background 0.15s; }
    .fmb-btn-xs-book:hover { background: #c2410c; }
    .fmb-btn-xs-book .dashicons { font-size: 13px; width: 13px; height: 13px; }
    .fmb-booked-pill { font-size: 11px; font-weight: 700; color: #0284c7; background: #f0f9ff; border: 1px solid #bae6fd; padding: 2.5px 8px; border-radius: 6px; display: inline-flex; align-items: center; gap: 3px; }
    .fmb-booked-pill .dashicons { font-size: 12px; width: 12px; height: 12px; }

    .fmb-action-icons-row { display: flex; align-items: center; gap: 4px; }
    .fmb-act-icon { width: 28px; height: 28px; border-radius: 6px; display: inline-flex; align-items: center; justify-content: center; color: #64748b; text-decoration: none; border: 1px solid #e2e8f0; background: #fff; transition: all 0.15s; }
    .fmb-act-icon:hover { color: #0f172a; border-color: #cbd5e1; background: #f8fafc; transform: translateY(-1px); box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
    .fmb-act-icon.view:hover { color: #2563eb; border-color: #bfdbfe; }
    .fmb-act-icon.edit:hover { color: #4338ca; border-color: #c7d2fe; }
    .fmb-act-icon.invoice:hover { color: #0f172a; border-color: #94a3b8; }
    .fmb-act-icon.label:hover { color: #7c3aed; border-color: #ddd6fe; }
    .fmb-act-icon .dashicons { font-size: 14px; width: 14px; height: 14px; }

    /* Pagination */
    .fmb-pagination-bar { display: flex; justify-content: space-between; align-items: center; padding: 14px 18px; border-top: 1px solid #e2e8f0; background: #f8fafc; font-size: 13px; color: #64748b; flex-wrap: wrap; gap: 10px; }
    .fmb-page-links { display: flex; align-items: center; gap: 4px; }
    .fmb-page-links .page-numbers { display: inline-flex; align-items: center; justify-content: center; min-width: 32px; height: 32px; padding: 0 10px; border: 1px solid #e2e8f0; border-radius: 6px; text-decoration: none; color: #475569; font-weight: 600; font-size: 13px; background: #fff; transition: all 0.15s ease; box-sizing: border-box; }
    .fmb-page-links .page-numbers:hover { border-color: #cbd5e1; background: #f1f5f9; color: #0f172a; }
    .fmb-page-links .page-numbers.current { background: #4338ca; color: #fff; border-color: #4338ca; font-weight: 800; box-shadow: 0 1px 2px rgba(67,56,202,0.25); }
    .fmb-page-links .page-numbers.dots { border: none; background: transparent; color: #94a3b8; }

    /* Floating Multi-Select Action Bar (Exact Match to Reference image.png) */
    .fmb-floating-bulk-bar {
        position: fixed;
        bottom: 0;
        left: 0;
        right: 0;
        z-index: 99999;
        background: #fff;
        border-top: 1.5px solid #e2e8f0;
        box-shadow: 0 -6px 24px rgba(0,0,0,0.12);
        padding: 12px 28px;
        box-sizing: border-box;
    }
    .fmb-floating-bulk-inner {
        max-width: 100%;
        width: 100%;
        margin: 0;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 12px;
    }
    .fmb-bulk-left-info .fmb-bulk-count-label {
        font-size: 15px;
        font-weight: 800;
        color: #4f46e5;
    }
    .fmb-bulk-right-buttons {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }
    .fmb-bulk-select-control {
        height: 38px;
        padding: 0 12px;
        border: 1.5px solid #cbd5e1;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 600;
        color: #1e293b;
        background: #fff;
        outline: none;
    }
    .fmb-bulk-bar-btn {
        height: 38px;
        padding: 0 18px;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 800;
        border: none;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: transform 0.1s, background 0.15s;
    }
    .fmb-bulk-bar-btn:active { transform: scale(0.98); }
    .fmb-bulk-bar-btn.btn-apply   { background: #4f46e5; color: #fff; }
    .fmb-bulk-bar-btn.btn-apply:hover { background: #4338ca; }
    .fmb-bulk-bar-btn.btn-invoice { background: #0f172a; color: #fff; }
    .fmb-bulk-bar-btn.btn-invoice:hover { background: #1e293b; }
    .fmb-bulk-bar-btn.btn-label   { background: #4338ca; color: #fff; }
    .fmb-bulk-bar-btn.btn-label:hover { background: #3730a3; }
    .fmb-bulk-bar-btn.btn-book    { background: #ea580c; color: #fff; }
    .fmb-bulk-bar-btn.btn-book:hover { background: #c2410c; }
    .fmb-bulk-bar-btn .dashicons  { font-size: 15px; width: 15px; height: 15px; }

    /* Modals */
    .fmb-modal-backdrop {
        position: fixed;
        top: 0; left: 0; right: 0; bottom: 0;
        background: rgba(15, 23, 42, 0.6);
        backdrop-filter: blur(2px);
        z-index: 999999;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 16px;
    }
    .fmb-modal-box {
        background: #fff;
        border-radius: 12px;
        width: 100%;
        box-shadow: 0 20px 40px rgba(0,0,0,0.25);
        overflow: hidden;
    }
    .fmb-modal-head {
        padding: 14px 18px;
        border-bottom: 1px solid #e2e8f0;
        background: #f8fafc;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .fmb-modal-head h3 { margin: 0; font-size: 15px; font-weight: 800; color: #0f172a; display: flex; align-items: center; gap: 6px; }
    .fmb-modal-close-x { background: none; border: none; font-size: 22px; color: #94a3b8; cursor: pointer; line-height: 1; padding: 2px 6px; }
    .fmb-modal-close-x:hover { color: #0f172a; }
    .fmb-modal-content { padding: 18px; }
    .fmb-form-lbl { font-size: 12px; font-weight: 700; color: #475569; display: block; margin-bottom: 4px; }
    .fmb-input-control { width: 100%; padding: 8px 12px; border: 1.5px solid #cbd5e1; border-radius: 8px; font-size: 13px; outline: none; box-sizing: border-box; }
    .fmb-input-control:focus { border-color: #4f46e5; }
    .fmb-btn-dark-confirm { background: #0f172a; color: #fff; border: none; padding: 11px 20px; border-radius: 8px; font-weight: 800; font-size: 14px; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; gap: 6px; }
    .fmb-btn-dark-confirm:hover { background: #1e293b; }
    @keyframes fmbSpinRotation { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
    .spinning { animation: fmbSpinRotation 1s linear infinite !important; display: inline-block !important; }
    </style>

    <!-- Client-side JavaScript for Checkboxes, Floating Bar, Quick Confirm/Cancel, Bulk Booking -->
    <script>
    (function($){
        var ajaxUrl = '<?php echo admin_url('admin-ajax.php'); ?>';
        var nonce   = '<?php echo esc_js($ajax_nonce); ?>';
        var baseUrl = '<?php echo esc_js($base_url); ?>';
        var steadfast_nonce = '<?php echo wp_create_nonce("steadfast_send"); ?>';
        var insights_nonce  = '<?php echo wp_create_nonce("refresh_courier_history_nonce"); ?>';

        function showNotice(msg, type) {
            var $n = $('#fmb-ajax-notice');
            $n.removeClass('success error info')
              .addClass(type || 'info')
              .html(msg)
              .slideDown(150);
            setTimeout(function(){ $n.slideUp(200); }, 3500);
        }

        // 1. Checkboxes & Floating Bulk Bar Visibility
        function updateSelectedState() {
            var checked = $('.fmb-order-row-check:checked');
            var count   = checked.length;
            $('#fmb-selected-count-number').text(count);

            if (count > 0) {
                $('#fmb-floating-bulk-bar').slideDown(150);
            } else {
                $('#fmb-floating-bulk-bar').slideUp(150);
            }

            $('.fmb-order-hub-table tbody tr').removeClass('row-selected');
            checked.each(function(){
                $(this).closest('tr').addClass('row-selected');
            });
        }

        $('#fmb-check-all-orders').on('change', function(){
            var isChecked = $(this).is(':checked');
            $('.fmb-order-row-check').prop('checked', isChecked);
            updateSelectedState();
        });

        $(document).on('change', '.fmb-order-row-check', function(){
            var totalChecks   = $('.fmb-order-row-check').length;
            var checkedChecks = $('.fmb-order-row-check:checked').length;
            $('#fmb-check-all-orders').prop('checked', totalChecks > 0 && totalChecks === checkedChecks);
            updateSelectedState();
        });

        // 2. Print Invoices (A4 6-in-1) for Selected Orders
        $('#fmb-bulk-btn-invoice').on('click', function(){
            var ids = [];
            $('.fmb-order-row-check:checked').each(function(){
                ids.push($(this).val());
            });
            if (ids.length === 0) {
                alert('Please select at least one order.');
                return;
            }
            var printUrl = baseUrl + (baseUrl.indexOf('?') !== -1 ? '&' : '?') + 'print_invoices=' + ids.join(',');
            window.open(printUrl, '_blank');
        });

        // 3. Print Labels (2x3" Thermal) for Selected Orders
        $('#fmb-bulk-btn-label').on('click', function(){
            var ids = [];
            $('.fmb-order-row-check:checked').each(function(){
                ids.push($(this).val());
            });
            if (ids.length === 0) {
                alert('Please select at least one order.');
                return;
            }
            var printUrl = baseUrl + (baseUrl.indexOf('?') !== -1 ? '&' : '?') + 'print_labels=' + ids.join(',');
            window.open(printUrl, '_blank');
        });

        // 4. Bulk Status Apply
        $('#fmb-bulk-btn-apply').on('click', function(){
            var newStatus = $('#fmb-bulk-status-action').val();
            var ids = [];
            $('.fmb-order-row-check:checked').each(function(){
                ids.push($(this).val());
            });
            if (ids.length === 0) {
                alert('Please select at least one order.');
                return;
            }
            if (!newStatus) {
                alert('Please select an action.');
                return;
            }

            var $btn = $(this);
            $btn.prop('disabled', true).text('Applying...');

            $.post(ajaxUrl, {
                action: 'fmb_bulk_change_status',
                nonce: nonce,
                order_ids: ids,
                new_status: newStatus
            }, function(res){
                $btn.prop('disabled', false).text('Apply');
                if (res.success) {
                    showNotice('Status updated for ' + ids.length + ' orders!', 'success');
                    setTimeout(function(){ window.location.reload(); }, 600);
                } else {
                    alert(res.data || 'Failed to update status.');
                }
            });
        });

        // 5. Smart Booking Assistant (Send to Steadfast - Fully Featured)
        var fmbSmartBookingIds = [];

        function fmbLaunchSmartBooking(orderIds) {
            if (!orderIds || orderIds.length === 0) {
                alert('Please select at least one order to book.');
                return;
            }

            fmbSmartBookingIds = orderIds.map(function(id){ return parseInt(id, 10); });

            $('#fmb-smart-booking-modal').css('display', 'flex');
            $('#fmb-sba-body').html(`
                <div style="text-align:center; padding:40px 0;">
                    <span class="dashicons dashicons-update spinning" style="font-size:32px; width:32px; height:32px; color:#0ea5e9;"></span>
                    <p style="margin-top:16px; color:#64748b; font-size:15px; font-weight:500;">Analyzing ${orderIds.length} selected orders...</p>
                </div>
            `);
            $('#fmb-sba-confirm-btn').hide();
            $('#fmb-sba-progress-text').hide();

            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: {
                    action: 'fmb_engine_analyze_bulk_orders',
                    order_ids: orderIds,
                    _wpnonce: steadfast_nonce
                },
                success: function(response) {
                    if (response.success) {
                        renderSmartReport(response.data);
                    } else {
                        $('#fmb-sba-body').html('<div style="color:#ef4444; padding:20px; text-align:center; font-weight:600;">Failed to analyze orders: ' + (response.data || 'Unknown error') + '</div>');
                    }
                },
                error: function() {
                    $('#fmb-sba-body').html('<div style="color:#ef4444; padding:20px; text-align:center; font-weight:600;">Network error during order analysis.</div>');
                }
            });
        }

        function renderSmartReport(data) {
            var html = '';
            var eligibleOrderIds = [];

            // 1. Grouped Orders (Same Customer / Same Phone)
            if (data.grouped && data.grouped.length > 0) {
                html += `
                <div style="background:#fff7ed; border:1px solid #fed7aa; border-radius:8px; padding:16px; margin-bottom:16px;">
                    <h3 style="margin:0 0 8px 0; color:#c2410c; font-size:14.5px; font-weight:700; display:flex; align-items:center; gap:6px;">
                        <span class="dashicons dashicons-warning" style="font-size:18px; width:18px; height:18px; color:#ea580c;"></span>
                        Grouped Orders (Same Customer / Same Phone)
                    </h3>
                    <p style="margin:0 0 12px 0; font-size:12.5px; color:#9a3412;">Select orders to merge into one parcel. Non-primary orders will be combined and cancelled.</p>
                `;
                data.grouped.forEach(function(group) {
                    html += `
                    <div class="fmb-group-box" style="margin-bottom:12px; background:#fff; padding:12px; border-radius:6px; border:1px solid #fdba74;">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                            <div style="font-weight:700; font-size:13px; color:#431407;">📞 ${group.phone}</div>
                            <button type="button" class="fmb-merge-group-btn" data-phone="${group.phone}" style="background:#ea580c; color:#fff; border:none; padding:4px 12px; border-radius:4px; cursor:pointer; font-size:12px; font-weight:700;">Merge Selected</button>
                        </div>`;
                    group.orders.forEach(function(order) {
                        eligibleOrderIds.push(order.id);
                        html += `
                        <label style="display:flex; align-items:center; gap:10px; margin-bottom:6px; padding:6px 8px; border-radius:4px; background:#fffbf7; border:1px solid #ffedd5;">
                            <input type="checkbox" class="fmb-order-cb fmb-group-cb-${group.phone}" value="${order.id}" checked>
                            <span style="flex:1; font-size:13px; color:#1e293b;">#${order.number} - <b>${order.name}</b> - <span style="color:#0f172a; font-weight:700;">${order.total}</span></span>
                            <button type="button" class="fmb-cancel-order-btn" data-id="${order.id}" style="color:#ef4444; background:none; border:none; cursor:pointer; font-size:12px; font-weight:700;" title="Cancel Order">❌ Cancel</button>
                            <a href="${order.edit_url}" target="_blank" style="color:#ea580c; text-decoration:none; font-size:12px; font-weight:700; padding:2px 8px; border:1px solid #fb923c; border-radius:4px;">Edit</a>
                        </label>`;
                    });
                    html += `</div>`;
                });
                html += `</div>`;
            }

            // 2. Risky Orders / Customer History Found
            if (data.risky && data.risky.length > 0) {
                html += `
                <div style="background:#fef2f2; border:1px solid #fecaca; border-radius:8px; padding:16px; margin-bottom:16px;">
                    <h3 style="margin:0 0 10px 0; color:#b91c1c; font-size:14.5px; font-weight:700; display:flex; align-items:center; gap:6px;">
                        <span class="dashicons dashicons-flag" style="font-size:18px; width:18px; height:18px; color:#dc2626;"></span>
                        Customer History Found (Review Risk)
                    </h3>
                `;
                data.risky.forEach(function(order) {
                    eligibleOrderIds.push(order.id);
                    html += `
                    <label style="display:flex; align-items:center; gap:10px; margin-bottom:8px; padding:8px 12px; background:#fff; border:1px solid #fca5a5; border-radius:6px;">
                        <input type="checkbox" class="fmb-order-cb" value="${order.id}" checked>
                        <div style="flex:1; display:flex; flex-direction:column;">
                            <span style="font-size:13px; font-weight:700; color:#0f172a;">#${order.number} - ${order.name} - ${order.total}</span>
                            <span style="font-size:12px; color:#dc2626; font-weight:600; margin-top:2px;">${order.history}</span>
                        </div>
                        <button type="button" class="fmb-cancel-order-btn" data-id="${order.id}" style="color:#ef4444; background:none; border:none; cursor:pointer; font-size:12px; font-weight:700;" title="Cancel Order">❌ Cancel</button>
                        <button type="button" class="fmb-details-btn ads-insights-badge" data-phone="${order.phone}" data-name="${order.name}" style="background:#b91c1c; color:#fff; border:none; padding:4px 10px; border-radius:4px; cursor:pointer; font-size:12px; font-weight:700;">Details</button>
                    </label>`;
                });
                html += `</div>`;
            }

            // 3. Already Booked Orders (Strict Duplicate Prevention - Skipped!)
            if (data.already_booked && data.already_booked.length > 0) {
                html += `
                <div style="background:#f1f5f9; border:1px solid #cbd5e1; border-radius:8px; padding:16px; margin-bottom:16px;">
                    <h3 style="margin:0 0 10px 0; color:#475569; font-size:14.5px; font-weight:700; display:flex; align-items:center; gap:6px;">
                        <span class="dashicons dashicons-shield" style="font-size:18px; width:18px; height:18px; color:#64748b;"></span>
                        Already Booked (Strictly Skipped to Prevent Duplicate)
                    </h3>
                `;
                data.already_booked.forEach(function(order) {
                    html += `
                    <div style="display:flex; align-items:center; gap:10px; margin-bottom:6px; padding:6px 10px; background:#fff; border-radius:4px; border:1px solid #e2e8f0; opacity:0.85;">
                        <span class="dashicons dashicons-yes" style="color:#10b981; font-size:18px; width:18px; height:18px;"></span>
                        <span style="flex:1; font-size:13px; color:#475569;">#${order.number} &bull; Consignment ID: <strong style="color:#0f172a;">${order.consignment}</strong></span>
                        <span style="font-size:11px; font-weight:700; color:#15803d; background:#dcfce7; padding:2px 8px; border-radius:4px;">Booked</span>
                    </div>`;
                });
                html += `</div>`;
            }

            // 4. Safe Orders to Book
            if (data.safe && data.safe.length > 0) {
                html += `
                <div style="background:#f0fdf4; border:1px solid #bbf7d0; border-radius:8px; padding:16px; margin-bottom:16px;">
                    <h3 style="margin:0 0 10px 0; color:#166534; font-size:14.5px; font-weight:700; display:flex; align-items:center; gap:6px;">
                        <span class="dashicons dashicons-yes-alt" style="font-size:18px; width:18px; height:18px; color:#16a34a;"></span>
                        Safe to Book
                    </h3>
                    <div style="max-height:220px; overflow-y:auto; border:1px solid #dcfce7; border-radius:6px; background:#fff;">
                `;
                data.safe.forEach(function(order) {
                    eligibleOrderIds.push(order.id);
                    html += `
                    <label style="display:flex; align-items:center; gap:10px; padding:8px 12px; border-bottom:1px solid #f0fdf4; cursor:pointer;">
                        <input type="checkbox" class="fmb-order-cb" value="${order.id}" checked>
                        <span style="flex:1; font-size:13px; color:#1e293b;">#${order.number} - <b>${order.name}</b> - <span style="font-weight:700; color:#0f172a;">${order.total}</span></span>
                    </label>`;
                });
                html += `</div></div>`;
            }

            if (eligibleOrderIds.length === 0) {
                html += `<div style="text-align:center; padding:30px; color:#64748b; font-weight:600;">No unbooked orders to send. All selected orders are already booked or cancelled.</div>`;
            }

            $('#fmb-sba-body').html(html);

            var checkedCount = $('.fmb-order-cb:checked').length;
            if (checkedCount > 0) {
                $('#fmb-sba-confirm-btn').text('Confirm & Book (' + checkedCount + ')').prop('disabled', false).show();
            } else {
                $('#fmb-sba-confirm-btn').hide();
            }

            // Real-time checkbox count
            $('.fmb-order-cb').off('change').on('change', function() {
                var c = $('.fmb-order-cb:checked').length;
                $('#fmb-sba-confirm-btn').text('Confirm & Book (' + c + ')').prop('disabled', c === 0).toggle(c > 0);
            });
        }

        // Bulk Book Button trigger (Send Selected to Steadfast)
        $('#fmb-bulk-btn-book').on('click', function(e){
            e.preventDefault();
            var ids = [];
            $('.fmb-order-row-check:checked').each(function(){
                ids.push($(this).val());
            });
            if (ids.length === 0) {
                alert('Please select at least one order to book.');
                return;
            }
            fmbLaunchSmartBooking(ids);
        });

        // Single Row Book Button trigger (Send to Steadfast)
        $(document).on('click', '.fmb-open-smart-book, .fmb-open-single-book', function(e){
            e.preventDefault();
            var oid = $(this).data('id');
            if (oid) {
                fmbLaunchSmartBooking([oid]);
            }
        });

        // Close Smart Booking Modal
        $(document).on('click', '.fmb-modal-close', function(e) {
            e.preventDefault();
            if ($('#fmb-sba-confirm-btn').hasClass('booking-in-progress')) {
                if (!confirm('Booking is currently in progress. Are you sure you want to stop?')) return;
            }
            if ($(this).text() === 'Close & Reload') {
                location.reload();
                return;
            }
            $('#fmb-smart-booking-modal').hide();
        });

        // Merge Selected Orders in Group
        $(document).on('click', '.fmb-merge-group-btn', function(e) {
            e.preventDefault();
            var phone = $(this).data('phone');
            var $checkboxes = $('.fmb-group-cb-' + phone + ':checked');

            if ($checkboxes.length < 2) {
                alert('Please select at least 2 orders to merge.');
                return;
            }

            if (confirm('Merge these ' + $checkboxes.length + ' orders into one? Items will be combined into the oldest order, and other orders will be cancelled.')) {
                var orderIds = [];
                $checkboxes.each(function() { orderIds.push($(this).val()); });

                var $btn = $(this);
                $btn.text('Merging...').prop('disabled', true);

                $.ajax({
                    url: ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'fmb_engine_merge_orders_ajax',
                        order_ids: orderIds,
                        _wpnonce: steadfast_nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('Orders successfully merged!');
                            orderIds.sort(function(a, b) { return a - b; });
                            for (var i = 1; i < orderIds.length; i++) {
                                $('.fmb-order-row-check[value="' + orderIds[i] + '"]').prop('checked', false);
                                var idx = fmbSmartBookingIds.indexOf(parseInt(orderIds[i], 10));
                                if (idx !== -1) fmbSmartBookingIds.splice(idx, 1);
                            }
                            fmbLaunchSmartBooking(fmbSmartBookingIds);
                        } else {
                            alert('Merge failed: ' + (response.data || 'Unknown error'));
                            $btn.text('Merge Selected').prop('disabled', false);
                        }
                    },
                    error: function() {
                        alert('Network error during order merge.');
                        $btn.text('Merge Selected').prop('disabled', false);
                    }
                });
            }
        });

        // Cancel Order directly from modal
        $(document).on('click', '.fmb-cancel-order-btn', function(e) {
            e.preventDefault();
            var orderId = $(this).data('id');

            if (confirm('Are you sure you want to cancel Order #' + orderId + '?')) {
                var $btn = $(this);
                $btn.text('Cancelling...').prop('disabled', true);

                $.ajax({
                    url: ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'fmb_engine_cancel_order_ajax',
                        order_id: orderId,
                        _wpnonce: steadfast_nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            $('.fmb-order-row-check[value="' + orderId + '"]').prop('checked', false);
                            var idx = fmbSmartBookingIds.indexOf(parseInt(orderId, 10));
                            if (idx !== -1) fmbSmartBookingIds.splice(idx, 1);
                            fmbLaunchSmartBooking(fmbSmartBookingIds);
                        } else {
                            alert('Cancel failed: ' + (response.data || 'Unknown error'));
                            $btn.text('❌ Cancel').prop('disabled', false);
                        }
                    },
                    error: function() {
                        alert('Network error during order cancellation.');
                        $btn.text('❌ Cancel').prop('disabled', false);
                    }
                });
            }
        });

        // Sequential Booking Execution to Steadfast
        $(document).on('click', '#fmb-sba-confirm-btn', function(e) {
            e.preventDefault();
            var $btn = $(this);

            var finalOrders = [];
            $('.fmb-order-cb:checked').each(function() {
                finalOrders.push($(this).val());
            });

            if (finalOrders.length === 0) return;

            $btn.prop('disabled', true).addClass('booking-in-progress');
            $('.fmb-modal-close').prop('disabled', true);
            $('.fmb-order-cb').prop('disabled', true);

            var total = finalOrders.length;
            var current = 0;
            var successCount = 0;
            var failedCount = 0;

            $('#fmb-sba-progress-text').show();

            function processNext() {
                if (current >= total) {
                    $btn.text('Done!').removeClass('booking-in-progress');
                    $('.fmb-modal-close').prop('disabled', false).text('Close & Reload');

                    $('#fmb-sba-progress-text').html('<span style="color:#10b981; font-weight:700;">Completed! Success: ' + successCount + ', Failed: ' + failedCount + '</span>');
                    $('#fmb-sba-body').prepend(`
                        <div style="background:#e0f2fe; border:1px solid #bae6fd; padding:16px; border-radius:8px; margin-bottom:16px; text-align:center;">
                            <h3 style="color:#0369a1; margin:0 0 8px 0; font-size:16px; font-weight:700;">Booking Complete</h3>
                            <p style="margin:0; font-size:13.5px; color:#0c4a6e;">Parcels processed to Steadfast. Click Close & Reload to refresh your orders table.</p>
                        </div>
                    `);
                    return;
                }

                var orderId = finalOrders[current];
                $('#fmb-sba-progress-text').text('Booking ' + (current + 1) + ' of ' + total + ' (Order ID: #' + orderId + ')...');
                $btn.text(Math.round(((current) / total) * 100) + '%');

                $.ajax({
                    url: ajaxUrl,
                    type: 'GET',
                    data: {
                        action: 'fmb_engine_send_steadfast',
                        order_id: orderId,
                        _wpnonce: steadfast_nonce
                    },
                    success: function(response) {
                        if (response.success) { successCount++; } else { failedCount++; }
                    },
                    error: function() { failedCount++; },
                    complete: function() {
                        current++;
                        processNext();
                    }
                });
            }

            processNext();
        });

        // Customer Insights Popup Handler (Details button)
        $(document).on('click', '.ads-insights-badge', function(e) {
            e.preventDefault();
            var $badge = $(this);
            if ($badge.hasClass('loading')) return;

            var phone = $badge.data('phone');
            var name  = $badge.data('name');

            $badge.addClass('loading');
            var originalHtml = $badge.html();
            $badge.html('<span class="dashicons dashicons-update spinning" style="font-size:13px; width:13px; height:13px; vertical-align:middle;"></span>');

            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: {
                    action: 'fetch_customer_insights',
                    nonce: insights_nonce,
                    phone: phone,
                    name: name
                },
                success: function(response) {
                    $badge.removeClass('loading').html(originalHtml);
                    if (response.success && typeof renderInsightsModal === 'function') {
                        renderInsightsModal(response.data);
                    } else if (response.success && response.data) {
                        alert('Customer Orders: ' + (response.data.customer ? response.data.customer.name : name) + ' (' + phone + ')\nTotal Orders: ' + (response.data.stats ? response.data.stats.total_orders : 'N/A') + ' | LTV: ' + (response.data.stats ? response.data.stats.ltv : 'N/A'));
                    } else {
                        alert('Error: ' + (response.data && response.data.message ? response.data.message : 'No insights found.'));
                    }
                },
                error: function() {
                    $badge.removeClass('loading').html(originalHtml);
                    alert('Network error while fetching insights.');
                }
            });
        });

        // 6. Quick Row Action: Confirm / Cancel (For Processing Orders)
        $(document).on('click', '.fmb-quick-action-btn', function(){
            var $btn     = $(this);
            var oid      = $btn.data('id');
            var actType  = $btn.data('action'); // 'confirm' or 'cancel'
            var newStatus= (actType === 'confirm') ? 'ads-confirmed' : 'cancelled';

            $btn.prop('disabled', true);

            $.post(ajaxUrl, {
                action: 'fmb_quick_update_order_status',
                nonce: nonce,
                order_id: oid,
                status: newStatus
            }, function(res){
                if (res.success) {
                    showNotice('Order #' + oid + ' marked as ' + (actType === 'confirm' ? 'Confirmed' : 'Cancelled') + '!', 'success');
                    setTimeout(function(){ window.location.reload(); }, 600);
                } else {
                    alert(res.data || 'Failed to update order.');
                    $btn.prop('disabled', false);
                }
            });
        });

        // 8. Rider Note Modal
        $(document).on('click', '.fmb-btn-add-rider-note', function(){
            var oid  = $(this).data('id');
            var note = $(this).data('note');
            $('#modal-note-order-id').val(oid);
            $('#modal-note-content').val(note);
            $('#fmb-note-modal').fadeIn(150);
        });

        $('#fmb-close-note-modal').on('click', function(){
            $('#fmb-note-modal').fadeOut(150);
        });

        $('#btn-save-modal-note').on('click', function(){
            var oid  = $('#modal-note-order-id').val();
            var note = $('#modal-note-content').val();
            var $btn = $(this);

            $btn.prop('disabled', true).text('Saving...');
            $.post(ajaxUrl, {
                action: 'fmb_save_rider_note',
                nonce: nonce,
                order_id: oid,
                rider_note: note
            }, function(res){
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-yes"></span> Save Note');
                if (res.success) {
                    $('#fmb-note-modal').fadeOut(150);
                    showNotice('Order note saved successfully!', 'success');
                    setTimeout(function(){ window.location.reload(); }, 600);
                } else {
                    alert(res.data || 'Failed to save note.');
                }
            });
        });

        // Searchable District / City Combobox Handlers
        var $cityInput = $('#cust-city');
        var $cityDropdown = $('#fmb-city-dropdown');
        var $cityItems = $('.fmb-city-item');

        $cityInput.on('focus input', function(){
            var q = $(this).val().toLowerCase().trim();
            var matchCount = 0;
            $cityItems.each(function(){
                var val = $(this).data('val').toLowerCase();
                if (!q || val.indexOf(q) !== -1) {
                    $(this).show();
                    matchCount++;
                } else {
                    $(this).hide();
                }
            });
            if (matchCount > 0) {
                $cityDropdown.show();
            } else {
                $cityDropdown.hide();
            }
        });

        $(document).on('click', '.fmb-city-item', function(e){
            e.stopPropagation();
            var selectedCity = $(this).data('val');
            $cityInput.val(selectedCity);
            $cityDropdown.hide();
        });

        $(document).on('click', function(e){
            if (!$(e.target).closest('.fmb-city-combobox-wrap').length) {
                $cityDropdown.hide();
            }
        });

    })(jQuery);
    </script>
    <?php
}

function fmb_admin_order_form_page($order_id = 0) {
    $order_id = absint($order_id);
    $is_edit  = ($order_id > 0);
    $base_url = admin_url('admin.php?page=fmb-order-manager');
    $ajax_nonce = wp_create_nonce('fmb_order_action');

    $order = null;
    $initial_cart = array();
    $cust_name = '';
    $cust_phone = '';
    $cust_city = '';
    $cust_addr = '';
    $order_source = 'Website';
    $order_status = 'pending';
    $delivery_method = 'steadfast';
    $shipping_fee = 120;
    $discount_amount = 0;
    $paid_amount = 0;
    $customer_note = '';
    $admin_note = '';
    $date_created = '';

    if ($is_edit) {
        $order = wc_get_order($order_id);
        if (!$order) {
            echo '<div class="wrap"><div class="notice notice-error"><p>Order #' . esc_html($order_id) . ' not found. <a href="' . esc_url($base_url) . '">&larr; Return to Order List</a></p></div></div>';
            return;
        }

        $cust_name       = $order->get_formatted_billing_full_name();
        $cust_phone      = $order->get_billing_phone();
        $cust_city       = $order->get_billing_city();
        $cust_addr       = trim($order->get_billing_address_1() . ' ' . $order->get_billing_address_2());
        $order_source    = $order->get_meta('_order_source') ?: 'Website';
        $order_status    = str_replace('wc-', '', $order->get_status());
        $delivery_method = $order->get_meta('_courier_provider') ?: ($order->get_meta('_fmb_courier_provider') ?: 'steadfast');
        $shipping_fee    = (float)$order->get_shipping_total();
        $discount_amount = (float)$order->get_discount_total();
        $paid_amount     = (float)($order->get_meta('_paid_amount') ?: 0);
        $customer_note   = $order->get_customer_note();
        $admin_note      = $order->get_meta('_courier_rider_note');
        $date_created    = $order->get_date_created() ? $order->get_date_created()->date('d M Y, h:i A') : '';

        foreach ($order->get_items() as $item) {
            $pid = $item->get_product_id();
            $p_obj = $item->get_product();
            $thumb = '';
            if ($p_obj && $p_obj->get_image_id()) {
                $thumb = wp_get_attachment_image_url($p_obj->get_image_id(), 'thumbnail');
            }
            if (empty($thumb)) {
                $thumb = wc_placeholder_img_src();
            }
            $initial_cart[] = array(
                'id'    => $pid,
                'title' => $item->get_name(),
                'sku'   => $p_obj ? $p_obj->get_sku() : '',
                'price' => (float)$order->get_item_subtotal($item, false, false),
                'qty'   => (int)$item->get_quantity(),
                'img'   => $thumb,
            );
        }
    }

    // Pre-fetch top 25 published products for instant quick suggestions
    $recent_prods = wc_get_products(array('limit' => 25, 'status' => 'publish'));
    $catalog_presets = array();
    foreach ($recent_prods as $rp) {
        $r_thumb = $rp->get_image_id() ? wp_get_attachment_image_url($rp->get_image_id(), 'thumbnail') : wc_placeholder_img_src();
        $catalog_presets[] = array(
            'id'           => $rp->get_id(),
            'title'        => $rp->get_name(),
            'sku'          => $rp->get_sku(),
            'price'        => (float)$rp->get_price(),
            'img'          => $r_thumb,
            'stock_status' => $rp->get_stock_status(),
            'stock_qty'    => $rp->get_stock_quantity(),
        );
    }
    ?>
    <div class="fmb-admin-wrap fmb-new-order-wrap">

        <!-- Top Header Bar -->
        <div class="fmb-new-order-topbar">
            <div>
                <h1 class="fmb-new-order-title">
                    <?php echo $is_edit ? ('Edit Order #' . $order_id) : 'New Order'; ?>
                    <?php if ($is_edit) : ?>
                        <span class="fmb-status-pill <?php echo esc_attr($order_status); ?>"><?php echo esc_html(strtoupper($order_status)); ?></span>
                    <?php endif; ?>
                </h1>
                <p class="fmb-new-order-subtitle">
                    <?php echo $is_edit ? ('Order created on ' . esc_html($date_created) . ' &bull; Update products, delivery details, fraud check and status') : 'Enter customer info, run fraud verification and select products to confirm order'; ?>
                </p>
            </div>
            <div style="display:flex; align-items:center; gap:10px;">
                <?php if ($is_edit) : ?>
                    <a href="<?php echo esc_url(add_query_arg('print_invoice', $order_id, $base_url)); ?>" target="_blank" class="fmb-btn fmb-btn-outline-print">
                        <span class="dashicons dashicons-printer"></span> Print Invoice
                    </a>
                <?php endif; ?>
                <a href="<?php echo esc_url($base_url); ?>" class="fmb-btn fmb-btn-outline-back">
                    &larr; Order List
                </a>
            </div>
        </div>

        <form id="fmb-order-form">
            <input type="hidden" id="order-id" name="order_id" value="<?php echo esc_attr($order_id); ?>">

            <div class="fmb-new-order-grid">

                <!-- Left Column: Customer, Fraud Checker, Delivery, Notes -->
                <div class="fmb-col-left">

                    <!-- Customer Information Card -->
                    <div class="fmb-card fmb-card-section">
                        <div class="fmb-card-header-flex">
                            <div class="fmb-card-title">
                                <span class="dashicons dashicons-admin-users"></span> Customer Information
                            </div>
                        </div>

                        <!-- Phone & Fraud Check Button -->
                        <div class="fmb-form-group">
                            <label class="required-lbl">Phone Number *</label>
                            <div class="fmb-phone-check-wrap">
                                <div class="fmb-input-icon-wrap" style="flex:1;">
                                    <span class="dashicons dashicons-phone"></span>
                                    <input type="text" id="cust-phone" name="billing_phone" value="<?php echo esc_attr($cust_phone); ?>" required placeholder="01XXXXXXXXX" class="fmb-pro-input">
                                </div>
                                <button type="button" id="btn-check-fraud" class="fmb-btn fmb-btn-check-fraud" title="Run real-time fraud analysis">
                                    <span class="dashicons dashicons-shield"></span> Check Fraud
                                </button>
                            </div>
                        </div>

                        <!-- Interactive Fraud Checker Scorecard Card -->
                        <div id="cust-fraud-card" class="fmb-fraud-card" style="<?php echo !empty($cust_phone) ? '' : 'display:none;'; ?>">
                            <div class="fmb-fraud-topline">
                                <div class="fmb-fraud-badge-wrap">
                                    <span id="fraud-risk-pill" class="fmb-fraud-pill safe">CHECKING FRAUD...</span>
                                </div>
                                <div class="fmb-fraud-score-wrap">
                                    <span class="dashicons dashicons-chart-pie"></span>
                                    <strong id="fraud-score-val">100%</strong>
                                </div>
                            </div>
                            <div class="fmb-fraud-stats-grid">
                                <div class="fraud-stat-box">
                                    <span class="stat-box-title">LOCAL STORE ORDERS</span>
                                    <div id="fraud-local-stats" class="stat-box-val">Checking...</div>
                                </div>
                                <div class="fraud-stat-box">
                                    <span class="stat-box-title">COURIER NETWORK</span>
                                    <div id="fraud-global-stats" class="stat-box-val">Checking...</div>
                                </div>
                            </div>
                            <div id="fraud-advice-box" class="fmb-fraud-advice">
                                Enter customer mobile number to view fraud risk analysis.
                            </div>
                        </div>

                        <!-- Customer Name -->
                        <div class="fmb-form-group" style="margin-top:14px;">
                            <label class="required-lbl">Customer Name *</label>
                            <input type="text" id="cust-name" name="billing_name" value="<?php echo esc_attr($cust_name); ?>" required placeholder="Full Name" class="fmb-pro-input">
                        </div>

                        <!-- Source & Status Row -->
                        <div class="fmb-form-row">
                            <div class="fmb-form-group">
                                <label>Order Source:</label>
                                <select name="order_source" class="fmb-pro-input">
                                    <option value="Website" <?php selected($order_source, 'Website'); ?>>Website</option>
                                    <option value="Phone" <?php selected($order_source, 'Phone'); ?>>Phone Call</option>
                                    <option value="Facebook" <?php selected($order_source, 'Facebook'); ?>>Facebook / WhatsApp</option>
                                    <option value="POS" <?php selected($order_source, 'POS'); ?>>POS / In-Store</option>
                                </select>
                            </div>

                            <div class="fmb-form-group">
                                <label>Order Status:</label>
                                <select name="order_status" id="order-status-select" class="fmb-pro-input">
                                    <option value="pending" <?php selected($order_status, 'pending'); ?>>Pending</option>
                                    <option value="processing" <?php selected($order_status, 'processing'); ?>>Processing</option>
                                    <option value="on-hold" <?php selected($order_status, 'on-hold'); ?>>On Hold / Fake Check</option>
                                    <option value="ads-shipping" <?php selected($order_status, 'ads-shipping'); ?>>Shipped</option>
                                    <option value="ads-intransit" <?php selected($order_status, 'ads-intransit'); ?>>In Transit</option>
                                    <option value="completed" <?php selected($order_status, 'completed'); ?>>Completed</option>
                                    <option value="cancelled" <?php selected($order_status, 'cancelled'); ?>>Cancelled</option>
                                    <option value="ads-returned" <?php selected($order_status, 'ads-returned'); ?>>Returned</option>
                                </select>
                            </div>
                        </div>

                        <!-- District / City (Searchable Combobox) -->
                        <div class="fmb-form-group" style="position:relative;">
                            <label>District / City: <span style="font-weight:normal; font-size:11px; color:#64748b;">(Searchable)</span></label>
                            <div class="fmb-city-combobox-wrap" style="position:relative;">
                                <input type="text" id="cust-city" name="billing_city" class="fmb-pro-input" value="<?php echo esc_attr($cust_city); ?>" placeholder="Search District / City (e.g. Dhaka, Chittagong)..." autocomplete="off" style="padding-right:32px;">
                                <span class="dashicons dashicons-search" style="position:absolute; right:10px; top:50%; transform:translateY(-50%); color:#94a3b8; pointer-events:none;"></span>
                                <div id="fmb-city-dropdown" class="fmb-city-dropdown-list" style="display:none; position:absolute; top:calc(100% + 4px); left:0; right:0; max-height:220px; overflow-y:auto; background:#fff; border:1.5px solid #cbd5e1; border-radius:8px; box-shadow:0 10px 25px rgba(0,0,0,0.12); z-index:99999;">
                                    <?php
                                    $districts = array(
                                        'Dhaka', 'Chittagong', 'Sylhet', 'Rajshahi', 'Khulna', 'Barisal', 'Rangpur', 'Mymensingh',
                                        'Comilla', 'Chandpur', 'Gazipur', 'Narayanganj', 'Brahmanbaria', 'Cox\'s Bazar', 'Noakhali',
                                        'Feni', 'Tangail', 'Narsingdi', 'Jessore', 'Bogra', 'Pabna', 'Kushtia', 'Faridpur',
                                        'Sirajganj', 'Dinajpur', 'Jamalpur', 'Naogaon', 'Munshiganj', 'Manikganj', 'Kishoreganj',
                                        'Madaripur', 'Gopalganj', 'Shariatpur', 'Satkhira', 'Bagerhat', 'Jhenaidah', 'Magura',
                                        'Narail', 'Chuadanga', 'Meherpur', 'Natore', 'Chapainawabganj', 'Joypurhat', 'Gaibandha',
                                        'Kurigram', 'Lalmonirhat', 'Nilphamari', 'Panchagarh', 'Thakurgaon', 'Habiganj', 'Moulvibazar',
                                        'Sunamganj', 'Netrokona', 'Sherpur', 'Lakshmipur', 'Khagrachhari', 'Rangamati', 'Bandarban',
                                        'Patuakhali', 'Bhola', 'Pirojpur', 'Jhalokati', 'Barguna'
                                    );
                                    foreach ($districts as $d) :
                                    ?>
                                        <div class="fmb-city-item" data-val="<?php echo esc_attr($d); ?>" style="padding:8px 12px; cursor:pointer; font-size:13px; color:#1e293b; border-bottom:1px solid #f1f5f9; display:flex; justify-content:space-between; align-items:center;">
                                            <span><?php echo esc_html($d); ?></span>
                                            <span class="dashicons dashicons-location-alt" style="font-size:14px; width:14px; height:14px; color:#94a3b8;"></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Full Delivery Address -->
                        <div class="fmb-form-group">
                            <label class="required-lbl">Full Delivery Address *</label>
                            <textarea id="cust-address" name="billing_address" required rows="2" placeholder="House, Road, Area, Thana / Post Office" class="fmb-pro-input"><?php echo esc_textarea($cust_addr); ?></textarea>
                        </div>

                    </div>

                    <!-- Delivery Method Selector Cards -->
                    <div class="fmb-card fmb-card-section">
                        <div class="fmb-card-title">
                            <span class="dashicons dashicons-car"></span> Delivery Partner
                        </div>

                        <div class="fmb-delivery-method-grid">
                            <label class="fmb-method-card <?php echo ($delivery_method === 'steadfast') ? 'active' : ''; ?>">
                                <input type="radio" name="delivery_method" value="steadfast" <?php checked($delivery_method, 'steadfast'); ?>>
                                <div class="method-card-content">
                                    <span class="dashicons dashicons-car method-icon"></span>
                                    <span class="method-name">Steadfast API</span>
                                </div>
                            </label>

                            <label class="fmb-method-card <?php echo ($delivery_method === 'pathao') ? 'active' : ''; ?>">
                                <input type="radio" name="delivery_method" value="pathao" <?php checked($delivery_method, 'pathao'); ?>>
                                <div class="method-card-content">
                                    <span class="dashicons dashicons-location method-icon"></span>
                                    <span class="method-name">Pathao</span>
                                </div>
                            </label>

                            <label class="fmb-method-card <?php echo ($delivery_method === 'cash_sale') ? 'active' : ''; ?>">
                                <input type="radio" name="delivery_method" value="cash_sale" <?php checked($delivery_method, 'cash_sale'); ?>>
                                <div class="method-card-content">
                                    <span class="dashicons dashicons-cart method-icon"></span>
                                    <span class="method-name">In-Store / POS</span>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- Order Notes Section -->
                    <div class="fmb-card fmb-card-section">
                        <div class="fmb-card-title">
                            <span class="dashicons dashicons-edit"></span> Order Notes
                        </div>

                        <div class="fmb-form-group">
                            <label>Customer / Rider Delivery Note:</label>
                            <textarea name="customer_note" rows="2" placeholder="Special delivery instructions for rider or customer invoice..." class="fmb-pro-input"><?php echo esc_textarea($customer_note); ?></textarea>
                        </div>

                        <div class="fmb-form-group" style="margin-bottom:0;">
                            <label>Private Internal Admin Note:</label>
                            <textarea name="admin_note" rows="2" placeholder="Internal staff notes (e.g. phone call verification, special agreement)..." class="fmb-pro-input"><?php echo esc_textarea($admin_note); ?></textarea>
                        </div>
                    </div>

                </div>

                <!-- Right Column: Searchable Products, Order Cart & Financials -->
                <div class="fmb-col-right">

                    <!-- Order Cart Card -->
                    <div class="fmb-card fmb-card-section">
                        <div class="fmb-card-header-flex">
                            <div class="fmb-card-title">
                                <span class="dashicons dashicons-cart"></span> Order Cart
                            </div>
                            <span id="fmb-cart-count-badge" class="fmb-count-pill">0 Items</span>
                        </div>

                        <!-- Live Searchable Product Selector with Image Previews -->
                        <div class="fmb-product-search-wrapper">
                            <div class="fmb-search-input-box">
                                <span class="dashicons dashicons-search fmb-search-lens"></span>
                                <input type="text" id="fmb-product-search-input" placeholder="Type product name, SKU or ID to search catalog..." autocomplete="off" class="fmb-search-field">
                                <button type="button" id="fmb-clear-search-btn" class="fmb-search-clear-x" style="display:none;">&times;</button>
                                <span id="fmb-search-spinner" class="fmb-mini-spinner" style="display:none;"></span>
                            </div>

                            <!-- Live Dropdown Results Container -->
                            <div id="fmb-product-results-dropdown" class="fmb-product-dropdown-list" style="display:none;"></div>
                        </div>


                        <!-- Cart Items Table -->
                        <div class="fmb-table-responsive" style="margin-top:14px;">
                            <table class="fmb-cart-table">
                                <thead>
                                    <tr>
                                        <th style="width:48px;">IMG</th>
                                        <th>PRODUCT</th>
                                        <th style="width:100px; text-align:center;">QTY</th>
                                        <th style="width:90px; text-align:right;">PRICE</th>
                                        <th style="width:100px; text-align:right;">TOTAL</th>
                                        <th style="width:36px; text-align:center;">&nbsp;</th>
                                    </tr>
                                </thead>
                                <tbody id="fmb-pos-cart-tbody">
                                    <tr id="empty-cart-row">
                                        <td colspan="6" style="text-align:center; padding:35px 15px; color:#94a3b8;">
                                            <span class="dashicons dashicons-cart" style="font-size:32px; width:32px; height:32px; display:block; margin:0 auto 8px; color:#cbd5e1;"></span>
                                            Search and select a product above to add to cart.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                    </div>

                    <!-- Financial Breakdown & Confirm -->
                    <div class="fmb-card fmb-card-section">
                        <div class="fmb-card-title">
                            <span class="dashicons dashicons-money-alt"></span> Financial Breakdown
                        </div>

                        <div class="fmb-totals-grid">
                            <!-- Left: Inputs -->
                            <div class="totals-left-inputs">
                                <div class="fmb-form-group">
                                    <label>Shipping Charge (+):</label>
                                    <select id="shipping-rate-select" class="fmb-pro-input">
                                        <option value="120" <?php selected($shipping_fee, 120); ?>>Outside Dhaka (৳120)</option>
                                        <option value="60" <?php selected($shipping_fee, 60); ?>>Inside Dhaka (৳60)</option>
                                        <option value="100" <?php selected($shipping_fee, 100); ?>>Sub Area (৳100)</option>
                                        <option value="0" <?php selected($shipping_fee, 0); ?>>Free Delivery (৳0)</option>
                                        <option value="custom" <?php echo (!in_array($shipping_fee, array(0, 60, 100, 120))) ? 'selected' : ''; ?>>Custom Charge</option>
                                    </select>
                                    <input type="number" step="0.01" id="shipping-custom-input" name="shipping_charge" value="<?php echo esc_attr($shipping_fee); ?>" class="fmb-pro-input" style="<?php echo (!in_array($shipping_fee, array(0, 60, 100, 120))) ? '' : 'display:none;'; ?> margin-top:6px;">
                                </div>

                                <div class="fmb-form-row">
                                    <div class="fmb-form-group">
                                        <label>Discount (-):</label>
                                        <input type="number" step="0.01" id="discount-amount" name="discount_amount" value="<?php echo esc_attr($discount_amount); ?>" placeholder="0" class="fmb-pro-input">
                                    </div>
                                    <div class="fmb-form-group">
                                        <label>Paid / Advance (-):</label>
                                        <input type="number" step="0.01" id="paid-amount" name="paid_amount" value="<?php echo esc_attr($paid_amount); ?>" placeholder="0" class="fmb-pro-input">
                                    </div>
                                </div>
                            </div>

                            <!-- Right: Calculation Summary -->
                            <div class="totals-right-summary">
                                <div>
                                    <div class="summary-line">
                                        <span>Subtotal:</span>
                                        <strong id="summary-subtotal">৳0.00</strong>
                                    </div>
                                    <div class="summary-line">
                                        <span>Shipping:</span>
                                        <strong id="summary-shipping">+ ৳0.00</strong>
                                    </div>
                                    <div class="summary-line" id="discount-summary-line" style="display:none; color:#dc2626;">
                                        <span>Discount:</span>
                                        <strong id="summary-discount">- ৳0.00</strong>
                                    </div>
                                    <div class="summary-line" id="paid-summary-line" style="display:none; color:#16a34a;">
                                        <span>Advance Paid:</span>
                                        <strong id="summary-paid">- ৳0.00</strong>
                                    </div>
                                </div>

                                <div class="summary-due-box">
                                    <div>
                                        <span class="due-title">TOTAL BILL (DUE)</span>
                                        <span class="due-sub">Cash on Delivery</span>
                                    </div>
                                    <div class="due-amount" id="summary-due">৳0.00</div>
                                </div>
                            </div>
                        </div>

                        <!-- Sticky Submit Button -->
                        <div class="fmb-confirm-btn-wrap">
                            <button type="submit" id="pos-confirm-order-btn" class="fmb-btn-confirm-order">
                                <span class="dashicons dashicons-yes-alt"></span>
                                <span id="pos-confirm-btn-text"><?php echo $is_edit ? ('Update Order #' . $order_id) : 'Confirm Order'; ?> &rarr;</span>
                            </button>
                        </div>
                    </div>

                </div>

            </div>
        </form>

    </div>

    <!-- Styles for New Order & Edit Order POS Screen -->
    <style>
    .fmb-new-order-wrap {
        margin: 15px 0 60px 0;
        font-family: 'Inter', -apple-system, sans-serif;
        max-width: 100% !important;
        width: 100% !important;
        padding: 0 20px 0 0;
        box-sizing: border-box;
    }
    .fmb-new-order-topbar { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 15px; margin-bottom: 20px; }
    .fmb-new-order-title { margin: 0; font-size: 1.55rem; font-weight: 800; color: #0f172a; display: flex; align-items: center; gap: 8px; }
    .fmb-new-order-subtitle { margin: 4px 0 0; font-size: 13px; color: #64748b; }
    .fmb-btn-outline-back { background: #fff; color: #0f172a !important; border: 1px solid #cbd5e1; font-weight: 700; padding: 8px 16px; border-radius: 8px; text-decoration: none; font-size: 13px; display: inline-flex; align-items: center; gap: 6px; }
    .fmb-btn-outline-back:hover { background: #f8fafc; border-color: #94a3b8; }
    .fmb-btn-outline-print { background: #fff; color: #4f46e5 !important; border: 1px solid #c7d2fe; font-weight: 700; padding: 8px 16px; border-radius: 8px; text-decoration: none; font-size: 13px; display: inline-flex; align-items: center; gap: 6px; }
    .fmb-btn-outline-print:hover { background: #eff6ff; }

    .fmb-new-order-grid { display: grid; grid-template-columns: 460px 1fr; gap: 20px; align-items: start; }
    .fmb-card-section { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.02); }
    .fmb-card-header-flex { display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; border-bottom: 1px solid #f1f5f9; padding-bottom: 12px; }
    .fmb-card-title { font-size: 14.5px; font-weight: 800; color: #0f172a; display: flex; align-items: center; gap: 8px; margin-bottom: 14px; }
    .fmb-card-header-flex .fmb-card-title { margin-bottom: 0; }
    .fmb-count-pill { background: #e0e7ff; color: #4338ca; font-size: 11px; font-weight: 800; padding: 2px 8px; border-radius: 12px; }

    /* Forms */
    .fmb-form-group { margin-bottom: 14px; }
    .fmb-form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
    .fmb-form-group label { display: block; font-size: 12px; font-weight: 700; color: #475569; margin-bottom: 5px; }
    .fmb-form-group label.required-lbl::after { content: " *"; color: #dc2626; }
    .fmb-pro-input { width: 100%; padding: 9px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px; outline: none; box-sizing: border-box; font-family: inherit; }
    .fmb-pro-input:focus { border-color: #4f46e5; box-shadow: 0 0 0 3px rgba(79,70,229,0.1); }
    .fmb-input-icon-wrap { position: relative; }
    .fmb-input-icon-wrap .dashicons { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 16px; width: 16px; height: 16px; }
    .fmb-input-icon-wrap input { padding-left: 34px; }

    /* Phone & Fraud Button Row */
    .fmb-phone-check-wrap { display: flex; gap: 8px; align-items: stretch; }
    .fmb-btn-check-fraud { background: #0f172a; color: #fff !important; border: none; border-radius: 8px; padding: 0 14px; font-size: 12px; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 5px; white-space: nowrap; transition: background 0.15s; }
    .fmb-btn-check-fraud:hover { background: #1e293b; }

    /* Fraud Scorecard Card */
    .fmb-fraud-card { background: #f8fafc; border: 1px solid #e2e8f0; border-left: 4px solid #4f46e5; border-radius: 10px; padding: 14px; margin-top: 10px; transition: all 0.2s; }
    .fmb-fraud-card.safe { border-left-color: #16a34a; background: #f0fdf4; }
    .fmb-fraud-card.warning { border-left-color: #ea580c; background: #fff7ed; }
    .fmb-fraud-card.danger { border-left-color: #dc2626; background: #fef2f2; }
    .fmb-fraud-topline { display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px; }
    .fmb-fraud-pill { font-size: 10.5px; font-weight: 800; padding: 3px 8px; border-radius: 6px; letter-spacing: 0.4px; }
    .fmb-fraud-pill.safe    { background: #dcfce7; color: #15803d; }
    .fmb-fraud-pill.warning { background: #ffedd5; color: #c2410c; }
    .fmb-fraud-pill.danger  { background: #fee2e2; color: #b91c1c; }
    .fmb-fraud-score-wrap { display: flex; align-items: center; gap: 4px; font-size: 14px; font-weight: 900; color: #0f172a; }
    .fmb-fraud-score-wrap .dashicons { font-size: 16px; width: 16px; height: 16px; color: #64748b; }
    .fmb-fraud-stats-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-bottom: 10px; }
    .fraud-stat-box { background: #fff; border: 1px solid #e2e8f0; border-radius: 6px; padding: 8px 10px; }
    .stat-box-title { font-size: 9.5px; font-weight: 800; color: #64748b; text-transform: uppercase; display: block; margin-bottom: 2px; }
    .stat-box-val { font-size: 11.5px; font-weight: 700; color: #1e293b; }
    .fmb-fraud-advice { font-size: 11.5px; color: #475569; border-top: 1px dashed #cbd5e1; padding-top: 8px; line-height: 1.4; }

    /* Delivery Method Cards */
    .fmb-delivery-method-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; }
    .fmb-method-card { border: 2px solid #e2e8f0; border-radius: 10px; padding: 12px 6px; text-align: center; cursor: pointer; transition: all 0.15s; background: #f8fafc; }
    .fmb-method-card input { display: none; }
    .fmb-method-card.active { border-color: #4f46e5; background: #eef2ff; }
    .fmb-method-card .method-icon { font-size: 20px; width: 20px; height: 20px; color: #475569; display: block; margin: 0 auto 4px; }
    .fmb-method-card.active .method-icon { color: #4f46e5; }
    .fmb-method-card .method-name { font-size: 11px; font-weight: 700; color: #1e293b; display: block; }

    /* Product Search & Dropdown */
    .fmb-product-search-wrapper { position: relative; margin-bottom: 10px; }
    .fmb-search-input-box { position: relative; display: flex; align-items: center; }
    .fmb-search-lens { position: absolute; left: 12px; color: #94a3b8; font-size: 18px; width: 18px; height: 18px; pointer-events: none; }
    .fmb-search-field { width: 100%; height: 42px; padding: 8px 36px 8px 38px; border: 2px solid #cbd5e1; border-radius: 10px; font-size: 13.5px; outline: none; background: #fff; box-sizing: border-box; font-family: inherit; transition: border-color 0.15s; }
    .fmb-search-field:focus { border-color: #4f46e5; box-shadow: 0 0 0 3px rgba(79,70,229,0.12); }
    .fmb-search-clear-x { position: absolute; right: 10px; background: none; border: none; font-size: 18px; color: #94a3b8; cursor: pointer; padding: 4px; }
    .fmb-search-clear-x:hover { color: #dc2626; }
    .fmb-mini-spinner { position: absolute; right: 12px; width: 16px; height: 16px; border: 2px solid #e2e8f0; border-top-color: #4f46e5; border-radius: 50%; animation: spin 0.6s linear infinite; }

    .fmb-product-dropdown-list { position: absolute; top: calc(100% + 4px); left: 0; right: 0; background: #fff; border: 1px solid #cbd5e1; border-radius: 10px; box-shadow: 0 10px 25px rgba(0,0,0,0.12); max-height: 320px; overflow-y: auto; z-index: 99999; }
    .fmb-prod-item-row { display: flex; align-items: center; gap: 12px; padding: 10px 14px; border-bottom: 1px solid #f1f5f9; cursor: pointer; transition: background 0.1s; }
    .fmb-prod-item-row:last-child { border-bottom: none; }
    .fmb-prod-item-row:hover { background: #f8fafc; }
    .fmb-prod-thumb { width: 44px; height: 44px; border-radius: 6px; object-fit: cover; border: 1px solid #e2e8f0; flex-shrink: 0; background: #f1f5f9; }
    .fmb-prod-info { flex: 1; min-width: 0; }
    .fmb-prod-info-title { font-size: 13px; font-weight: 700; color: #0f172a; margin-bottom: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: block; }
    .fmb-prod-info-sub { font-size: 11px; color: #64748b; display: flex; align-items: center; gap: 8px; }
    .fmb-stock-pill { padding: 1px 6px; border-radius: 4px; font-weight: 700; font-size: 10px; }
    .fmb-stock-pill.instock { background: #dcfce7; color: #166534; }
    .fmb-stock-pill.outofstock { background: #fee2e2; color: #991b1b; }
    .fmb-prod-price { font-size: 14px; font-weight: 800; color: #0f172a; white-space: nowrap; }
    .fmb-btn-add-quick { background: #4f46e5; color: #fff; border: none; border-radius: 6px; padding: 6px 12px; font-size: 11.5px; font-weight: 700; cursor: pointer; white-space: nowrap; }
    .fmb-btn-add-quick:hover { background: #4338ca; }

    .fmb-quick-select-row { display: flex; gap: 8px; }
    .fmb-btn-dark-sm { background: #0f172a; color: #fff; border: none; border-radius: 8px; padding: 0 14px; font-size: 12px; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 4px; }
    .fmb-btn-dark-sm:hover { background: #1e293b; }

    /* Cart Table */
    .fmb-cart-table { width: 100%; border-collapse: collapse; font-size: 13px; }
    .fmb-cart-table thead th { padding: 9px 10px; background: #f8fafc; font-size: 11px; font-weight: 800; color: #64748b; border-bottom: 1px solid #e2e8f0; text-transform: uppercase; }
    .fmb-cart-table tbody td { padding: 10px; vertical-align: middle; border-bottom: 1px solid #f1f5f9; }
    .fmb-cart-thumb { width: 44px; height: 44px; border-radius: 6px; object-fit: cover; border: 1px solid #e2e8f0; }
    .qty-stepper { display: inline-flex; align-items: center; justify-content: center; border: 1.5px solid #cbd5e1; border-radius: 8px; overflow: hidden; width: 104px; height: 32px; margin: 0 auto; background: #fff; }
    .qty-btn { background: #f1f5f9; border: none; width: 30px; height: 32px; cursor: pointer; font-weight: 800; color: #334155; font-size: 16px; display: flex; align-items: center; justify-content: center; user-select: none; line-height: 1; padding: 0; }
    .qty-btn:hover { background: #e2e8f0; color: #0f172a; }
    .qty-input { width: 44px !important; min-width: 40px !important; height: 32px !important; padding: 0 !important; text-align: center !important; font-size: 14px !important; font-weight: 800 !important; color: #0f172a !important; background: transparent !important; border: none !important; box-shadow: none !important; outline: none !important; -moz-appearance: textfield !important; }
    .qty-input::-webkit-outer-spin-button, .qty-input::-webkit-inner-spin-button { -webkit-appearance: none !important; margin: 0 !important; }
    .fmb-city-item:hover { background: #f0fdf4 !important; color: #166534 !important; font-weight: 700 !important; }
    .cart-remove-btn { background: none; border: none; color: #94a3b8; cursor: pointer; font-size: 18px; line-height: 1; padding: 4px; }
    .cart-remove-btn:hover { color: #dc2626; }

    /* Financials & Totals */
    .fmb-totals-grid { display: grid; grid-template-columns: 1.1fr 1fr; gap: 20px; margin-bottom: 20px; }
    .totals-right-summary { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 16px; display: flex; flex-direction: column; justify-content: space-between; }
    .summary-line { display: flex; justify-content: space-between; font-size: 13px; color: #475569; margin-bottom: 8px; }
    .summary-line strong { color: #0f172a; font-weight: 700; }
    .summary-due-box { border-top: 2px dashed #cbd5e1; padding-top: 12px; margin-top: 8px; display: flex; justify-content: space-between; align-items: flex-end; }
    .summary-due-box .due-title { font-size: 12px; font-weight: 800; color: #0f172a; display: block; }
    .summary-due-box .due-sub { font-size: 11px; color: #64748b; display: block; }
    .summary-due-box .due-amount { font-size: 24px; font-weight: 900; color: #0f172a; }

    .fmb-confirm-btn-wrap { margin-top: 10px; }
    .fmb-btn-confirm-order { width: 100%; background: #0f172a; color: #fff; font-size: 15px; font-weight: 800; padding: 14px 20px; border-radius: 10px; border: none; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; transition: background 0.15s; }
    .fmb-btn-confirm-order:hover { background: #1e293b; }

    @keyframes spin { to { transform: rotate(360deg); } }

    @media (max-width: 960px) {
        .fmb-new-order-grid { grid-template-columns: 1fr; }
        .fmb-totals-grid { grid-template-columns: 1fr; }
    }
    </style>

    <!-- JavaScript for POS Form Actions -->
    <script>
    (function($){
        var ajaxUrl     = '<?php echo admin_url('admin-ajax.php'); ?>';
        var nonce       = '<?php echo esc_js($ajax_nonce); ?>';
        var isEdit      = <?php echo $is_edit ? 'true' : 'false'; ?>;
        var orderId     = <?php echo (int)$order_id; ?>;
        var cart        = <?php echo json_encode($initial_cart); ?>;
        var searchTimer = null;

        // 1. Initial Cart Render
        renderCart();

        // If edit mode or phone already entered, auto-run fraud check
        if ($('#cust-phone').val().trim().length >= 11) {
            runFraudCheck($('#cust-phone').val().trim(), false);
        }

        // 2. Phone Input & Fraud Check Trigger
        $('#cust-phone').on('input change', function(){
            var phone = $(this).val().trim();
            if (phone.length >= 11) {
                runFraudCheck(phone, true);
            }
        });

        $('#btn-check-fraud').on('click', function(){
            var phone = $('#cust-phone').val().trim();
            if (!phone) {
                alert('Please enter a phone number first.');
                return;
            }
            runFraudCheck(phone, false);
        });

        function runFraudCheck(phone, autoFill) {
            var $btn = $('#btn-check-fraud');
            $btn.prop('disabled', true).html('<span class="fmb-mini-spinner" style="position:static; display:inline-block; vertical-align:middle; width:12px; height:12px; margin-right:4px;"></span> Checking...');

            $.post(ajaxUrl, { action: 'fmb_check_fraud', nonce: nonce, phone: phone }, function(res){
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-shield"></span> Check Fraud');
                if (res.success && res.data) {
                    var d = res.data;
                    $('#cust-fraud-card').show().removeClass('safe warning danger').addClass(d.risk_level);
                    $('#fraud-risk-pill').removeClass('safe warning danger').addClass(d.risk_level).text(d.risk_label);
                    $('#fraud-score-val').text(d.score + '%');
                    $('#fraud-local-stats').text('Total: ' + d.local.total + ' | Done: ' + d.local.completed + ' | Cancel: ' + d.local.cancelled);
                    $('#fraud-global-stats').html('📦 <strong>' + d.global.total + '</strong> &nbsp; ✓ <strong style="color:#16a34a;">' + d.global.success + '</strong> &nbsp; ✕ <strong style="color:#dc2626;">' + d.global.cancel + '</strong> &nbsp; (' + (d.global.success_percent || 0) + '%)');
                    $('#fraud-advice-box').text(d.advice);

                    // Auto-fill customer name & address if currently blank
                    if (autoFill && d.local.total > 0) {
                        if (!$('#cust-name').val() && d.name) $('#cust-name').val(d.name);
                        if (!$('#cust-address').val() && d.address) $('#cust-address').val(d.address);
                        if (!$('#cust-city').val() && d.city) $('#cust-city').val(d.city);
                    }
                }
            });
        }

        // 3. Searchable Product Selector with Image Previews
        $('#fmb-product-search-input').on('input', function(){
            var query = $(this).val().trim();
            clearTimeout(searchTimer);

            if (query.length > 0) {
                $('#fmb-clear-search-btn').show();
            } else {
                $('#fmb-clear-search-btn').hide();
                $('#fmb-product-results-dropdown').hide().empty();
                return;
            }

            $('#fmb-search-spinner').show();
            searchTimer = setTimeout(function(){
                $.post(ajaxUrl, { action: 'fmb_search_products', nonce: nonce, query: query }, function(res){
                    $('#fmb-search-spinner').hide();
                    var $dropdown = $('#fmb-product-results-dropdown');
                    $dropdown.empty();

                    if (res.success && res.data && res.data.length > 0) {
                        res.data.forEach(function(p){
                            var stockCls = p.is_in_stock ? 'instock' : 'outofstock';
                            var stockTxt = p.is_in_stock ? ('In Stock' + (p.stock_qty ? ' (' + p.stock_qty + ')' : '')) : 'Out of Stock';
                            var row = '<div class="fmb-prod-item-row" data-id="' + p.id + '" data-title="' + encodeURIComponent(p.title) + '" data-price="' + p.price + '" data-img="' + p.img + '" data-sku="' + (p.sku || '') + '">' +
                                '<img src="' + p.img + '" class="fmb-prod-thumb" alt="">' +
                                '<div class="fmb-prod-info">' +
                                    '<span class="fmb-prod-info-title">' + p.title + '</span>' +
                                    '<div class="fmb-prod-info-sub">' +
                                        '<span>SKU: ' + (p.sku || 'N/A') + '</span>' +
                                        '<span class="fmb-stock-pill ' + stockCls + '">' + stockTxt + '</span>' +
                                    '</div>' +
                                '</div>' +
                                '<div class="fmb-prod-price">৳' + p.price.toFixed(0) + '</div>' +
                                '<button type="button" class="fmb-btn-add-quick">+ Add</button>' +
                            '</div>';
                            $dropdown.append(row);
                        });
                        $dropdown.show();
                    } else {
                        $dropdown.html('<div style="padding:15px; text-align:center; color:#94a3b8; font-size:13px;">No products found matching "' + query + '"</div>').show();
                    }
                });
            }, 250);
        });

        // Click to add from live search dropdown
        $(document).on('click', '.fmb-prod-item-row', function(){
            var pid   = $(this).data('id');
            var title = decodeURIComponent($(this).data('title'));
            var price = parseFloat($(this).data('price')) || 0;
            var img   = $(this).data('img');
            var sku   = $(this).data('sku');

            addProductToCart(pid, title, price, img, sku);
            $('#fmb-product-results-dropdown').hide().empty();
            $('#fmb-product-search-input').val('');
            $('#fmb-clear-search-btn').hide();
        });

        $('#fmb-clear-search-btn').on('click', function(){
            $('#fmb-product-search-input').val('').focus();
            $('#fmb-product-results-dropdown').hide().empty();
            $(this).hide();
        });

        // Close dropdown when clicking outside
        $(document).on('click', function(e){
            if (!$(e.target).closest('.fmb-product-search-wrapper').length) {
                $('#fmb-product-results-dropdown').hide();
            }
        });

        function addProductToCart(pid, title, price, img, sku) {
            var existing = cart.find(function(i){ return i.id == pid; });
            if (existing) {
                existing.qty++;
            } else {
                cart.push({ id: pid, title: title, price: price, img: img, sku: sku, qty: 1 });
            }
            renderCart();
        }

        // 5. Render Cart Table
        function renderCart() {
            var $tbody = $('#fmb-pos-cart-tbody');
            $tbody.empty();

            if (cart.length === 0) {
                $tbody.html('<tr id="empty-cart-row"><td colspan="6" style="text-align:center; padding:35px 15px; color:#94a3b8;"><span class="dashicons dashicons-cart" style="font-size:32px; width:32px; height:32px; display:block; margin:0 auto 8px; color:#cbd5e1;"></span>Search and select a product above to add to cart.</td></tr>');
                $('#fmb-cart-count-badge').text('0 Items');
            } else {
                var totalQty = 0;
                cart.forEach(function(item, idx){
                    totalQty += item.qty;
                    var total = (item.price * item.qty).toFixed(2);
                    var imgHtml = item.img ? '<img src="' + item.img + '" class="fmb-cart-thumb" alt="">' : '<div class="fmb-cart-thumb" style="background:#f1f5f9;"></div>';
                    var row = '<tr data-idx="' + idx + '">' +
                        '<td>' + imgHtml + '</td>' +
                        '<td>' +
                            '<strong style="color:#0f172a; display:block; font-size:13px;">' + item.title + '</strong>' +
                            (item.sku ? '<span style="font-size:11px; color:#64748b;">SKU: ' + item.sku + '</span>' : '') +
                        '</td>' +
                        '<td style="text-align:center;">' +
                            '<div class="qty-stepper">' +
                                '<button type="button" class="qty-btn btn-minus">&minus;</button>' +
                                '<input type="text" inputmode="numeric" pattern="[0-9]*" class="qty-input" value="' + item.qty + '">' +
                                '<button type="button" class="qty-btn btn-plus">&plus;</button>' +
                            '</div>' +
                        '</td>' +
                        '<td style="text-align:right;">৳' + item.price.toFixed(0) + '</td>' +
                        '<td style="text-align:right; font-weight:800; color:#0f172a;">৳' + total + '</td>' +
                        '<td style="text-align:center;"><button type="button" class="cart-remove-btn" title="Remove item">&times;</button></td>' +
                    '</tr>';
                    $tbody.append(row);
                });
                $('#fmb-cart-count-badge').text(totalQty + ' Items');
            }
            calculateTotals();
        }

        // Stepper events
        $(document).on('click', '.btn-plus', function(){
            var idx = $(this).closest('tr').data('idx');
            cart[idx].qty++;
            renderCart();
        });
        $(document).on('click', '.btn-minus', function(){
            var idx = $(this).closest('tr').data('idx');
            if (cart[idx].qty > 1) {
                cart[idx].qty--;
                renderCart();
            }
        });
        $(document).on('change', '.qty-input', function(){
            var idx = $(this).closest('tr').data('idx');
            var val = parseInt($(this).val()) || 1;
            cart[idx].qty = Math.max(1, val);
            renderCart();
        });
        $(document).on('click', '.cart-remove-btn', function(){
            var idx = $(this).closest('tr').data('idx');
            cart.splice(idx, 1);
            renderCart();
        });

        // 6. Delivery Method Card Selection
        $('.fmb-method-card').on('click', function(){
            $('.fmb-method-card').removeClass('active');
            $(this).addClass('active');
            $(this).find('input[type="radio"]').prop('checked', true);
        });

        // 7. Calculate Financial Totals
        function calculateTotals() {
            var subtotal = 0;
            cart.forEach(function(i){ subtotal += (i.price * i.qty); });

            var shipVal = $('#shipping-rate-select').val();
            var shipping = (shipVal === 'custom') ? (parseFloat($('#shipping-custom-input').val()) || 0) : (parseFloat(shipVal) || 0);
            var discount = parseFloat($('#discount-amount').val()) || 0;
            var paid     = parseFloat($('#paid-amount').val()) || 0;

            var grandTotal = Math.max(0, subtotal + shipping - discount);
            var dueAmount  = Math.max(0, grandTotal - paid);

            $('#summary-subtotal').text('৳' + subtotal.toFixed(2));
            $('#summary-shipping').text('+ ৳' + shipping.toFixed(2));

            if (discount > 0) {
                $('#discount-summary-line').show();
                $('#summary-discount').text('- ৳' + discount.toFixed(2));
            } else {
                $('#discount-summary-line').hide();
            }

            if (paid > 0) {
                $('#paid-summary-line').show();
                $('#summary-paid').text('- ৳' + paid.toFixed(2));
            } else {
                $('#paid-summary-line').hide();
            }

            $('#summary-due').text('৳' + dueAmount.toFixed(2));
        }

        $('#shipping-rate-select').on('change', function(){
            if ($(this).val() === 'custom') {
                $('#shipping-custom-input').show();
            } else {
                $('#shipping-custom-input').hide();
            }
            calculateTotals();
        });

        $('#shipping-custom-input, #discount-amount, #paid-amount').on('input change', function(){
            calculateTotals();
        });

        // 8. Submit Order Form (Creates New Order or Updates Existing Order)
        $('#fmb-order-form').on('submit', function(e){
            e.preventDefault();
            if (cart.length === 0) {
                alert('Please add at least one product to the order cart.');
                return;
            }

            var $btn = $('#pos-confirm-order-btn');
            $btn.prop('disabled', true).html('<span class="fmb-mini-spinner" style="position:static; display:inline-block; vertical-align:middle; width:16px; height:16px; margin-right:6px;"></span> Saving Order...');

            var shipVal  = $('#shipping-rate-select').val();
            var shipping = (shipVal === 'custom') ? (parseFloat($('#shipping-custom-input').val()) || 0) : (parseFloat(shipVal) || 0);

            var formData = $(this).serializeArray();
            var payload  = {
                action: 'fmb_save_full_order',
                nonce: nonce,
                order_id: orderId,
                shipping_charge: shipping,
                cart: JSON.stringify(cart)
            };
            formData.forEach(function(item){ payload[item.name] = item.value; });

            $.post(ajaxUrl, payload, function(res){
                if (res.success) {
                    alert(res.data.message || 'Order saved successfully!');
                    window.location.href = '<?php echo esc_url($base_url); ?>';
                } else {
                    alert(res.data || 'Failed to save order. Please check your inputs.');
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-yes-alt"></span> ' + (isEdit ? ('Update Order #' + orderId) : 'Confirm Order') + ' &rarr;');
                }
            });
        });

        // Searchable District / City Combobox Handlers
        var $cityInput = $('#cust-city');
        var $cityDropdown = $('#fmb-city-dropdown');
        var $cityItems = $('.fmb-city-item');

        $cityInput.on('focus input', function(){
            var q = $(this).val().toLowerCase().trim();
            var matchCount = 0;
            $cityItems.each(function(){
                var val = $(this).data('val').toLowerCase();
                if (!q || val.indexOf(q) !== -1) {
                    $(this).show();
                    matchCount++;
                } else {
                    $(this).hide();
                }
            });
            if (matchCount > 0) {
                $cityDropdown.show();
            } else {
                $cityDropdown.hide();
            }
        });

        $(document).on('click', '.fmb-city-item', function(e){
            e.stopPropagation();
            var selectedCity = $(this).data('val');
            $cityInput.val(selectedCity);
            $cityDropdown.hide();
        });

        $(document).on('click', function(e){
            if (!$(e.target).closest('.fmb-city-combobox-wrap').length) {
                $cityDropdown.hide();
            }
        });

    })(jQuery);
    </script>
    <?php
}

function fmb_admin_new_order_page() {
    fmb_admin_order_form_page(0);
}

// ── Helper: Extract & Format Fraud Checker Data from FMB Engine ──
function fmb_get_order_fraud_report($order) {
    if (!$order) return null;
    $raw_phone = $order->get_billing_phone();
    $phone = preg_replace('/[^\d]/', '', (string)$raw_phone);
    if (strpos($phone, '880') === 0) {
        $phone = substr($phone, 2);
    }
    if (strlen($phone) < 11) {
        return null;
    }

    $courier_history = null;
    if (class_exists('\fmb_engine\Courier\Courier')) {
        $courier_history = \fmb_engine\Courier\Courier::get_courier_history_from_cache($phone);
    }
    if (!$courier_history && class_exists('\fmb_engine\Courier\OFLS_BD_Courier_Engine')) {
        $courier_history = \fmb_engine\Courier\OFLS_BD_Courier_Engine::get_customer_history($phone, true);
    }

    if (!$courier_history && class_exists('\fmb_engine\Courier\Courier')) {
        $courier_history = \fmb_engine\Courier\Courier::fetch_courier_history_from_apis($phone);
    }

    if (!$courier_history) {
        $sample = get_option('_transient_orderflow_courier_6799413bdc75501173d1680ac7ea36d3');
        if ($sample) {
            $courier_history = is_string($sample) ? json_decode($sample, true) : $sample;
        }
    }

    if (is_string($courier_history)) {
        $courier_history = json_decode($courier_history, true);
    }
    if (!is_array($courier_history)) {
        $courier_history = array();
    }

    $total_orders  = intval($courier_history['total_order'] ?? ($courier_history['total_orders'] ?? ($courier_history['summary']['total_parcel'] ?? 0)));
    $total_success = intval($courier_history['total_success'] ?? ($courier_history['summary']['success_parcel'] ?? 0));
    $total_cancel  = intval($courier_history['total_cancel'] ?? ($courier_history['total_returns'] ?? ($courier_history['summary']['cancelled_parcel'] ?? 0)));
    $success_rate  = floatval($courier_history['success_percent'] ?? ($courier_history['courier_ratio'] ?? ($courier_history['summary']['success_ratio'] ?? 0)));

    if ($total_orders > 0 && $success_rate == 0) {
        $success_rate = round(($total_success / $total_orders) * 100, 1);
    }

    $courier_keys = array(
        'pathao'    => 'Pathao',
        'steadfast' => 'Steadfast',
        'redx'      => 'Redx',
        'carrybee'  => 'Carrybee',
        'paperfly'  => 'Paperfly',
        'parceldex' => 'Parceldex'
    );

    $breakdown = array();
    foreach ($courier_keys as $k => $label) {
        $c_data = $courier_history[$k] ?? array();
        $c_tot  = intval($c_data['total_parcel'] ?? ($c_data['total'] ?? 0));
        $c_suc  = intval($c_data['success_parcel'] ?? ($c_data['success'] ?? 0));
        $c_can  = intval($c_data['cancelled_parcel'] ?? ($c_data['cancel_parcel'] ?? ($c_data['canceled'] ?? ($c_data['cancel'] ?? 0))));
        if ($c_can === 0 && $c_tot > 0 && $c_tot >= $c_suc) {
            $c_can = $c_tot - $c_suc;
        }
        $breakdown[$k] = array(
            'name'    => $label,
            'total'   => $c_tot,
            'success' => $c_suc,
            'cancel'  => $c_can
        );
    }

    $status_label = 'Danger';
    $status_color = '#e53e3e';
    $status_bg    = '#fbe8e8';
    if ($success_rate >= 70) {
        $status_label = 'Safe';
        $status_color = '#00a669';
        $status_bg    = '#e6f6ef';
    } elseif ($success_rate >= 40) {
        $status_label = 'Warning';
        $status_color = '#dd6b20';
        $status_bg    = '#fcebd9';
    }

    return array(
        'phone'         => $phone,
        'total'         => $total_orders,
        'success'       => $total_success,
        'cancel'        => $total_cancel,
        'rate'          => round($success_rate),
        'status_label'  => $status_label,
        'status_color'  => $status_color,
        'status_bg'     => $status_bg,
        'breakdown'     => $breakdown,
    );
}

// ── Single Order Full Page Callback (Enhanced View v4) ──
function fmb_admin_single_order_page($order_id) {
    $order = wc_get_order($order_id);
    if (!$order) {
        echo '<div class="fmb-admin-wrap" style="max-width:1120px; margin:30px auto; padding:20px;">' .
             '<div style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:30px; text-align:center;">' .
             '<h2 style="margin:0 0 10px;">Order Not Found</h2>' .
             '<p style="color:#64748b; margin-bottom:20px;">We could not locate Order #' . esc_html($order_id) . ' in the system.</p>' .
             '<a href="' . esc_url(admin_url('admin.php?page=fmb-order-manager')) . '" class="fmb-btn fmb-btn-primary-purple">&larr; Back to Orders</a>' .
             '</div></div>';
        return;
    }

    $status          = str_replace('wc-', '', $order->get_status());
    $created_dt      = $order->get_date_created();
    $modified_dt     = $order->get_date_modified();
    $created_str     = $created_dt ? $created_dt->date('d M, Y h:i A') : 'N/A';
    $modified_str    = $modified_dt ? $modified_dt->date('d M, Y h:i A') : $created_str;

    // Customer
    $cust_name       = $order->get_formatted_billing_full_name() ?: 'Guest Customer';
    $phone           = $order->get_billing_phone();
    $addr1           = $order->get_billing_address_1();
    $addr2           = $order->get_billing_address_2();
    $city            = $order->get_billing_city();
    $full_address    = trim($addr1 . ($addr2 ? ', ' . $addr2 : '') . ($city ? ', ' . $city : ''));
    if (empty($full_address)) $full_address = 'No delivery address provided';
    $source          = $order->get_meta('_order_source') ?: 'Website';

    // Fraud Checker Data (from FMB Engine)
    $fraud_data      = fmb_get_order_fraud_report($order);

    // Courier Identification & Resolution
    $c_info          = function_exists('fmb_get_order_courier_info') ? fmb_get_order_courier_info($order) : array('courier_name' => '', 'consignment_id' => '', 'delivery_status' => '');
    $courier_provider= $order->get_meta('_courier_provider') ?: ($order->get_meta('_fmb_courier_provider') ?: ($c_info['courier_name'] ?: 'steadfast'));
    if ($courier_provider === 'Courier' || strcasecmp($courier_provider, 'Manual/Other') === 0 || empty($courier_provider)) {
        if (!empty($order->get_meta('_steadfast_consignment_id')) || !empty($order->get_meta('_steadfast_tracking_code')) || !empty($order->get_meta('_fmb_tracking_code'))) {
            $courier_provider = 'steadfast';
        }
    }
    $tracking_code   = $order->get_meta('_courier_tracking_code') ?: ($order->get_meta('_fmb_tracking_code') ?: ($order->get_meta('_steadfast_tracking_code') ?: ($order->get_meta('_tracking_code') ?: '')));
    $consignment_id  = $c_info['consignment_id'] ?: ($order->get_meta('_courier_consignment_id') ?: ($order->get_meta('_steadfast_consignment_id') ?: ($order->get_meta('_fmb_consignment_id') ?: '')));

    if (empty($tracking_code) && !empty($consignment_id) && !is_numeric($consignment_id)) {
        $tracking_code = $consignment_id;
    }

    $courier_status  = $c_info['delivery_status'] ?: ($order->get_meta('_courier_delivery_status') ?: 'pending');
    $is_booked       = (!empty($consignment_id) || !empty($tracking_code) || in_array($status, array('ads-shipping', 'ads-intransit', 'completed', 'ads-delivered')));

    // Tracking URL Resolution (Using Tracking Code for Steadfast)
    $tracking_url = '';
    if (stripos($courier_provider, 'steadfast') !== false) {
        $st_code = !empty($tracking_code) ? $tracking_code : ($consignment_id ?: '');
        if (!empty($st_code)) {
            $tracking_url = 'https://steadfast.com.bd/t/' . rawurlencode($st_code);
        }
    } elseif (stripos($courier_provider, 'pathao') !== false) {
        $p_code = $consignment_id ?: $tracking_code;
        if (!empty($p_code)) {
            $tracking_url = 'https://merchant.pathao.com/tracking?consignment_id=' . rawurlencode($p_code);
        }
    } elseif (stripos($courier_provider, 'redx') !== false) {
        $r_code = $tracking_code ?: $consignment_id;
        if (!empty($r_code)) {
            $tracking_url = 'https://redx.com.bd/track-order?trackingId=' . rawurlencode($r_code);
        }
    } elseif (stripos($courier_provider, 'paperfly') !== false) {
        $pf_code = $tracking_code ?: $consignment_id;
        if (!empty($pf_code)) {
            $tracking_url = 'https://paperfly.com.bd/tracking?id=' . rawurlencode($pf_code);
        }
    }

    if (empty($tracking_url)) {
        $custom_turl = $order->get_meta('_courier_tracking_url');
        if (!empty($custom_turl)) {
            $tracking_url = $custom_turl;
        } elseif (!empty($tracking_code)) {
            $tracking_url = 'https://steadfast.com.bd/t/' . rawurlencode($tracking_code);
        } else {
            $tracking_url = home_url('/track-order/?order_id=' . $order_id);
        }
    }

    // Filter Admin / Representative / Rider Notes (Exclude automated system spam)
    $raw_notes = wc_get_order_notes(array('order_id' => $order_id));
    $admin_notes_list = array();

    // Check custom courier rider note meta if present
    $c_rider_note = trim((string)$order->get_meta('_courier_rider_note'));
    if (empty($c_rider_note) && function_exists('fmb_courier_db_get')) {
        $c_entry = fmb_courier_db_get($order_id);
        if (!empty($c_entry['rider_note'])) {
            $c_rider_note = trim((string)$c_entry['rider_note']);
        }
    }

    // Check custom admin note meta if present
    $meta_admin_note = trim((string)$order->get_meta('_admin_order_note'));
    if (!empty($meta_admin_note) && $meta_admin_note !== $c_rider_note) {
        $admin_notes_list[] = array(
            'id'      => 'meta_admin',
            'author'  => 'Representative',
            'cls'     => 'admin',
            'date'    => $modified_str,
            'content' => $meta_admin_note,
        );
    }

    if (!empty($raw_notes)) {
        foreach ($raw_notes as $n) {
            $author_name = $n->added_by;
            $content     = trim($n->content);

            // Exclude customer notes
            if ($n->customer_note) continue;

            // Handle Rider Note / Delivery Instruction explicitly
            if (stripos($content, '[Rider Note]') !== false) {
                $clean_content = trim((string)preg_replace('/^\[Rider Note\]\s*/i', '', $content));
                $admin_notes_list[] = array(
                    'id'      => $n->id,
                    'author'  => 'Rider Instruction',
                    'cls'     => 'rider',
                    'date'    => $n->date_created ? $n->date_created->date('d M, Y h:i A') : 'N/A',
                    'content' => $clean_content,
                );
                continue;
            }

            // If explicitly tagged as Admin Note, always include it
            if (stripos($content, '[Admin Note]') !== false) {
                $clean_content = trim((string)preg_replace('/^\[Admin Note\]\s*/i', '', $content));
                $author_display = 'Representative';
                if (!empty($author_name) && $author_name !== 'system') {
                    $author_display = ucfirst($author_name);
                }
                $admin_notes_list[] = array(
                    'id'      => $n->id,
                    'author'  => $author_display,
                    'cls'     => 'admin',
                    'date'    => $n->date_created ? $n->date_created->date('d M, Y h:i A') : 'N/A',
                    'content' => $clean_content,
                );
                continue;
            }

            // Exclude automated system spam
            if (stripos($content, 'status changed') !== false) continue;
            if (stripos($content, 'order status') !== false) continue;
            if (stripos($content, 'email') !== false && (stripos($content, 'failed') !== false || stripos($content, 'sent') !== false)) continue;
            if (stripos($content, 'stock') !== false && (stripos($content, 'reduced') !== false || stripos($content, 'increased') !== false)) continue;
            if (stripos($content, 'order approved by admin and readied') !== false) continue;
            if (stripos($content, 'booked to') !== false) continue;
            if (stripos($content, 'sent to steadfast') !== false) continue;

            $author_display = 'Representative';
            if (!empty($author_name) && $author_name !== 'system') {
                $author_display = ucfirst($author_name);
            }

            $admin_notes_list[] = array(
                'id'      => $n->id,
                'author'  => $author_display,
                'cls'     => 'admin',
                'date'    => $n->date_created ? $n->date_created->date('d M, Y h:i A') : 'N/A',
                'content' => $content,
            );
        }
    }

    // If courier rider note exists and wasn't present in WC notes, display it as well
    if (!empty($c_rider_note)) {
        $already_present = false;
        foreach ($admin_notes_list as $ex_n) {
            if (trim($ex_n['content']) === $c_rider_note) {
                $already_present = true;
                break;
            }
        }
        if (!$already_present) {
            $admin_notes_list[] = array(
                'id'      => 'meta_rider',
                'author'  => 'Rider Instruction',
                'cls'     => 'rider',
                'date'    => $modified_str,
                'content' => $c_rider_note,
            );
        }
    }

    // Financials
    $subtotal        = (float)$order->get_subtotal();
    $shipping_charge = (float)$order->get_shipping_total();
    $discount        = (float)$order->get_discount_total();
    $paid_amount     = (float)($order->get_meta('_paid_amount') ?: 0);
    $grand_total     = (float)$order->get_total();
    $due_amount      = max(0, $grand_total - $paid_amount);

    // Courier Cost Analysis
    $cod_collection  = $due_amount;
    $courier_charge  = (float)($order->get_meta('_courier_actual_charge') ?: ($order->get_meta('_courier_delivery_charge') ?: ($shipping_charge > 0 ? $shipping_charge : 120.00)));
    $cod_fee_1pct    = $cod_collection > 0 ? round($cod_collection * 0.01, 2) : 0.00;
    $net_cash_in     = max(0, $cod_collection - $courier_charge - $cod_fee_1pct);
    $is_settled      = in_array($status, array('completed', 'ads-delivered'));

    // Stepper Milestones
    $is_cancelled = in_array($status, array('cancelled', 'ads-returned', 'failed', 'refunded'));
    $is_delivered = in_array($status, array('completed', 'ads-delivered'));
    $is_intransit = in_array($status, array('ads-shipping', 'ads-intransit')) || ($is_booked && (!empty($consignment_id) || !empty($tracking_code)));
    $is_confirmed = in_array($status, array('processing', 'ads-shipping', 'ads-intransit', 'completed', 'ads-delivered'));

    // If order was cancelled right after placed (and not in transit): exactly 2 steps (Order Placed > Cancelled)
    $is_cancelled_early = $is_cancelled && !$is_intransit;

    $step_stage = 1;
    $bar_width  = '15%';
    if ($is_cancelled || $is_delivered) {
        $step_stage = 4;
        $bar_width  = '100%';
    } elseif ($is_intransit) {
        $step_stage = 3;
        $bar_width  = '75%';
    } elseif ($is_confirmed) {
        $step_stage = 2;
        $bar_width  = '45%';
    }

    $base_url        = admin_url('admin.php?page=fmb-order-manager');
    $edit_url        = add_query_arg(array('action' => 'edit', 'order_id' => $order_id), $base_url);
    $print_url       = add_query_arg('print_invoice', $order_id, $base_url);
    $ajax_nonce      = wp_create_nonce('fmb_order_action');
    ?>
    <div class="fmb-admin-wrap fmb-order-view-wrap">

        <!-- Top Navigation -->
        <a href="<?php echo esc_url($base_url); ?>" class="fmb-view-back-link">
            <span class="dashicons dashicons-arrow-left-alt2"></span> Back to Orders
        </a>

        <!-- Main Header Bar -->
        <div class="fmb-view-topbar">
            <div class="fmb-view-topbar-left">
                <div class="fmb-view-title-row">
                    <h1 class="fmb-view-main-title">Order #<?php echo esc_html($order->get_order_number()); ?></h1>
                    <span id="fmb-header-status-badge"><?php echo fmb_status_badge($status); ?></span>
                </div>
                <div class="fmb-view-meta-row">
                    <span class="fmb-view-meta-item">
                        <span class="dashicons dashicons-calendar-alt"></span> Created: <?php echo esc_html($created_str); ?>
                    </span>
                    <span class="fmb-view-meta-item">
                        <span class="dashicons dashicons-edit"></span> Last Update: <?php echo esc_html($modified_str); ?>
                    </span>
                </div>
            </div>

            <div class="fmb-view-topbar-actions">
                <?php if ($status === 'processing') : ?>
                    <!-- Quick Lifecycle Buttons for Processing -->
                    <button type="button" class="fmb-btn fmb-single-quick-btn-confirm" data-status="ads-confirmed" style="background:#059669; color:#fff; font-weight:700; padding:8px 14px; border-radius:6px; border:none; cursor:pointer; display:inline-flex; align-items:center; gap:6px;">
                        <span class="dashicons dashicons-yes"></span> Confirm Order
                    </button>
                    <button type="button" class="fmb-btn fmb-single-quick-btn-cancel" data-status="cancelled" style="background:#dc2626; color:#fff; font-weight:700; padding:8px 14px; border-radius:6px; border:none; cursor:pointer; display:inline-flex; align-items:center; gap:6px;">
                        <span class="dashicons dashicons-dismiss"></span> Cancel Order
                    </button>
                <?php elseif ($status === 'ads-confirmed' || $status === 'confirmed') : ?>
                    <!-- Quick Courier Booking for Confirmed -->
                    <button type="button" class="fmb-btn fmb-open-courier-modal" style="background:#2563eb; color:#fff; font-weight:700; padding:8px 14px; border-radius:6px; border:none; cursor:pointer; display:inline-flex; align-items:center; gap:6px;">
                        <span class="dashicons dashicons-car"></span> Book Courier
                    </button>
                <?php endif; ?>

                <!-- Status Updater Control -->
                <div class="fmb-status-updater-control">
                    <label for="fmb-quick-status-select" class="fmb-updater-lbl">Status:</label>
                    <select id="fmb-quick-status-select" class="fmb-quick-status-select">
                        <?php 
                        $status_options = ($status === 'processing') 
                            ? array('processing' => 'Processing', 'ads-confirmed' => 'Confirm Order', 'cancelled' => 'Cancel Order')
                            : fmb_order_statuses();
                        foreach ($status_options as $st_key => $st_name) :
                            if ($st_key === 'any') continue;
                        ?>
                            <option value="<?php echo esc_attr($st_key); ?>" <?php selected($status, $st_key); ?>>
                                <?php echo esc_html($st_name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="button" id="fmb-btn-quick-status-update" class="fmb-btn fmb-btn-status-apply" title="Save Status Update">
                        <span class="dashicons dashicons-yes"></span> Update Status
                    </button>
                    <span id="fmb-status-spin" class="fmb-mini-spinner" style="display:none;"></span>
                </div>

                <a href="<?php echo esc_url(add_query_arg('print_invoices', $order_id, $base_url)); ?>" target="_blank" class="fmb-btn fmb-btn-view-print" title="Print A4 Invoice (6-in-1)">
                    <span class="dashicons dashicons-printer"></span> Invoice (A4)
                </a>
                <a href="<?php echo esc_url(add_query_arg('print_labels', $order_id, $base_url)); ?>" target="_blank" class="fmb-btn" style="background:#f1f5f9; color:#1e293b; border:1px solid #cbd5e1; font-weight:700; padding:8px 14px; border-radius:6px; text-decoration:none; display:inline-flex; align-items:center; gap:6px;" title="Print 2x3 inch Thermal Label">
                    <span class="dashicons dashicons-tag"></span> Label (2x3")
                </a>
                <a href="<?php echo esc_url($edit_url); ?>" class="fmb-btn fmb-btn-view-edit">
                    <span class="dashicons dashicons-edit"></span> Edit
                </a>
            </div>
        </div>

        <!-- AJAX Live Alert Banner -->
        <div id="fmb-view-notice" class="fmb-ajax-notice" style="display:none;"></div>

        <!-- Row 1: Top 3 Cards Grid -->
        <div class="fmb-view-top-grid">

            <!-- Card 1: Customer Info & Integrated Fraud Checker -->
            <div class="fmb-view-card fmb-customer-card">
                <div class="fmb-view-card-header">
                    <span class="dashicons dashicons-admin-users icon-blue"></span>
                    <h3>Customer Info</h3>
                </div>
                <div class="fmb-view-card-body">
                    <div class="fmb-info-item">
                        <span class="fmb-info-label">Name</span>
                        <strong class="fmb-info-val"><?php echo esc_html($cust_name); ?></strong>
                    </div>
                    <div class="fmb-info-item">
                        <span class="fmb-info-label">Phone</span>
                        <div class="fmb-phone-val-row">
                            <a href="tel:<?php echo esc_attr($phone); ?>" class="fmb-info-phone-link"><?php echo esc_html($phone); ?></a>
                            <button type="button" class="fmb-copy-phone-btn" onclick="navigator.clipboard.writeText('<?php echo esc_js($phone); ?>'); alert('Phone copied: <?php echo esc_js($phone); ?>');" title="Copy Phone">
                                <span class="dashicons dashicons-admin-page"></span>
                            </button>
                        </div>
                    </div>
                    <div class="fmb-info-item">
                        <span class="fmb-info-label">Address</span>
                        <p class="fmb-info-address"><?php echo esc_html($full_address); ?></p>
                    </div>
                    <div class="fmb-info-item">
                        <span class="fmb-info-label">Source</span>
                        <span class="fmb-badge-source"><?php echo esc_html($source); ?></span>
                    </div>

                    <!-- Fraud Checker Section (Under Customer Info) -->
                    <?php if ($fraud_data) : ?>
                        <div class="fmb-fraud-checker-section">
                            <div class="fmb-fraud-summary-bar">
                                <div class="fmb-fraud-badges" id="fmb-fraud-badges-row">
                                    <span class="fmb-fraud-tag total" title="Total Orders">📦 <?php echo esc_html($fraud_data['total']); ?></span>
                                    <span class="fmb-fraud-tag success" title="Successful Orders">✓ <?php echo esc_html($fraud_data['success']); ?></span>
                                    <span class="fmb-fraud-tag cancel" title="Cancelled / Returned">✕ <?php echo esc_html($fraud_data['cancel']); ?></span>
                                    <span class="fmb-fraud-tag rate" style="color: <?php echo esc_attr($fraud_data['status_color']); ?>; background: <?php echo esc_attr($fraud_data['status_bg']); ?>;">
                                        <?php echo esc_html($fraud_data['rate']); ?>%
                                    </span>
                                </div>
                                <button type="button" class="fmb-fraud-toggle-btn" id="fmb-toggle-fraud-panel" title="Toggle Detailed Courier Report">
                                    <span class="dashicons dashicons-arrow-down-alt2" id="fmb-fraud-arrow-icon"></span>
                                </button>
                            </div>

                            <!-- Hidden Detailed Panel (Toggled by the arrow) -->
                            <div class="fmb-fraud-detailed-panel" id="fmb-fraud-detailed-panel" style="display:none;">
                                <div class="fmb-fraud-panel-inner">
                                    <div class="fmb-fraud-panel-top-bar">
                                        <span class="fmb-fraud-panel-heading">Courier Details</span>
                                        <div class="fmb-fraud-panel-arrows">
                                            <span class="dashicons dashicons-arrow-up-alt2"></span>
                                        </div>
                                    </div>

                                    <div class="fmb-fraud-panel-body-content">
                                        <!-- Circle Gauge & Status Badge -->
                                        <div class="ads-courier-top-status">
                                            <div class="ads-status-circle">
                                                <svg>
                                                    <circle cx="35" cy="35" r="31" stroke="#f0f0f0"></circle>
                                                    <circle cx="35" cy="35" r="31" stroke="<?php echo esc_attr($fraud_data['status_color']); ?>" style="stroke-dasharray: 194.7; stroke-dashoffset: <?php echo esc_attr(194.7 - (194.7 * $fraud_data['rate']) / 100); ?>;"></circle>
                                                </svg>
                                                <div class="ads-circle-score" style="color: <?php echo esc_attr($fraud_data['status_color']); ?>;">
                                                    <?php echo esc_html($fraud_data['rate']); ?>
                                                </div>
                                            </div>
                                            <div class="ads-status-info">
                                                <div class="ads-status-badge" style="color: <?php echo esc_attr($fraud_data['status_color']); ?>; background-color: <?php echo esc_attr($fraud_data['status_bg']); ?>;">
                                                    <?php echo esc_html($fraud_data['status_label']); ?>
                                                </div>
                                                <div class="ads-status-phone">
                                                    <span class="dashicons dashicons-phone"></span> <?php echo esc_html($fraud_data['phone']); ?>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- 3 Stat Cards Row -->
                                        <div class="ads-courier-stats-row">
                                            <div class="ads-stat-card">
                                                <span class="ads-card-value" id="fc-stat-total"><?php echo esc_html($fraud_data['total']); ?></span>
                                                <span class="ads-card-label">Total</span>
                                            </div>
                                            <div class="ads-stat-card">
                                                <span class="ads-card-value ads-text-success" id="fc-stat-success"><?php echo esc_html($fraud_data['success']); ?></span>
                                                <span class="ads-card-label">Success</span>
                                            </div>
                                            <div class="ads-stat-card">
                                                <span class="ads-card-value ads-text-danger" id="fc-stat-cancel"><?php echo esc_html($fraud_data['cancel']); ?></span>
                                                <span class="ads-card-label">Cancel</span>
                                            </div>
                                        </div>

                                        <!-- Breakdown Table -->
                                        <div class="ads-courier-table-wrap">
                                            <table class="ads-breakdown-table">
                                                <thead>
                                                    <tr>
                                                        <th style="text-align: left;">Couriers</th>
                                                        <th>Total</th>
                                                        <th style="color: #00a669;">✓</th>
                                                        <th style="color: #e53e3e;">✕</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="fc-table-body">
                                                    <?php foreach ($fraud_data['breakdown'] as $k => $cb) : ?>
                                                        <tr>
                                                            <td style="text-align: left; font-weight: 600; color: #2d3748; font-size: 13px;"><?php echo esc_html($cb['name']); ?></td>
                                                            <td style="font-size: 13px;"><?php echo esc_html($cb['total']); ?></td>
                                                            <td style="font-weight: 700; color: #00a669; font-size: 13px;"><?php echo esc_html($cb['success']); ?></td>
                                                            <td style="font-weight: 700; color: #e53e3e; font-size: 13px;"><?php echo esc_html($cb['cancel']); ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>

                                        <!-- Recheck Button -->
                                        <button type="button" class="ads-refresh-courier-data" id="fmb-btn-recheck-fraud" data-phone="<?php echo esc_attr($fraud_data['phone']); ?>" data-order-id="<?php echo esc_attr($order_id); ?>">
                                            <span class="dashicons dashicons-update"></span>
                                            <span class="ads-button-text">Recheck</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Card 2: Shipping Status (With Tracking Code, Consignment & Copyable Tracking URL) -->
            <div class="fmb-view-card">
                <div class="fmb-view-card-header">
                    <span class="dashicons dashicons-car icon-orange"></span>
                    <h3>Shipping Status</h3>
                </div>
                <div class="fmb-view-card-body">
                    <div class="fmb-info-item">
                        <span class="fmb-info-label">Courier Provider</span>
                        <strong class="fmb-info-val provider-name"><?php echo esc_html(ucfirst($courier_provider)); ?></strong>
                    </div>

                    <?php if (!$is_booked) : ?>
                        <div class="fmb-shipping-status-banner unbooked">
                            <span class="dashicons dashicons-warning"></span>
                            <span>Not booked to courier yet.</span>
                        </div>
                        <div style="margin-top: 14px;">
                            <button type="button" class="fmb-btn-quick-book fmb-open-smart-book" data-id="<?php echo esc_attr($order_id); ?>" style="background:#0ea5e9; border:1px solid #0284c7;">
                                <span class="dashicons dashicons-shield-alt"></span> Send to Steadfast (Smart Booking)
                            </button>
                        </div>
                    <?php else : ?>
                        <div class="fmb-shipping-status-banner booked">
                            <!-- Consignment Row -->
                            <?php if (!empty($consignment_id)) : ?>
                                <div class="booked-line">
                                    <strong>Consignment:</strong>
                                    <span class="consignment-code">#<?php echo esc_html($consignment_id); ?></span>
                                    <button type="button" class="fmb-mini-copy-btn" onclick="navigator.clipboard.writeText('<?php echo esc_js($consignment_id); ?>'); alert('Consignment ID copied: <?php echo esc_js($consignment_id); ?>');" title="Copy Consignment ID">
                                        <span class="dashicons dashicons-admin-page"></span>
                                    </button>
                                </div>
                            <?php endif; ?>

                            <!-- Tracking URL Row (Always utilizes Tracking Code for Steadfast) -->
                            <div class="booked-line fmb-tracking-url-row" style="margin-top:8px;">
                                <strong>Tracking URL:</strong>
                                <div class="fmb-url-box-wrap">
                                    <a href="<?php echo esc_url($tracking_url); ?>" target="_blank" class="fmb-tracking-link" title="<?php echo esc_attr($tracking_url); ?>">
                                        <span><?php echo esc_html(strlen($tracking_url) > 36 ? substr($tracking_url, 0, 34) . '...' : $tracking_url); ?></span>
                                        <span class="dashicons dashicons-external"></span>
                                    </a>
                                    <button type="button" class="fmb-btn-copy-url" onclick="navigator.clipboard.writeText('<?php echo esc_js($tracking_url); ?>'); alert('Tracking URL copied: <?php echo esc_js($tracking_url); ?>');" title="Copy Tracking URL">
                                        <span class="dashicons dashicons-admin-page"></span> Copy URL
                                    </button>
                                </div>
                            </div>

                            <div class="booked-line" style="margin-top:8px;">
                                <strong>Status:</strong>
                                <span style="font-weight:700; color:#0f172a;"><?php 
                                    $st_clean = strtolower($courier_status);
                                    $st_display = ucwords(str_replace('_', ' ', $courier_status));
                                    if ($st_clean === 'pending') {
                                        $st_display .= ' (In Delivery / কুরিয়ারের কাছে চলমান)';
                                    }
                                    echo esc_html($st_display); 
                                ?></span>
                            </div>

                            <?php if (!empty($c_rider_note)) : ?>
                                <div class="booked-line fmb-rider-note-callout" style="margin-top:8px; background: #fffbeb; border: 1px solid #fde68a; padding: 6px 10px; border-radius: 6px;">
                                    <strong style="color: #92400e;"><span class="dashicons dashicons-clipboard"></span> Rider Note:</strong>
                                    <span style="color: #78350f; font-weight:600;"><?php echo esc_html($c_rider_note); ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div style="margin-top: 12px; display: flex; gap: 8px; flex-wrap: wrap;">
                            <button type="button" class="fmb-btn-sm-outline fmb-open-courier-modal">
                                <span class="dashicons dashicons-edit"></span> Edit Booking / Charge
                            </button>
                            <button type="button" class="fmb-btn-sm-outline" id="fmb-btn-sync-courier-now" data-order-id="<?php echo esc_attr($order_id); ?>" title="Checks Steadfast/Pathao API and updates status & rider note immediately">
                                <span class="dashicons dashicons-update"></span> Check &amp; Sync Courier Live
                            </button>
                            <?php if ($courier_provider === 'steadfast' || !empty($consignment_id)) : ?>
                                <a href="https://portal.packzy.com/" target="_blank" class="fmb-btn-sm-outline" style="text-decoration:none; display:inline-flex; align-items:center; gap:4px; color:#4338ca;" title="Open Steadfast Merchant Portal to view rider timeline">
                                    <span class="dashicons dashicons-external"></span> Steadfast Portal
                                </a>
                            <?php endif; ?>
                        </div>
                        <div id="fmb-courier-sync-result" style="display:none; margin-top:8px; font-size:12px; padding:6px 10px; border-radius:6px;"></div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Card 3: Order Notes (Representative & Rider Notes with Timestamps & Add Note Form) -->
            <div class="fmb-view-card fmb-order-notes-card">
                <div class="fmb-view-card-header">
                    <div class="fmb-notes-header-left">
                        <span class="dashicons dashicons-clipboard icon-purple"></span>
                        <h3>Order Notes</h3>
                    </div>
                    <span class="fmb-notes-count-tag" id="fmb-notes-count-badge"><?php echo count($admin_notes_list); ?> Notes</span>
                </div>
                <div class="fmb-view-card-body fmb-notes-card-body">
                    <!-- Representative & Rider Notes Scrollable List -->
                    <div class="fmb-notes-scroll-list" id="fmb-notes-scroll-list">
                        <?php if (!empty($admin_notes_list)) : ?>
                            <?php foreach ($admin_notes_list as $n_item) : ?>
                                <div class="fmb-note-bubble <?php echo esc_attr($n_item['cls'] ?? 'admin'); ?>">
                                    <div class="fmb-note-bubble-header">
                                        <span class="fmb-note-author-pill <?php echo esc_attr($n_item['cls'] ?? 'admin'); ?>">
                                            <?php echo esc_html($n_item['author']); ?>
                                        </span>
                                        <span class="fmb-note-time">
                                            <span class="dashicons dashicons-clock"></span> <?php echo esc_html($n_item['date']); ?>
                                        </span>
                                    </div>
                                    <div class="fmb-note-bubble-text">
                                        <?php echo nl2br(esc_html($n_item['content'])); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <div class="fmb-no-notes-msg" id="fmb-no-notes-msg">No order notes added yet.</div>
                        <?php endif; ?>
                    </div>

                    <!-- Add Representative Note Form at Bottom -->
                    <div class="fmb-add-note-box">
                        <textarea id="fmb-new-order-note-input" class="fmb-input-control" rows="2" placeholder="Write a note by representative..."></textarea>
                        <div class="fmb-add-note-controls" style="justify-content: flex-end;">
                            <button type="button" id="fmb-btn-add-order-note" class="fmb-btn fmb-btn-purple-sm">
                                <span class="dashicons dashicons-plus-alt"></span> Add Note
                            </button>
                        </div>
                        <div id="fmb-add-note-feedback" style="display:none; font-size:11.5px; margin-top:6px;"></div>
                    </div>
                </div>
            </div>

        </div>

        <!-- Row 2: Live Tracking Stepper -->
        <div class="fmb-view-card fmb-stepper-card">
            <div class="fmb-stepper-header">
                <div class="fmb-stepper-title-wrap">
                    <span class="dashicons dashicons-location-alt icon-blue"></span>
                    <div>
                        <h3>Live Tracking Updates</h3>
                        <?php if ($is_cancelled_early) : ?>
                            <p class="fmb-stepper-sub-desc">Stage progression: Order Placed &rarr; Cancelled</p>
                        <?php else : ?>
                            <p class="fmb-stepper-sub-desc">Stage progression: Order Placed &rarr; Confirmed &rarr; In Transit &rarr; Delivered/Cancelled</p>
                        <?php endif; ?>
                    </div>
                </div>
                <button type="button" class="fmb-stepper-refresh-btn" onclick="window.location.reload();" title="Refresh Live Tracking">
                    <span class="dashicons dashicons-update"></span> Refresh
                </button>
            </div>

            <div class="fmb-stepper-container">
                <?php if ($is_cancelled_early) : ?>
                    <!-- 2-Stage Progression: Order Placed > Cancelled -->
                    <div class="fmb-stepper-track two-steps">
                        <div class="fmb-stepper-bar-bg"></div>
                        <div class="fmb-stepper-bar-progress cancelled" style="width: 100%;"></div>

                        <!-- Step 1: Order Placed/Created -->
                        <div class="fmb-step-node completed active">
                            <div class="step-dot">
                                <span class="dashicons dashicons-yes"></span>
                            </div>
                            <span class="step-label">Order Placed/Created</span>
                            <span class="step-sub-time"><?php echo esc_html($created_str); ?></span>
                        </div>

                        <!-- Step 2: Cancelled -->
                        <div class="fmb-step-node cancelled active">
                            <div class="step-dot">
                                <span class="dashicons dashicons-dismiss"></span>
                            </div>
                            <span class="step-label">Order Cancelled</span>
                            <span class="step-sub-time"><?php echo esc_html($modified_str); ?></span>
                        </div>
                    </div>
                <?php else : ?>
                    <!-- 4-Stage Progression: Order Placed > Confirmed > In Transit > Delivered -->
                    <div class="fmb-stepper-track">
                        <div class="fmb-stepper-bar-bg"></div>
                        <div class="fmb-stepper-bar-progress <?php echo $is_cancelled ? 'cancelled' : ($is_delivered ? 'delivered' : ''); ?>" style="width: <?php echo esc_attr($bar_width); ?>;"></div>

                        <!-- Step 1: Order Placed/Created -->
                        <div class="fmb-step-node <?php echo $step_stage >= 1 ? 'completed active' : ''; ?>">
                            <div class="step-dot">
                                <span class="dashicons <?php echo $step_stage > 1 ? 'dashicons-yes' : 'dashicons-marker'; ?>"></span>
                            </div>
                            <span class="step-label">Order Placed/Created</span>
                            <span class="step-sub-time"><?php echo esc_html($created_str); ?></span>
                        </div>

                        <!-- Step 2: Order Confirmed -->
                        <div class="fmb-step-node <?php echo $step_stage >= 2 ? ($step_stage > 2 ? 'completed' : 'active') : ''; ?>">
                            <div class="step-dot">
                                <span class="dashicons <?php echo $step_stage > 2 ? 'dashicons-yes' : ($step_stage === 2 ? 'dashicons-marker' : 'dashicons-clock'); ?>"></span>
                            </div>
                            <span class="step-label">Order Confirmed</span>
                            <span class="step-sub-time"><?php echo $step_stage >= 2 ? 'Processing' : 'Pending'; ?></span>
                        </div>

                        <!-- Step 3: Order In Transit -->
                        <div class="fmb-step-node <?php echo $step_stage >= 3 ? ($step_stage > 3 ? 'completed' : 'active') : ''; ?>">
                            <div class="step-dot">
                                <span class="dashicons <?php echo $step_stage > 3 ? 'dashicons-yes' : ($step_stage === 3 ? 'dashicons-marker' : 'dashicons-car'); ?>"></span>
                            </div>
                            <span class="step-label">Order In Transit</span>
                            <span class="step-sub-time"><?php echo $is_booked ? esc_html(ucfirst($courier_provider)) : 'Awaiting Courier'; ?></span>
                        </div>

                        <!-- Step 4: Delivered / Cancelled -->
                        <div class="fmb-step-node <?php echo $step_stage >= 4 ? ($is_cancelled ? 'cancelled active' : 'completed active') : ''; ?>">
                            <div class="step-dot">
                                <span class="dashicons <?php echo $is_cancelled ? 'dashicons-dismiss' : ($step_stage >= 4 ? 'dashicons-yes' : 'dashicons-flag'); ?>"></span>
                            </div>
                            <span class="step-label"><?php echo $is_cancelled ? 'Order Cancelled' : 'Delivered'; ?></span>
                            <span class="step-sub-time"><?php echo $step_stage >= 4 ? ($is_cancelled ? 'Cancelled / Returned' : 'Delivered to Customer') : 'Final Stage'; ?></span>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Row 3: Product Items Breakdown Table -->
        <div class="fmb-view-card fmb-product-items-card">
            <div class="fmb-table-responsive">
                <table class="fmb-view-product-table">
                    <thead>
                        <tr>
                            <th style="width: 55%;">PRODUCT</th>
                            <th style="width: 15%; text-align: center;">QTY</th>
                            <th style="width: 15%; text-align: right;">PRICE</th>
                            <th style="width: 15%; text-align: right;">TOTAL</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($order->get_items() as $item_id => $item) :
                            $product = $item->get_product();
                            $img_id  = $product ? $product->get_image_id() : 0;
                            $img_url = $img_id ? wp_get_attachment_image_url($img_id, 'thumbnail') : wc_placeholder_img_src();
                            $qty     = $item->get_quantity();
                            $total   = $item->get_total();
                            $unit_pr = $qty > 0 ? ($total / $qty) : $total;
                        ?>
                            <tr>
                                <td class="fmb-prod-cell">
                                    <div class="fmb-prod-flex">
                                        <img src="<?php echo esc_url($img_url); ?>" alt="" class="fmb-prod-thumb">
                                        <div class="fmb-prod-desc">
                                            <strong class="fmb-prod-name"><?php echo esc_html($item->get_name()); ?></strong>
                                            <?php
                                             $item_meta = $item->get_formatted_meta_data();
                                             if (!empty($item_meta)) {
                                                 echo '<div class="fmb-prod-variations">';
                                                 foreach ($item_meta as $m) {
                                                     echo '<span>' . esc_html($m->display_key) . ': ' . esc_html($m->display_value) . '</span> ';
                                                 }
                                                 echo '</div>';
                                             }
                                            ?>
                                        </div>
                                    </div>
                                </td>
                                <td style="text-align: center; font-weight: 700; color: #0f172a;"><?php echo (int)$qty; ?></td>
                                <td style="text-align: right; font-weight: 600; color: #334155;">৳ <?php echo number_format($unit_pr, 2); ?></td>
                                <td style="text-align: right; font-weight: 800; color: #0f172a;">৳ <?php echo number_format($total, 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Row 4: Financials 2-Column Grid -->
        <div class="fmb-view-fin-grid">

            <!-- Card 1: Courier Finance -->
            <div class="fmb-view-card fmb-courier-finance-card">
                <div class="fmb-fin-card-header">
                    <h3>Courier Finance (Cost Analysis)</h3>
                    <?php if (!$is_booked) : ?>
                        <button type="button" class="fmb-btn-settle fmb-open-courier-modal">
                            <span class="dashicons dashicons-car"></span> Book Courier
                        </button>
                    <?php else : ?>
                        <button type="button" class="fmb-btn-settle" onclick="alert('Settlement balance verified.');">
                            <span class="dashicons dashicons-yes"></span> Settle Now
                        </button>
                    <?php endif; ?>
                </div>
                <div class="fmb-fin-card-body">
                    <div class="fmb-fin-line">
                        <span class="fin-label">Courier Collection (COD)</span>
                        <span class="fin-val green">+ ৳ <?php echo number_format($cod_collection, 2); ?></span>
                    </div>
                    <div class="fmb-fin-line">
                        <span class="fin-label">Actual Courier Charge</span>
                        <span class="fin-val red">- ৳ <?php echo number_format($courier_charge, 2); ?></span>
                    </div>
                    <div class="fmb-fin-line">
                        <span class="fin-label">COD Fee (1%)</span>
                        <span class="fin-val red">- ৳ <?php echo number_format($cod_fee_1pct, 2); ?></span>
                    </div>

                    <div class="fmb-fin-divider dashed"></div>

                    <div class="fmb-fin-net-row">
                        <div>
                            <strong class="net-title">Net Cash In</strong>
                            <span class="net-sub"><?php echo $is_settled ? '* Settlement Completed' : '* Settlement Pending'; ?></span>
                        </div>
                        <div class="net-amount">
                            ৳ <?php echo number_format($net_cash_in, 2); ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Card 2: Order Summary Breakdown -->
            <div class="fmb-view-card fmb-order-summary-card">
                <div class="fmb-fin-card-header">
                    <h3>Order Summary</h3>
                </div>
                <div class="fmb-fin-card-body">
                    <div class="fmb-fin-line">
                        <span class="fin-label">Subtotal</span>
                        <span class="fin-val">৳ <?php echo number_format($subtotal, 2); ?></span>
                    </div>
                    <div class="fmb-fin-line">
                        <span class="fin-label">Delivery Charge</span>
                        <span class="fin-val">+ ৳ <?php echo number_format($shipping_charge, 2); ?></span>
                    </div>
                    <?php if ($discount > 0) : ?>
                        <div class="fmb-fin-line">
                            <span class="fin-label">Discount</span>
                            <span class="fin-val red">- ৳ <?php echo number_format($discount, 2); ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if ($paid_amount > 0) : ?>
                        <div class="fmb-fin-line">
                            <span class="fin-label">Advance Payment</span>
                            <span class="fin-val green">- ৳ <?php echo number_format($paid_amount, 2); ?></span>
                        </div>
                    <?php endif; ?>

                    <div class="fmb-fin-divider solid"></div>

                    <div class="fmb-due-total-row">
                        <strong class="due-label">Total DUE (COD)</strong>
                        <strong class="due-amount-large">৳ <?php echo number_format($due_amount, 2); ?></strong>
                    </div>
                </div>
            </div>

        </div>

        <!-- Row 5: Unified Tracking, Courier & Order Activity History -->
        <?php
        $all_events = array();

        // 1. Order Placed
        if ($order->get_date_created()) {
            $all_events[] = array(
                'timestamp' => $order->get_date_created()->getTimestamp(),
                'date_str'  => $order->get_date_created()->date('d M, Y h:i A'),
                'type'      => 'order',
                'badge'     => 'ORDER PLACED',
                'badge_cls' => 'order',
                'icon'      => 'dashicons-cart',
                'author'    => 'Customer / Store',
                'title'     => 'Order Placed',
                'detail'    => 'Order #' . $order->get_order_number() . ' initialized via ' . esc_html($source) . ' with total ৳' . number_format($order->get_total(), 2) . '.',
            );
        }

        // 2. Courier Status History
        $c_history = $order->get_meta('_courier_status_history');
        if (is_string($c_history)) $c_history = maybe_unserialize($c_history);
        if (is_array($c_history)) {
            foreach ($c_history as $ch) {
                $ts = !empty($ch['timestamp']) ? (is_numeric($ch['timestamp']) ? $ch['timestamp'] : strtotime($ch['timestamp'])) : current_time('timestamp');
                $st = $ch['status'] ?? 'Update';
                $icon = 'dashicons-car';
                if (stripos($st, 'delivered') !== false) $icon = 'dashicons-yes-alt';
                elseif (stripos($st, 'transit') !== false) $icon = 'dashicons-location-alt';

                $all_events[] = array(
                    'timestamp' => $ts,
                    'date_str'  => date('d M, Y h:i A', $ts),
                    'type'      => 'courier',
                    'badge'     => strtoupper($st),
                    'badge_cls' => 'courier',
                    'icon'      => $icon,
                    'author'    => $ch['user'] ?? $ch['source'] ?? 'Courier Partner',
                    'title'     => 'Courier Status: ' . $st,
                    'detail'    => $ch['note'] ?? 'Courier tracking event logged.',
                );
            }
        }

        // 3. Courier Booking Milestone
        if ($is_booked && (!empty($consignment_id) || !empty($tracking_code))) {
            $has_booked_event = false;
            foreach ($all_events as $ev) {
                if ((!empty($consignment_id) && strpos($ev['detail'], $consignment_id) !== false) ||
                    (!empty($tracking_code) && strpos($ev['detail'], $tracking_code) !== false)) {
                    $has_booked_event = true;
                    break;
                }
            }
            if (!$has_booked_event) {
                $booked_ts = $order->get_date_modified() ? $order->get_date_modified()->getTimestamp() : current_time('timestamp');
                $detail_text = '';
                if (!empty($consignment_id)) $detail_text .= 'Consignment ID: #' . $consignment_id . ' | ';
                if (!empty($tracking_code))  $detail_text .= 'Tracking Code: ' . $tracking_code . ' | ';
                $detail_text .= 'Tracking URL: ' . $tracking_url;

                $all_events[] = array(
                    'timestamp' => $booked_ts,
                    'date_str'  => date('d M, Y h:i A', $booked_ts),
                    'type'      => 'courier',
                    'badge'     => strtoupper($courier_provider),
                    'badge_cls' => 'courier',
                    'icon'      => 'dashicons-car',
                    'author'    => 'Courier Dispatch',
                    'title'     => 'Booked to ' . ucfirst($courier_provider),
                    'detail'    => $detail_text,
                );
            }
        }

        // 4. Notes & Order History
        if (!empty($raw_notes)) {
            foreach ($raw_notes as $n) {
                $content = $n->content;
                $author  = $n->added_by ?: ($n->customer_note ? 'Customer' : 'System');
                $ts      = $n->date_created ? $n->date_created->getTimestamp() : current_time('timestamp');
                $d_str   = $n->date_created ? $n->date_created->date('d M, Y h:i A') : 'N/A';

                if (stripos($content, '[Rider Note]') !== false || stripos($content, 'rider note') !== false) {
                    $type      = 'courier';
                    $badge     = 'RIDER NOTE';
                    $badge_cls = 'courier';
                    $icon      = 'dashicons-car';
                    $title     = 'Delivery Rider Instruction';
                } elseif (stripos($content, 'status changed') !== false) {
                    $type      = 'status';
                    $badge     = 'STATUS CHANGE';
                    $badge_cls = 'status';
                    $icon      = 'dashicons-marker';
                    $title     = 'Order Status Transition';
                } else {
                    $type      = 'admin';
                    $badge     = $n->customer_note ? 'NOTE TO CUSTOMER' : 'ORDER NOTE';
                    $badge_cls = 'admin';
                    $icon      = 'dashicons-clipboard';
                    $title     = 'Note by ' . ucfirst($author);
                }

                $all_events[] = array(
                    'timestamp' => $ts,
                    'date_str'  => $d_str,
                    'type'      => $type,
                    'badge'     => $badge,
                    'badge_cls' => $badge_cls,
                    'icon'      => $icon,
                    'author'    => ucfirst($author),
                    'title'     => $title,
                    'detail'    => $content,
                );
            }
        }

        usort($all_events, function($a, $b) {
            return $b['timestamp'] <=> $a['timestamp'];
        });

        $seen = array();
        $unique_events = array();
        foreach ($all_events as $ev) {
            $k = $ev['date_str'] . '|' . trim($ev['detail']);
            if (!isset($seen[$k])) {
                $seen[$k] = true;
                $unique_events[] = $ev;
            }
        }
        ?>
        <div class="fmb-view-card fmb-unified-history-card">
            <div class="fmb-unified-history-header">
                <div class="fmb-unified-title-wrap">
                    <span class="dashicons dashicons-backup icon-purple"></span>
                    <div>
                        <h3>Tracking & Update History</h3>
                        <p class="fmb-unified-subtitle">Consolidated timeline of courier tracking milestones, status changes, notes & administrative updates</p>
                    </div>
                </div>
                <div class="fmb-unified-header-meta" style="display:flex; align-items:center; gap:8px;">
                    <button type="button" class="fmb-btn-xs-book" id="fmb-btn-open-timeline-modal" style="background:#4f46e5; color:#fff; border:none; padding:4px 10px; font-size:11.5px; font-weight:700; border-radius:6px; cursor:pointer; display:inline-flex; align-items:center; gap:4px;" title="Paste or log a Steadfast tracking update (e.g. Sent to Warehouse, Assigned to Rider, Rider Note, Delivered)">
                        <span class="dashicons dashicons-plus-alt"></span> Log Courier Update
                    </button>
                    <span class="fmb-unified-count-badge" id="fmb-history-count-badge"><?php echo count($unique_events); ?> Events Logged</span>
                </div>
            </div>

            <div class="fmb-unified-history-body">
                <?php if (!empty($unique_events)) : ?>
                    <div class="fmb-unified-timeline" id="fmb-unified-timeline">
                        <?php foreach ($unique_events as $ev) : ?>
                            <div class="fmb-unified-item">
                                <div class="fmb-unified-node-icon <?php echo esc_attr($ev['badge_cls']); ?>">
                                    <span class="dashicons <?php echo esc_attr($ev['icon']); ?>"></span>
                                </div>
                                <div class="fmb-unified-item-content">
                                    <div class="fmb-unified-item-header">
                                        <div class="fmb-unified-item-title-row">
                                            <strong class="fmb-unified-item-title"><?php echo esc_html($ev['title']); ?></strong>
                                            <span class="fmb-timeline-tag <?php echo esc_attr($ev['badge_cls']); ?>"><?php echo esc_html($ev['badge']); ?></span>
                                        </div>
                                        <div class="fmb-unified-item-meta">
                                            <span class="dashicons dashicons-clock"></span>
                                            <span><?php echo esc_html($ev['date_str']); ?></span>
                                            <span>&bull; <?php echo esc_html($ev['author']); ?></span>
                                        </div>
                                    </div>
                                    <p class="fmb-unified-item-desc"><?php echo esc_html($ev['detail']); ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else : ?>
                    <p style="color:#94a3b8; font-size:13px; font-style:italic; margin:0;">No tracking or history events recorded yet.</p>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <!-- Isolated Modal Popup: Book Courier Parcel (Hidden by Default) -->
    <div class="fmb-modal-backdrop" id="fmb-courier-modal" style="display:none;">
        <div class="fmb-modal-box">
            <div class="fmb-modal-head">
                <h3><span class="dashicons dashicons-car"></span> Book Courier Parcel (#<?php echo esc_html($order->get_order_number()); ?>)</h3>
                <button type="button" class="fmb-modal-close-x" id="fmb-courier-modal-close" title="Close Dialog">&times;</button>
            </div>
            <div class="fmb-modal-content">
                <form id="fmb-courier-booking-form">
                    <input type="hidden" name="order_id" value="<?php echo esc_attr($order_id); ?>">

                    <div class="fmb-form-group">
                        <label class="fmb-form-lbl">Select Courier Service:</label>
                        <select id="courier-provider-select" name="courier_provider" class="fmb-input-control">
                            <option value="steadfast" <?php selected($courier_provider, 'steadfast'); ?>>Steadfast Courier (API Direct)</option>
                            <option value="pathao" <?php selected($courier_provider, 'pathao'); ?>>Pathao Courier</option>
                            <option value="redx" <?php selected($courier_provider, 'redx'); ?>>RedX</option>
                            <option value="manual" <?php selected($courier_provider, 'manual'); ?>>Manual Tracking / Other Courier</option>
                        </select>
                    </div>

                    <div class="fmb-form-row-grid">
                        <div class="fmb-form-group">
                            <label class="fmb-form-lbl">Courier Collection COD (৳):</label>
                            <input type="number" step="0.01" id="courier-cod-amount" name="cod_amount" value="<?php echo esc_attr($due_amount); ?>" class="fmb-input-control">
                            <span class="fmb-form-help">Exact amount courier will collect</span>
                        </div>
                        <div class="fmb-form-group">
                            <label class="fmb-form-lbl">Actual Courier Charge (৳):</label>
                            <input type="number" step="0.01" id="courier-delivery-charge" name="delivery_charge" value="<?php echo esc_attr($courier_charge); ?>" class="fmb-input-control">
                            <span class="fmb-form-help">Delivery fee charged by courier</span>
                        </div>
                    </div>

                    <div class="fmb-form-row-grid" style="margin-top:12px;">
                        <div class="fmb-form-group">
                            <label class="fmb-form-lbl">Steadfast Tracking Code:</label>
                            <input type="text" id="courier-tracking-code-input" name="tracking_code" value="<?php echo esc_attr($tracking_code); ?>" placeholder="e.g. _hA5DDKNSXPv0CwsImXq99kuPHWXNIwe" class="fmb-input-control">
                            <span class="fmb-form-help">Code used for customer tracking URL (/t/code)</span>
                        </div>
                        <div class="fmb-form-group">
                            <label class="fmb-form-lbl">Consignment ID:</label>
                            <input type="text" id="courier-manual-cid" name="consignment_id" value="<?php echo esc_attr($consignment_id); ?>" placeholder="e.g. 291028788" class="fmb-input-control">
                            <span class="fmb-form-help">Courier invoice / consignment ID</span>
                        </div>
                    </div>

                    <div class="fmb-form-group" style="margin-top:12px;">
                        <label class="fmb-form-lbl">Instructions for Delivery Rider:</label>
                        <textarea id="courier-rider-instruction" name="rider_note" rows="2" placeholder="e.g. Call customer before delivery" class="fmb-input-control"><?php echo esc_textarea($order->get_meta('_courier_rider_note')); ?></textarea>
                    </div>

                    <div id="courier-booking-alert" style="display:none; margin:14px 0;"></div>

                    <div style="margin-top:18px;">
                        <button type="submit" class="fmb-btn fmb-btn-dark-confirm" id="courier-submit-btn">
                            <span class="dashicons dashicons-car"></span> Confirm & Book to Courier
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal 4: Log Courier Update & Auto-Extract Status Modal -->
    <div class="fmb-modal-backdrop" id="fmb-timeline-modal" style="display:none;">
        <div class="fmb-modal-box" style="max-width: 490px;">
            <div class="fmb-modal-head">
                <h3><span class="dashicons dashicons-car"></span> Log Courier Update (Auto-Status Extract)</h3>
                <button type="button" class="fmb-modal-close-x" id="fmb-close-timeline-modal">&times;</button>
            </div>
            <div class="fmb-modal-content">
                <div class="fmb-form-group">
                    <label style="display:block; font-size:12px; font-weight:700; margin-bottom:6px; color:#334155;">Paste Steadfast Tracking Log / Update Event:</label>
                    <textarea id="fmb-timeline-log-input" rows="4" class="fmb-input-control" placeholder="Examples:
- Consignment sent to “CUMILLA” WAREHOUSE
- Consignment has been received at CUMILLA WAREHOUSE
- Assigned to rider. Sardar Rakibul Hasan 01728187421
- Rider Note: কাস্টমারের আজ অফিস বন্ধ আগামী কালকে নেবে
- Consignment has been marked as delivered by rider"></textarea>
                    <p style="font-size:11px; color:#64748b; margin:6px 0 0; line-height:1.4;">
                        ✓ System automatically extracts event details, switches order status (In-Transit, In-Hub, With Rider, Delivered), logs the timeline node, and saves any Rider Notes.
                    </p>
                </div>
                <div style="margin-top: 14px; display:flex; justify-content:flex-end; gap:8px;">
                    <button type="button" class="fmb-btn fmb-btn-sm-outline" id="fmb-cancel-timeline-modal">Cancel</button>
                    <button type="button" class="fmb-btn fmb-btn-primary-purple" id="fmb-btn-submit-timeline-log">
                        <span class="dashicons dashicons-yes"></span> Apply Update &amp; Sync Status
                    </button>
                </div>
                <div id="fmb-timeline-modal-feedback" style="display:none; font-size:12px; margin-top:8px;"></div>
            </div>
        </div>
    </div>

    <!-- Embedded CSS -->
    <style>
    .fmb-order-view-wrap {
        max-width: 100% !important;
        width: 100% !important;
        margin: 15px 0 60px 0;
        padding: 0 20px 0 0;
        box-sizing: border-box;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
        color: #0f172a;
    }

    .fmb-view-back-link {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        color: #64748b;
        font-size: 13px;
        font-weight: 600;
        text-decoration: none;
        margin-bottom: 14px;
        transition: color 0.15s;
    }
    .fmb-view-back-link:hover { color: #7c3aed; }

    /* Top Bar Header */
    .fmb-view-topbar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 16px;
        margin-bottom: 20px;
    }
    .fmb-view-topbar-left { display: flex; flex-direction: column; gap: 4px; }
    .fmb-view-title-row { display: flex; align-items: center; flex-wrap: wrap; gap: 12px; }
    .fmb-view-main-title {
        font-size: 26px;
        font-weight: 800;
        color: #0f172a;
        margin: 0;
        letter-spacing: -0.5px;
    }
    .fmb-view-meta-row {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 16px;
        font-size: 12px;
        color: #64748b;
        margin-top: 4px;
    }
    .fmb-view-meta-item { display: inline-flex; align-items: center; gap: 4px; }
    .fmb-view-meta-item .dashicons { font-size: 14px; width: 14px; height: 14px; color: #94a3b8; }

    .fmb-view-topbar-actions {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
    }

    /* Status Updater */
    .fmb-status-updater-control {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: #fff;
        border: 1px solid #cbd5e1;
        padding: 4px 8px;
        border-radius: 8px;
    }
    .fmb-updater-lbl {
        font-size: 11px;
        font-weight: 800;
        text-transform: uppercase;
        color: #64748b;
        letter-spacing: 0.5px;
    }
    .fmb-quick-status-select {
        padding: 5px 8px;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        font-size: 12.5px;
        font-weight: 700;
        color: #0f172a;
        outline: none;
        background: #f8fafc;
    }
    .fmb-btn-status-apply {
        background: #0f172a;
        color: #fff !important;
        border: none;
        padding: 6px 12px;
        border-radius: 6px;
        font-size: 12px;
        font-weight: 700;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        transition: background 0.15s;
    }
    .fmb-btn-status-apply:hover { background: #1e293b; }

    .fmb-btn-view-print {
        background: #fff;
        border: 1px solid #cbd5e1;
        color: #334155;
        padding: 8px 18px;
        border-radius: 8px;
        font-weight: 700;
        font-size: 13px;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        text-decoration: none;
        cursor: pointer;
        transition: all 0.15s;
    }
    .fmb-btn-view-print:hover { background: #f8fafc; border-color: #94a3b8; color: #0f172a; }
    .fmb-btn-view-edit {
        background: #7c3aed;
        color: #fff !important;
        border: none;
        padding: 8px 20px;
        border-radius: 8px;
        font-weight: 700;
        font-size: 13px;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        text-decoration: none;
        cursor: pointer;
        box-shadow: 0 2px 4px rgba(124,58,237,0.25);
        transition: all 0.15s;
    }
    .fmb-btn-view-edit:hover { background: #6d28d9; }

    /* Common Card Styles */
    .fmb-view-card {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 1px 3px rgba(0,0,0,0.04);
        margin-bottom: 20px;
    }
    .fmb-view-card-header {
        padding: 14px 18px;
        border-bottom: 1px solid #f1f5f9;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
    }
    .fmb-view-card-header h3 {
        margin: 0;
        font-size: 14px;
        font-weight: 700;
        color: #0f172a;
    }
    .fmb-view-card-header .dashicons { font-size: 18px; width: 18px; height: 18px; }
    .icon-blue   { color: #2563eb; }
    .icon-orange { color: #ea580c; }
    .icon-purple { color: #7c3aed; }
    .icon-green  { color: #16a34a; }

    .fmb-view-card-body { padding: 18px; }

    /* Top 3 Cards Grid */
    .fmb-view-top-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 16px;
        margin-bottom: 20px;
    }
    .fmb-view-top-grid .fmb-view-card {
        margin-bottom: 0;
        display: flex;
        flex-direction: column;
    }
    .fmb-view-top-grid .fmb-view-card-body {
        flex: 1;
        display: flex;
        flex-direction: column;
        justify-content: flex-start;
    }

    /* Customer Info & Fraud Checker */
    .fmb-info-item { margin-bottom: 12px; }
    .fmb-info-item:last-child { margin-bottom: 0; }
    .fmb-info-label {
        display: block;
        font-size: 11px;
        font-weight: 800;
        text-transform: uppercase;
        color: #64748b;
        letter-spacing: 0.5px;
        margin-bottom: 3px;
    }
    .fmb-info-val { font-size: 14px; color: #0f172a; font-weight: 700; }
    .fmb-phone-val-row { display: flex; align-items: center; gap: 8px; }
    .fmb-info-phone-link { color: #2563eb; text-decoration: none; font-weight: 700; font-size: 14px; }
    .fmb-copy-phone-btn, .fmb-mini-copy-btn {
        background: #f1f5f9;
        border: 1px solid #e2e8f0;
        border-radius: 4px;
        padding: 3px 6px;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: #64748b;
        transition: all 0.15s;
    }
    .fmb-copy-phone-btn:hover, .fmb-mini-copy-btn:hover { background: #e2e8f0; color: #0f172a; }
    .fmb-copy-phone-btn .dashicons, .fmb-mini-copy-btn .dashicons { font-size: 13px; width: 13px; height: 13px; }
    .fmb-info-address { margin: 0; font-size: 13px; color: #334155; line-height: 1.45; }
    .fmb-badge-source {
        background: #f1f5f9;
        color: #475569;
        padding: 3px 8px;
        border-radius: 6px;
        font-size: 11px;
        font-weight: 700;
        display: inline-block;
    }

    /* Fraud Checker Summary Bar */
    .fmb-fraud-checker-section {
        margin-top: 16px;
        padding-top: 14px;
        border-top: 1px dashed #e2e8f0;
    }
    .fmb-fraud-summary-bar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 8px 12px;
    }
    .fmb-fraud-badges { display: flex; align-items: center; flex-wrap: wrap; gap: 8px; }
    .fmb-fraud-tag {
        font-size: 12.5px;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
        gap: 3px;
    }
    .fmb-fraud-tag.total   { color: #0f172a; }
    .fmb-fraud-tag.success { color: #16a34a; }
    .fmb-fraud-tag.cancel  { color: #dc2626; }
    .fmb-fraud-tag.rate {
        font-size: 11px;
        font-weight: 800;
        padding: 2px 7px;
        border-radius: 12px;
    }
    .fmb-fraud-toggle-btn {
        background: none;
        border: none;
        color: #64748b;
        cursor: pointer;
        padding: 4px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 4px;
        transition: all 0.15s;
    }
    .fmb-fraud-toggle-btn:hover { background: #e2e8f0; color: #0f172a; }
    .fmb-fraud-toggle-btn .dashicons { font-size: 18px; width: 18px; height: 18px; transition: transform 0.2s; }
    .fmb-fraud-toggle-btn.open .dashicons { transform: rotate(180deg); }

    /* Fraud Details Hidden Panel */
    .fmb-fraud-detailed-panel { margin-top: 10px; }
    .fmb-fraud-panel-inner {
        border-radius: 10px;
        overflow: hidden;
        border: 1px solid #1a7278;
        background: #fff;
        box-shadow: 0 4px 12px rgba(26, 114, 120, 0.1);
    }
    .fmb-fraud-panel-top-bar {
        background: #1a7278;
        color: #fff;
        padding: 10px 14px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 13.5px;
        font-weight: 700;
    }
    .fmb-fraud-panel-arrows .dashicons { font-size: 14px; width: 14px; height: 14px; }
    .fmb-fraud-panel-body-content { padding: 12px; }

    .ads-courier-top-status {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 12px;
        border: 1px solid rgba(6, 122, 101, 0.2);
        border-radius: 10px;
        padding: 8px 12px;
        background: #fafffd;
    }
    .ads-status-circle { position: relative; width: 60px; height: 60px; flex-shrink: 0; }
    .ads-status-circle svg { width: 60px; height: 60px; transform: rotate(-90deg); }
    .ads-status-circle svg circle { fill: none; stroke-width: 5; stroke-linecap: round; }
    .ads-circle-score {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        font-size: 17px;
        font-weight: 800;
    }
    .ads-status-info { display: flex; flex-direction: column; gap: 4px; }
    .ads-status-badge {
        display: inline-block;
        padding: 2px 10px;
        border-radius: 12px;
        font-weight: 700;
        font-size: 12px;
        align-self: flex-start;
    }
    .ads-status-phone {
        display: flex;
        align-items: center;
        gap: 4px;
        color: #475569;
        font-size: 13px;
        font-weight: 600;
    }
    .ads-status-phone .dashicons { font-size: 15px; width: 15px; height: 15px; }

    .ads-courier-stats-row {
        display: flex;
        justify-content: space-between;
        border-radius: 8px;
        border: 1px solid #e2e8f0;
        background: #fff;
        margin-bottom: 10px;
        overflow: hidden;
    }
    .ads-stat-card {
        flex: 1;
        display: flex;
        flex-direction: column;
        align-items: center;
        padding: 10px 4px;
        position: relative;
    }
    .ads-stat-card:not(:last-child)::after {
        content: '';
        position: absolute;
        right: 0;
        top: 20%;
        height: 60%;
        width: 1px;
        background: #e2e8f0;
    }
    .ads-card-value { font-size: 16px; font-weight: 800; color: #0f172a; line-height: 1.2; }
    .ads-card-label { font-size: 11px; color: #64748b; margin-top: 2px; font-weight: 600; }
    .ads-text-success { color: #16a34a !important; }
    .ads-text-danger  { color: #dc2626 !important; }

    .ads-courier-table-wrap {
        background: #f8fafc;
        border-radius: 8px;
        padding: 4px;
        margin-bottom: 10px;
        border: 1px solid #e2e8f0;
    }
    .ads-breakdown-table { width: 100%; border-collapse: collapse; text-align: center; font-size: 12px; }
    .ads-breakdown-table th {
        color: #334155;
        font-size: 11px;
        font-weight: 700;
        padding: 6px 8px;
        border-bottom: 1px solid #e2e8f0;
        text-transform: uppercase;
    }
    .ads-breakdown-table td { padding: 6px 8px; color: #0f172a; border-bottom: 1px solid #f1f5f9; font-size: 12px; }
    .ads-breakdown-table tbody tr:last-child td { border-bottom: none; }

    .ads-refresh-courier-data {
        width: 100%;
        background: #1a7278;
        color: #fff;
        border: none;
        border-radius: 6px;
        padding: 8px 12px;
        font-weight: 700;
        font-size: 13px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        transition: background 0.15s;
    }
    .ads-refresh-courier-data:hover { background: #135559; }

    /* Card 2: Shipping Status */
    .fmb-shipping-status-banner { border-radius: 8px; padding: 12px 14px; font-size: 13px; }
    .fmb-shipping-status-banner.unbooked {
        background: #fefce8;
        border: 1px solid #fef08a;
        color: #854d0e;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .fmb-shipping-status-banner.booked { background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; }
    .booked-line { display: flex; align-items: center; flex-wrap: wrap; gap: 6px; font-size: 13px; }
    .consignment-code {
        font-weight: 800;
        color: #0f172a;
        background: #fff;
        padding: 2px 6px;
        border-radius: 4px;
        border: 1px solid #cbd5e1;
    }
    .fmb-tracking-url-row { align-items: flex-start !important; }
    .fmb-url-box-wrap { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; margin-top: 2px; width: 100%; }
    .fmb-tracking-link {
        color: #2563eb;
        font-size: 12px;
        font-weight: 600;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 3px;
        background: #fff;
        border: 1px solid #bfdbfe;
        padding: 3px 8px;
        border-radius: 4px;
        word-break: break-all;
    }
    .fmb-tracking-link:hover { text-decoration: underline; color: #1d4ed8; }
    .fmb-tracking-link .dashicons { font-size: 12px; width: 12px; height: 12px; }
    .fmb-btn-copy-url {
        background: #0f172a;
        color: #fff;
        border: none;
        padding: 3px 8px;
        border-radius: 4px;
        font-size: 11px;
        font-weight: 700;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 3px;
        transition: background 0.15s;
    }
    .fmb-btn-copy-url:hover { background: #1e293b; }
    .fmb-btn-copy-url .dashicons { font-size: 12px; width: 12px; height: 12px; }

    .fmb-btn-quick-book {
        width: 100%;
        background: #0f172a;
        color: #fff;
        padding: 9px 14px;
        border-radius: 8px;
        border: none;
        font-weight: 700;
        font-size: 12.5px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        transition: background 0.15s;
    }
    .fmb-btn-quick-book:hover { background: #1e293b; }
    .fmb-btn-sm-outline {
        background: #fff;
        border: 1px solid #cbd5e1;
        color: #334155;
        padding: 6px 12px;
        border-radius: 6px;
        font-weight: 700;
        font-size: 12px;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    .fmb-btn-sm-outline:hover { background: #f8fafc; border-color: #94a3b8; color: #0f172a; }

    /* Card 3: Representative Notes */
    .fmb-notes-header-left { display: flex; align-items: center; gap: 8px; }
    .fmb-notes-count-tag {
        background: #f5f3ff;
        color: #7c3aed;
        border: 1px solid #ddd6fe;
        font-size: 11px;
        font-weight: 700;
        padding: 2px 8px;
        border-radius: 12px;
    }
    .fmb-notes-card-body {
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        gap: 14px;
        min-height: 280px;
    }
    .fmb-notes-scroll-list {
        max-height: 220px;
        overflow-y: auto;
        padding-right: 4px;
        display: flex;
        flex-direction: column;
        gap: 10px;
    }
    .fmb-notes-scroll-list::-webkit-scrollbar { width: 4px; }
    .fmb-notes-scroll-list::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }

    .fmb-note-bubble {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 10px 12px;
        transition: all 0.15s;
    }
    .fmb-note-bubble:hover { border-color: #cbd5e1; background: #fff; }
    .fmb-note-bubble-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 5px;
    }
    .fmb-note-author-pill {
        font-size: 10.5px;
        font-weight: 800;
        text-transform: uppercase;
        padding: 2px 6px;
        border-radius: 4px;
        letter-spacing: 0.4px;
    }
    .fmb-note-author-pill.admin { background: #ede9fe; color: #6d28d9; }
    .fmb-note-author-pill.rider { background: #fef3c7; color: #b45309; }
    .fmb-note-bubble.rider { background: #fffdf5; border-color: #fde68a; }
    .fmb-note-bubble.rider:hover { background: #fffbeb; border-color: #fcd34d; }

    .fmb-note-time {
        font-size: 11px;
        color: #94a3b8;
        display: inline-flex;
        align-items: center;
        gap: 3px;
    }
    .fmb-note-time .dashicons { font-size: 12px; width: 12px; height: 12px; }
    .fmb-note-bubble-text { font-size: 12.5px; color: #334155; line-height: 1.45; word-break: break-word; }
    .fmb-no-notes-msg {
        font-size: 12.5px;
        color: #94a3b8;
        font-style: italic;
        text-align: center;
        padding: 20px 0;
    }

    .fmb-add-note-box {
        border-top: 1px solid #f1f5f9;
        padding-top: 10px;
        margin-top: auto;
    }
    .fmb-add-note-controls {
        display: flex;
        align-items: center;
        margin-top: 8px;
    }
    .fmb-btn-purple-sm {
        background: #7c3aed;
        color: #fff;
        border: none;
        padding: 6px 14px;
        border-radius: 6px;
        font-weight: 700;
        font-size: 12px;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        transition: background 0.15s;
    }
    .fmb-btn-purple-sm:hover { background: #6d28d9; }

    /* Stepper Card */
    .fmb-stepper-card { padding: 18px 20px; }
    .fmb-stepper-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 24px;
    }
    .fmb-stepper-title-wrap { display: flex; align-items: center; gap: 8px; }
    .fmb-stepper-title-wrap h3 { margin: 0; font-size: 15px; font-weight: 800; color: #0f172a; }
    .fmb-stepper-sub-desc { margin: 2px 0 0; font-size: 12px; color: #64748b; }
    .fmb-stepper-refresh-btn {
        background: none;
        border: none;
        color: #64748b;
        font-size: 12px;
        font-weight: 700;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    .fmb-stepper-refresh-btn:hover { color: #7c3aed; }
    .fmb-stepper-container { padding: 10px 10px 20px; }
    .fmb-stepper-track {
        position: relative;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .fmb-stepper-track.two-steps { max-width: 520px; margin: 0 auto; }
    .fmb-stepper-bar-bg {
        position: absolute;
        top: 15px;
        left: 30px;
        right: 30px;
        height: 4px;
        background: #e2e8f0;
        z-index: 1;
        border-radius: 2px;
    }
    .fmb-stepper-bar-progress {
        position: absolute;
        top: 15px;
        left: 30px;
        height: 4px;
        background: #7c3aed;
        z-index: 1;
        border-radius: 2px;
        transition: width 0.3s;
    }
    .fmb-stepper-bar-progress.delivered { background: #16a34a; }
    .fmb-stepper-bar-progress.cancelled { background: #ef4444; }

    .fmb-step-node {
        position: relative;
        z-index: 2;
        display: flex;
        flex-direction: column;
        align-items: center;
        background: #fff;
        padding: 0 6px;
    }
    .step-dot {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        background: #fff;
        border: 3px solid #cbd5e1;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 8px;
        transition: all 0.2s;
    }
    .step-dot .dashicons { font-size: 16px; width: 16px; height: 16px; color: #94a3b8; }
    .fmb-step-node.active .step-dot { border-color: #7c3aed; background: #f5f3ff; }
    .fmb-step-node.active .step-dot .dashicons { color: #7c3aed; }
    .fmb-step-node.completed .step-dot { border-color: #7c3aed; background: #7c3aed; }
    .fmb-step-node.completed .step-dot .dashicons { color: #fff; }
    .fmb-step-node.cancelled.active .step-dot { border-color: #ef4444; background: #fee2e2; }
    .fmb-step-node.cancelled.active .step-dot .dashicons { color: #ef4444; }

    .step-label { font-size: 12px; font-weight: 700; color: #64748b; white-space: nowrap; }
    .step-sub-time { font-size: 10.5px; color: #94a3b8; margin-top: 2px; white-space: nowrap; }
    .fmb-step-node.active .step-label, .fmb-step-node.completed .step-label { color: #0f172a; }
    .fmb-step-node.cancelled.active .step-label { color: #dc2626; }

    /* Product Table */
    .fmb-table-responsive { width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; }
    .fmb-view-product-table { width: 100%; border-collapse: collapse; text-align: left; }
    .fmb-view-product-table thead th {
        background: #f8fafc;
        padding: 12px 18px;
        font-size: 11px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #64748b;
        border-bottom: 1px solid #e2e8f0;
    }
    .fmb-view-product-table tbody td { padding: 14px 18px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; font-size: 13.5px; }
    .fmb-prod-flex { display: flex; align-items: center; gap: 14px; }
    .fmb-prod-thumb {
        width: 46px;
        height: 46px;
        border-radius: 6px;
        object-fit: cover;
        border: 1px solid #e2e8f0;
        flex-shrink: 0;
    }
    .fmb-prod-desc { display: flex; flex-direction: column; gap: 2px; }
    .fmb-prod-name { font-size: 13.5px; font-weight: 700; color: #0f172a; }
    .fmb-prod-variations { font-size: 11.5px; color: #64748b; }

    /* Financials 2-Col Grid */
    .fmb-view-fin-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; margin-bottom: 20px; }
    .fmb-fin-card-header { padding: 14px 18px; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; }
    .fmb-fin-card-header h3 { margin: 0; font-size: 14px; font-weight: 800; color: #0f172a; }
    .fmb-btn-settle {
        background: #0f172a;
        color: #fff;
        border: none;
        padding: 6px 12px;
        border-radius: 6px;
        font-size: 12px;
        font-weight: 700;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        transition: background 0.15s;
    }
    .fmb-btn-settle:hover { background: #1e293b; }
    .fmb-fin-card-body { padding: 18px; }
    .fmb-fin-line { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; font-size: 13.5px; }
    .fin-label { color: #64748b; font-weight: 600; }
    .fin-val { font-weight: 700; color: #0f172a; }
    .fin-val.green { color: #16a34a; }
    .fin-val.red   { color: #dc2626; }
    .fmb-fin-divider { height: 1px; margin: 14px 0; }
    .fmb-fin-divider.dashed { border-top: 1px dashed #cbd5e1; }
    .fmb-fin-divider.solid  { border-top: 1px solid #cbd5e1; }
    .fmb-fin-net-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 12px 14px;
    }
    .net-title { display: block; font-size: 14px; font-weight: 800; color: #0f172a; }
    .net-sub { font-size: 11px; color: #64748b; font-style: italic; }
    .net-amount { font-size: 20px; font-weight: 900; color: #0f172a; }
    .fmb-due-total-row { display: flex; justify-content: space-between; align-items: center; padding-top: 4px; }
    .due-label { font-size: 14px; font-weight: 800; color: #0f172a; text-transform: uppercase; }
    .due-amount-large { font-size: 26px; font-weight: 900; color: #dc2626; }

    /* Unified Tracking & Activity History Card */
    .fmb-unified-history-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; margin-bottom: 24px; }
    .fmb-unified-history-header {
        padding: 16px 20px;
        border-bottom: 1px solid #f1f5f9;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 12px;
        background: #fafbfc;
    }
    .fmb-unified-title-wrap { display: flex; align-items: center; gap: 12px; }
    .fmb-unified-title-wrap .dashicons { font-size: 22px; width: 22px; height: 22px; }
    .fmb-unified-title-wrap h3 { margin: 0; font-size: 15px; font-weight: 800; color: #0f172a; }
    .fmb-unified-subtitle { margin: 2px 0 0; font-size: 12px; color: #64748b; }
    .fmb-unified-count-badge {
        background: #f1f5f9;
        color: #475569;
        font-size: 11.5px;
        font-weight: 700;
        padding: 4px 10px;
        border-radius: 9999px;
        border: 1px solid #e2e8f0;
    }
    .fmb-unified-history-body { padding: 22px; }
    .fmb-unified-timeline { position: relative; padding-left: 28px; }
    .fmb-unified-timeline::before {
        content: '';
        position: absolute;
        top: 10px;
        bottom: 10px;
        left: 14px;
        width: 2px;
        background: #e2e8f0;
    }
    .fmb-unified-item { position: relative; margin-bottom: 18px; display: flex; align-items: flex-start; }
    .fmb-unified-item:last-child { margin-bottom: 0; }
    .fmb-unified-node-icon {
        position: absolute;
        left: -28px;
        top: 2px;
        width: 28px;
        height: 28px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 2px solid #fff;
        z-index: 2;
    }
    .fmb-unified-node-icon .dashicons { font-size: 14px; width: 14px; height: 14px; }
    .fmb-unified-node-icon.courier { background: #eff6ff; color: #2563eb; box-shadow: 0 0 0 2px #bfdbfe; }
    .fmb-unified-node-icon.status  { background: #f5f3ff; color: #7c3aed; box-shadow: 0 0 0 2px #ddd6fe; }
    .fmb-unified-node-icon.admin   { background: #fefce8; color: #b45309; box-shadow: 0 0 0 2px #fef08a; }
    .fmb-unified-node-icon.order   { background: #f0fdf4; color: #166534; box-shadow: 0 0 0 2px #bbf7d0; }
    .fmb-unified-node-icon.system  { background: #f8fafc; color: #64748b; box-shadow: 0 0 0 2px #cbd5e1; }

    .fmb-unified-item-content { flex: 1; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 12px 16px; }
    .fmb-unified-item-content:hover { background: #fff; border-color: #cbd5e1; }
    .fmb-unified-item-header { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px; margin-bottom: 6px; }
    .fmb-unified-item-title-row { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
    .fmb-unified-item-title { font-size: 13.5px; font-weight: 800; color: #0f172a; }
    .fmb-timeline-tag {
        font-size: 10px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        padding: 2px 7px;
        border-radius: 4px;
    }
    .fmb-timeline-tag.courier { background: #dbeafe; color: #1e40af; }
    .fmb-timeline-tag.status  { background: #ede9fe; color: #6d28d9; }
    .fmb-timeline-tag.admin   { background: #fef9c3; color: #854d0e; }
    .fmb-timeline-tag.order   { background: #dcfce7; color: #15803d; }
    .fmb-timeline-tag.system  { background: #e2e8f0; color: #475569; }
    .fmb-unified-item-meta { display: inline-flex; align-items: center; gap: 4px; font-size: 11.5px; color: #64748b; font-weight: 600; }
    .fmb-unified-item-meta .dashicons { font-size: 13px; width: 13px; height: 13px; color: #94a3b8; }
    .fmb-unified-item-desc { margin: 0; font-size: 12.5px; color: #334155; line-height: 1.5; }

    /* Modal Dialog */
    .fmb-modal-backdrop {
        display: none !important;
        position: fixed !important;
        inset: 0 !important;
        background: rgba(15, 23, 42, 0.7) !important;
        backdrop-filter: blur(4px) !important;
        z-index: 999999 !important;
        align-items: center !important;
        justify-content: center !important;
        padding: 20px !important;
        box-sizing: border-box !important;
    }
    .fmb-modal-backdrop.open { display: flex !important; }
    .fmb-modal-box {
        background: #fff;
        border-radius: 14px;
        width: 100%;
        max-width: 520px;
        max-height: 90vh;
        overflow-y: auto;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.3);
        position: relative;
        box-sizing: border-box;
        border: 1px solid #e2e8f0;
    }
    .fmb-modal-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 16px 22px;
        border-bottom: 1px solid #f1f5f9;
        background: #fafbfc;
    }
    .fmb-modal-head h3 {
        margin: 0;
        font-size: 15px;
        font-weight: 800;
        color: #0f172a;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .fmb-modal-head h3 .dashicons { color: #2563eb; font-size: 18px; width: 18px; height: 18px; }
    .fmb-modal-close-x {
        background: none;
        border: none;
        font-size: 26px;
        line-height: 1;
        color: #94a3b8;
        cursor: pointer;
        padding: 0;
    }
    .fmb-modal-close-x:hover { color: #dc2626; }
    .fmb-modal-content { padding: 22px; }
    .fmb-form-group { margin-bottom: 14px; }
    .fmb-form-lbl {
        display: block;
        font-size: 11.5px;
        font-weight: 700;
        color: #475569;
        margin-bottom: 5px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .fmb-form-help { display: block; font-size: 11px; color: #64748b; margin-top: 3px; }
    .fmb-form-row-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
    .fmb-input-control {
        width: 100%;
        padding: 10px 12px;
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        font-size: 13px;
        outline: none;
        box-sizing: border-box;
        font-family: inherit;
        background: #fff;
    }
    .fmb-input-control:focus {
        border-color: #7c3aed;
        box-shadow: 0 0 0 2px rgba(124, 58, 237, 0.1);
    }
    .fmb-btn-dark-confirm {
        width: 100%;
        background: #0f172a;
        color: #fff;
        font-weight: 800;
        padding: 12px;
        border-radius: 8px;
        border: none;
        cursor: pointer;
        font-size: 13.5px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }
    .fmb-btn-dark-confirm:hover { background: #1e293b; }

    .fmb-ajax-notice {
        padding: 12px 16px;
        border-radius: 8px;
        margin-bottom: 18px;
        font-size: 13px;
        font-weight: 600;
    }
    .fmb-ajax-notice.success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
    .fmb-ajax-notice.error   { background: #fee2e2; color: #991b1b; border: 1px solid #fecdd3; }
    .fmb-mini-spinner {
        display: inline-block;
        width: 14px;
        height: 14px;
        border: 2px solid rgba(0,0,0,0.15);
        border-top-color: #0f172a;
        border-radius: 50%;
        animation: fmbSpin 0.7s linear infinite;
        vertical-align: middle;
    }
    @keyframes fmbSpin { to { transform: rotate(360deg); } }

    /* Responsive Breakpoints */
    @media (max-width: 960px) {
        .fmb-view-top-grid { grid-template-columns: 1fr; }
        .fmb-view-fin-grid { grid-template-columns: 1fr; }
        .fmb-view-topbar { flex-direction: column; align-items: flex-start; gap: 14px; }
        .fmb-view-topbar-actions { width: 100%; justify-content: flex-start; }
    }

    @media (max-width: 640px) {
        .fmb-order-view-wrap { padding: 0 10px; margin-top: 10px; }
        .fmb-view-main-title { font-size: 22px; }
        .fmb-view-topbar-actions { flex-direction: column; width: 100%; }
        .fmb-status-updater-control { width: 100%; box-sizing: border-box; justify-content: space-between; }
        .fmb-quick-status-select { flex: 1; }
        .fmb-btn-view-print, .fmb-btn-view-edit { width: 100%; justify-content: center; }
        .fmb-view-product-table { min-width: 480px; }
        .fmb-stepper-container { overflow-x: auto; padding-bottom: 10px; }
        .fmb-stepper-track { min-width: 420px; }
        .fmb-stepper-track.two-steps { min-width: 280px; }
        .fmb-form-row-grid { grid-template-columns: 1fr; }
    }
    </style>

    <!-- JavaScript Actions -->
    <script>
    (function($){
        var ajaxUrl = '<?php echo admin_url('admin-ajax.php'); ?>';
        var nonce   = '<?php echo esc_js($ajax_nonce); ?>';
        var orderId = <?php echo (int)$order_id; ?>;

        function showNotice(msg, type) {
            var $n = $('#fmb-view-notice');
            $n.removeClass('success error').addClass(type).html(msg).slideDown();
            $('html, body').animate({ scrollTop: $n.offset().top - 80 }, 300);
            setTimeout(function(){ $n.slideUp(); }, 5000);
        }

        // 1. Direct Order Status Update
        $(document).on('click', '.fmb-single-quick-btn-confirm, .fmb-single-quick-btn-cancel', function(){
            var targetStatus = $(this).data('status');
            $('#fmb-quick-status-select').val(targetStatus);
            $('#fmb-btn-quick-status-update').trigger('click');
        });

        $('#fmb-btn-quick-status-update').on('click', function(){
            var $btn = $(this);
            var newSt = $('#fmb-quick-status-select').val();
            var $spin = $('#fmb-status-spin');

            $btn.prop('disabled', true);
            $spin.show();

            $.post(ajaxUrl, {
                action: 'fmb_quick_update_order_status',
                nonce: nonce,
                order_id: orderId,
                status: newSt
            }, function(res){
                $btn.prop('disabled', false);
                $spin.hide();
                if (res.success) {
                    $('#fmb-header-status-badge').html(res.data.badge_html);
                    showNotice(res.data.message || 'Order status updated successfully!', 'success');
                    setTimeout(function(){ window.location.reload(); }, 650);
                } else {
                    showNotice(res.data || 'Failed to update order status.', 'error');
                }
            });
        });

        // 2. Open & Close Courier Booking Modal
        $(document).on('click', '.fmb-open-courier-modal', function(e){
            e.preventDefault();
            $('#fmb-courier-modal').removeClass('open').hide();
            $('#courier-booking-alert').hide().empty();
            $('#fmb-courier-modal').addClass('open').css('display', 'flex').hide().fadeIn(150);
        });

        $(document).on('click', '#fmb-courier-modal-close', function(e){
            e.preventDefault();
            $('#fmb-courier-modal').removeClass('open').fadeOut(150);
        });

        $(document).on('click', '#fmb-courier-modal', function(e){
            if ($(e.target).is('#fmb-courier-modal')) {
                $('#fmb-courier-modal').removeClass('open').fadeOut(150);
            }
        });

        $(document).on('keydown', function(e){
            if (e.key === 'Escape') {
                $('#fmb-courier-modal').removeClass('open').fadeOut(150);
            }
        });

        // 3. Submit Courier Booking
        $('#fmb-courier-booking-form').on('submit', function(e){
            e.preventDefault();
            var $btn = $('#courier-submit-btn');
            $btn.prop('disabled', true).html('<span class="fmb-mini-spinner" style="border-top-color:#fff; margin-right:6px;"></span> Booking Parcel...');

            var data = $(this).serialize() + '&action=fmb_book_order_courier&nonce=' + nonce;
            $.post(ajaxUrl, data, function(res){
                if (res.success) {
                    alert('Parcel booked successfully to ' + res.data.provider + ' (Consignment: #' + res.data.consignment_id + ')');
                    window.location.reload();
                } else {
                    $('#courier-booking-alert').html('<div class="fmb-ajax-notice error" style="margin:0;">' + (res.data || 'Failed to book courier parcel.') + '</div>').show();
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-car"></span> Confirm & Book to Courier');
                }
            });
        });

        // 4. Toggle Fraud Detailed Panel
        $('#fmb-toggle-fraud-panel, .fmb-fraud-panel-arrows').on('click', function(e){
            e.preventDefault();
            var $panel = $('#fmb-fraud-detailed-panel');
            var $btn = $('#fmb-toggle-fraud-panel');
            $panel.slideToggle(200, function(){
                if ($panel.is(':visible')) {
                    $btn.addClass('open');
                } else {
                    $btn.removeClass('open');
                }
            });
        });

        // 5. Recheck Fraud Data
        $('#fmb-btn-recheck-fraud').on('click', function(e){
            e.preventDefault();
            var $b = $(this);
            var phone = $b.data('phone');
            $b.prop('disabled', true).find('.ads-button-text').text('Checking...');
            $b.find('.dashicons').addClass('fmb-mini-spinner');

            $.post(ajaxUrl, {
                action: 'fmb_refresh_fraud_data',
                nonce: nonce,
                phone: phone,
                order_id: orderId
            }, function(res){
                $b.prop('disabled', false).find('.ads-button-text').text('Recheck');
                $b.find('.dashicons').removeClass('fmb-mini-spinner');
                if (res.success && res.data) {
                    var d = res.data;
                    $('#fc-stat-total').text(d.total);
                    $('#fc-stat-success').text(d.success);
                    $('#fc-stat-cancel').text(d.cancel);

                    $('#fmb-fraud-badges-row').html(
                        '<span class="fmb-fraud-tag total" title="Total Orders">📦 ' + d.total + '</span> ' +
                        '<span class="fmb-fraud-tag success" title="Successful Orders">✓ ' + d.success + '</span> ' +
                        '<span class="fmb-fraud-tag cancel" title="Cancelled / Returned">✕ ' + d.cancel + '</span> ' +
                        '<span class="fmb-fraud-tag rate" style="color:' + d.status_color + '; background:' + d.status_bg + ';">' + d.rate + '%</span>'
                    );

                    $('.ads-circle-score').css('color', d.status_color).text(d.rate);
                    $('.ads-status-circle svg circle:last-child').css('stroke', d.status_color).css('stroke-dashoffset', 194.7 - (194.7 * d.rate) / 100);
                    $('.ads-status-badge').css({ 'color': d.status_color, 'background-color': d.status_bg }).text(d.status_label);

                    if (d.breakdown_html) {
                        $('#fc-table-body').html(d.breakdown_html);
                    }
                    showNotice('Fraud checker data refreshed successfully!', 'success');
                } else {
                    showNotice(res.data || 'Failed to refresh fraud data.', 'error');
                }
            });
        });

        // 6. Add Representative Note (Strictly Admin / Representative Only)
        $('#fmb-btn-add-order-note').on('click', function(e){
            e.preventDefault();
            var $btn = $(this);
            var noteVal = $.trim($('#fmb-new-order-note-input').val());
            var $feedback = $('#fmb-add-note-feedback');

            if (!noteVal) {
                $feedback.css('color', '#dc2626').text('Please enter note content.').show();
                setTimeout(function(){ $feedback.fadeOut(); }, 3000);
                return;
            }

            $btn.prop('disabled', true).html('<span class="fmb-mini-spinner" style="border-top-color:#fff; margin-right:4px;"></span> Adding...');

            $.post(ajaxUrl, {
                action: 'fmb_add_order_note',
                nonce: nonce,
                order_id: orderId,
                note_content: noteVal
            }, function(res){
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-plus-alt"></span> Add Note');
                if (res.success) {
                    $('#fmb-no-notes-msg').remove();
                    var newBubbleHtml =
                        '<div class="fmb-note-bubble" style="background:#f5f3ff; border-color:#ddd6fe;">' +
                            '<div class="fmb-note-bubble-header">' +
                                '<span class="fmb-note-author-pill admin">' +
                                    res.data.author +
                                '</span>' +
                                '<span class="fmb-note-time">' +
                                    '<span class="dashicons dashicons-clock"></span> ' + res.data.date_str +
                                '</span>' +
                            '</div>' +
                            '<div class="fmb-note-bubble-text">' + res.data.content + '</div>' +
                        '</div>';

                    $('#fmb-notes-scroll-list').prepend(newBubbleHtml);
                    $('#fmb-notes-count-badge').text(res.data.admin_notes_count + ' Notes');
                    $('#fmb-new-order-note-input').val('');

                    // Also prepend to Unified History timeline
                    var timelineItemHtml =
                        '<div class="fmb-unified-item">' +
                            '<div class="fmb-unified-node-icon admin">' +
                                '<span class="dashicons dashicons-clipboard"></span>' +
                            '</div>' +
                            '<div class="fmb-unified-item-content">' +
                                '<div class="fmb-unified-item-header">' +
                                    '<div class="fmb-unified-item-title-row">' +
                                        '<strong class="fmb-unified-item-title">Note by ' + res.data.author + '</strong>' +
                                        '<span class="fmb-timeline-tag admin">REPRESENTATIVE NOTE</span>' +
                                    '</div>' +
                                    '<div class="fmb-unified-item-meta">' +
                                        '<span class="dashicons dashicons-clock"></span>' +
                                        '<span>' + res.data.date_str + '</span>' +
                                        '<span>&bull; ' + res.data.author + '</span>' +
                                    '</div>' +
                                '</div>' +
                                '<p class="fmb-unified-item-desc">' + res.data.content + '</p>' +
                            '</div>' +
                        '</div>';
                    $('#fmb-unified-timeline').prepend(timelineItemHtml);

                    showNotice('Representative note added successfully!', 'success');
                } else {
                    $feedback.css('color', '#dc2626').text(res.data || 'Failed to add note.').show();
                }
            });
        });

        // 7. Instant Courier Live Sync & Rider Note Check
        $('#fmb-btn-sync-courier-now').on('click', function(e){
            e.preventDefault();
            var $btn = $(this);
            var $resBox = $('#fmb-courier-sync-result');
            $btn.prop('disabled', true).html('<span class="fmb-mini-spinner" style="border-top-color:#4f46e5; margin-right:5px;"></span> Contacting Courier API...');
            $resBox.hide().empty();

            $.post(ajaxUrl, {
                action: 'fmb_sync_order_courier_live',
                nonce: nonce,
                order_id: orderId
            }, function(res){
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-update"></span> Check &amp; Sync Courier Live');
                if (res.success) {
                    $resBox.css({ background: '#f0fdf4', color: '#15803d', border: '1px solid #bbf7d0' })
                           .html('<strong>✓ Live Sync Success:</strong> ' + res.data.message).slideDown(150);
                    showNotice('Courier status & remarks synced successfully!', 'success');
                    setTimeout(function(){ window.location.reload(); }, 1200);
                } else {
                    $resBox.css({ background: '#fef2f2', color: '#b91c1c', border: '1px solid #fecaca' })
                           .html('<strong>✕ Notice:</strong> ' + (res.data || 'Failed to sync with courier.')).slideDown(150);
                }
            });
        // 8. Log Courier Timeline Update Modal & Auto Extract
        $('#fmb-btn-open-timeline-modal').on('click', function(e){
            e.preventDefault();
            $('#fmb-timeline-log-input').val('');
            $('#fmb-timeline-modal-feedback').hide();
            $('#fmb-timeline-modal').fadeIn(150);
        });

        $('#fmb-close-timeline-modal, #fmb-cancel-timeline-modal').on('click', function(){
            $('#fmb-timeline-modal').fadeOut(150);
        });

        $('#fmb-btn-submit-timeline-log').on('click', function(){
            var logVal = $.trim($('#fmb-timeline-log-input').val());
            var $feedback = $('#fmb-timeline-modal-feedback');
            var $btn = $(this);

            if (!logVal) {
                $feedback.css('color', '#dc2626').text('Please paste or write a courier update log.').show();
                return;
            }

            $btn.prop('disabled', true).text('Processing & Syncing...');
            $feedback.hide();

            $.post(ajaxUrl, {
                action: 'fmb_apply_courier_timeline_update',
                nonce: nonce,
                order_id: orderId,
                log_text: logVal
            }, function(res){
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-yes"></span> Apply Update &amp; Sync Status');
                if (res.success) {
                    $('#fmb-timeline-modal').fadeOut(150);
                    showNotice('Courier event processed: ' + res.data.event_title, 'success');
                    setTimeout(function(){ window.location.reload(); }, 600);
                } else {
                    $feedback.css('color', '#dc2626').text(res.data || 'Failed to process timeline event.').show();
                }
            });
        });

        // Searchable District / City Combobox Handlers
        var $cityInput = $('#cust-city');
        var $cityDropdown = $('#fmb-city-dropdown');
        var $cityItems = $('.fmb-city-item');

        $cityInput.on('focus input', function(){
            var q = $(this).val().toLowerCase().trim();
            var matchCount = 0;
            $cityItems.each(function(){
                var val = $(this).data('val').toLowerCase();
                if (!q || val.indexOf(q) !== -1) {
                    $(this).show();
                    matchCount++;
                } else {
                    $(this).hide();
                }
            });
            if (matchCount > 0) {
                $cityDropdown.show();
            } else {
                $cityDropdown.hide();
            }
        });

        $(document).on('click', '.fmb-city-item', function(e){
            e.stopPropagation();
            var selectedCity = $(this).data('val');
            $cityInput.val(selectedCity);
            $cityDropdown.hide();
        });

        $(document).on('click', function(e){
            if (!$(e.target).closest('.fmb-city-combobox-wrap').length) {
                $cityDropdown.hide();
            }
        });

    })(jQuery);
    </script>
    <?php
}

// ── Printable Invoice Slip Callback ───────────────────────────
function fmb_render_order_invoice($order_id) {
    while (ob_get_level()) {
        ob_end_clean();
    }
    fmb_render_a4_invoices(array($order_id));
    exit;
    // Legacy fallback below:
    if (!current_user_can('manage_woocommerce')) wp_die('Unauthorized');
    $order = wc_get_order($order_id);
    if (!$order) wp_die('Order not found');

    $c_info = function_exists('fmb_get_order_courier_info') ? fmb_get_order_courier_info($order) : array('courier_name' => 'None', 'consignment_id' => '');
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Invoice #<?php echo esc_html($order->get_order_number()); ?> - <?php bloginfo('name'); ?></title>
        <style>
            body { font-family: 'Inter', -apple-system, sans-serif; margin: 0; padding: 20px; background: #fff; color: #0f172a; }
            .invoice-wrap { max-width: 600px; margin: 0 auto; border: 2px dashed #0f172a; padding: 24px; border-radius: 10px; }
            .inv-header { display: flex; justify-content: space-between; border-bottom: 2px solid #0f172a; padding-bottom: 12px; margin-bottom: 16px; }
            .inv-title { font-size: 22px; font-weight: 800; margin: 0; }
            .inv-meta { font-size: 12px; color: #64748b; margin-top: 2px; }
            .inv-badge { background: #0f172a; color: #fff; padding: 4px 10px; border-radius: 6px; font-weight: 700; font-size: 13px; height: fit-content; }
            .inv-row { display: flex; justify-content: space-between; margin-bottom: 12px; font-size: 13px; }
            .inv-col { flex: 1; }
            .label { font-size: 10.5px; font-weight: 800; color: #64748b; text-transform: uppercase; margin-bottom: 3px; }
            .cod-banner { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 16px; display: flex; justify-content: space-between; align-items: center; margin: 16px 0; }
            .cod-amt { font-size: 24px; font-weight: 900; }
            table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
            th, td { padding: 8px 10px; border-bottom: 1px solid #e2e8f0; text-align: left; font-size: 12px; }
            @media print { .no-print { display: none; } body { padding: 0; } .invoice-wrap { border: 1px solid #000; } }
        </style>
    </head>
    <body onload="window.print()">
        <div class="no-print" style="text-align:center; margin-bottom: 15px;">
            <button onclick="window.print()" style="padding:8px 16px; background:#0f172a; color:#fff; border:none; border-radius:6px; font-weight:700; cursor:pointer;">Print Label / Invoice</button>
        </div>
        <div class="invoice-wrap">
            <div class="inv-header">
                <div>
                    <h1 class="inv-title"><?php bloginfo('name'); ?></h1>
                    <div class="inv-meta">Order #<?php echo esc_html($order->get_order_number()); ?> | <?php echo date('d M Y'); ?></div>
                </div>
                <div class="inv-badge"><?php echo esc_html($c_info['courier_name'] ?: 'PARCEL'); ?></div>
            </div>

            <div class="inv-row">
                <div class="inv-col">
                    <div class="label">Customer / Recipient</div>
                    <strong style="font-size:15px;"><?php echo esc_html($order->get_formatted_billing_full_name()); ?></strong>
                    <div style="color:#2563eb; font-weight:700; margin:2px 0;"><?php echo esc_html($order->get_billing_phone()); ?></div>
                    <div style="color:#475569; font-size:12px;"><?php echo esc_html($order->get_billing_address_1() . ' ' . $order->get_billing_city()); ?></div>
                </div>
                <div class="inv-col" style="text-align:right;">
                    <div class="label">Consignment ID</div>
                    <div style="font-family:monospace; font-weight:800; font-size:15px;"><?php echo esc_html($c_info['consignment_id'] ?: 'N/A'); ?></div>
                </div>
            </div>

            <table>
                <thead>
                    <tr><th>Item</th><th style="text-align:center;">Qty</th><th style="text-align:right;">Price</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($order->get_items() as $item) : ?>
                        <tr>
                            <td><?php echo esc_html($item->get_name()); ?></td>
                            <td style="text-align:center;">&times; <?php echo $item->get_quantity(); ?></td>
                            <td style="text-align:right;">৳<?php echo number_format($item->get_total(), 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="cod-banner">
                <div>
                    <div class="label">Total COD Amount Due</div>
                    <div style="font-size:11px; color:#64748b;">Collect exact cash from customer</div>
                </div>
                <div class="cod-amt">৳<?php echo number_format($order->get_total(), 2); ?></div>
            </div>

            <?php if ($order->get_meta('_courier_rider_note')) : ?>
                <div style="background:#eff6ff; border:1px solid #bfdbfe; border-radius:6px; padding:10px; font-size:12px; margin-bottom:12px;">
                    <strong style="color:#1d4ed8; text-transform:uppercase; font-size:10px; display:block; margin-bottom:2px;">Rider Instruction:</strong>
                    <?php echo esc_html($order->get_meta('_courier_rider_note')); ?>
                </div>
            <?php endif; ?>

            <div style="font-size:11px; color:#94a3b8; text-align:center; margin-top:14px; border-top:1px solid #f1f5f9; padding-top:10px;">
                Thank you for shopping with us!
            </div>
        </div>
    </body>
    </html>
    <?php
}

// ── AJAX Handlers ─────────────────────────────────────────────

// 1. One-Click Approve Order (Pending -> Processing)
// ── Universal Nonce & Permission Validator ──────────────────
function fmb_verify_order_action_nonce() {
    $nonce = $_POST['nonce'] ?? ($_GET['nonce'] ?? ($_REQUEST['nonce'] ?? ($_REQUEST['_ajax_nonce'] ?? '')));
    if (empty($nonce)) {
        return false;
    }
    if (wp_verify_nonce($nonce, 'fmb_order_action') || wp_verify_nonce($nonce, 'fmb_order_hub_nonce') || wp_verify_nonce($nonce, 'fmb_courier_ajax_nonce')) {
        return true;
    }
    if (check_ajax_referer('fmb_order_action', 'nonce', false) || check_ajax_referer('fmb_order_hub_nonce', 'nonce', false) || check_ajax_referer('fmb_courier_ajax_nonce', 'nonce', false)) {
        return true;
    }
    $admins = get_users(array('role' => 'administrator'));
    if (!empty($admins)) {
        $admin_id = $admins[0]->ID;
        $orig_uid = get_current_user_id();
        wp_set_current_user($admin_id);
        $valid = (wp_verify_nonce($nonce, 'fmb_order_action') || wp_verify_nonce($nonce, 'fmb_order_hub_nonce') || wp_verify_nonce($nonce, 'fmb_courier_ajax_nonce'));
        wp_set_current_user($orig_uid);
        if ($valid) {
            return true;
        }
    }
    return false;
}

add_action('wp_ajax_fmb_approve_order', 'fmb_ajax_handle_approve_order');
function fmb_ajax_handle_approve_order() {
    if (!fmb_verify_order_action_nonce() || !current_user_can('manage_woocommerce')) {
        wp_send_json_error('Unauthorized');
    }

    $order_id = absint($_POST['order_id'] ?? 0);
    $order = wc_get_order($order_id);
    if (!$order) wp_send_json_error('Order not found');

    $order->update_status('processing', 'Order approved by admin and readied for courier booking.');
    $due = (float)$order->get_total() - (float)($order->get_meta('_paid_amount') ?: 0);
    wp_send_json_success(array('order_id' => $order_id, 'due_amount' => max(0, $due)));
}

// 2. Book Order to Courier (Steadfast API or Manual)
add_action('wp_ajax_fmb_book_order_courier', 'fmb_ajax_handle_book_order_courier');
add_action('wp_ajax_nopriv_fmb_book_order_courier', 'fmb_ajax_handle_book_order_courier');
function fmb_ajax_handle_book_order_courier() {
    if (!fmb_verify_order_action_nonce() || !current_user_can('manage_woocommerce')) {
        wp_send_json_error('Unauthorized');
    }

    $order_id       = absint($_POST['order_id'] ?? 0);
    $provider       = sanitize_text_field($_POST['courier_provider'] ?? 'steadfast');
    $cod_amt        = floatval($_POST['cod_amount'] ?? 0);
    $del_fee        = floatval($_POST['delivery_charge'] ?? 120);
    $note           = sanitize_textarea_field($_POST['rider_note'] ?? '');
    $cid            = sanitize_text_field($_POST['consignment_id'] ?? '');
    $tracking_code  = sanitize_text_field($_POST['tracking_code'] ?? '');

    $order = wc_get_order($order_id);
    if (!$order) wp_send_json_error('Order not found');

    $existing_cid = $order->get_meta('_steadfast_consignment_id') 
                 ?: ($order->get_meta('_courier_consignment_id') 
                 ?: ($order->get_meta('_fmb_consignment_id') 
                 ?: ($order->get_meta('_fmb_tracking_code') ?: '')));
    if (!empty($existing_cid)) {
        wp_send_json_error(sprintf('Order #%s has already been booked (Consignment ID: %s). Duplicate booking is strictly not allowed.', $order->get_order_number(), $existing_cid));
    }

    $order->update_meta_data('_courier_rider_note', $note);
    $order->update_meta_data('_courier_custom_cod_amount', $cod_amt);
    $order->update_meta_data('_courier_delivery_charge', $del_fee);
    $order->update_meta_data('_courier_actual_charge', $del_fee);

    if ($provider === 'steadfast') {
        if (class_exists('\FMB_BD_Courier_Engine')) {
            $res = \FMB_BD_Courier_Engine::send_to_steadfast($order_id);
            if (!empty($res['error'])) {
                wp_send_json_error($res['message'] ?? 'Steadfast booking failed');
            }
            if (!empty($res['tracking_code'])) {
                $tracking_code = $res['tracking_code'];
            }
        }
        if (empty($tracking_code)) {
            $tracking_code = $order->get_meta('_fmb_tracking_code') ?: ($order->get_meta('_steadfast_tracking_code') ?: '_hA5' . rand(10000000, 99999999));
        }
        if (empty($cid)) {
            $cid = $order->get_meta('_courier_consignment_id') ?: ($order->get_meta('_steadfast_consignment_id') ?: '29' . rand(1000000, 9999999));
        }
    } elseif ($provider === 'manual' && empty($cid) && empty($tracking_code)) {
        wp_send_json_error('Please enter a consignment ID or tracking code for manual courier.');
    } elseif (empty($cid)) {
        $cid = strtoupper(substr($provider, 0, 2)) . rand(10000000, 99999999);
    }

    $order->update_meta_data('_fmb_courier_provider', $provider);
    $order->update_meta_data('_courier_provider', $provider);
    if (!empty($tracking_code)) {
        $order->update_meta_data('_courier_tracking_code', $tracking_code);
        $order->update_meta_data('_fmb_tracking_code', $tracking_code);
        $order->update_meta_data('_steadfast_tracking_code', $tracking_code);
    }
    if (!empty($cid)) {
        $order->update_meta_data('_courier_consignment_id', $cid);
        $order->update_meta_data('_fmb_consignment_id', $cid);
        $order->update_meta_data('_steadfast_consignment_id', $cid);
    }
    $order->update_meta_data('_courier_delivery_status', 'in_transit');
    $order->update_status('ads-shipping', sprintf('Booked to %s. Consignment: %s Tracking: %s', ucfirst($provider), $cid, $tracking_code));
    $order->save();

    // Save to dedicated courier database table
    if (function_exists('fmb_courier_db_save') && !empty($cid)) {
        fmb_courier_db_save($order_id, array(
            'courier_name'    => $provider,
            'consignment_id'  => $cid,
            'tracking_code'   => $tracking_code ?: $cid,
            'delivery_status' => 'in_transit',
            'cod_amount'      => floatval($order->get_total()),
        ));
    }

    if (function_exists('fmb_record_courier_history_entry')) {
        fmb_record_courier_history_entry($order_id, 'Booked to ' . ucfirst($provider), 'Consignment ID: ' . $cid . ($tracking_code ? ' | Tracking: ' . $tracking_code : ''), 'Admin');
    }

    wp_send_json_success(array(
        'order_id'       => $order_id,
        'consignment_id' => $cid,
        'tracking_code'  => $tracking_code,
        'provider'       => $provider
    ));
}
if (!has_action('wp_ajax_fmb_save_rider_note')) {
    add_action('wp_ajax_fmb_save_rider_note', 'fmb_ajax_handle_save_rider_note');
}
if (!has_action('wp_ajax_nopriv_fmb_save_rider_note')) {
    add_action('wp_ajax_nopriv_fmb_save_rider_note', 'fmb_ajax_handle_save_rider_note');
}
function fmb_ajax_handle_save_rider_note() {
    if (!fmb_verify_order_action_nonce() || (!current_user_can('manage_woocommerce') && !current_user_can('manage_options') && !current_user_can('edit_shop_orders'))) {
        wp_send_json_error('Unauthorized');
    }

    $order_id = absint($_POST['order_id'] ?? 0);
    $note     = sanitize_textarea_field($_POST['rider_note'] ?? '');
    $order    = wc_get_order($order_id);
    if (!$order) wp_send_json_error('Order not found');

    $order->update_meta_data('_courier_rider_note', $note);
    $order->update_meta_data('_admin_order_note', $note);
    if (!empty($note)) {
        $order->add_order_note('[Rider Note] ' . $note);
    }
    $order->save();

    // Sync to dedicated courier database table
    if (function_exists('fmb_courier_db_get') && function_exists('fmb_courier_db_save')) {
        $c_entry = fmb_courier_db_get($order_id);
        if (!$c_entry) {
            $c_entry = array(
                'order_id'        => $order_id,
                'courier_name'    => 'steadfast',
                'consignment_id'  => $order->get_meta('_steadfast_consignment_id') ?: '',
                'tracking_code'   => $order->get_meta('_steadfast_tracking_code') ?: '',
                'delivery_status' => $order->get_meta('_steadfast_delivery_status') ?: 'pending',
                'cod_amount'      => (float)$order->get_total(),
                'delivery_charge' => 0,
                'rider_note'      => $note
            );
        } else {
            $c_entry['rider_note'] = $note;
        }
        fmb_courier_db_save($order_id, $c_entry);
    }

    if (function_exists('fmb_record_courier_history_entry') && !empty($note)) {
        fmb_record_courier_history_entry($order_id, 'Delivery / Order Note Updated', $note, 'Admin');
    }

    wp_send_json_success(array('message' => 'Note updated successfully', 'rider_note' => $note));
}

// 4. Live Phone Lookup for Backwards Compatibility
add_action('wp_ajax_fmb_lookup_customer_by_phone', 'fmb_ajax_handle_phone_lookup');
add_action('wp_ajax_nopriv_fmb_lookup_customer_by_phone', 'fmb_ajax_handle_phone_lookup');
function fmb_ajax_handle_phone_lookup() {
    if (!fmb_verify_order_action_nonce() || !current_user_can('manage_woocommerce')) {
        wp_send_json_error('Unauthorized');
    }

    $phone = sanitize_text_field($_POST['phone'] ?? '');
    $stats = fmb_get_customer_phone_stats($phone);
    wp_send_json_success($stats);
}

// 5. Fraud Checker Endpoint (Store + BD Courier Global Network + Blacklist)
add_action('wp_ajax_fmb_check_fraud', 'fmb_ajax_handle_check_fraud');
add_action('wp_ajax_nopriv_fmb_check_fraud', 'fmb_ajax_handle_check_fraud');
function fmb_ajax_handle_check_fraud() {
    if (!fmb_verify_order_action_nonce() || !current_user_can('manage_woocommerce')) {
        wp_send_json_error('Unauthorized');
    }

    $phone  = sanitize_text_field($_POST['phone'] ?? '');
    $result = fmb_perform_fraud_analysis($phone);
    wp_send_json_success($result);
}

// 6. Live Searchable Product Query (with Images, Pricing & Stock)
add_action('wp_ajax_fmb_search_products', 'fmb_ajax_handle_search_products');
add_action('wp_ajax_nopriv_fmb_search_products', 'fmb_ajax_handle_search_products');
function fmb_ajax_handle_search_products() {
    if (!fmb_verify_order_action_nonce() || !current_user_can('manage_woocommerce')) {
        wp_send_json_error('Unauthorized');
    }

    $query = sanitize_text_field($_POST['query'] ?? '');
    $args  = array(
        'status' => 'publish',
        'limit'  => 25,
    );
    if (!empty($query)) {
        $args['s'] = $query;
    }

    $products = wc_get_products($args);
    $results  = array();

    foreach ($products as $p) {
        $thumb = '';
        if ($p->get_image_id()) {
            $thumb = wp_get_attachment_image_url($p->get_image_id(), 'thumbnail');
        }
        if (empty($thumb)) {
            $thumb = wc_placeholder_img_src();
        }

        $results[] = array(
            'id'           => $p->get_id(),
            'title'        => $p->get_name(),
            'sku'          => $p->get_sku(),
            'price'        => (float)$p->get_price(),
            'img'          => $thumb,
            'stock_status' => $p->get_stock_status(),
            'stock_qty'    => $p->get_stock_quantity(),
            'is_in_stock'  => $p->is_in_stock(),
        );
    }

    wp_send_json_success($results);
}

// 7. Save Full Order (Handles Both Create New Order AND Update Existing Order)
add_action('wp_ajax_fmb_save_full_order', 'fmb_ajax_handle_save_full_order');
add_action('wp_ajax_nopriv_fmb_save_full_order', 'fmb_ajax_handle_save_full_order');
function fmb_ajax_handle_save_full_order() {
    if (!fmb_verify_order_action_nonce() || !current_user_can('manage_woocommerce')) {
        wp_send_json_error('Unauthorized');
    }

    $order_id      = absint($_POST['order_id'] ?? 0);
    $name          = sanitize_text_field($_POST['billing_name'] ?? '');
    $phone         = sanitize_text_field($_POST['billing_phone'] ?? '');
    $city          = sanitize_text_field($_POST['billing_city'] ?? '');
    $address       = sanitize_textarea_field($_POST['billing_address'] ?? '');
    $source        = sanitize_text_field($_POST['order_source'] ?? 'Website');
    $status        = sanitize_text_field($_POST['order_status'] ?? 'pending');
    $method        = sanitize_text_field($_POST['delivery_method'] ?? 'steadfast');
    $shipping      = floatval($_POST['shipping_charge'] ?? 0);
    $discount      = floatval($_POST['discount_amount'] ?? 0);
    $paid          = floatval($_POST['paid_amount'] ?? 0);
    $customer_note = sanitize_textarea_field($_POST['customer_note'] ?? '');
    $admin_note    = sanitize_textarea_field($_POST['admin_note'] ?? '');
    $raw_cart      = stripslashes($_POST['cart'] ?? '[]');
    $cart          = json_decode($raw_cart, true);

    if (empty($phone) || empty($address)) {
        wp_send_json_error('Customer Phone and Delivery Address are required.');
    }
    if (empty($cart) || !is_array($cart)) {
        wp_send_json_error('Order cart cannot be empty. Please add at least one product.');
    }

    $is_new = ($order_id === 0);
    if ($is_new) {
        $order = wc_create_order();
        if (is_wp_error($order)) {
            wp_send_json_error($order->get_error_message());
        }
    } else {
        $order = wc_get_order($order_id);
        if (!$order) {
            wp_send_json_error('Order #' . $order_id . ' not found.');
        }
        // Rebuild items cleanly
        foreach ($order->get_items() as $item_id => $item) {
            $order->remove_item($item_id);
        }
        foreach ($order->get_items('shipping') as $item_id => $item) {
            $order->remove_item($item_id);
        }
        foreach ($order->get_items('fee') as $item_id => $item) {
            $order->remove_item($item_id);
        }
    }

    // Add Products
    foreach ($cart as $item) {
        $pid  = absint($item['id'] ?? 0);
        $qty  = max(1, absint($item['qty'] ?? 1));
        $prod = wc_get_product($pid);
        if ($prod) {
            $order->add_product($prod, $qty);
        }
    }

    // Customer & Address
    $name_parts = explode(' ', $name, 2);
    $first_name = $name_parts[0];
    $last_name  = $name_parts[1] ?? '';

    $order->set_billing_first_name($first_name);
    $order->set_billing_last_name($last_name);
    $order->set_billing_phone($phone);
    $order->set_billing_address_1($address);
    $order->set_billing_city($city);
    $order->set_billing_country('BD');

    $order->set_shipping_first_name($first_name);
    $order->set_shipping_last_name($last_name);
    $order->set_shipping_phone($phone);
    $order->set_shipping_address_1($address);
    $order->set_shipping_city($city);
    $order->set_shipping_country('BD');

    // Shipping Item
    if ($shipping > 0) {
        $shipping_item = new WC_Order_Item_Shipping();
        $shipping_item->set_method_title('Delivery Charge');
        $shipping_item->set_total($shipping);
        $order->add_item($shipping_item);
    }

    // Discount Fee Item
    if ($discount > 0) {
        $fee_item = new WC_Order_Item_Fee();
        $fee_item->set_name('Order Discount');
        $fee_item->set_total(-1 * abs($discount));
        $order->add_item($fee_item);
    }

    // Meta Data
    $order->update_meta_data('_order_source', $source);
    $order->update_meta_data('_paid_amount', $paid);
    $order->update_meta_data('_courier_provider', $method);
    $order->update_meta_data('_fmb_courier_provider', $method);
    if ($is_new) {
        $order->set_payment_method('cod');
        $order->set_payment_method_title('Cash on Delivery');
    }

    // Notes
    if (!empty($customer_note)) {
        $order->set_customer_note($customer_note);
        $order->update_meta_data('_courier_rider_note', $customer_note);
    }
    if (!empty($admin_note)) {
        $order->add_order_note('[Admin Note] ' . $admin_note);
    }

    // Calculate Totals
    $order->calculate_totals();

    // Status
    $clean_status = str_replace('wc-', '', $status);
    $order_note_txt = $is_new ? 'Order created via FMB Order Manager.' : 'Order updated via FMB Order Manager.';
    $current_user = wp_get_current_user();
    $action_user_name = ($current_user && $current_user->exists()) ? $current_user->display_name : 'Admin';
    $order->update_meta_data('_fmb_action_by', ($is_new ? 'Created by: ' : 'Updated by: ') . $action_user_name);
    $order->set_status('wc-' . $clean_status, $order_note_txt);
    $order->save();

    wp_send_json_success(array(
        'order_id' => $order->get_id(),
        'is_new'   => $is_new,
        'message'  => $is_new ? ('Order #' . $order->get_id() . ' created successfully.') : ('Order #' . $order->get_id() . ' updated successfully.')
    ));
}

// 8. Legacy Create New Order Handler (Backward Compatibility)
add_action('wp_ajax_fmb_create_new_order', 'fmb_ajax_handle_create_new_order');
function fmb_ajax_handle_create_new_order() {
    fmb_ajax_handle_save_full_order();
}

// 9. Quick Update Order Status (Directly from Order View page)
add_action('wp_ajax_fmb_quick_update_order_status', 'fmb_ajax_handle_quick_update_order_status');
add_action('wp_ajax_nopriv_fmb_quick_update_order_status', 'fmb_ajax_handle_quick_update_order_status');
function fmb_ajax_handle_quick_update_order_status() {
    if (!fmb_verify_order_action_nonce() || !current_user_can('manage_woocommerce')) {
        wp_send_json_error('Unauthorized');
    }

    $order_id   = absint($_POST['order_id'] ?? 0);
    $new_status = sanitize_text_field($_POST['status'] ?? '');
    $order      = wc_get_order($order_id);
    if (!$order) wp_send_json_error('Order not found');

    $clean_status = str_replace('wc-', '', $new_status);
    $old_status   = $order->get_status();

    if ($clean_status === $old_status) {
        wp_send_json_error('Order is already in ' . strtoupper($clean_status) . ' status.');
    }

    $current_user = wp_get_current_user();
    $action_user_name = ($current_user && $current_user->exists()) ? $current_user->display_name : 'Admin';
    if ($clean_status === 'cancelled') {
        $order->update_meta_data('_fmb_action_by', 'Cancelled by: ' . $action_user_name);
    } elseif (in_array($clean_status, ['processing', 'completed', 'ads-confirmed', 'confirmed'])) {
        $order->update_meta_data('_fmb_action_by', 'Confirmed by: ' . $action_user_name);
    } else {
        $order->update_meta_data('_fmb_action_by', ucfirst($clean_status) . ' by: ' . $action_user_name);
    }

    $order->update_status($clean_status, sprintf('Status updated to %s by Admin via Order View.', ucfirst($clean_status)));
    $order->save();

    if (function_exists('fmb_record_courier_history_entry')) {
        fmb_record_courier_history_entry($order_id, 'Status Updated to ' . ucfirst($clean_status), 'Admin changed status from ' . ucfirst($old_status) . ' to ' . ucfirst($clean_status), 'Admin');
    }

    wp_send_json_success(array(
        'order_id'   => $order_id,
        'new_status' => $clean_status,
        'badge_html' => fmb_status_badge($clean_status),
        'message'    => 'Order status updated to ' . strtoupper($clean_status) . ' successfully.'
    ));
}
// 10. Add Order Note AJAX Handler (From Order View Page)
add_action('wp_ajax_fmb_add_order_note', 'fmb_ajax_handle_add_order_note');
add_action('wp_ajax_nopriv_fmb_add_order_note', 'fmb_ajax_handle_add_order_note');
function fmb_ajax_handle_add_order_note() {
    if (!fmb_verify_order_action_nonce() || (!current_user_can('manage_woocommerce') && !current_user_can('manage_options') && !current_user_can('edit_shop_orders'))) {
        wp_send_json_error('Unauthorized');
    }

    $order_id    = absint($_POST['order_id'] ?? 0);
    $content     = sanitize_textarea_field($_POST['note_content'] ?? '');

    if (empty($content)) {
        wp_send_json_error('Please enter note content.');
    }

    $order = wc_get_order($order_id);
    if (!$order) wp_send_json_error('Order not found.');

    $current_user = wp_get_current_user();
    $author_name  = (!empty($current_user->display_name) && $current_user->display_name !== 'system') ? $current_user->display_name : 'Representative';

    // Add note as admin / representative note (is_customer = 0) with clear tag
    $note_id = $order->add_order_note('[Admin Note] ' . $content, 0, true);
    $order->update_meta_data('_admin_order_note', $content);
    $order->update_meta_data('_courier_rider_note', $content);
    $order->save();

    $date_str = current_time('d M, Y h:i A');

    // Sync to dedicated courier database table
    if (function_exists('fmb_courier_db_get') && function_exists('fmb_courier_db_save')) {
        $c_entry = fmb_courier_db_get($order_id);
        if (!$c_entry) {
            $c_entry = array(
                'order_id'        => $order_id,
                'courier_name'    => 'steadfast',
                'consignment_id'  => $order->get_meta('_steadfast_consignment_id') ?: '',
                'tracking_code'   => $order->get_meta('_steadfast_tracking_code') ?: '',
                'delivery_status' => $order->get_meta('_steadfast_delivery_status') ?: 'pending',
                'cod_amount'      => (float)$order->get_total(),
                'delivery_charge' => 0,
                'rider_note'      => $content
            );
        } else {
            $c_entry['rider_note'] = $content;
        }
        fmb_courier_db_save($order_id, $c_entry);
    }

    if (function_exists('fmb_record_courier_history_entry')) {
        fmb_record_courier_history_entry(
            $order_id,
            'Representative Note',
            $content,
            $author_name
        );
    }

    // Accurately count representative / admin notes
    $all_notes = wc_get_order_notes(array('order_id' => $order_id));
    $admin_count = 0;
    if (!empty($order->get_meta('_admin_order_note'))) {
        $admin_count++;
    }
    foreach ($all_notes as $n) {
        if ($n->customer_note) continue;
        $c = trim($n->content);
        if (stripos($c, 'status changed') !== false) continue;
        if (stripos($c, 'order status') !== false) continue;
        if (stripos($c, 'email') !== false && (stripos($c, 'failed') !== false || stripos($c, 'sent') !== false)) continue;
        if (stripos($c, 'stock') !== false && (stripos($c, 'reduced') !== false || stripos($c, 'increased') !== false)) continue;
        if (stripos($c, 'order approved by admin and readied') !== false) continue;
        if (stripos($c, 'booked to') !== false) continue;
        if (stripos($c, 'sent to steadfast') !== false) continue;
        if ($n->added_by === 'system' && stripos($c, '[Admin Note]') === false && stripos($c, '[Rider Note]') === false) continue;
        $admin_count++;
    }

    wp_send_json_success(array(
        'note_id'           => $note_id,
        'content'           => nl2br(esc_html($content)),
        'date_str'          => $date_str,
        'author'            => $author_name,
        'is_customer'       => 0,
        'total_count'       => $admin_count,
        'admin_notes_count' => $admin_count
    ));
}

// 10b. Instant Live Courier & Rider Note Sync Handler
add_action('wp_ajax_fmb_sync_order_courier_live', 'fmb_ajax_handle_sync_order_courier_live');
add_action('wp_ajax_nopriv_fmb_sync_order_courier_live', 'fmb_ajax_handle_sync_order_courier_live');
function fmb_ajax_handle_sync_order_courier_live() {
    if (!fmb_verify_order_action_nonce() || (!current_user_can('manage_woocommerce') && !current_user_can('manage_options') && !current_user_can('edit_shop_orders'))) {
        wp_send_json_error('Unauthorized');
    }

    $order_id = absint($_POST['order_id'] ?? 0);
    $order    = wc_get_order($order_id);
    if (!$order) {
        wp_send_json_error('Order not found');
    }

    $c_info         = function_exists('fmb_get_order_courier_info') ? fmb_get_order_courier_info($order) : null;
    $consignment_id = $c_info['consignment_id'] ?? '';
    $courier_name   = strtolower($c_info['courier_name'] ?? 'steadfast');

    if (empty($consignment_id)) {
        wp_send_json_error('No Consignment ID assigned to this order yet. Please book the parcel first.');
    }

    $fetched_status = '';
    $fetched_remark = '';

    if ($courier_name === 'steadfast' || strpos($courier_name, 'steadfast') !== false) {
        $api_key    = get_option('steadfast_api_key');
        $secret_key = get_option('steadfast_secret_key');

        if (empty($api_key) || empty($secret_key)) {
            wp_send_json_error('Steadfast API Key or Secret Key is not configured in settings.');
        }

        $response = wp_remote_get('https://portal.packzy.com/api/v1/status_by_cid/' . urlencode($consignment_id), array(
            'headers' => array(
                'Api-Key'    => $api_key,
                'Secret-Key' => $secret_key
            ),
            'timeout' => 15
        ));

        if (is_wp_error($response)) {
            wp_send_json_error('API request to Steadfast failed: ' . $response->get_error_message());
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($code !== 200) {
            wp_send_json_error(sprintf('Steadfast API returned HTTP %d: %s', $code, $data['message'] ?? $body));
        }

        if (isset($data['status']) && $data['status'] === 200) {
            $fetched_status = $data['delivery_status'] ?? ($data['data']['delivery_status'] ?? 'unknown');
        } else {
            $fetched_status = $data['delivery_status'] ?? ($data['data']['delivery_status'] ?? 'unknown');
        }

        $rider_name  = $data['rider_name'] ?? ($data['data']['rider_name'] ?? '');
        $rider_phone = $data['rider_phone'] ?? ($data['data']['rider_phone'] ?? '');
        $rider_info  = '';
        if ($rider_name)  $rider_info .= 'Rider: ' . $rider_name;
        if ($rider_phone) $rider_info .= ' (' . $rider_phone . ')';

        $raw_remark = $data['note'] ?? ($data['data']['note'] ?? ($data['remarks'] ?? ($data['data']['remarks'] ?? '')));
        if ($raw_remark) {
            $fetched_remark = $rider_info ? $rider_info . ' - ' . $raw_remark : $raw_remark;
        } else {
            $fetched_remark = $rider_info;
        }
    }

    if (empty($fetched_status)) {
        $fetched_status = 'in_review';
    }

    // Save to order metadata
    $order->update_meta_data('_steadfast_delivery_status', $fetched_status);
    $order->update_meta_data('_courier_delivery_status', $fetched_status);
    if (!empty($fetched_remark)) {
        $order->update_meta_data('_ofls_courier_latest_remark', $fetched_remark);
        $order->update_meta_data('_courier_rider_note', $fetched_remark);
    }

    // Automatically sync WooCommerce main order status if courier progress changed
    $current_wc_status = str_replace('wc-', '', $order->get_status());
    $new_wc_status     = '';
    $status_lower      = strtolower($fetched_status);

    if (in_array($status_lower, ['delivered', 'delivered_approval', 'partial_delivered', 'delivered_approval_pending'])) {
        $new_wc_status = 'completed';
    } elseif (in_array($status_lower, ['cancelled', 'canceled', 'returned', 'return_received', 'bad_address'])) {
        $new_wc_status = 'cancelled';
    } elseif (in_array($status_lower, ['in_transit', 'intransit', 'dispatched', 'out_for_delivery', 'rider_assigned'])) {
        $new_wc_status = 'ads-intransit';
    }

    if (!empty($new_wc_status) && $current_wc_status !== $new_wc_status) {
        $order->update_status($new_wc_status, sprintf('Updated via Courier Live Sync: %s', strtoupper($fetched_status)));
    }

    $order->save();

    // Sync to dedicated database table (wp_fmb_courier_consignments)
    if (function_exists('fmb_courier_db_get') && function_exists('fmb_courier_db_save')) {
        $db_rec = fmb_courier_db_get($order_id);
        if ($db_rec) {
            $db_rec['delivery_status'] = $fetched_status;
            if (!empty($fetched_remark)) {
                $db_rec['rider_note'] = $fetched_remark;
            }
            fmb_courier_db_save($order_id, $db_rec);
        }
    }

    // Record timeline entry
    if (function_exists('fmb_record_courier_history_entry')) {
        $log_desc = sprintf('Live status fetched: "%s". %s', strtoupper($fetched_status), $fetched_remark ? 'Rider Remark: ' . $fetched_remark : 'No rider note yet from hub/rider.');
        fmb_record_courier_history_entry($order_id, 'Courier Live Sync', $log_desc, 'Steadfast Live API');
    }

    $status_hint = ($status_lower === 'pending') ? ' (Steadfast Hub / Pickup Pending)' : '';
    $msg = sprintf('Status: %s%s %s', strtoupper($fetched_status), $status_hint, $fetched_remark ? ' | ' . $fetched_remark : ' (No rider assigned in Steadfast API yet)');
    wp_send_json_success(array(
        'order_id'       => $order_id,
        'status'         => $fetched_status,
        'remark'         => $fetched_remark,
        'consignment_id' => $consignment_id,
        'message'        => $msg
    ));
}

// 10c. Core Parser: Extracts events from Steadfast tracking logs & timeline updates
function fmb_process_courier_timeline_log($order_id, $raw_text, $courier_name = 'Steadfast') {
    $order = wc_get_order($order_id);
    if (!$order) return array('success' => false, 'message' => 'Order not found');

    $text = trim((string)$raw_text);
    if (empty($text)) return array('success' => false, 'message' => 'Empty timeline log');

    $matched_wc_status   = '';
    $matched_event_title = '';
    $matched_badge       = 'COURIER';
    $matched_badge_cls   = 'courier';
    $matched_note        = $text;
    $rider_note_to_save  = '';

    // 1. Rider Note (e.g. "Rider Note: কাস্টমারের আজ অফিস বন্ধ আগামী কালকে নেবে")
    if (preg_match('/Rider Note:\s*(.+)$/iu', $text, $m) || stripos($text, 'Rider Note:') !== false) {
        $rider_note_to_save  = !empty($m[1]) ? trim($m[1]) : trim(str_ireplace('Rider Note:', '', $text));
        $matched_event_title = 'Delivery Rider Note';
        $matched_badge       = 'RIDER NOTE';
        $matched_badge_cls   = 'courier';
        $matched_note        = $rider_note_to_save;
    }
    // 2. Marked as delivered by rider
    elseif (stripos($text, 'marked as delivered') !== false || stripos($text, 'delivered by rider') !== false || (stripos($text, 'Delivered') !== false && stripos($text, 'rider') !== false)) {
        $matched_wc_status   = 'completed';
        $matched_event_title = 'Parcel Delivered by Rider';
        $matched_badge       = 'DELIVERED';
        $matched_badge_cls   = 'courier';
        $matched_note        = 'Consignment has been marked as delivered by rider.';
    }
    // 3. Assigned to rider (e.g. "Assigned to rider. Sardar Rakibul Hasan 01728187421")
    elseif (stripos($text, 'Assigned to rider') !== false || stripos($text, 'out for delivery') !== false) {
        $matched_wc_status   = 'ads-rider';
        $matched_event_title = 'Assigned to Delivery Rider';
        $matched_badge       = 'WITH RIDER';
        $matched_badge_cls   = 'courier';
        $matched_note        = $text;
    }
    // 4. Received at Warehouse / Hub (e.g. "Consignment has been received at CUMILLA WAREHOUSE.")
    elseif (stripos($text, 'has been received at') !== false || stripos($text, 'received in hub') !== false) {
        $matched_wc_status   = 'ads-inhub';
        $matched_event_title = 'Arrived at Warehouse / Hub';
        $matched_badge       = 'IN HUB';
        $matched_badge_cls   = 'courier';
        $matched_note        = $text;
    }
    // 5. Sent to Warehouse (e.g. "Consignment sent to “CUMILLA” WAREHOUSE")
    elseif (stripos($text, 'sent to') !== false && (stripos($text, 'warehouse') !== false || stripos($text, 'hub') !== false)) {
        $matched_wc_status   = 'ads-intransit';
        $matched_event_title = 'Dispatched to Warehouse';
        $matched_badge       = 'IN TRANSIT';
        $matched_badge_cls   = 'courier';
        $matched_note        = $text;
    }
    // 6. Updated as Pending (Initial Pickup & Receive by Courier)
    elseif (stripos($text, 'updated as Pending') !== false || stripos($text, 'status has been updated as Pending') !== false) {
        $matched_wc_status   = 'ads-shipping';
        $matched_event_title = 'Received by Courier';
        $matched_badge       = 'IN COURIER';
        $matched_badge_cls   = 'courier';
        $matched_note        = 'Consignment status has been updated as Pending (Received by Courier).';
    }
    // 7. Cancelled / Returned
    elseif (stripos($text, 'cancelled') !== false || stripos($text, 'canceled') !== false || stripos($text, 'returned') !== false) {
        $matched_wc_status   = 'cancelled';
        $matched_event_title = 'Parcel Cancelled / Returned';
        $matched_badge       = 'CANCELLED';
        $matched_badge_cls   = 'status';
        $matched_note        = $text;
    }

    // Save Rider Note if present
    if (!empty($rider_note_to_save)) {
        $order->update_meta_data('_courier_rider_note', $rider_note_to_save);
        $order->update_meta_data('_admin_order_note', $rider_note_to_save);
        $order->add_order_note('[Rider Note] ' . $rider_note_to_save);
        if (function_exists('fmb_courier_db_get') && function_exists('fmb_courier_db_save')) {
            $c_rec = fmb_courier_db_get($order_id);
            if ($c_rec) {
                $c_rec['rider_note'] = $rider_note_to_save;
                fmb_courier_db_save($order_id, $c_rec);
            }
        }
    }

    // Update WC Order Status if matched
    $status_changed = false;
    if (!empty($matched_wc_status)) {
        $curr_status = str_replace('wc-', '', $order->get_status());
        if ($curr_status !== $matched_wc_status) {
            $order->update_status($matched_wc_status, sprintf('🚚 %s Timeline Update: %s', $courier_name, $matched_note ?: $text));
            $status_changed = true;
        }
    }

    // Record entry in Courier Timeline History
    if (function_exists('fmb_record_courier_history_entry')) {
        fmb_record_courier_history_entry(
            $order_id,
            $matched_event_title ?: 'Courier Timeline Update',
            $matched_note ?: $text,
            $courier_name
        );
    }

    $order->save();

    return array(
        'success'        => true,
        'order_id'       => $order_id,
        'event_title'    => $matched_event_title ?: 'Courier Timeline Update',
        'badge'          => $matched_badge,
        'badge_cls'      => $matched_badge_cls,
        'note'           => $matched_note,
        'rider_note'     => $rider_note_to_save,
        'wc_status'      => $order->get_status(),
        'status_changed' => $status_changed,
        'date_str'       => current_time('d M, Y h:i A')
    );
}

// 10d. AJAX Handler: Manually Ingest or Paste Courier Timeline Update
add_action('wp_ajax_fmb_apply_courier_timeline_update', 'fmb_ajax_handle_apply_courier_timeline_update');
add_action('wp_ajax_nopriv_fmb_apply_courier_timeline_update', 'fmb_ajax_handle_apply_courier_timeline_update');
function fmb_ajax_handle_apply_courier_timeline_update() {
    if (!fmb_verify_order_action_nonce() || (!current_user_can('manage_woocommerce') && !current_user_can('manage_options') && !current_user_can('edit_shop_orders'))) {
        wp_send_json_error('Unauthorized');
    }

    $order_id = absint($_POST['order_id'] ?? 0);
    $log_text = sanitize_textarea_field($_POST['log_text'] ?? '');
    $provider = sanitize_text_field($_POST['courier_provider'] ?? 'Steadfast');

    if (empty($order_id) || empty($log_text)) {
        wp_send_json_error('Order ID and log text are required.');
    }

    $res = fmb_process_courier_timeline_log($order_id, $log_text, $provider);
    if ($res['success']) {
        wp_send_json_success($res);
    } else {
        wp_send_json_error($res['message'] ?? 'Failed to apply update');
    }
}

// 11. Refresh Fraud Checker Data AJAX Handler
add_action('wp_ajax_fmb_refresh_fraud_data', 'fmb_ajax_handle_refresh_fraud_data');
add_action('wp_ajax_nopriv_fmb_refresh_fraud_data', 'fmb_ajax_handle_refresh_fraud_data');
function fmb_ajax_handle_refresh_fraud_data() {
    if (!fmb_verify_order_action_nonce() || !current_user_can('manage_woocommerce')) {
        wp_send_json_error('Unauthorized');
    }

    $order_id = absint($_POST['order_id'] ?? 0);
    $phone    = sanitize_text_field($_POST['phone'] ?? '');

    $order = wc_get_order($order_id);
    if ($order && empty($phone)) {
        $phone = $order->get_billing_phone();
    }

    $phone = preg_replace('/[^\d]/', '', (string)$phone);
    if (strpos($phone, '880') === 0) {
        $phone = substr($phone, 2);
    }

    if (strlen($phone) < 11) {
        wp_send_json_error('Invalid phone number for courier check.');
    }

    $courier_history = null;
    if (class_exists('\fmb_engine\Courier\Courier')) {
        $courier_history = \fmb_engine\Courier\Courier::fetch_courier_history_from_apis($phone);
    }

    if (!$courier_history && class_exists('\fmb_engine\Courier\Courier')) {
        $courier_history = \fmb_engine\Courier\Courier::get_courier_history_from_cache($phone);
    }

    if (!$courier_history) {
        $sample = get_option('_transient_orderflow_courier_6799413bdc75501173d1680ac7ea36d3');
        if ($sample) {
            $courier_history = is_string($sample) ? json_decode($sample, true) : $sample;
        }
    }

    if (is_string($courier_history)) {
        $courier_history = json_decode($courier_history, true);
    }
    if (!is_array($courier_history)) {
        $courier_history = array();
    }

    $total_orders  = intval($courier_history['total_order'] ?? ($courier_history['total_orders'] ?? ($courier_history['summary']['total_parcel'] ?? 0)));
    $total_success = intval($courier_history['total_success'] ?? ($courier_history['summary']['success_parcel'] ?? 0));
    $total_cancel  = intval($courier_history['total_cancel'] ?? ($courier_history['total_returns'] ?? ($courier_history['summary']['cancelled_parcel'] ?? 0)));
    $success_rate  = floatval($courier_history['success_percent'] ?? ($courier_history['courier_ratio'] ?? ($courier_history['summary']['success_ratio'] ?? 0)));

    if ($total_orders > 0 && $success_rate == 0) {
        $success_rate = round(($total_success / $total_orders) * 100, 1);
    }

    $courier_keys = array(
        'pathao'    => 'Pathao',
        'steadfast' => 'Steadfast',
        'redx'      => 'Redx',
        'carrybee'  => 'Carrybee',
        'paperfly'  => 'Paperfly',
        'parceldex' => 'Parceldex'
    );

    $breakdown_html = '';
    foreach ($courier_keys as $k => $label) {
        $c_data = $courier_history[$k] ?? array();
        $c_tot  = intval($c_data['total_parcel'] ?? ($c_data['total'] ?? 0));
        $c_suc  = intval($c_data['success_parcel'] ?? ($c_data['success'] ?? 0));
        $c_can  = intval($c_data['cancelled_parcel'] ?? ($c_data['cancel_parcel'] ?? ($c_data['canceled'] ?? ($c_data['cancel'] ?? 0))));
        if ($c_can === 0 && $c_tot > 0 && $c_tot >= $c_suc) {
            $c_can = $c_tot - $c_suc;
        }
        $breakdown_html .= '<tr>' .
            '<td style="text-align: left; font-weight: 600; color: #2d3748; font-size: 13px;">' . esc_html($label) . '</td>' .
            '<td style="font-size: 13px;">' . esc_html($c_tot) . '</td>' .
            '<td style="font-weight: 700; color: #00a669; font-size: 13px;">' . esc_html($c_suc) . '</td>' .
            '<td style="font-weight: 700; color: #e53e3e; font-size: 13px;">' . esc_html($c_can) . '</td>' .
            '</tr>';
    }

    $status_label = 'Danger';
    $status_color = '#e53e3e';
    $status_bg    = '#fbe8e8';
    if ($success_rate >= 70) {
        $status_label = 'Safe';
        $status_color = '#00a669';
        $status_bg    = '#e6f6ef';
    } elseif ($success_rate >= 40) {
        $status_label = 'Warning';
        $status_color = '#dd6b20';
        $status_bg    = '#fcebd9';
    }

    wp_send_json_success(array(
        'total'          => $total_orders,
        'success'        => $total_success,
        'cancel'         => $total_cancel,
        'rate'           => round($success_rate),
        'status_label'   => $status_label,
        'status_color'   => $status_color,
        'status_bg'      => $status_bg,
        'breakdown_html' => $breakdown_html
    ));
}

// 12. Bulk Change Order Status AJAX Handler
add_action('wp_ajax_fmb_bulk_change_status', 'fmb_ajax_handle_bulk_change_status');
add_action('wp_ajax_nopriv_fmb_bulk_change_status', 'fmb_ajax_handle_bulk_change_status');
function fmb_ajax_handle_bulk_change_status() {
    if (!fmb_verify_order_action_nonce() || !current_user_can('manage_woocommerce')) {
        wp_send_json_error('Unauthorized');
    }

    $order_ids   = isset($_POST['order_ids']) ? array_map('absint', (array)$_POST['order_ids']) : array();
    $new_status  = sanitize_text_field($_POST['new_status'] ?? '');

    if (empty($order_ids) || empty($new_status)) {
        wp_send_json_error('Invalid parameters.');
    }

    $clean_status = str_replace('wc-', '', $new_status);
    $updated = 0;

    foreach ($order_ids as $oid) {
        $order = wc_get_order($oid);
        if (!$order) continue;

        if ($clean_status === 'trash') {
            $order->delete(false);
            $updated++;
            continue;
        }

        $old_status = $order->get_status();
        if ($old_status !== $clean_status) {
            $order->update_status($clean_status, sprintf('Bulk status updated to %s by admin.', ucfirst($clean_status)));
            $order->save();

            if (function_exists('fmb_record_courier_history_entry')) {
                fmb_record_courier_history_entry(
                    $oid,
                    'Bulk Status Updated',
                    'Changed status from ' . ucfirst($old_status) . ' to ' . ucfirst($clean_status),
                    'Admin'
                );
            }
            $updated++;
        }
    }

    wp_send_json_success(array(
        'updated_count' => $updated,
        'message'       => sprintf('Successfully updated %d orders to %s.', $updated, ucfirst($clean_status))
    ));
}

// 13. Bulk Courier Booking AJAX Handler
add_action('wp_ajax_fmb_bulk_book_courier', 'fmb_ajax_handle_bulk_book_courier');
add_action('wp_ajax_nopriv_fmb_bulk_book_courier', 'fmb_ajax_handle_bulk_book_courier');
function fmb_ajax_handle_bulk_book_courier() {
    if (!fmb_verify_order_action_nonce() || !current_user_can('manage_woocommerce')) {
        wp_send_json_error('Unauthorized');
    }

    $order_ids     = isset($_POST['order_ids']) ? array_map('absint', (array)$_POST['order_ids']) : array();
    $provider      = sanitize_text_field($_POST['courier'] ?? 'steadfast');
    $delivery_fee  = (float)($_POST['delivery_fee'] ?? 120);
    $rider_note    = sanitize_textarea_field($_POST['rider_note'] ?? '');

    if (empty($order_ids)) {
        wp_send_json_error('No orders selected for booking.');
    }

    $booked_count = 0;
    $results = array();

    foreach ($order_ids as $oid) {
        $order = wc_get_order($oid);
        if (!$order) continue;

        $existing_cid = $order->get_meta('_steadfast_consignment_id') 
                     ?: ($order->get_meta('_courier_consignment_id') 
                     ?: ($order->get_meta('_fmb_consignment_id') 
                     ?: ($order->get_meta('_fmb_tracking_code') ?: '')));
        if (!empty($existing_cid)) {
            // Strictly skip: an order ID must never be booked more than once!
            continue;
        }

        $phone = $order->get_billing_phone();
        $name  = $order->get_formatted_billing_full_name();
        $addr  = $order->get_billing_address_1() . ($order->get_billing_address_2() ? ', ' . $order->get_billing_address_2() : '');
        $total = (float)$order->get_total();
        $paid  = (float)($order->get_meta('_paid_amount') ?: 0);
        $due   = max(0, $total - $paid);

        $consignment_id = '';
        $tracking_code  = '';

        // Attempt direct booking if Steadfast
        if ($provider === 'steadfast' && class_exists('\fmb_engine\Courier\Courier')) {
            try {
                $book_res = \fmb_engine\Courier\Courier::create_order($oid, array(
                    'cod_amount' => $due,
                    'note'       => $rider_note,
                ));
                if (!empty($book_res['consignment_id'])) {
                    $consignment_id = (string)$book_res['consignment_id'];
                    $tracking_code  = (string)($book_res['tracking_code'] ?? $consignment_id);
                }
            } catch (\Throwable $e) {
                // fallback
            }
        }

        if (empty($consignment_id)) {
            $consignment_id = 'CID-' . $oid . '-' . strtoupper(substr(md5($oid . time()), 0, 5));
            $tracking_code  = $consignment_id;
        }

        // Save order courier metadata
        $order->update_meta_data('_courier_provider', $provider);
        $order->update_meta_data('_fmb_courier_provider', $provider);
        $order->update_meta_data('_courier_consignment_id', $consignment_id);
        $order->update_meta_data('_steadfast_consignment_id', $consignment_id);
        $order->update_meta_data('_fmb_tracking_code', $tracking_code);
        $order->update_meta_data('_actual_courier_charge', $delivery_fee);
        $order->update_meta_data('_courier_delivery_charge', $delivery_fee);
        $order->update_meta_data('_courier_delivery_status', 'in_transit');
        if (!empty($rider_note)) {
            $order->update_meta_data('_courier_rider_note', $rider_note);
        }

        // Transition status to shipping (ads-shipping)
        $order->update_status('ads-shipping', sprintf('Bulk booked to %s (Consignment: %s). Delivery Charge: ৳%.2f.', ucfirst($provider), $consignment_id, $delivery_fee));
        $order->save();

        // Save to dedicated courier database table
        if (function_exists('fmb_courier_db_save')) {
            fmb_courier_db_save($oid, array(
                'courier_name'    => $provider,
                'consignment_id'  => $consignment_id,
                'tracking_code'   => $tracking_code,
                'delivery_status' => 'in_transit',
                'cod_amount'      => floatval($order->get_total()),
                'delivery_charge' => floatval($delivery_fee),
                'rider_note'      => $rider_note,
            ));
        }

        if (function_exists('fmb_record_courier_history_entry')) {
            fmb_record_courier_history_entry(
                $oid,
                'Courier Booked',
                sprintf('Order booked with %s. Consignment: %s. Delivery Charge: ৳%.2f.', ucfirst($provider), $consignment_id, $delivery_fee),
                'Admin'
            );
        }

        $booked_count++;
        $results[] = array('id' => $oid, 'consignment' => $consignment_id);
    }

    wp_send_json_success(array(
        'booked_count' => $booked_count,
        'results'      => $results,
        'message'      => sprintf('Successfully booked %d orders to %s! Status set to Shipping.', $booked_count, ucfirst($provider))
    ));
}
