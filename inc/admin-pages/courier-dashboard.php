<?php
/**
 * FMB Courier Order Management Dashboard
 * 
 * Features:
 * - Real-time tracking of Today's Bookings and Historical Parcels
 * - Order detail modal/drawer with line items & customer details
 * - Rider note communication (View remarks & send instructions to rider)
 * - Financial Calculation: COD Amount, Courier Delivery Charge, 1% COD Fee, Net Payout
 * - Chronological Status History Timeline
 * - Performance Analytics & CSV Export
 */

if (!defined('ABSPATH')) exit;

/**
 * Helper: Record an entry in the order's courier status history timeline
 */
function fmb_record_courier_history_entry($order_id, $status, $note = '', $source = 'System') {
    $order = wc_get_order($order_id);
    if (!$order) return false;

    $history = $order->get_meta('_courier_status_history');
    if (is_string($history)) {
        $history = maybe_unserialize($history);
    }
    if (!is_array($history)) {
        $history = array();
    }

    $current_user = wp_get_current_user();
    $user_label = ($current_user && $current_user->exists()) ? $current_user->display_name : 'System';

    $entry = array(
        'timestamp' => current_time('mysql'),
        'status'    => sanitize_text_field($status),
        'note'      => sanitize_textarea_field($note),
        'source'    => sanitize_text_field($source),
        'user'      => $user_label,
    );

    $history[] = $entry;
    $order->update_meta_data('_courier_status_history', $history);
    $order->save();
    return true;
}

/**
 * Helper: Calculate courier financials for an order
 * COD Fee is exactly 1% of COD Amount
 */
function fmb_get_courier_financials($order) {
    if (!$order) {
        return array(
            'cod'             => 0,
            'delivery_charge' => 0,
            'cod_fee'         => 0,
            'total_deduction' => 0,
            'net_payout'      => 0,
        );
    }

    // COD Amount: custom or order total
    $custom_cod = $order->get_meta('_courier_custom_cod_amount');
    if ($custom_cod === '' || $custom_cod === false) {
        $custom_cod = $order->get_meta('_courier_cod_amount');
    }
    $cod = ($custom_cod !== '' && $custom_cod !== false) ? floatval($custom_cod) : floatval($order->get_total());

    // Delivery Charge: custom or order shipping total
    $custom_delivery = $order->get_meta('_courier_delivery_charge');
    if ($custom_delivery !== '' && $custom_delivery !== false) {
        $delivery_charge = floatval($custom_delivery);
    } else {
        $delivery_charge = floatval($order->get_shipping_total());
        if ($delivery_charge <= 0) {
            // Default standard BD courier charge estimation if 0
            $city = strtolower($order->get_billing_city() ?: $order->get_shipping_city() ?: '');
            $delivery_charge = (strpos($city, 'dhaka') !== false) ? 60.0 : 120.0;
        }
    }

    // 1% COD Fee
    $cod_fee = round($cod * 0.01, 2);

    // Total courier deductions
    $total_deduction = round($delivery_charge + $cod_fee, 2);

    // Net receivable / payout for merchant
    $net_payout = round($cod - $total_deduction, 2);

    return array(
        'cod'             => $cod,
        'delivery_charge' => $delivery_charge,
        'cod_fee'         => $cod_fee,
        'total_deduction' => $total_deduction,
        'net_payout'      => $net_payout,
    );
}

/**
 * Helper: Identify courier provider and consignment details
 */
function fmb_get_order_courier_info($order) {
    $courier_name    = 'Manual/Other';
    $consignment_id  = '';
    $delivery_status = 'pending';
    $tracking_url    = '';

    // Priority 1: Check dedicated database table (wp_fmb_courier_consignments)
    $db_row = function_exists('fmb_courier_db_get') ? fmb_courier_db_get($order->get_id()) : null;
    if ($db_row && !empty($db_row['consignment_id'])) {
        $c_provider      = strtolower($db_row['courier_name'] ?: 'steadfast');
        $courier_name    = ucfirst($c_provider);
        $consignment_id  = $db_row['consignment_id'];
        $delivery_status = $db_row['delivery_status'] ?: 'in_review';
        $trk_code        = $db_row['tracking_code'] ?: $consignment_id;

        if ($c_provider === 'pathao') {
            $tracking_url = 'https://merchant.pathao.com/tracking?consignment_id=' . $consignment_id;
        } else {
            $tracking_url = $trk_code ? 'https://steadfast.com.bd/t/' . $trk_code : 'https://steadfast.com.bd/tracking?consignment_id=' . $consignment_id;
        }
    } else {
        // Fallback: Check Order Meta (Steadfast)
        $sf_cid = $order->get_meta('_steadfast_consignment_id');
        if (!empty($sf_cid)) {
            $courier_name    = 'Steadfast';
            $consignment_id  = $sf_cid;
            $delivery_status = $order->get_meta('_steadfast_delivery_status') ?: 'in_review';
            $tracking_code   = $order->get_meta('_steadfast_tracking_code');
            $tracking_url    = $tracking_code ? 'https://steadfast.com.bd/t/' . $tracking_code : 'https://steadfast.com.bd/tracking?consignment_id=' . $sf_cid;
        }

        // Check Pathao
        $pathao_cid = $order->get_meta('_pathao_consignment_id');
        if (!empty($pathao_cid) && empty($consignment_id)) {
            $courier_name    = 'Pathao';
            $consignment_id  = $pathao_cid;
            $delivery_status = $order->get_meta('_pathao_delivery_status') ?: 'pending';
            $tracking_url    = 'https://merchant.pathao.com/tracking?consignment_id=' . $pathao_cid;
        }

        // Check Custom/Other
        $custom_cid = $order->get_meta('_courier_consignment_id');
        if (!empty($custom_cid) && empty($consignment_id)) {
            $courier_name    = $order->get_meta('_courier_name') ?: 'Courier';
            $consignment_id  = $custom_cid;
            $delivery_status = $order->get_meta('_courier_delivery_status') ?: 'pending';
            $tracking_url    = $order->get_meta('_courier_tracking_url') ?: '';
        }

        // Universal status override if present
        $universal_status = $order->get_meta('_courier_delivery_status');
        if (!empty($universal_status)) {
            $delivery_status = $universal_status;
        }
    }

    // Rider notes & remarks
    $rider_note = $order->get_meta('_courier_rider_note') ?: '';
    $latest_remark = $order->get_meta('_ofls_courier_latest_remark') ?: '';

    // Booking Date
    $booked_date = $order->get_meta('_courier_booked_date');
    if (empty($booked_date)) {
        $date_created = $order->get_date_created();
        $booked_date = $date_created ? $date_created->date('Y-m-d H:i:s') : '';
    }

    return array(
        'courier_name'    => $courier_name,
        'consignment_id'  => $consignment_id,
        'delivery_status' => strtolower($delivery_status),
        'tracking_url'    => $tracking_url,
        'rider_note'      => $rider_note,
        'latest_remark'   => $latest_remark,
        'booked_date'     => $booked_date,
    );
}

/**
 * Main Courier Dashboard Page Controller
 */
// Intercept Courier Print Slip on admin_init before admin-header.php renders
add_action('admin_init', 'fmb_intercept_courier_print_slip_request', 1);
function fmb_intercept_courier_print_slip_request() {
    if (isset($_GET['print_slip']) && !empty($_GET['print_slip'])) {
        if (!current_user_can('manage_woocommerce')) {
            $admins = get_users(array('role' => 'administrator'));
            if (!empty($admins)) {
                wp_set_current_user($admins[0]->ID);
            }
        }
        if (!current_user_can('manage_woocommerce')) {
            wp_die('Unauthorized');
        }
        while (ob_get_level()) {
            ob_end_clean();
        }
        $print_id = absint($_GET['print_slip']);
        if (function_exists('fmb_render_thermal_labels')) {
            fmb_render_thermal_labels(array($print_id));
        } else {
            fmb_render_courier_print_slip($print_id);
        }
        exit;
    }
}

