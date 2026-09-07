<?php
// Live Visitor Tracker & Analytics Backend

function fmb_create_visitor_logs_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'fmb_visitor_logs';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        session_id varchar(100) NOT NULL,
        ip_address varchar(50) NOT NULL,
        url text NOT NULL,
        title text NOT NULL,
        device varchar(50) NOT NULL,
        browser varchar(50) NOT NULL,
        os varchar(50) NOT NULL,
        referrer text NOT NULL,
        campaign varchar(150) NOT NULL,
        events longtext NOT NULL,
        time_in datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
        last_activity datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
        PRIMARY KEY  (id),
        KEY session_id (session_id),
        KEY ip_address (ip_address),
        KEY time_in (time_in),
        KEY last_activity (last_activity)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

    update_option('fmb_visitor_table_created_v2', true);
}
if (!get_option('fmb_visitor_table_created_v2')) {
    add_action('init', 'fmb_create_visitor_logs_table');
}

// Helper to get real IP
function fmb_get_real_ip() {
    if (isset($_SERVER["HTTP_CF_CONNECTING_IP"])) return $_SERVER["HTTP_CF_CONNECTING_IP"];
    if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($ips[0]);
    }
    return $_SERVER['REMOTE_ADDR'] ?? '';
}

// AJAX: Track new page visit
add_action('wp_ajax_fmb_track_visit', 'fmb_ajax_track_visit');
add_action('wp_ajax_nopriv_fmb_track_visit', 'fmb_ajax_track_visit');
function fmb_ajax_track_visit() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'fmb_visitor_logs';

    $session_id = sanitize_text_field($_POST['session_id'] ?? '');
    $url        = esc_url_raw($_POST['url'] ?? '');
    $title      = sanitize_text_field($_POST['title'] ?? '');
    $device     = sanitize_text_field($_POST['device'] ?? '');
    $browser    = sanitize_text_field($_POST['browser'] ?? '');
    $os         = sanitize_text_field($_POST['os'] ?? '');
    $referrer   = esc_url_raw($_POST['referrer'] ?? '');
    $campaign   = sanitize_text_field($_POST['campaign'] ?? '');
    $ip         = fmb_get_real_ip();
    $now        = current_time('mysql');

    if (!$session_id || !$url) wp_send_json_error();

    // Rate limiting: max 1 insert per IP per 2 seconds
    $rate_key = 'fmb_visit_rate_' . md5($ip);
    if (get_transient($rate_key)) {
        wp_send_json_error(['rate_limited' => true]);
    }
    set_transient($rate_key, 1, 2);

    $wpdb->insert($table_name, array(
        'session_id'    => $session_id,
        'ip_address'    => $ip,
        'url'           => $url,
        'title'         => $title,
        'device'        => $device,
        'browser'       => $browser,
        'os'            => $os,
        'referrer'      => $referrer,
        'campaign'      => $campaign,
        'time_in'       => $now,
        'last_activity' => $now
    ));

    wp_send_json_success(['log_id' => $wpdb->insert_id]);
}

// AJAX: Heartbeat update (time spent & events)
add_action('wp_ajax_fmb_track_heartbeat', 'fmb_ajax_track_heartbeat');
add_action('wp_ajax_nopriv_fmb_track_heartbeat', 'fmb_ajax_track_heartbeat');
function fmb_ajax_track_heartbeat() {
    global $wpdb;
    $log_id = intval($_POST['log_id'] ?? 0);
    $new_events = isset($_POST['events']) ? json_decode(wp_unslash($_POST['events']), true) : [];
    
    if ($log_id > 0) {
        $table_name = $wpdb->prefix . 'fmb_visitor_logs';
        
        $update_data = array('last_activity' => current_time('mysql'));
        
        if (!empty($new_events)) {
            $existing = $wpdb->get_var($wpdb->prepare("SELECT events FROM $table_name WHERE id = %d", $log_id));
            $existing_events = $existing ? json_decode($existing, true) : [];
            if (!is_array($existing_events)) $existing_events = [];
            
            $merged = array_merge($existing_events, $new_events);
            $update_data['events'] = wp_json_encode($merged);
        }
        
        $wpdb->update($table_name, $update_data, array('id' => $log_id));
        wp_send_json_success();
    }
    wp_send_json_error();
}

