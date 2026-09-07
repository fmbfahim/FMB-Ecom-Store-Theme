<?php
/**
 * Product Purchase & Stock Management Database & Backend Operations
 * FMB E-Commerce Store
 */

if (!defined('ABSPATH')) {
    exit;
}

// 1. Initialize Database Tables
add_action('admin_init', 'fmb_init_purchase_stock_tables');
function fmb_init_purchase_stock_tables() {
    global $wpdb;
    $version = '1.3';
    $installed = get_option('fmb_purchase_stock_db_version');

    if ($installed === $version) {
        return;
    }

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $charset_collate = $wpdb->get_charset_collate();
    
    // 0. Expense Categories Table
    $table_expense_cats = $wpdb->prefix . 'fmb_expense_categories';
    $sql_expense_cats = "CREATE TABLE IF NOT EXISTS {$table_expense_cats} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        name VARCHAR(255) NOT NULL,
        parent_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
        PRIMARY KEY (id),
        KEY parent_id (parent_id)
    ) {$charset_collate};";
    dbDelta($sql_expense_cats);

    // Insert Default Expense Categories if empty
    $cat_count = $wpdb->get_var("SELECT COUNT(*) FROM {$table_expense_cats}");
    if ($cat_count == 0) {
        $default_cats = [
            'অফিস ভাড়া (Office Rent)',
            'বেতন (Salary)',
            'মার্কেটিং (Marketing / Ads)',
            'প্যাকেজিং (Packaging)',
            'ডেলিভারি খরচ (Delivery Cost)',
            'সার্ভার ও ডোমেইন (Server/Domain)',
            'অন্যান্য (Others)'
        ];
        foreach ($default_cats as $cat) {
            $wpdb->insert($table_expense_cats, array('name' => $cat, 'parent_id' => 0));
        }
    }

    // 1. Suppliers Table
    $table_suppliers = $wpdb->prefix . 'fmb_suppliers';
    $sql_suppliers = "CREATE TABLE IF NOT EXISTS {$table_suppliers} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        name VARCHAR(255) NOT NULL,
        company VARCHAR(255) NULL,
        phone VARCHAR(50) NULL,
        email VARCHAR(255) NULL,
        address TEXT NULL,
        total_purchases DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        total_paid DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        total_due DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY phone (phone)
    ) {$charset_collate};";
    dbDelta($sql_suppliers);

    // 2. Purchases Table
    $table_purchases = $wpdb->prefix . 'fmb_purchases';
    $sql_purchases = "CREATE TABLE IF NOT EXISTS {$table_purchases} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        purchase_no VARCHAR(50) NOT NULL,
        supplier_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
        supplier_name VARCHAR(255) NOT NULL,
        purchase_date DATE NOT NULL,
        total_items INT NOT NULL DEFAULT 0,
        total_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        paid_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        due_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        payment_method VARCHAR(50) NOT NULL DEFAULT 'cash',
        payment_status VARCHAR(20) NOT NULL DEFAULT 'paid',
        invoice_slip_no VARCHAR(100) NULL,
        notes TEXT NULL,
        created_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY purchase_no (purchase_no),
        KEY supplier_id (supplier_id),
        KEY purchase_date (purchase_date)
    ) {$charset_collate};";
    dbDelta($sql_purchases);

    // 3. Purchase Items Table
    $table_items = $wpdb->prefix . 'fmb_purchase_items';
    $sql_items = "CREATE TABLE IF NOT EXISTS {$table_items} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        purchase_id BIGINT UNSIGNED NOT NULL,
        product_id BIGINT UNSIGNED NOT NULL,
        product_name VARCHAR(255) NOT NULL,
        variation_text VARCHAR(255) NULL,
        sku VARCHAR(100) NULL,
        quantity INT NOT NULL DEFAULT 1,
        unit_cost DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        subtotal DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        stock_updated TINYINT(1) NOT NULL DEFAULT 1,
        PRIMARY KEY (id),
        KEY purchase_id (purchase_id),
        KEY product_id (product_id)
    ) {$charset_collate};";
    dbDelta($sql_items);

    // 4. Stock Adjustments Table
    $table_adjustments = $wpdb->prefix . 'fmb_stock_adjustments';
    $sql_adjustments = "CREATE TABLE IF NOT EXISTS {$table_adjustments} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        product_id BIGINT UNSIGNED NOT NULL,
        product_name VARCHAR(255) NOT NULL,
        adjustment_type VARCHAR(20) NOT NULL DEFAULT 'add',
        quantity INT NOT NULL DEFAULT 0,
        previous_stock INT NOT NULL DEFAULT 0,
        new_stock INT NOT NULL DEFAULT 0,
        reason VARCHAR(100) NULL,
        notes TEXT NULL,
        created_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY product_id (product_id)
    ) {$charset_collate};";
    dbDelta($sql_adjustments);

    // 5. Expenses Table
    $table_expenses = $wpdb->prefix . 'fmb_expenses';
    $sql_expenses = "CREATE TABLE IF NOT EXISTS {$table_expenses} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        expense_date DATE NOT NULL,
        category_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
        category_name VARCHAR(255) NULL,
        amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        payment_method VARCHAR(50) NOT NULL DEFAULT 'cash',
        reference_no VARCHAR(100) NULL,
        description TEXT NULL,
        attachment_id BIGINT UNSIGNED NULL,
        created_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY expense_date (expense_date),
        KEY category_id (category_id)
    ) {$charset_collate};";
    dbDelta($sql_expenses);

    update_option('fmb_purchase_stock_db_version', $version);
}

