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

    // 6. Supplier Payments Table
    $table_supplier_payments = $wpdb->prefix . 'fmb_supplier_payments';
    $sql_supplier_payments = "CREATE TABLE IF NOT EXISTS {$table_supplier_payments} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        supplier_id BIGINT UNSIGNED NOT NULL,
        payment_date DATE NOT NULL,
        amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        payment_method VARCHAR(50) NOT NULL DEFAULT 'cash',
        reference_no VARCHAR(100) NULL,
        notes TEXT NULL,
        created_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY supplier_id (supplier_id)
    ) {$charset_collate};";
    dbDelta($sql_supplier_payments);

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
    return $wpdb->get_results("
        SELECT 
            id, 
            expense_date, 
            category_id, 
            sub_category_id, 
            category_name AS cat_name, 
            category_name AS category, 
            amount, 
            payment_method AS method, 
            reference_no AS reference, 
            description AS notes 
        FROM {$table} 
        ORDER BY expense_date DESC, id DESC
    ");
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

// ==========================================
// AJAX ENDPOINTS
// ==========================================

add_action('wp_ajax_fmb_ajax_save_supplier', 'fmb_ajax_save_supplier');
function fmb_ajax_save_supplier() {
    check_ajax_referer('fmb_purchase_stock_nonce', 'nonce');
    if (!current_user_can('manage_woocommerce')) wp_send_json_error('Unauthorized');

    global $wpdb;
    $name = sanitize_text_field($_POST['name'] ?? '');
    $company = sanitize_text_field($_POST['company'] ?? '');
    $phone = sanitize_text_field($_POST['phone'] ?? '');
    $email = sanitize_email($_POST['email'] ?? '');
    $address = sanitize_textarea_field($_POST['address'] ?? '');

    if (empty($name)) wp_send_json_error('Name is required');

    $wpdb->insert($wpdb->prefix . 'fmb_suppliers', array(
        'name' => $name, 'company' => $company, 'phone' => $phone, 'email' => $email, 'address' => $address
    ));

    wp_send_json_success('Supplier added successfully');
}

add_action('wp_ajax_fmb_ajax_add_general_expense', 'fmb_ajax_add_general_expense');
function fmb_ajax_add_general_expense() {
    check_ajax_referer('fmb_purchase_stock_nonce', 'nonce');
    if (!current_user_can('manage_woocommerce')) wp_send_json_error('Unauthorized');

    global $wpdb;
    $expense_date = sanitize_text_field($_POST['date'] ?? current_time('Y-m-d'));
    $category_id = (int)($_POST['category_id'] ?? 0);
    $sub_category_id = (int)($_POST['sub_category_id'] ?? 0);
    $amount = (float)($_POST['amount'] ?? 0);
    $payment_method = sanitize_text_field($_POST['method'] ?? 'cash');
    $reference_no = sanitize_text_field($_POST['reference'] ?? '');
    $description = sanitize_textarea_field($_POST['notes'] ?? '');

    if ($amount <= 0) wp_send_json_error('Invalid amount');

    // Use subcategory if selected
    $final_cat_id = ($sub_category_id > 0) ? $sub_category_id : $category_id;
    $category_name = $wpdb->get_var($wpdb->prepare("SELECT name FROM {$wpdb->prefix}fmb_expense_categories WHERE id = %d", $final_cat_id));

    $wpdb->insert($wpdb->prefix . 'fmb_expenses', array(
        'expense_date' => $expense_date, 
        'category_id' => $final_cat_id, 
        'category_name' => $category_name,
        'amount' => $amount, 
        'payment_method' => $payment_method, 
        'reference_no' => $reference_no,
        'description' => $description,
        'created_by' => get_current_user_id(), 
        'created_at' => current_time('mysql')
    ));

    wp_send_json_success('Expense added successfully');
}

add_action('wp_ajax_fmb_ajax_adjust_stock', 'fmb_ajax_adjust_stock');
function fmb_ajax_adjust_stock() {
    check_ajax_referer('fmb_purchase_stock_nonce', 'nonce');
    if (!current_user_can('manage_woocommerce')) wp_send_json_error('Unauthorized');

    $product_id = (int)($_POST['product_id'] ?? 0);
    $type = sanitize_text_field($_POST['type'] ?? 'add');
    $qty = (int)($_POST['qty'] ?? 0);
    $notes = sanitize_textarea_field($_POST['notes'] ?? '');

    $result = fmb_manual_adjust_stock($product_id, $type, $qty, 'Manual Adjustment', $notes);
    if (is_wp_error($result)) {
        wp_send_json_error($result->get_error_message());
    }

    wp_send_json_success('Stock adjusted');
}

add_action('wp_ajax_fmb_ajax_save_expense_category', 'fmb_ajax_save_expense_category');
function fmb_ajax_save_expense_category() {
    check_ajax_referer('fmb_purchase_stock_nonce', 'nonce');
    if (!current_user_can('manage_woocommerce')) wp_send_json_error('Unauthorized');
    
    global $wpdb;
    $name = sanitize_text_field($_POST['name'] ?? '');
    $parent_id = (int)($_POST['parent_id'] ?? 0);
    if (empty($name)) wp_send_json_error('Name is required');

    $wpdb->insert($wpdb->prefix . 'fmb_expense_categories', array('name' => $name, 'parent_id' => $parent_id));
    wp_send_json_success('Category saved');
}

// ==========================================
// ADVANCED PURCHASE & SUPPLIER ENDPOINTS
// ==========================================

add_action('wp_ajax_fmb_ajax_save_purchase', 'fmb_ajax_save_purchase');
function fmb_ajax_save_purchase() {
    check_ajax_referer('fmb_purchase_stock_nonce', 'nonce');
    if (!current_user_can('manage_woocommerce')) wp_send_json_error('Unauthorized');

    global $wpdb;
    $data_json = wp_unslash($_POST['purchase_data'] ?? '');
    $data = json_decode($data_json, true);

    if (empty($data) || empty($data['items'])) {
        wp_send_json_error('Invalid purchase data');
    }

    $supplier_id = (int)($data['supplier_id'] ?? 0);
    $supplier_name = sanitize_text_field($data['supplier_name'] ?? 'Unknown');
    if ($supplier_id > 0) {
        $db_name = $wpdb->get_var($wpdb->prepare("SELECT name FROM {$wpdb->prefix}fmb_suppliers WHERE id = %d", $supplier_id));
        if ($db_name) $supplier_name = $db_name;
    }
    $purchase_date = sanitize_text_field($data['purchase_date'] ?? current_time('Y-m-d'));
    $invoice_slip_no = sanitize_text_field($data['invoice_slip_no'] ?? '');
    $payment_method = sanitize_text_field($data['payment_method'] ?? 'cash');
    $notes = sanitize_textarea_field($data['notes'] ?? '');
    $paid_amount = (float)($data['paid_amount'] ?? 0);

    // Calculate totals
    $total_amount = 0;
    $total_items = 0;
    foreach ($data['items'] as $item) {
        $qty = (int)$item['quantity'];
        $cost = (float)$item['unit_cost'];
        $total_amount += ($qty * $cost);
        $total_items += $qty;
    }

    $due_amount = max(0, $total_amount - $paid_amount);
    $payment_status = ($due_amount > 0) ? 'partial' : 'paid';
    $purchase_no = 'PUR-' . date('Ymd') . '-' . rand(1000, 9999);

    // Insert purchase record
    $wpdb->insert($wpdb->prefix . 'fmb_purchases', array(
        'purchase_no' => $purchase_no,
        'supplier_id' => $supplier_id,
        'supplier_name' => $supplier_name,
        'purchase_date' => $purchase_date,
        'total_items' => $total_items,
        'total_amount' => $total_amount,
        'paid_amount' => $paid_amount,
        'due_amount' => $due_amount,
        'payment_method' => $payment_method,
        'payment_status' => $payment_status,
        'invoice_slip_no' => $invoice_slip_no,
        'notes' => $notes,
        'created_by' => get_current_user_id(),
        'created_at' => current_time('mysql')
    ));
    $purchase_id = $wpdb->insert_id;

    // Insert items & Update Stock
    foreach ($data['items'] as $item) {
        $product_id = (int)$item['product_id'];
        $qty = (int)$item['quantity'];
        $cost = (float)$item['unit_cost'];
        $subtotal = $qty * $cost;

        $product = wc_get_product($product_id);
        $p_name = $product ? $product->get_name() : 'Unknown Product';
        $sku = $product ? $product->get_sku() : '';
        $var_text = '';
        if ($product && $product->is_type('variation')) {
            $p_name = wc_get_product($product->get_parent_id())->get_name();
            $var_text = wc_get_formatted_variation($product, true);
        }

        $wpdb->insert($wpdb->prefix . 'fmb_purchase_items', array(
            'purchase_id' => $purchase_id,
            'product_id' => $product_id,
            'product_name' => $p_name,
            'variation_text' => $var_text,
            'sku' => $sku,
            'quantity' => $qty,
            'unit_cost' => $cost,
            'subtotal' => $subtotal,
            'stock_updated' => 1
        ));

        // Adjust Stock using helper
        fmb_manual_adjust_stock($product_id, 'add', $qty, "Purchase V: $purchase_no", $notes);
        
        // Update purchase cost meta
        if ($product) {
            $product->update_meta_data('_purchase_cost', $cost);
            $product->save();
        }
    }

    // Update Supplier Ledger
    if ($supplier_id > 0) {
        $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->prefix}fmb_suppliers SET total_purchases = total_purchases + %f, total_paid = total_paid + %f, total_due = total_due + %f WHERE id = %d",
            $total_amount, $paid_amount, $due_amount, $supplier_id
        ));
    }

    wp_send_json_success(array('purchase_id' => $purchase_id, 'purchase_no' => $purchase_no));
}

