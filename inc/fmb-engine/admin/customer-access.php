<?php
namespace fmb_engine\Admin;

class Customer_Access {
    public function __construct() {
        // AJAX handlers
        add_action('wp_ajax_ads_set_customer_access', [$this, 'handle_customer_access']);
        
        add_action('wp_ajax_fmb_engine_fetch_blocked_list', [$this, 'fetch_blocked_list']);

        add_action('admin_footer', [$this, 'output_inline_scripts']);
    }

    /**
     * Outputs inline JavaScript with necessary configurations
     */
    public function output_inline_scripts(): void {
        // Create security nonce
        $nonce = wp_create_nonce('ads_set_customer_access_secure_nonce');

        ?>
        <script type="text/javascript">
            (function($) {
                "use strict";

                // Configuration object
                const adsConfig = {
                    ajaxUrl: '<?php echo admin_url('admin-ajax.php'); ?>',
                    nonce: '<?php echo $nonce; ?>'
                };

                // Event handler for customer access buttons
                $(document).on('click', '.of-action-btn-order, .of-action-btn-block', function(e) {
                    e.preventDefault();
                    e.stopPropagation();

                    const $button = $(this);
                    const type = $button.data('type');
                    const value = $button.data('value');
                    const orderId = $button.data('order-id') || null;

                    // AJAX request
                    $.ajax({
                        url: adsConfig.ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'ads_set_customer_access',
                            _ajax_nonce: adsConfig.nonce,
                            type: type,
                            value: value,
                            order_id: orderId
                        },
                        success: function(response) {
                            if (response.success) {
                                const newStatus = response.data;

                                // Update button appearance
                                if (newStatus === 'blocked') {
                                    $button
                                        .removeClass('ads-customer-access-allowed')
                                        .addClass('ads-customer-access-blocked');
                                } else {
                                    // If unblocked from the Block List tab, remove the row smoothly
                                    const $tr = $button.closest('tr.fmb-engine-blocklist-row');
                                    if ($tr.length) {
                                        $tr.fadeOut(300, function() { 
                                            $(this).remove(); 
                                        });
                                    } else {
                                        $button
                                            .removeClass('ads-customer-access-blocked')
                                            .addClass('ads-customer-access-allowed');
                                    }
                                }
                            }
                        },
                        error: function() {
                            console.error('Customer access update failed');
                        }
                    });
                });
            })(jQuery);
        </script>
        <?php
    }

    public function handle_customer_access(): void {
        // Security verification
        check_ajax_referer('ads_set_customer_access_secure_nonce');

        // Sanitize input data
        $type_raw = isset($_POST['type']) ? sanitize_text_field(wp_unslash($_POST['type'])) : '';
        $value_raw = isset($_POST['value']) ? sanitize_text_field(wp_unslash($_POST['value'])) : '';
        $order_id = isset($_POST['order_id']) && !empty($_POST['order_id']) ? intval($_POST['order_id']) : null;

        // Validate input
        if (empty($type_raw) || empty($value_raw)) {
            wp_send_json_error('Invalid data parameters');
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'ads_customers_data';
        $alt_table  = $wpdb->prefix . 'fmb_customers_data';
        
        $types = explode('+', $type_raw);
        $values = explode('|', $value_raw);
        $final_access = '';

        foreach ($types as $i => $type) {
            $value = $values[$i] ?? $values[0];

            // Check for existing record
            $existing_record = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT data_access FROM {$table_name} 
                    WHERE data_type = %s AND data_value = %s",
                    $type,
                    $value
                )
            );

            // Prepare fields for update or insert
            $custom_note = isset($_POST['note']) ? sanitize_text_field(wp_unslash($_POST['note'])) : '';
            $note = !empty($custom_note) ? $custom_note : 'Manual Block';
            if ($type === 'partial_payment_dropout' && empty($custom_note)) $note = 'Checkout Abandoned';

            if ($existing_record) {
                // Toggle existing access status
                $new_access = ($existing_record->data_access === 'allowed') ? 'blocked' : 'allowed';
                $final_access = $new_access;
                
                $update_data = ['data_access' => $new_access];
                $update_format = ['%s'];
                
                // Only update note and optional order_id if we are blocking it again
                if ($new_access === 'blocked') {
                    if ($order_id) {
                        $update_data['order_id'] = $order_id;
                        $update_format[] = '%d';
                    }
                    $update_data['block_note'] = $note;
                    $update_format[] = '%s';
                }

                $wpdb->update(
                    $table_name,
                    $update_data,
                    ['data_type' => $type, 'data_value' => $value],
                    $update_format,
                    ['%s', '%s']
                );
            } else {
                // Create new record with default blocked access
                $new_access = 'blocked';
                $final_access = $new_access;

                $insert_data = [
                    'data_type' => $type,
                    'data_value' => $value,
                    'data_access' => $new_access,
                    'block_note' => $note
                ];
                $insert_format = ['%s', '%s', '%s', '%s'];

                if ($order_id) {
                    $insert_data['order_id'] = $order_id;
                    $insert_format[] = '%d';
                }

                $wpdb->insert(
                    $table_name,
                    $insert_data,
                    $insert_format
                );
            }

            // Clear transient cache for device hashes to ensure next checkout validation uses fresh data
            if ($type === 'device_hash') {
                delete_transient('ads_blocked_devices_cache');
            }
        }

        wp_send_json_success($final_access);
    }

    /**
     * AJAX handler to fetch blocked customer list with search
     */
    public function fetch_blocked_list(): void {
        check_ajax_referer('ads_set_customer_access_secure_nonce');

        $search = isset($_POST['search']) ? sanitize_text_field(wp_unslash($_POST['search'])) : '';
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'ads_customers_data';
        $alt_table  = $wpdb->prefix . 'fmb_customers_data';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") !== $table_name) {
            $table_name = $alt_table;
        }

        $query = "
            SELECT 
                COALESCE(NULLIF(order_id, 0), id) as group_key,
                MAX(id) as id,
                MAX(order_id) as order_id,
                MAX(block_note) as block_note,
                MAX(created_at) as created_at,
                GROUP_CONCAT(data_type SEPARATOR '+') as data_type,
                GROUP_CONCAT(data_value SEPARATOR '|') as data_value
            FROM {$table_name} 
            WHERE data_access = 'blocked' AND data_type != 'partial_payment_dropout'
        ";
        $params = [];
        
        if (!empty($search)) {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $query .= " AND (data_value LIKE %s OR order_id LIKE %s OR block_note LIKE %s OR data_type LIKE %s)";
            $params = array_fill(0, 4, $like);
        }
        
        $query .= " GROUP BY group_key ORDER BY created_at DESC LIMIT 100";
        
        if (!empty($params)) {
            $results = $wpdb->get_results($wpdb->prepare($query, ...$params));
        } else {
            $results = $wpdb->get_results($query);
        }
        
        ob_start();
        
        if (!empty($results)) {
            foreach ($results as $row) {
                $order_id_html = '-';
                $phone = 'N/A';
                $device_ip = 'N/A';

                // Attempt to fetch Order details for comprehensive block mapping
                if (!empty($row->order_id)) {
                    $order = function_exists('wc_get_order') ? wc_get_order($row->order_id) : null;
                    if ($order) {
                        $edit_url = $order->get_edit_order_url();
                        $order_id_html = '<a href="' . esc_url($edit_url) . '" target="_blank" style="color: #197278; text-decoration: none; font-weight: 600;">#' . esc_html($row->order_id) . '</a>';
                        
                        $phone = $order->get_billing_phone() ?: 'N/A';
                        $hash = method_exists($order, 'get_meta') ? $order->get_meta('_ads_device_hash', true) : get_post_meta($order->get_id(), '_ads_device_hash', true);
                        $ip = $order->get_customer_ip_address();
                        if (!$ip && method_exists($order, 'get_meta')) {
                            $ip = $order->get_meta('_customer_ip_address', true);
                        }
                        
                        if ($hash || $ip) {
                            $device_ip = '';
                            if ($ip) $device_ip .= $ip;
                            if ($hash && $ip) $device_ip .= ' / ';
                            if ($hash) $device_ip .= substr($hash, 0, 10) . '...';
                        }
                    } else {
                        $order_id_html = '<span style="font-weight: 600; color: #197278;">#' . esc_html($row->order_id) . '</span>';
                    }
                }

                $is_dropout = strpos($row->data_type, 'partial_payment_dropout') !== false;

                // Fallbacks enforcing the row data
                $data_types = explode('+', $row->data_type);
                $data_values = explode('|', $row->data_value);
                
                foreach ($data_types as $i => $dtype) {
                    $dvalue = $data_values[$i] ?? '';
                    if ($dtype === 'phone_number') {
                        $phone = esc_html($dvalue);
                    } elseif ($dtype === 'ip_address' || $dtype === 'device_hash') {
                        if ($device_ip === 'N/A' || empty($device_ip)) {
                            $device_ip = esc_html($dvalue);
                        }
                    } elseif ($dtype === 'partial_payment_dropout') {
                        $parts = explode('|', $dvalue);
                        if ($phone === 'N/A') {
                            $phone = esc_html($parts[0] ?? $dvalue);
                        }
                    }
                }

                $reason_html = '';
                if ($is_dropout) {
                    $reason_html .= '<span style="background: #fef08a; color: #854d0e; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 700; white-space: nowrap; margin-right:4px;">Checkout Abandoned</span>';
                }
                
                foreach (array_unique($data_types) as $type_item) {
                     if ($type_item === 'partial_payment_dropout') continue;
                     
                     $type_clean = strtoupper(str_replace('_', ' ', $type_item)) . ' BLOCKED';
                     if ($type_item === 'phone_number') $type_clean = 'PHONE BLOCKED';
                     if ($type_item === 'ip_address') $type_clean = 'IP BLOCKED';
                     if ($type_item === 'device_hash') $type_clean = 'DEVICE BLOCKED';

                     $reason_html .= '<span style="background: #fee2e2; color: #dc2626; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; display: inline-block; white-space: nowrap; margin-right:4px;">' . esc_html($type_clean) . '</span>';
                }
                
                $note = esc_html($row->block_note ?: '-');
                
                echo '<tr class="fmb-engine-blocklist-row">';
                echo '<td>' . $order_id_html . '</td>';
                echo '<td style="font-family: monospace; font-size: 13px;">' . $phone . '</td>';
                echo '<td style="font-family: monospace; font-size: 13px;">' . esc_html($device_ip) . '</td>';
                echo '<td>' . $reason_html . '</td>';
                echo '<td>' . $note . '</td>';
                echo '<td style="text-align: center;">';
                if($is_dropout) {
                    echo '<button class="of-action-btn-block ads-customer-access-blocked" data-type="' . esc_attr($row->data_type) . '" data-value="' . esc_attr($row->data_value) . '" style="background: #f59e0b; color: white; border: none; padding: 8px 16px; border-radius: 8px; font-size: 12px; font-weight: 600; cursor: pointer; transition: 0.2s; display: inline-flex; align-items: center; justify-content: center;">Clear</button>';
                } else {
                    echo '<button class="of-action-btn-block ads-customer-access-blocked" data-type="' . esc_attr($row->data_type) . '" data-value="' . esc_attr($row->data_value) . '" style="background: #ef4444; color: white; border: none; padding: 8px 16px; border-radius: 8px; font-size: 12px; font-weight: 600; cursor: pointer; transition: 0.2s; display: inline-flex; align-items: center; justify-content: center;">Unblock</button>';
                }
                echo '</td>';
                echo '</tr>';
            }
        } else {
            echo '<tr id="fmb-engine-empty-blocklist"><td colspan="5" style="text-align: center; padding: 32px; color: #94a3b8;">No blocked customers found.</td></tr>';
        }
        
        $html = ob_get_clean();
        wp_send_json_success(['html' => $html]);
    }
}