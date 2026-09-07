<?php
// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// 1. Add Menu Item
add_action('admin_menu', 'fmb_custom_orders_menu');
function fmb_custom_orders_menu() {
    add_menu_page(
        'FMB Orders',
        '📦 FMB Orders',
        'manage_woocommerce',
        'fmb-orders',
        'fmb_render_custom_orders_page',
        'dashicons-cart',
        54
    );
}

// 2. Enqueue Tailwind ONLY on our page
add_action('admin_enqueue_scripts', 'fmb_orders_admin_assets');
function fmb_orders_admin_assets($hook) {
    if ( $hook === 'toplevel_page_fmb-orders' ) {
        wp_enqueue_script('tailwindcss', 'https://cdn.tailwindcss.com', array(), null, false);
        $tailwind_config = "
            tailwind.config = {
                important: '.fmb-admin-app',
                corePlugins: { preflight: false }
            }
        ";
        wp_add_inline_script('tailwindcss', $tailwind_config, 'before');
        
        // Add custom styles to override WP admin defaults inside our app
        wp_add_inline_style('wp-admin', "
            .fmb-admin-app { margin-top: 20px; margin-right: 20px; font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; }
            .fmb-admin-app input:not([type='checkbox']):not([type='radio']), .fmb-admin-app select, .fmb-admin-app textarea { border-color: #e5e7eb; border-radius: 0.375rem; padding: 0.5rem 0.75rem; font-size: 0.875rem; line-height: 1.25rem; }
            .fmb-admin-app input:focus, .fmb-admin-app select:focus, .fmb-admin-app textarea:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 1px #3b82f6; }
        ");
    }
}

// 3. Handle AJAX Updates
add_action('wp_ajax_fmb_update_order_status', 'fmb_ajax_update_order_status');
function fmb_ajax_update_order_status() {
    check_ajax_referer('fmb_order_nonce', 'nonce');
    if (!current_user_can('manage_woocommerce')) wp_send_json_error('Unauthorized');
    
    $order_id = intval($_POST['order_id']);
    $status = sanitize_text_field($_POST['status']);
    
    $order = wc_get_order($order_id);
    if ($order) {
        $order->update_status($status, 'Status updated via Custom FMB Dashboard.', true);
        wp_send_json_success(array('message' => 'অর্ডার স্ট্যাটাস আপডেট হয়েছে!', 'new_status' => wc_get_order_status_name($status)));
    }
    wp_send_json_error('Order not found');
}

// 4. Render Main App Container
function fmb_render_custom_orders_page() {
    if (!class_exists('WooCommerce')) {
        echo '<div class="wrap"><h1>WooCommerce is not active.</h1></div>';
        return;
    }

    echo '<div class="wrap fmb-admin-app">';
    
    $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';
    
    if ($action === 'view' && isset($_GET['id'])) {
        fmb_render_order_details(intval($_GET['id']));
    } else {
        fmb_render_order_list();
    }
    
    echo '</div>';
}

// Helper: Status Colors
function fmb_get_status_color_classes($status) {
    switch ($status) {
        case 'completed': return 'bg-green-100 text-green-800 border-green-200';
        case 'processing': return 'bg-blue-100 text-blue-800 border-blue-200';
        case 'on-hold': return 'bg-yellow-100 text-yellow-800 border-yellow-200';
        case 'pending': return 'bg-gray-100 text-gray-800 border-gray-200';
        case 'cancelled': 
        case 'failed': return 'bg-red-100 text-red-800 border-red-200';
        default: return 'bg-gray-100 text-gray-800 border-gray-200';
    }
}

// 5. Order List View
function fmb_render_order_list() {
    $paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $per_page = 20;
    
    $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
    $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
    
    $args = array(
        'limit' => $per_page,
        'page' => $paged,
        'paginate' => true,
        'orderby' => 'date',
        'order' => 'DESC',
    );
    
    if (!empty($search)) {
        // Simple search logic for WooCommerce orders
        // Note: WC_Order_Query search might be limited, but works for ID or basic fields in HPOS
        $args['field_query'] = array(
            array(
                'field' => 'billing_phone',
                'value' => $search,
                'compare' => 'LIKE'
            )
        );
        // We will try simple search first, if it is a number it might be an ID
        if (is_numeric($search)) {
           // Can search by ID or Phone
        }
    }
    
    if (!empty($status_filter) && $status_filter !== 'all') {
        $args['status'] = array($status_filter);
    }
    
    // Improved search: If search is numeric, try to find exact ID first
    if (!empty($search) && is_numeric($search)) {
        $args_id = $args;
        unset($args_id['field_query']);
        $args_id['post__in'] = array(intval($search));
        $results = wc_get_orders($args_id);
        
        if (empty($results->orders)) {
            // fallback to phone search
            $results = wc_get_orders($args);
        }
    } else {
         $results = wc_get_orders($args);
    }

    $orders = $results->orders;
    $total_orders = $results->total;
    $total_pages = $results->max_num_pages;
    
    // Calculate total revenue for today just for quick stats
    $today_args = array(
        'date_created' => date('Y-m-d'),
        'limit' => -1,
        'return' => 'ids'
    );
    $today_orders = wc_get_orders($today_args);
    $today_count = count($today_orders);
    
    ?>
    <div class="mb-6 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
            <h1 class="text-2xl md:text-3xl font-bold text-gray-900 m-0">Order Management</h1>
            <p class="text-sm text-gray-500 mt-1">Manage all your WooCommerce orders from this beautiful dashboard.</p>
        </div>
        <div class="flex gap-4">
            <div class="bg-white px-4 py-2 rounded-lg shadow-sm border border-gray-200 flex flex-col items-center">
                <span class="text-xs text-gray-500 font-medium uppercase tracking-wider">Today's Orders</span>
                <span class="text-xl font-bold text-blue-600"><?php echo esc_html($today_count); ?></span>
            </div>
            <div class="bg-white px-4 py-2 rounded-lg shadow-sm border border-gray-200 flex flex-col items-center">
                <span class="text-xs text-gray-500 font-medium uppercase tracking-wider">Total Orders</span>
                <span class="text-xl font-bold text-gray-900"><?php echo esc_html($total_orders); ?></span>
            </div>
        </div>
    </div>

    <!-- Filters & Search -->
    <div class="bg-white p-4 rounded-t-xl shadow-sm border border-gray-200 border-b-0 flex flex-wrap gap-4 justify-between items-center">
        <form method="GET" class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 w-full md:w-auto">
            <input type="hidden" name="page" value="fmb-orders">
            
            <select name="status" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block p-2">
                <option value="all">All Statuses</option>
                <?php
                $wc_statuses = wc_get_order_statuses();
                foreach ($wc_statuses as $slug => $name) {
                    $clean_slug = str_replace('wc-', '', $slug);
                    $selected = ($status_filter === $clean_slug) ? 'selected' : '';
                    echo '<option value="' . esc_attr($clean_slug) . '" ' . $selected . '>' . esc_html($name) . '</option>';
                }
                ?>
            </select>
            
            <div class="relative">
                <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                    <svg class="w-4 h-4 text-gray-500" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 20 20"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m19 19-4-4m0-7A7 7 0 1 1 1 8a7 7 0 0 1 14 0Z"/></svg>
                </div>
                <input type="text" name="s" value="<?php echo esc_attr($search); ?>" class="block w-full p-2 pl-10 text-sm text-gray-900 border border-gray-300 rounded-lg bg-gray-50 focus:ring-blue-500 focus:border-blue-500" placeholder="Search orders (ID/Phone)">
            </div>
            
            <button type="submit" class="text-white bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:ring-blue-300 font-medium rounded-lg text-sm px-4 py-2">Filter</button>
            <?php if (!empty($search) || !empty($status_filter)): ?>
                <a href="?page=fmb-orders" class="text-gray-500 hover:text-gray-900 text-sm font-medium">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Table -->
    <div class="relative overflow-x-auto shadow-sm sm:rounded-b-xl border border-gray-200">
        <table class="w-full min-w-[1000px] text-sm text-left text-gray-500">
            <thead class="text-xs text-gray-700 uppercase bg-gray-50 border-b border-gray-200">
                <tr>
                    <th scope="col" class="px-6 py-4">Order</th>
                    <th scope="col" class="px-6 py-4">Date</th>
                    <th scope="col" class="px-6 py-4">Status</th>
                    <th scope="col" class="px-6 py-4">Customer</th>
                    <th scope="col" class="px-6 py-4">Items</th>
                    <th scope="col" class="px-6 py-4">Total</th>
                    <th scope="col" class="px-6 py-4">Origin</th>
                    <th scope="col" class="px-6 py-4 text-right">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($orders)): ?>
                    <tr><td colspan="7" class="px-6 py-8 text-center text-gray-500">No orders found.</td></tr>
                <?php else: ?>
                    <?php foreach ($orders as $order): 
                        $status = $order->get_status();
                        $color_classes = fmb_get_status_color_classes($status);
                    ?>
                        <tr class="bg-white border-b hover:bg-gray-50 transition">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <a href="?page=fmb-orders&action=view&id=<?php echo $order->get_id(); ?>" class="font-bold text-blue-600 hover:underline">
                                    #<?php echo $order->get_order_number(); ?>
                                </a>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-gray-900"><?php echo wc_format_datetime($order->get_date_created(), 'M j, Y'); ?></div>
                                <div class="text-xs text-gray-500"><?php echo wc_format_datetime($order->get_date_created(), 'g:i a'); ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <select class="fmb-status-dropdown text-xs font-semibold rounded-full border p-1 outline-none cursor-pointer hover:shadow-sm <?php echo $color_classes; ?>" data-order-id="<?php echo $order->get_id(); ?>">
                                    <?php
                                    foreach (wc_get_order_statuses() as $slug => $name) {
                                        $clean_slug = str_replace('wc-', '', $slug);
                                        $selected = ($status === $clean_slug) ? 'selected' : '';
                                        echo '<option value="' . esc_attr($clean_slug) . '" ' . $selected . '>' . esc_html($name) . '</option>';
                                    }
                                    ?>
                                </select>
                            </td>
                            <td class="px-6 py-4">
                                <div class="font-medium text-gray-900 mb-1"><?php echo esc_html($order->get_formatted_billing_full_name()); ?></div>
                                <div class="flex items-center gap-2 mb-1">
                                    <a href="tel:<?php echo esc_attr($order->get_billing_phone()); ?>" class="text-blue-600 font-medium hover:underline flex items-center gap-1">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>
                                        <?php echo esc_html($order->get_billing_phone()); ?>
                                    </a>
                                    <button class="text-gray-400 hover:text-blue-600 copy-btn p-1 bg-gray-50 rounded border border-gray-200" data-clipboard-text="<?php echo esc_attr($order->get_billing_phone()); ?>" title="Copy Number">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path></svg>
                                    </button>
                                </div>
                                <?php
                                $addr = $order->get_formatted_shipping_address();
                                if (!$addr) $addr = $order->get_formatted_billing_address();
                                ?>
                                <div class="text-xs text-gray-500 max-w-[200px] truncate" title="<?php echo esc_attr(str_replace('<br/>', ', ', $addr)); ?>">
                                    <?php echo wp_kses_post(str_replace('<br/>', ', ', $addr)); ?>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="space-y-2 text-xs">
                                    <?php 
                                    foreach ($order->get_items() as $item) {
                                        echo '<div class="leading-tight"><span class="font-bold text-gray-900 bg-gray-100 px-1 py-0.5 rounded">' . esc_html($item->get_quantity()) . 'x</span> <span class="text-gray-700 font-medium">' . esc_html($item->get_name()) . '</span> <span class="text-gray-500 ml-1">(' . wp_kses_post(wc_price($order->get_item_total($item))) . ')</span></div>';
                                    }
                                    ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap font-bold text-gray-900">
                                <?php echo wp_kses_post($order->get_formatted_order_total()); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php 
                                $origin = get_post_meta($order->get_id(), '_created_via', true);
                                if (!$origin) $origin = 'Website';
                                echo '<span class="bg-purple-50 text-purple-700 px-2 py-1 rounded-md text-xs font-semibold border border-purple-200">' . esc_html(ucfirst($origin)) . '</span>';
                                ?>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <a href="?page=fmb-orders&action=view&id=<?php echo $order->get_id(); ?>" class="font-medium text-blue-600 hover:text-blue-900 bg-blue-50 hover:bg-blue-100 px-3 py-1.5 rounded-lg transition">View / Edit</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <div class="mt-4 flex flex-col sm:flex-row justify-between items-center gap-4 bg-white p-4 rounded-xl shadow-sm border border-gray-200">
            <span class="text-sm text-gray-700">
                Showing page <span class="font-semibold text-gray-900"><?php echo $paged; ?></span> of <span class="font-semibold text-gray-900"><?php echo $total_pages; ?></span>
            </span>
            <div class="inline-flex">
                <?php
                $base_url = '?page=fmb-orders';
                if (!empty($search)) $base_url .= '&s=' . urlencode($search);
                if (!empty($status_filter)) $base_url .= '&status=' . urlencode($status_filter);
                
                if ($paged > 1): ?>
                    <a href="<?php echo $base_url . '&paged=' . ($paged - 1); ?>" class="flex items-center justify-center px-4 h-8 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-l-lg hover:bg-gray-100 hover:text-gray-700">Prev</a>
                <?php else: ?>
                    <span class="flex items-center justify-center px-4 h-8 text-sm font-medium text-gray-300 bg-gray-50 border border-gray-200 rounded-l-lg cursor-not-allowed">Prev</span>
                <?php endif; ?>
                
                <?php if ($paged < $total_pages): ?>
                    <a href="<?php echo $base_url . '&paged=' . ($paged + 1); ?>" class="flex items-center justify-center px-4 h-8 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-r-lg hover:bg-gray-100 hover:text-gray-700 border-l-0">Next</a>
                <?php else: ?>
                    <span class="flex items-center justify-center px-4 h-8 text-sm font-medium text-gray-300 bg-gray-50 border border-gray-200 rounded-r-lg border-l-0 cursor-not-allowed">Next</span>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <script>
    jQuery(document).ready(function($) {
        // Inline Status Update
        $('.fmb-status-dropdown').on('change', function() {
            var dropdown = $(this);
            var status = dropdown.val();
            var orderId = dropdown.data('order-id');
            var originalColor = dropdown.attr('class').match(/bg-\w+-100/);
            
            dropdown.prop('disabled', true).css('opacity', '0.6');
            
            $.post(ajaxurl, {
                action: 'fmb_update_order_status',
                nonce: '<?php echo wp_create_nonce("fmb_order_nonce"); ?>',
                order_id: orderId,
                status: status
            }, function(response) {
                dropdown.prop('disabled', false).css('opacity', '1');
                if (response.success) {
                    // Just reload to reflect the new color classes correctly
                    location.reload();
                } else {
                    alert('Update failed: ' + (response.data || 'Unknown error'));
                }
            }).fail(function() {
                dropdown.prop('disabled', false).css('opacity', '1');
                alert('Server error.');
            });
        });

        // Copy Phone Number
        $('.copy-btn').on('click', function(e) {
            e.preventDefault();
            var text = $(this).data('clipboard-text');
            var btn = $(this);
            navigator.clipboard.writeText(text).then(function() {
                var originalHtml = btn.html();
                btn.html('<svg class="w-3 h-3 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>');
                setTimeout(function() { btn.html(originalHtml); }, 2000);
            });
        });
    });
    </script>
    <?php
}

// 6. Order Details View
function fmb_render_order_details($order_id) {
    $order = wc_get_order($order_id);
    if (!$order) {
        echo '<div class="p-4 bg-red-100 text-red-800 rounded-lg">Order not found.</div>';
        return;
    }
    
    $status = $order->get_status();
    $color_classes = fmb_get_status_color_classes($status);
    ?>
    <div class="mb-6 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div class="flex flex-col sm:flex-row items-start sm:items-center gap-4 w-full md:w-auto">
            <a href="?page=fmb-orders" class="text-gray-500 hover:text-gray-900 bg-white border border-gray-200 p-2 rounded-lg shadow-sm self-start sm:self-auto">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            </a>
            <div>
                <h1 class="text-3xl font-bold text-gray-900 m-0 flex items-center gap-3">
                    Order #<?php echo $order->get_order_number(); ?>
                    <span id="order-status-badge" class="px-3 py-1 text-sm font-semibold rounded-full border <?php echo $color_classes; ?>">
                        <?php echo wc_get_order_status_name($status); ?>
                    </span>
                </h1>
                <p class="text-sm text-gray-500 mt-1">Placed on <?php echo wc_format_datetime($order->get_date_created(), 'F j, Y \a\t g:i a'); ?></p>
            </div>
        </div>
        <div class="w-full md:w-auto">
            <!-- Print or Invoice Button (Optional hook for plugins) -->
            <button onclick="window.print()" class="w-full md:w-auto bg-white text-gray-700 border border-gray-300 font-bold py-2 px-4 rounded-lg shadow-sm hover:bg-gray-50 flex items-center justify-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                Print
            </button>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        <!-- Left Column: Items and Totals -->
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 bg-gray-50 font-bold text-gray-700">
                    Order Items
                </div>
                <ul class="divide-y divide-gray-200">
                    <?php 
                    foreach ($order->get_items() as $item_id => $item) {
                        $product = $item->get_product();
                        $image_url = $product ? wp_get_attachment_image_url($product->get_image_id(), 'thumbnail') : wc_placeholder_img_src();
                        ?>
                        <li class="p-6 flex gap-4 items-start">
                            <img src="<?php echo esc_url($image_url); ?>" alt="" class="w-16 h-16 rounded border bg-gray-50 object-cover shrink-0">
                            <div class="flex-grow">
                                <h4 class="font-bold text-gray-900 text-base mb-1">
                                    <?php echo esc_html($item->get_name()); ?>
                                </h4>
                                <?php
                                $meta_data = $item->get_formatted_meta_data('');
                                if (!empty($meta_data)) {
                                    echo '<div class="text-xs text-gray-500 mb-2">';
                                    foreach ($meta_data as $meta) {
                                        echo '<p><strong>' . wp_kses_post($meta->display_key) . ':</strong> ' . wp_kses_post($meta->display_value) . '</p>';
                                    }
                                    echo '</div>';
                                }
                                ?>
                                <div class="text-sm font-medium text-gray-600 bg-gray-100 w-fit px-2 py-0.5 rounded mt-1">
                                    Qty: <?php echo esc_html($item->get_quantity()); ?>
                                </div>
                            </div>
                            <div class="text-right shrink-0">
                                <div class="font-bold text-gray-900 text-lg">
                                    <?php echo wp_kses_post($order->get_formatted_line_subtotal($item)); ?>
                                </div>
                                <div class="text-xs text-gray-500">
                                    <?php echo wc_price($order->get_item_total($item)); ?> each
                                </div>
                            </div>
                        </li>
                        <?php
                    }
                    ?>
                </ul>
                
                <div class="bg-gray-50 p-6 border-t border-gray-200">
                    <div class="max-w-xs ml-auto space-y-3">
                        <?php foreach ($order->get_order_item_totals() as $key => $total) : ?>
                            <div class="flex justify-between items-center text-sm <?php echo ($key === 'order_total') ? 'text-lg font-black text-gray-900 border-t border-gray-300 pt-3' : 'text-gray-600 font-medium'; ?>">
                                <span><?php echo wp_kses_post($total['label']); ?></span>
                                <span><?php echo wp_kses_post($total['value']); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <!-- Payment & Shipping details block -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 bg-gray-50 font-bold text-gray-700">
                    Additional Information
                </div>
                <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <h5 class="text-sm font-bold text-gray-500 uppercase tracking-wider mb-2">Payment Method</h5>
                        <p class="text-gray-900 font-medium"><?php echo wp_kses_post($order->get_payment_method_title()); ?></p>
                        <?php if ($order->get_transaction_id()): ?>
                            <p class="text-xs text-gray-500 mt-1">Transaction ID: <?php echo esc_html($order->get_transaction_id()); ?></p>
                        <?php endif; ?>
                    </div>
                    <div>
                        <h5 class="text-sm font-bold text-gray-500 uppercase tracking-wider mb-2">Customer Note</h5>
                        <p class="text-gray-900 italic">
                            <?php echo $order->get_customer_note() ? nl2br(esc_html($order->get_customer_note())) : 'No notes provided.'; ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column: Customer & Actions -->
        <div class="space-y-6">
            <!-- Action Panel -->
            <div class="bg-white rounded-xl shadow-sm border border-blue-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 bg-blue-50/50 font-bold text-gray-900 flex justify-between items-center">
                    Order Actions
                    <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"></path></svg>
                </div>
                <div class="p-6">
                    <label class="block text-sm font-bold text-gray-700 mb-2">Change Status</label>
                    <select id="fmb-order-status" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 mb-4">
                        <?php
                        $wc_statuses = wc_get_order_statuses();
                        foreach ($wc_statuses as $slug => $name) {
                            $clean_slug = str_replace('wc-', '', $slug);
                            $selected = ($status === $clean_slug) ? 'selected' : '';
                            echo '<option value="' . esc_attr($clean_slug) . '" ' . $selected . '>' . esc_html($name) . '</option>';
                        }
                        ?>
                    </select>
                    
                    <button id="fmb-update-order-btn" data-order="<?php echo $order_id; ?>" class="w-full text-white bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:ring-blue-300 font-bold rounded-lg text-sm px-5 py-3 text-center transition">
                        Update Order
                    </button>
                    <p id="fmb-update-msg" class="text-sm mt-3 text-center font-medium hidden"></p>
                </div>
            </div>

            <!-- Customer Details -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 bg-gray-50 font-bold text-gray-700 flex justify-between items-center">
                    Customer Details
                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                </div>
                <div class="p-6 space-y-4">
                    <div>
                        <div class="text-sm text-gray-500 mb-1">Name</div>
                        <div class="font-bold text-gray-900"><?php echo esc_html($order->get_formatted_billing_full_name()); ?></div>
                    </div>
                    <?php if ($order->get_billing_phone()): ?>
                    <div>
                        <div class="text-sm text-gray-500 mb-1">Phone</div>
                        <a href="tel:<?php echo esc_attr($order->get_billing_phone()); ?>" class="font-bold text-blue-600 hover:underline flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>
                            <?php echo esc_html($order->get_billing_phone()); ?>
                        </a>
                    </div>
                    <?php endif; ?>
                    <div>
                        <div class="text-sm text-gray-500 mb-1">Shipping Address</div>
                        <div class="text-gray-900 leading-relaxed bg-gray-50 p-3 rounded border border-gray-100">
                            <?php echo wp_kses_post($order->get_formatted_shipping_address()); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        $('#fmb-update-order-btn').on('click', function(e) {
            e.preventDefault();
            var btn = $(this);
            var msg = $('#fmb-update-msg');
            var status = $('#fmb-order-status').val();
            var orderId = btn.data('order');
            
            btn.prop('disabled', true).text('Updating...');
            msg.hide();
            
            $.post(ajaxurl, {
                action: 'fmb_update_order_status',
                nonce: '<?php echo wp_create_nonce("fmb_order_nonce"); ?>',
                order_id: orderId,
                status: status
            }, function(response) {
                btn.prop('disabled', false).text('Update Order');
                if (response.success) {
                    msg.removeClass('text-red-600').addClass('text-green-600').text(response.data.message).fadeIn();
                    setTimeout(function() { location.reload(); }, 1000);
                } else {
                    msg.removeClass('text-green-600').addClass('text-red-600').text('Update failed.').fadeIn();
                }
            });
        });
    });
    </script>
    <?php
}
