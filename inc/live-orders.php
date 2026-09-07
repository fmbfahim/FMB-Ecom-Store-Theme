<?php
add_action('admin_menu', 'fmb_live_orders_menu');
function fmb_live_orders_menu() {
    add_submenu_page(
        'fmb-store',
        'Live Orders',
        'Live Orders',
        'manage_options',
        'fmb-live-orders',
        'fmb_live_orders_page_html'
    );
}

function fmb_live_orders_page_html() {
    if (!class_exists('WooCommerce')) {
        echo '<div class="wrap"><h1>WooCommerce is not active.</h1></div>';
        return;
    }

    // Get today's orders
    $args = array(
        'date_created' => date('Y-m-d'),
        'return'       => 'objects',
        'limit'        => -1,
    );
    $orders = wc_get_orders($args);

    $total_orders = count($orders);
    $total_revenue = 0;
    
    foreach ($orders as $order) {
        // Count all orders for today
        $total_revenue += $order->get_total();
    }

    ?>
    <div class="wrap">
        <h1 style="margin-bottom: 20px;">Today's Live Orders</h1>
        
        <div style="display: flex; gap: 20px; margin-bottom: 20px;">
            <div style="background: #fff; padding: 20px; border-radius: 8px; border: 1px solid #ccd0d4; min-width: 200px; text-align: center;">
                <h3 style="margin-top: 0; color: #646970;">Total Orders</h3>
                <div style="font-size: 32px; font-weight: bold; color: #1d2327;"><?php echo esc_html($total_orders); ?></div>
            </div>
            <div style="background: #fff; padding: 20px; border-radius: 8px; border: 1px solid #ccd0d4; min-width: 200px; text-align: center;">
                <h3 style="margin-top: 0; color: #646970;">Total Revenue</h3>
                <div style="font-size: 32px; font-weight: bold; color: #1d2327;"><?php echo wc_price($total_revenue); ?></div>
            </div>
        </div>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width: 100px;">Order ID</th>
                    <th style="width: 100px;">Time</th>
                    <th>Customer</th>
                    <th>Items</th>
                    <th style="width: 120px;">Status</th>
                    <th style="width: 120px;">Total</th>
                    <th style="width: 100px;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($orders)): ?>
                    <tr><td colspan="7">No orders today yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($orders as $order): ?>
                        <tr>
                            <td><strong>#<?php echo esc_html($order->get_order_number()); ?></strong></td>
                            <td><?php echo esc_html(wc_format_datetime($order->get_date_created(), 'h:i A')); ?></td>
                            <td>
                                <div><?php echo esc_html($order->get_formatted_billing_full_name()); ?></div>
                                <div style="color: #646970; font-size: 12px;"><?php echo esc_html($order->get_billing_phone()); ?></div>
                            </td>
                            <td>
                                <?php 
                                $items = $order->get_items();
                                foreach ($items as $item) {
                                    echo '<div>' . esc_html($item->get_name()) . ' <strong style="color:#000;">x ' . esc_html($item->get_quantity()) . '</strong></div>';
                                }
                                ?>
                            </td>
                            <td>
                                <?php
                                $status = $order->get_status();
                                $status_name = wc_get_order_status_name($status);
                                $color = '#a00'; // Failed/Cancelled
                                if ($status === 'completed') $color = '#7ad03a';
                                else if ($status === 'processing') $color = '#9c5d90';
                                else if ($status === 'on-hold') $color = '#ffba00';
                                ?>
                                <span style="background: <?php echo esc_attr($color); ?>; color: white; padding: 3px 8px; border-radius: 4px; font-size: 12px; font-weight: bold;">
                                    <?php echo esc_html($status_name); ?>
                                </span>
                            </td>
                            <td><strong><?php echo wp_kses_post($order->get_formatted_order_total()); ?></strong></td>
                            <td>
                                <a href="<?php echo esc_url($order->get_edit_order_url()); ?>" class="button button-primary">View</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <script>
        // Auto refresh every 60 seconds
        setTimeout(function(){
            location.reload();
        }, 60000);
    </script>
    <?php
}
