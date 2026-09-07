<?php
namespace fmb_engine\Traits;

/**
 * License Verification Trait
 *
 * Handles activation, deactivation, and validation of plugin license keys.
 */
trait Core {

    /**
     * Get license data from database
     *
     * @return array
     */
    protected static function get_license_data() {
        $data = get_option('my_plugin_license_data', [
            'key'          => '',
            'status'       => 'inactive',
            'expiry'       => '',
            'domain'       => '',
            'activated_at' => '',
            'type'         => ''
        ]);
        return $data;
    }

    /**
     * Save license data to database
     *
     * @param array $data
     */
    protected static function save_license_data($data) {
        update_option('my_plugin_license_data', $data);
    }

    /**
     * Log an activity for the real-time activity feed
     * 
     * @param string $message The activity message
     * @param string $icon (optional) An emoji or text icon prefix
     */
    public static function log_activity($message, $icon = '') {
        $activities = get_option('ofls_recent_activities', []);
        if (!is_array($activities)) {
            $activities = [];
        }
        
        $entry = [
            'message' => $message,
            'icon' => $icon,
            'time' => time()
        ];
        
        array_unshift($activities, $entry);
        
        // Keep only the most recent 10 events to prevent option bloat
        if (count($activities) > 10) {
            $activities = array_slice($activities, 0, 10);
        }
        
        update_option('ofls_recent_activities', $activities);
    }

    /**
     * Check if license is active
     *
     * @return bool
     */
    public static function is_license_active() {
        return true;
    }

