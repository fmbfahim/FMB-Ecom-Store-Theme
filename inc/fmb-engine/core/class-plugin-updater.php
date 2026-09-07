<?php
namespace fmb_engine\Core;

/**
 * Self-Hosted Plugin Updater Class
 */
class Plugin_Updater {

    private $slug;
    private $version;
    private $api_url;
    private $license_key;

    public function __construct($slug, $version, $api_url, $license_key) {
        $this->slug = $slug;
        $this->version = $version;
        $this->api_url = $api_url;
        $this->license_key = $license_key;

        // Hook into the plugin update check
        add_filter('pre_set_site_transient_update_plugins', [$this, 'check_for_updates']);
        add_filter('site_transient_update_plugins', [$this, 'check_for_updates']);

        // Hook into the plugin details API (for the "View version details" popup)
        add_filter('plugins_api', [$this, 'plugins_api_handler'], 10, 3);
        
        // Clean cache when update is complete
        add_action('upgrader_process_complete', [$this, 'clean_update_cache'], 10, 2);
    }

    /**
     * Check for updates against the remote API
     */
    public function check_for_updates($transient) {
        if (empty($transient) || !is_object($transient)) {
            return $transient;
        }

        $plugin_file = $this->slug . '/' . $this->slug . '-real-order-tracking.php';
        $remote_data = $this->get_remote_data();

        if ($remote_data && isset($remote_data['new_version']) && version_compare($this->version, $remote_data['new_version'], '<')) {
            $obj = new \stdClass();
            $obj->slug = $this->slug;
            $obj->new_version = $remote_data['new_version'];
            $obj->url = 'https://orderflowbd.com'; // Your plugin homepage
            $obj->package = $remote_data['package']; // The ZIP download link
            $obj->tested = isset($remote_data['tested']) ? $remote_data['tested'] : '';
            $obj->requires_php = isset($remote_data['requires_php']) ? $remote_data['requires_php'] : '';
            $obj->plugin = $plugin_file;

            $transient->response[$plugin_file] = $obj;
        } else {
            // Ensure no update notice is shown if the plugin is already up to date
            if (isset($transient->response[$plugin_file])) {
                unset($transient->response[$plugin_file]);
            }
            
            // Explicitly mark as no update needed to prevent WP.org overrides
            if (isset($transient->no_update)) {
                $obj = new \stdClass();
                $obj->id = $plugin_file;
                $obj->slug = $this->slug;
                $obj->plugin = $plugin_file;
                $obj->new_version = $this->version;
                $obj->url = '';
                $obj->package = '';
                $transient->no_update[$plugin_file] = $obj;
            }
        }

        return $transient;
    }

    /**
     * Provide information for the "View version details" popup
     */
    public function plugins_api_handler($res, $action, $args) {
        if ($action !== 'plugin_information') {
            return $res;
        }

        if ($args->slug !== $this->slug) {
            return $res;
        }

        $remote_data = $this->get_remote_data();

        if ($remote_data) {
            $res = new \stdClass();
            $res->name = 'FMB Engine';
            $res->slug = $this->slug;
            $res->version = $remote_data['new_version'];
            $res->tested = isset($remote_data['tested']) ? $remote_data['tested'] : '';
            $res->requires_php = isset($remote_data['requires_php']) ? $remote_data['requires_php'] : '';
            $res->author = 'FMB';
            $res->homepage = 'https://orderflowbd.com';
            $res->download_link = $remote_data['package'];
            $res->trunk = $remote_data['package'];
            $res->last_updated = date('Y-m-d');
            $res->sections = [
                'description' => 'Streamline your WooCommerce orders – capture abandoned checkouts, block fake or duplicate orders, validate customer data, and send accurate server-side events to Facebook Pixel for better ad performance.',
                'changelog' => isset($remote_data['sections']['changelog']) ? $remote_data['sections']['changelog'] : 'No changelog available.'
            ];
            
            return $res;
        }

        return $res;
    }

    /**
     * Get remote data from the API
     */
    private function get_remote_data() {
        $transient_name = 'orderflow_update_data_' . $this->slug;
        
        // Bypass cache if forcing an update check
        if (isset($_GET['force-check']) && $_GET['force-check'] == '1') {
            delete_transient($transient_name);
            $data = false;
        } else {
            $data = get_transient($transient_name);
        }

        if ($data !== false) {
            return $data;
        }
    }

    /**
     * Clean cache after update
     */
    public function clean_update_cache($upgrader_object, $options) {
        if ($options['action'] == 'update' && $options['type'] == 'plugin') {
            if (isset($options['plugins']) && is_array($options['plugins'])) {
                foreach ($options['plugins'] as $plugin) {
                    if (strpos($plugin, $this->slug) !== false) {
                        delete_transient('orderflow_update_data_' . $this->slug);
                    }
                }
            }
        }
    }
}