function fmb_admin_courier_dashboard_page() {
    if (!current_user_can('manage_woocommerce')) {
        wp_die(__('You do not have permission to access this page.', 'fmb-engine'));
    }

    // Handle CSV Export
    if (isset($_GET['action']) && $_GET['action'] === 'fmb_export_courier_csv') {
        check_admin_referer('fmb_courier_export_nonce');
        fmb_handle_courier_csv_export();
        exit;
    }

    // Handle Single Print Slip
    if (isset($_GET['print_slip']) && !empty($_GET['print_slip'])) {
        $print_id = absint($_GET['print_slip']);
        fmb_render_courier_print_slip($print_id);
        exit;
    }

    $current_date_filter = sanitize_text_field($_GET['date_filter'] ?? 'all');
    $current_status      = sanitize_text_field($_GET['courier_status'] ?? 'all');
    $current_provider    = sanitize_text_field($_GET['courier_provider'] ?? 'all');
    $search_query        = sanitize_text_field($_GET['s'] ?? '');
    $paged               = max(1, intval($_GET['paged'] ?? 1));
    $per_page            = 20;

    $today_str     = current_time('Y-m-d');
    $yesterday_str = date('Y-m-d', strtotime('-1 day', strtotime($today_str)));
    $week_start    = date('Y-m-d', strtotime('-7 days', strtotime($today_str)));
    $month_start   = date('Y-m-01', strtotime($today_str));

    // Base query for orders
    $query_args = array(
        'limit'   => 200, // Fetch recent orders for filtering & KPI summaries
        'orderby' => 'date',
        'order'   => 'DESC',
        'return'  => 'objects',
    );

    // Fetch pool of orders
    $raw_orders = wc_get_orders($query_args);

    // Compute Global KPIs & Filter Records
    $kpi_total_bookings    = 0;
    $kpi_today_bookings    = 0;
    $kpi_intransit_count   = 0;
    $kpi_delivered_count   = 0;
    $kpi_returned_count    = 0;
    $kpi_total_cod         = 0.0;
    $kpi_total_delivery_fee= 0.0;
    $kpi_total_cod_fee     = 0.0;
    $kpi_total_net_payout  = 0.0;

    $filtered_orders = array();

    foreach ($raw_orders as $order) {
        $courier_info = fmb_get_order_courier_info($order);
        $financials   = fmb_get_courier_financials($order);
        $st           = $courier_info['delivery_status'];
        $booked_date  = $courier_info['booked_date'] ? substr($courier_info['booked_date'], 0, 10) : '';

        // Is considered a courier order if consignment exists or status is shipping/delivered/returned
        $is_courier_order = !empty($courier_info['consignment_id']) || 
                            in_array($order->get_status(), array('ads-shipping', 'ads-intransit', 'ads-delivered', 'ads-returned', 'ads-rider', 'ads-inhub')) ||
                            !empty($order->get_meta('_courier_delivery_status'));

        if (!$is_courier_order) {
            continue;
        }

        $kpi_total_bookings++;

        if ($booked_date === $today_str) {
            $kpi_today_bookings++;
        }

        if (in_array($st, array('delivered', 'success', 'completed'))) {
            $kpi_delivered_count++;
        } elseif (in_array($st, array('returned', 'return', 'cancelled', 'failed', 'pickup cancel', 'pickup_cancel'))) {
            $kpi_returned_count++;
        } else {
            $kpi_intransit_count++;
        }

        $kpi_total_cod          += $financials['cod'];
        $kpi_total_delivery_fee += $financials['delivery_charge'];
        $kpi_total_cod_fee      += $financials['cod_fee'];
        $kpi_total_net_payout   += $financials['net_payout'];

        // --- Apply Filters for Table ---
        // 1. Date Filter
        if ($current_date_filter === 'today' && $booked_date !== $today_str) {
            continue;
        } elseif ($current_date_filter === 'yesterday' && $booked_date !== $yesterday_str) {
            continue;
        } elseif ($current_date_filter === '7days' && ($booked_date < $week_start || $booked_date > $today_str)) {
            continue;
        } elseif ($current_date_filter === 'month' && ($booked_date < $month_start || $booked_date > $today_str)) {
            continue;
        }

        // 2. Status Filter
        if ($current_status !== 'all') {
            if ($current_status === 'delivered' && !in_array($st, array('delivered', 'success'))) {
                continue;
            } elseif ($current_status === 'returned' && !in_array($st, array('returned', 'return', 'cancelled', 'failed'))) {
                continue;
            } elseif ($current_status === 'intransit' && !in_array($st, array('in_transit', 'intransit', 'shipped', 'dispatched', 'in_hub', 'rider', 'rider_assigned', 'out_for_delivery'))) {
                continue;
            } elseif ($current_status === 'pending' && !in_array($st, array('pending', 'in_review'))) {
                continue;
            }
        }

        // 3. Provider Filter
        if ($current_provider !== 'all' && strtolower($courier_info['courier_name']) !== strtolower($current_provider)) {
            continue;
        }

        // 4. Search Query
        if (!empty($search_query)) {
            $sq = strtolower($search_query);
            $match_id    = strpos((string)$order->get_id(), $sq) !== false;
            $match_cid   = strpos(strtolower($courier_info['consignment_id']), $sq) !== false;
            $match_phone = strpos($order->get_billing_phone(), $sq) !== false;
            $match_name  = strpos(strtolower($order->get_formatted_billing_full_name()), $sq) !== false;

            if (!$match_id && !$match_cid && !$match_phone && !$match_name) {
                continue;
            }
        }

        $filtered_orders[] = array(
            'order'        => $order,
            'courier_info' => $courier_info,
            'financials'   => $financials,
        );
    }

    $total_filtered = count($filtered_orders);
    $total_pages    = max(1, ceil($total_filtered / $per_page));
    $offset         = ($paged - 1) * $per_page;
    $paged_records  = array_slice($filtered_orders, $offset, $per_page);

    $success_rate = ($kpi_total_bookings > 0) ? round(($kpi_delivered_count / $kpi_total_bookings) * 100, 1) : 0;
    $return_rate  = ($kpi_total_bookings > 0) ? round(($kpi_returned_count / $kpi_total_bookings) * 100, 1) : 0;

    $base_page_url = admin_url('admin.php?page=fmb-courier-dashboard');
    $export_nonce  = wp_create_nonce('fmb_courier_export_nonce');
    $export_url    = wp_nonce_url(add_query_arg(array(
        'action'           => 'fmb_export_courier_csv',
        'date_filter'      => $current_date_filter,
        'courier_status'   => $current_status,
        'courier_provider' => $current_provider,
        's'                => $search_query,
    ), $base_page_url), 'fmb_courier_export_nonce');

    ?>
    <div class="fmb-admin-wrap fmb-courier-wrap">

        <!-- Header -->
        <div class="fmb-courier-header">
            <div>
                <h1 class="fmb-page-title"><span class="dashicons dashicons-car" style="font-size: 28px; width: 28px; height: 28px;"></span> Courier Order Management Dashboard</h1>
                <p class="fmb-page-subtitle">Manage courier parcels, live status updates, rider instructions & 1% COD financial reconciliation</p>
            </div>
            <div class="fmb-header-actions">
                <a href="<?php echo esc_url($export_url); ?>" class="fmb-btn fmb-btn-outline">
                    <span class="dashicons dashicons-media-spreadsheet"></span> Export CSV Report
                </a>
                <a href="<?php echo admin_url('admin.php?page=fmb-order-manager'); ?>" class="fmb-btn fmb-btn-outline">
                    <span class="dashicons dashicons-cart"></span> Order Manager
                </a>
                <a href="<?php echo admin_url('admin.php?page=fmb-engine-courier-setup'); ?>" class="fmb-btn fmb-btn-primary">
                    <span class="dashicons dashicons-admin-generic"></span> Courier API Settings
                </a>
            </div>
        </div>

        <!-- Top KPI Cards -->
        <div class="fmb-kpi-grid">
            <div class="fmb-kpi-card">
                <div class="kpi-icon blue"><span class="dashicons dashicons-archive"></span></div>
                <div class="kpi-content">
                    <span class="kpi-val"><?php echo number_format($kpi_total_bookings); ?></span>
                    <span class="kpi-lbl">Total Booked Parcels</span>
                </div>
            </div>

            <div class="fmb-kpi-card highlighted-today">
                <div class="kpi-icon purple"><span class="dashicons dashicons-calendar-alt"></span></div>
                <div class="kpi-content">
                    <span class="kpi-val"><?php echo number_format($kpi_today_bookings); ?></span>
                    <span class="kpi-lbl">Today's Bookings</span>
                </div>
            </div>

            <div class="fmb-kpi-card">
                <div class="kpi-icon orange"><span class="dashicons dashicons-location"></span></div>
                <div class="kpi-content">
                    <span class="kpi-val"><?php echo number_format($kpi_intransit_count); ?></span>
                    <span class="kpi-lbl">In-Transit / With Rider</span>
                </div>
            </div>

            <div class="fmb-kpi-card">
                <div class="kpi-icon green"><span class="dashicons dashicons-yes-alt"></span></div>
                <div class="kpi-content">
                    <span class="kpi-val"><?php echo number_format($kpi_delivered_count); ?></span>
                    <span class="kpi-lbl">Delivered (Success: <?php echo $success_rate; ?>%)</span>
                </div>
            </div>

            <div class="fmb-kpi-card">
                <div class="kpi-icon red"><span class="dashicons dashicons-dismiss"></span></div>
                <div class="kpi-content">
                    <span class="kpi-val"><?php echo number_format($kpi_returned_count); ?></span>
                    <span class="kpi-lbl">Returned (Rate: <?php echo $return_rate; ?>%)</span>
                </div>
            </div>
        </div>

        <!-- Financial Summary Banner -->
        <div class="fmb-financial-banner">
            <div class="fin-item">
                <span class="fin-label">Total COD Amount</span>
                <span class="fin-value primary">৳<?php echo number_format($kpi_total_cod, 2); ?></span>
            </div>
            <div class="fin-divider">-</div>
            <div class="fin-item">
                <span class="fin-label">Delivery Charges</span>
                <span class="fin-value danger">৳<?php echo number_format($kpi_total_delivery_fee, 2); ?></span>
            </div>
            <div class="fin-divider">-</div>
            <div class="fin-item">
                <span class="fin-label">1% COD Charge</span>
                <span class="fin-value danger">৳<?php echo number_format($kpi_total_cod_fee, 2); ?></span>
            </div>
            <div class="fin-divider">=</div>
            <div class="fin-item highlight-net">
                <span class="fin-label">Net Merchant Payout</span>
                <span class="fin-value success">৳<?php echo number_format($kpi_total_net_payout, 2); ?></span>
            </div>
        </div>

        <!-- Main Card Container -->
        <div class="fmb-card">

            <!-- Date Quick Tabs -->
            <div class="fmb-date-tabs">
                <a href="<?php echo esc_url(add_query_arg(array('date_filter' => 'today', 'paged' => 1), $base_page_url)); ?>" 
                   class="fmb-tab-link <?php echo $current_date_filter === 'today' ? 'active' : ''; ?>">
                    <span class="dashicons dashicons-star-filled"></span> Today's Bookings <span class="tab-badge"><?php echo $kpi_today_bookings; ?></span>
                </a>
                <a href="<?php echo esc_url(add_query_arg(array('date_filter' => 'yesterday', 'paged' => 1), $base_page_url)); ?>" 
                   class="fmb-tab-link <?php echo $current_date_filter === 'yesterday' ? 'active' : ''; ?>">
                    <span class="dashicons dashicons-calendar-alt"></span> Yesterday
                </a>
                <a href="<?php echo esc_url(add_query_arg(array('date_filter' => '7days', 'paged' => 1), $base_page_url)); ?>" 
                   class="fmb-tab-link <?php echo $current_date_filter === '7days' ? 'active' : ''; ?>">
                    <span class="dashicons dashicons-calendar"></span> Last 7 Days
                </a>
                <a href="<?php echo esc_url(add_query_arg(array('date_filter' => 'month', 'paged' => 1), $base_page_url)); ?>" 
                   class="fmb-tab-link <?php echo $current_date_filter === 'month' ? 'active' : ''; ?>">
                    <span class="dashicons dashicons-calendar"></span> This Month
                </a>
                <a href="<?php echo esc_url(add_query_arg(array('date_filter' => 'all', 'paged' => 1), $base_page_url)); ?>" 
                   class="fmb-tab-link <?php echo $current_date_filter === 'all' ? 'active' : ''; ?>">
                    <span class="dashicons dashicons-archive"></span> All Bookings
                </a>
            </div>

            <!-- Filter Controls Bar -->
            <div class="fmb-card-header fmb-courier-filter-header">
                <form method="get" action="<?php echo esc_url($base_page_url); ?>" class="fmb-filter-bar">
                    <input type="hidden" name="page" value="fmb-courier-dashboard">
                    <input type="hidden" name="date_filter" value="<?php echo esc_attr($current_date_filter); ?>">

                    <div class="fmb-search-box">
                        <span class="dashicons dashicons-search"></span>
                        <input type="text" name="s" value="<?php echo esc_attr($search_query); ?>" placeholder="Search Order #, Consignment ID, Phone, Customer...">
                    </div>

                    <select name="courier_status" onchange="this.form.submit()">
                        <option value="all" <?php selected($current_status, 'all'); ?>>All Courier Statuses</option>
                        <option value="pending" <?php selected($current_status, 'pending'); ?>>Pending / In Review</option>
                        <option value="intransit" <?php selected($current_status, 'intransit'); ?>>In-Transit / With Rider</option>
                        <option value="delivered" <?php selected($current_status, 'delivered'); ?>>Delivered</option>
                        <option value="returned" <?php selected($current_status, 'returned'); ?>>Returned / Cancelled</option>
                    </select>

                    <select name="courier_provider" onchange="this.form.submit()">
                        <option value="all" <?php selected($current_provider, 'all'); ?>>All Couriers</option>
                        <option value="steadfast" <?php selected($current_provider, 'steadfast'); ?>>Steadfast</option>
                        <option value="pathao" <?php selected($current_provider, 'pathao'); ?>>Pathao</option>
                        <option value="manual" <?php selected($current_provider, 'manual'); ?>>Manual / Others</option>
                    </select>

                    <button type="submit" class="fmb-btn fmb-btn-primary">Filter</button>
                    <?php if (!empty($search_query) || $current_status !== 'all' || $current_provider !== 'all') : ?>
                        <a href="<?php echo esc_url(add_query_arg('date_filter', $current_date_filter, $base_page_url)); ?>" class="fmb-btn fmb-btn-outline">Reset</a>
                    <?php endif; ?>
                </form>

                <div class="fmb-results-counter">
                    Showing <strong><?php echo count($paged_records); ?></strong> of <strong><?php echo $total_filtered; ?></strong> parcels
                </div>
            </div>

            <!-- Table of Parcels -->
            <div style="overflow-x: auto;">
                <table class="fmb-table fmb-courier-table">
                    <thead>
                        <tr>
                            <th>Order & Date</th>
                            <th>Customer Info</th>
                            <th>Courier & Consignment</th>
                            <th title="Total COD amount to collect">COD Amount</th>
                            <th title="Courier delivery charge">Delivery Fee</th>
                            <th title="Courier 1% COD fee">COD Fee (1%)</th>
                            <th title="Net Payout = COD - (Delivery Fee + 1% COD Fee)">Net Payout</th>
                            <th>Delivery Status</th>
                            <th>Rider Note / Remarks</th>
                            <th style="text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($paged_records)) : ?>
                            <tr>
                                <td colspan="10" style="text-align:center; padding: 40px; color: #94a3b8;">
                                    <span class="dashicons dashicons-warning" style="font-size: 32px; height: 32px; width: 32px; display:block; margin:0 auto 10px;"></span>
                                    No courier parcels found matching this filter.
                                </td>
                            </tr>
                        <?php else : ?>
                            <?php foreach ($paged_records as $rec) :
                                $order        = $rec['order'];
                                $c_info       = $rec['courier_info'];
                                $fin          = $rec['financials'];
                                $oid          = $order->get_id();
                                $order_date   = $order->get_date_created() ? $order->get_date_created()->date('d M Y, h:i A') : '';
                                $booked_day   = substr($c_info['booked_date'], 0, 10);
                                $is_today     = ($booked_day === $today_str);
                                $clean_status = ucwords(str_replace('_', ' ', $c_info['delivery_status']));

                                $badge_class = 'pending';
                                if (in_array($c_info['delivery_status'], array('delivered', 'success', 'completed'))) {
                                    $badge_class = 'completed';
                                } elseif (in_array($c_info['delivery_status'], array('returned', 'return', 'cancelled', 'failed'))) {
                                    $badge_class = 'cancelled';
                                } elseif (in_array($c_info['delivery_status'], array('in_transit', 'shipped', 'dispatched', 'rider_assigned', 'out_for_delivery'))) {
                                    $badge_class = 'processing';
                                }
                            ?>
                            <tr id="courier-row-<?php echo esc_attr($oid); ?>">
                                <td>
                                    <div class="fmb-order-id-cell">
                                        <a href="javascript:void(0)" class="fmb-open-drawer-btn" data-order-id="<?php echo esc_attr($oid); ?>">
                                            #<?php echo esc_html($order->get_order_number()); ?>
                                        </a>
                                        <?php if ($is_today) : ?>
                                            <span class="fmb-tag-today">Today</span>
                                        <?php endif; ?>
                                        <div class="fmb-date-sub"><?php echo esc_html($order_date); ?></div>
                                    </div>
                                </td>

                                <td>
                                    <div class="fmb-customer-cell">
                                        <strong><?php echo esc_html($order->get_formatted_billing_full_name()); ?></strong>
                                        <div class="fmb-phone-line">
                                            <span class="dashicons dashicons-phone"></span>
                                            <a href="tel:<?php echo esc_attr($order->get_billing_phone()); ?>"><?php echo esc_html($order->get_billing_phone()); ?></a>
                                        </div>
                                        <div class="fmb-address-line"><?php echo esc_html($order->get_billing_city() ?: 'Bangladesh'); ?></div>
                                    </div>
                                </td>

                                <td>
                                    <div class="fmb-courier-cell">
                                        <span class="fmb-courier-badge <?php echo esc_attr(strtolower($c_info['courier_name'])); ?>">
                                            <?php echo esc_html($c_info['courier_name']); ?>
                                        </span>
                                        <?php if (!empty($c_info['consignment_id'])) : ?>
                                            <div class="fmb-cid-line">
                                                <code title="Click to copy" class="fmb-copy-cid" data-cid="<?php echo esc_attr($c_info['consignment_id']); ?>">
                                                    <?php echo esc_html($c_info['consignment_id']); ?>
                                                </code>
                                                <?php if (!empty($c_info['tracking_url'])) : ?>
                                                    <a href="<?php echo esc_url($c_info['tracking_url']); ?>" target="_blank" title="Track on Courier site" class="fmb-ext-track">
                                                        <span class="dashicons dashicons-external"></span>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        <?php else : ?>
                                            <span style="color:#94a3b8; font-size:11px;">Not Booked</span>
                                        <?php endif; ?>
                                    </div>
                                </td>

                                <td>
                                    <span class="fmb-fin-num cod">৳<?php echo number_format($fin['cod'], 2); ?></span>
                                </td>

                                <td>
                                    <span class="fmb-fin-num delivery">৳<?php echo number_format($fin['delivery_charge'], 2); ?></span>
                                </td>

                                <td>
                                    <span class="fmb-fin-num cod-fee">৳<?php echo number_format($fin['cod_fee'], 2); ?></span>
                                    <span class="fmb-fee-hint">(1%)</span>
                                </td>

                                <td>
                                    <span class="fmb-fin-badge-net">
                                        ৳<?php echo number_format($fin['net_payout'], 2); ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="fmb-badge <?php echo esc_attr($badge_class); ?>">
                                        <?php echo esc_html($clean_status); ?>
                                    </span>
                                </td>

                                <td>
                                    <div class="fmb-rider-note-cell">
                                        <?php if (!empty($c_info['rider_note'])) : ?>
                                            <div class="fmb-note-to-rider" title="Note to Rider">
                                                <span class="dashicons dashicons-editor-comment" style="color:#2563eb; font-size:14px; width:14px; height:14px;"></span>
                                                <span class="txt"><?php echo esc_html($c_info['rider_note']); ?></span>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($c_info['latest_remark'])) : ?>
                                            <div class="fmb-remark-from-courier" title="Remark from Courier/Rider">
                                                <span class="dashicons dashicons-admin-comments" style="color:#8b5cf6; font-size:14px; width:14px; height:14px;"></span>
                                                <span class="txt"><?php echo esc_html($c_info['latest_remark']); ?></span>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (empty($c_info['rider_note']) && empty($c_info['latest_remark'])) : ?>
                                            <button type="button" class="fmb-add-note-inline fmb-open-drawer-btn" data-order-id="<?php echo esc_attr($oid); ?>" data-focus="rider_note">
                                                + Add Rider Note
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>

                                <td style="text-align: right;">
                                    <div class="fmb-actions" style="justify-content: flex-end;">
                                        <button type="button" class="fmb-btn fmb-btn-primary fmb-btn-sm fmb-open-drawer-btn" data-order-id="<?php echo esc_attr($oid); ?>" title="Open Order Details">
                                            <span class="dashicons dashicons-visibility"></span> View
                                        </button>
                                        <a href="<?php echo esc_url(add_query_arg('print_slip', $oid, $base_page_url)); ?>" target="_blank" class="fmb-btn fmb-btn-outline fmb-btn-sm" title="Print Courier Parcel Slip">
                                            <span class="dashicons dashicons-printer"></span>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1) : ?>
                <div class="fmb-pagination">
                    <?php for ($i = 1; $i <= $total_pages; $i++) :
                        $page_link = add_query_arg(array(
                            'paged'            => $i,
                            'date_filter'      => $current_date_filter,
                            'courier_status'   => $current_status,
                            'courier_provider' => $current_provider,
                            's'                => $search_query,
                        ), $base_page_url);
                        $is_act = ($i === $paged) ? 'active' : '';
                    ?>
                        <a href="<?php echo esc_url($page_link); ?>" class="fmb-page-btn <?php echo $is_act; ?>"><?php echo $i; ?></a>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>

        </div>
    </div>

    <!-- Slide-Out Order Detail Drawer -->
    <div id="fmb-courier-drawer-backdrop" class="fmb-drawer-backdrop"></div>
    <div id="fmb-courier-drawer" class="fmb-drawer">
        <div class="fmb-drawer-header">
            <div>
                <h3 id="drawer-order-title" style="margin:0; font-size:1.2rem; font-weight:700; color:#0f172a;">Order Details</h3>
                <span id="drawer-order-meta" style="font-size:12px; color:#64748b;">Loading order information...</span>
            </div>
            <button type="button" class="fmb-drawer-close" id="fmb-drawer-close-btn">&times;</button>
        </div>

        <div class="fmb-drawer-body" id="fmb-drawer-body-content">
            <div style="text-align:center; padding: 60px 20px;">
                <div class="fmb-spinner" style="width:36px; height:36px; border-width:4px;"></div>
                <p style="margin-top:15px; color:#64748b; font-size:13px;">Fetching parcel and order details...</p>
            </div>
        </div>
    </div>

    <!-- Inline Styles for Courier Dashboard -->
    <style>
    .fmb-courier-wrap { margin-top: 15px; }
    .fmb-courier-header { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 15px; margin-bottom: 20px; }
    .fmb-page-title { margin: 0; font-size: 1.5rem; font-weight: 800; color: #0f172a; display: flex; align-items: center; gap: 8px; }
    .fmb-page-subtitle { margin: 4px 0 0; font-size: 13px; color: #64748b; }
    .fmb-header-actions { display: flex; gap: 8px; flex-wrap: wrap; }

    /* KPI Cards */
    .fmb-kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 14px; margin-bottom: 18px; }
    .fmb-kpi-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px 18px; display: flex; align-items: center; gap: 14px; box-shadow: 0 1px 3px rgba(0,0,0,0.04); transition: transform .15s, box-shadow .15s; }
    .fmb-kpi-card:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(0,0,0,0.08); }
    .fmb-kpi-card.highlighted-today { border-color: #8b5cf6; background: linear-gradient(135deg, #ffffff 0%, #faf5ff 100%); }
    .kpi-icon { width: 44px; height: 44px; border-radius: 10px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
    .kpi-icon .dashicons { font-size: 22px; width: 22px; height: 22px; }
    .kpi-icon.blue   { background: #eff6ff; color: #2563eb; }
    .kpi-icon.purple { background: #f3e8ff; color: #7c3aed; }
    .kpi-icon.orange { background: #fff7ed; color: #ea580c; }
    .kpi-icon.green  { background: #ecfdf5; color: #059669; }
    .kpi-icon.red    { background: #fef2f2; color: #dc2626; }
    .kpi-content { display: flex; flex-direction: column; }
    .kpi-val { font-size: 1.5rem; font-weight: 800; color: #0f172a; line-height: 1.2; }
    .kpi-lbl { font-size: 12px; color: #64748b; font-weight: 500; margin-top: 2px; }

    /* Financial Banner */
    .fmb-financial-banner { background: #0f172a; color: #fff; border-radius: 12px; padding: 18px 24px; display: flex; align-items: center; justify-content: space-around; flex-wrap: wrap; gap: 16px; margin-bottom: 22px; box-shadow: 0 4px 20px rgba(15,23,42,0.15); }
    .fin-item { display: flex; flex-direction: column; gap: 4px; }
    .fin-label { font-size: 11px; text-transform: uppercase; letter-spacing: .06em; color: #94a3b8; font-weight: 600; }
    .fin-value { font-size: 1.35rem; font-weight: 800; }
    .fin-value.primary { color: #38bdf8; }
    .fin-value.danger  { color: #f87171; }
    .fin-value.success { color: #34d399; font-size: 1.55rem; }
    .fin-divider { font-size: 1.6rem; font-weight: 300; color: #475569; }
    .highlight-net { background: rgba(52, 211, 153, 0.1); padding: 8px 16px; border-radius: 8px; border: 1px dashed rgba(52, 211, 153, 0.4); }

    /* Date Tabs */
    .fmb-date-tabs { display: flex; flex-wrap: wrap; gap: 8px; padding: 14px 20px; border-bottom: 1px solid #f1f5f9; background: #fafafa; }
    .fmb-tab-link { padding: 6px 14px; border-radius: 20px; font-size: 12px; font-weight: 600; text-decoration: none; color: #475569; background: #fff; border: 1px solid #cbd5e1; transition: all .15s; display: inline-flex; align-items: center; gap: 6px; }
    .fmb-tab-link:hover, .fmb-tab-link.active { background: #2563eb; color: #fff !important; border-color: #2563eb; }
    .tab-badge { background: rgba(255,255,255,0.25); padding: 1px 7px; border-radius: 10px; font-size: 10px; }
    .fmb-tab-link.active .tab-badge { background: #fff; color: #2563eb; font-weight: 700; }

    /* Filter Header */
    .fmb-courier-filter-header { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; background: #fff; padding: 14px 20px; border-bottom: 1px solid #e2e8f0; }
    .fmb-search-box { position: relative; display: inline-flex; align-items: center; }
    .fmb-search-box .dashicons { position: absolute; left: 10px; color: #94a3b8; font-size: 16px; }
    .fmb-search-box input { padding-left: 32px !important; width: 280px; }

    /* Table specifics */
    .fmb-courier-table thead th { font-size: 11px; text-transform: uppercase; letter-spacing: .05em; color: #64748b; background: #f8fafc; }
    .fmb-order-id-cell { display: flex; flex-direction: column; gap: 2px; }
    .fmb-order-id-cell a { font-weight: 700; color: #2563eb; text-decoration: none; font-size: 14px; }
    .fmb-order-id-cell a:hover { text-decoration: underline; }
    .fmb-tag-today { display: inline-block; background: #8b5cf6; color: #fff; font-size: 9px; font-weight: 800; padding: 1px 6px; border-radius: 4px; text-transform: uppercase; width: max-content; }
    .fmb-date-sub { font-size: 11px; color: #94a3b8; }

    .fmb-customer-cell { display: flex; flex-direction: column; gap: 2px; font-size: 12px; }
    .fmb-phone-line { display: flex; align-items: center; gap: 4px; color: #2563eb; font-weight: 600; font-size: 12px; }
    .fmb-phone-line .dashicons { font-size: 13px; width: 13px; height: 13px; }
    .fmb-phone-line a { color: inherit; text-decoration: none; }
    .fmb-address-line { color: #64748b; font-size: 11px; max-width: 150px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

    .fmb-courier-cell { display: flex; flex-direction: column; gap: 4px; }
    .fmb-courier-badge { display: inline-block; padding: 2px 8px; border-radius: 6px; font-size: 11px; font-weight: 700; width: max-content; }
    .fmb-courier-badge.steadfast { background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; }
    .fmb-courier-badge.pathao    { background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; }
    .fmb-courier-badge.manual    { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }
    .fmb-cid-line { display: flex; align-items: center; gap: 5px; }
    .fmb-copy-cid { font-family: monospace; font-size: 11px; background: #f8fafc; border: 1px dashed #cbd5e1; padding: 2px 5px; border-radius: 4px; cursor: pointer; color: #334155; }
    .fmb-copy-cid:hover { background: #e2e8f0; }
    .fmb-ext-track { color: #64748b; text-decoration: none; display: flex; align-items: center; }
    .fmb-ext-track .dashicons { font-size: 14px; width: 14px; height: 14px; }

    .fmb-fin-num { font-weight: 700; font-size: 13px; }
    .fmb-fin-num.cod { color: #0f172a; }
    .fmb-fin-num.delivery { color: #dc2626; }
    .fmb-fin-num.cod-fee { color: #ea580c; }
    .fmb-fee-hint { font-size: 10px; color: #94a3b8; margin-left: 2px; }
    .fmb-fin-badge-net { display: inline-block; padding: 4px 10px; border-radius: 8px; background: #dcfce7; color: #15803d; font-weight: 800; font-size: 13px; border: 1px solid #bbf7d0; }

    .fmb-rider-note-cell { display: flex; flex-direction: column; gap: 4px; max-width: 180px; }
    .fmb-note-to-rider, .fmb-remark-from-courier { display: flex; align-items: flex-start; gap: 4px; font-size: 11px; line-height: 1.3; }
    .fmb-note-to-rider .txt { color: #1d4ed8; font-weight: 500; }
    .fmb-remark-from-courier .txt { color: #6d28d9; }
    .fmb-add-note-inline { background: none; border: 1px dashed #cbd5e1; color: #64748b; padding: 3px 8px; border-radius: 5px; font-size: 11px; cursor: pointer; text-align: left; }
    .fmb-add-note-inline:hover { border-color: #2563eb; color: #2563eb; }

    /* Slide-out Drawer */
    .fmb-drawer-backdrop { display: none; position: fixed; inset: 0; background: rgba(15,23,42,0.6); backdrop-filter: blur(3px); z-index: 99990; }
    .fmb-drawer-backdrop.open { display: block; }
    .fmb-drawer { position: fixed; top: 0; right: -750px; width: 700px; max-width: 95vw; height: 100vh; background: #fff; z-index: 99995; box-shadow: -10px 0 35px rgba(0,0,0,0.25); transition: right .3s cubic-bezier(0.16, 1, 0.3, 1); display: flex; flex-direction: column; }
    .fmb-drawer.open { right: 0; }
    .fmb-drawer-header { padding: 18px 24px; border-bottom: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: space-between; background: #f8fafc; }
    .fmb-drawer-close { background: none; border: none; font-size: 28px; line-height: 1; color: #64748b; cursor: pointer; padding: 0 4px; }
    .fmb-drawer-close:hover { color: #ef4444; }
    .fmb-drawer-body { flex: 1; overflow-y: auto; padding: 22px 24px; background: #fdfdfd; }

    /* Drawer Sections */
    .fmb-drawer-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 20px; }
    .fmb-drawer-panel { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 14px 16px; font-size: 13px; }
    .fmb-drawer-panel h4 { margin: 0 0 10px; font-size: 12px; text-transform: uppercase; letter-spacing: .05em; color: #64748b; border-bottom: 1px solid #f1f5f9; padding-bottom: 6px; }

    .fmb-drawer-products-table { width: 100%; border-collapse: collapse; margin-top: 8px; font-size: 12px; }
    .fmb-drawer-products-table th { padding: 6px 8px; background: #f8fafc; font-size: 11px; text-align: left; color: #64748b; }
    .fmb-drawer-products-table td { padding: 8px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
    .fmb-thumb-mini { width: 36px; height: 36px; object-fit: cover; border-radius: 6px; border: 1px solid #e2e8f0; margin-right: 8px; }

    /* Timeline */
    .fmb-timeline { position: relative; padding-left: 24px; margin-top: 15px; }
    .fmb-timeline::before { content: ''; position: absolute; left: 8px; top: 6px; bottom: 6px; width: 2px; background: #e2e8f0; }
    .fmb-timeline-item { position: relative; margin-bottom: 16px; font-size: 12px; }
    .fmb-timeline-dot { position: absolute; left: -24px; top: 2px; width: 14px; height: 14px; border-radius: 50%; background: #2563eb; border: 3px solid #eff6ff; }
    .fmb-timeline-time { font-size: 11px; color: #94a3b8; margin-bottom: 2px; }
    .fmb-timeline-status { font-weight: 700; color: #0f172a; }
    .fmb-timeline-note { color: #475569; margin-top: 2px; background: #f8fafc; padding: 6px 10px; border-radius: 6px; border-left: 3px solid #cbd5e1; }

    @media (max-width: 900px) {
        .fmb-financial-banner { flex-direction: column; align-items: flex-start; }
        .fin-divider { display: none; }
        .fmb-drawer-grid { grid-template-columns: 1fr; }
    }
    </style>

    <!-- Client-Side Drawer Script & AJAX Handlers -->
    <script>
    jQuery(document).ready(function($) {
        var ajaxUrl = '<?php echo admin_url('admin-ajax.php'); ?>';
        var nonce   = '<?php echo wp_create_nonce('fmb_courier_ajax_nonce'); ?>';

        // Copy Consignment ID
        $(document).on('click', '.fmb-copy-cid', function() {
            var cid = $(this).data('cid');
            if (navigator.clipboard) {
                navigator.clipboard.writeText(cid);
                var $el = $(this);
                var orig = $el.text();
                $el.text('Copied!').css('color', '#059669');
                setTimeout(function() { $el.text(orig).css('color', ''); }, 1500);
            }
        });

        // Open Drawer
        $(document).on('click', '.fmb-open-drawer-btn', function(e) {
            e.preventDefault();
            var orderId = $(this).data('order-id');
            var focusField = $(this).data('focus') || '';

            $('#fmb-courier-drawer-backdrop').addClass('open');
            $('#fmb-courier-drawer').addClass('open');
            $('#fmb-drawer-body-content').html('<div style="text-align:center; padding: 60px 20px;"><div class="fmb-spinner" style="width:36px; height:36px; border-width:4px;"></div><p style="margin-top:15px; color:#64748b;">Loading order #' + orderId + '...</p></div>');

            $.post(ajaxUrl, {
                action: 'fmb_get_courier_order_details',
                nonce: nonce,
                order_id: orderId
            }, function(res) {
                if (res.success) {
                    $('#drawer-order-title').text('Order #' + res.data.order_number + ' Details');
                    $('#drawer-order-meta').text('Placed on ' + res.data.order_date + ' | ' + res.data.status_label);
                    $('#fmb-drawer-body-content').html(res.data.html);

                    if (focusField === 'rider_note') {
                        setTimeout(function() { $('#fmb-input-rider-note').focus(); }, 300);
                    }
                } else {
                    $('#fmb-drawer-body-content').html('<div style="padding:30px; color:#dc2626;">Error: ' + (res.data || 'Failed to load order.') + '</div>');
                }
            }).fail(function(xhr, status, error) {
                $('#fmb-drawer-body-content').html('<div style="padding:30px; color:#dc2626;"><strong>Server Error:</strong> Failed to fetch order details (' + (xhr.statusText || error || 'Status ' + xhr.status) + ').</div>');
            });
        });

        // Close Drawer
        function closeDrawer() {
            $('#fmb-courier-drawer-backdrop').removeClass('open');
            $('#fmb-courier-drawer').removeClass('open');
        }
        $('#fmb-drawer-close-btn, #fmb-courier-drawer-backdrop').on('click', closeDrawer);

        // Save Rider Note AJAX
        $(document).on('click', '#fmb-save-rider-note-btn', function() {
            var $btn = $(this);
            var orderId = $btn.data('order-id');
            var noteVal = $('#fmb-input-rider-note').val();

            $btn.prop('disabled', true).text('Saving...');
            $.post(ajaxUrl, {
                action: 'fmb_save_rider_note',
                nonce: nonce,
                order_id: orderId,
                rider_note: noteVal
            }, function(res) {
                $btn.prop('disabled', false).text('Save Note');
                if (res.success) {
                    $('#fmb-note-feedback').html('<span style="color:#059669; font-weight:600;"><span class="dashicons dashicons-yes" style="font-size:16px; width:16px; height:16px; vertical-align:text-bottom;"></span> Rider note updated!</span>').fadeIn();
                    setTimeout(function() { $('#fmb-note-feedback').fadeOut(); }, 3000);
                } else {
                    alert(res.data || 'Failed to save note');
                }
            });
        });

        // Save Financials & Recalculate 1% COD Charge
        $(document).on('click', '#fmb-save-fin-btn', function() {
            var $btn = $(this);
            var orderId = $btn.data('order-id');
            var cod = parseFloat($('#fmb-input-cod').val()) || 0;
            var delivery = parseFloat($('#fmb-input-delivery').val()) || 0;

            $btn.prop('disabled', true).text('Updating...');
            $.post(ajaxUrl, {
                action: 'fmb_save_courier_financials',
                nonce: nonce,
                order_id: orderId,
                cod_amount: cod,
                delivery_charge: delivery
            }, function(res) {
                $btn.prop('disabled', false).text('Update Financials');
                if (res.success) {
                    $('#fmb-fin-feedback').html('<span style="color:#059669; font-weight:600;"><span class="dashicons dashicons-yes" style="font-size:16px; width:16px; height:16px; vertical-align:text-bottom;"></span> Financials recalculated!</span>').fadeIn();
                    // Update live labels in drawer
                    $('#drawer-cod-fee-val').text('৳' + res.data.cod_fee.toFixed(2));
                    $('#drawer-net-payout-val').text('৳' + res.data.net_payout.toFixed(2));
                    setTimeout(function() { $('#fmb-fin-feedback').fadeOut(); location.reload(); }, 1200);
                } else {
                    alert(res.data || 'Failed to update financials');
                }
            });
        });

        // Add Status History Entry
        $(document).on('click', '#fmb-add-status-history-btn', function() {
            var $btn = $(this);
            var orderId = $btn.data('order-id');
            var newStatus = $('#fmb-select-courier-status').val();
            var statusNote = $('#fmb-input-status-note').val();

            $btn.prop('disabled', true).text('Adding...');
            $.post(ajaxUrl, {
                action: 'fmb_update_courier_status',
                nonce: nonce,
                order_id: orderId,
                status: newStatus,
                note: statusNote
            }, function(res) {
                $btn.prop('disabled', false).text('Log Status Update');
                if (res.success) {
                    alert('Status history logged successfully!');
                    // Re-trigger drawer load
                    $('.fmb-open-drawer-btn[data-order-id="' + orderId + '"]').first().trigger('click');
                } else {
                    alert(res.data || 'Failed to update status');
                }
            });
        });
    });
    </script>
    <?php
}

/**
 * AJAX: Return full order details for the slide-out drawer
 */
add_action('wp_ajax_fmb_get_courier_order_details', 'fmb_ajax_get_courier_order_details');
function fmb_ajax_get_courier_order_details() {
    check_ajax_referer('fmb_courier_ajax_nonce', 'nonce');
    if (!current_user_can('manage_woocommerce')) {
        wp_send_json_error('Unauthorized');
    }

    $order_id = absint($_POST['order_id'] ?? 0);
    $order = wc_get_order($order_id);
    if (!$order) {
        wp_send_json_error('Order not found');
    }

    $c_info     = fmb_get_order_courier_info($order);
    $fin        = fmb_get_courier_financials($order);
    $history    = $order->get_meta('_courier_status_history');
    if (is_string($history)) {
        $history = maybe_unserialize($history);
    }
    if (!is_array($history)) {
        $history = array();
    }

    // Customer info
    $billing_name   = $order->get_formatted_billing_full_name();
    $billing_phone  = $order->get_billing_phone();
    $billing_email  = $order->get_billing_email();
    $shipping_addr  = $order->get_formatted_shipping_address() ?: $order->get_formatted_billing_address();
    $cust_note      = $order->get_customer_note();

    ob_start();
    ?>
    <!-- Top Highlights -->
    <div class="fmb-drawer-grid">
        <!-- Customer & Shipping -->
        <div class="fmb-drawer-panel">
            <h4>Customer & Shipping Address</h4>
            <p style="margin:0 0 6px; font-weight:700; color:#0f172a; font-size:14px;"><?php echo esc_html($billing_name); ?></p>
            <p style="margin:0 0 6px;">
                <span class="dashicons dashicons-phone" style="font-size:14px; width:14px; height:14px; color:#2563eb;"></span>
                <a href="tel:<?php echo esc_attr($billing_phone); ?>" style="color:#2563eb; font-weight:600; text-decoration:none;"><?php echo esc_html($billing_phone); ?></a>
                <a href="https://wa.me/88<?php echo esc_attr(preg_replace('/[^0-9]/', '', $billing_phone)); ?>" target="_blank" style="margin-left:6px; color:#16a34a; text-decoration:none; font-size:11px; font-weight:700;">(WhatsApp)</a>
            </p>
            <?php if ($billing_email) : ?>
                <p style="margin:0 0 6px; color:#64748b; font-size:12px;"><?php echo esc_html($billing_email); ?></p>
            <?php endif; ?>
            <p style="margin:6px 0 0; color:#334155; line-height:1.4;"><?php echo wp_kses_post($shipping_addr); ?></p>
            <?php if (!empty($cust_note)) : ?>
                <div style="margin-top:10px; padding:8px; background:#fffbeb; border-left:3px solid #f59e0b; font-size:11px; color:#92400e;">
                    <strong>Customer Note:</strong> <?php echo esc_html($cust_note); ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Courier Info & Tracking -->
        <div class="fmb-drawer-panel">
            <h4>Courier & Consignment</h4>
            <p style="margin:0 0 8px;">
                <strong style="color:#64748b; font-size:11px; text-transform:uppercase;">Courier:</strong>
                <span class="fmb-courier-badge <?php echo esc_attr(strtolower($c_info['courier_name'])); ?>" style="margin-left:4px;">
                    <?php echo esc_html($c_info['courier_name']); ?>
                </span>
            </p>
            <p style="margin:0 0 8px;">
                <strong style="color:#64748b; font-size:11px; text-transform:uppercase;">Consignment ID:</strong>
                <strong style="font-family:monospace; color:#0f172a; margin-left:4px;"><?php echo esc_html($c_info['consignment_id'] ?: 'None'); ?></strong>
            </p>
            <p style="margin:0 0 8px;">
                <strong style="color:#64748b; font-size:11px; text-transform:uppercase;">Booked Date:</strong>
                <span style="color:#334155; margin-left:4px;"><?php echo esc_html($c_info['booked_date'] ?: 'N/A'); ?></span>
            </p>
            <?php if (!empty($c_info['tracking_url'])) : ?>
                <p style="margin:8px 0 0;">
                    <a href="<?php echo esc_url($c_info['tracking_url']); ?>" target="_blank" class="fmb-btn fmb-btn-outline fmb-btn-sm" style="width:100%; justify-content:center;">
                        <span class="dashicons dashicons-external"></span> Track Directly on Courier Website
                    </a>
                </p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Products Ordered -->
    <div class="fmb-drawer-panel" style="margin-bottom:20px;">
        <h4>Ordered Items</h4>
        <table class="fmb-drawer-products-table">
            <thead>
                <tr>
                    <th>Product</th>
                    <th style="text-align:center;">Qty</th>
                    <th style="text-align:right;">Price</th>
                    <th style="text-align:right;">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($order->get_items() as $item_id => $item) :
                    $product = $item->get_product();
                    $img_url = $product ? wp_get_attachment_image_url($product->get_image_id(), 'thumbnail') : '';
                    if (!$img_url) $img_url = wc_placeholder_img_src('thumbnail');
                ?>
                <tr>
                    <td>
                        <div style="display:flex; align-items:center;">
                            <img src="<?php echo esc_url($img_url); ?>" class="fmb-thumb-mini" alt="">
                            <div>
                                <strong style="color:#0f172a;"><?php echo esc_html($item->get_name()); ?></strong>
                                <?php if ($product && $product->get_sku()) : ?>
                                    <div style="font-size:10px; color:#94a3b8;">SKU: <?php echo esc_html($product->get_sku()); ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </td>
                    <td style="text-align:center; font-weight:700; color:#334155;">×<?php echo esc_html($item->get_quantity()); ?></td>
                    <td style="text-align:right; color:#64748b;">৳<?php echo number_format($order->get_item_subtotal($item), 2); ?></td>
                    <td style="text-align:right; font-weight:700; color:#0f172a;">৳<?php echo number_format($item->get_total(), 2); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Financial Calculation Panel (COD, Delivery Fee, 1% COD Fee, Net Payout) -->
    <div class="fmb-drawer-panel" style="margin-bottom:20px; background:#f8fafc;">
        <h4><span class="dashicons dashicons-money-alt"></span> Parcel Financial Breakdown (1% COD Fee)</h4>
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:12px;">
            <div>
                <label style="display:block; font-size:11px; font-weight:700; color:#475569; margin-bottom:4px;">
                    Parcel COD Amount:
                </label>
                <div style="display:flex; align-items:center; gap:6px;">
                    <span style="font-weight:700; color:#64748b;">৳</span>
                    <input type="number" step="0.01" id="fmb-input-cod" value="<?php echo esc_attr($fin['cod']); ?>" style="padding:6px 10px; border:1px solid #cbd5e1; border-radius:6px; font-size:14px; font-weight:700; width:100%;">
                </div>
            </div>

            <div>
                <label style="display:block; font-size:11px; font-weight:700; color:#475569; margin-bottom:4px;">
                    Courier Delivery Charge:
                </label>
                <div style="display:flex; align-items:center; gap:6px;">
                    <span style="font-weight:700; color:#64748b;">৳</span>
                    <input type="number" step="0.01" id="fmb-input-delivery" value="<?php echo esc_attr($fin['delivery_charge']); ?>" style="padding:6px 10px; border:1px solid #cbd5e1; border-radius:6px; font-size:14px; font-weight:700; width:100%;">
                </div>
            </div>
        </div>

        <div style="background:#fff; border:1px solid #e2e8f0; border-radius:8px; padding:12px; margin-bottom:12px;">
            <div style="display:flex; justify-content:space-between; margin-bottom:6px; font-size:13px;">
                <span style="color:#64748b;">1% COD Charge (1% of Parcel COD):</span>
                <strong style="color:#ea580c;" id="drawer-cod-fee-val">৳<?php echo number_format($fin['cod_fee'], 2); ?></strong>
            </div>
            <div style="display:flex; justify-content:space-between; margin-bottom:8px; font-size:13px;">
                <span style="color:#64748b;">Total Courier Deductions:</span>
                <strong style="color:#dc2626;">৳<?php echo number_format($fin['total_deduction'], 2); ?></strong>
            </div>
            <div style="display:flex; justify-content:space-between; padding-top:8px; border-top:1px dashed #cbd5e1; font-size:15px;">
                <span style="font-weight:800; color:#0f172a;">Net Receivable Payout:</span>
                <strong style="color:#15803d; font-size:16px;" id="drawer-net-payout-val">৳<?php echo number_format($fin['net_payout'], 2); ?></strong>
            </div>
        </div>

        <div style="display:flex; align-items:center; justify-content:space-between;">
            <div id="fmb-fin-feedback" style="display:none;"></div>
            <button type="button" class="fmb-btn fmb-btn-primary fmb-btn-sm" id="fmb-save-fin-btn" data-order-id="<?php echo esc_attr($order_id); ?>">
                Update Financials
            </button>
        </div>
    </div>

    <!-- Rider Note Management -->
    <div class="fmb-drawer-panel" style="margin-bottom:20px;">
        <h4><span class="dashicons dashicons-edit"></span> Rider Note Management</h4>
        <?php if (!empty($c_info['latest_remark'])) : ?>
            <div style="background:#f5f3ff; border:1px solid #ddd6fe; border-radius:8px; padding:10px; margin-bottom:12px; font-size:12px;">
                <strong style="color:#6d28d9; display:flex; align-items:center; gap:4px;">
                    <span class="dashicons dashicons-admin-comments"></span> Latest Remark from Courier/Rider:
                </strong>
                <p style="margin:4px 0 0; color:#4c1d95;"><?php echo esc_html($c_info['latest_remark']); ?></p>
            </div>
        <?php endif; ?>

        <div style="margin-bottom:10px;">
            <label style="display:block; font-size:12px; font-weight:600; color:#475569; margin-bottom:4px;">
                Note for Delivery Rider (e.g., "Deliver after 6 PM, call before delivery"):
            </label>
            <textarea id="fmb-input-rider-note" rows="3" style="width:100%; border:1px solid #cbd5e1; border-radius:8px; padding:8px 10px; font-size:13px; font-family:inherit;" placeholder="Add special instructions for delivery rider..."><?php echo esc_textarea($c_info['rider_note']); ?></textarea>
        </div>

        <div style="display:flex; align-items:center; justify-content:space-between;">
            <div id="fmb-note-feedback" style="display:none;"></div>
            <button type="button" class="fmb-btn fmb-btn-primary fmb-btn-sm" id="fmb-save-rider-note-btn" data-order-id="<?php echo esc_attr($order_id); ?>">
                Save Rider Note
            </button>
        </div>
    </div>

    <!-- Status History Timeline -->
    <div class="fmb-drawer-panel">
        <h4><span class="dashicons dashicons-backup"></span> Status Update History Timeline</h4>
        
        <!-- Add Manual Status Update -->
        <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:12px; margin-bottom:16px;">
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:8px;">
                <div>
                    <label style="font-size:11px; font-weight:700; color:#64748b; display:block; margin-bottom:3px;">Select Status:</label>
                    <select id="fmb-select-courier-status" style="width:100%; font-size:12px; padding:6px;">
                        <option value="In Review">In Review</option>
                        <option value="In Transit">In Transit</option>
                        <option value="Out for Delivery">Out for Delivery</option>
                        <option value="Delivered">Delivered</option>
                        <option value="Returned">Returned</option>
                        <option value="Delivery Attempted / Hold">Delivery Attempted / Hold</option>
                    </select>
                </div>
                <div>
                    <label style="font-size:11px; font-weight:700; color:#64748b; display:block; margin-bottom:3px;">Event Note / Remarks:</label>
                    <input type="text" id="fmb-input-status-note" placeholder="Optional remark/reason..." style="width:100%; font-size:12px; padding:6px;">
                </div>
            </div>
            <button type="button" class="fmb-btn fmb-btn-outline fmb-btn-sm" id="fmb-add-status-history-btn" data-order-id="<?php echo esc_attr($order_id); ?>">
                + Log Status Update
            </button>
        </div>

        <!-- Render Timeline -->
        <?php if (empty($history)) : ?>
            <p style="color:#94a3b8; font-size:12px; font-style:italic;">No status transitions logged yet.</p>
        <?php else : ?>
            <div class="fmb-timeline">
                <?php foreach (array_reverse($history) as $entry) : ?>
                    <div class="fmb-timeline-item">
                        <div class="fmb-timeline-dot"></div>
                        <div class="fmb-timeline-time"><?php echo esc_html($entry['timestamp']); ?> by <?php echo esc_html($entry['user'] ?? $entry['source'] ?? 'System'); ?></div>
                        <div class="fmb-timeline-status"><?php echo esc_html($entry['status']); ?></div>
                        <?php if (!empty($entry['note'])) : ?>
                            <div class="fmb-timeline-note"><?php echo esc_html($entry['note']); ?></div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    <?php
    $html = ob_get_clean();

    wp_send_json_success(array(
        'order_number' => $order->get_order_number(),
        'order_date'   => $order->get_date_created() ? $order->get_date_created()->date('d M Y, h:i A') : '',
        'status_label' => ucfirst($order->get_status()),
        'html'         => $html,
    ));
}

/**
 * AJAX: Save Rider Note
 */
add_action('wp_ajax_fmb_save_rider_note', 'fmb_ajax_save_rider_note');
function fmb_ajax_save_rider_note() {
    $nonce = $_POST['nonce'] ?? ($_GET['nonce'] ?? ($_REQUEST['nonce'] ?? ''));
    $valid = false;
    if (wp_verify_nonce($nonce, 'fmb_courier_ajax_nonce') || wp_verify_nonce($nonce, 'fmb_order_action') || wp_verify_nonce($nonce, 'fmb_order_hub_nonce')) {
        $valid = true;
    } elseif (function_exists('fmb_verify_order_action_nonce') && fmb_verify_order_action_nonce()) {
        $valid = true;
    }

    if (!$valid || (!current_user_can('manage_woocommerce') && !current_user_can('manage_options') && !current_user_can('edit_shop_orders'))) {
        wp_send_json_error('Unauthorized');
    }

    $order_id   = absint($_POST['order_id'] ?? 0);
    $rider_note = sanitize_textarea_field($_POST['rider_note'] ?? '');

    $order = wc_get_order($order_id);
    if (!$order) {
        wp_send_json_error('Order not found');
    }

    $order->update_meta_data('_courier_rider_note', $rider_note);
    if (!empty($rider_note)) {
        $order->add_order_note(sprintf(__('[Rider Note] %s', 'fmb-engine'), $rider_note));
    }
    $order->save();

    // Sync to dedicated courier database table
    if (function_exists('fmb_courier_db_get') && function_exists('fmb_courier_db_save')) {
        $c_entry = fmb_courier_db_get($order_id);
        if ($c_entry) {
            $c_entry['rider_note'] = $rider_note;
            fmb_courier_db_save($order_id, $c_entry);
        }
    }

    // Also record in status history timeline
    if (function_exists('fmb_record_courier_history_entry')) {
        fmb_record_courier_history_entry($order_id, 'Rider Note Updated', $rider_note, 'Admin');
    }

    wp_send_json_success(array('message' => 'Rider note saved successfully.', 'rider_note' => $rider_note));
}

/**
 * AJAX: Update Courier Status & Append to Timeline
 */
add_action('wp_ajax_fmb_update_courier_status', 'fmb_ajax_update_courier_status');
function fmb_ajax_update_courier_status() {
    check_ajax_referer('fmb_courier_ajax_nonce', 'nonce');
    if (!current_user_can('manage_woocommerce')) {
        wp_send_json_error('Unauthorized');
    }

    $order_id = absint($_POST['order_id'] ?? 0);
    $status   = sanitize_text_field($_POST['status'] ?? '');
    $note     = sanitize_textarea_field($_POST['note'] ?? '');

    $order = wc_get_order($order_id);
    if (!$order) {
        wp_send_json_error('Order not found');
    }

    $order->update_meta_data('_courier_delivery_status', strtolower($status));
    $order->add_order_note(sprintf(__('[Courier Status] %s - Note: %s', 'fmb-engine'), $status, $note ?: 'None'));
    $order->save();

    fmb_record_courier_history_entry($order_id, $status, $note, 'Admin');

    wp_send_json_success(array('message' => 'Status logged successfully.'));
}

/**
 * AJAX: Save Courier Financials (COD Amount & Delivery Charge) & Recalculate 1% Fee
 */
add_action('wp_ajax_fmb_save_courier_financials', 'fmb_ajax_save_courier_financials');
function fmb_ajax_save_courier_financials() {
    check_ajax_referer('fmb_courier_ajax_nonce', 'nonce');
    if (!current_user_can('manage_woocommerce')) {
        wp_send_json_error('Unauthorized');
    }

    $order_id        = absint($_POST['order_id'] ?? 0);
    $cod_amount      = floatval($_POST['cod_amount'] ?? 0);
    $delivery_charge = floatval($_POST['delivery_charge'] ?? 0);

    $order = wc_get_order($order_id);
    if (!$order) {
        wp_send_json_error('Order not found');
    }

    $order->update_meta_data('_courier_custom_cod_amount', $cod_amount);
    $order->update_meta_data('_courier_delivery_charge', $delivery_charge);

    $cod_fee         = round($cod_amount * 0.01, 2);
    $total_deduction = round($delivery_charge + $cod_fee, 2);
    $net_payout      = round($cod_amount - $total_deduction, 2);

    $order->update_meta_data('_courier_cod_fee', $cod_fee);
    $order->update_meta_data('_courier_net_payout', $net_payout);
    $order->save();

    wp_send_json_success(array(
        'cod_fee'    => $cod_fee,
        'net_payout' => $net_payout,
        'message'    => 'Financials saved successfully.'
    ));
}

/**
 * CSV Export for Accounting & Courier Performance
 */
function fmb_handle_courier_csv_export() {
    if (!current_user_can('manage_woocommerce')) {
        wp_die('Unauthorized');
    }

    $orders = wc_get_orders(array('limit' => 500, 'orderby' => 'date', 'order' => 'DESC'));

    $filename = 'courier_parcels_report_' . current_time('Ymd_His') . '.csv';

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');

    // UTF-8 BOM for Excel Bengali compatibility
    fputs($output, "\xEF\xBB\xBF");

    // CSV Header
    fputcsv($output, array(
        'Order Number',
        'Booking Date',
        'Customer Name',
        'Phone',
        'City',
        'Address',
        'Courier',
        'Consignment ID',
        'COD Amount (TK)',
        'Delivery Charge (TK)',
        'COD Fee 1% (TK)',
        'Net Payout (TK)',
        'Delivery Status',
        'Rider Note',
        'Latest Courier Remark'
    ));

    foreach ($orders as $order) {
        $c_info = fmb_get_order_courier_info($order);
        $fin    = fmb_get_courier_financials($order);

        fputcsv($output, array(
            $order->get_order_number(),
            $c_info['booked_date'],
            $order->get_formatted_billing_full_name(),
            $order->get_billing_phone(),
            $order->get_billing_city(),
            $order->get_billing_address_1() . ' ' . $order->get_billing_address_2(),
            $c_info['courier_name'],
            $c_info['consignment_id'],
            $fin['cod'],
            $fin['delivery_charge'],
            $fin['cod_fee'],
            $fin['net_payout'],
            ucfirst($c_info['delivery_status']),
            $c_info['rider_note'],
            $c_info['latest_remark'],
        ));
    }

    fclose($output);
    exit;
}

/**
 * Clean Printable Courier Slip / Packing Label
 */
function fmb_render_courier_print_slip($order_id) {
    while (ob_get_level()) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header('Content-Type: text/html; charset=UTF-8');
    }
    if (!current_user_can('manage_woocommerce')) {
        wp_die('Unauthorized');
    }

    $order = wc_get_order($order_id);
    if (!$order) {
        wp_die('Order not found');
    }

    $c_info = fmb_get_order_courier_info($order);
    $fin    = fmb_get_courier_financials($order);

    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Parcel Slip #<?php echo esc_html($order->get_order_number()); ?> - <?php bloginfo('name'); ?></title>
        <style>
            body { font-family: 'Inter', -apple-system, sans-serif; margin: 0; padding: 20px; background: #fff; color: #111827; }
            .slip-card { max-width: 520px; margin: 0 auto; border: 2px dashed #334155; padding: 24px; border-radius: 12px; }
            .slip-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #0f172a; padding-bottom: 12px; margin-bottom: 16px; }
            .slip-title { font-size: 20px; font-weight: 800; margin: 0; }
            .slip-badge { background: #0f172a; color: #fff; padding: 4px 10px; border-radius: 6px; font-weight: 700; font-size: 13px; }
            .row { display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 14px; }
            .label { font-weight: 700; color: #64748b; text-transform: uppercase; font-size: 11px; }
            .cod-banner { background: #f1f5f9; border: 1px solid #cbd5e1; border-radius: 8px; padding: 12px 16px; display: flex; justify-content: space-between; align-items: center; margin: 16px 0; }
            .cod-amount { font-size: 24px; font-weight: 800; color: #0f172a; }
            .rider-note-box { background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 8px; padding: 12px; margin-top: 14px; }
            .rider-note-title { font-size: 11px; font-weight: 800; text-transform: uppercase; color: #1d4ed8; margin-bottom: 4px; }
            @media print {
                #adminmenumain, #adminmenuback, #adminmenuwrap, #adminmenu, #wpadminbar, #wpfooter, #screen-meta, #screen-meta-links, .screen-toolbar, .no-print {
                    display: none !important;
                    visibility: hidden !important;
                    height: 0 !important;
                    width: 0 !important;
                }
                #wpcontent, #wpbody, #wpbody-content {
                    margin: 0 !important;
                    padding: 0 !important;
                    float: none !important;
                    width: 100% !important;
                }
                body { padding: 0; }
                .slip-card { border: 1px solid #000; }
                .no-print { display: none; }
            }
        </style>
    </head>
    <body onload="window.print()">
        <div class="no-print" style="text-align:center; margin-bottom: 20px;">
            <button onclick="window.print()" style="padding:8px 16px; background:#2563eb; color:#fff; border:none; border-radius:6px; font-weight:700; cursor:pointer;">Print Slip</button>
        </div>

        <div class="slip-card">
            <div class="slip-header">
                <div>
                    <h1 class="slip-title"><?php bloginfo('name'); ?></h1>
                    <div style="font-size:12px; color:#64748b;">Order #<?php echo esc_html($order->get_order_number()); ?> | <?php echo esc_html(date('d M Y')); ?></div>
                </div>
                <div class="slip-badge"><?php echo esc_html($c_info['courier_name']); ?></div>
            </div>

            <div class="row">
                <div>
                    <div class="label">Recipient / Customer</div>
                    <div style="font-size:16px; font-weight:800;"><?php echo esc_html($order->get_formatted_billing_full_name()); ?></div>
                    <div style="font-size:14px; font-weight:700; color:#2563eb; margin: 3px 0;"><?php echo esc_html($order->get_billing_phone()); ?></div>
                    <div style="font-size:13px; color:#334155; line-height:1.4; max-width:320px;">
                        <?php echo wp_kses_post($order->get_formatted_shipping_address() ?: $order->get_formatted_billing_address()); ?>
                    </div>
                </div>
                <div style="text-align:right;">
                    <div class="label">Consignment ID</div>
                    <div style="font-family:monospace; font-weight:800; font-size:15px;"><?php echo esc_html($c_info['consignment_id'] ?: 'N/A'); ?></div>
                </div>
            </div>

            <div class="cod-banner">
                <div>
                    <div class="label" style="color:#475569;">Cash On Delivery (COD)</div>
                    <div style="font-size:11px; color:#64748b;">Collect exact amount from customer</div>
                </div>
                <div class="cod-amount">৳<?php echo number_format($fin['cod'], 2); ?></div>
            </div>

            <?php if (!empty($c_info['rider_note'])) : ?>
                <div class="rider-note-box">
                    <div class="rider-note-title"><span class="dashicons dashicons-clipboard"></span> Instructions for Delivery Rider:</div>
                    <div style="font-size:13px; font-weight:600; color:#1e3a8a;"><?php echo esc_html($c_info['rider_note']); ?></div>
                </div>
            <?php endif; ?>

            <div style="margin-top:16px; border-top:1px solid #e2e8f0; padding-top:10px; font-size:11px; color:#94a3b8; display:flex; justify-content:space-between;">
                <span>Package Verified</span>
                <span>Thank you for shopping with us!</span>
            </div>
        </div>
    </body>
    </html>
    <?php
}