function fmb_get_expense_categories() {
    global $wpdb;
    $table = $wpdb->prefix . 'fmb_expense_categories';
    $cats = $wpdb->get_results("SELECT * FROM {$table} ORDER BY parent_id ASC, name ASC");
    $organized = array();
    foreach ($cats as $cat) {
        if ($cat->parent_id == 0) {
            $organized[$cat->id] = array('id' => $cat->id, 'name' => $cat->name, 'sub' => array());
        } else {
            if (isset($organized[$cat->parent_id])) {
                $organized[$cat->parent_id]['sub'][] = array('id' => $cat->id, 'name' => $cat->name, 'parent_id' => $cat->parent_id);
            }
        }
    }
    return $organized;
}

function fmb_get_expenses() {
    global $wpdb;
    $table = $wpdb->prefix . 'fmb_expenses';
    return $wpdb->get_results("SELECT * FROM {$table} ORDER BY expense_date DESC, id DESC");
}

function fmb_get_all_suppliers() {
    global $wpdb;
    return $wpdb->get_results("SELECT * FROM {$wpdb->prefix}fmb_suppliers ORDER BY name ASC");
}

function fmb_get_purchases() {
    global $wpdb;
    return $wpdb->get_results("SELECT * FROM {$wpdb->prefix}fmb_purchases ORDER BY purchase_date DESC, id DESC");
}

function fmb_manual_adjust_stock($product_id, $type, $quantity, $reason = '', $notes = '') {
    global $wpdb;
    $product = wc_get_product($product_id);
    if (!$product) return new WP_Error('not_found', 'Product not found.');
    $product->set_manage_stock(true);
    $prev_stock = (int)$product->get_stock_quantity();
    $quantity = max(1, (int)$quantity);
    $new_stock = ($type === 'add') ? ($prev_stock + $quantity) : max(0, $prev_stock - $quantity);
    $product->set_stock_quantity($new_stock);
    $product->set_stock_status($new_stock > 0 ? 'instock' : 'outofstock');
    $product->save();
    $wpdb->insert($wpdb->prefix . 'fmb_stock_adjustments', array(
        'product_id' => $product_id, 'product_name' => $product->get_name(), 'adjustment_type' => $type,
        'quantity' => $quantity, 'previous_stock' => $prev_stock, 'new_stock' => $new_stock,
        'reason' => sanitize_text_field($reason ?: 'manual_correction'), 'notes' => sanitize_textarea_field($notes),
        'created_by' => get_current_user_id(), 'created_at' => current_time('mysql'),
    ));
    return array('product_id' => $product_id, 'prev_stock' => $prev_stock, 'new_stock' => $new_stock);
}
