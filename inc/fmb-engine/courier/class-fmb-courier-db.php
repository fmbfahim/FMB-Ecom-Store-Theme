<?php
/**
 * FMB Courier Database Management
 * Manages dedicated database table for courier bookings & consignment IDs.
 *
 * @package FMB_Ecom_Store
 */

if (!defined('ABSPATH')) {
    exit;
}

class FMB_Courier_DB {

    const TABLE_NAME = 'fmb_courier_consignments';

    /**
     * Get full table name with WordPress prefix.
     *
     * @return string
     */
    public static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_NAME;
    }

    /**
     * Create the custom database table if it doesn't exist.
     */
    public static function create_table() {
        global $wpdb;
        $table_name = self::get_table_name();
        $charset_collate = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            order_id BIGINT UNSIGNED NOT NULL,
            courier_name VARCHAR(50) NOT NULL DEFAULT 'steadfast',
            consignment_id VARCHAR(100) NOT NULL,
            tracking_code VARCHAR(100) DEFAULT NULL,
            delivery_status VARCHAR(50) DEFAULT 'in_review',
            cod_amount DECIMAL(10,2) DEFAULT 0.00,
            delivery_charge DECIMAL(10,2) DEFAULT 0.00,
            rider_note TEXT DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_order_id (order_id),
            KEY idx_consignment_id (consignment_id),
            KEY idx_courier_name (courier_name),
            KEY idx_delivery_status (delivery_status)
        ) {$charset_collate};";

        dbDelta($sql);

        // Run one-time migration for existing orders
        self::migrate_existing_orders();
    }

    /**
     * Get courier consignment record for a specific order.
     *
     * @param int $order_id
     * @return array|null
     */
    public static function get_consignment($order_id) {
        global $wpdb;
        $order_id = absint($order_id);
        if (!$order_id) {
            return null;
        }

        $table_name = self::get_table_name();
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table_name} WHERE order_id = %d LIMIT 1", $order_id),
            ARRAY_A
        );

        return $row ?: null;
    }

    /**
     * Save or update courier consignment data in the dedicated table.
     *
     * @param int   $order_id
     * @param array $data
     * @return bool|int ID of record on success, false on failure
     */
    public static function save_consignment($order_id, $data = array()) {
        global $wpdb;
        $order_id = absint($order_id);
        if (!$order_id) {
            return false;
        }

        $table_name = self::get_table_name();

        $courier_name    = sanitize_text_field($data['courier_name'] ?? 'steadfast');
        $consignment_id  = sanitize_text_field($data['consignment_id'] ?? '');
        $tracking_code   = sanitize_text_field($data['tracking_code'] ?? $consignment_id);
        $delivery_status = sanitize_text_field($data['delivery_status'] ?? 'in_review');
        $cod_amount      = isset($data['cod_amount']) ? floatval($data['cod_amount']) : 0.00;
        $delivery_charge = isset($data['delivery_charge']) ? floatval($data['delivery_charge']) : 0.00;
        $rider_note      = isset($data['rider_note']) ? sanitize_textarea_field($data['rider_note']) : null;

        if (empty($consignment_id)) {
            return false;
        }

        $existing = self::get_consignment($order_id);

        if ($existing) {
            $updated = $wpdb->update(
                $table_name,
                array(
                    'courier_name'    => $courier_name,
                    'consignment_id'  => $consignment_id,
                    'tracking_code'   => $tracking_code,
                    'delivery_status' => $delivery_status,
                    'cod_amount'      => $cod_amount,
                    'delivery_charge' => $delivery_charge,
                    'rider_note'      => $rider_note,
                    'updated_at'      => current_time('mysql'),
                ),
                array('order_id' => $order_id),
                array('%s', '%s', '%s', '%s', '%f', '%f', '%s', '%s'),
                array('%d')
            );
            $res_id = $existing['id'];
        } else {
            $inserted = $wpdb->insert(
                $table_name,
                array(
                    'order_id'        => $order_id,
                    'courier_name'    => $courier_name,
                    'consignment_id'  => $consignment_id,
                    'tracking_code'   => $tracking_code,
                    'delivery_status' => $delivery_status,
                    'cod_amount'      => $cod_amount,
                    'delivery_charge' => $delivery_charge,
                    'rider_note'      => $rider_note,
                    'created_at'      => current_time('mysql'),
                    'updated_at'      => current_time('mysql'),
                ),
                array('%d', '%s', '%s', '%s', '%s', '%f', '%f', '%s', '%s', '%s')
            );
            $res_id = $inserted ? $wpdb->insert_id : false;
        }

        // Keep WooCommerce order meta synced as fallback
        if ($order_id && function_exists('wc_get_order')) {
            $order = wc_get_order($order_id);
            if ($order) {
                $order->update_meta_data('_courier_provider', $courier_name);
                $order->update_meta_data('_courier_consignment_id', $consignment_id);
                if (strtolower($courier_name) === 'steadfast') {
                    $order->update_meta_data('_steadfast_consignment_id', $consignment_id);
                    $order->update_meta_data('_steadfast_delivery_status', $delivery_status);
                    if ($tracking_code) {
                        $order->update_meta_data('_steadfast_tracking_code', $tracking_code);
                    }
                } elseif (strtolower($courier_name) === 'pathao') {
                    $order->update_meta_data('_pathao_consignment_id', $consignment_id);
                    $order->update_meta_data('_pathao_delivery_status', $delivery_status);
                }
                $order->update_meta_data('_courier_delivery_status', $delivery_status);
                if ($cod_amount > 0) {
                    $order->update_meta_data('_courier_custom_cod_amount', $cod_amount);
                }
                if ($delivery_charge > 0) {
                    $order->update_meta_data('_courier_delivery_charge', $delivery_charge);
                }
                if (!empty($rider_note)) {
                    $order->update_meta_data('_courier_rider_note', $rider_note);
                }
                $order->save();
            }
        }

        return $res_id;
    }

    /**
     * Find order ID by consignment ID or tracking code.
     *
     * @param string $consignment_id
     * @return int|null
     */
    public static function find_order_by_consignment($consignment_id) {
        global $wpdb;
        $consignment_id = sanitize_text_field($consignment_id);
        if (empty($consignment_id)) {
            return null;
        }

        $table_name = self::get_table_name();
        $order_id = $wpdb->get_var(
            $wpdb->prepare("SELECT order_id FROM {$table_name} WHERE consignment_id = %s OR tracking_code = %s LIMIT 1", $consignment_id, $consignment_id)
        );

        return $order_id ? absint($order_id) : null;
    }

    /**
     * Delete consignment record for an order.
     *
     * @param int $order_id
     * @return bool
     */
    public static function delete_consignment($order_id) {
        global $wpdb;
        $order_id = absint($order_id);
        if (!$order_id) {
            return false;
        }
        $table_name = self::get_table_name();
        return (bool)$wpdb->delete($table_name, array('order_id' => $order_id), array('%d'));
    }

    /**
     * Automatically migrate existing booked orders from WooCommerce order meta into this table.
     */
    public static function migrate_existing_orders() {
        global $wpdb;

        $migrated_flag = get_option('fmb_courier_db_migrated_v1');
        if ($migrated_flag) {
            return;
        }

        $table_name = self::get_table_name();

        // 1. Gather all orders that have Steadfast, Pathao, or generic courier consignment IDs from HPOS or Postmeta
        $found_orders = array();

        // Try HPOS meta table if exists
        $hpos_table = $wpdb->prefix . 'wc_orders_meta';
        $hpos_exists = $wpdb->get_var("SHOW TABLES LIKE '{$hpos_table}'") === $hpos_table;

        if ($hpos_exists) {
            $hpos_rows = $wpdb->get_results("
                SELECT order_id, meta_key, meta_value 
                FROM {$hpos_table} 
                WHERE meta_key IN ('_steadfast_consignment_id', '_pathao_consignment_id', '_courier_consignment_id', '_fmb_consignment_id')
                  AND meta_value != ''
            ", ARRAY_A);

            foreach ($hpos_rows as $r) {
                $oid = absint($r['order_id']);
                if ($oid && !isset($found_orders[$oid])) {
                    $found_orders[$oid] = true;
                }
            }
        }

        // Also check wp_postmeta
        $pm_rows = $wpdb->get_results("
            SELECT post_id AS order_id 
            FROM {$wpdb->postmeta} 
            WHERE meta_key IN ('_steadfast_consignment_id', '_pathao_consignment_id', '_courier_consignment_id', '_fmb_consignment_id')
              AND meta_value != ''
        ", ARRAY_A);

        foreach ($pm_rows as $r) {
            $oid = absint($r['order_id']);
            if ($oid && !isset($found_orders[$oid])) {
                $found_orders[$oid] = true;
            }
        }

        // Populate table
        foreach (array_keys($found_orders) as $order_id) {
            $order = function_exists('wc_get_order') ? wc_get_order($order_id) : null;
            if (!$order) {
                continue;
            }

            // Determine courier details
            $courier_name   = 'steadfast';
            $consignment_id = '';
            $status         = 'in_review';
            $tracking_code  = '';

            $sf_cid = $order->get_meta('_steadfast_consignment_id');
            $pt_cid = $order->get_meta('_pathao_consignment_id');
            $custom = $order->get_meta('_courier_consignment_id');

            if (!empty($sf_cid)) {
                $courier_name   = 'steadfast';
                $consignment_id = $sf_cid;
                $status         = $order->get_meta('_steadfast_delivery_status') ?: 'in_review';
                $tracking_code  = $order->get_meta('_steadfast_tracking_code') ?: $sf_cid;
            } elseif (!empty($pt_cid)) {
                $courier_name   = 'pathao';
                $consignment_id = $pt_cid;
                $status         = $order->get_meta('_pathao_delivery_status') ?: 'pending';
                $tracking_code  = $pt_cid;
            } elseif (!empty($custom)) {
                $courier_name   = $order->get_meta('_courier_provider') ?: 'courier';
                $consignment_id = $custom;
                $status         = $order->get_meta('_courier_delivery_status') ?: 'in_review';
                $tracking_code  = $order->get_meta('_fmb_tracking_code') ?: $custom;
            }

            if (empty($consignment_id)) {
                continue;
            }

            $cod_amount      = floatval($order->get_meta('_courier_custom_cod_amount') ?: $order->get_total());
            $delivery_charge = floatval($order->get_meta('_courier_delivery_charge') ?: 0.00);
            $rider_note      = $order->get_meta('_courier_rider_note') ?: '';

            // Check if already in table
            $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table_name} WHERE order_id = %d", $order_id));
            if (!$exists) {
                $wpdb->insert(
                    $table_name,
                    array(
                        'order_id'        => $order_id,
                        'courier_name'    => $courier_name,
                        'consignment_id'  => $consignment_id,
                        'tracking_code'   => $tracking_code,
                        'delivery_status' => $status,
                        'cod_amount'      => $cod_amount,
                        'delivery_charge' => $delivery_charge,
                        'rider_note'      => $rider_note,
                        'created_at'      => current_time('mysql'),
                        'updated_at'      => current_time('mysql'),
                    ),
                    array('%d', '%s', '%s', '%s', '%s', '%f', '%f', '%s', '%s', '%s')
                );
            }
        }

        update_option('fmb_courier_db_migrated_v1', 1);
    }
}

/**
 * Global helper function to get courier consignment info from dedicated table.
 *
 * @param int $order_id
 * @return array|null
 */
function fmb_courier_db_get($order_id) {
    return FMB_Courier_DB::get_consignment($order_id);
}

/**
 * Global helper function to save/update courier consignment info into dedicated table.
 *
 * @param int   $order_id
 * @param array $data
 * @return bool|int
 */
function fmb_courier_db_save($order_id, $data = array()) {
    return FMB_Courier_DB::save_consignment($order_id, $data);
}
