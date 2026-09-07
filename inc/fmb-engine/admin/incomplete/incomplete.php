<?php

namespace fmb_engine\Admin\Incomplete;
class Incomplete
{
    public function __construct()
    {
        add_action('admin_init', array( $this,  'fmb_engine_handle_settings_form' ) );
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));


        add_action('wp_ajax_update_incomplete_status', array($this, 'handle_update_incomplete_status'));


        add_action('wp_ajax_delete_incomplete_orders', array($this, 'handle_delete_incomplete_orders'));

        add_action('wp_ajax_bulk_mark_incomplete_orders', array($this, 'handle_bulk_mark_incomplete_orders'));

        add_action('wp_ajax_bulk_delete_incomplete_orders', array($this, 'handle_bulk_delete_incomplete_orders'));

        add_action('wp_ajax_bulk_cancel_incomplete_orders', array($this, 'handle_bulk_cancel_incomplete_orders'));


        add_action('wp_ajax_update_abandoned_note', array($this, 'handle_update_abandoned_note'));


        add_action('admin_post_export_abandoned_checkouts', array($this, 'handle_export_incomplete_orders'));
    }

    function enqueue_scripts($hook) {
        // Get current admin page
        $current_screen = get_current_screen();

        // Check if we're on the specific plugin pages
        if (isset($_GET['page']) && in_array($_GET['page'], ['fmb-engine', 'fmb-engine-incomplete-orders'])) {

            // Tailwind CSS load
            wp_enqueue_style(
                'tailwindcss',
                FMB_ENGINE_URL . 'assets/css/tailwind.min.css',
                array(),
                '2.2.19'
            );

            // Your custom CSS
            wp_enqueue_style(
                'ic-custom-css',
                FMB_ENGINE_URL . 'assets/css/incomplete.css',
                array('tailwindcss'),
                time()
            );


            // Your custom JS
            wp_enqueue_script(
                'incomplete-function',
                FMB_ENGINE_URL . 'assets/js/incomplete.js',
                array('jquery'),
                time(),
                true
            );

            // Localize script
            wp_localize_script('incomplete-function', 'ic_ajax_object', array(
                'ajax_url'               => admin_url('admin-ajax.php'),
                'delete_nonce'           => wp_create_nonce('ic_delete_nonce'),
                'status_nonce'           => wp_create_nonce('ic_status_nonce'),
                'note_nonce'             => wp_create_nonce('ic_note_nonce'),
                'bulk_action_nonce'      => wp_create_nonce('ic_bulk_action_nonce'),
                'settings_save_nonce'    => wp_create_nonce('ic_settings_save_nonce'),
                'schedule_cleanup_nonce' => wp_create_nonce('ic_schedule_cleanup_nonce'),
                'run_cleanup_nonce'      => wp_create_nonce('ic_run_cleanup_nonce'),
                'reset_analytics_nonce'  => wp_create_nonce('ic_reset_analytics_nonce'),
                'fetch_analytics_nonce'  => wp_create_nonce('ic_fetch_analytics_nonce'),
            ));
        }
    }


    function render_settings()
    {
        $current_search_term = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        $current_paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $current_date_filter = isset($_GET['date_filter']) ? sanitize_text_field($_GET['date_filter']) : '';
        $current_items_per_page = isset($_GET['items_per_page']) ? intval($_GET['items_per_page']) : 10;
        // Get globalwpdb object
        global $wpdb;
        $table_name = $wpdb->prefix . 'fmb_incomplete_orders_tracker';
        // Build the base query
        $query = "SELECT * FROM $table_name WHERE status_to IN ('ads-incomplete', 'fmb-incomplete', 'cancelled')";
        $count_query = "SELECT COUNT(*) FROM $table_name WHERE status_to IN ('ads-incomplete', 'fmb-incomplete', 'cancelled')";
        // Add search conditions
        if (!empty($current_search_term)) {
            $search_term = '%' . $wpdb->esc_like($current_search_term) . '%';
            $query .= $wpdb->prepare(" AND (billing_first_name LIKE %s OR billing_last_name LIKE %s OR billing_phone LIKE %s OR email LIKE %s)",
                $search_term, $search_term, $search_term, $search_term);
            $count_query .= $wpdb->prepare(" AND (billing_first_name LIKE %s OR billing_last_name LIKE %s OR billing_phone LIKE %s OR email LIKE %s)",
                $search_term, $search_term, $search_term, $search_term);
        }
        // Add date filter
        if (!empty($current_date_filter)) {
            $query .= $wpdb->prepare(" AND DATE(created_at) = %s", $current_date_filter);
            $count_query .= $wpdb->prepare(" AND DATE(created_at) = %s", $current_date_filter);
        }
        // Add ordering and pagination
        $query .= " ORDER BY created_at DESC";
        $query .= $wpdb->prepare(" LIMIT %d OFFSET %d", $current_items_per_page, ($current_paged - 1) * $current_items_per_page);
        // Get total items for pagination
        $total_items = $wpdb->get_var($count_query);
        $total_pages = ceil($total_items / $current_items_per_page);
        // Execute the main query
        $results = $wpdb->get_results($query);
        // Get states for Bangladesh
        $country_code = 'BD';
        $states_arr = (function_exists('WC') && WC()->countries) ? WC()->countries->get_states($country_code) : [];
        ?>
        <style>
            @keyframes gradientShift { 0% { background-position: 0% 50%; } 50% { background-position: 100% 50%; } 100% { background-position: 0% 50%; } }
            @keyframes fadeUp { from { opacity: 0; transform: translateY(15px); } to { opacity: 1; transform: translateY(0); } }
            
            .fmb-premium-header {
                display: flex; justify-content: space-between; align-items: center; margin: 0 0 30px 0; padding: 28px 36px; border-radius: 20px; color: white; 
                background: linear-gradient(135deg, #1e1b4b, #312e81, #1e40af); background-size: 200% 200%; animation: gradientShift 10s ease infinite, fadeUp 0.6s ease-out backwards; 
                box-shadow: 0 20px 40px -10px rgba(49, 46, 129, 0.3); position: relative; overflow: hidden; font-family: 'Inter', -apple-system, sans-serif;
            }
            .fmb-premium-header::after {
                content: ''; position: absolute; top: 0; left: 0; right: 0; bottom: 0; 
                background: url('data:image/svg+xml;utf8,<svg viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg"><filter id="noise"><feTurbulence type="fractalNoise" baseFrequency="0.65" numOctaves="3" stitchTiles="stitch"/></filter><rect width="100%" height="100%" filter="url(%23noise)" opacity="0.05"/></svg>'); 
                pointer-events: none; mix-blend-mode: overlay;
            }
            .fmb-premium-title { font-size: 26px; font-weight: 800; color: #ffffff; margin: 0; display: flex; align-items: center; gap: 14px; position: relative; z-index: 2; text-shadow: 0 2px 10px rgba(0,0,0,0.2); line-height: 1.2; }
            
            /* Overrides for modern design */
            #ic-main-dashboard-wrapper { background: transparent !important; font-family: 'Inter', sans-serif; animation: fadeUp 0.5s ease-out backwards; margin-top: 10px; }
            .bg-white.rounded-lg.shadow { background: #ffffff !important; border-radius: 24px !important; box-shadow: 0 10px 40px -10px rgba(15, 23, 42, 0.05) !important; border: 1px solid #f1f5f9; padding: 24px !important; }
            .checkout-table { border-collapse: separate; border-spacing: 0; width: 100%; }
            .checkout-table th { background: #f8fafc !important; color: #64748b !important; font-weight: 700 !important; text-transform: uppercase; font-size: 12px; letter-spacing: 1px; padding: 18px !important; border-bottom: 2px solid #f1f5f9 !important; border-top: none !important; }
            .checkout-table th:first-child { border-top-left-radius: 16px; border-bottom-left-radius: 16px; }
            .checkout-table th:last-child { border-top-right-radius: 16px; border-bottom-right-radius: 16px; }
            .checkout-table td { padding: 18px !important; font-size: 14px; border-bottom: 1px solid #f1f5f9 !important; transition: all 0.2s ease; vertical-align: middle; }
            .checkout-table tbody tr { transition: transform 0.2s ease, background 0.2s ease; border-radius: 16px; }
            .checkout-table tbody tr:hover { transform: translateX(4px); background: #f8fafc !important; }
            .checkout-table tbody tr:hover td { border-bottom-color: transparent !important; }
            
            .tab-button { font-weight: 600 !important; padding: 14px 28px !important; border-radius: 16px 16px 0 0 !important; transition: all 0.2s !important; color: #64748b !important; }
            .tab-button:hover { color: #0f172a !important; background: #f8fafc !important; }
            .tab-button.active { background: #ffffff !important; color: #2563eb !important; box-shadow: 0 -4px 15px rgba(0,0,0,0.03) !important; border-bottom: none !important; position: relative; }
            .tab-button.active::after { content: ''; position: absolute; bottom: -2px; left: 0; right: 0; height: 3px; background: #ffffff; }
            .border-b.border-gray-200 { border-bottom: 2px solid #e2e8f0 !important; }
            
            .btn-primary { background: #2563eb !important; color: white !important; border-radius: 10px !important; font-weight: 600 !important; transition: transform 0.2s, box-shadow 0.2s !important; border: none !important; box-shadow: 0 4px 6px -1px rgba(37,99,235,0.2) !important; }
            .btn-primary:hover { transform: translateY(-1px) !important; box-shadow: 0 6px 12px -2px rgba(37,99,235,0.3) !important; background: #1d4ed8 !important; }
            .filter-input { border-radius: 10px !important; border: 1px solid #cbd5e1 !important; padding: 10px 14px !important; box-shadow: 0 1px 2px rgba(0,0,0,0.02) !important; }
            .filter-input:focus { border-color: #3b82f6 !important; box-shadow: 0 0 0 3px rgba(59,130,246,0.1) !important; outline: none !important; }
        </style>

        <div class="fmb-engine-main-box">

            <div class="wrap text-gray-800" id="ic-main-dashboard-wrapper">
                <?php // The default WordPress H1 page title has been removed. ?>
                <main class="md:p-2 lg:p-4">
                    <header class="fmb-premium-header">
                        <h2 class="fmb-premium-title">
                            <span style="font-size:30px; background: #ffffff; border-radius: 12px; padding: 6px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); display:flex; align-items:center; justify-content:center;">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 28px; height: 28px; color: #2563eb;">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z"></path>
                                </svg>
                            </span>
                            <?php esc_html_e('Incomplete Orders Dashboard', 'incomplete-orders'); ?>
                        </h2>
                        <div style="font-size: 14px; color: #ffffff; font-weight: 600; background: rgba(255, 255, 255, 0.15); backdrop-filter: blur(10px); padding: 8px 16px; border-radius: 30px; border: 1px solid rgba(255,255,255,0.2); position: relative; z-index: 2;">
                            Developed by FMB Soft
                        </div>
                    </header>
                    <div class="mb-8">
                        <div class="border-b border-gray-200">
                            <nav class="-mb-px flex space-x-4" aria-label="Tabs">
                                <a href="#incomplete-orders" id="tab-incomplete-orders" class="tab-button active"
                                   aria-current="page"
                                   onclick="showTabContent('incomplete-orders-content', this, '<?php echo esc_js(__('Incomplete Orders List', 'incomplete-orders')); ?>'); return false;">
                                    <?php esc_html_e('Incomplete Orders', 'incomplete-orders'); ?>
                                </a>
                                <a href="#analytics" id="tab-analytics" class="tab-button"
                                   onclick="showTabContent('analytics-content', this, '<?php echo esc_js(__('Analytics Overview', 'incomplete-orders')); ?>'); return false;">
                                    <?php esc_html_e('Analytics', 'incomplete-orders'); ?>
                                </a>
                                <a href="#settings" id="tab-settings" class="tab-button"
                                   onclick="showTabContent('settings-content', this, '<?php echo esc_js(__('Plugin Settings', 'incomplete-orders')); ?>'); return false;">
                                    <?php esc_html_e('Settings', 'incomplete-orders'); ?>
                                </a>
                            </nav>
                        </div>
                    </div>
                    <div id="tab-content-area">
                        <div id="incomplete-orders-content" class="tab-pane active-pane">
                            <form method="get" id="ic-filters-form" class="mb-6 p-4 bg-white rounded-lg shadow" action="">
                                <input type="hidden" name="page" value="fmb-engine-incomplete-orders">
                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 items-end">
                                    <div>
                                        <label for="search-checkout"
                                               class="block text-sm font-medium text-gray-700"><?php esc_html_e('Search:', 'incomplete-orders'); ?></label>
                                        <input type="text" id="search-checkout" name="s"
                                               placeholder="<?php esc_attr_e('Search by name, phone or email', 'incomplete-orders'); ?>"
                                               class="filter-input w-full text-sm"
                                               value="<?php echo esc_attr($current_search_term); ?>">
                                    </div>
                                    <div>
                                        <label for="filter-date"
                                               class="block text-sm font-medium text-gray-700"><?php esc_html_e('Filter by Date:', 'incomplete-orders'); ?></label>
                                        <input type="date" id="filter-date" name="date_filter"
                                               class="filter-input w-full text-sm"
                                               value="<?php echo esc_attr($current_date_filter); ?>">
                                    </div>
                                    <div>
                                        <label for="items-per-page"
                                               class="block text-sm font-medium text-gray-700"><?php esc_html_e('Items Per Page:', 'incomplete-orders'); ?></label>
                                        <select id="items-per-page" name="items_per_page"
                                                class="filter-input w-full text-sm">
                                            <?php
                                            $ipp_options = [10, 25, 50, 100];
                                            foreach ($ipp_options as $option) {
                                                echo '<option value="' . esc_attr($option) . '" ' . selected($current_items_per_page, $option, false) . '>' . esc_html($option) . '</option>';
                                            }
                                            ?>
                                        </select>
                                    </div>
                                    <button type="submit"
                                            class="btn-primary py-2 text-sm w-full lg:w-auto"><?php esc_html_e('Filter', 'incomplete-orders'); ?></button>
                                </div>
                            </form>
                            <div class="mb-6 p-4 bg-white rounded-lg shadow">
                                <div class="flex flex-wrap justify-between items-center gap-4">
                                    <div class="flex items-center gap-2">
                                        <label for="bulk-actions-top"
                                               class="sr-only"><?php esc_html_e('Bulk Actions', 'incomplete-orders'); ?></label>
                                        <select id="bulk-actions-top" name="action_bulk_top"
                                                class="border border-gray-300 rounded px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-[#1a7278]">
                                            <option value="-1"><?php esc_html_e('Bulk Actions', 'incomplete-orders'); ?></option>
                                            <option value="mark-recovered"><?php esc_html_e('Mark as Recovered', 'incomplete-orders'); ?></option>
                                            <option value="cancel"><?php esc_html_e('Mark as Cancel', 'incomplete-orders'); ?></option>
                                            <option value="delete"><?php esc_html_e('Mark as Delete', 'incomplete-orders'); ?></option>
                                        </select>
                                        <button type="button" id="doaction-top"
                                                class="btn-secondary py-2 text-sm"><?php esc_html_e('Apply', 'incomplete-orders'); ?></button>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <div class="relative">
                                            <button type="button" id="toggleColumnsBtn"
                                                    class="btn-secondary py-2 text-sm"><?php esc_html_e('Columns', 'incomplete-orders'); ?></button>
                                            <div id="columnsDropdown" class="column-toggle-dropdown hidden">
                                                <label><input type="checkbox" class="column-select-all"
                                                              checked> <?php esc_html_e('Select All', 'incomplete-orders'); ?>
                                                </label>
                                                <hr class="my-1 border-gray-200">
                                            </div>
                                        </div>
                                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
                                              style="display:inline;">
                                            <input type="hidden" name="action" value="export_abandoned_checkouts">
                                            <?php wp_nonce_field('export_abandoned_checkouts_action', 'export_abandoned_checkouts_nonce'); ?>
                                            <button type="submit"
                                                    class="btn-primary py-2 text-sm ml-2"><?php esc_html_e('Export All as CSV', 'incomplete-orders'); ?></button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <div class="table-card overflow-x-auto">
                                <table class="w-full checkout-table" id="incompleteOrdersTable">
                                    <thead>
                                    <tr>
                                        <th class="w-10" data-column-key="checkbox"><input type="checkbox"
                                                                                           class="rounded border-gray-300 text-[#1a7278] focus:ring-[#1a7278] table-select-all-rows">
                                        </th>
                                        <th data-column-key="date"><?php esc_html_e('Date', 'incomplete-orders'); ?></th>
                                        <th data-column-key="name"><?php esc_html_e('Name', 'incomplete-orders'); ?></th>
                                        <th data-column-key="phone"><?php esc_html_e('Phone', 'incomplete-orders'); ?></th>
                                        <th data-column-key="courier"><?php esc_html_e('Fraud Checker', 'incomplete-orders'); ?></th>
                                        <th data-column-key="address"><?php esc_html_e('Address', 'incomplete-orders'); ?></th>
                                        <th data-column-key="state"><?php esc_html_e('State', 'incomplete-orders'); ?></th>
                                        <th class="text-right"
                                            data-column-key="subtotal"><?php esc_html_e('Subtotal', 'incomplete-orders'); ?></th>
                                        <th data-column-key="products"><?php esc_html_e('Products', 'incomplete-orders'); ?></th>
                                        <th data-column-key="status"><?php esc_html_e('Status', 'incomplete-orders'); ?></th>
                                        <th class="w-64" data-column-key="note"><?php esc_html_e('Note', 'incomplete-orders'); ?></th>
                                        <th class="w-32" data-column-key="actions"><?php esc_html_e('Actions', 'incomplete-orders'); ?></th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php
                                    if (empty($results)) {
                                        echo '<tr><td colspan="9" class="text-center py-4">' . esc_html__('No incomplete orders found matching your criteria.', 'incomplete-orders') . '</td></tr>';
                                    } else {
                                        foreach ($results as $row) {
                                            $id = $row->id;
                                            $first = $row->billing_first_name;
                                            $last = $row->billing_last_name;
                                            $name_str = trim("$first $last");
                                            $name_display = !empty($name_str) ? esc_html($name_str) : '<em>' . esc_html__('No Name Provided', 'incomplete-orders') . '</em>';
                                            $phone_val = $row->billing_phone;
                                            $addr_val = $row->billing_address_1;
                                            $state_code = $row->billing_state;
                                            $state_display = isset($states_arr[$state_code]) ? esc_html($states_arr[$state_code]) : esc_html($state_code ?: __('Unknown', 'incomplete-orders'));
                                            $subtotal_raw = floatval($row->order_total);
                                            $subtotal_display = function_exists('wc_price') ? wc_price($subtotal_raw) : esc_html(number_format($subtotal_raw, 2));
                                            // Format date - Short format for Incomplete Orders
                                            $date_val_display = __('N/A', 'incomplete-orders');
                                            $time_val_display = '';
                                            if (!empty($row->created_at) && $row->created_at != '0000-00-00 00:00:00') {
                                                $date_val_display = mysql2date('M j, Y', $row->created_at);
                                                $time_val_display = mysql2date('g:i a', $row->created_at);
                                            }
                                            $note_val = $row->note;
                                            if ($row->status_to === 'cancelled') {
                                                $status_html_display = '<span class="status-badge" style="background-color: #fee2e2; color: #991b1b; padding: 2px 8px; border-radius: 4px; font-size: 0.75rem;">' . esc_html__('Cancel', 'incomplete-orders') . '</span>';
                                            } else {
                                                $status_html_display = '<span class="status-badge status-pending">' . esc_html__('Pending', 'incomplete-orders') . '</span>';
                                            }

                                            // প্রোডাক্ট তালিকা HTML তৈরি করা
                                            $products_list_html = "";
                                            $all_products_data = array();
                                            $order_id = absint($row->order_id ?? 0);
                                            $cart_data_raw = !empty($row->cart_data) ? json_decode($row->cart_data, true) : array();

                                            if (!empty($cart_data_raw) && is_array($cart_data_raw)) {
                                                $product_count = 0;
                                                $max_display = 2;
                                                foreach ($cart_data_raw as $c_item) {
                                                    $p_id = absint($c_item['id'] ?? $c_item['product_id'] ?? 0);
                                                    $p_name = $c_item['name'] ?? '';
                                                    $p_qty = absint($c_item['qty'] ?? $c_item['quantity'] ?? 1);
                                                    $p_price = floatval($c_item['price'] ?? 0);
                                                    $p_total = floatval($c_item['total'] ?? ($p_price * $p_qty));

                                                    if (!$p_name && $p_id > 0) {
                                                        $wc_p = wc_get_product($p_id);
                                                        if ($wc_p) $p_name = $wc_p->get_name();
                                                    }
                                                    if (!$p_name) $p_name = 'Product #' . $p_id;

                                                    $all_products_data[] = array(
                                                        'name' => $p_name,
                                                        'quantity' => $p_qty,
                                                        'total' => $p_total,
                                                        'formatted_total' => function_exists('wc_price') ? wc_price($p_total) : number_format($p_total, 2)
                                                    );

                                                    $display_name = (strlen($p_name) > 22) ? substr($p_name, 0, 22) . '...' : $p_name;
                                                    if ($product_count < $max_display) {
                                                        $products_list_html .= sprintf(
                                                            '<div class="product-item" style="font-size:12px; font-weight:600; color:#1e293b;">%s <span style="color:#64748b;">(Qty: %d)</span></div>',
                                                            esc_html($display_name),
                                                            $p_qty
                                                        );
                                                    }
                                                    $product_count++;
                                                }
                                                if ($product_count > $max_display) {
                                                    $remaining = $product_count - $max_display;
                                                    $products_list_html .= sprintf('<div class="more-items font-semibold mt-1 text-xs text-blue-600">+%d more</div>', $remaining);
                                                }
                                            } elseif ($order_id > 0 && function_exists('wc_get_order')) {
                                                $order = wc_get_order($order_id);
                                                if ($order) {
                                                    $items = $order->get_items();
                                                    $product_count = 0;
                                                    $max_display = 2;
                                                    foreach ($items as $item_id => $item) {
                                                        $product = $item->get_product();
                                                        if (!$product) continue;
                                                        $product_name = $product->get_name();
                                                        $quantity = $item->get_quantity();
                                                        $total = $item->get_total();

                                                        $all_products_data[] = array(
                                                            'name' => $product_name,
                                                            'quantity' => $quantity,
                                                            'total' => $total,
                                                            'formatted_total' => function_exists('wc_price') ? wc_price($total) : number_format($total, 2)
                                                        );

                                                        $display_name = (strlen($product_name) > 22) ? substr($product_name, 0, 22) . '...' : $product_name;
                                                        if ($product_count < $max_display) {
                                                            $products_list_html .= sprintf(
                                                                '<div class="product-item" style="font-size:12px; font-weight:600; color:#1e293b;">%s <span style="color:#64748b;">(Qty: %d)</span></div>',
                                                                esc_html($display_name),
                                                                $quantity
                                                            );
                                                        }
                                                        $product_count++;
                                                    }
                                                    if ($product_count > $max_display) {
                                                        $remaining = $product_count - $max_display;
                                                        $products_list_html .= sprintf('<div class="more-items font-semibold mt-1 text-xs text-blue-600">+%d more</div>', $remaining);
                                                    }
                                                } else {
                                                    $products_list_html = '<span style="color:#94a3b8; font-size:12px;">No products</span>';
                                                }
                                            } else {
                                                $products_list_html = '<span style="color:#94a3b8; font-size:12px;">No products</span>';
                                            }
                                            ?>
                                            <tr id="checkout-row-<?php echo esc_attr($id); ?>">
                                                <td data-column-key="checkbox"><input type="checkbox"
                                                                                      class="rounded border-gray-300 text-[#1a7278] focus:ring-[#1a7278] row-checkbox"
                                                                                      value="<?php echo esc_attr($id); ?>">
                                                </td>
                                                <td class="text-xs text-gray-500"
                                                    data-column-key="date"><?php echo esc_html($date_val_display); ?>
                                                    <br><?php echo esc_html($time_val_display); ?></td>
                                                <td class="relative-cell group" data-column-key="name">
                                                    <span class="data-text"><?php echo $name_display; ?></span>
                                                    <!--
                                                <button type="button" class="btn-action copy-button"
                                                        data-copytext="<?php echo esc_attr(strip_tags($name_str)); ?>">
                                                    Copy
                                                </button> -->
                                                </td>
                                                <td class="relative-cell group" data-column-key="phone">
                                                    <span class="data-text"><?php echo esc_html($phone_val); ?></span>
                                                    <div class="absolute bottom-[-22px] left-1/2 transform -translate-x-1/2 whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity duration-200 z-10">
                                                        <!--<button type="button"
                                                            class="btn-action !visible !opacity-100 !relative !bottom-auto !left-auto !transform-none copy-button mr-1"
                                                            data-copytext="<?php echo esc_attr($phone_val); ?>">Copy
                                                    </button> -->
                                                        <a href="tel:<?php echo esc_attr($phone_val); ?>"
                                                       class="btn-action !visible !opacity-100 !relative !bottom-auto !left-auto !transform-none bg-green-500 hover:bg-green-600 text-white" style="padding: 2px 8px; font-size: 11px; border-radius: 4px; text-decoration: none;">Call</a>
                                                    </div>
                                                </td>
                                                <td class="relative-cell group" data-column-key="courier">
                                                    <?php
                                                    $fraud_html = '';
                                                    $fraud_checker_enabled = get_option('ads_enable_fraud_checker', false);
                                                    if ($fraud_checker_enabled) {
                                                        $clean_phone = preg_replace('/[^\d]/', '', $phone_val);
                                                        if (preg_match('/(?:^88|^)(01[0-9]{9})$/', $clean_phone, $matches)) {
                                                            $clean_phone = $matches[1];
                                                        }

                                                        if (!$clean_phone || strlen($clean_phone) != 11) {
                                                            $fraud_html = '<span style="color:#a0aec0;font-size:11px;">Invalid Phone</span>';
                                                        } else {
                                                            if (class_exists('\fmb_engine\Courier\OFLS_BD_Courier_Engine')) {
                                                                $courier_history = \fmb_engine\Courier\OFLS_BD_Courier_Engine::get_customer_history($clean_phone, true);
                                                                if (is_string($courier_history)) {
                                                                    $courier_history = json_decode($courier_history, true);
                                                                }
                                                                if (is_array($courier_history)) {
                                                                    $total_order     = intval($courier_history['total_order'] ?? ($courier_history['total_orders'] ?? 0));
                                                                    $total_success   = intval($courier_history['total_success'] ?? 0);
                                                                    $total_cancel    = intval($courier_history['total_cancel'] ?? ($courier_history['total_returns'] ?? 0));
                                                                    $success_percent = floatval($courier_history['success_percent'] ?? ($courier_history['courier_ratio'] ?? 0));

                                                                    if ($total_order > 0 && $success_percent == 0) {
                                                                        $success_percent = round(($total_success / $total_order) * 100, 1);
                                                                    }
                                                                    $cancel_percent  = floatval($courier_history['cancel_percent'] ?? 0);
                                                                    if ($total_order > 0 && $cancel_percent == 0) {
                                                                        $cancel_percent = round(($total_cancel / $total_order) * 100, 1);
                                                                    }

                                                                    $status_color = '#e53e3e';
                                                                    if ($success_percent >= 70) $status_color = '#00a669';
                                                                    else if ($success_percent >= 40) $status_color = '#dd6b20';

                                                                    ob_start();
                                                                    ?>
                                                                    <div class="courier-history-stats" style="margin-bottom: 6px; font-size: 13px; display: flex; gap: 12px;">
                                                                        <span class="stat-label" title="Total" style="color: #1a202c; font-weight: 600;">📦 <?php echo esc_html($total_order); ?></span>
                                                                        <span class="stat-label success" title="Success" style="color: #00a669; font-weight: 600;">✓ <?php echo esc_html($total_success); ?></span>
                                                                        <span class="stat-label cancel" title="Cancel" style="color: #e53e3e; font-weight: 600;">✕ <?php echo esc_html($total_cancel); ?></span>
                                                                    </div>
                                                                    <div class="courier-progress-wrap" style="display: flex; align-items: center; width: 100%; max-width: 160px; gap: 10px;">
                                                                        <div class="progress-bar-bg" style="flex: 1; height: 6px; background: #edf2f7; border-radius: 4px; overflow: hidden;">
                                                                            <div class="progress-bar-fill" style="width: <?php echo esc_attr($success_percent); ?>%; height: 100%; background: <?php echo esc_attr($status_color); ?>; border-radius: 4px;"></div>
                                                                        </div>
                                                                        <div class="progress-text" style="font-size: 12px; font-weight: 700; color: <?php echo esc_attr($status_color); ?>; min-width: 25px;">
                                                                            <?php echo round($success_percent); ?>%
                                                                        </div>
                                                                        <button type="button" class="refresh-courier-history" data-phone="<?php echo esc_attr($clean_phone); ?>" data-order-id="<?php echo esc_attr($order_id); ?>" title="Refresh" style="background: none; border: none; padding: 0; cursor: pointer; color: #a0aec0; transition: color 0.2s;">
                                                                            <span class="dashicons dashicons-update" style="font-size: 14px; width: 14px; height: 14px;"></span>
                                                                        </button>
                                                                    </div>
                                                                    <?php
                                                                    $stats_html = ob_get_clean();
                                                                    $fraud_html = '<div class="courier-history-container loaded" data-phone="' . esc_attr($clean_phone) . '" data-order-id="' . esc_attr($order_id) . '">' . $stats_html . '</div>';
                                                                } else {
                                                                    $fraud_html = '<div class="courier-history-container" data-phone="' . esc_attr($clean_phone) . '" data-order-id="' . esc_attr($order_id) . '" style="text-align: left;">';
                                                                    $fraud_html .= '<button type="button" class="refresh-courier-history ads-check-btn" data-phone="' . esc_attr($clean_phone) . '" data-order-id="' . esc_attr($order_id) . '"><span class="dashicons dashicons-search" style="font-size: 15px; width: 15px; height: 15px; display: flex; align-items: center; justify-content: center;"></span> Check</button>';
                                                                    $fraud_html .= '</div>';
                                                                }
                                                            } else {
                                                                $fraud_html = '<span style="color:#a0aec0;font-size:11px;">Error</span>';
                                                            }
                                                        }
                                                    } else {
                                                        $fraud_html = '<span style="color:#a0aec0;font-size:11px;">Disabled</span>';
                                                    }
                                                    echo $fraud_html;
                                                    ?>
                                                </td>
                                                <td class="relative-cell group" data-column-key="address">
                                                    <span class="data-text"><?php echo esc_html($addr_val); ?></span>
                                                    <!--
                                                <button type="button" class="btn-action copy-button"
                                                        data-copytext="<?php echo esc_attr($addr_val); ?>">Copy
                                                </button> -->
                                                </td>
                                                <td class="relative-cell group" data-column-key="state">
                                                    <span class="data-text"><?php echo $state_display; ?></span>
                                                    <!--
                                                <button type="button" class="btn-action copy-button"
                                                        data-copytext="<?php echo esc_attr($state_display); ?>">Copy
                                                </button> -->
                                                </td>
                                                <td class="text-right font-medium"
                                                    data-column-key="subtotal"><?php echo $subtotal_display; ?></td>
                                                <td class="text-right font-medium"
                                                    data-column-key="products"><?php echo $products_list_html; ?></td>
                                                <td data-column-key="status"
                                                    id="status-cell-<?php echo esc_attr($id); ?>"><?php echo $status_html_display; ?></td>
                                                <td class="w-64" data-column-key="note">
            <textarea rows="1" id="note-<?php echo esc_attr($id); ?>"
                      class="filter-input w-full text-xs p-1"
                      placeholder="<?php esc_attr_e('Add note...', 'incomplete-orders'); ?>"><?php echo esc_textarea($note_val); ?></textarea>
                                                </td>
                                                <td class="action-buttons-cell whitespace-nowrap" data-column-key="actions">
                                                    <button type="button"
                                                            class="btn-secondary btn-action !visible !opacity-100 !relative !bottom-auto !left-auto !transform-none py-1 px-2 text-xs ic-toggle-status"
                                                            data-id="<?php echo esc_attr($id); ?>"><?php esc_html_e('Create Order', 'incomplete-orders'); ?></button>
                                                    <button type="button"
                                                            class="btn-primary btn-action !visible !opacity-100 !relative !bottom-auto !left-auto !transform-none py-1 px-2 text-xs ic-save-note"
                                                            data-id="<?php echo esc_attr($id); ?>"><?php esc_html_e('Update', 'incomplete-orders'); ?></button>
                                                    <button type="button"
                                                            class="btn-danger btn-action !visible !opacity-100 !relative !bottom-auto !left-auto !transform-none py-1 px-2 text-xs ic-delete-lead"
                                                            data-id="<?php echo esc_attr($id); ?>"><?php esc_html_e('Delete', 'incomplete-orders'); ?></button>
                                                </td>
                                            </tr>
                                            <?php
                                        }
                                    }
                                    ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="mt-4 text-sm text-gray-600" id="ic-pagination-summary">
                                <?php
                                if ($total_items > 0) {
                                    $start_item = ($current_paged - 1) * $current_items_per_page + 1;
                                    $end_item = min($current_paged * $current_items_per_page, $total_items);
                                    printf(
                                        wp_kses_post( /* translators: 1: Start item number, 2: End item number, 3: Total items. */ __('Showing %1$s to %2$s of %3$s incomplete orders', 'incomplete-orders')),
                                        '<strong>' . esc_html($start_item) . '</strong>',
                                        '<strong>' . esc_html($end_item) . '</strong>',
                                        '<strong>' . esc_html($total_items) . '</strong>'
                                    );
                                }
                                ?>
                            </div>
                            <div class="mt-4 flex items-center justify-center" id="ic-pagination-links">
                                <?php
                                if ($total_pages > 1) {
                                    $pagination_links = paginate_links([
                                        'base' => add_query_arg('paged', '%#%', ads_get_current_admin_url()),
                                        'format' => '',
                                        'current' => $current_paged,
                                        'total' => $total_pages,
                                        'prev_text' => __('&laquo; Previous', 'incomplete-orders'),
                                        'next_text' => __('Next &raquo;', 'incomplete-orders'),
                                        'type' => 'array',
                                        'add_args' => true,
                                        'show_all' => false,
                                        'mid_size' => 2,
                                        'end_size' => 1,
                                    ]);
                                    if ($pagination_links) {
                                        echo '<div class="flex items-center space-x-2">';
                                        foreach ($pagination_links as $link) {
                                            if (strpos($link, 'current') !== false) {
                                                // Current page number
                                                $clean_link = strip_tags($link);
                                                echo '<span class="px-3 py-2 text-sm font-medium text-white bg-[#1a7278] rounded-md">' . esc_html($clean_link) . '</span>';
                                            } elseif (strpos($link, 'prev') !== false || strpos($link, 'next') !== false) {
                                                // Previous/Next buttons
                                                $clean_link = preg_replace('/<a[^>]*>/', '<a class="px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 hover:text-gray-900 transition-colors duration-200">', $link);
                                                echo $clean_link;
                                            } elseif (strpos($link, 'dots') !== false) {
                                                // Ellipsis
                                                echo '<span class="px-3 py-2 text-sm font-medium text-gray-500">...</span>';
                                            } else {
                                                // Regular page numbers
                                                $clean_link = preg_replace('/<a[^>]*>/', '<a class="px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 hover:text-gray-900 transition-colors duration-200">', $link);
                                                echo $clean_link;
                                            }
                                        }
                                        echo '</div>';
                                    }
                                }
                                ?>
                            </div>
                        </div>
                        <!-- Analytics and Settings tabs remain unchanged -->
                        <div id="analytics-content" class="tab-pane" style="display: none;">
                            <?php
                            require_once __DIR__ . '/deshobard.php';
                            ?>
                        </div>
                        <div id="settings-content" class="tab-pane" style="display: none;">
                            <form id="ic-settings-form" method="POST" action="">
                                <input type="hidden" name="page" value="fmb-engine-incomplete-orders">

                                <div class="fmb-engine-settings-wrapper">
                                    <div class="fmb-engine-settings-content" style="padding: 30px;">
                                        <div class="fmb-engine-section" style="margin-bottom: 0;">
                                            <h2 class="fmb-engine-section-title">General Settings</h2>
                                            <div class="fmb-engine-form-container">
                                                
                                                <div class="fmb-engine-field-row">
                                                    <div class="fmb-engine-field-label">Enable Order Incomplete</div>
                                                    <div class="fmb-engine-field-input">
                                                        <label class="fmb-engine-toggle-switch">
                                                            <input type="checkbox" name="ads_enable_order_autosave" value="1"  <?php checked( get_option('ads_enable_order_autosave'), 1 ); ?>>
                                                            <span class="fmb-engine-slider"></span>
                                                        </label>
                                                    </div>
                                                </div>

                                            <div class="fmb-engine-field-row">
                                                <div class="fmb-engine-field-label">
                                                    Incomplete Session Time Duration (minutes)
                                                    <p class="ads-settings-description">Default: 5 minutes. Min: 1, Max: 4320 (3 days)</p>
                                                </div>
                                                <div class="fmb-engine-field-input">
                                                    <input type="number" name="ads_order_autosave_session_time" value="<?php echo esc_attr( get_option('ads_order_autosave_session_time', 5) ); ?>" min="1" max="4320" style="max-width: 200px;">
                                                </div>
                                            </div>

                                            <div class="fmb-engine-field-row">
                                                <div class="fmb-engine-field-label">
                                                    Allowed Roles
                                                    <p class="ads-settings-description">Select which roles can view the Incomplete Orders data. Administrator is always allowed.</p>
                                                </div>
                                                <div class="fmb-engine-field-input">
                                                    <?php
                                                    $wp_roles = wp_roles()->get_names();
                                                    $allowed_roles = get_option('ofls_incomplete_orders_roles', ['administrator']);
                                                    if (!is_array($allowed_roles)) $allowed_roles = ['administrator'];
                                                    ?>
                                                    <select name="ofls_incomplete_orders_roles[]" multiple="multiple" class="wc-enhanced-select" style="width: 100%; max-width: 400px;" data-placeholder="Select roles...">
                                                        <?php foreach ($wp_roles as $role_key => $role_name) : ?>
                                                            <option value="<?php echo esc_attr($role_key); ?>" <?php echo in_array($role_key, $allowed_roles) ? 'selected' : ''; ?> <?php echo ($role_key === 'administrator') ? 'disabled' : ''; ?>>
                                                                <?php echo esc_html($role_name); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                            </div>

                                        </div>
                                    </div>

                                    <button type="submit" id="ic-save-settings-btn" name="save_ic_settings" class="fmb-engine-submit-button" style="margin-top: 20px;">
                                        Save Settings
                                    </button>
                                    </div>
                                </div>
                            </form>

                        </div>
                    </div>
                </main>
                <div id="copyMessage"><?php esc_html_e('Copied to clipboard!', 'incomplete-orders'); ?></div>
            </div>
        </div>

        <!-- Products Modal/Popup -->
        <div id="products-modal" class="products-modal-overlay" style="display: none;">
            <div class="products-modal-content">
                <div class="products-modal-header">
                    <h3 class="products-modal-title"><?php esc_html_e('All Products', 'incomplete-orders'); ?></h3>
                    <button type="button" class="products-modal-close" aria-label="<?php esc_attr_e('Close', 'incomplete-orders'); ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <div class="products-modal-body">
                    <div id="products-modal-list"></div>
                </div>
            </div>
        </div>
        <style>
            .notice.notice-info {
                display: none;
            }
        </style>
        <?php
    }


    function handle_update_incomplete_status()
    {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'ic_status_nonce')) {
            wp_send_json_error(array('reason' => 'Security check failed'));
        }

        // Get and validate parameters
        $lead_id = isset($_POST['lead_id']) ? intval($_POST['lead_id']) : 0;

        if ($lead_id <= 0) {
            wp_send_json_error(array('reason' => 'Invalid lead ID'));
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'fmb_incomplete_orders_tracker';

        $lead = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $lead_id));
        if (!$lead) {
            wp_send_json_error(array('reason' => 'Lead not found'));
        }

        // Lock to prevent concurrent requests
        $lock_key = 'fmb_create_order_lock_' . $lead_id;
        if (get_transient($lock_key)) {
            wp_send_json_error(array('reason' => 'অর্ডার তৈরির কাজ ইতিমধ্যে চলমান রয়েছে।'));
        }
        set_transient($lock_key, true, 10);

        $order_id = absint($lead->order_id ?? 0);

        if ($order_id > 0 && function_exists('wc_get_order')) {
            $order = wc_get_order($order_id);
            if ($order) {
                $order->update_status('processing', 'Converted from incomplete order.');
                $current_time = new \WC_DateTime('now', new \DateTimeZone(wp_timezone_string()));
                $order->set_date_created($current_time);
                $order->save();
                wc_delete_shop_order_transients($order_id);
                clean_post_cache($order_id);
            }
        } elseif (function_exists('wc_create_order')) {
            // Create a brand new WooCommerce Order from lead data
            $order = wc_create_order();
            $cart_items = !empty($lead->cart_data) ? json_decode($lead->cart_data, true) : array();
            
            if (!empty($cart_items) && is_array($cart_items)) {
                foreach ($cart_items as $c_item) {
                    $p_id = absint($c_item['id'] ?? $c_item['product_id'] ?? 0);
                    $qty = max(1, absint($c_item['qty'] ?? $c_item['quantity'] ?? 1));
                    if ($p_id > 0) {
                        $p_obj = wc_get_product($p_id);
                        if ($p_obj) {
                            $order->add_product($p_obj, $qty);
                        }
                    }
                }
            }

            $address = array(
                'first_name' => $lead->billing_first_name,
                'phone'      => $lead->billing_phone,
                'address_1'  => $lead->billing_address_1,
                'country'    => 'BD',
            );
            $order->set_address($address, 'billing');
            $order->set_address($address, 'shipping');
            $order->set_payment_method('cod');

            $order->calculate_totals();
            $order->update_status('processing', 'Order created from Live Lead / Incomplete Order.');
            $order_id = $order->get_id();
        }

        // Update database status
        $wpdb->update(
            $table_name,
            array(
                'status_to' => 'ads-recovered',
                'order_id'  => $order_id
            ),
            array('id' => $lead_id)
        );

        delete_transient($lock_key);

        // Return success response
        wp_send_json_success(array(
            'message' => 'Order created/updated successfully! Order ID: #' . $order_id,
            'new_status' => "Purchase",
            'order_id' => $order_id
        ));
    }

    function handle_delete_incomplete_orders()
    {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'ic_delete_nonce')) {
            wp_send_json_error(array('reason' => 'Security check failed'));
        }

        // Get and validate lead_id
        $lead_id = isset($_POST['lead_id']) ? intval($_POST['lead_id']) : 0;

        if ($lead_id <= 0) {
            wp_send_json_error(array('reason' => 'Invalid lead ID'));
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'fmb_incomplete_orders_tracker';

        // Get the order_id from the custom table
        $order_id = $wpdb->get_var($wpdb->prepare(
            "SELECT order_id FROM $table_name WHERE id = %d",
            $lead_id
        ));

        if ($order_id && class_exists('\fmb_engine\Incomplete_Order')) {
            $incomplete_order = new \fmb_engine\Incomplete_Order();
            $incomplete_order->delete_autosave_order_on_checkout($order_id, true);
        }

        // Delete row directly
        $wpdb->delete($table_name, array('id' => $lead_id));

        // Return success response
        wp_send_json_success(array(
            'message' => 'Record deleted successfully',
            'deleted_id' => $lead_id
        ));
    }

    function handle_bulk_mark_incomplete_orders()
    {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'ic_bulk_action_nonce')) {
            wp_send_json_error(array('message' => 'Security check failed'));
        }

        // Get and validate lead_ids
        $lead_ids = isset($_POST['lead_ids']) ? array_map('intval', $_POST['lead_ids']) : array();

        if (empty($lead_ids)) {
            wp_send_json_error(array('message' => 'No lead IDs provided'));
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'fmb_incomplete_orders_tracker';
        $updated_count = 0;
        $errors = array();

        foreach ($lead_ids as $lead_id) {
            // Get the order_id from the custom table
            $order_id = $wpdb->get_var($wpdb->prepare(
                "SELECT order_id FROM $table_name WHERE id = %d",
                $lead_id
            ));

            if (!$order_id) {
                $errors[] = "Order ID not found for lead ID: $lead_id";
                continue;
            }

            // Update WooCommerce order status + created time
            $order = wc_get_order($order_id);
            if ($order) {
                // Update status
                $order->update_status('ads-recovered', 'Converted from incomplete order.');

                // Update created time to now
                $current_time = new \WC_DateTime('now', new \DateTimeZone(wp_timezone_string()));
                $order->set_date_created($current_time);

                // Save the order
                $order->save();

                wc_delete_shop_order_transients($order_id);
                clean_post_cache($order_id);

                $updated_count++;
            } else {
                $errors[] = "WooCommerce order not found for ID: $order_id";
            }
        }

        // Return success response
        wp_send_json_success(array(
            'updated_count' => $updated_count,
            'errors'        => $errors
        ));
    }

    function handle_bulk_cancel_incomplete_orders()
    {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'ic_bulk_action_nonce')) {
            wp_send_json_error(array('message' => 'Security check failed'));
        }

        // Get and validate lead_ids
        $lead_ids = isset($_POST['lead_ids']) ? array_map('intval', $_POST['lead_ids']) : array();

        if (empty($lead_ids)) {
            wp_send_json_error(array('message' => 'No lead IDs provided'));
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'fmb_incomplete_orders_tracker';
        $updated_count = 0;
        $errors = array();

        foreach ($lead_ids as $lead_id) {
            // Get the order_id from the custom table
            $order_id = $wpdb->get_var($wpdb->prepare(
                "SELECT order_id FROM $table_name WHERE id = %d",
                $lead_id
            ));

            if (!$order_id) {
                $errors[] = "Order ID not found for lead ID: $lead_id";
                continue;
            }

            // Update WooCommerce order status
            $order = wc_get_order($order_id);
            if ($order) {
                // Update status
                $order->update_status('cancelled', 'Cancelled from bulk action in incomplete orders.');

                // Save the order
                $order->save();

                wc_delete_shop_order_transients($order_id);
                clean_post_cache($order_id);

                $updated_count++;
            } else {
                $errors[] = "WooCommerce order not found for ID: $order_id";
            }
        }

        // Return success response
        wp_send_json_success(array(
            'updated_count' => $updated_count,
            'errors'        => $errors
        ));
    }



    function handle_bulk_delete_incomplete_orders()
    {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'ic_bulk_action_nonce')) {
            wp_send_json_error(array('reason' => 'Security check failed'));
        }

        // Get and validate lead_ids
        $lead_ids = isset($_POST['lead_ids']) ? array_map('intval', $_POST['lead_ids']) : array();

        if (empty($lead_ids)) {
            wp_send_json_error(array('reason' => 'No lead IDs provided'));
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'fmb_incomplete_orders_tracker';
        $deleted_count = 0;
        $errors = array();

        // Create an instance of the Incomplete_Order class to use its methods
        $incomplete_order = new \fmb_engine\Incomplete_Order();

        foreach ($lead_ids as $lead_id) {
            // Get the order_id from the custom table
            $order_id = $wpdb->get_var($wpdb->prepare(
                "SELECT order_id FROM $table_name WHERE id = %d",
                $lead_id
            ));

            if (!$order_id) {
                $errors[] = "Order ID not found for lead ID: $lead_id";
                continue;
            }

            // Use the existing delete_autosave_order_on_checkout method
            // This will handle all the cleanup including deleting from custom table and WooCommerce order
            $result = $incomplete_order->delete_autosave_order_on_checkout($order_id, true);

            if ($result) {
                $deleted_count++;
            } else {
                $errors[] = "Failed to delete lead ID: $lead_id";
            }
        }

        // Return success response
        wp_send_json_success(array(
            'message' => sprintf('%d record(s) deleted successfully', $deleted_count),
            'deleted' => $deleted_count,
            'deleted_ids' => $lead_ids,
            'errors' => $errors
        ));
    }

    public function fmb_engine_handle_settings_form() {
        if (isset($_POST['save_ic_settings'])) {
            // নিরাপত্তা চেক
            if (!current_user_can('manage_options')) {
                wp_die('Unauthorized user');
            }

            // ভ্যালু নেওয়া
            $enabled = isset($_POST['ads_enable_order_autosave']) ? 1 : 0;
            $session_time = isset($_POST['ads_order_autosave_session_time']) ? intval($_POST['ads_order_autosave_session_time']) : 5;

            // Validation
            if ($session_time < 1) {
                $session_time = 1;
            } elseif ($session_time > 4320) {
                $session_time = 4320;
            }

            $allowed_roles = isset($_POST['ofls_incomplete_orders_roles']) && is_array($_POST['ofls_incomplete_orders_roles']) 
                ? array_map('sanitize_text_field', $_POST['ofls_incomplete_orders_roles']) 
                : [];
            // Always ensure administrator is in the list
            if (!in_array('administrator', $allowed_roles)) {
                $allowed_roles[] = 'administrator';
            }

            // অপশন সেভ
            update_option('ads_enable_order_autosave', $enabled);
            update_option('ads_order_autosave_session_time', $session_time);
            update_option('ofls_incomplete_orders_roles', $allowed_roles);

            // success query param
            wp_redirect(add_query_arg('settings-updated', 'true', $_SERVER['REQUEST_URI']));
            exit;
        }
    }


    public function handle_update_abandoned_note() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'ic_note_nonce')) {
            wp_send_json_error(array('reason' => 'Security check failed'));
        }

        // Get and validate parameters
        $lead_id = isset($_POST['lead_id']) ? intval($_POST['lead_id']) : 0;
        $note = isset($_POST['note']) ? sanitize_textarea_field($_POST['note']) : '';

        if ($lead_id <= 0) {
            wp_send_json_error(array('reason' => 'Invalid lead ID'));
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'fmb_incomplete_orders_tracker';

        // Update the note in the database
        $result = $wpdb->update(
            $table_name,
            array('note' => $note),
            array('id' => $lead_id),
            array('%s'),
            array('%d')
        );

        if ($result === false) {
            wp_send_json_error(array('reason' => 'Database update failed'));
        }

        // Return success response
        wp_send_json_success(array(
            'message' => 'Note updated successfully',
            'note' => $note
        ) );
    }

    public function handle_export_incomplete_orders() {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized user');
        }

        // Verify nonce
        if (!isset($_POST['export_abandoned_checkouts_nonce']) ||
            !wp_verify_nonce($_POST['export_abandoned_checkouts_nonce'], 'export_abandoned_checkouts_action')) {
            wp_die('Security check failed');
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'fmb_incomplete_orders_tracker';

        // Get all incomplete orders with order_id
        $results = $wpdb->get_results(
            "SELECT * FROM $table_name WHERE status_to IN ('ads-incomplete', 'fmb-incomplete', 'cancelled') ORDER BY created_at DESC",
            ARRAY_A
        );

        if (empty($results)) {
            wp_die('No incomplete orders found to export');
        }

        // Prepare CSV headers
        $filename = 'incomplete_orders_' . date('Y-m-d') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);

        // Open output stream
        $output = fopen('php://output', 'w');

        // Add CSV headers
        fputcsv($output, [
            'ID',
            'Date',
            'First Name',
            'Last Name',
            'Phone',
            'Email',
            'Address',
            'City',
            'State',
            'Postcode',
            'Country',
            'Order Total',
            'Products',
            'Status',
            'Note'
        ]);

        // Add data rows
        foreach ($results as $row) {
            // Get WooCommerce order for additional data
            $order = wc_get_order($row['order_id']);

            // Initialize missing fields with empty values
            $billing_city = '';
            $billing_postcode = '';
            $billing_country = '';
            $products_list = '';

            // Get missing fields from WooCommerce order if it exists
            if ($order) {
                $billing_city = $order->get_billing_city();
                $billing_postcode = $order->get_billing_postcode();
                $billing_country = $order->get_billing_country();

                // Get all products from the order
                $items = $order->get_items();
                $product_names = array();

                foreach ($items as $item_id => $item) {
                    $product = $item->get_product();

                    if (!$product) {
                        continue;
                    }

                    $product_name = $product->get_name();
                    $quantity = $item->get_quantity();
                    
                    // Format: "Product Name (Qty: X)"
                    $product_names[] = sprintf(
                        '%s (Qty: %d)',
                        $product_name,
                        $quantity
                    );
                }

                // Join all products with comma and space
                $products_list = implode(', ', $product_names);
            }

            fputcsv($output, [
                $row['id'],
                $row['created_at'],
                $row['billing_first_name'],
                $row['billing_last_name'],
                $row['billing_phone'],
                $row['email'],
                $row['billing_address_1'],
                $billing_city,
                $row['billing_state'],
                $billing_postcode,
                $billing_country,
                $row['order_total'],
                $products_list,
                $row['status_to'],
                $row['note']
            ]);
        }

        // Close output stream
        fclose($output);

        // Terminate script
        exit;
    }


}