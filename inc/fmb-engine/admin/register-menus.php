<?php
namespace fmb_engine\Admin;

if (!defined('ABSPATH')) {
    exit;
}

class Register_Menus {

    private $incomplete_order;
    private $incomplete_orders_dashboard;

    public function __construct($incomplete_order = null, $incomplete_orders_dashboard = null) {
        $this->incomplete_order = $incomplete_order;
        $this->incomplete_orders_dashboard = $incomplete_orders_dashboard;

        add_action('admin_menu', array($this, 'register_admin_menu'));
        add_filter('submenu_file', array($this, 'highlight_order_optimizer'), 10, 2);
    }

    public function highlight_order_optimizer($submenu_file, $parent_file) {
        $current_page = isset($_GET['page']) ? $_GET['page'] : '';
        $optimizer_pages = array('fmb-engine-courier', 'fmb-engine-courier-setup', 'fmb-engine-fraud-customer-block', 'fmb-engine-partial-payment');
        if (in_array($current_page, $optimizer_pages)) {
            return 'fmb-engine-optimizer';
        }
        return $submenu_file;
    }

    public function register_admin_menu() {
        $general_settings = new General_Settings();
        $fb_settings = new Fb_Settings();

        // 1. Top-Level Main Menu: FMB Engine
        add_menu_page(
            'FMB Engine',         // Page Title
            'FMB Engine',         // Menu Title
            'manage_woocommerce', // Capability
            'fmb-engine',         // Menu Slug
            array($this, 'render_dashboard'),
            'dashicons-shield',   // Icon
            25                    // Position
        );

        // 2. Submenu 1: Dashboard
        add_submenu_page(
            'fmb-engine',
            'Dashboard',
            'Dashboard',
            'manage_woocommerce',
            'fmb-engine',
            array($this, 'render_dashboard')
        );

        // 3. Submenu 2: Incomplete Orders
        add_submenu_page(
            'fmb-engine',
            'Incomplete Orders',
            'Incomplete Orders',
            'manage_woocommerce',
            'fmb-engine-incomplete-orders',
            array($this, 'render_incomplete_orders')
        );

        // 3.1 Submenu: Courier Dashboard
        add_submenu_page(
            'fmb-engine',
            'Courier Dashboard',
            'Courier Dashboard',
            'manage_woocommerce',
            'fmb-courier-dashboard',
            'fmb_admin_courier_dashboard_page'
        );

        // 4. Submenu 3: Order Optimizer
        add_submenu_page(
            'fmb-engine',
            'Order Optimizer',
            'Order Optimizer',
            'manage_options',
            'fmb-engine-optimizer',
            array($general_settings, 'render_optimizer_page')
        );

        // Hidden tabs routing to Optimizer
        add_submenu_page(null, 'Fraud Checker', 'Fraud Checker', 'manage_options', 'fmb-engine-courier', array($general_settings, 'render_optimizer_page'));
        add_submenu_page(null, 'Courier Setup', 'Courier Setup', 'manage_options', 'fmb-engine-courier-setup', array($general_settings, 'render_optimizer_page'));
        add_submenu_page(null, 'Fraud Block', 'Fraud Block', 'manage_options', 'fmb-engine-fraud-customer-block', array($general_settings, 'render_optimizer_page'));
        add_submenu_page(null, 'Partial Payment', 'Partial Payment', 'manage_options', 'fmb-engine-partial-payment', array($general_settings, 'render_optimizer_page'));

        // 5. Submenu 4: Pixel & CAPI Tracking
        add_submenu_page(
            'fmb-engine',
            'Pixel & Tracking',
            'Pixel & CAPI Tracking',
            'manage_options',
            'fmb-engine-fb-settings',
            array($fb_settings, 'render_settings')
        );
    }

    public function render_dashboard() {
        if ($this->incomplete_orders_dashboard && method_exists($this->incomplete_orders_dashboard, 'render_dashboard')) {
            $this->incomplete_orders_dashboard->render_dashboard();
        } else {
            \fmb_engine\Admin\Incomplete_Orders_Dashboard::render_dashboard();
        }
    }

    public function render_incomplete_orders() {
        if ($this->incomplete_order && method_exists($this->incomplete_order, 'render_settings')) {
            $this->incomplete_order->render_settings();
        } else {
            $incomplete = new \fmb_engine\Admin\Incomplete();
            $incomplete->render_settings();
        }
    }
}
