<?php
// Standalone preview for Order Hub, Invoices, Labels, View & New Order pages
require_once __DIR__ . '/../../../wp-load.php';

$admins = get_users(['role' => 'administrator']);
if (!empty($admins)) {
    wp_set_current_user($admins[0]->ID);
    if (!is_user_logged_in()) {
        wp_set_auth_cookie($admins[0]->ID);
    }
}

// Print Invoices (A4 6-in-1)
if (!empty($_GET['print_invoices']) || !empty($_GET['print_invoice'])) {
    $raw = $_GET['print_invoices'] ?? $_GET['print_invoice'];
    $ids = array_filter(array_map('absint', explode(',', (string)$raw)));
    if (!empty($ids)) {
        fmb_render_a4_invoices($ids);
        exit;
    }
}

// Print Labels (2x3" Thermal)
if (!empty($_GET['print_labels']) || !empty($_GET['print_label'])) {
    $raw = $_GET['print_labels'] ?? $_GET['print_label'];
    $ids = array_filter(array_map('absint', explode(',', (string)$raw)));
    if (!empty($ids)) {
        fmb_render_thermal_labels($ids);
        exit;
    }
}

$action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : '';
$order_id = isset($_GET['order_id']) ? absint($_GET['order_id']) : 0;
$order = $order_id ? wc_get_order($order_id) : null;
$order_num = $order ? $order->get_order_number() : ($order_id ?: '');

$page_title = 'Order Management Hub';
if ($action === 'new') {
    $page_title = 'New Order';
} elseif ($action === 'edit') {
    $page_title = 'Edit Order #' . $order_num;
} elseif ($action === 'view' || $order_id > 0) {
    $page_title = 'Order #' . $order_num . ' - View Details';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html($page_title); ?></title>
    <link rel="stylesheet" href="<?php echo includes_url('css/dashicons.min.css'); ?>">
    <link rel="stylesheet" href="<?php echo admin_url('css/common.min.css'); ?>">
    <script src="<?php echo includes_url('js/jquery/jquery.min.js'); ?>"></script>
    <style>
        body { background: #f8fafc; margin: 0; padding: 20px 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif; }
    </style>
</head>
<body>
    <?php 
    if ($action === 'new') {
        fmb_admin_new_order_page(0);
    } elseif ($action === 'edit') {
        fmb_admin_new_order_page($order_id);
    } elseif ($action === 'view' || ($order_id > 0 && $action !== 'list')) {
        fmb_admin_single_order_page($order_id ?: 149);
    } else {
        fmb_admin_order_manager_page();
    }
    ?>
</body>
</html>
