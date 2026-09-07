<?php

namespace fmb_engine\Courier;

class Courier_Remarks_Extractor {

    public function __construct() {
        add_action('init', [$this, 'schedule_cron_job']);
        add_action('ofls_courier_remarks_sync_cron', [$this, 'process_remarks_sync']);
    }

    public function schedule_cron_job() {
        if (!wp_next_scheduled('ofls_courier_remarks_sync_cron')) {
            wp_schedule_event(time(), 'hourly', 'ofls_courier_remarks_sync_cron');
        }
    }

    public function process_remarks_sync() {
        // Run Steadfast sync
        if (get_option('steadfast_enable', false) && get_option('steadfast_enable_remarks_sync', false)) {
            $this->process_courier_batch('steadfast');
        }
        
        // Run Pathao sync
        if (get_option('pathao_enable', false) && get_option('pathao_enable_remarks_sync', false)) {
            $this->process_courier_batch('pathao');
        }
    }

    private function process_courier_batch($active_courier) {
        $meta_key = "_{$active_courier}_consignment_id";

        $args = [
            'status' => ['wc-processing', 'wc-on-hold', 'wc-pending', 'wc-shipped', 'wc-in-transit'],
            'limit' => 20,
            'meta_query' => [
                [
                    'key' => $meta_key,
                    'compare' => 'EXISTS'
                ]
            ],
            'orderby' => 'date',
            'order' => 'ASC',
        ];

        $orders = wc_get_orders($args);

        if (empty($orders)) {
            return;
        }

        $skip_statuses = ['delivered', 'success', 'cancelled', 'returned', 'failed', 'pickup cancel', 'pickup_cancel'];

        foreach ($orders as $order) {
            $consignment_id = $order->get_meta($meta_key);
            if (!$consignment_id) {
                continue;
            }

            $current_status = strtolower($order->get_meta("_{$active_courier}_delivery_status"));
            
            if (in_array($current_status, $skip_statuses)) {
                continue; // Skip finalized orders
            }

            $fetched_data = $this->fetch_courier_remark($active_courier, $consignment_id);
            $remark = $fetched_data['remark'];
            $new_status = $fetched_data['status'];

            if (!empty($new_status) && $new_status !== $current_status) {
                $order->update_meta_data("_{$active_courier}_delivery_status", $new_status);
                // Also optionally update the global status
                $order->update_meta_data('ads_courier_status', $new_status);
                
                $status_clean = ucwords(str_replace('_', ' ', sanitize_text_field($new_status)));
                $order->add_order_note(sprintf(__('🚚 Courier Status Sync: Package marked as "%s".', 'fmb-engine'), $status_clean));
                if (function_exists('fmb_record_courier_history_entry')) {
                    fmb_record_courier_history_entry($order->get_id(), $status_clean, 'Automated Sync: ' . $status_clean, ucfirst($active_courier) . ' Sync');
                }
                $order->save();
            }

            if (!empty($remark)) {
                $old_remark = $order->get_meta('_ofls_courier_latest_remark');
                if ($old_remark !== $remark) {
                    $order->update_meta_data('_ofls_courier_latest_remark', sanitize_text_field($remark));
                    $order->add_order_note(sprintf(__('🚚 Courier Remarks Update: %s', 'fmb-engine'), sanitize_text_field($remark)));
                    if (function_exists('fmb_record_courier_history_entry')) {
                        fmb_record_courier_history_entry($order->get_id(), 'Rider Remark', sanitize_text_field($remark), ucfirst($active_courier) . ' Rider');
                    }
                    $order->save();
                }
            }
        }
    }

