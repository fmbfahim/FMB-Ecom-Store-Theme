<?php
/**
 * FMB Employee Management & Payroll System — Backend Manager
 * Handles DB schema, employee profiles, real-time performance tracking, and commission/payroll calculations.
 */

if (!defined('ABSPATH')) {
    exit;
}

class FMB_Employee_Manager {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('init', array($this, 'check_db_tables'));
        add_action('woocommerce_order_status_changed', array($this, 'track_order_status_action'), 10, 4);
    }

    /**
     * Create Database Tables
     */
    public function check_db_tables() {
        if (get_option('fmb_employee_db_version') !== '1.0.0') {
            $this->create_tables();
            update_option('fmb_employee_db_version', '1.0.0');
        }
    }

    public function create_tables() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset_collate = $wpdb->get_charset_collate();

        // 1. Employees Table
        $emp_table = $wpdb->prefix . 'fmb_employees';
        $sql_emp = "CREATE TABLE IF NOT EXISTS {$emp_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            name VARCHAR(255) NOT NULL,
            phone VARCHAR(50) NULL,
            email VARCHAR(255) NULL,
            designation VARCHAR(150) NOT NULL DEFAULT 'Order Confirmation Officer',
            base_salary DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            commission_type VARCHAR(20) NOT NULL DEFAULT 'fixed_per_order',
            commission_rate DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            joining_date DATE NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            notes TEXT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY status (status)
        ) {$charset_collate};";
        dbDelta($sql_emp);

        // 2. Monthly Payroll Table
        $payroll_table = $wpdb->prefix . 'fmb_employee_payroll';
        $sql_payroll = "CREATE TABLE IF NOT EXISTS {$payroll_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            employee_id BIGINT UNSIGNED NOT NULL,
            month_year VARCHAR(7) NOT NULL,
            base_salary DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            total_confirmed_orders INT NOT NULL DEFAULT 0,
            total_delivered_orders INT NOT NULL DEFAULT 0,
            total_cancelled_orders INT NOT NULL DEFAULT 0,
            total_sales_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            earned_commission DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            bonus DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            deductions DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            net_payable DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            payment_status VARCHAR(20) NOT NULL DEFAULT 'unpaid',
            paid_at DATETIME NULL,
            payment_method VARCHAR(50) NULL,
            payment_trx_id VARCHAR(100) NULL,
            notes TEXT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY emp_month (employee_id, month_year)
        ) {$charset_collate};";
        dbDelta($sql_payroll);
    }

    /**
     * Track Order Actions in Meta
     */
    public function track_order_status_action($order_id, $from_status, $to_status, $order) {
        $current_user_id = get_current_user_id();
        if ($current_user_id > 0 && is_a($order, 'WC_Order')) {
            $user = get_userdata($current_user_id);
            $user_name = $user ? $user->display_name : 'Staff';
            
            // Set action by meta if not already set or updated
            $order->update_meta_data('_fmb_action_by', $user_name);
            $order->update_meta_data('_fmb_action_user_id', $current_user_id);
            
            if ($to_status === 'processing' || $to_status === 'completed') {
                $order->update_meta_data('_fmb_confirmed_by_user_id', $current_user_id);
                $order->update_meta_data('_fmb_confirmed_by_name', $user_name);
            }
            if ($to_status === 'cancelled' || $to_status === 'failed') {
                $order->update_meta_data('_fmb_cancelled_by_user_id', $current_user_id);
                $order->update_meta_data('_fmb_cancelled_by_name', $user_name);
            }
            
            // Safe save to avoid infinite hooks
            $order->save_meta_data();
        }
    }

    /**
     * Get All Employees
     */
    public static function get_employees($status = null) {
        global $wpdb;
        $table = $wpdb->prefix . 'fmb_employees';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") !== $table) {
            return [];
        }

        $sql = "SELECT * FROM {$table}";
        if ($status) {
            $sql .= $wpdb->prepare(" WHERE status = %s", $status);
        }
        $sql .= " ORDER BY id DESC";

        return $wpdb->get_results($sql);
    }

    /**
     * Get Single Employee by ID
     */
    public static function get_employee($id) {
        global $wpdb;
        $table = $wpdb->prefix . 'fmb_employees';
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id));
    }

    /**
     * Get Employee by User ID
     */
    public static function get_employee_by_user_id($user_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'fmb_employees';
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE user_id = %d", $user_id));
    }

    /**
     * Save / Update Employee
     */
    public static function save_employee($data) {
        global $wpdb;
        $table = $wpdb->prefix . 'fmb_employees';

        $id = !empty($data['id']) ? intval($data['id']) : 0;
        $user_id = !empty($data['user_id']) ? intval($data['user_id']) : 0;
        $name = sanitize_text_field($data['name'] ?? '');
        $phone = sanitize_text_field($data['phone'] ?? '');
        $email = sanitize_email($data['email'] ?? '');
        $designation = sanitize_text_field($data['designation'] ?? 'Order Confirmation Officer');
        $base_salary = max(0, floatval($data['base_salary'] ?? 0));
        $commission_type = in_array($data['commission_type'] ?? '', ['fixed_per_order', 'percent_of_sales']) ? $data['commission_type'] : 'fixed_per_order';
        $commission_rate = max(0, floatval($data['commission_rate'] ?? 0));
        $joining_date = !empty($data['joining_date']) ? sanitize_text_field($data['joining_date']) : current_time('Y-m-d');
        $status = in_array($data['status'] ?? '', ['active', 'inactive']) ? $data['status'] : 'active';
        $notes = sanitize_textarea_field($data['notes'] ?? '');

        if (empty($name)) {
            return new WP_Error('missing_name', 'Employee name is required.');
        }

        // If user_id is provided, auto-fill email/name if empty
        if ($user_id > 0 && empty($email)) {
            $user = get_userdata($user_id);
            if ($user) {
                $email = $user->user_email;
                if (empty($name)) $name = $user->display_name;
            }
        }

        $fields = [
            'user_id'         => $user_id,
            'name'            => $name,
            'phone'           => $phone,
            'email'           => $email,
            'designation'     => $designation,
            'base_salary'     => $base_salary,
            'commission_type' => $commission_type,
            'commission_rate' => $commission_rate,
            'joining_date'    => $joining_date,
            'status'          => $status,
            'notes'           => $notes,
        ];

        $format = ['%d', '%s', '%s', '%s', '%s', '%f', '%s', '%f', '%s', '%s', '%s'];

        if ($id > 0) {
            $wpdb->update($table, $fields, ['id' => $id], $format, ['%d']);
            return $id;
        } else {
            $wpdb->insert($table, $fields, $format);
            return $wpdb->insert_id;
        }
    }

    /**
     * Delete Employee
     */
    public static function delete_employee($id) {
        global $wpdb;
        $table = $wpdb->prefix . 'fmb_employees';
        return $wpdb->delete($table, ['id' => intval($id)], ['%d']);
    }

    /**
     * Calculate Real-time Employee Performance
     * Queries orders confirmed, delivered, cancelled within a date range
     */
    public static function get_employee_performance($emp_id, $start_date = null, $end_date = null) {
        $emp = self::get_employee($emp_id);
        if (!$emp) {
            return false;
        }

        global $wpdb;
        $user_id = intval($emp->user_id);
        $emp_name = $emp->name;

        // Default to current month if no date specified
        if (!$start_date) {
            $start_date = current_time('Y-m-01 00:00:00');
        } else {
            $start_date = date('Y-m-d 00:00:00', strtotime($start_date));
        }

        if (!$end_date) {
            $end_date = current_time('Y-m-d 23:59:59');
        } else {
            $end_date = date('Y-m-d 23:59:59', strtotime($end_date));
        }

        // Query orders from WooCommerce (Support both HPOS & CPT)
        $hpos_orders_table = $wpdb->prefix . 'wc_orders';
        $hpos_meta_table   = $wpdb->prefix . 'wc_orders_meta';
        $post_meta_table   = $wpdb->prefix . 'postmeta';
        $posts_table       = $wpdb->prefix . 'posts';

        $is_hpos = ($wpdb->get_var("SHOW TABLES LIKE '{$hpos_orders_table}'") === $hpos_orders_table);

        $confirmed_orders = [];
        $delivered_orders = [];
        $cancelled_orders = [];
        $total_sales = 0.00;

        if ($is_hpos) {
            // HPOS Query
            $sql = "
                SELECT DISTINCT o.id, o.status, o.total_amount, o.date_updated_gmt
                FROM {$hpos_orders_table} o
                LEFT JOIN {$hpos_meta_table} m1 ON o.id = m1.order_id AND m1.meta_key = '_fmb_action_user_id'
                LEFT JOIN {$hpos_meta_table} m2 ON o.id = m2.order_id AND m2.meta_key = '_fmb_action_by'
                LEFT JOIN {$hpos_meta_table} m3 ON o.id = m3.order_id AND m3.meta_key = '_fmb_confirmed_by_user_id'
                WHERE o.date_updated_gmt >= %s AND o.date_updated_gmt <= %s
                AND (
                    m1.meta_value = %s 
                    OR m2.meta_value = %s
                    OR m3.meta_value = %s
                    OR o.customer_id = %s
                )
            ";
            $results = $wpdb->get_results($wpdb->prepare($sql, $start_date, $end_date, (string)$user_id, $emp_name, (string)$user_id, (string)$user_id));
        } else {
            // Legacy CPT Query
            $sql = "
                SELECT DISTINCT p.ID as id, p.post_status as status
                FROM {$posts_table} p
                LEFT JOIN {$post_meta_table} m1 ON p.ID = m1.post_id AND m1.meta_key = '_fmb_action_user_id'
                LEFT JOIN {$post_meta_table} m2 ON p.ID = m2.post_id AND m2.meta_key = '_fmb_action_by'
                LEFT JOIN {$post_meta_table} m3 ON p.ID = m3.post_id AND m3.meta_key = '_fmb_confirmed_by_user_id'
                WHERE p.post_type = 'shop_order'
                AND p.post_modified >= %s AND p.post_modified <= %s
                AND (
                    m1.meta_value = %s 
                    OR m2.meta_value = %s
                    OR m3.meta_value = %s
                )
            ";
            $results = $wpdb->get_results($wpdb->prepare($sql, $start_date, $end_date, (string)$user_id, $emp_name, (string)$user_id));
        }

        if (!empty($results)) {
            foreach ($results as $row) {
                $status = str_replace('wc-', '', $row->status);
                $order_obj = wc_get_order($row->id);
                $order_total = $order_obj ? floatval($order_obj->get_total()) : floatval($row->total_amount ?? 0);

                if (in_array($status, ['processing', 'completed', 'on-hold', 'partial-paid'])) {
                    $confirmed_orders[] = $row->id;
                    $total_sales += $order_total;
                }
                if ($status === 'completed') {
                    $delivered_orders[] = $row->id;
                }
                if (in_array($status, ['cancelled', 'failed', 'refunded'])) {
                    $cancelled_orders[] = $row->id;
                }
            }
        }

        $count_confirmed = count($confirmed_orders);
        $count_delivered = count($delivered_orders);
        $count_cancelled = count($cancelled_orders);
        $total_handled   = $count_confirmed + $count_cancelled;

        $conf_rate = $total_handled > 0 ? round(($count_confirmed / $total_handled) * 100, 1) : 0;
        $deliv_rate = $count_confirmed > 0 ? round(($count_delivered / $count_confirmed) * 100, 1) : 0;

        // Calculate Commission
        $earned_commission = 0.00;
        if ($emp->commission_type === 'percent_of_sales') {
            $earned_commission = round(($total_sales * ($emp->commission_rate / 100)), 2);
        } else {
            // Fixed per confirmed order
            $earned_commission = round($count_confirmed * $emp->commission_rate, 2);
        }

        return [
            'employee'           => $emp,
            'start_date'         => $start_date,
            'end_date'           => $end_date,
            'total_handled'      => $total_handled,
            'confirmed_orders'   => $count_confirmed,
            'delivered_orders'   => $count_delivered,
            'cancelled_orders'   => $count_cancelled,
            'confirmation_rate'  => $conf_rate,
            'delivery_rate'      => $deliv_rate,
            'total_sales'        => $total_sales,
            'earned_commission'  => $earned_commission,
            'base_salary'        => floatval($emp->base_salary),
            'estimated_total'    => floatval($emp->base_salary) + $earned_commission,
        ];
    }

    /**
     * Generate Monthly Payroll Record for a specific month
     */
    public static function generate_monthly_payroll($month_year = null) {
        if (!$month_year) {
            $month_year = current_time('Y-m');
        }

        $start_date = $month_year . '-01 00:00:00';
        $end_date   = date('Y-m-t 23:59:59', strtotime($start_date));

        $employees = self::get_employees('active');
        global $wpdb;
        $payroll_table = $wpdb->prefix . 'fmb_employee_payroll';

        foreach ($employees as $emp) {
            $perf = self::get_employee_performance($emp->id, $start_date, $end_date);

            // Check if record exists
            $existing = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$payroll_table} WHERE employee_id = %d AND month_year = %s",
                $emp->id, $month_year
            ));

            $base_salary       = floatval($emp->base_salary);
            $earned_commission = floatval($perf['earned_commission']);
            $bonus             = $existing ? floatval($existing->bonus) : 0.00;
            $deductions        = $existing ? floatval($existing->deductions) : 0.00;
            $net_payable       = ($base_salary + $earned_commission + $bonus) - $deductions;

            $payload = [
                'employee_id'            => $emp->id,
                'month_year'             => $month_year,
                'base_salary'            => $base_salary,
                'total_confirmed_orders' => $perf['confirmed_orders'],
                'total_delivered_orders' => $perf['delivered_orders'],
                'total_cancelled_orders' => $perf['cancelled_orders'],
                'total_sales_amount'     => $perf['total_sales'],
                'earned_commission'      => $earned_commission,
                'bonus'                  => $bonus,
                'deductions'             => $deductions,
                'net_payable'            => max(0, $net_payable),
            ];

            $format = ['%d', '%s', '%f', '%d', '%d', '%d', '%f', '%f', '%f', '%f', '%f'];

            if ($existing) {
                // If not yet marked paid, update counts and net payable
                if ($existing->payment_status !== 'paid') {
                    $wpdb->update($payroll_table, $payload, ['id' => $existing->id], $format, ['%d']);
                }
            } else {
                $payload['payment_status'] = 'unpaid';
                $format[] = '%s';
                $wpdb->insert($payroll_table, $payload, $format);
            }
        }
    }

    /**
     * Get Payroll Records for Month
     */
    public static function get_payroll_records($month_year = null) {
        if (!$month_year) {
            $month_year = current_time('Y-m');
        }

        global $wpdb;
        $payroll_table = $wpdb->prefix . 'fmb_employee_payroll';
        $emp_table     = $wpdb->prefix . 'fmb_employees';

        if ($wpdb->get_var("SHOW TABLES LIKE '{$payroll_table}'") !== $payroll_table) {
            return [];
        }

        $sql = $wpdb->prepare("
            SELECT p.*, e.name as emp_name, e.phone as emp_phone, e.email as emp_email, e.designation as emp_designation, e.commission_type, e.commission_rate
            FROM {$payroll_table} p
            JOIN {$emp_table} e ON p.employee_id = e.id
            WHERE p.month_year = %s
            ORDER BY p.id DESC
        ", $month_year);

        return $wpdb->get_results($sql);
    }
}

// Instantiate backend manager
FMB_Employee_Manager::get_instance();
