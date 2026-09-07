<?php
/**
 * FMB Store — Custom Admin Panel
 * Registers top-level menu + all sub-pages
 */

if (!defined('ABSPATH')) exit;

// =============================================
// 1. Register Admin Menu
// =============================================
function fmb_register_admin_menu() {

    // Top-level menu
    add_menu_page(
        'FMB Store',
        'FMB Store',
        'manage_woocommerce',
        'fmb-store',
        'fmb_admin_overview_page',
        'dashicons-store',
        56
    );

    // Sub: Overview
    add_submenu_page(
        'fmb-store',
        'Overview',
        'Overview',
        'manage_woocommerce',
        'fmb-store',
        'fmb_admin_overview_page'
    );

    // Sub: Order Manager
    add_submenu_page(
        'fmb-store',
        'Order Manager',
        'Order Manager',
        'manage_woocommerce',
        'fmb-order-manager',
        'fmb_admin_order_manager_page'
    );

    // Sub: Courier Dashboard
    add_submenu_page(
        'fmb-store',
        'Courier Dashboard',
        'Courier Dashboard',
        'manage_woocommerce',
        'fmb-courier-dashboard',
        'fmb_admin_courier_dashboard_page'
    );

    // Sub: Product Purchase & Stock Management
    add_submenu_page(
        'fmb-store',
        'Purchase & Stock',
        'Purchase & Stock',
        'manage_woocommerce',
        'fmb-purchase-stock',
        'fmb_admin_purchase_stock_page'
    );

    // Sub: Expenses
    add_submenu_page(
        'fmb-store',
        'Expenses',
        'Expenses',
        'manage_woocommerce',
        'fmb-expenses',
        'fmb_admin_expenses_page'
    );

    // Sub: Combo Offers
    add_submenu_page(
        'fmb-store',
        'Combos',
        'Combos',
        'manage_woocommerce',
        'fmb-goto-combos',
        'fmb_admin_redirect_combos'
    );

    // Sub: Sales Funnel / Pages
    add_submenu_page(
        'fmb-store',
        'Sales Pages / Funnels',
        'Funnels',
        'manage_woocommerce',
        'fmb-goto-funnels',
        'fmb_admin_redirect_funnels'
    );

    // Sub: Contact Messages
    add_submenu_page(
        'fmb-store',
        'Contact Messages',
        'Contact Messages',
        'manage_woocommerce',
        'fmb-contact-messages',
        'fmb_admin_contact_messages_page'
    );

    // Sub: WC Products (external redirect handled via JS)
    add_submenu_page(
        'fmb-store',
        'Products',
        'Products',
        'manage_woocommerce',
        'fmb-goto-products',
        'fmb_admin_redirect_products'
    );

    // Sub: Theme Customizer
    add_submenu_page(
        'fmb-store',
        'Theme Settings',
        'Theme Settings',
        'manage_options',
        'fmb-goto-customizer',
        'fmb_admin_redirect_customizer'
    );


    // Pages quick links
    $pages_list = array(
        'fmb-page-about'    => array('About Us',        'about'),
        'fmb-page-contact'  => array('Contact Us',      'contact'),
        'fmb-page-track'    => array('Track Order',     'track-order'),
        'fmb-page-privacy'  => array('Privacy Policy',  'privacy-policy'),
    );
    foreach ($pages_list as $slug => $info) {
        add_submenu_page(
            'fmb-store',
            $info[0],
            $info[0],
            'manage_pages',
            $slug,
            'fmb_admin_goto_page'
        );
    }
}
add_action('admin_menu', 'fmb_register_admin_menu');

// =============================================
// 2. Redirect Callbacks
// =============================================
function fmb_admin_redirect_combos() {
    wp_redirect(admin_url('edit.php?post_type=fmb_combo_offer'));
    exit;
}
function fmb_admin_redirect_funnels() {
    wp_redirect(admin_url('edit.php?post_type=fmb_sales_page'));
    exit;
}
function fmb_admin_redirect_products() {
    wp_redirect(admin_url('edit.php?post_type=product'));
    exit;
}
function fmb_admin_redirect_customizer() {
    wp_redirect(admin_url('customize.php'));
    exit;
}
function fmb_admin_goto_page() {
    $map = array(
        'fmb-page-about'   => 'about',
        'fmb-page-contact' => 'contact',
        'fmb-page-track'   => 'track-order',
        'fmb-page-privacy' => 'privacy-policy',
    );
    $page_key = sanitize_text_field($_GET['page'] ?? '');
    $slug     = $map[$page_key] ?? '';
    if ($slug) {
        $page = get_page_by_path($slug);
        if ($page) {
            wp_redirect(get_edit_post_link($page->ID, 'url'));
            exit;
        }
    }
    wp_redirect(admin_url('edit.php?post_type=page'));
    exit;
}