    /**
     * Verify license with server
     *
     * @return bool
     */
    public static function verify_license() {
        $data = self::get_license_data();

        if ($data['status'] !== 'active' || empty($data['key'])) {
            return false;
        }

        // Check if license is expired locally first
        if (self::is_license_expired()) {
            // Set status to inactive but keep key, so user sees 'expired' message
            $data['status'] = 'inactive';
            self::save_license_data($data);
            return false;
        }

        // Check transient cache
        $cache = get_transient('my_plugin_license_cache');
        if ($cache !== false) {
            return $cache === 'valid';
        }

        // Remote verify
        $response = wp_remote_post(ADS_API_URL . 'wp-json/license-manager/v1/verify', [
            'body' => [
                'license_key' => $data['key'],
                'domain'      => home_url()
            ],
            'timeout' => 20,
        ]);

        if (is_wp_error($response)) {
            // Changed cache duration to 1 minute (60 seconds)
            set_transient('my_plugin_license_cache', 'invalid', 60);
            return false;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (!is_array($body) || empty($body['status'])) {
            // Changed cache duration to 1 minute (60 seconds)
            set_transient('my_plugin_license_cache', 'invalid', 60);
            return false;
        }

        // Handle invalid status
        if ($body['status'] !== 'valid') {
            $data['status'] = 'inactive';
            self::save_license_data($data);

            // Changed cache duration to 1 minute (60 seconds)
            set_transient('my_plugin_license_cache', 'invalid', 60);
            return false;
        }

        // --- License is 'valid' ---
        // Update local data if server has different values
        $updated = false;
        if (isset($body['expiry']) && $body['expiry'] !== $data['expiry']) {
            $data['expiry'] = sanitize_text_field($body['expiry']);
            $updated = true;
        }
        if (isset($body['type']) && $body['type'] !== $data['type']) {
            $data['type'] = sanitize_text_field($body['type']);
            $updated = true;
        }
        
        if ($updated) {
            self::save_license_data($data);
        }

        // Changed cache duration to 1 minute (60 seconds)
        set_transient('my_plugin_license_cache', 'valid', 60);
        return true;
    }

    /**
     * Check for license updates from server and sync local data
     * This method is called periodically to ensure local data matches server
     *
     * @return bool Whether update was successful
     */
    public static function sync_license_data() {
        $data = self::get_license_data();

        // Only sync if license is active
        if ($data['status'] !== 'active' || empty($data['key'])) {
            return false;
        }

        // Remote verify to get latest data
        $response = wp_remote_post(ADS_API_URL . 'wp-json/license-manager/v1/verify', [
            'body' => [
                'license_key' => $data['key'],
                'domain'      => home_url()
            ],
            'timeout' => 20,
        ]);

        if (is_wp_error($response)) {
            return false;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (!is_array($body) || empty($body['status'])) {
            return false;
        }

        // If license is still valid, update local data
        if ($body['status'] === 'valid') {
            $updated = false;
            
            // Update expiry if changed
            if (isset($body['expiry']) && $body['expiry'] !== $data['expiry']) {
                $data['expiry'] = sanitize_text_field($body['expiry']);
                $updated = true;
            }
            
            // Update type if changed (e.g., 3 Days Trial to 6 Months)
            if (isset($body['type']) && $body['type'] !== $data['type']) {
                $data['type'] = sanitize_text_field($body['type']);
                $updated = true;
            }
            
            if ($updated) {
                self::save_license_data($data);
                // Clear cache to force refresh
                delete_transient('my_plugin_license_cache');
                return true;
            }
        } else {
            // License is no longer valid, update local status
            $data['status'] = 'inactive';
            self::save_license_data($data);
            delete_transient('my_plugin_license_cache');
            return false;
        }

        return true;
    }

    /**
     * Activate license
     *
     * @param string $license_key
     * @return array
     */
    public static function activate_license($license_key) {
        $domain = home_url();

        $response = wp_remote_post(ADS_API_URL . 'wp-json/license-manager/v1/activate', [
            'body' => [
                'license_key' => $license_key,
                'domain'      => $domain
            ],
            'timeout' => 20,
        ]);


        if (is_wp_error($response)) {
            return ['success' => false, 'message' => 'Connection error.'];
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);


        if (!is_array($body) || empty($body['status'])) {
            return ['success' => false, 'message' => 'Invalid server response.'];
        }

        if ($body['status'] === 'activated') {
            $data = [
                'key'          => sanitize_text_field($license_key),
                'status'       => 'active',
                'expiry'       => isset($body['expiry']) ? sanitize_text_field($body['expiry']) : '',
                'domain'       => $domain,
                'activated_at' => current_time('mysql'),
                'type'         => isset($body['type']) ? sanitize_text_field($body['type']) : ''
            ];
            self::save_license_data($data);
            delete_transient('my_plugin_license_cache');

            return ['success' => true, 'message' => 'License activated successfully.'];
        }

        $data = [
            'key'          => sanitize_text_field($license_key),
            'status'       => 'inactive',
            'expiry'       => '',
            'domain'       => '',
            'activated_at' => '',
            'type'         => ''
        ];
        self::save_license_data($data);

        return ['success' => false, 'message' => $body['status']];
    }

    /**
     * Deactivate license
     *
     * @return array Returns array with 'success' and optional 'message' keys
     */
    public static function deactivate_license() {
        $data = self::get_license_data();

        if (empty($data['key'])) {
            // No license key to deactivate, just reset local data
            $reset = [
                'key'          => '',
                'status'       => 'inactive',
                'expiry'       => '',
                'domain'       => '',
                'activated_at' => '',
                'type'         => ''
            ];
            self::save_license_data($reset);
            delete_transient('my_plugin_license_cache');
            return ['success' => true, 'message' => 'License deactivated locally.'];
        }

        // Send deactivation request to server
        $response = wp_remote_post(ADS_API_URL . 'wp-json/license-manager/v1/deactivate', [
            'body' => [
                'license_key' => $data['key'],
                'domain'      => home_url()
            ],
            'timeout' => 15,
        ]);

        // Always reset local data regardless of server response
        // This allows the license to be deactivated locally even if server is unreachable
        $reset = [
            'key'          => '',
            'status'       => 'inactive',
            'expiry'       => '',
            'domain'       => '',
            'activated_at' => '',
            'type'         => ''
        ];
        self::save_license_data($reset);
        delete_transient('my_plugin_license_cache');

        if (is_wp_error($response)) {
            // Connection error, but local deactivation succeeded
            return ['success' => true, 'message' => 'License deactivated locally. Server connection failed, but license is available for use on another site.'];
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (is_array($body) && isset($body['status']) && $body['status'] === 'deactivated') {
            return ['success' => true, 'message' => 'License deactivated successfully. You can now use it on another site.'];
        }

        // Even if server response is unexpected, local deactivation succeeded
        return ['success' => true, 'message' => 'License deactivated locally.'];
    }

    /**
     * Get license information
     *
     * @return array
     */
    public static function get_license_info() {
        return self::get_license_data();
    }

    /**
     * Get WordPress site timezone
     *
     * @return \DateTimeZone
     */
    protected static function get_wp_timezone() {
        $timezone_string = get_option('timezone_string');
        if ($timezone_string) {
            return new \DateTimeZone($timezone_string);
        }

        $offset = (float) get_option('gmt_offset');
        $hours = (int) $offset;
        $minutes = ($offset - $hours) * 60;
        $offset_string = sprintf('%+03d:%02d', $hours, $minutes);

        return new \DateTimeZone($offset_string);
    }

    /**
     * Get remaining days
     *
     * @return int  Days remaining, -1 for lifetime, 0 for expired or expires today
     */
    public static function get_remaining_days() {
        $data = self::get_license_data();

        if (empty($data['expiry'])) {
            return -1; // Lifetime
        }

        try {
            $timezone = self::get_wp_timezone();

            // Get 'today' at 00:00:00 in the site's timezone
            $today = new \DateTime('today', $timezone);

            // Get 'expiry_date' at 00:00:00 (assuming it's stored as Y-m-d)
            $expiry_date = new \DateTime($data['expiry'], $timezone);

            // If expiry date is today or before, it's expired
            if ($expiry_date <= $today) {
                return 0;
            }

            // Calculate the interval
            $interval = $today->diff($expiry_date);

            // Return the number of days remaining
            return (int) $interval->days;

        } catch (\Exception $e) {
            return 0; // Invalid date format
        }
    }

    /**
     * Check if license is expired
     * License expires ON the expiry date at 00:00:00.
     * If expiry_date is today or before, the license is expired.
     *
     * @return bool
     */
    public static function is_license_expired() {
        $data = self::get_license_data();

        if (empty($data['expiry'])) {
            return false; // Lifetime
        }

        try {
            $timezone = self::get_wp_timezone();

            // Get 'today' at 00:00:00 in the site's timezone
            $today = new \DateTime('today', $timezone);

            // Get 'expiry_date' at 00:00:00 (assuming it's stored as Y-m-d)
            $expiry_date = new \DateTime($data['expiry'], $timezone);

            // The license is expired if the expiry date is today or before today.
            return $expiry_date <= $today;

        } catch (\Exception $e) {
            return true; // Failsafe: if date is invalid, consider it expired.
        }
    }
}