// ---------------------------------------------------------
// Cron Job: Daily Email Report & Delete logs older than 3 days
// ---------------------------------------------------------
if (!wp_next_scheduled('fmb_daily_tracker_maintenance')) {
    wp_schedule_event(time(), 'daily', 'fmb_daily_tracker_maintenance');
}

add_action('fmb_daily_tracker_maintenance', 'fmb_run_daily_tracker_maintenance');
function fmb_run_daily_tracker_maintenance() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'fmb_visitor_logs';

    // 1. Gather stats for yesterday
    $yesterday_start = date('Y-m-d 00:00:00', strtotime('-1 day', current_time('timestamp')));
    $yesterday_end   = date('Y-m-d 23:59:59', strtotime('-1 day', current_time('timestamp')));

    $total_views = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE time_in BETWEEN '$yesterday_start' AND '$yesterday_end'");
    $unique_visitors = $wpdb->get_var("SELECT COUNT(DISTINCT ip_address) FROM $table_name WHERE time_in BETWEEN '$yesterday_start' AND '$yesterday_end'");
    
    $mobile = $wpdb->get_var("SELECT COUNT(DISTINCT ip_address) FROM $table_name WHERE device='Mobile' AND time_in BETWEEN '$yesterday_start' AND '$yesterday_end'");
    $desktop = $wpdb->get_var("SELECT COUNT(DISTINCT ip_address) FROM $table_name WHERE device='Desktop' AND time_in BETWEEN '$yesterday_start' AND '$yesterday_end'");

    // Top 5 pages
    $top_pages = $wpdb->get_results("SELECT title, url, COUNT(*) as views FROM $table_name WHERE time_in BETWEEN '$yesterday_start' AND '$yesterday_end' GROUP BY url ORDER BY views DESC LIMIT 5");

    // 2. Build HTML Email
    $admin_email = get_option('admin_email');
    $site_name = get_bloginfo('name');
    $subject = "Daily Traffic Report - $site_name (" . date('M d, Y', strtotime('-1 day')) . ")";
    
    $html = "<h2>Daily Traffic Report for $site_name</h2>";
    $html .= "<p><strong>Date:</strong> " . date('F j, Y', strtotime('-1 day')) . "</p>";
    $html .= "<h3>Overview</h3>";
    $html .= "<ul>";
    $html .= "<li><strong>Total Page Views:</strong> $total_views</li>";
    $html .= "<li><strong>Unique Visitors:</strong> $unique_visitors</li>";
    $html .= "<li><strong>Mobile Visitors:</strong> $mobile</li>";
    $html .= "<li><strong>Desktop Visitors:</strong> $desktop</li>";
    $html .= "</ul>";
    
    $html .= "<h3>Top 5 Pages</h3>";
    $html .= "<table border='1' cellpadding='5' style='border-collapse:collapse;'>";
    $html .= "<tr><th>Page Title</th><th>Views</th></tr>";
    if ($top_pages) {
        foreach($top_pages as $p) {
            $html .= "<tr><td>{$p->title}</td><td>{$p->views}</td></tr>";
        }
    } else {
        $html .= "<tr><td colspan='2'>No page views yesterday.</td></tr>";
    }
    $html .= "</table>";
    $html .= "<br><p><em>Notice: To keep your website fast, tracking data older than 3 days is automatically deleted.</em></p>";

    $headers = array('Content-Type: text/html; charset=UTF-8');
    wp_mail($admin_email, $subject, $html, $headers);

    // 3. Delete logs older than 3 days
    $three_days_ago = date('Y-m-d H:i:s', strtotime('-3 days', current_time('timestamp')));
    $wpdb->query($wpdb->prepare("DELETE FROM $table_name WHERE time_in < %s", $three_days_ago));
}
