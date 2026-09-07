<?php
/**
 * FMB Door Admin - Custom Admin Login URL Protection
 * 
 * Changes the WordPress admin login URL from /wp-login.php to /door-admin/
 * Blocks unauthorized access to /wp-login.php and /wp-admin by displaying a 404 Not Found.
 *
 * @package FMB_Ecom_Store
 */

if (!defined('ABSPATH')) {
    exit;
}

class FMB_Door_Admin {

    private static $instance = null;
    private $default_slug = 'door-admin';

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Check if emergency bypass is enabled
        if (defined('FMB_DISABLE_DOOR_ADMIN') && FMB_DISABLE_DOOR_ADMIN) {
            return;
        }

        // Intercept incoming requests
        add_action('plugins_loaded', array($this, 'init_door_admin'), 1);
        add_action('wp_loaded', array($this, 'handle_door_admin_request'));
        add_action('init', array($this, 'block_direct_wp_admin'), 1);
        add_action('login_init', array($this, 'block_direct_wp_login'), 1);

        // URL Filters
        add_filter('login_url', array($this, 'filter_login_url'), 10, 3);
        add_filter('logout_url', array($this, 'filter_logout_url'), 10, 2);
        add_filter('lostpassword_url', array($this, 'filter_lostpassword_url'), 10, 2);
        add_filter('register_url', array($this, 'filter_register_url'), 10, 1);
        add_filter('site_url', array($this, 'filter_site_url'), 10, 4);
        add_filter('network_site_url', array($this, 'filter_site_url'), 10, 4);

