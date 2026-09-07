<?php

namespace fmb_engine\Courier;

if (!defined('ABSPATH')) {
    exit;
}

class OFLS_BD_Courier_Engine {

    /**
     * Main static method to securely fetch customer courier history.
     * Tier 1: Local WordPress Transient (24 hours) - To optimize API calls
     * Tier 2: Live BD Courier API - Directly fetching data
     * After fetching from API, stores data into Firebase
     * 
     * @param string $phone_number The raw phone number string
     * @param bool $only_from_cache Whether to only check local cache to avoid blocking page loads
     * @return array|null The statistics array or null on failure
     */
    public static function get_customer_history($phone_number, $only_from_cache = false) {
        // 1. Sanitization & Hashing
        $phone_number = preg_replace('/[^\d]/', '', $phone_number);
        if (strlen($phone_number) < 11) {
            return null; // Invalid phone format
        }

        // Anonymize the data to ensure privacy
        $hashed_phone = hash('sha256', $phone_number);
        $transient_key = 'ofls_bdc_cache_' . $hashed_phone;

        // Tier 1: Check Local Transient (Fastest) - Optimizes API calls and avoids server load
        $local_cache = get_transient($transient_key);
        if ($local_cache !== false) {
            if (is_array($local_cache)) {
                return $local_cache;
            }
            if (is_string($local_cache)) {
                $decoded = json_decode($local_cache, true);
                if (is_array($decoded)) {
                    return $decoded;
                }
            }
        }

        if ($only_from_cache) {
            return null;
        }

        // Fetch courier data directly using the BD Courier API key
        $bd_api_key = get_option('ofls_bd_courier_api_key', '');
        if (empty($bd_api_key)) {
            return null; // Cannot perform live lookup without the merchant's key
        }

        $api_url = "https://api.bdcourier.com/courier-check";
        
        $api_response = wp_remote_post($api_url, [
            'timeout' => 15,
            'sslverify' => false,
            'headers' => [
                'Authorization' => 'Bearer ' . $bd_api_key,
                'Content-Type'  => 'application/json'
            ],
            'body' => wp_json_encode(['phone' => $phone_number])
        ]);

        // Error handling for API connection
        if (is_wp_error($api_response)) {
            return ['error' => true, 'message' => 'Connection Error: ' . $api_response->get_error_message()];
        }

        $api_body = wp_remote_retrieve_body($api_response);
        $api_data = json_decode($api_body, true);
        
        $status_code = wp_remote_retrieve_response_code($api_response);

        // Error handling for invalid responses / API keys
        if ($status_code !== 200) {
            $error_msg = 'API Error (Status ' . $status_code . ')';
            if (is_array($api_data)) {
                if (isset($api_data['message'])) {
                    $error_msg = $api_data['message'];
                } elseif (isset($api_data['error'])) {
                    $error_msg = is_string($api_data['error']) ? $api_data['error'] : json_encode($api_data['error']);
                }
            }
            return ['error' => true, 'message' => $error_msg];
        }

        // Handle 200 OK but with API error status
        if (is_array($api_data) && isset($api_data['status']) && $api_data['status'] === 'error') {
            return [
                'error' => true, 
                'message' => $api_data['message'] ?? 'API returned an error.'
            ];
        }

        // Try to unwrap if it's inside "data" or "response"
        $api_payload = $api_data;
        if (is_array($api_data) && isset($api_data['data']) && is_array($api_data['data'])) {
            $api_payload = $api_data['data'];
        } elseif (is_array($api_data) && isset($api_data['response']) && is_array($api_data['response'])) {
            $api_payload = $api_data['response'];
        }
        
        // BD Courier new API places totals in "summary"
        $summary = (isset($api_payload['summary']) && is_array($api_payload['summary'])) ? $api_payload['summary'] : $api_payload;

        // Standardize variables with robust fallbacks
        $total_orders = (int) ($summary['total_orders'] ?? ($summary['total_parcel'] ?? ($summary['total_order'] ?? ($summary['total'] ?? 0))));
        $total_success = (int) ($summary['total_success'] ?? ($summary['success_parcel'] ?? ($summary['success'] ?? 0)));
        $total_returns = (int) ($summary['total_returns'] ?? ($summary['return_parcel'] ?? ($summary['cancelled_parcel'] ?? ($summary['cancel_parcel'] ?? ($summary['cancelled'] ?? ($summary['cancel'] ?? 0))))));

        // Error handling for completely empty or malformed responses
        if ($total_orders === 0 && empty($api_payload)) {
            return ['error' => true, 'message' => 'Empty response from courier API.'];
        }
        
        // Courier ratio calculation
        $courier_ratio = ($total_orders > 0) ? round(($total_success / $total_orders) * 100, 2) : 0;

        $metrics = [
            'total_order' => $total_orders,
            'total_success' => $total_success,
            'total_cancel' => $total_returns,
            'success_percent' => $courier_ratio,
            // cancel_percent is kept for UI backwards compatibility if needed
            'cancel_percent' => ($total_orders > 0) ? round(($total_returns / $total_orders) * 100, 2) : 0
        ];

        // Merge raw API payload to preserve any additional details (like courier breakdown arrays)
        if (is_array($api_payload)) {
            $metrics = array_merge($api_payload, $metrics);
        }

        // Store to Tier 1 local transient for 24 hours to optimize future calls
        set_transient($transient_key, json_encode($metrics), 24 * HOUR_IN_SECONDS);

        // Firebase Sync - Store all fetched data properly in Firebase

        if (!empty($firebase_project_id) && !empty($firebase_api_key)) {
            self::async_firebase_update($phone_number, $api_body, $firebase_project_id, $firebase_api_key);
        }

        return $metrics;
    }

    /**
     * Executes a non-blocking request to Firebase Firestore REST API to upload the fresh metrics.
     * Uses the raw phone number as the unique document ID to ensure updates without duplicates.
     */
    private static function async_firebase_update($phone_number, $raw_api_payload, $project_id, $api_key) {
        $current_timestamp_iso = gmdate('Y-m-d\TH:i:s\Z');

        // Firestore REST structure for a Document matching the legacy schema
        $document = [
            'name' => "projects/{$project_id}/databases/(default)/documents/courier_cache/{$phone_number}",
            'fields' => [
                'phone_number' => ['stringValue' => $phone_number],
                'courier_data' => ['stringValue' => $raw_api_payload],
                'source_api'   => ['stringValue' => home_url()],
                'timestamp'    => ['timestampValue' => $current_timestamp_iso]
            ]
        ];

        // Send non-blocking request with POST method using method override headers
        wp_remote_post($firestore_url, [
            'method'    => 'PATCH',
            'blocking'  => false,
            'timeout'   => 0.01,
            'headers'   => [
                'Content-Type' => 'application/json',
                'X-HTTP-Method-Override' => 'PATCH'
            ],
            'body'      => wp_json_encode($document)
        ]);
    }
}
