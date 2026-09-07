<?php
/**
 * FMB Commerce Engine Initializer
 * Custom e-commerce suite for fmb-ecom-store theme.
 */

if (!defined('ABSPATH')) {
    exit;
}

class FMB_Engine_Init {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->define_constants();
        $this->create_db_tables();
        $this->register_custom_order_statuses();
        $this->load_modules();
        $this->enqueue_admin_assets();
    }

    private function define_constants() {
        if (!defined('FMB_ENGINE_PATH')) {
            define('FMB_ENGINE_PATH', get_template_directory() . '/inc/fmb-engine/');
        }
        if (!defined('FMB_ENGINE_URL')) {
            define('FMB_ENGINE_URL', get_template_directory_uri() . '/inc/fmb-engine/');
        }
        if (!defined('FMB_ADMIN_ASSETS_URL')) {
            define('FMB_ADMIN_ASSETS_URL', FMB_ENGINE_URL . 'assets/');
        }
    }

    /**
     * Create/Sync DB tables for FMB Commerce Engine
     */
    private function create_db_tables() {
        add_action('after_switch_theme', array($this, 'run_db_setup'));
        add_action('admin_init', array($this, 'check_db_tables'));
    }

    public function check_db_tables() {
        $this->run_db_setup();
    }

    public function run_db_setup() {
        global $wpdb;
        if (file_exists(ABSPATH . 'wp-admin/includes/upgrade.php')) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }
        $charset_collate = $wpdb->get_charset_collate();

        // 1. Customers Data Table (Blacklist / History) - create both aliases for total compatibility
        $ads_customers_table = $wpdb->prefix . 'ads_customers_data';
        $sql_ads_customers = "CREATE TABLE IF NOT EXISTS {$ads_customers_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            data_type VARCHAR(255) NOT NULL,
            data_value VARCHAR(255) NOT NULL,
            data_courier_history LONGTEXT NULL,
            data_courier_history_last_update_time DATETIME NULL,
            data_access VARCHAR(255) NOT NULL DEFAULT 'allowed',
            order_id BIGINT NULL,
            block_note TEXT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) {$charset_collate};";
        dbDelta($sql_ads_customers);

        $customers_table = $wpdb->prefix . 'fmb_customers_data';
        $sql_customers = "CREATE TABLE IF NOT EXISTS {$customers_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            data_type VARCHAR(255) NOT NULL,
            data_value VARCHAR(255) NOT NULL,
            data_courier_history LONGTEXT NULL,
            data_courier_history_last_update_time DATETIME NULL,
            data_access VARCHAR(255) NOT NULL DEFAULT 'allowed',
            order_id BIGINT NULL,
            block_note TEXT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) {$charset_collate};";
        dbDelta($sql_customers);

        // 2. Incomplete Orders Tracker Table
        $orders_table = $wpdb->prefix . 'fmb_incomplete_orders_tracker';
        $sql_orders = "CREATE TABLE IF NOT EXISTS {$orders_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            order_id BIGINT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            recovered_at DATETIME NULL,
            status_from VARCHAR(50) DEFAULT 'fmb-incomplete',
            status_to VARCHAR(50) DEFAULT 'fmb-incomplete',
            order_total DECIMAL(10,2) DEFAULT 0.00,
            user_id BIGINT NULL,
            email VARCHAR(255) NULL,
            billing_first_name VARCHAR(255) NULL,
            billing_last_name VARCHAR(255) NULL,
            billing_phone VARCHAR(50) NULL,
            billing_address_1 TEXT NULL,
            billing_state VARCHAR(100) NULL,
            cart_data LONGTEXT NULL,
            note TEXT NULL,
            PRIMARY KEY (id)
        ) {$charset_collate};";
        dbDelta($sql_orders);

        // 3. Dedicated Courier Consignments Table
        if (class_exists('FMB_Courier_DB')) {
            FMB_Courier_DB::create_table();
        }
    }

    /**
     * Register Custom WooCommerce Order Statuses
     */
    private function register_custom_order_statuses() {
        add_action('init', function() {
            register_post_status('wc-fmb-incomplete', array(
                'label'                     => _x('Incomplete', 'Order status', 'fmb-store'),
                'public'                    => true,
                'exclude_from_search'       => false,
                'show_in_admin_all_list'    => true,
                'show_in_admin_status_list' => true,
                'label_count'               => _n_noop('Incomplete <span class="count">(%s)</span>', 'Incomplete <span class="count">(%s)</span>', 'fmb-store')
            ));

            register_post_status('wc-partial-paid', array(
                'label'                     => _x('Partial Paid', 'Order status', 'fmb-store'),
                'public'                    => true,
                'exclude_from_search'       => false,
                'show_in_admin_all_list'    => true,
                'show_in_admin_status_list' => true,
                'label_count'               => _n_noop('Partial Paid <span class="count">(%s)</span>', 'Partial Paid <span class="count">(%s)</span>', 'fmb-store')
            ));

            register_post_status('wc-fake-order', array(
                'label'                     => _x('Fake Order', 'Order status', 'fmb-store'),
                'public'                    => true,
                'exclude_from_search'       => false,
                'show_in_admin_all_list'    => true,
                'show_in_admin_status_list' => true,
                'label_count'               => _n_noop('Fake Order <span class="count">(%s)</span>', 'Fake Order <span class="count">(%s)</span>', 'fmb-store')
            ));

            register_post_status('wc-follow-up', array(
                'label'                     => _x('Follow Up', 'Order status', 'fmb-store'),
                'public'                    => true,
                'exclude_from_search'       => false,
                'show_in_admin_all_list'    => true,
                'show_in_admin_status_list' => true,
                'label_count'               => _n_noop('Follow Up <span class="count">(%s)</span>', 'Follow Up <span class="count">(%s)</span>', 'fmb-store')
            ));
        });

        add_filter('wc_order_statuses', function($statuses) {
            $new_statuses = array();
            foreach ($statuses as $key => $status) {
                $new_statuses[$key] = $status;
                if ('wc-processing' === $key) {
                    $new_statuses['wc-fmb-incomplete'] = _x('Incomplete Order', 'Order status', 'fmb-store');
                    $new_statuses['wc-partial-paid']   = _x('Partial Paid', 'Order status', 'fmb-store');
                    $new_statuses['wc-follow-up']      = _x('Follow Up', 'Order status', 'fmb-store');
                    $new_statuses['wc-fake-order']      = _x('Fake Order', 'Order status', 'fmb-store');
                }
            }
            return $new_statuses;
        });
    }

    /**
     * Load Sub-Modules
     */
    private function load_modules() {
        require_once FMB_ENGINE_PATH . 'traits/core.php';
        require_once FMB_ENGINE_PATH . 'traits/order-check-trait.php';
        require_once FMB_ENGINE_PATH . 'traits/courier.php';
        require_once FMB_ENGINE_PATH . 'core/base.php';
        require_once FMB_ENGINE_PATH . 'incomplete-orders-tracker.php';

        require_once FMB_ENGINE_PATH . 'class-checkout-restrictions.php';
        
        // Dedicated Courier Database
        if (file_exists(FMB_ENGINE_PATH . 'courier/class-fmb-courier-db.php')) {
            require_once FMB_ENGINE_PATH . 'courier/class-fmb-courier-db.php';
        }

        // Courier Engine Modules
        if (file_exists(FMB_ENGINE_PATH . 'courier/class-ofls-bd-courier-engine.php')) {
            require_once FMB_ENGINE_PATH . 'courier/class-ofls-bd-courier-engine.php';
        }
        if (file_exists(FMB_ENGINE_PATH . 'courier/courier.php')) {
            require_once FMB_ENGINE_PATH . 'courier/courier.php';
        }
        if (file_exists(FMB_ENGINE_PATH . 'courier/courier-remarks-extractor.php')) {
            require_once FMB_ENGINE_PATH . 'courier/courier-remarks-extractor.php';
        }
        if (file_exists(FMB_ENGINE_PATH . 'courier/courier-webhooks.php')) {
            require_once FMB_ENGINE_PATH . 'courier/courier-webhooks.php';
        }
        if (file_exists(FMB_ENGINE_PATH . 'class-bd-courier-engine.php')) {
            require_once FMB_ENGINE_PATH . 'class-bd-courier-engine.php';
        }

        if (file_exists(FMB_ENGINE_PATH . 'custom-order-statuses.php')) {
            require_once FMB_ENGINE_PATH . 'custom-order-statuses.php';
            new \fmb_engine\Custom_Order_Statuses();
        }

        require_once FMB_ENGINE_PATH . 'class-partial-payment.php';
        if (file_exists(FMB_ENGINE_PATH . 'sms/class-smart-sms.php')) {
            require_once FMB_ENGINE_PATH . 'sms/class-smart-sms.php';
        }
        if (file_exists(FMB_ENGINE_PATH . 'class-smart-sms.php')) {
            require_once FMB_ENGINE_PATH . 'class-smart-sms.php';
        }
        require_once FMB_ENGINE_PATH . 'class-capi-tracker.php';
        require_once FMB_ENGINE_PATH . 'class-incomplete-orders.php';
        require_once FMB_ENGINE_PATH . 'class-admin-settings.php';

        // Load Facebook CAPI & Pixel Modules
        if (file_exists(FMB_ENGINE_PATH . 'facebook/functions-common.php')) {
            require_once FMB_ENGINE_PATH . 'facebook/functions-common.php';
        }
        if (file_exists(FMB_ENGINE_PATH . 'facebook/functions-woo.php')) {
            require_once FMB_ENGINE_PATH . 'facebook/functions-woo.php';
        }
        if (file_exists(FMB_ENGINE_PATH . 'facebook/class-settings.php')) {
            require_once FMB_ENGINE_PATH . 'facebook/class-settings.php';
        }
        if (file_exists(FMB_ENGINE_PATH . 'facebook/events/class-facebook-event-model.php')) {
            require_once FMB_ENGINE_PATH . 'facebook/events/class-facebook-event-model.php';
        }
        if (file_exists(FMB_ENGINE_PATH . 'facebook/class-events-manager.php')) {
            require_once FMB_ENGINE_PATH . 'facebook/class-events-manager.php';
        }
        if (file_exists(FMB_ENGINE_PATH . 'facebook/class-custom-events-manager.php')) {
            require_once FMB_ENGINE_PATH . 'facebook/class-custom-events-manager.php';
        }
        if (file_exists(FMB_ENGINE_PATH . 'facebook/class-custom-order-status-event-manager.php')) {
            require_once FMB_ENGINE_PATH . 'facebook/class-custom-order-status-event-manager.php';
        }
        if (file_exists(FMB_ENGINE_PATH . 'facebook/class-purchase-event-manager.php')) {
            require_once FMB_ENGINE_PATH . 'facebook/class-purchase-event-manager.php';
        }
        if (file_exists(FMB_ENGINE_PATH . 'facebook/modules/facebook/function-helpers.php')) {
            require_once FMB_ENGINE_PATH . 'facebook/modules/facebook/function-helpers.php';
        }
        if (file_exists(FMB_ENGINE_PATH . 'facebook/modules/facebook/FacebookCapiEventHelper.php')) {
            require_once FMB_ENGINE_PATH . 'facebook/modules/facebook/FacebookCapiEventHelper.php';
        }
        if (file_exists(FMB_ENGINE_PATH . 'facebook/modules/facebook/facebook.php')) {
            require_once FMB_ENGINE_PATH . 'facebook/modules/facebook/facebook.php';
        }
        if (file_exists(FMB_ENGINE_PATH . 'facebook/class-facebook-runtime.php')) {
            require_once FMB_ENGINE_PATH . 'facebook/class-facebook-runtime.php';
            if (function_exists('fmb_engine\Facebook\FacebookRuntime')) {
                \fmb_engine\Facebook\FacebookRuntime();
            }
        }
        if (file_exists(FMB_ENGINE_PATH . 'facebook/modules/facebook/facebook-server.php')) {
            require_once FMB_ENGINE_PATH . 'facebook/modules/facebook/facebook-server.php';
        }

        // Load Admin UI Stack
        if (is_admin()) {
            require_once FMB_ENGINE_PATH . 'admin/promo.php';
            require_once FMB_ENGINE_PATH . 'admin/general-settings.php';
            require_once FMB_ENGINE_PATH . 'admin/fb-settings.php';
            require_once FMB_ENGINE_PATH . 'admin/incomplete-orders-dashboard.php';
            require_once FMB_ENGINE_PATH . 'admin/customer-access.php';
            require_once FMB_ENGINE_PATH . 'admin/courier-details-meta-box.php';
            require_once FMB_ENGINE_PATH . 'admin/order-list-columns.php';
            if (file_exists(FMB_ENGINE_PATH . 'admin/incomplete/incomplete.php')) {
                require_once FMB_ENGINE_PATH . 'admin/incomplete/incomplete.php';
            }
            require_once FMB_ENGINE_PATH . 'admin/register-menus.php';

            $dash = new \fmb_engine\Admin\Incomplete_Orders_Dashboard();
            $inc = new \fmb_engine\Admin\Incomplete\Incomplete();
            new \fmb_engine\Admin\Register_Menus($inc, $dash);
            new \fmb_engine\Admin\Customer_Access();
            new \fmb_engine\Admin\Courier_Details_Meta_Box();
            new \fmb_engine\Admin\Order_List_Columns();
        }

        // Instantiate modules
        FMB_Checkout_Restrictions::get_instance();
        FMB_BD_Courier_Engine::get_instance();
        FMB_Partial_Payment::get_instance();
        if (class_exists('\fmb_engine\SMS\Smart_SMS')) {
            \fmb_engine\SMS\Smart_SMS::instance();
        }
        if (class_exists('FMB_Smart_SMS')) {
            FMB_Smart_SMS::get_instance();
        }
        FMB_CAPI_Tracker::get_instance();
        FMB_Incomplete_Orders::get_instance();
        FMB_Admin_Settings::get_instance();
    }

    private function enqueue_admin_assets() {
        add_action('admin_enqueue_scripts', function($hook) {
            $is_fmb = (isset($_GET['page']) && (strpos($_GET['page'], 'fmb-engine') !== false || strpos($_GET['page'], 'fmb-store') !== false)) 
                      || strpos($hook, 'fmb-engine') !== false 
                      || strpos($hook, 'fmb-store') !== false;
            if ($is_fmb) {
                wp_enqueue_style('fmb-engine-admin-css', FMB_ENGINE_URL . 'assets/css/admin.css', array(), time());
                wp_enqueue_style('fmb-engine-setting-css', FMB_ENGINE_URL . 'assets/css/setting.css', array(), time());
            }
        });
    }
}

// Initialize on setup
FMB_Engine_Init::get_instance();
