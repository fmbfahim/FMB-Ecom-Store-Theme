<?php
namespace fmb_engine;

class Custom_Order_Statuses {

    private $statuses = [
        'ads-purchase'  => ['label' => 'Purchase',  'color' => '#28a745'],
        'ads-recovered' => ['label' => 'Recovered', 'color' => '#28a745'],
        'ads-confirmed' => ['label' => 'Confirmed', 'color' => '#90be6d'],
        'ads-shipping'  => ['label' => 'Shipping',  'color' => '#577590'],
        'ads-cpending'  => ['label' => 'Courier Pending', 'color' => '#f39c12'],
        'ads-intransit' => ['label' => 'In Transit','color' => '#3498db'],
        'ads-inhub'     => ['label' => 'In Hub',    'color' => '#9b59b6'],
        'ads-rider'     => ['label' => 'Rider',     'color' => '#e67e22'],
        'ads-returned'  => ['label' => 'Returned',  'color' => '#f94144'],
        'ads-delivered' => ['label' => 'Delivered', 'color' => '#43aa8b'],
        'ads-incomplete'=> ['label' => 'Incomplete','color' => '#a29bfe'],
    ];

    public function __construct() {
        // Safe check for WooCommerce orders page
        if (isset($_GET['page']) && $_GET['page'] === 'wc-orders') {
            unset($this->statuses['ads-incomplete']);
        } else if (isset($_GET['post_type']) && $_GET['post_type'] === 'shop_order') {
            unset($this->statuses['ads-incomplete']);
        }
        add_action('init', [$this, 'register_custom_order_statuses']);
        add_filter('wc_order_statuses', [$this, 'add_custom_order_statuses']);
        add_action('admin_head', [$this, 'custom_order_status_colors']);

        add_filter('bulk_actions-edit-shop_order', [$this, 'add_bulk_actions'], 99);
        add_filter('bulk_actions-woocommerce_page_wc-orders', [$this, 'add_bulk_actions'], 99);
        add_filter('handle_bulk_actions-edit-shop_order', [$this, 'handle_bulk_action'], 99, 3 );
        add_filter('handle_bulk_actions-woocommerce_page_wc-orders', [$this, 'handle_bulk_action'], 99, 3 );
    }

    public function register_custom_order_statuses() {
        foreach ($this->statuses as $slug => $data) {
            register_post_status('wc-' . $slug, [
                'label'                     => $data['label'],
                'public'                    => true,
                'exclude_from_search'       => false,
                'show_in_admin_all_list'    => true,
                'show_in_admin_status_list' => true,
                'label_count'               => _n_noop("{$data['label']} (%s)", "{$data['label']} (%s)")
            ]);
        }
    }

    public function add_custom_order_statuses($order_statuses) {
        $custom_statuses = [];
        foreach ($this->statuses as $slug => $data) {
            $custom_statuses['wc-' . $slug] = $data['label'];
        }

        $new_statuses = [];
        foreach ($order_statuses as $key => $label) {
            $new_statuses[$key] = $label;
            if ($key === 'wc-processing') {
                $new_statuses = array_merge($new_statuses, $custom_statuses);
            }
        }
        return $new_statuses;
    }

    public function custom_order_status_colors() {
        echo '<style>';
        foreach ($this->statuses as $slug => $data) {
            echo ".order-status.status-{$slug} { background: {$data['color']}; color: #fff; }";
        }
        echo ".order-status.status-cancelled { background: #dc3545; color: #fff; }";
        echo '</style>';
    }

    public function add_bulk_actions($bulk_actions) {
        $bulk_actions['ads_send_to_steadfast'] = __('Send to Steadfast Courier', 'ads');
        foreach ($this->statuses as $key => $data) {
            $bulk_actions['ads_change_status_to_' . $key] = sprintf(__('Change Status to %s', 'ads'), $data['label']);
        }
        return $bulk_actions;
    }

    public function handle_bulk_action( $redirect_to, $action, $post_ids ) {
        $order_ids = array_map('intval', $post_ids);
        if (empty($order_ids)) {
            return $redirect_to;
        }

        $status_message = '';
        $notice_type = 'success';

        // Handle custom status changes
        if (strpos($action, 'ads_change_status_to_') === 0) {
            $status = str_replace('ads_change_status_to_', '', $action);
            if (isset($this->statuses[$status])) {
                foreach ($order_ids as $order_id) {
                    $order = wc_get_order($order_id);
                    if ($order) {
                        $order->set_status($status);
                        $order->save();
                    }
                }
                $status_message = sprintf(__('Order status changed to %s.', 'ads'), $this->statuses[$status]['label']);
            } else {
                $status_message = __('Invalid status.', 'ads');
                $notice_type = 'error';
            }
        }
        // Handle other actions (if needed)
        elseif ($action === 'delete') {
            foreach ($order_ids as $order_id) {
                $order = wc_get_order($order_id);
                if ($order) {
                    $order->delete(true);
                }
            }
            $status_message = 'Selected orders deleted successfully.';
        }
        elseif ($action === 'create') {
            foreach ($order_ids as $order_id) {
                $order = wc_get_order($order_id);
                if ($order) {
                    $order->set_status('processing');
                    $order->save();
                }
            }
            $status_message = 'Selected orders converted successfully.';
        }
        elseif ($action === 'ads_send_to_steadfast') {
            $success = 0;
            $failed = 0;
            foreach ($order_ids as $order_id) {
                if (class_exists('\FMB_BD_Courier_Engine')) {
                    $result = \FMB_BD_Courier_Engine::send_to_steadfast($order_id);
                    if (isset($result['success'])) {
                        $order = wc_get_order($order_id);
                        if ($order) {
                            $order->update_status('ads-shipping', 'Order successfully booked to Steadfast Courier.');
                        }
                        $success++;
                    } else {
                        $failed++;
                    }
                }
            }
            $status_message = "Sent to Steadfast: $success successful, $failed failed.";
            $notice_type = $failed > 0 ? 'warning' : 'success';
        }
        else {
            $status_message = 'No valid bulk action performed.';
            $notice_type = 'warning';
        }

        // Build the redirect URL with message
        $redirect_to = remove_query_arg(['action', 'action2', 'order_ids'], $redirect_to);
        $redirect_to = add_query_arg([
            'message' => urlencode($status_message),
            'type'    => $notice_type
        ], $redirect_to);

        return $redirect_to;
    }
}