    private function fetch_courier_remark($courier, $consignment) {
        $remark = '';
        $status = '';
        
        if ($courier === 'steadfast') {
            $api_key = get_option('steadfast_api_key');
            $secret_key = get_option('steadfast_secret_key');

            $response = wp_remote_get('https://portal.packzy.com/api/v1/status_by_cid/' . urlencode($consignment), [
                'headers' => [
                    'Api-Key' => $api_key,
                    'Secret-Key' => $secret_key
                ],
                'timeout' => 15
            ]);

            if (!is_wp_error($response)) {
                $body = wp_remote_retrieve_body($response);
                $data = json_decode($body, true);
                
                if (isset($data['status']) && $data['status'] === 200) {
                    if (isset($data['delivery_status'])) {
                        $status = $data['delivery_status'];
                    } elseif (isset($data['data']['delivery_status'])) {
                        $status = $data['data']['delivery_status'];
                    }
                }

                $rider_name = isset($data['rider_name']) ? $data['rider_name'] : (isset($data['data']['rider_name']) ? $data['data']['rider_name'] : '');
                $rider_phone = isset($data['rider_phone']) ? $data['rider_phone'] : (isset($data['data']['rider_phone']) ? $data['data']['rider_phone'] : '');
                $rider_info = '';
                if ($rider_name) $rider_info .= "Rider: " . $rider_name;
                if ($rider_phone) $rider_info .= " (" . $rider_phone . ")";

                if (isset($data['note']) && !empty($data['note'])) {
                    $remark = $data['note'];
                } elseif (isset($data['data']['note']) && !empty($data['data']['note'])) {
                    $remark = $data['data']['note'];
                } elseif (isset($data['remarks']) && !empty($data['remarks'])) {
                    $remark = $data['remarks'];
                } elseif (isset($data['data']['remarks']) && !empty($data['data']['remarks'])) {
                    $remark = $data['data']['remarks'];
                }

                if (!empty($rider_info)) {
                    $remark = $remark ? $rider_info . " - " . $remark : $rider_info;
                }
            }
        } elseif ($courier === 'pathao') {
            $token = $this->get_pathao_access_token();

            if ($token) {
                $response = wp_remote_get('https://api-hermes.pathao.com/aladdin/api/v1/orders/' . urlencode($consignment), [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $token,
                        'Accept' => 'application/json'
                    ],
                    'timeout' => 15
                ]);

                if (!is_wp_error($response)) {
                    $body = wp_remote_retrieve_body($response);
                    $data = json_decode($body, true);

                    if (isset($data['data']['order_status'])) {
                        $status = $data['data']['order_status'];
                    }

                    $rider_name = isset($data['data']['rider_name']) ? $data['data']['rider_name'] : '';
                    $rider_phone = isset($data['data']['rider_phone']) ? $data['data']['rider_phone'] : '';
                    $rider_info = '';
                    if ($rider_name) $rider_info .= "Rider: " . $rider_name;
                    if ($rider_phone) $rider_info .= " (" . $rider_phone . ")";

                    if (isset($data['data']['reason']) && !empty($data['data']['reason'])) {
                        $remark = $data['data']['reason'];
                    } elseif (isset($data['data']['remarks']) && !empty($data['data']['remarks'])) {
                        $remark = $data['data']['remarks'];
                    } elseif (isset($data['data']['comments']) && !empty($data['data']['comments'])) {
                        $remark = $data['data']['comments'];
                    }
                    
                    if (!empty($rider_info)) {
                        $remark = $remark ? $rider_info . " - " . $remark : $rider_info;
                    }
                }
            }
        }

        return ['remark' => $remark, 'status' => strtolower($status)];
    }

    private function get_pathao_access_token() {
        $token_data = get_transient('pathao_api_token_data');
        if ($token_data && !empty($token_data['access_token'])) {
            return $token_data['access_token'];
        }

        $api_key = get_option('pathao_api_key');
        $secret_key = get_option('pathao_secret_key');
        
        $response = wp_remote_post('https://api-hermes.pathao.com/aladdin/api/v1/external/login', array(
            'headers' => array(
                'accept' => 'application/json',
                'content-type' => 'application/json'
            ),
            'body' => wp_json_encode(array(
                'client_id' => $api_key,
                'client_secret' => $secret_key,
            )),
            'timeout' => 15
        ));

        if (is_wp_error($response)) {
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (isset($data['access_token'])) {
            $expires_in = isset($data['expires_in']) ? (int)$data['expires_in'] : 86400;
            set_transient('pathao_api_token_data', $data, $expires_in - 300);
            return $data['access_token'];
        }

        return false;
    }
}