add_action('wp_ajax_fmb_ajax_get_purchase_memo', 'fmb_ajax_get_purchase_memo');
function fmb_ajax_get_purchase_memo() {
    check_ajax_referer('fmb_purchase_stock_nonce', 'nonce');
    if (!current_user_can('manage_woocommerce')) wp_send_json_error('Unauthorized');
    
    global $wpdb;
    $id = (int)($_GET['id'] ?? 0);
    $purchase = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}fmb_purchases WHERE id = %d", $id));
    if (!$purchase) wp_send_json_error('Not found');

    $items = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}fmb_purchase_items WHERE purchase_id = %d", $id));
    $supplier = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}fmb_suppliers WHERE id = %d", $purchase->supplier_id));

    wp_send_json_success(array(
        'purchase' => $purchase,
        'items' => $items,
        'supplier' => $supplier
    ));
}

add_action('wp_ajax_fmb_ajax_delete_purchase', 'fmb_ajax_delete_purchase');
function fmb_ajax_delete_purchase() {
    check_ajax_referer('fmb_purchase_stock_nonce', 'nonce');
    if (!current_user_can('manage_woocommerce')) wp_send_json_error('Unauthorized');

    global $wpdb;
    $id = (int)($_POST['purchase_id'] ?? ($_POST['id'] ?? 0));
    $purchase = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}fmb_purchases WHERE id = %d", $id));
    
    if (!$purchase) wp_send_json_error('Not found');

    // Revert Stock
    $items = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}fmb_purchase_items WHERE purchase_id = %d", $id));
    foreach ($items as $item) {
        fmb_manual_adjust_stock($item->product_id, 'subtract', $item->quantity, "Rollback Purchase: " . $purchase->purchase_no, '');
    }

    // Revert Supplier
    if ($purchase->supplier_id > 0) {
        $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->prefix}fmb_suppliers SET total_purchases = GREATEST(0, total_purchases - %f), total_paid = GREATEST(0, total_paid - %f), total_due = GREATEST(0, total_due - %f) WHERE id = %d",
            $purchase->total_amount, $purchase->paid_amount, $purchase->due_amount, $purchase->supplier_id
        ));
    }

    $wpdb->delete($wpdb->prefix . 'fmb_purchase_items', array('purchase_id' => $id));
    $wpdb->delete($wpdb->prefix . 'fmb_purchases', array('id' => $id));

    wp_send_json_success('Purchase Rollbacked successfully');
}