// =============================================
// 3. Admin CSS (themed to match frontend)
// =============================================
function fmb_admin_enqueue_assets($hook) {
    // admin_enqueue_scripts passes the hook — check both hook and page param
    $fmb_pages = array('fmb-store', 'fmb-order-manager', 'fmb-contact-messages', 'fmb-courier-dashboard');
    $current_page = sanitize_text_field($_GET['page'] ?? '');
    $is_fmb_page  = in_array($current_page, $fmb_pages)
                    || strpos($hook, 'fmb') !== false;
    if (!$is_fmb_page) return;

    $primary   = get_theme_mod('fmb_primary_color', '#3B82F6');
    $secondary = get_theme_mod('fmb_secondary_color', '#1E3A5F');

    wp_register_style('fmb-admin-css', false);
    wp_enqueue_style('fmb-admin-css');
    wp_add_inline_style('fmb-admin-css', "
    :root { --fmb-primary: {$primary}; --fmb-secondary: {$secondary}; --fmb-radius: 10px; }
    .fmb-admin-wrap { margin: 20px 20px 20px 0; font-family: 'Inter', -apple-system, sans-serif; }
    .fmb-stat-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 16px; margin-bottom: 24px; }
    .fmb-stat-card { background: #fff; border: 1px solid #e5e7eb; border-radius: var(--fmb-radius); padding: 18px 20px; display: flex; flex-direction: column; gap: 6px; transition: box-shadow .2s; }
    .fmb-stat-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,.08); }
    .fmb-stat-card .num { font-size: 2rem; font-weight: 800; line-height: 1; color: var(--fmb-primary); }
    .fmb-stat-card .lbl { font-size: .8rem; color: #6b7280; font-weight: 500; }
    .fmb-card { background: #fff; border: 1px solid #e5e7eb; border-radius: var(--fmb-radius); overflow: hidden; }
    .fmb-card-header { padding: 16px 20px; border-bottom: 1px solid #f3f4f6; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px; background: #f9fafb; }
    .fmb-card-header h2 { margin: 0; font-size: 1rem; font-weight: 700; color: #111827; }
    .fmb-filter-bar { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; }
    .fmb-filter-bar input[type=text], .fmb-filter-bar select { padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 13px; outline: none; background: #fff; }
    .fmb-filter-bar input[type=text]:focus, .fmb-filter-bar select:focus { border-color: var(--fmb-primary); }
    .fmb-status-tabs { display: flex; flex-wrap: wrap; gap: 6px; padding: 12px 20px; border-bottom: 1px solid #f3f4f6; }
    .fmb-status-tab { padding: 5px 14px; border-radius: 20px; font-size: 12px; font-weight: 600; cursor: pointer; border: 1px solid #e5e7eb; background: #fff; text-decoration: none; color: #6b7280; transition: all .15s; }
    .fmb-status-tab:hover, .fmb-status-tab.active { background: var(--fmb-primary); color: #fff !important; border-color: var(--fmb-primary); }
    .fmb-status-tab .cnt { background: rgba(255,255,255,.3); padding: 1px 6px; border-radius: 10px; font-size: 10px; margin-left: 4px; }
    .fmb-table { width: 100%; border-collapse: collapse; font-size: 13px; }
    .fmb-table thead th { padding: 10px 16px; text-align: left; font-weight: 600; font-size: 12px; color: #6b7280; text-transform: uppercase; letter-spacing: .05em; background: #f9fafb; border-bottom: 1px solid #e5e7eb; white-space: nowrap; }
    .fmb-table tbody tr { border-bottom: 1px solid #f3f4f6; transition: background .1s; }
    .fmb-table tbody tr:hover { background: #f9fafb; }
    .fmb-table tbody td { padding: 12px 16px; vertical-align: middle; color: #374151; }
    .fmb-table tbody tr:last-child { border-bottom: none; }
    .fmb-badge { display: inline-flex; align-items: center; gap: 4px; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; white-space: nowrap; }
    .fmb-badge.pending    { background: #fef3c7; color: #92400e; }
    .fmb-badge.processing { background: #dbeafe; color: #1e40af; }
    .fmb-badge.completed  { background: #d1fae5; color: #065f46; }
    .fmb-badge.cancelled  { background: #fee2e2; color: #991b1b; }
    .fmb-badge.on-hold    { background: #f3e8ff; color: #6b21a8; }
    .fmb-badge.refunded   { background: #e0e7ff; color: #3730a3; }
    .fmb-badge.failed     { background: #fecaca; color: #7f1d1d; }
    .fmb-actions { display: flex; align-items: center; gap: 6px; }
    .fmb-btn { display: inline-flex; align-items: center; gap: 4px; padding: 6px 12px; border-radius: 7px; font-size: 12px; font-weight: 600; cursor: pointer; border: none; transition: all .15s; text-decoration: none; }
    .fmb-btn-sm { padding: 4px 9px; font-size: 11px; border-radius: 6px; }
    .fmb-btn-primary { background: var(--fmb-primary); color: #fff !important; }
    .fmb-btn-primary:hover { filter: brightness(1.1); color: #fff !important; }
    .fmb-btn-outline { background: #fff; color: #374151 !important; border: 1px solid #d1d5db; }
    .fmb-btn-outline:hover { border-color: var(--fmb-primary); color: var(--fmb-primary) !important; background: #fff; }
    .fmb-btn-danger { background: #fee2e2; color: #991b1b !important; }
    .fmb-btn-danger:hover { background: #fca5a5; color: #7f1d1d !important; }
    .fmb-btn-success { background: #d1fae5; color: #065f46 !important; }
    .fmb-modal-bg { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.55); backdrop-filter: blur(4px); z-index: 99999; align-items: center; justify-content: center; padding: 20px; }
    .fmb-modal-bg.open { display: flex !important; }
    .fmb-modal { background: #fff; border-radius: 16px; max-width: 600px; width: 100%; max-height: 90vh; overflow-y: auto; box-shadow: 0 25px 50px rgba(0,0,0,.25); animation: fmbSlideUp .2s ease; }
    @keyframes fmbSlideUp { from { transform: translateY(24px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
    .fmb-modal-header { padding: 18px 24px; border-bottom: 1px solid #f3f4f6; display: flex; align-items: center; justify-content: space-between; }
    .fmb-modal-header h3 { margin: 0; font-size: 1.1rem; font-weight: 700; color: #111827; }
    .fmb-modal-close { background: #f3f4f6; border: none; width: 32px; height: 32px; border-radius: 8px; cursor: pointer; font-size: 16px; color: #6b7280; line-height: 1; }
    .fmb-modal-close:hover { background: #fee2e2; color: #dc2626; }
    .fmb-modal-body { padding: 20px 24px; }
    .fmb-modal-footer { padding: 14px 24px; border-top: 1px solid #f3f4f6; display: flex; gap: 10px; justify-content: flex-end; background: #f9fafb; border-radius: 0 0 16px 16px; }
    .fmb-form-group { margin-bottom: 16px; }
    .fmb-form-group label { display: block; font-size: 11px; font-weight: 700; color: #6b7280; margin-bottom: 6px; text-transform: uppercase; letter-spacing: .05em; }
    .fmb-form-group input, .fmb-form-group textarea, .fmb-form-group select { width: 100%; padding: 10px 14px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px; outline: none; font-family: inherit; box-sizing: border-box; }
    .fmb-form-group input:focus, .fmb-form-group textarea:focus, .fmb-form-group select:focus { border-color: var(--fmb-primary); box-shadow: 0 0 0 3px rgba(59,130,246,.15); }
    .fmb-form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
    .fmb-pagination { display: flex; align-items: center; gap: 6px; padding: 14px 20px; border-top: 1px solid #f3f4f6; justify-content: flex-end; }
    .fmb-page-btn { width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; border-radius: 7px; border: 1px solid #e5e7eb; font-size: 13px; font-weight: 600; color: #374151; text-decoration: none; cursor: pointer; background: #fff; }
    .fmb-page-btn.active { background: var(--fmb-primary); color: #fff !important; border-color: var(--fmb-primary); }
    .fmb-page-btn:hover:not(.active) { border-color: var(--fmb-primary); color: var(--fmb-primary) !important; }
    .fmb-checkbox { width: 15px; height: 15px; accent-color: var(--fmb-primary); cursor: pointer; }
    .fmb-notice { padding: 12px 16px; border-radius: 8px; font-size: 13px; font-weight: 500; margin-bottom: 16px; display: none; }
    .fmb-notice.success { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; display: block; }
    .fmb-notice.error   { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; display: block; }
    .fmb-spinner { width: 18px; height: 18px; border: 3px solid #e5e7eb; border-top-color: var(--fmb-primary); border-radius: 50%; animation: fmbSpin .7s linear infinite; display: inline-block; vertical-align: middle; }
    @keyframes fmbSpin { to { transform: rotate(360deg); } }
    .fmb-status-select { padding: 5px 8px; border: 1px solid #e5e7eb; border-radius: 6px; font-size: 12px; background: #fff; cursor: pointer; outline: none; }
    .fmb-status-select:focus { border-color: var(--fmb-primary); }
    .fmb-bulk-bar { display: none; padding: 10px 20px; background: #eff6ff; border-bottom: 1px solid #bfdbfe; align-items: center; gap: 12px; }
    .fmb-bulk-bar.visible { display: flex !important; }
    .fmb-bulk-bar span { font-size: 13px; color: #1e40af; font-weight: 600; }
    @media (max-width: 800px) {
        .fmb-form-row { grid-template-columns: 1fr; }
        .fmb-stat-grid { grid-template-columns: 1fr 1fr; }
    }
    ");
}
add_action('admin_enqueue_scripts', 'fmb_admin_enqueue_assets');

// =============================================
// 4. Overview Dashboard Page
// =============================================

function fmb_admin_overview_page() {
    if (!class_exists('WooCommerce')) {
        echo '<div class="wrap"><h1>FMB Store</h1><p>WooCommerce is required.</p></div>';
        return;
    }

    // 1. Order Status Counts
    $statuses = array('pending', 'processing', 'completed', 'cancelled', 'on-hold', 'refunded');
    $counts   = array();
    $total    = 0;
    foreach ($statuses as $s) {
        $counts[$s] = wc_orders_count($s);
        $total     += $counts[$s];
    }

    // 2. Today's Revenue & Orders
    $today_revenue      = 0.0;
    $today_orders_count = 0;
    try {
        $today_orders = wc_get_orders(array(
            'date_created' => current_time('Y-m-d'),
            'limit'        => -1,
        ));
        if (is_array($today_orders)) {
            $today_orders_count = count($today_orders);
            foreach ($today_orders as $o) {
                if (is_object($o) && method_exists($o, 'get_total')) {
                    if (in_array($o->get_status(), array('processing', 'completed', 'ads-shipping', 'ads-delivered'))) {
                        $today_revenue += (float) $o->get_total();
                    }
                }
            }
        }
    } catch (\Throwable $e) {}

    // 3. Products & Catalog
    $product_counts = wp_count_posts('product');
    $total_products = isset($product_counts->publish) ? (int)$product_counts->publish : 0;
    $product_cats   = wp_count_terms(array('taxonomy' => 'product_cat', 'hide_empty' => false));

    // 4. Courier Statistics (recent pool)
    $recent_orders = wc_get_orders(array(
        'limit'   => 50,
        'orderby' => 'date',
        'order'   => 'DESC',
    ));
    $courier_booked    = 0;
    $courier_intransit = 0;
    $courier_delivered = 0;
    $courier_returned  = 0;
    foreach ($recent_orders as $o) {
        if (function_exists('fmb_get_order_courier_info')) {
            $c_info = fmb_get_order_courier_info($o);
            $st     = $c_info['delivery_status'];
            if (!empty($c_info['consignment_id']) || in_array($o->get_status(), array('ads-shipping', 'ads-intransit', 'ads-delivered', 'ads-returned'))) {
                $courier_booked++;
                if (in_array($st, array('delivered', 'success', 'completed'))) {
                    $courier_delivered++;
                } elseif (in_array($st, array('returned', 'return', 'cancelled', 'failed', 'pickup cancel', 'pickup_cancel'))) {
                    $courier_returned++;
                } else {
                    $courier_intransit++;
                }
            }
        }
    }

    // 5. Incomplete Orders count
    $incomplete_count = 0;
    global $wpdb;
    $table_incomplete = $wpdb->prefix . 'fmb_incomplete_orders';
    if ($wpdb->get_var("SHOW TABLES LIKE '{$table_incomplete}'") === $table_incomplete) {
        $incomplete_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table_incomplete} WHERE status = 'unrecovered' OR status = 'pending'");
    }

    $recent_display = array_slice($recent_orders, 0, 8);
    ?>
    <div class="fmb-admin-wrap fmb-overview-wrap">

        <!-- Executive Header -->
        <div class="fmb-overview-header">
            <div>
                <div class="fmb-header-badge-row">
                    <span class="fmb-pill-badge primary"><span class="dashicons dashicons-admin-plugins"></span> Theme v<?php echo esc_html(defined('FMB_THEME_VERSION') ? FMB_THEME_VERSION : '1.3.4'); ?></span>
                    <span class="fmb-pill-badge success"><span class="dashicons dashicons-cart"></span> WooCommerce Active</span>
                    <span class="fmb-pill-badge info"><span class="dashicons dashicons-shield"></span> FMB Commerce Engine</span>
                </div>
                <h1 class="fmb-overview-title">
                    <span class="dashicons dashicons-store"></span> FMB Store — Executive Overview
                </h1>
                <p class="fmb-overview-subtitle">
                    Real-time management dashboard for store sales, order processing, courier fulfillment, and engine analytics.
                </p>
            </div>
            <div class="fmb-header-actions">
                <a href="<?php echo admin_url('post-new.php?post_type=shop_order'); ?>" class="fmb-btn fmb-btn-primary">
                    <span class="dashicons dashicons-plus-alt2"></span> New Order
                </a>
                <a href="<?php echo admin_url('admin.php?page=fmb-order-manager'); ?>" class="fmb-btn fmb-btn-outline">
                    <span class="dashicons dashicons-cart"></span> Order Manager
                </a>
                <a href="<?php echo admin_url('admin.php?page=fmb-courier-dashboard'); ?>" class="fmb-btn fmb-btn-outline">
                    <span class="dashicons dashicons-car"></span> Courier Dashboard
                </a>
                <a href="<?php echo admin_url('customize.php'); ?>" class="fmb-btn fmb-btn-outline" title="Live Frontend Theme Customizer">
                    <span class="dashicons dashicons-admin-appearance"></span> Theme Settings
                </a>
            </div>
        </div>

        <!-- Metric KPI Cards -->
        <div class="fmb-kpi-grid">
            <div class="fmb-kpi-card">
                <div class="kpi-icon green"><span class="dashicons dashicons-money-alt"></span></div>
                <div class="kpi-content">
                    <span class="kpi-val"><?php echo wc_price($today_revenue); ?></span>
                    <span class="kpi-lbl">Today's Revenue</span>
                    <span class="kpi-sub">Paid & processing orders</span>
                </div>
            </div>

            <div class="fmb-kpi-card highlighted-card">
                <div class="kpi-icon blue"><span class="dashicons dashicons-calendar-alt"></span></div>
                <div class="kpi-content">
                    <span class="kpi-val"><?php echo number_format($today_orders_count); ?></span>
                    <span class="kpi-lbl">Today's Orders</span>
                    <span class="kpi-sub">New orders created today</span>
                </div>
            </div>

            <div class="fmb-kpi-card">
                <div class="kpi-icon orange"><span class="dashicons dashicons-clock"></span></div>
                <div class="kpi-content">
                    <span class="kpi-val"><?php echo number_format($counts['processing']); ?></span>
                    <span class="kpi-lbl">Processing Orders</span>
                    <span class="kpi-sub">Needs packaging & dispatch</span>
                </div>
            </div>

            <div class="fmb-kpi-card">
                <div class="kpi-icon sky"><span class="dashicons dashicons-location"></span></div>
                <div class="kpi-content">
                    <span class="kpi-val"><?php echo number_format($courier_intransit); ?></span>
                    <span class="kpi-lbl">In-Transit / Courier</span>
                    <span class="kpi-sub">Active with delivery riders</span>
                </div>
            </div>

            <div class="fmb-kpi-card">
                <div class="kpi-icon teal"><span class="dashicons dashicons-yes-alt"></span></div>
                <div class="kpi-content">
                    <span class="kpi-val"><?php echo number_format($counts['completed']); ?></span>
                    <span class="kpi-lbl">Completed Orders</span>
                    <span class="kpi-sub">Successfully fulfilled</span>
                </div>
            </div>

            <div class="fmb-kpi-card">
                <div class="kpi-icon slate"><span class="dashicons dashicons-archive"></span></div>
                <div class="kpi-content">
                    <span class="kpi-val"><?php echo number_format($total); ?></span>
                    <span class="kpi-lbl">Total Store Orders</span>
                    <span class="kpi-sub">Lifetime order database</span>
                </div>
            </div>
        </div>

        <!-- Operational Hubs Grid (4 Main Functional Modules) -->
        <div class="fmb-hub-grid">

            <!-- Hub 1: Order Manager -->
            <div class="fmb-hub-card">
                <div class="hub-header">
                    <div class="hub-icon-wrap blue">
                        <span class="dashicons dashicons-cart"></span>
                    </div>
                    <div>
                        <h3 class="hub-title">Order Manager Hub</h3>
                        <span class="hub-sub">Workflow & Fulfillment</span>
                    </div>
                </div>
                <p class="hub-desc">
                    Search customer records, filter by status, inspect item totals, update delivery addresses, and apply bulk status changes.
                </p>
                <div class="hub-metrics-row">
                    <span class="hub-pill pending">Pending: <?php echo $counts['pending']; ?></span>
                    <span class="hub-pill processing">Processing: <?php echo $counts['processing']; ?></span>
                    <span class="hub-pill on-hold">On Hold: <?php echo $counts['on-hold']; ?></span>
                    <span class="hub-pill cancelled">Cancelled: <?php echo $counts['cancelled']; ?></span>
                </div>
                <div class="hub-footer">
                    <a href="<?php echo admin_url('admin.php?page=fmb-order-manager'); ?>" class="fmb-btn fmb-btn-primary hub-action-btn">
                        <span class="dashicons dashicons-arrow-right-alt2"></span> Launch Order Manager
                    </a>
                </div>
            </div>

            <!-- Hub 2: Courier Logistics -->
            <div class="fmb-hub-card">
                <div class="hub-header">
                    <div class="hub-icon-wrap sky">
                        <span class="dashicons dashicons-car"></span>
                    </div>
                    <div>
                        <h3 class="hub-title">Courier Logistics Dashboard</h3>
                        <span class="hub-sub">Steadfast & Parcel Sync</span>
                    </div>
                </div>
                <p class="hub-desc">
                    Manage courier bookings, live tracking sync, rider instructions, 1% COD fee reconciliation, and 1-click printable parcel slips.
                </p>
                <div class="hub-metrics-row">
                    <span class="hub-pill info">Booked: <?php echo $courier_booked; ?></span>
                    <span class="hub-pill processing">With Rider: <?php echo $courier_intransit; ?></span>
                    <span class="hub-pill success">Delivered: <?php echo $courier_delivered; ?></span>
                    <span class="hub-pill danger">Returned: <?php echo $courier_returned; ?></span>
                </div>
                <div class="hub-footer">
                    <a href="<?php echo admin_url('admin.php?page=fmb-courier-dashboard'); ?>" class="fmb-btn fmb-btn-primary hub-action-btn">
                        <span class="dashicons dashicons-arrow-right-alt2"></span> Launch Courier Dashboard
                    </a>
                </div>
            </div>

            <!-- Hub 3: FMB Commerce Engine -->
            <div class="fmb-hub-card">
                <div class="hub-header">
                    <div class="hub-icon-wrap purple">
                        <span class="dashicons dashicons-shield"></span>
                    </div>
                    <div>
                        <h3 class="hub-title">Commerce Engine & Anti-Fraud</h3>
                        <span class="hub-sub">Conversion & Security</span>
                    </div>
                </div>
                <p class="hub-desc">
                    Single checkout flow, incomplete checkout recovery, smart OTP verification, phone fraud prevention, and server-side Facebook CAPI tracking.
                </p>
                <div class="hub-metrics-row">
                    <span class="hub-pill info">Incomplete: <?php echo $incomplete_count; ?></span>
                    <span class="hub-pill success">Fraud Blocker: Active</span>
                    <span class="hub-pill purple">CAPI Tracking: Ready</span>
                </div>
                <div class="hub-footer">
                    <a href="<?php echo admin_url('admin.php?page=fmb-engine'); ?>" class="fmb-btn fmb-btn-outline hub-action-btn">
                        <span class="dashicons dashicons-arrow-right-alt2"></span> Engine Dashboard
                    </a>
                </div>
            </div>

            <!-- Hub 4: Catalog & Theme Customizer -->
            <div class="fmb-hub-card">
                <div class="hub-header">
                    <div class="hub-icon-wrap amber">
                        <span class="dashicons dashicons-products"></span>
                    </div>
                    <div>
                        <h3 class="hub-title">Catalog & Theme Settings</h3>
                        <span class="hub-sub">Storefront & Customization</span>
                    </div>
                </div>
                <p class="hub-desc">
                    Organize WooCommerce products, product categories, stock inventory alerts, sales landing page variations, and live theme customization.
                </p>
                <div class="hub-metrics-row">
                    <span class="hub-pill info">Products: <?php echo $total_products; ?></span>
                    <span class="hub-pill success">Categories: <?php echo is_wp_error($product_cats) ? '0' : $product_cats; ?></span>
                    <span class="hub-pill purple">Customizer: Live</span>
                </div>
                <div class="hub-footer">
                    <a href="<?php echo admin_url('edit.php?post_type=product'); ?>" class="fmb-btn fmb-btn-outline hub-action-btn">
                        <span class="dashicons dashicons-arrow-right-alt2"></span> Manage Products
                    </a>
                </div>
            </div>

        </div>

        <!-- Recent Orders Live Table -->
        <div class="fmb-card fmb-recent-card">
            <div class="fmb-card-header">
                <div style="display:flex; align-items:center; gap:8px;">
                    <span class="dashicons dashicons-list-view" style="font-size:18px; width:18px; height:18px; color:var(--fmb-primary);"></span>
                    <h2>Recent Customer Orders</h2>
                </div>
                <div style="display:flex; align-items:center; gap:10px;">
                    <a href="<?php echo admin_url('admin.php?page=fmb-order-manager'); ?>" class="fmb-btn fmb-btn-outline fmb-btn-sm">
                        View All Orders in Order Manager &rarr;
                    </a>
                </div>
            </div>

            <div class="fmb-table-responsive" style="overflow-x:auto;">
                <table class="fmb-table">
                    <thead>
                        <tr>
                            <th>Order & Date</th>
                            <th>Customer Info</th>
                            <th>Status</th>
                            <th>Courier / Consignment ID</th>
                            <th style="text-align:right;">Order Total</th>
                            <th style="text-align:right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recent_display)) : ?>
                            <tr>
                                <td colspan="6" style="text-align:center; padding: 40px; color:#64748b;">
                                    <span class="dashicons dashicons-cart" style="font-size:32px; width:32px; height:32px; color:#cbd5e1; margin-bottom:8px; display:block; margin: 0 auto 8px;"></span>
                                    No customer orders placed yet.
                                </td>
                            </tr>
                        <?php else : ?>
                            <?php foreach ($recent_display as $order) :
                                $oid        = $order->get_id();
                                $order_date = $order->get_date_created() ? $order->get_date_created()->date('d M Y, h:i A') : '';
                                $c_info     = function_exists('fmb_get_order_courier_info') ? fmb_get_order_courier_info($order) : array('courier_name' => 'N/A', 'consignment_id' => '');
                                $status     = $order->get_status();
                            ?>
                            <tr>
                                <td>
                                    <div style="font-weight:700; color:#0f172a;">
                                        <a href="<?php echo esc_url(add_query_arg(array('page' => 'fmb-order-manager', 'view' => $oid), admin_url('admin.php'))); ?>" style="text-decoration:none; color:inherit;">
                                            #<?php echo esc_html($order->get_order_number()); ?>
                                        </a>
                                    </div>
                                    <div style="font-size:11px; color:#64748b; margin-top:2px;">
                                        <?php echo esc_html($order_date); ?>
                                    </div>
                                </td>

                                <td>
                                    <strong style="color:#1e293b;"><?php echo esc_html($order->get_formatted_billing_full_name()); ?></strong>
                                    <?php if ($order->get_billing_phone()) : ?>
                                        <div style="font-size:12px; color:#64748b; display:flex; align-items:center; gap:4px; margin-top:2px;">
                                            <span class="dashicons dashicons-phone" style="font-size:13px; width:13px; height:13px;"></span>
                                            <a href="tel:<?php echo esc_attr($order->get_billing_phone()); ?>" style="text-decoration:none; color:#2563eb;">
                                                <?php echo esc_html($order->get_billing_phone()); ?>
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <?php if (function_exists('fmb_status_badge')) : ?>
                                        <?php echo fmb_status_badge($status); ?>
                                    <?php else : ?>
                                        <span class="fmb-badge <?php echo esc_attr($status); ?>"><?php echo esc_html(ucfirst($status)); ?></span>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <?php if (!empty($c_info['consignment_id'])) : ?>
                                        <div style="display:flex; align-items:center; gap:6px;">
                                            <span class="fmb-pill-badge info" style="font-size:11px;"><?php echo esc_html($c_info['courier_name']); ?></span>
                                            <code style="font-size:11px; padding:2px 6px; background:#f1f5f9; border-radius:4px; font-weight:700;">
                                                <?php echo esc_html($c_info['consignment_id']); ?>
                                            </code>
                                        </div>
                                    <?php else : ?>
                                        <span style="color:#94a3b8; font-size:12px;">Not Booked</span>
                                    <?php endif; ?>
                                </td>

                                <td style="text-align:right; font-weight:800; font-size:14px; color:#0f172a;">
                                    <?php echo wc_price($order->get_total()); ?>
                                </td>

                                <td style="text-align:right;">
                                    <div class="fmb-actions" style="justify-content:flex-end;">
                                        <a href="<?php echo esc_url(add_query_arg(array('page' => 'fmb-order-manager', 'view' => $oid), admin_url('admin.php'))); ?>" class="fmb-btn fmb-btn-primary fmb-btn-sm" title="View in Order Manager">
                                            <span class="dashicons dashicons-visibility"></span> View
                                        </a>
                                        <a href="<?php echo esc_url(add_query_arg(array('page' => 'fmb-courier-dashboard', 'print_slip' => $oid), admin_url('admin.php'))); ?>" target="_blank" class="fmb-btn fmb-btn-outline fmb-btn-sm" title="Print Courier Slip">
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
        </div>

        <!-- System Diagnostics Footer -->
        <div class="fmb-system-bar">
            <div class="sys-item">
                <span class="dashicons dashicons-admin-plugins"></span>
                <span><strong>Theme:</strong> FMB E-Com Store v<?php echo esc_html(defined('FMB_THEME_VERSION') ? FMB_THEME_VERSION : '1.3.4'); ?></span>
            </div>
            <div class="sys-divider">&bull;</div>
            <div class="sys-item">
                <span class="dashicons dashicons-cart"></span>
                <span><strong>WooCommerce:</strong> v<?php echo esc_html(defined('WC_VERSION') ? WC_VERSION : 'Unknown'); ?></span>
            </div>
            <div class="sys-divider">&bull;</div>
            <div class="sys-item">
                <span class="dashicons dashicons-wordpress"></span>
                <span><strong>WordPress:</strong> v<?php echo esc_html(get_bloginfo('version')); ?></span>
            </div>
            <div class="sys-divider">&bull;</div>
            <div class="sys-item">
                <span class="dashicons dashicons-admin-generic"></span>
                <span><strong>PHP:</strong> v<?php echo esc_html(PHP_VERSION); ?></span>
            </div>
            <div class="sys-divider">&bull;</div>
            <div class="sys-item">
                <span class="dashicons dashicons-car"></span>
                <span><strong>Courier Integration:</strong> Ready</span>
            </div>
        </div>

    </div>

    <!-- Inline Styles for Upgraded Overview Dashboard -->
    <style>
    .fmb-overview-wrap { margin-top: 15px; }
    .fmb-overview-header { display: flex; align-items: flex-start; justify-content: space-between; flex-wrap: wrap; gap: 15px; margin-bottom: 24px; }
    .fmb-header-badge-row { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 6px; }
    .fmb-pill-badge { display: inline-flex; align-items: center; gap: 4px; padding: 2px 8px; border-radius: 20px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.4px; }
    .fmb-pill-badge .dashicons { font-size: 13px; width: 13px; height: 13px; }
    .fmb-pill-badge.primary { background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; }
    .fmb-pill-badge.success { background: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0; }
    .fmb-pill-badge.info    { background: #f5f3ff; color: #6d28d9; border: 1px solid #ddd6fe; }
    .fmb-pill-badge.warning { background: #fffbeb; color: #b45309; border: 1px solid #fde68a; }

    .fmb-overview-title { margin: 0; font-size: 1.6rem; font-weight: 800; color: #0f172a; display: flex; align-items: center; gap: 10px; }
    .fmb-overview-title .dashicons { color: var(--fmb-primary, #2563eb); font-size: 28px; width: 28px; height: 28px; }
    .fmb-overview-subtitle { margin: 4px 0 0; font-size: 13px; color: #64748b; }
    .fmb-header-actions { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }

    /* Modern KPI Cards */
    .fmb-kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 14px; margin-bottom: 24px; }
    .fmb-kpi-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px 18px; display: flex; align-items: center; gap: 14px; box-shadow: 0 1px 3px rgba(0,0,0,0.03); transition: transform 0.15s, box-shadow 0.15s; }
    .fmb-kpi-card:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(0,0,0,0.06); }
    .fmb-kpi-card.highlighted-card { border-color: #93c5fd; background: linear-gradient(180deg, #ffffff 0%, #f0f7ff 100%); }
    .fmb-kpi-card .kpi-icon { width: 44px; height: 44px; border-radius: 10px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
    .fmb-kpi-card .kpi-icon .dashicons { font-size: 22px; width: 22px; height: 22px; }
    .fmb-kpi-card .kpi-icon.green  { background: #dcfce7; color: #16a34a; }
    .fmb-kpi-card .kpi-icon.blue   { background: #dbeafe; color: #2563eb; }
    .fmb-kpi-card .kpi-icon.orange { background: #ffedd5; color: #ea580c; }
    .fmb-kpi-card .kpi-icon.sky    { background: #e0f2fe; color: #0284c7; }
    .fmb-kpi-card .kpi-icon.teal   { background: #ccfbf1; color: #0d9488; }
    .fmb-kpi-card .kpi-icon.slate  { background: #f1f5f9; color: #475569; }

    .fmb-kpi-card .kpi-content { display: flex; flex-direction: column; min-width: 0; }
    .fmb-kpi-card .kpi-val { font-size: 1.45rem; font-weight: 800; color: #0f172a; line-height: 1.2; }
    .fmb-kpi-card .kpi-lbl { font-size: 12px; font-weight: 700; color: #475569; margin-top: 2px; }
    .fmb-kpi-card .kpi-sub { font-size: 11px; color: #94a3b8; margin-top: 1px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

    /* Operational Hubs */
    .fmb-hub-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 16px; margin-bottom: 24px; }
    .fmb-hub-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; display: flex; flex-direction: column; justify-content: space-between; box-shadow: 0 1px 3px rgba(0,0,0,0.03); transition: border-color 0.2s, box-shadow 0.2s; }
    .fmb-hub-card:hover { border-color: #cbd5e1; box-shadow: 0 6px 18px rgba(0,0,0,0.05); }
    .fmb-hub-card .hub-header { display: flex; align-items: center; gap: 12px; margin-bottom: 12px; }
    .fmb-hub-card .hub-icon-wrap { width: 38px; height: 38px; border-radius: 8px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
    .fmb-hub-card .hub-icon-wrap .dashicons { font-size: 20px; width: 20px; height: 20px; }
    .fmb-hub-card .hub-icon-wrap.blue   { background: #eff6ff; color: #2563eb; }
    .fmb-hub-card .hub-icon-wrap.sky    { background: #f0f9ff; color: #0284c7; }
    .fmb-hub-card .hub-icon-wrap.purple { background: #faf5ff; color: #9333ea; }
    .fmb-hub-card .hub-icon-wrap.amber  { background: #fffbeb; color: #d97706; }

    .fmb-hub-card .hub-title { margin: 0; font-size: 15px; font-weight: 700; color: #0f172a; }
    .fmb-hub-card .hub-sub { font-size: 11px; color: #64748b; }
    .fmb-hub-card .hub-desc { margin: 0 0 14px; font-size: 12px; color: #475569; line-height: 1.5; flex-grow: 1; }
    .fmb-hub-card .hub-metrics-row { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 16px; }
    .fmb-hub-card .hub-pill { font-size: 10.5px; font-weight: 700; padding: 2px 7px; border-radius: 6px; }
    .fmb-hub-card .hub-pill.pending    { background: #fef3c7; color: #92400e; }
    .fmb-hub-card .hub-pill.processing { background: #dbeafe; color: #1e40af; }
    .fmb-hub-card .hub-pill.on-hold    { background: #f3e8ff; color: #6b21a8; }
    .fmb-hub-card .hub-pill.cancelled  { background: #fee2e2; color: #991b1b; }
    .fmb-hub-card .hub-pill.success    { background: #dcfce7; color: #166534; }
    .fmb-hub-card .hub-pill.info       { background: #f1f5f9; color: #334155; }
    .fmb-hub-card .hub-pill.purple     { background: #f5f3ff; color: #6d28d9; }
    .fmb-hub-card .hub-pill.danger     { background: #fee2e2; color: #b91c1c; }

    .fmb-hub-card .hub-footer { margin-top: auto; padding-top: 12px; border-top: 1px solid #f1f5f9; }
    .fmb-hub-card .hub-action-btn { width: 100%; justify-content: center; padding: 7px 12px; font-size: 12px; }

    /* Recent Orders Card */
    .fmb-recent-card { margin-bottom: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.03); }

    /* Diagnostics Footer */
    .fmb-system-bar { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 12px 18px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; font-size: 12px; color: #64748b; }
    .fmb-system-bar .sys-item { display: flex; align-items: center; gap: 6px; }
    .fmb-system-bar .sys-item .dashicons { font-size: 15px; width: 15px; height: 15px; color: #94a3b8; }
    .fmb-system-bar .sys-divider { color: #cbd5e1; }

    @media (max-width: 900px) {
        .fmb-overview-header { flex-direction: column; }
        .fmb-kpi-grid { grid-template-columns: 1fr 1fr; }
        .fmb-hub-grid { grid-template-columns: 1fr; }
        .fmb-system-bar { flex-direction: column; align-items: flex-start; }
        .fmb-system-bar .sys-divider { display: none; }
    }
    </style>
    <?php
}

// =============================================
// 5. Include sub-pages
// =============================================
require_once get_template_directory() . '/inc/admin-pages/order-manager.php';
require_once get_template_directory() . '/inc/admin-pages/contact-messages.php';

// =============================================
// 6. AJAX Handlers
// =============================================

// ── GET single order data ─────────────────────
add_action('wp_ajax_fmb_get_order', 'fmb_ajax_get_order');
function fmb_ajax_get_order() {
    if ((!check_ajax_referer('fmb_order_action', 'nonce', false) && !check_ajax_referer('fmb_order_hub_nonce', 'nonce', false)) || !current_user_can('manage_woocommerce')) {
        wp_send_json_error('Unauthorized'); return;
    }
    $id    = absint($_POST['order_id'] ?? 0);
    $order = wc_get_order($id);
    if (!$order) { wp_send_json_error('Order not found'); return; }

    // Build items HTML for view modal
    $items_html = '<table style="width:100%;border-collapse:collapse;font-size:13px;">';
    $items_html .= '<thead><tr style="background:#f8fafc;"><th style="padding:8px;text-align:left;border-bottom:1px solid #e2e8f0;font-size:11px;font-weight:700;color:#64748b;">PRODUCT</th><th style="padding:8px;text-align:center;border-bottom:1px solid #e2e8f0;font-size:11px;font-weight:700;color:#64748b;">QTY</th><th style="padding:8px;text-align:right;border-bottom:1px solid #e2e8f0;font-size:11px;font-weight:700;color:#64748b;">TOTAL</th></tr></thead><tbody>';
    foreach ($order->get_items() as $item) {
        $items_html .= '<tr style="border-bottom:1px solid #f1f5f9;">';
        $items_html .= '<td style="padding:10px 8px;font-weight:600;color:#0f172a;">' . esc_html($item->get_name()) . '</td>';
        $items_html .= '<td style="padding:10px 8px;text-align:center;">' . $item->get_quantity() . '</td>';
        $items_html .= '<td style="padding:10px 8px;text-align:right;font-weight:700;">' . wc_price($item->get_total()) . '</td>';
        $items_html .= '</tr>';
    }
    $items_html .= '</tbody></table>';

    $statuses   = function_exists('fmb_order_statuses') ? fmb_order_statuses() : array();
    $status     = $order->get_status();
    $status_lbl = $statuses[$status] ?? ucfirst($status);
    $date_str   = $order->get_date_created() ? $order->get_date_created()->date('d M Y, h:i A') : '—';

    $c_provider = $order->get_meta('_courier_provider') ?: ($order->get_meta('_fmb_courier_provider') ?: 'Not Booked');
    $c_id       = $order->get_meta('_courier_consignment_id') ?: ($order->get_meta('_fmb_tracking_code') ?: '');
    $rider_note = $order->get_meta('_courier_rider_note');
    $source     = $order->get_meta('_order_source') ?: 'Website';
    $paid       = (float)($order->get_meta('_paid_amount') ?: 0);
    $due        = max(0, (float)$order->get_total() - $paid);

    $view_html = '
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:18px;">
        <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:14px;">
            <p style="font-size:10px;color:#64748b;font-weight:800;text-transform:uppercase;margin:0 0 8px;letter-spacing:0.5px;">Customer Info</p>
            <p style="margin:4px 0;font-size:13px;font-weight:700;color:#0f172a;">' . esc_html($order->get_formatted_billing_full_name() ?: 'Guest Customer') . '</p>
            <p style="margin:4px 0;font-size:12.5px;"><strong>Phone:</strong> <a href="tel:' . esc_attr($order->get_billing_phone()) . '" style="color:#2563eb;text-decoration:none;font-weight:600;">' . esc_html($order->get_billing_phone()) . '</a></p>
            <p style="margin:4px 0;font-size:12px;color:#475569;"><strong>Address:</strong> ' . esc_html($order->get_billing_address_1() . ($order->get_billing_address_2() ? ', ' . $order->get_billing_address_2() : '') . ($order->get_billing_city() ? ', ' . $order->get_billing_city() : '')) . '</p>
            <p style="margin:4px 0;font-size:11px;color:#64748b;"><strong>Source:</strong> ' . esc_html($source) . '</p>
        </div>
        <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:14px;">
            <p style="font-size:10px;color:#64748b;font-weight:800;text-transform:uppercase;margin:0 0 8px;letter-spacing:0.5px;">Order & Courier</p>
            <p style="margin:4px 0;font-size:12.5px;"><strong>Status:</strong> <span style="display:inline-block;padding:2px 8px;border-radius:5px;font-size:11px;font-weight:800;background:#e0e7ff;color:#4338ca;">' . esc_html(strtoupper($status_lbl)) . '</span></p>
            <p style="margin:4px 0;font-size:12px;color:#475569;"><strong>Date:</strong> ' . esc_html($date_str) . '</p>
            <p style="margin:4px 0;font-size:12px;color:#475569;"><strong>Courier:</strong> <span style="font-weight:700;color:#0284c7;">' . esc_html(ucfirst($c_provider)) . '</span></p>
            ' . ($c_id ? '<p style="margin:4px 0;font-size:12px;color:#0f172a;"><strong>Tracking / CID:</strong> <code style="background:#fff;padding:2px 6px;border:1px solid #cbd5e1;border-radius:4px;font-weight:700;">' . esc_html($c_id) . '</code></p>' : '') . '
            <p style="margin:4px 0;font-size:12px;color:#475569;"><strong>Payment:</strong> ' . esc_html($order->get_payment_method_title() ?: 'Cash on Delivery') . '</p>
        </div>
    </div>';

    if ($rider_note) {
        $view_html .= '<div style="margin-bottom:16px;padding:10px 14px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;font-size:12px;color:#1e40af;"><strong>Rider Note:</strong> ' . esc_html($rider_note) . '</div>';
    }

    $view_html .= '<div style="margin-bottom:16px;border:1px solid #e2e8f0;border-radius:8px;overflow:hidden;">' . $items_html . '</div>
    <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:12px 16px;">
        <div style="display:flex;justify-content:space-between;padding:4px 0;font-size:13px;color:#475569;">
            <span>Subtotal</span><strong>' . wc_price($order->get_subtotal()) . '</strong>
        </div>
        <div style="display:flex;justify-content:space-between;padding:4px 0;font-size:13px;color:#475569;">
            <span>Delivery Charge</span><strong>' . wc_price($order->get_shipping_total()) . '</strong>
        </div>';

    if ($paid > 0) {
        $view_html .= '
        <div style="display:flex;justify-content:space-between;padding:4px 0;font-size:13px;color:#16a34a;">
            <span>Paid Amount</span><strong>- ' . wc_price($paid) . '</strong>
        </div>';
    }

    $view_html .= '
        <div style="display:flex;justify-content:space-between;padding-top:8px;margin-top:6px;border-top:1px dashed #cbd5e1;font-size:15px;font-weight:800;color:#0f172a;">
            <span>Due Amount (COD)</span><span style="color:#0f172a;font-size:18px;">' . wc_price($due) . '</span>
        </div>
    </div>';

    if ($order->get_customer_note()) {
        $view_html .= '<div style="margin-top:14px;padding:10px 14px;background:#fffbeb;border:1px solid #fde68a;border-radius:8px;font-size:12.5px;color:#92400e;"><strong>Customer Note:</strong> ' . esc_html($order->get_customer_note()) . '</div>';
    }

    wp_send_json_success(array(
        'html'                => $view_html,
        'date_created'        => $date_str,
        'billing_first_name'  => $order->get_billing_first_name(),
        'billing_last_name'   => $order->get_billing_last_name(),
        'billing_phone'       => $order->get_billing_phone(),
        'billing_address_1'   => $order->get_billing_address_1(),
        'status'              => $status,
        'customer_note'       => $order->get_customer_note(),
        'courier_provider'    => $c_provider,
        'consignment_id'      => $c_id,
        'due_amount'          => $due,
    ));
}

// ── UPDATE order ──────────────────────────────
add_action('wp_ajax_fmb_update_order', 'fmb_ajax_update_order');
function fmb_ajax_update_order() {
    if ((!check_ajax_referer('fmb_order_action', 'nonce', false) && !check_ajax_referer('fmb_order_hub_nonce', 'nonce', false)) || !current_user_can('manage_woocommerce')) {
        wp_send_json_error('Unauthorized'); return;
    }
    $id    = absint($_POST['order_id'] ?? 0);
    $order = wc_get_order($id);
    if (!$order) { wp_send_json_error('Order not found'); return; }

    if (!empty($_POST['billing_first_name'])) $order->set_billing_first_name(sanitize_text_field($_POST['billing_first_name']));
    if (!empty($_POST['billing_last_name']))  $order->set_billing_last_name(sanitize_text_field($_POST['billing_last_name']));
    if (!empty($_POST['billing_phone']))      $order->set_billing_phone(sanitize_text_field($_POST['billing_phone']));
    if (!empty($_POST['billing_address_1'])) $order->set_billing_address_1(sanitize_text_field($_POST['billing_address_1']));
    if (!empty($_POST['customer_note']))      $order->set_customer_note(sanitize_textarea_field($_POST['customer_note']));
    if (!empty($_POST['status'])) {
        $order->set_status('wc-' . sanitize_text_field($_POST['status']), 'Status updated via FMB Order Manager.', true);
    }
    $order->save();
    wp_send_json_success(array('message' => 'Order updated'));
}

// ── DELETE order ──────────────────────────────
add_action('wp_ajax_fmb_delete_order', 'fmb_ajax_delete_order');
function fmb_ajax_delete_order() {
    if ((!check_ajax_referer('fmb_order_action', 'nonce', false) && !check_ajax_referer('fmb_order_hub_nonce', 'nonce', false)) || !current_user_can('manage_woocommerce')) {
        wp_send_json_error('Unauthorized'); return;
    }
    $id    = absint($_POST['order_id'] ?? 0);
    $order = wc_get_order($id);
    if (!$order) { wp_send_json_error('Order not found'); return; }
    $order->delete(true); // force delete
    wp_send_json_success(array('message' => 'Deleted'));
}

// ── BULK action ───────────────────────────────
add_action('wp_ajax_fmb_bulk_action', 'fmb_ajax_bulk_action');
function fmb_ajax_bulk_action() {
    if ((!check_ajax_referer('fmb_order_action', 'nonce', false) && !check_ajax_referer('fmb_order_hub_nonce', 'nonce', false)) || !current_user_can('manage_woocommerce')) {
        wp_send_json_error('Unauthorized'); return;
    }
    $ids    = array_map('absint', (array)($_POST['ids'] ?? array()));
    $action = sanitize_text_field($_POST['bulk_action'] ?? '');
    $status = sanitize_text_field($_POST['status'] ?? '');
    $done   = 0;
    foreach ($ids as $id) {
        $order = wc_get_order($id);
        if (!$order) continue;
        if ($action === 'status' && $status) {
            $order->set_status('wc-' . $status, 'Bulk status update via FMB Order Manager.', true);
            $order->save();
            $done++;
        } elseif ($action === 'delete') {
            $order->delete(true);
            $done++;
        }
    }
    wp_send_json_success(array('done' => $done));
}

// ── DELETE contact message ──────────────────────────────
add_action('wp_ajax_fmb_delete_contact_msg', 'fmb_ajax_delete_contact_msg');
function fmb_ajax_delete_contact_msg() {
    if (!check_ajax_referer('fmb_contact_msg_action', 'nonce', false) || !current_user_can('manage_woocommerce')) {
        wp_send_json_error('Unauthorized'); return;
    }
    $id = absint($_POST['msg_id'] ?? 0);
    if (!$id) { wp_send_json_error('Invalid ID'); return; }
    wp_delete_post($id, true);
    wp_send_json_success(array('message' => 'Deleted'));
}


// =============================================
// Global Print Style: Guarantee WordPress admin menu & bar NEVER print
// =============================================
add_action('admin_head', 'fmb_admin_global_print_hide_menu');
function fmb_admin_global_print_hide_menu() {
    ?>
    <style>
    @media print {
        #adminmenumain,
        #adminmenuback,
        #adminmenuwrap,
        #adminmenu,
        #wpadminbar,
        #wpfooter,
        #screen-meta,
        #screen-meta-links,
        .notice,
        div.error,
        div.updated,
        .update-nag,
        .fmb-view-topbar-actions,
        .fmb-view-back-link,
        .fmb-floating-bulk-bar,
        .fmb-topbar-actions,
        .fmb-table-filters,
        .fmb-pagination-bar,
        .no-print {
            display: none !important;
            visibility: hidden !important;
            height: 0 !important;
            width: 0 !important;
            margin: 0 !important;
            padding: 0 !important;
            overflow: hidden !important;
        }
        #wpcontent,
        #wpbody,
        #wpbody-content {
            margin: 0 !important;
            padding: 0 !important;
            float: none !important;
            width: 100% !important;
            left: 0 !important;
        }
        html, body, body.wp-admin {
            background: #fff !important;
            color: #000 !important;
            margin: 0 !important;
            padding: 0 !important;
        }
    }
    </style>
    <?php
}