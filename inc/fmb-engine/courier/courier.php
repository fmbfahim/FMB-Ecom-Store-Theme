<?php

namespace fmb_engine\Courier;

use fmb_engine\Core\Base;
if(!class_exists(__NAMESPACE__ . '\Courier')){
    class Courier extends Base {

        public function __construct() {
            // Initialization relies wholly on WP Remote Post proxying
        }

        /**
         * Get courier history from cache
         *
         * @param string $phone_number
         * @return array|null
         */
        public static function get_courier_history_from_cache($phone_number) {
            $transient_key = 'orderflow_courier_' . md5($phone_number);
            $cached = get_transient($transient_key);
            if ($cached !== false) {
                if (is_array($cached)) {
                    return $cached;
                }
                if (is_string($cached)) {
                    $decoded = json_decode($cached, true);
                    if (is_array($decoded)) {
                        return $decoded;
                    }
                }
            }

            // Fallback to OFLS_BD_Courier_Engine cache check
            $engine_cached = \fmb_engine\Courier\OFLS_BD_Courier_Engine::get_customer_history($phone_number, true);
            if ($engine_cached) {
                set_transient($transient_key, json_encode($engine_cached), 24 * HOUR_IN_SECONDS);
                return $engine_cached;
            }

            return null;
        }

        /**
         * Force fetch courier history from APIs
         *
         * @param string $phone_number
         * @return array|null
         */
        public static function fetch_courier_history_from_apis($phone_number) {
            $transient_key = 'orderflow_courier_' . md5($phone_number);
            
            // Force fetch via engine
            $history = \fmb_engine\Courier\OFLS_BD_Courier_Engine::get_customer_history($phone_number, false);
            if ($history) {
                set_transient($transient_key, json_encode($history), 24 * HOUR_IN_SECONDS);
                return $history;
            }

            return null;
        }

    }
}