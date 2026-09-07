<?php
/**
 * Product Purchase & Stock Management Admin Page
 * FMB E-Commerce Store
 */

if (!defined('ABSPATH')) {
    exit;
}

function fmb_admin_purchase_stock_page() {
    if (!current_user_can('manage_woocommerce')) {
        wp_die('Unauthorized access');
    }

    global $wpdb;
    fmb_init_purchase_stock_tables();
    
    // Ensure select2 is available for searchable dropdowns
    if (function_exists('wp_enqueue_script')) {
        wp_enqueue_style('woocommerce_admin_styles');
        wp_enqueue_script('wc-enhanced-select');
    }

    $current_tab = sanitize_text_field($_GET['tab'] ?? 'stock');
    $base_url    = admin_url('admin.php?page=fmb-purchase-stock');
    $ajax_nonce  = wp_create_nonce('fmb_purchase_stock_nonce');

    // Retrieve Supplier List for Dropdowns
    $suppliers = fmb_get_all_suppliers();

    // Query WooCommerce products for Inventory Tab
    $stock_filter = sanitize_text_field($_GET['stock_status'] ?? 'all');
    $search_query = sanitize_text_field($_GET['s'] ?? '');
    $cat_filter   = sanitize_text_field($_GET['category'] ?? 'all');
    $paged        = max(1, (int)($_GET['paged'] ?? 1));
    $per_page     = 25;

    // Build Product Stats
    $total_products_count = 0;
    $in_stock_count       = 0;
    $low_stock_count      = 0;
    $out_of_stock_count   = 0;
    $total_inventory_val  = 0.00;
    $total_inventory_units= 0;

    // Fetch all products to calculate accurate KPI cards
    $all_wc_products = wc_get_products(array(
        'limit'   => -1,
        'status'  => 'publish',
        'return'  => 'objects',
    ));

    $inventory_items = array();

    foreach ($all_wc_products as $p) {
        $pid   = $p->get_id();
        $name  = $p->get_name();
        $sku   = $p->get_sku() ?: 'N/A';
        $cats  = wc_get_product_category_list($pid, ', ');
        $sale_price = (float)$p->get_price();
        $cost_price = (float)($p->get_meta('_purchase_cost') ?: ($p->get_meta('_cost_price') ?: 0));

        if ($p->is_type('variable')) {
            $variations = $p->get_available_variations();
            foreach ($variations as $v) {
                $v_obj   = wc_get_product($v['variation_id']);
                if (!$v_obj) continue;
                $v_stock = $v_obj->get_stock_quantity() !== null ? (int)$v_obj->get_stock_quantity() : 0;
                $v_cost  = (float)($v_obj->get_meta('_purchase_cost') ?: ($v_obj->get_meta('_cost_price') ?: $cost_price));
                $v_val   = $v_stock * $v_cost;

                $total_products_count++;
                $total_inventory_units += $v_stock;
                $total_inventory_val   += $v_val;

                if ($v_stock <= 0) {
                    $out_of_stock_count++;
                    $st_label = 'out_of_stock';
                } elseif ($v_stock < 5) {
                    $low_stock_count++;
                    $st_label = 'low_stock';
                } else {
                    $in_stock_count++;
                    $st_label = 'in_stock';
                }

                $inventory_items[] = array(
                    'id'          => $v['variation_id'],
                    'parent_id'   => $pid,
                    'name'        => $name . ' (' . implode(', ', array_values($v['attributes'])) . ')',
                    'sku'         => $v['sku'] ?: $sku,
                    'category'    => wp_strip_all_tags($cats ?: 'General'),
                    'sale_price'  => (float)$v['display_price'],
                    'cost_price'  => $v_cost,
                    'stock'       => $v_stock,
                    'status'      => $st_label,
                    'stock_value' => $v_val,
                    'image'       => $v['image']['thumb_src'] ?? '',
                );
            }
        } else {
            $stock = $p->get_stock_quantity() !== null ? (int)$p->get_stock_quantity() : 0;
            $val   = $stock * $cost_price;

            $total_products_count++;
            $total_inventory_units += $stock;
            $total_inventory_val   += $val;

            if ($stock <= 0) {
                $out_of_stock_count++;
                $st_label = 'out_of_stock';
            } elseif ($stock < 5) {
                $low_stock_count++;
                $st_label = 'low_stock';
            } else {
                $in_stock_count++;
                $st_label = 'in_stock';
            }

            $img_id  = $p->get_image_id();
            $img_url = $img_id ? wp_get_attachment_thumb_url($img_id) : '';

            $inventory_items[] = array(
                'id'          => $pid,
                'parent_id'   => 0,
                'name'        => $name,
                'sku'         => $sku,
                'category'    => wp_strip_all_tags($cats ?: 'General'),
                'sale_price'  => $sale_price,
                'cost_price'  => $cost_price,
                'stock'       => $stock,
                'status'      => $st_label,
                'stock_value' => $val,
                'image'       => $img_url,
            );
        }
    }

    // Filter items based on user search & dropdown filters
    $filtered_inventory = array();
    foreach ($inventory_items as $item) {
        if (!empty($search_query)) {
            if (mb_stripos($item['name'], $search_query) === false &&
                mb_stripos($item['sku'], $search_query) === false) {
                continue;
            }
        }
        if ($stock_filter !== 'all' && $item['status'] !== $stock_filter) {
            continue;
        }
        if ($cat_filter !== 'all' && stripos($item['category'], $cat_filter) === false) {
            continue;
        }
        $filtered_inventory[] = $item;
    }

    $filtered_count = count($filtered_inventory);
    $total_pages    = max(1, ceil($filtered_count / $per_page));
    $offset         = ($paged - 1) * $per_page;
    $paged_inventory= array_slice($filtered_inventory, $offset, $per_page);

    // Fetch Product Categories for Filter Dropdown
    $categories = get_terms(array('taxonomy' => 'product_cat', 'hide_empty' => true));

    // Fetch Purchases for Tab 3 (History)
    $table_purchases = $wpdb->prefix . 'fmb_purchases';
    $purchases_list  = $wpdb->get_results("SELECT * FROM {$table_purchases} ORDER BY id DESC LIMIT 100");

    // Fetch Expenses for Tab 5 and 6
    $table_expenses = $wpdb->prefix . 'fmb_expenses';
    $expenses_list  = fmb_get_expenses();
    $expense_categories = fmb_get_expense_categories();

    // Date filtering for Expense Reports
    $report_start = sanitize_text_field($_GET['report_start'] ?? date('Y-m-01'));
    $report_end   = sanitize_text_field($_GET['report_end'] ?? date('Y-m-t'));

    ?>
    <style>
    .fmb-purchase-stock-wrap {
        max-width: 100% !important;
        width: 100% !important;
        margin: 15px 0 70px 0 !important;
        padding: 0 20px 0 0 !important;
        box-sizing: border-box !important;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        color: #0f172a;
    }
    .fmb-hub-topbar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 12px;
        margin-bottom: 16px;
    }
    .fmb-hub-main-title {
        font-size: 22px;
        font-weight: 800;
        margin: 0;
        color: #0f172a;
        display: flex;
        align-items: center;
        gap: 10px;
    }
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
    .fmb-hub-subtext {
        margin: 4px 0 0;
        font-size: 12.5px;
        color: #64748b;
    }
    .fmb-topbar-actions {
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .fmb-btn-primary-purple {
        background: #4f46e5;
        color: #fff !important;
        border: none;
        padding: 9px 18px;
        border-radius: 8px;
        font-weight: 700;
        font-size: 13px;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        cursor: pointer;
        text-decoration: none;
        transition: background 0.15s;
    }
    .fmb-btn-primary-purple:hover {
        background: #4338ca;
        color: #fff !important;
    }
    .fmb-btn-outline-adjust {
        background: #fff;
        border: 1px solid #cbd5e1;
        color: #334155;
        padding: 8px 14px;
        border-radius: 8px;
        font-weight: 700;
        font-size: 13px;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        transition: all 0.15s;
    }
    .fmb-btn-outline-adjust:hover {
        background: #f1f5f9;
        color: #0f172a;
        border-color: #94a3b8;
    }

    /* Underline Tabs */
    .fmb-underline-tabs {
        display: flex;
        gap: 24px;
        border-bottom: 2px solid #e2e8f0;
        margin-bottom: 24px;
        overflow-x: auto;
        padding-bottom: 0px;
        scrollbar-width: thin;
        scrollbar-color: #cbd5e1 transparent;
    }
    .fmb-underline-tabs::-webkit-scrollbar { height: 4px; }
    .fmb-underline-tabs::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
    .fmb-underline-tabs::-webkit-scrollbar-track { background: transparent; }
    .fmb-underline-tab {
        font-size: 14px;
        font-weight: 600;
        color: #64748b;
        text-decoration: none !important;
        padding: 0 4px 12px 4px;
        border-bottom: 3px solid transparent;
        margin-bottom: -2px;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        white-space: nowrap;
        transition: all 0.15s ease-in-out;
        box-shadow: none !important;
    }
    .fmb-underline-tab:hover { color: #4338ca; }
    .fmb-underline-tab.active { color: #4338ca; font-weight: 700; border-bottom-color: #4338ca; }
    .fmb-underline-tab .tab-num {
        background: #f1f5f9;
        color: #64748b;
        padding: 2px 8px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 700;
        transition: all 0.15s;
    }
    .fmb-underline-tab.active .tab-num {
        background: #e0e7ff;
        color: #4338ca;
        font-weight: 800;
    }

    /* KPI Metric Cards */
    .fmb-kpi-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 14px;
        margin-bottom: 20px;
    }
    .fmb-kpi-card {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 16px;
        display: flex;
        align-items: center;
        gap: 12px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.03);
    }
    .fmb-kpi-card .kpi-icon-wrap {
        width: 44px;
        height: 44px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }
    .fmb-kpi-card .kpi-icon-wrap .dashicons { font-size: 22px; width: 22px; height: 22px; }
    .fmb-kpi-card.card-blue   .kpi-icon-wrap { background: #eff6ff; color: #2563eb; }
    .fmb-kpi-card.card-purple .kpi-icon-wrap { background: #f5f3ff; color: #7c3aed; }
    .fmb-kpi-card.card-green  .kpi-icon-wrap { background: #ecfdf5; color: #059669; }
    .fmb-kpi-card.card-amber  .kpi-icon-wrap { background: #fffbeb; color: #d97706; }
    .fmb-kpi-card.card-rose   .kpi-icon-wrap { background: #fef2f2; color: #dc2626; }
    .fmb-kpi-card .kpi-label { font-size: 11.5px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.4px; display: block; }
    .fmb-kpi-card .kpi-val { font-size: 19px; font-weight: 800; margin: 4px 0 0 0; color: #0f172a; line-height: 1.1; }
    .fmb-kpi-card .kpi-val small { font-size: 13px; font-weight: 600; color: #64748b; }

    /* Filter Bar */
    .fmb-filter-container {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        padding: 12px 16px;
        margin-bottom: 18px;
    }
    .fmb-horizontal-filter-form {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
    }
    .fmb-filter-search-wrap {
        position: relative;
        flex: 1;
        min-width: 240px;
    }
    .fmb-filter-search-wrap .search-icon {
        position: absolute;
        left: 10px;
        top: 50%;
        transform: translateY(-50%);
        color: #94a3b8;
        font-size: 17px;
        width: 17px;
        height: 17px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .fmb-filter-search-input {
        width: 100%;
        height: 38px;
        padding: 0 12px 0 36px;
        border: 1.5px solid #cbd5e1;
        border-radius: 8px;
        font-size: 13px;
        outline: none;
        box-sizing: border-box;
        background: #fff;
        color: #1e293b;
        transition: border-color 0.15s;
    }
    .fmb-filter-search-input:focus {
        border-color: #4f46e5;
    }
    .fmb-filter-select-wrap {
        min-width: 160px;
    }
    .fmb-filter-dropdown {
        height: 38px;
        padding: 0 12px;
        border: 1.5px solid #cbd5e1;
        border-radius: 8px;
        font-size: 12.5px;
        font-weight: 600;
        color: #1e293b;
        background: #fff;
        outline: none;
        cursor: pointer;
        box-sizing: border-box;
        transition: border-color 0.15s;
    }
    .fmb-filter-dropdown:focus {
        border-color: #4f46e5;
    }
    .fmb-btn-dark-apply {
        background: #0f172a;
        color: #fff !important;
        border: none;
        height: 38px;
        padding: 0 18px;
        border-radius: 8px;
        font-weight: 700;
        font-size: 12.5px;
        cursor: pointer;
        transition: background 0.15s;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
    .fmb-btn-dark-apply:hover {
        background: #1e293b;
    }
    .fmb-btn-outline-clear {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        height: 38px;
        padding: 0 14px;
        border: 1.5px solid #cbd5e1;
        border-radius: 8px;
        font-weight: 700;
        font-size: 12.5px;
        color: #64748b !important;
        text-decoration: none;
        background: #fff;
        box-sizing: border-box;
        transition: all 0.15s;
    }
    .fmb-btn-outline-clear:hover {
        background: #f1f5f9;
        color: #0f172a !important;
        border-color: #94a3b8;
    }

    /* Table Card */
    .fmb-table-card {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 1px 4px rgba(0,0,0,0.03);
        width: 100%;
        box-sizing: border-box;
    }
    .fmb-table-responsive {
        width: 100%;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
    .fmb-order-hub-table {
        width: 100%;
        min-width: 1050px;
        border-collapse: collapse;
        font-size: 13px;
    }
    .fmb-order-hub-table thead th {
        background: #f8fafc;
        padding: 14px 16px;
        font-size: 12px;
        font-weight: 800;
        color: #475569;
        border-bottom: 1px solid #e2e8f0;
        text-transform: uppercase;
        letter-spacing: 0.6px;
        text-align: left;
        white-space: nowrap;
    }
    .fmb-order-hub-table tbody td {
        padding: 14px 16px;
        vertical-align: middle;
        border-bottom: 1px solid #f1f5f9;
        transition: background 0.12s ease;
        color: #334155;
    }
    .fmb-order-hub-table tbody tr:hover {
        background: #f8fafc;
    }

    /* Inventory Items Table Elements */
    .fmb-stock-thumb { width: 44px; height: 44px; border-radius: 6px; object-fit: cover; border: 1px solid #e2e8f0; display: block; }
    .fmb-stock-thumb-placeholder { width: 44px; height: 44px; border-radius: 6px; background: #f1f5f9; display: flex; align-items: center; justify-content: center; color: #94a3b8; }
    .fmb-stock-prod-link { font-weight: 700; color: #0f172a; text-decoration: none !important; font-size: 14px; line-height: 1.4; display: block; }
    .fmb-stock-prod-link:hover { color: #4338ca; text-decoration: underline !important; }
    .fmb-stock-sku { font-size: 12px; font-family: monospace; color: #64748b; margin-top: 4px; display: block; }
    .fmb-cat-tag { font-size: 12px; background: #f1f5f9; border: 1px solid #cbd5e1; padding: 4px 8px; border-radius: 6px; color: #334155; font-weight: 600; white-space: nowrap; }
    .fmb-stock-price { font-size: 14px; color: #0f172a; }
    .fmb-cost-price { font-size: 13.5px; font-weight: 700; color: #64748b; }
    .fmb-stock-valuation { font-size: 14px; font-weight: 800; color: #4338ca; }

    .fmb-stock-pill {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        font-size: 12px;
        font-weight: 700;
        padding: 4px 10px;
        border-radius: 6px;
        line-height: 1.3;
        white-space: nowrap;
    }
    .fmb-stock-pill.in   { background: #ecfdf5; color: #059669; border: 1px solid #a7f3d0; }
    .fmb-stock-pill.low  { background: #fffbeb; color: #b45309; border: 1px solid #fde68a; }
    .fmb-stock-pill.out  { background: #fef2f2; color: #dc2626; border: 1px solid #fecdd3; }
    .fmb-stock-pill .dashicons { font-size: 14px; width: 14px; height: 14px; }

    .fmb-btn-xs-adjust { background: #f8fafc; border: 1px solid #cbd5e1; color: #334155 !important; font-size: 12px; font-weight: 700; padding: 6px 12px; border-radius: 6px; cursor: pointer; display: inline-flex; align-items: center; gap: 4px; transition: all 0.15s; text-decoration: none !important; }
    .fmb-btn-xs-adjust:hover { background: #e2e8f0; color: #0f172a !important; }
    .fmb-btn-xs-buy { background: #4f46e5; border: none; color: #fff !important; font-size: 12px; font-weight: 700; padding: 6px 14px; border-radius: 6px; text-decoration: none !important; display: inline-flex; align-items: center; gap: 4px; transition: background 0.15s; }
    .fmb-btn-xs-buy:hover { background: #4338ca; color: #fff !important; }

    /* Pagination */
    .fmb-pagination-bar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 14px 18px;
        border-top: 1px solid #e2e8f0;
        background: #f8fafc;
        font-size: 13px;
        color: #64748b;
        flex-wrap: wrap;
        gap: 10px;
    }
    .fmb-page-links { display: flex; align-items: center; gap: 4px; }
    .fmb-page-links .page-numbers {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 32px;
        height: 32px;
        padding: 0 10px;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        text-decoration: none;
        color: #475569;
        font-weight: 600;
        font-size: 13px;
        background: #fff;
        transition: all 0.15s ease;
        box-sizing: border-box;
    }
    .fmb-page-links .page-numbers:hover { border-color: #cbd5e1; background: #f1f5f9; color: #0f172a; }
    .fmb-page-links .page-numbers.current {
        background: #4338ca;
        color: #fff;
        border-color: #4338ca;
        font-weight: 800;
        box-shadow: 0 1px 2px rgba(67,56,202,0.25);
    }
    .fmb-page-links .page-numbers.dots { border: none; background: transparent; color: #94a3b8; }

    /* New Purchase Voucher Form */
    .fmb-purchase-form-container { max-width: 1040px; margin: 0 auto; }
    .fmb-form-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 24px; box-shadow: 0 1px 4px rgba(0,0,0,0.04); }
    .fmb-card-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e2e8f0; padding-bottom: 14px; margin-bottom: 20px; }
    .fmb-card-header h2 { margin: 0; font-size: 18px; font-weight: 800; color: #0f172a; display: flex; align-items: center; gap: 8px; }
    .fmb-header-badge { font-size: 11px; font-weight: 700; background: #ecfdf5; color: #059669; border: 1px solid #a7f3d0; padding: 3px 9px; border-radius: 20px; }

    .fmb-form-row-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 24px; }
    .fmb-btn-outline-add-sup { background: #f8fafc; border: 1.5px solid #cbd5e1; border-radius: 8px; padding: 0 12px; font-weight: 700; font-size: 12px; color: #4338ca; cursor: pointer; white-space: nowrap; height: 38px; }
    .fmb-btn-outline-add-sup:hover { background: #e0e7ff; }

    /* Repeater Table */
    .fmb-repeater-container { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 16px; margin-bottom: 20px; }
    .fmb-repeater-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
    .fmb-repeater-header h3 { margin: 0; font-size: 14px; font-weight: 800; color: #0f172a; }
    .fmb-btn-sm-add { background: #4f46e5; color: #fff; border: none; padding: 5px 12px; border-radius: 6px; font-size: 12px; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 4px; }
    .fmb-btn-sm-add:hover { background: #4338ca; }

    .fmb-repeater-table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 8px; overflow: hidden; border: 1px solid #e2e8f0; }
    .fmb-repeater-table th { background: #f1f5f9; padding: 10px 12px; font-size: 11px; font-weight: 800; color: #475569; text-transform: uppercase; text-align: left; border-bottom: 1px solid #e2e8f0; }
    .fmb-repeater-table td { padding: 8px 12px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
    .fmb-repeater-select { width: 100%; padding: 7px 10px; border: 1.5px solid #cbd5e1; border-radius: 6px; font-size: 12.5px; }
    .fmb-repeater-input { width: 100%; padding: 7px 10px; border: 1.5px solid #cbd5e1; border-radius: 6px; font-size: 12.5px; font-weight: 700; box-sizing: border-box; }
    .fmb-btn-del-row { background: none; border: none; color: #94a3b8; cursor: pointer; padding: 4px; display: inline-flex; }
    .fmb-btn-del-row:hover { color: #dc2626; }

    /* Bottom Summary */
    .fmb-purchase-bottom-grid { display: grid; grid-template-columns: 1fr 340px; gap: 20px; align-items: start; }
    .fmb-bottom-summary-card { background: #f8fafc; border: 1.5px solid #e2e8f0; border-radius: 10px; padding: 16px; }
    .fmb-summary-row { display: flex; justify-content: space-between; align-items: center; font-size: 13px; color: #475569; margin-bottom: 8px; }
    .fmb-sum-val { font-size: 15px; font-weight: 800; color: #0f172a; }
    .fmb-sum-val.red { color: #dc2626; }
    .fmb-input-sm-right { width: 110px; text-align: right; padding: 5px 8px; border: 1.5px solid #cbd5e1; border-radius: 6px; font-weight: 700; font-size: 13px; }
    .fmb-summary-row.due-row { border-top: 1px dashed #cbd5e1; padding-top: 8px; margin-top: 8px; }
    .fmb-btn-save-purchase { width: 100%; margin-top: 14px; background: #00a669; color: #fff; border: none; padding: 11px 16px; border-radius: 8px; font-size: 14px; font-weight: 800; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; gap: 6px; transition: background 0.15s; }
    .fmb-btn-save-purchase:hover { background: #008f5a; }

    /* History & Vouchers */
    .fmb-voucher-no { font-size: 13.5px; color: #4338ca; font-weight: 800; display: block; }
    .fmb-slip-sub { font-size: 11px; color: #64748b; display: block; }
    .fmb-date-txt { font-size: 12px; color: #475569; }
    .fmb-items-count { font-size: 12px; font-weight: 700; color: #0f172a; }
    .fmb-pur-total { font-size: 13.5px; color: #0f172a; font-weight: 800; }
    .fmb-pur-paid { font-size: 12.5px; color: #16a34a; font-weight: 700; }
    .fmb-pur-due { font-size: 12.5px; font-weight: 800; }
    .fmb-pur-due.red { color: #dc2626; }
    .fmb-pur-due.green { color: #16a34a; }
    .fmb-btn-delete-voucher:hover .dashicons { color: #b91c1c !important; }

    /* Status Pills */
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
    .fmb-status-pill.completed  { background: #ecfdf5; color: #059669; border: 1px solid #a7f3d0; }
    .fmb-status-pill.processing { background: #eff6ff; color: #2563eb; border: 1px solid #bfdbfe; }
    .fmb-status-pill.cancelled  { background: #fef2f2; color: #dc2626; border: 1px solid #fecdd3; }

    /* Action Icons */
    .fmb-act-icon {
        width: 30px;
        height: 30px;
        border-radius: 6px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: #64748b;
        text-decoration: none;
        border: 1px solid #e2e8f0;
        background: #fff;
        cursor: pointer;
        transition: all 0.15s;
    }
    .fmb-act-icon:hover {
        color: #0f172a;
        border-color: #cbd5e1;
        background: #f8fafc;
        transform: translateY(-1px);
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    }
    .fmb-act-icon.view:hover { color: #2563eb; border-color: #bfdbfe; }
    .fmb-act-icon .dashicons { font-size: 15px; width: 15px; height: 15px; }

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
    .fmb-modal-head h3 {
        margin: 0;
        font-size: 15px;
        font-weight: 800;
        color: #0f172a;
        display: flex;
        align-items: center;
        gap: 6px;
    }
    .fmb-modal-close-x {
        background: none;
        border: none;
        font-size: 22px;
        color: #94a3b8;
        cursor: pointer;
        line-height: 1;
        padding: 2px 6px;
    }
    .fmb-modal-close-x:hover { color: #0f172a; }
    .fmb-modal-content { padding: 18px; }
    .fmb-form-lbl {
        font-size: 12px;
        font-weight: 700;
        color: #475569;
        display: block;
        margin-bottom: 4px;
    }
    .fmb-input-control {
        width: 100%;
        padding: 8px 12px;
        border: 1.5px solid #cbd5e1;
        border-radius: 8px;
        font-size: 13px;
        outline: none;
        box-sizing: border-box;
        background: #fff;
        color: #1e293b;
    }
    .fmb-input-control:focus {
        border-color: #4f46e5;
    }
    .fmb-btn-outline-print {
        background: #fff;
        border: 1px solid #cbd5e1;
        padding: 7px 14px;
        border-radius: 6px;
        font-weight: 700;
        font-size: 12px;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    .fmb-btn-outline-print:hover { background: #f1f5f9; color: #0f172a; }

    /* Memo Modal Content */
    .fmb-memo-slip { font-size: 13px; line-height: 1.5; color: #0f172a; }
    .fmb-memo-head-grid { display: flex; justify-content: space-between; border-bottom: 2px solid #e2e8f0; padding-bottom: 12px; margin-bottom: 14px; }
    .fmb-memo-table { width: 100%; border-collapse: collapse; margin: 14px 0; font-size: 12.5px; }
    .fmb-memo-table th { background: #f8fafc; padding: 8px 10px; border-bottom: 1px solid #e2e8f0; text-align: left; font-size: 11px; font-weight: 800; }
    .fmb-memo-table td { padding: 8px 10px; border-bottom: 1px solid #f1f5f9; }
    .fmb-memo-totals { margin-top: 12px; border-top: 1.5px solid #e2e8f0; padding-top: 8px; }
    .fmb-memo-totals .row { display: flex; justify-content: space-between; margin-bottom: 4px; }
    </style>
    <div class="fmb-admin-wrap fmb-purchase-stock-wrap">

        <!-- Top Header Bar -->
        <div class="fmb-hub-topbar" style="margin: 25px 0 15px 0;">
            <div>
                <h1 class="fmb-hub-main-title">
                    📦 Product Purchase &amp; Stock Management
                    <span class="title-count"><?php echo number_format($total_products_count); ?> Items</span>
                </h1>
                <p class="fmb-hub-subtext">Track inventory levels, record purchase vouchers, manage suppliers and monitor valuation</p>
            </div>
            <div class="fmb-topbar-actions" style="display:flex; align-items:center; gap:8px;">
                <button type="button" class="fmb-btn fmb-btn-outline-adjust fmb-open-adjust-modal" data-id="" data-name="">
                    <span class="dashicons dashicons-forms"></span> Quick Adjust Stock
                </button>
                <a href="<?php echo esc_url(add_query_arg('tab', 'new_purchase', $base_url)); ?>" class="fmb-btn fmb-btn-primary-purple">
                    <span class="dashicons dashicons-plus-alt2"></span> New Purchase Voucher
                </a>
            </div>
        </div>

        <!-- Navigation Tabs -->
        <div class="fmb-underline-tabs">
            <a href="<?php echo esc_url(add_query_arg('tab', 'stock', $base_url)); ?>" class="fmb-underline-tab <?php echo $current_tab === 'stock' ? 'active' : ''; ?>">
                📊 Stock &amp; Inventory
                <span class="tab-num"><?php echo (int)$total_products_count; ?></span>
            </a>
            <a href="<?php echo esc_url(add_query_arg('tab', 'new_purchase', $base_url)); ?>" class="fmb-underline-tab <?php echo $current_tab === 'new_purchase' ? 'active' : ''; ?>">
                ➕ New Purchase
            </a>
            <a href="<?php echo esc_url(add_query_arg('tab', 'history', $base_url)); ?>" class="fmb-underline-tab <?php echo $current_tab === 'history' ? 'active' : ''; ?>">
                📑 Purchase History
                <span class="tab-num"><?php echo count($purchases_list); ?></span>
            </a>
            <a href="<?php echo esc_url(add_query_arg('tab', 'suppliers', $base_url)); ?>" class="fmb-underline-tab <?php echo $current_tab === 'suppliers' ? 'active' : ''; ?>">
                👥 Suppliers
                <span class="tab-num"><?php echo count($suppliers); ?></span>
            </a>
        </div>

        <!-- TAB 1: Stock & Inventory -->
        <?php if ($current_tab === 'stock') : ?>

            <!-- KPI Metric Cards -->
            <div class="fmb-kpi-grid">
                <div class="fmb-kpi-card card-blue">
                    <div class="kpi-icon-wrap"><span class="dashicons dashicons-archive"></span></div>
                    <div class="kpi-content">
                        <span class="kpi-label">Total Inventory Units</span>
                        <h2 class="kpi-val"><?php echo number_format($total_inventory_units); ?> <small>pcs</small></h2>
                    </div>
                </div>

                <div class="fmb-kpi-card card-purple">
                    <div class="kpi-icon-wrap"><span class="dashicons dashicons-money-alt"></span></div>
                    <div class="kpi-content">
                        <span class="kpi-label">Total Stock Valuation</span>
                        <h2 class="kpi-val">৳ <?php echo number_format($total_inventory_val, 0); ?></h2>
                    </div>
                </div>

                <div class="fmb-kpi-card card-green">
                    <div class="kpi-icon-wrap"><span class="dashicons dashicons-yes-alt"></span></div>
                    <div class="kpi-content">
                        <span class="kpi-label">In Stock Items</span>
                        <h2 class="kpi-val"><?php echo number_format($in_stock_count); ?></h2>
                    </div>
                </div>

                <div class="fmb-kpi-card card-amber">
                    <div class="kpi-icon-wrap"><span class="dashicons dashicons-warning"></span></div>
                    <div class="kpi-content">
                        <span class="kpi-label">Low Stock (&lt; 5 pcs)</span>
                        <h2 class="kpi-val"><?php echo number_format($low_stock_count); ?></h2>
                    </div>
                </div>

                <div class="fmb-kpi-card card-rose">
                    <div class="kpi-icon-wrap"><span class="dashicons dashicons-dismiss"></span></div>
                    <div class="kpi-content">
                        <span class="kpi-label">Out of Stock</span>
                        <h2 class="kpi-val"><?php echo number_format($out_of_stock_count); ?></h2>
                    </div>
                </div>
            </div>

            <!-- Filter Bar -->
            <div class="fmb-filter-container">
                <form method="get" action="<?php echo esc_url($base_url); ?>" class="fmb-horizontal-filter-form">
                    <input type="hidden" name="page" value="fmb-purchase-stock">
                    <input type="hidden" name="tab" value="stock">

                    <div class="fmb-filter-search-wrap">
                        <span class="dashicons dashicons-search search-icon"></span>
                        <input type="text" name="s" value="<?php echo esc_attr($search_query); ?>" placeholder="Search product by title, SKU..." class="fmb-filter-search-input">
                    </div>

                    <div class="fmb-filter-select-wrap">
                        <select name="stock_status" class="fmb-filter-dropdown" onchange="this.form.submit()">
                            <option value="all" <?php selected($stock_filter, 'all'); ?>>All Stock Status</option>
                            <option value="in_stock" <?php selected($stock_filter, 'in_stock'); ?>>In Stock (5+)</option>
                            <option value="low_stock" <?php selected($stock_filter, 'low_stock'); ?>>Low Stock (&lt; 5)</option>
                            <option value="out_of_stock" <?php selected($stock_filter, 'out_of_stock'); ?>>Out of Stock (0)</option>
                        </select>
                    </div>

                    <?php if (!empty($categories) && !is_wp_error($categories)) : ?>
                        <div class="fmb-filter-select-wrap">
                            <select name="category" class="fmb-filter-dropdown" onchange="this.form.submit()">
                                <option value="all" <?php selected($cat_filter, 'all'); ?>>All Categories</option>
                                <?php foreach ($categories as $cat) : ?>
                                    <option value="<?php echo esc_attr($cat->name); ?>" <?php selected($cat_filter, $cat->name); ?>>
                                        <?php echo esc_html($cat->name); ?> (<?php echo (int)$cat->count; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>

                    <button type="submit" class="fmb-btn fmb-btn-dark-apply">Filter</button>
                    <?php if (!empty($search_query) || $stock_filter !== 'all' || $cat_filter !== 'all') : ?>
                        <a href="<?php echo esc_url(add_query_arg('tab', 'stock', $base_url)); ?>" class="fmb-btn fmb-btn-outline-clear" title="Reset Filters">
                            Reset
                        </a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Inventory Table Card -->
            <div class="fmb-table-card">
                <div class="fmb-table-responsive">
                    <table class="fmb-order-hub-table">
                        <thead>
                            <tr>
                                <th style="width:50px;">Image</th>
                                <th style="min-width:220px;">Product &amp; SKU</th>
                                <th style="min-width:140px;">Category</th>
                                <th style="min-width:110px;">Selling Price</th>
                                <th style="min-width:110px;">Unit Cost</th>
                                <th style="min-width:130px;">Stock Qty</th>
                                <th style="min-width:120px;">Stock Value</th>
                                <th style="min-width:150px; text-align:right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($paged_inventory)) : ?>
                                <tr>
                                    <td colspan="8" style="text-align:center; padding:50px 20px; color:#64748b;">
                                        <span class="dashicons dashicons-archive" style="font-size:36px; width:36px; height:36px; color:#cbd5e1; display:block; margin:0 auto 10px;"></span>
                                        <strong>No products found matching your filter criteria.</strong>
                                    </td>
                                </tr>
                            <?php else : ?>
                                <?php foreach ($paged_inventory as $item) : ?>
                                    <tr>
                                        <!-- Product Image -->
                                        <td>
                                            <?php if (!empty($item['image'])) : ?>
                                                <img src="<?php echo esc_url($item['image']); ?>" alt="" class="fmb-stock-thumb">
                                            <?php else : ?>
                                                <div class="fmb-stock-thumb-placeholder"><span class="dashicons dashicons-format-image"></span></div>
                                            <?php endif; ?>
                                        </td>

                                        <!-- Name & SKU -->
                                        <td>
                                            <div class="fmb-stock-title-wrap">
                                                <a href="<?php echo esc_url(get_edit_post_link($item['parent_id'] ?: $item['id'])); ?>" target="_blank" class="fmb-stock-prod-link">
                                                    <?php echo esc_html($item['name']); ?>
                                                </a>
                                                <span class="fmb-stock-sku">SKU: <?php echo esc_html($item['sku']); ?></span>
                                            </div>
                                        </td>

                                        <!-- Category -->
                                        <td>
                                            <span class="fmb-cat-tag"><?php echo esc_html($item['category']); ?></span>
                                        </td>

                                        <!-- Selling Price -->
                                        <td>
                                            <strong class="fmb-stock-price">৳ <?php echo number_format($item['sale_price'], 0); ?></strong>
                                        </td>

                                        <!-- Cost Price -->
                                        <td>
                                            <span class="fmb-cost-price" title="Recent purchase cost">
                                                ৳ <?php echo number_format($item['cost_price'], 0); ?>
                                            </span>
                                        </td>

                                        <!-- Stock Qty with Badges -->
                                        <td>
                                            <?php if ($item['stock'] <= 0) : ?>
                                                <span class="fmb-stock-pill out" title="Out of Stock">
                                                    <span class="dashicons dashicons-dismiss"></span> 0 pcs (Out)
                                                </span>
                                            <?php elseif ($item['stock'] < 5) : ?>
                                                <span class="fmb-stock-pill low" title="Low Stock Warning">
                                                    <span class="dashicons dashicons-warning"></span> <?php echo (int)$item['stock']; ?> pcs (Low)
                                                </span>
                                            <?php else : ?>
                                                <span class="fmb-stock-pill in" title="Adequate Stock">
                                                    <span class="dashicons dashicons-yes"></span> <?php echo (int)$item['stock']; ?> pcs
                                                </span>
                                            <?php endif; ?>
                                        </td>

                                        <!-- Stock Value -->
                                        <td>
                                            <strong class="fmb-stock-valuation">৳ <?php echo number_format($item['stock_value'], 0); ?></strong>
                                        </td>

                                        <!-- Actions -->
                                        <td style="text-align:right;">
                                            <div style="display:flex; align-items:center; justify-content:flex-end; gap:6px;">
                                                <button type="button" class="fmb-btn-xs-adjust fmb-open-adjust-modal" data-id="<?php echo $item['id']; ?>" data-name="<?php echo esc_attr($item['name']); ?>" data-stock="<?php echo (int)$item['stock']; ?>" title="Adjust Stock (Damage, Loss, Correction)">
                                                    <span class="dashicons dashicons-forms"></span> Adjust
                                                </button>
                                                <a href="<?php echo esc_url(add_query_arg(array('tab' => 'new_purchase', 'prod_id' => $item['id']), $base_url)); ?>" class="fmb-btn-xs-buy" title="Add Purchase for this item">
                                                    <span class="dashicons dashicons-plus-alt2"></span> Buy
                                                </a>
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
                            Showing page <?php echo $paged; ?> of <?php echo $total_pages; ?> (<?php echo number_format($filtered_count); ?> items total)
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

        <!-- TAB 2: New Purchase Voucher -->
        <?php elseif ($current_tab === 'new_purchase') : ?>

            <div class="fmb-purchase-form-container">
                <div class="fmb-form-card">
                    <div class="fmb-card-header">
                        <h2><span class="dashicons dashicons-cart"></span> Record New Purchase Voucher</h2>
                        <span class="fmb-header-badge">Auto Stock Sync Active</span>
                    </div>

                    <form id="fmb-new-purchase-form" onsubmit="return false;">
                        <!-- Supplier & Invoice Details -->
                        <div class="fmb-form-row-grid">
                            <div class="fmb-form-group">
                                <label class="fmb-form-lbl">Supplier Name *</label>
                                <div style="display:flex; gap:6px;">
                                    <select id="purchase-supplier-id" class="fmb-input-control" style="flex:1;">
                                        <option value="0">-- Select Existing Supplier --</option>
                                        <?php foreach ($suppliers as $sup) : ?>
                                            <option value="<?php echo $sup->id; ?>"><?php echo esc_html($sup->name); ?><?php echo !empty($sup->phone) ? ' (' . esc_html($sup->phone) . ')' : ''; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="button" class="fmb-btn fmb-btn-outline-add-sup" id="btn-quick-new-sup" title="Create New Supplier">+ New</button>
                                </div>
                                <input type="text" id="purchase-new-supplier-name" class="fmb-input-control" placeholder="Or type new supplier name directly..." style="margin-top:6px;">
                            </div>

                            <div class="fmb-form-group">
                                <label class="fmb-form-lbl">Purchase Date *</label>
                                <input type="date" id="purchase-date" class="fmb-input-control" value="<?php echo current_time('Y-m-d'); ?>">
                            </div>

                            <div class="fmb-form-group">
                                <label class="fmb-form-lbl">Challan / Invoice Slip No</label>
                                <input type="text" id="purchase-slip-no" class="fmb-input-control" placeholder="e.g. CH-89421">
                            </div>

                            <div class="fmb-form-group">
                                <label class="fmb-form-lbl">Payment Method</label>
                                <select id="purchase-payment-method" class="fmb-input-control">
                                    <option value="cash">Cash in Hand</option>
                                    <option value="bkash">bKash</option>
                                    <option value="nagad">Nagad</option>
                                    <option value="bank">Bank Transfer</option>
                                    <option value="due">Full Due / Credit</option>
                                </select>
                            </div>
                        </div>

                        <!-- Purchase Items Table Repeater -->
                        <div class="fmb-repeater-container">
                            <div class="fmb-repeater-header">
                                <h3>Product Items to Purchase</h3>
                                <button type="button" class="fmb-btn fmb-btn-sm-add" id="btn-add-repeater-row">
                                    <span class="dashicons dashicons-plus-alt2"></span> Add Product Row
                                </button>
                            </div>

                            <table class="fmb-repeater-table" id="purchase-items-table">
                                <thead>
                                    <tr>
                                        <th style="width:40%;">Product / Variation</th>
                                        <th style="width:15%;">Current Stock</th>
                                        <th style="width:15%;">Purchase Qty</th>
                                        <th style="width:15%;">Unit Cost (৳)</th>
                                        <th style="width:15%;">Subtotal (৳)</th>
                                        <th style="width:50px;"></th>
                                    </tr>
                                </thead>
                                <tbody id="purchase-items-body">
                                    <!-- Dynamic Rows will be inserted here -->
                                </tbody>
                            </table>
                        </div>

                        <!-- Calculations and Bottom Bar -->
                        <div class="fmb-purchase-bottom-grid">
                            <div class="fmb-bottom-notes">
                                <label class="fmb-form-lbl">Voucher Notes / Supplier Instructions:</label>
                                <textarea id="purchase-notes" rows="3" class="fmb-input-control" placeholder="Optional notes for this purchase (e.g. Paid via bKash personal, goods received at Dhanmondi warehouse)..."></textarea>
                            </div>

                            <div class="fmb-bottom-summary-card">
                                <div class="fmb-summary-row">
                                    <span>Total Items:</span>
                                    <strong id="summary-total-qty">0 pcs</strong>
                                </div>
                                <div class="fmb-summary-row">
                                    <span>Grand Total:</span>
                                    <strong class="fmb-sum-val" id="summary-grand-total">৳ 0</strong>
                                </div>
                                <div class="fmb-summary-row" style="margin-top:6px;">
                                    <span>Paid Amount:</span>
                                    <input type="number" id="purchase-paid-amount" class="fmb-input-sm-right" value="0" min="0" step="any" placeholder="0">
                                </div>
                                <div class="fmb-summary-row due-row">
                                    <span>Due Amount:</span>
                                    <strong class="fmb-sum-val red" id="summary-due-amount">৳ 0</strong>
                                </div>

                                <button type="button" class="fmb-btn fmb-btn-save-purchase" id="btn-submit-purchase">
                                    <span class="dashicons dashicons-saved"></span> Save Purchase &amp; Increase Stock
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

        <!-- TAB 3: Purchase History -->
        <?php elseif ($current_tab === 'history') : ?>

            <div class="fmb-table-card">
                <div class="fmb-table-responsive">
                    <table class="fmb-order-hub-table">
                        <thead>
                            <tr>
                                <th style="min-width:130px;">Voucher No</th>
                                <th style="min-width:110px;">Date</th>
                                <th style="min-width:180px;">Supplier</th>
                                <th style="min-width:90px;">Total Items</th>
                                <th style="min-width:110px;">Grand Total</th>
                                <th style="min-width:100px;">Paid</th>
                                <th style="min-width:100px;">Due</th>
                                <th style="min-width:100px;">Status</th>
                                <th style="min-width:120px; text-align:right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($purchases_list)) : ?>
                                <tr>
                                    <td colspan="9" style="text-align:center; padding:50px 20px; color:#64748b;">
                                        <span class="dashicons dashicons-cart" style="font-size:36px; width:36px; height:36px; color:#cbd5e1; display:block; margin:0 auto 10px;"></span>
                                        <strong>No purchase records found yet.</strong>
                                        <p style="margin:6px 0 0; font-size:12.5px;">Click "New Purchase Voucher" to record your first product purchase.</p>
                                    </td>
                                </tr>
                            <?php else : ?>
                                <?php foreach ($purchases_list as $pur) : ?>
                                    <tr>
                                        <!-- Voucher No -->
                                        <td>
                                            <strong class="fmb-voucher-no">#<?php echo esc_html($pur->purchase_no); ?></strong>
                                            <?php if (!empty($pur->invoice_slip_no)) : ?>
                                                <span class="fmb-slip-sub">Slip: <?php echo esc_html($pur->invoice_slip_no); ?></span>
                                            <?php endif; ?>
                                        </td>

                                        <!-- Date -->
                                        <td>
                                            <span class="fmb-date-txt"><?php echo esc_html(date('d M, Y', strtotime($pur->purchase_date))); ?></span>
                                        </td>

                                        <!-- Supplier -->
                                        <td>
                                            <div class="fmb-sup-cell">
                                                <strong><?php echo esc_html($pur->supplier_name); ?></strong>
                                            </div>
                                        </td>

                                        <!-- Items Count -->
                                        <td>
                                            <span class="fmb-items-count"><?php echo (int)$pur->total_items; ?> pcs</span>
                                        </td>

                                        <!-- Grand Total -->
                                        <td>
                                            <strong class="fmb-pur-total">৳ <?php echo number_format($pur->total_amount, 0); ?></strong>
                                        </td>

                                        <!-- Paid -->
                                        <td>
                                            <span class="fmb-pur-paid">৳ <?php echo number_format($pur->paid_amount, 0); ?></span>
                                        </td>

                                        <!-- Due -->
                                        <td>
                                            <span class="fmb-pur-due <?php echo $pur->due_amount > 0 ? 'red' : 'green'; ?>">
                                                ৳ <?php echo number_format($pur->due_amount, 0); ?>
                                            </span>
                                        </td>

                                        <!-- Payment Status -->
                                        <td>
                                            <?php if ($pur->payment_status === 'paid') : ?>
                                                <span class="fmb-status-pill completed">PAID</span>
                                            <?php elseif ($pur->payment_status === 'partial') : ?>
                                                <span class="fmb-status-pill processing">PARTIAL</span>
                                            <?php else : ?>
                                                <span class="fmb-status-pill cancelled">DUE</span>
                                            <?php endif; ?>
                                        </td>

                                        <!-- Actions -->
                                        <td style="text-align:right;">
                                            <div style="display:flex; align-items:center; justify-content:flex-end; gap:6px;">
                                                <button type="button" class="fmb-act-icon view fmb-btn-view-memo" data-id="<?php echo $pur->id; ?>" title="View Memo / Slip">
                                                    <span class="dashicons dashicons-visibility"></span>
                                                </button>
                                                <button type="button" class="fmb-act-icon fmb-btn-delete-voucher" data-id="<?php echo $pur->id; ?>" data-no="<?php echo esc_attr($pur->purchase_no); ?>" title="Delete Voucher & Revert Stock">
                                                    <span class="dashicons dashicons-trash" style="color:#ef4444;"></span>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        <!-- TAB 4: Suppliers -->
        <?php elseif ($current_tab === 'suppliers') : ?>

            <div class="fmb-suppliers-wrap">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                    <h2 style="margin:0; font-size:17px; font-weight:800; color:#0f172a;">Suppliers Directory &amp; Ledger</h2>
                    <button type="button" class="fmb-btn fmb-btn-primary-purple" id="btn-open-supplier-modal">
                        <span class="dashicons dashicons-plus-alt2"></span> Add New Supplier
                    </button>
                </div>

                <div class="fmb-table-card">
                    <div class="fmb-table-responsive">
                        <table class="fmb-order-hub-table">
                            <thead>
                                <tr>
                                    <th style="min-width:180px;">Supplier / Shop Name</th>
                                    <th style="min-width:150px;">Company</th>
                                    <th style="min-width:130px;">Phone</th>
                                    <th style="min-width:180px;">Address</th>
                                    <th style="min-width:120px;">Total Purchases</th>
                                    <th style="min-width:110px;">Total Paid</th>
                                    <th style="min-width:110px;">Total Due</th>
                                    <th style="min-width:100px; text-align:right;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($suppliers)) : ?>
                                    <tr>
                                        <td colspan="7" style="text-align:center; padding:50px 20px; color:#64748b;">
                                            <span class="dashicons dashicons-admin-users" style="font-size:36px; width:36px; height:36px; color:#cbd5e1; display:block; margin:0 auto 10px;"></span>
                                            <strong>No suppliers registered yet.</strong>
                                            <p style="margin:6px 0 0; font-size:12.5px;">Click "Add New Supplier" to record your vendor information.</p>
                                        </td>
                                    </tr>
                                <?php else : ?>
                                    <?php foreach ($suppliers as $s) : ?>
                                        <tr>
                                            <td>
                                                <strong style="color:#0f172a; font-size:13.5px;"><?php echo esc_html($s->name); ?></strong>
                                            </td>
                                            <td>
                                                <span><?php echo esc_html($s->company ?: '-'); ?></span>
                                            </td>
                                            <td>
                                                <span style="font-weight:700; color:#4338ca;"><?php echo esc_html($s->phone ?: '-'); ?></span>
                                            </td>
                                            <td>
                                                <span style="font-size:12px; color:#475569;"><?php echo esc_html($s->address ?: '-'); ?></span>
                                            </td>
                                            <td>
                                                <strong>৳ <?php echo number_format($s->total_purchases, 0); ?></strong>
                                            </td>
                                            <td>
                                                <span style="color:#16a34a; font-weight:700;">৳ <?php echo number_format($s->total_paid, 0); ?></span>
                                            </td>
                                            <td>
                                                <strong style="color:<?php echo $s->total_due > 0 ? '#dc2626' : '#16a34a'; ?>;">
                                                    ৳ <?php echo number_format($s->total_due, 0); ?>
                                                </strong>
                                            </td>
                                            <td style="text-align:right;">
                                                <div style="display:flex; align-items:center; justify-content:flex-end; gap:6px;">
                                                    <button type="button" class="fmb-act-icon fmb-btn-pay-supplier" data-id="<?php echo $s->id; ?>" data-name="<?php echo esc_attr($s->name); ?>" data-due="<?php echo $s->total_due; ?>" title="Make Payment">
                                                        <span class="dashicons dashicons-money-alt" style="color:#16a34a;"></span>
                                                    </button>
                                                    <button type="button" class="fmb-act-icon fmb-btn-view-ledger" data-id="<?php echo $s->id; ?>" data-name="<?php echo esc_attr($s->name); ?>" title="View Ledger">
                                                        <span class="dashicons dashicons-media-document" style="color:#4338ca;"></span>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        <?php endif; ?>

    </div>

    <!-- MODAL: Pay Supplier -->
    <div class="fmb-modal-backdrop" id="fmb-pay-supplier-modal" style="display:none;">
        <div class="fmb-modal-box" style="max-width: 450px;">
            <div class="fmb-modal-head">
                <h3><span class="dashicons dashicons-money-alt"></span> Pay Supplier</h3>
                <button type="button" class="fmb-modal-close-x" id="fmb-close-pay-modal">&times;</button>
            </div>
            <div class="fmb-modal-content">
                <form id="fmb-pay-supplier-form" onsubmit="return false;">
                    <input type="hidden" id="pay-sup-id" value="">
                    
                    <div style="margin-bottom:15px; padding:12px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px;">
                        <span style="display:block; font-size:12px; color:#64748b;">Supplier Name</span>
                        <strong id="pay-sup-name" style="font-size:15px; color:#0f172a;">-</strong>
                        <div style="margin-top:6px; font-size:13px;">Current Due: <strong id="pay-sup-due" style="color:#dc2626;">৳ 0</strong></div>
                    </div>

                    <div class="fmb-form-group">
                        <label class="fmb-form-lbl">Payment Date *</label>
                        <input type="date" id="pay-sup-date" class="fmb-input-control" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>

                    <div class="fmb-form-group" style="margin-top:12px;">
                        <label class="fmb-form-lbl">Payment Amount (৳) *</label>
                        <input type="number" id="pay-sup-amount" class="fmb-input-control" value="0" min="1" step="any" required>
                    </div>

                    <div class="fmb-form-group" style="margin-top:12px;">
                        <label class="fmb-form-lbl">Payment Method *</label>
                        <select id="pay-sup-method" class="fmb-input-control">
                            <option value="cash">Cash</option>
                            <option value="bank">Bank Transfer</option>
                            <option value="bkash">bKash</option>
                            <option value="nagad">Nagad</option>
                            <option value="check">Check</option>
                        </select>
                    </div>

                    <div class="fmb-form-group" style="margin-top:12px;">
                        <label class="fmb-form-lbl">Reference / Transaction ID</label>
                        <input type="text" id="pay-sup-ref" class="fmb-input-control" placeholder="Optional">
                    </div>

                    <div class="fmb-form-group" style="margin-top:12px;">
                        <label class="fmb-form-lbl">Notes</label>
                        <textarea id="pay-sup-notes" rows="2" class="fmb-input-control" placeholder="Optional notes..."></textarea>
                    </div>

                    <div style="margin-top:18px; text-align:right;">
                        <button type="button" class="fmb-btn fmb-btn-primary-purple" id="btn-submit-sup-payment">
                            <span class="dashicons dashicons-yes"></span> Confirm Payment
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL: Supplier Ledger -->
    <div class="fmb-modal-backdrop" id="fmb-supplier-ledger-modal" style="display:none;">
        <div class="fmb-modal-box" style="max-width: 800px; overflow: hidden; border: 1px solid rgba(255,255,255,0.2); box-shadow: 0 20px 40px rgba(0,0,0,0.2);">
            <div class="fmb-modal-head" style="background: linear-gradient(135deg, #1e293b, #0f172a); color: #fff; border-bottom: none;">
                <h3 style="color: #fff;"><span class="dashicons dashicons-media-document" style="color: #60a5fa;"></span> Supplier Account Ledger</h3>
                <button type="button" class="fmb-modal-close-x" id="fmb-close-ledger-modal" style="color: #94a3b8; transition: 0.2s;">&times;</button>
            </div>
            <div class="fmb-modal-content" style="background: #f8fafc; padding: 25px;">
                <div style="margin-bottom:20px; padding:16px 20px; background:#fff; border:1px solid #e2e8f0; border-radius:10px; display:flex; justify-content:space-between; align-items:center; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
                    <div style="display: flex; gap: 15px; align-items: center;">
                        <div style="width: 48px; height: 48px; background: #e0e7ff; color: #4338ca; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 20px; font-weight: bold;">
                            <span class="dashicons dashicons-businessman"></span>
                        </div>
                        <div>
                            <span style="display:block; font-size:12px; font-weight:600; color:#64748b; text-transform:uppercase; letter-spacing:0.5px;">Supplier Profile</span>
                            <strong id="ledger-sup-name" style="font-size:18px; color:#0f172a; font-weight:800;">-</strong>
                        </div>
                    </div>
                    <div style="text-align:right; background: #fef2f2; padding: 10px 16px; border-radius: 8px; border: 1px solid #fecaca;">
                        <span style="display:block; font-size:11px; font-weight:700; color:#b91c1c; text-transform:uppercase; letter-spacing:0.5px;">Current Outstanding Due</span>
                        <strong id="ledger-sup-due" style="font-size:22px; color:#dc2626; font-weight:900;">৳ 0</strong>
                    </div>
                </div>

                <div class="fmb-table-responsive" style="max-height: 420px; overflow-y:auto; border:1px solid #e2e8f0; border-radius:10px; background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                    <table class="fmb-order-hub-table fmb-memo-table" style="margin:0; width: 100%;">
                        <thead style="position:sticky; top:0; z-index:10; background: #f1f5f9;">
                            <tr>
                                <th style="width:110px; font-weight: 800; color:#334155;">Date</th>
                                <th style="font-weight: 800; color:#334155;">Transaction Details</th>
                                <th style="width:120px; text-align:right; font-weight: 800; color:#334155;">Bill / Purchase</th>
                                <th style="width:120px; text-align:right; font-weight: 800; color:#334155;">Paid / Credit</th>
                                <th style="width:130px; text-align:right; font-weight: 800; color:#334155;">Running Balance</th>
                            </tr>
                        </thead>
                        <tbody id="fmb-ledger-body">
                            <tr><td colspan="5" style="text-align:center; padding: 30px;">
                                <span class="dashicons dashicons-update spinning" style="font-size:30px; color:#94a3b8;"></span>
                                <div style="margin-top:10px; color:#64748b; font-weight:600;">Loading Ledger...</div>
                            </td></tr>
                        </tbody>
                    </table>
                </div>
                
                <div style="margin-top:20px; display:flex; justify-content:flex-end;">
                    <button type="button" class="fmb-btn fmb-btn-outline-print" onclick="window.print();" style="background:#fff; border:1.5px solid #cbd5e1; color:#0f172a; border-radius:8px; padding: 10px 18px; font-weight:700;">
                        <span class="dashicons dashicons-printer" style="color:#4338ca;"></span> Print Ledger Statement
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL 1: Quick Stock Adjustment -->
    <div class="fmb-modal-backdrop" id="fmb-adjust-modal" style="display:none;">
        <div class="fmb-modal-box" style="max-width: 480px;">
            <div class="fmb-modal-head">
                <h3><span class="dashicons dashicons-forms"></span> Quick Stock Adjustment</h3>
                <button type="button" class="fmb-modal-close-x" id="fmb-close-adjust-modal">&times;</button>
            </div>
            <div class="fmb-modal-content">
                <form id="fmb-adjust-stock-form" onsubmit="return false;">
                    <div class="fmb-form-group">
                        <label class="fmb-form-lbl">Select Product *</label>
                        <select id="adjust-product-id" class="fmb-input-control" style="width:100%;">
                            <option value="">-- Choose Product --</option>
                            <?php foreach ($inventory_items as $item) : ?>
                                <option value="<?php echo $item['id']; ?>" data-name="<?php echo esc_attr($item['name']); ?>" data-stock="<?php echo (int)$item['stock']; ?>">
                                    <?php echo esc_html($item['name']); ?> (Current: <?php echo (int)$item['stock']; ?> pcs)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="fmb-form-group" style="margin-top:12px;">
                        <label class="fmb-form-lbl">Adjustment Action *</label>
                        <div style="display:flex; gap:10px;">
                            <label style="display:flex; align-items:center; gap:4px; font-size:13px; font-weight:700; cursor:pointer;">
                                <input type="radio" name="adjust_type" value="add" checked> ➕ Add Stock (Increase)
                            </label>
                            <label style="display:flex; align-items:center; gap:4px; font-size:13px; font-weight:700; cursor:pointer;">
                                <input type="radio" name="adjust_type" value="reduce"> ➖ Deduct Stock (Decrease)
                            </label>
                        </div>
                    </div>

                    <div class="fmb-form-group" style="margin-top:12px;">
                        <label class="fmb-form-lbl">Quantity to Adjust (pcs) *</label>
                        <input type="number" id="adjust-qty" class="fmb-input-control" value="1" min="1" step="1">
                    </div>

                    <div class="fmb-form-group" style="margin-top:12px;">
                        <label class="fmb-form-lbl">Reason</label>
                        <select id="adjust-reason" class="fmb-input-control">
                            <option value="manual_correction">Manual Inventory Count Correction</option>
                            <option value="damaged">Damaged / Broken Goods</option>
                            <option value="lost">Lost / Missing Item</option>
                            <option value="customer_return">Customer Return Restock</option>
                            <option value="gift_sample">Marketing Sample / Gift</option>
                        </select>
                    </div>

                    <div class="fmb-form-group" style="margin-top:12px;">
                        <label class="fmb-form-lbl">Notes / Comments</label>
                        <textarea id="adjust-notes" rows="2" class="fmb-input-control" placeholder="Optional reason details..."></textarea>
                    </div>

                    <div style="margin-top:18px; text-align:right;">
                        <button type="button" class="fmb-btn fmb-btn-primary-purple" id="btn-save-adjust-stock">
                            <span class="dashicons dashicons-yes"></span> Apply Adjustment
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL 2: Add New Supplier -->
    <div class="fmb-modal-backdrop" id="fmb-supplier-modal" style="display:none;">
        <div class="fmb-modal-box" style="max-width: 480px;">
            <div class="fmb-modal-head">
                <h3><span class="dashicons dashicons-admin-users"></span> Register Supplier</h3>
                <button type="button" class="fmb-modal-close-x" id="fmb-close-supplier-modal">&times;</button>
            </div>
            <div class="fmb-modal-content">
                <form id="fmb-new-supplier-form" onsubmit="return false;">
                    <div class="fmb-form-group">
                        <label class="fmb-form-lbl">Supplier / Contact Person Name *</label>
                        <input type="text" id="sup-modal-name" class="fmb-input-control" required placeholder="e.g. Rahim Chowdhury">
                    </div>
                    <div class="fmb-form-group" style="margin-top:10px;">
                        <label class="fmb-form-lbl">Company / Shop Name</label>
                        <input type="text" id="sup-modal-company" class="fmb-input-control" placeholder="e.g. Dhaka Garments & Trading">
                    </div>
                    <div class="fmb-form-group" style="margin-top:10px;">
                        <label class="fmb-form-lbl">Phone Number</label>
                        <input type="text" id="sup-modal-phone" class="fmb-input-control" placeholder="e.g. 017XXXXXXXX">
                    </div>
                    <div class="fmb-form-group" style="margin-top:10px;">
                        <label class="fmb-form-lbl">Email Address</label>
                        <input type="email" id="sup-modal-email" class="fmb-input-control" placeholder="e.g. supplier@example.com">
                    </div>
                    <div class="fmb-form-group" style="margin-top:10px;">
                        <label class="fmb-form-lbl">Address / Market Location</label>
                        <textarea id="sup-modal-address" rows="2" class="fmb-input-control" placeholder="e.g. Shop 24, Islampur Market, Dhaka"></textarea>
                    </div>

                    <div style="margin-top:18px; text-align:right;">
                        <button type="button" class="fmb-btn fmb-btn-primary-purple" id="btn-save-modal-supplier">
                            <span class="dashicons dashicons-yes"></span> Save Supplier
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL 3: View Purchase Memo / Slip -->
    <div class="fmb-modal-backdrop" id="fmb-memo-modal" style="display:none;">
        <div class="fmb-modal-box" style="max-width: 600px;">
            <div class="fmb-modal-head">
                <h3><span class="dashicons dashicons-media-document"></span> Purchase Memo Voucher</h3>
                <button type="button" class="fmb-modal-close-x" id="fmb-close-memo-modal">&times;</button>
            </div>
            <div class="fmb-modal-content" id="fmb-memo-body">
                <!-- Dynamically loaded memo slip -->
            </div>
            <div style="padding:12px 18px; background:#f8fafc; border-top:1px solid #e2e8f0; text-align:right;">
                <button type="button" class="fmb-btn fmb-btn-outline-print" onclick="window.print();">
                    <span class="dashicons dashicons-printer"></span> Print Voucher
                </button>
            </div>
        </div>
    </div>



    <!-- Client-side Script for Interactivity -->
    <script>
    (function($){
        var ajaxUrl = '<?php echo admin_url('admin-ajax.php'); ?>';
        var nonce   = '<?php echo esc_js($ajax_nonce); ?>';
        var productsCache = <?php echo json_encode(array_values($inventory_items)); ?>;

        function initSelectElements() {
            if ($.fn.selectWoo) {
                $('#adjust-product-id, #purchase-supplier-id').selectWoo({width: '100%'});
            } else if ($.fn.select2) {
                $('#adjust-product-id, #purchase-supplier-id').select2({width: '100%'});
            }
        }
        
        $(document).ready(function() {
            initSelectElements();
        });

        // 1. Stock Adjustment Modal
        $(document).on('click', '.fmb-open-adjust-modal', function(){
            var pid = $(this).data('id');
            if (pid) {
                $('#adjust-product-id').val(pid);
            }
            $('#fmb-adjust-modal').fadeIn(150);
        });

        $('#fmb-close-adjust-modal').on('click', function(){
            $('#fmb-adjust-modal').fadeOut(150);
        });

        $('#btn-save-adjust-stock').on('click', function(){
            var pid    = $('#adjust-product-id').val();
            var type   = $('input[name="adjust_type"]:checked').val();
            var qty    = parseInt($('#adjust-qty').val(), 10);
            var reason = $('#adjust-reason').val();
            var notes  = $('#adjust-notes').val();

            if (!pid || isNaN(qty) || qty <= 0) {
                alert('Please select a product and enter a valid quantity.');
                return;
            }

            var $btn = $(this);
            $btn.prop('disabled', true).text('Applying...');

            $.post(ajaxUrl, {
                action: 'fmb_ajax_adjust_stock',
                nonce: nonce,
                product_id: pid,
                type: type,
                quantity: qty,
                reason: reason,
                notes: notes
            }, function(res){
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-yes"></span> Apply Adjustment');
                if (res.success) {
                    alert('Stock successfully updated!');
                    window.location.reload();
                } else {
                    alert(res.data || 'Failed to adjust stock.');
                }
            });
        });

        // 2. Supplier Modal
        $('#btn-open-supplier-modal, #btn-quick-new-sup').on('click', function(){
            $('#fmb-supplier-modal').fadeIn(150);
        });

        $('#fmb-close-supplier-modal').on('click', function(){
            $('#fmb-supplier-modal').fadeOut(150);
        });

        $('#btn-save-modal-supplier').on('click', function(){
            var name    = $('#sup-modal-name').val();
            var company = $('#sup-modal-company').val();
            var phone   = $('#sup-modal-phone').val();
            var email   = $('#sup-modal-email').val();
            var address = $('#sup-modal-address').val();

            if (!name) {
                alert('Please enter supplier name.');
                return;
            }

            var $btn = $(this);
            $btn.prop('disabled', true).text('Saving...');

            $.post(ajaxUrl, {
                action: 'fmb_ajax_save_supplier',
                nonce: nonce,
                name: name,
                company: company,
                phone: phone,
                email: email,
                address: address
            }, function(res){
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-yes"></span> Save Supplier');
                if (res.success) {
                    alert('Supplier saved successfully!');
                    $('#fmb-supplier-modal').fadeOut(150);
                    // Update supplier dropdown if present
                    if ($('#purchase-supplier-id').length && res.data.suppliers) {
                        var $sel = $('#purchase-supplier-id');
                        $sel.empty().append('<option value="0">-- Select Existing Supplier --</option>');
                        res.data.suppliers.forEach(function(s){
                            $sel.append('<option value="' + s.id + '" ' + (s.id == res.data.supplier_id ? 'selected' : '') + '>' + s.name + (s.phone ? ' (' + s.phone + ')' : '') + '</option>');
                        });
                    } else {
                        window.location.reload();
                    }
                } else {
                    alert(res.data || 'Failed to save supplier.');
                }
            });
        });

        // 3. New Purchase Voucher Dynamic Rows
        function addPurchaseRow(prefillId) {
            var optionsHtml = '<option value="">-- Choose Product --</option>';
            productsCache.forEach(function(p){
                var sel = (prefillId && prefillId == p.id) ? 'selected' : '';
                optionsHtml += '<option value="' + p.id + '" data-stock="' + p.stock + '" data-cost="' + p.cost_price + '" ' + sel + '>' + p.name + ' (SKU: ' + p.sku + ')</option>';
            });

            var rowHtml = `
                <tr class="fmb-purchase-item-row">
                    <td>
                        <select class="fmb-repeater-select item-product-select">${optionsHtml}</select>
                    </td>
                    <td>
                        <span class="item-current-stock" style="font-size:12px; font-weight:700; color:#64748b;">-</span>
                    </td>
                    <td>
                        <input type="number" class="fmb-repeater-input item-qty" value="1" min="1" step="1">
                    </td>
                    <td>
                        <input type="number" class="fmb-repeater-input item-cost" value="0" min="0" step="any">
                    </td>
                    <td>
                        <strong class="item-subtotal-val" style="color:#0f172a; font-size:13px;">৳ 0</strong>
                    </td>
                    <td style="text-align:center;">
                        <button type="button" class="fmb-btn-del-row" title="Remove row"><span class="dashicons dashicons-no-alt"></span></button>
                    </td>
                </tr>
            `;

            var $row = $(rowHtml);
            $('#purchase-items-body').append($row);

            if ($.fn.selectWoo) {
                $row.find('.item-product-select').selectWoo({width: '100%'});
            } else if ($.fn.select2) {
                $row.find('.item-product-select').select2({width: '100%'});
            }

            if (prefillId) {
                $row.find('.item-product-select').val(prefillId).trigger('change');
            }
        }

        // Initialize with one row if on new_purchase tab
        if ($('#purchase-items-body').length) {
            var urlParams = new URLSearchParams(window.location.search);
            var prefill = urlParams.get('prod_id');
            addPurchaseRow(prefill);
        }

        $('#btn-add-repeater-row').on('click', function(){
            addPurchaseRow();
        });

        $(document).on('click', '.fmb-btn-del-row', function(){
            if ($('.fmb-purchase-item-row').length > 1) {
                $(this).closest('tr').remove();
                recalculatePurchaseTotals();
            } else {
                alert('At least one item row is required.');
            }
        });

        // Product selection change in repeater
        $(document).on('change', '.item-product-select', function(){
            var $row = $(this).closest('tr');
            var $opt = $(this).find(':selected');
            var stock = $opt.data('stock');
            var cost  = $opt.data('cost');

            $row.find('.item-current-stock').text(stock !== undefined ? stock + ' pcs' : '-');
            if (cost !== undefined && cost > 0 && parseFloat($row.find('.item-cost').val()) === 0) {
                $row.find('.item-cost').val(cost);
            }
            recalculatePurchaseTotals();
        });

        $(document).on('input', '.item-qty, .item-cost, #purchase-paid-amount', function(){
            recalculatePurchaseTotals();
        });

        function recalculatePurchaseTotals() {
            var grandTotal = 0;
            var totalUnits = 0;

            $('.fmb-purchase-item-row').each(function(){
                var qty  = parseFloat($(this).find('.item-qty').val()) || 0;
                var cost = parseFloat($(this).find('.item-cost').val()) || 0;
                var sub  = qty * cost;

                $(this).find('.item-subtotal-val').text('৳ ' + sub.toLocaleString());
                grandTotal += sub;
                totalUnits += qty;
            });

            $('#summary-total-qty').text(totalUnits + ' pcs');
            $('#summary-grand-total').text('৳ ' + grandTotal.toLocaleString());

            var paidInput = $('#purchase-paid-amount');
            var paidVal = parseFloat(paidInput.val());
            if (isNaN(paidVal) || paidVal === 0) {
                paidInput.val(grandTotal);
                paidVal = grandTotal;
            }

            var due = Math.max(0, grandTotal - paidVal);
            $('#summary-due-amount').text('৳ ' + due.toLocaleString());
        }

        // Submit Purchase Voucher
        $('#btn-submit-purchase').on('click', function(){
            var supId   = parseInt($('#purchase-supplier-id').val(), 10) || 0;
            var supName = $('#purchase-new-supplier-name').val();
            var date    = $('#purchase-date').val();
            var slip    = $('#purchase-slip-no').val();
            var method  = $('#purchase-payment-method').val();
            var notes   = $('#purchase-notes').val();
            var paid    = parseFloat($('#purchase-paid-amount').val()) || 0;

            if (supId === 0 && !supName) {
                alert('Please select an existing supplier or enter a supplier name.');
                return;
            }

            var items = [];
            var hasInvalid = false;

            $('.fmb-purchase-item-row').each(function(){
                var pid  = parseInt($(this).find('.item-product-select').val(), 10);
                var qty  = parseInt($(this).find('.item-qty').val(), 10);
                var cost = parseFloat($(this).find('.item-cost').val()) || 0;

                if (!pid || isNaN(qty) || qty <= 0) {
                    hasInvalid = true;
                } else {
                    items.push({
                        product_id: pid,
                        quantity: qty,
                        unit_cost: cost
                    });
                }
            });

            if (hasInvalid || items.length === 0) {
                alert('Please select valid products and quantities for all item rows.');
                return;
            }

            var $btn = $(this);
            $btn.prop('disabled', true).text('Saving Voucher & Updating Stock...');

            var purchaseData = {
                supplier_id: supId,
                supplier_name: supName,
                purchase_date: date,
                invoice_slip_no: slip,
                payment_method: method,
                notes: notes,
                paid_amount: paid,
                items: items
            };

            $.post(ajaxUrl, {
                action: 'fmb_ajax_save_purchase',
                nonce: nonce,
                purchase_data: JSON.stringify(purchaseData)
            }, function(res){
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-saved"></span> Save Purchase &amp; Increase Stock');
                if (res.success) {
                    alert('Purchase voucher ' + res.data.purchase_no + ' successfully saved! Stock has been updated in WooCommerce.');
                    window.location.href = '<?php echo esc_js($base_url); ?>&tab=history';
                } else {
                    alert(res.data || 'Failed to save purchase.');
                }
            });
        });

        // 4. View Purchase Memo Modal
        $(document).on('click', '.fmb-btn-view-memo', function(){
            var id = $(this).data('id');
            $('#fmb-memo-body').html('<div style="text-align:center; padding:30px;"><span class="dashicons dashicons-update spinning" style="font-size:26px;"></span> Loading memo...</div>');
            $('#fmb-memo-modal').fadeIn(150);

            $.get(ajaxUrl, {
                action: 'fmb_ajax_get_purchase_memo',
                nonce: nonce,
                id: id
            }, function(res){
                if (res.success) {
                    var p = res.data.purchase;
                    var items = res.data.items;
                    var s = res.data.supplier;

                    var itemsHtml = '';
                    items.forEach(function(it, idx){
                        itemsHtml += `
                            <tr>
                                <td>${idx + 1}</td>
                                <td><strong>${it.product_name}</strong>${it.sku ? '<br><small style="color:#64748b;">SKU: ' + it.sku + '</small>' : ''}</td>
                                <td style="text-align:center;">${it.quantity} pcs</td>
                                <td style="text-align:right;">৳ ${parseFloat(it.unit_cost).toLocaleString()}</td>
                                <td style="text-align:right;"><strong>৳ ${parseFloat(it.subtotal).toLocaleString()}</strong></td>
                            </tr>
                        `;
                    });

                    var memoHtml = `
                        <div class="fmb-memo-slip">
                            <div class="fmb-memo-head-grid">
                                <div>
                                    <h2 style="margin:0 0 4px; font-size:18px; color:#4338ca;">${p.purchase_no}</h2>
                                    <span style="font-size:12px; color:#64748b;">Date: ${p.purchase_date}</span>
                                    ${p.invoice_slip_no ? '<br><span style="font-size:12px; color:#64748b;">Slip/Challan: ' + p.invoice_slip_no + '</span>' : ''}
                                </div>
                                <div style="text-align:right;">
                                    <strong>Supplier:</strong><br>
                                    <span style="font-size:13.5px; font-weight:700; color:#0f172a;">${p.supplier_name}</span>
                                    ${s && s.phone ? '<br><span style="font-size:12px; color:#475569;">' + s.phone + '</span>' : ''}
                                </div>
                            </div>

                            <table class="fmb-memo-table">
                                <thead>
                                    <tr>
                                        <th style="width:30px;">#</th>
                                        <th>Item Description</th>
                                        <th style="width:80px; text-align:center;">Qty</th>
                                        <th style="width:90px; text-align:right;">Unit Cost</th>
                                        <th style="width:100px; text-align:right;">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${itemsHtml}
                                </tbody>
                            </table>

                            <div class="fmb-memo-totals">
                                <div class="row"><span>Total Items:</span><strong>${p.total_items} pcs</strong></div>
                                <div class="row"><span>Grand Total:</span><strong style="font-size:15px; color:#0f172a;">৳ ${parseFloat(p.total_amount).toLocaleString()}</strong></div>
                                <div class="row"><span>Paid Amount:</span><span style="color:#16a34a; font-weight:700;">৳ ${parseFloat(p.paid_amount).toLocaleString()} (${p.payment_method.toUpperCase()})</span></div>
                                <div class="row"><span>Due Amount:</span><strong style="color:${p.due_amount > 0 ? '#dc2626' : '#16a34a'};">৳ ${parseFloat(p.due_amount).toLocaleString()}</strong></div>
                            </div>

                            ${p.notes ? '<div style="margin-top:14px; background:#f8fafc; padding:10px; border-radius:6px; font-size:12px; color:#475569;"><strong>Notes:</strong> ' + p.notes + '</div>' : ''}
                        </div>
                    `;
                    $('#fmb-memo-body').html(memoHtml);
                } else {
                    $('#fmb-memo-body').html('<div style="color:#ef4444; padding:20px;">' + (res.data || 'Failed to load voucher.') + '</div>');
                }
            });
        });

        $('#fmb-close-memo-modal').on('click', function(){
            $('#fmb-memo-modal').fadeOut(150);
        });

        // 5. Delete Voucher & Revert Stock
        $(document).on('click', '.fmb-btn-delete-voucher', function(){
            var id = $(this).data('id');
            var no = $(this).data('no');

            if (confirm('Are you sure you want to delete voucher #' + no + '?\n\nWARNING: The stock added by this purchase will be automatically deducted/reverted from your WooCommerce inventory!')) {
                var $btn = $(this);
                $btn.prop('disabled', true);

                $.post(ajaxUrl, {
                    action: 'fmb_ajax_delete_purchase',
                    nonce: nonce,
                    purchase_id: id
                }, function(res){
                    if (res.success) {
                        alert(res.data || 'Purchase deleted and stock reverted.');
                        window.location.reload();
                    } else {
                        alert(res.data || 'Failed to delete purchase.');
                        $btn.prop('disabled', false);
                    }
                });
            }
        });

        // 6. Pay Supplier Modal
        $(document).on('click', '.fmb-btn-pay-supplier', function(){
            var id = $(this).data('id');
            var name = $(this).data('name');
            var due = $(this).data('due');

            $('#pay-sup-id').val(id);
            $('#pay-sup-name').text(name);
            $('#pay-sup-due').text('৳ ' + parseFloat(due).toLocaleString());
            $('#pay-sup-amount').val('');
            $('#pay-sup-ref').val('');
            $('#pay-sup-notes').val('');

            $('#fmb-pay-supplier-modal').fadeIn(150);
        });

        $('#fmb-close-pay-modal').on('click', function(){
            $('#fmb-pay-supplier-modal').fadeOut(150);
        });

        $('#btn-submit-sup-payment').on('click', function(){
            var id = $('#pay-sup-id').val();
            var date = $('#pay-sup-date').val();
            var amount = $('#pay-sup-amount').val();
            var method = $('#pay-sup-method').val();
            var ref = $('#pay-sup-ref').val();
            var notes = $('#pay-sup-notes').val();

            if (!amount || amount <= 0) {
                alert('Please enter a valid amount.');
                return;
            }

            var $btn = $(this);
            $btn.prop('disabled', true).text('Processing...');

            $.post(ajaxUrl, {
                action: 'fmb_ajax_add_supplier_payment',
                nonce: nonce,
                supplier_id: id,
                date: date,
                amount: amount,
                method: method,
                reference: ref,
                notes: notes
            }, function(res){
                if (res.success) {
                    alert('Payment successful!');
                    window.location.reload();
                } else {
                    alert(res.data || 'Failed to process payment.');
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-yes"></span> Confirm Payment');
                }
            });
        });

        // 7. Supplier Ledger Modal
        $(document).on('click', '.fmb-btn-view-ledger', function(){
            var id = $(this).data('id');
            var name = $(this).data('name');

            $('#ledger-sup-name').text(name);
            $('#ledger-sup-due').text('Loading...');
            $('#fmb-ledger-body').html('<tr><td colspan="5" style="text-align:center;">Loading ledger...</td></tr>');
            
            $('#fmb-supplier-ledger-modal').fadeIn(150);

            $.get(ajaxUrl, {
                action: 'fmb_ajax_get_supplier_ledger',
                nonce: nonce,
                supplier_id: id
            }, function(res){
                if (res.success) {
                    var s = res.data.supplier;
                    var ledger = res.data.ledger;
                    
                    $('#ledger-sup-due').text('৳ ' + parseFloat(s.total_due).toLocaleString());

                    if (ledger.length === 0) {
                        $('#fmb-ledger-body').html('<tr><td colspan="5" style="text-align:center; padding:20px; color:#64748b;">No transactions found for this supplier.</td></tr>');
                        return;
                    }

                    var html = '';
                    ledger.forEach(function(row){
                        var isPur = row.type === 'Purchase';
                        html += `
                            <tr>
                                <td style="font-size:12px; color:#64748b;">${row.date}</td>
                                <td>
                                    <strong>${row.type}</strong>
                                    <br><span style="font-size:12px; color:#0f172a;">${row.reference || '-'}</span>
                                    ${row.notes ? '<br><small style="color:#64748b;">' + row.notes + '</small>' : ''}
                                </td>
                                <td style="text-align:right; color:#475569;">${isPur ? '৳ ' + parseFloat(row.debit).toLocaleString() : '-'}</td>
                                <td style="text-align:right; color:#16a34a;">${!isPur ? '৳ ' + parseFloat(row.credit).toLocaleString() : '-'}</td>
                                <td style="text-align:right; font-weight:700; color:${row.balance > 0 ? '#dc2626' : '#0f172a'}">৳ ${parseFloat(row.balance).toLocaleString()}</td>
                            </tr>
                        `;
                    });

                    $('#fmb-ledger-body').html(html);
                } else {
                    $('#fmb-ledger-body').html('<tr><td colspan="5" style="text-align:center; color:#ef4444;">' + (res.data || 'Failed to load ledger.') + '</td></tr>');
                }
            });
        });

        $('#fmb-close-ledger-modal').on('click', function(){
            $('#fmb-supplier-ledger-modal').fadeOut(150);
        });



    })(jQuery);
    </script>
    <?php
}