add_action('wp_ajax_fmb_ajax_add_supplier_payment', 'fmb_ajax_add_supplier_payment');
function fmb_ajax_add_supplier_payment() {
    check_ajax_referer('fmb_purchase_stock_nonce', 'nonce');
    if (!current_user_can('manage_woocommerce')) wp_send_json_error('Unauthorized');

    global $wpdb;
    $supplier_id = (int)($_POST['supplier_id'] ?? 0);
    $amount = (float)($_POST['amount'] ?? 0);
    $date = sanitize_text_field($_POST['date'] ?? current_time('Y-m-d'));
    $method = sanitize_text_field($_POST['method'] ?? 'cash');
    $reference = sanitize_text_field($_POST['reference'] ?? '');
    $notes = sanitize_textarea_field($_POST['notes'] ?? '');

    if ($supplier_id <= 0 || $amount <= 0) wp_send_json_error('Invalid input');

    $wpdb->insert($wpdb->prefix . 'fmb_supplier_payments', array(
        'supplier_id' => $supplier_id,
        'payment_date' => $date,
        'amount' => $amount,
        'payment_method' => $method,
        'reference_no' => $reference,
        'notes' => $notes,
        'created_by' => get_current_user_id(),
        'created_at' => current_time('mysql')
    ));

    $wpdb->query($wpdb->prepare(
        "UPDATE {$wpdb->prefix}fmb_suppliers SET total_paid = total_paid + %f, total_due = GREATEST(0, total_due - %f) WHERE id = %d",
        $amount, $amount, $supplier_id
    ));

    wp_send_json_success('Payment added successfully');
}