        // Admin Notices & Settings
        add_action('admin_notices', array($this, 'render_admin_notice'));
        add_action('admin_init', array($this, 'register_settings'));
    }

    public static function is_enabled() {
        if (defined('FMB_DISABLE_DOOR_ADMIN') && FMB_DISABLE_DOOR_ADMIN) {
            return false;
        }
        return get_option('fmb_door_admin_enabled', 'no') === 'yes';
    }

    public function get_slug() {
        $slug = get_option('fmb_door_admin_slug', $this->default_slug);
        $slug = sanitize_title_with_dashes($slug);
        return !empty($slug) ? $slug : $this->default_slug;
    }

    public function get_login_url($action = '') {
        $slug = $this->get_slug();
        $url = home_url('/' . $slug . '/');
        if (!empty($action)) {
            $url = add_query_arg('action', $action, $url);
        }
        return $url;
    }

    /**
     * Get the relative request path regardless of root or subdirectory installation
     */
    public function get_current_relative_path() {
        if (!isset($_SERVER['REQUEST_URI'])) {
            return '';
        }

        $raw_uri   = $_SERVER['REQUEST_URI'];
        $path_only = (string) wp_parse_url($raw_uri, PHP_URL_PATH);
        $home_path = (string) wp_parse_url(home_url(), PHP_URL_PATH);

        $path_only = untrailingslashit($path_only);
        $home_path = untrailingslashit($home_path);

        if (!empty($home_path) && strpos($path_only, $home_path) === 0) {
            $path_only = substr($path_only, strlen($home_path));
        }

        return trim($path_only, '/');
    }

    public function init_door_admin() {
        // Ensure rewrite rules or query vars can detect door-admin
        $current_path = $this->get_current_relative_path();
        $slug = $this->get_slug();

        if ($current_path === $slug) {
            $GLOBALS['fmb_is_door_admin_hit'] = true;
        }
    }

    /**
     * Process /door-admin/ requests
     */
    public function handle_door_admin_request() {
        if (!self::is_enabled()) {
            return;
        }

        $slug = $this->get_slug();
        $current_path = $this->get_current_relative_path();

        if ($current_path === $slug) {
            $uri = $_SERVER['REQUEST_URI'];
            $path_part = (string) wp_parse_url($uri, PHP_URL_PATH);

            // Ensure trailing slash on GET requests
            if (substr($path_part, -1) !== '/' && $_SERVER['REQUEST_METHOD'] === 'GET') {
                $query = (string) wp_parse_url($uri, PHP_URL_QUERY);
                $target = home_url('/' . $slug . '/');
                if (!empty($query)) {
                    $target .= '?' . $query;
                }
                wp_safe_redirect($target, 301);
                exit;
            }

            // If user is already logged in
            if (is_user_logged_in()) {
                $action = isset($_REQUEST['action']) ? sanitize_key($_REQUEST['action']) : '';
                // Allow logout flow to proceed
                if ($action === 'logout') {
                    $this->render_login_page();
                    exit;
                }
                // Otherwise redirect directly to admin dashboard
                wp_safe_redirect(admin_url());
                exit;
            }

            // Render WordPress login page
            $this->render_login_page();
            exit;
        }
    }

    /**
     * Render the core wp-login.php page safely
     */
    private function render_login_page() {
        $GLOBALS['fmb_door_admin_allowed'] = true;

        global $error, $interim_login, $action, $user_login, $wp_error;

        nocache_headers();

        require_once ABSPATH . 'wp-login.php';
        exit;
    }

    /**
     * Block direct access to /wp-login.php
     */
    public function block_direct_wp_login() {
        if (!self::is_enabled()) {
            return;
        }

        // Allowed if invoked through door-admin
        if (!empty($GLOBALS['fmb_door_admin_allowed'])) {
            return;
        }

        // Block direct access and render 404
        $this->block_and_render_404();
    }

    /**
     * Block unauthenticated direct access to /wp-admin/
     */
    public function block_direct_wp_admin() {
        if (!self::is_enabled()) {
            return;
        }

        if (!is_admin()) {
            return;
        }

        // Allow AJAX requests (both frontend and backend)
        if (wp_doing_ajax()) {
            return;
        }

        // Allow background CRON
        if ((defined('DOING_CRON') && DOING_CRON) || wp_doing_cron()) {
            return;
        }

        // Allow async uploads if session exists
        if (isset($_SERVER['SCRIPT_NAME']) && strpos($_SERVER['SCRIPT_NAME'], 'async-upload.php') !== false) {
            return;
        }

        // If user is logged in, grant normal access to admin
        if (is_user_logged_in()) {
            return;
        }

        // Unauthenticated visitor trying to access /wp-admin!
        // Block and render 404
        $this->block_and_render_404();
    }

    /**
     * Return 404 Not Found without leaking the custom login URL
     */
    public function block_and_render_404() {
        global $wp_query;

        status_header(404);
        nocache_headers();

        if (!empty($wp_query) && is_object($wp_query)) {
            $wp_query->set_404();
        }

        if (function_exists('wc_load_cart') && function_exists('WC') && is_null(WC()->cart)) {
            wc_load_cart();
        }

        $template_404 = get_404_template();
        if ($template_404 && file_exists($template_404)) {
            include $template_404;
            exit;
        }

        // Fallback: Safe redirect to home page
        wp_safe_redirect(home_url('/'), 302);
        exit;
    }

    /**
     * Filter WordPress login URL to point to /door-admin/
     */
    public function filter_login_url($login_url, $redirect, $force_reauth) {
        if (!self::is_enabled()) {
            return $login_url;
        }

        $url = home_url('/' . $this->get_slug() . '/');
        if (!empty($redirect)) {
            $url = add_query_arg('redirect_to', urlencode($redirect), $url);
        }
        if ($force_reauth) {
            $url = add_query_arg('reauth', '1', $url);
        }
        return $url;
    }

    /**
     * Filter logout URL
     */
    public function filter_logout_url($logout_url, $redirect) {
        if (!self::is_enabled()) {
            return $logout_url;
        }

        $url = add_query_arg('action', 'logout', home_url('/' . $this->get_slug() . '/'));
        if (!empty($redirect)) {
            $url = add_query_arg('redirect_to', urlencode($redirect), $url);
        }

        return wp_nonce_url($url, 'log-out');
    }

    /**
     * Filter lost password URL
     */
    public function filter_lostpassword_url($lostpassword_url, $redirect) {
        if (!self::is_enabled()) {
            return $lostpassword_url;
        }

        $url = add_query_arg('action', 'lostpassword', home_url('/' . $this->get_slug() . '/'));
        if (!empty($redirect)) {
            $url = add_query_arg('redirect_to', urlencode($redirect), $url);
        }
        return $url;
    }

    /**
     * Filter registration URL
     */
    public function filter_register_url($register_url) {
        if (!self::is_enabled()) {
            return $register_url;
        }

        return add_query_arg('action', 'register', home_url('/' . $this->get_slug() . '/'));
    }

    /**
     * Rewrite site_url when targeting wp-login.php
     */
    public function filter_site_url($url, $path, $scheme, $blog_id) {
        if (!self::is_enabled()) {
            return $url;
        }

        if (is_string($path) && strpos($path, 'wp-login.php') !== false) {
            $slug = $this->get_slug();
            $query = (string) wp_parse_url($path, PHP_URL_QUERY);
            $new_url = home_url('/' . $slug . '/');
            if (!empty($query)) {
                $new_url .= '?' . $query;
            }
            return $new_url;
        }

        return $url;
    }

    /**
     * Register Settings in WordPress Admin
     */
    public function register_settings() {
        register_setting('general', 'fmb_door_admin_slug', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_title_with_dashes',
            'default'           => 'door-admin',
        ));

        register_setting('general', 'fmb_door_admin_enabled', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => 'no',
        ));

        add_settings_section(
            'fmb_door_admin_section',
            '<span class="dashicons dashicons-shield-alt" style="color:#0ea5e9;vertical-align:middle;margin-right:5px;"></span> FMB Door Admin Security',
            array($this, 'render_settings_section_info'),
            'general'
        );

        add_settings_field(
            'fmb_door_admin_slug',
            'Admin Login URL (Secret Slug)',
            array($this, 'render_slug_field'),
            'general',
            'fmb_door_admin_section'
        );
    }

    public function render_settings_section_info() {
        $login_url = $this->get_login_url();
        echo '<p style="max-width:700px;line-height:1.6;color:#475569;">';
        echo 'ডিফল্ট <code>wp-login.php</code> এবং <code>/wp-admin</code> হাইড করা রয়েছে। শুধুমাত্র এই সিক্রেট URL ব্যবহার করে ড্যাশবোর্ডে লগইন করা যাবে: ';
        echo '<br><strong style="font-size:14px;color:#0f172a;display:inline-block;margin-top:5px;background:#f1f5f9;padding:6px 12px;border-radius:6px;border:1px solid #cbd5e1;">' . esc_url($login_url) . '</strong>';
        echo '</p>';
    }

    public function render_slug_field() {
        $slug = $this->get_slug();
        echo home_url('/') . ' <input type="text" name="fmb_door_admin_slug" value="' . esc_attr($slug) . '" class="regular-text" style="width:200px;font-weight:600;color:#0284c7;" /> /';
        echo '<p class="description">ডিফল্ট হলো <code>door-admin</code>। এটি পরিবর্তন করলে নতুন লিঙ্কে লগইন করতে হবে।</p>';
    }

    /**
     * Show reminder notice in admin dashboard
     */
    public function render_admin_notice() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $screen = get_current_screen();
        if ($screen && $screen->id === 'dashboard') {
            $login_url = $this->get_login_url();
            ?>
            <div class="notice notice-info is-dismissible" style="border-left-color:#0284c7;background:#f0f9ff;">
                <p style="font-size:13px;color:#0369a1;margin:6px 0;">
                    🛡️ <strong>FMB Door Admin সিকিউরিটি সক্রিয়:</strong> আপনার অ্যাডমিন লগইন পেজটি হাইড করা আছে। ভবিষ্যতে লগইন করতে হলে এই লিঙ্কটি মনে রাখুন: 
                    <a href="<?php echo esc_url($login_url); ?>" target="_blank" style="font-weight:bold;text-decoration:underline;color:#0284c7;">
                        <?php echo esc_url($login_url); ?>
                    </a>
                </p>
            </div>
            <?php
        }
    }
}

// Auto-initialize Door Admin
function fmb_door_admin_init() {
    return FMB_Door_Admin::get_instance();
}
fmb_door_admin_init();