add_action('wp_ajax_fmb_ajax_get_supplier_ledger', 'fmb_ajax_get_supplier_ledger');
function fmb_ajax_get_supplier_ledger() {
    check_ajax_referer('fmb_purchase_stock_nonce', 'nonce');
    if (!current_user_can('manage_woocommerce')) wp_send_json_error('Unauthorized');
    
    global $wpdb;
    $id = (int)($_GET['supplier_id'] ?? ($_GET['id'] ?? 0));
    $supplier = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}fmb_suppliers WHERE id = %d", $id));
    if (!$supplier) wp_send_json_error('Supplier not found');

    $purchases = $wpdb->get_results($wpdb->prepare("SELECT purchase_date as date, 'Purchase' as type, purchase_no as reference, notes, total_amount as debit, 0 as credit, created_at FROM {$wpdb->prefix}fmb_purchases WHERE supplier_id = %d", $id));
    
    // Check if payments table exists, then query
    $table_supplier_payments = $wpdb->prefix . 'fmb_supplier_payments';
    $payments = array();
    if ($wpdb->get_var("SHOW TABLES LIKE '{$table_supplier_payments}'") === $table_supplier_payments) {
        $payments = $wpdb->get_results($wpdb->prepare("SELECT payment_date as date, 'Payment' as type, reference_no as reference, notes, 0 as debit, amount as credit, created_at FROM {$table_supplier_payments} WHERE supplier_id = %d", $id));
    }

    $ledger_items = array_merge($purchases, $payments);
    usort($ledger_items, function($a, $b) {
        return strtotime($a->created_at) - strtotime($b->created_at);
    });

    $ledger = array();
    $balance = 0;
    foreach ($ledger_items as $item) {
        $balance += (float)$item->debit;
        $balance -= (float)$item->credit;
        $ledger[] = array(
            'date' => $item->date,
            'type' => $item->type,
            'reference' => $item->reference,
            'notes' => $item->notes,
            'debit' => $item->debit,
            'credit' => $item->credit,
            'balance' => $balance
        );
    }
    
    // Sort descending for display (newest first)
    $ledger = array_reverse($ledger);

    wp_send_json_success(array(
        'supplier' => $supplier,
        'ledger' => $ledger
    ));
}
