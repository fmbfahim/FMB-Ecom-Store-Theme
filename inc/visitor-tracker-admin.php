<?php

add_action('admin_menu', 'fmb_visitor_tracker_admin_menu');
function fmb_visitor_tracker_admin_menu() {
    add_submenu_page(
        'fmb-store',
        'Visitor Tracker',
        'Live Visitors',
        'manage_options',
        'fmb-visitor-tracker',
        'fmb_visitor_tracker_page'
    );
}

// AJAX: Get Visitor Journey
add_action('wp_ajax_fmb_get_visitor_journey', 'fmb_ajax_get_visitor_journey');
function fmb_ajax_get_visitor_journey() {
    global $wpdb;
    $ip = sanitize_text_field($_POST['ip'] ?? '');
    if (!$ip) wp_send_json_error();

    $table_name = $wpdb->prefix . 'fmb_visitor_logs';
    // Get last 24 hours journey for this IP
    $last_24h = date('Y-m-d H:i:s', strtotime('-24 hours', current_time('timestamp')));
    
    $logs = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table_name WHERE ip_address = %s AND time_in >= %s ORDER BY time_in ASC",
        $ip, $last_24h
    ));

    if (empty($logs)) wp_send_json_error(['message' => 'No recent activity found.']);

    $html = '<div style="max-height: 520px; overflow-y: auto; padding-right: 5px;">';

    // -------------------------------------------------------------
    // 1. Check for WooCommerce Orders (Completed / Processing / etc)
    // -------------------------------------------------------------
    $orders = wc_get_orders([
        'customer_ip_address' => $ip,
        'date_created' => '>=' . strtotime('-48 hours'),
        'limit' => 5,
    ]);

    // Direct DB fallback for HPOS/CPT if not found by wc_get_orders
    if (empty($orders)) {
        $hpos_table = $wpdb->prefix . 'wc_orders';
        if ($wpdb->get_var("SHOW TABLES LIKE '$hpos_table'") === $hpos_table) {
            $order_ids = $wpdb->get_col($wpdb->prepare(
                "SELECT id FROM $hpos_table WHERE ip_address = %s AND date_created_gmt >= %s ORDER BY id DESC LIMIT 5",
                $ip, date('Y-m-d H:i:s', strtotime('-48 hours'))
            ));
            if (!empty($order_ids)) {
                $orders = array_filter(array_map('wc_get_order', $order_ids));
            }
        }
    }

    if (!empty($orders)) {
        $html .= '<div style="margin-bottom: 24px; padding: 20px; background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 16px; box-shadow: 0 4px 6px -1px rgba(22, 163, 74, 0.05);">';
        $html .= '<h4 style="margin:0 0 16px 0; color: #166534; font-size:16px; font-weight:800; display:flex; align-items:center; gap:8px;">🛍️ নিশ্চিত অর্ডার (Confirmed WooCommerce Order)</h4>';
        foreach ($orders as $o) {
            $status = $o->get_status();
            $status_label = wc_get_order_status_name($status);
            $color = in_array($status, ['processing', 'completed']) ? '#16a34a' : ($status === 'cancelled' ? '#dc2626' : '#d97706');
            $bg_color = in_array($status, ['processing', 'completed']) ? '#dcfce7' : ($status === 'cancelled' ? '#fee2e2' : '#fef3c7');
            $phone = $o->get_billing_phone();
            $clean_phone = preg_replace('/[^\d]/', '', $phone);
            
            // Items summary
            $items_str = [];
            foreach ($o->get_items() as $item) {
                $items_str[] = esc_html($item->get_name()) . ' <span style="color:#64748b; font-weight:600;">× ' . $item->get_quantity() . '</span>';
            }

            $html .= '<div style="font-size: 13px; color: #334155; margin-bottom: 12px; padding: 16px; background: #ffffff; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 2px 4px rgba(0,0,0,0.02);">';
            $html .= '<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; border-bottom:1px solid #f1f5f9; padding-bottom:12px;">';
            $html .= '<strong style="font-size:15px;"><a href="' . esc_url($o->get_edit_order_url()) . '" target="_blank" style="text-decoration:none; color:#2563eb; font-weight:700;">অর্ডার #' . $o->get_id() . ' <span style="font-size:12px;opacity:0.7;">↗</span></a></strong>';
            $html .= '<span style="color:' . $color . '; font-size:11px; font-weight:700; background-color:' . $bg_color . '; padding:4px 10px; border-radius:20px; text-transform:uppercase; letter-spacing:0.5px;">' . esc_html($status_label) . '</span>';
            $html .= '</div>';
            
            $html .= '<div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px; margin-bottom:12px;">';
            $html .= '<div><span style="color:#64748b; font-size:11px; display:block; text-transform:uppercase; font-weight:600; margin-bottom:4px;">কাস্টমার নাম</span><strong style="color:#0f172a; font-size:14px;">' . esc_html($o->get_billing_first_name() . ' ' . $o->get_billing_last_name() ?: 'N/A') . '</strong></div>';
            
            $html .= '<div><span style="color:#64748b; font-size:11px; display:block; text-transform:uppercase; font-weight:600; margin-bottom:4px;">মোবাইল নম্বর</span><div style="display:flex; align-items:center; gap:8px;"><strong style="font-family:monospace; color:#0f172a; font-size:14px;">' . esc_html($phone ?: 'N/A') . '</strong>';
            if ($clean_phone) {
                $html .= '<a href="tel:' . esc_attr($clean_phone) . '" style="background:#eff6ff; color:#2563eb; font-size:10px; font-weight:700; padding:4px 8px; border-radius:6px; text-decoration:none; transition:all 0.2s;">CALL</a>';
                $html .= '<a href="https://wa.me/88' . esc_attr($clean_phone) . '" target="_blank" style="background:#dcfce7; color:#16a34a; font-size:10px; font-weight:700; padding:4px 8px; border-radius:6px; text-decoration:none; transition:all 0.2s;">WA</a>';
            }
            $html .= '</div></div>';
            $html .= '</div>';
            
            if ($o->get_billing_address_1()) {
                $html .= '<div style="margin-bottom:12px;"><span style="color:#64748b; font-size:11px; display:block; text-transform:uppercase; font-weight:600; margin-bottom:4px;">ঠিকানা</span><span style="color:#334155;">' . esc_html($o->get_billing_address_1()) . '</span></div>';
            }
            if (!empty($items_str)) {
                $html .= '<div style="margin-bottom:12px; padding:12px; background:#f8fafc; border-radius:8px; border:1px solid #f1f5f9;"><span style="color:#64748b; font-size:11px; display:block; text-transform:uppercase; margin-bottom:6px; font-weight:600;">অর্ডারকৃত পণ্য</span>' . implode('<br>', $items_str) . '</div>';
            }
            $html .= '<div style="font-weight:800; color:#0f172a; font-size:16px; margin-top:16px; padding-top:12px; border-top:1px dashed #e2e8f0; display:flex; justify-content:space-between;"><span style="color:#64748b; font-size:13px; font-weight:600;">মোট বিল:</span>' . wc_price($o->get_total()) . '</div>';
            $html .= '</div>';
        }
        $html .= '</div>';
    }

    // -------------------------------------------------------------
    // 2. Check for Incomplete Orders (Leads / Abandoned Checkouts)
    // -------------------------------------------------------------
    $inc_table = $wpdb->prefix . 'fmb_incomplete_orders_tracker';
    if ($wpdb->get_var("SHOW TABLES LIKE '$inc_table'") === $inc_table) {
        $incomplete_leads = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $inc_table WHERE (billing_phone != '' OR billing_first_name != '') AND created_at >= %s ORDER BY id DESC LIMIT 3",
            date('Y-m-d H:i:s', strtotime('-48 hours'))
        ));

    if (!empty($incomplete_leads)) {
        $html .= '<div style="margin-bottom: 24px; padding: 20px; background: #fffbeb; border: 1px solid #fde047; border-radius: 16px; box-shadow: 0 4px 6px -1px rgba(202, 138, 4, 0.05);">';
        $html .= '<h4 style="margin:0 0 16px 0; color: #b45309; font-size:16px; font-weight:800; display:flex; align-items:center; gap:8px;">⚠️ অসম্পূর্ণ অর্ডার / লাইভ লিড (Incomplete Lead)</h4>';
        foreach ($incomplete_leads as $inc) {
            $clean_inc_phone = preg_replace('/[^\d]/', '', $inc->billing_phone);
            $html .= '<div style="font-size: 13px; color: #334155; margin-bottom: 12px; padding: 16px; background: #ffffff; border-radius: 12px; border: 1px solid #fef08a; box-shadow: 0 2px 4px rgba(0,0,0,0.02);">';
            
            $html .= '<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; border-bottom:1px solid #fef08a; padding-bottom:12px;">';
            $html .= '<strong style="font-size:15px; color:#d97706;">Incomplete Cart #' . esc_html($inc->id) . '</strong>';
            $html .= '<span style="color:#b45309; font-size:11px; font-weight:700; background-color:#fef3c7; padding:4px 10px; border-radius:20px; text-transform:uppercase; letter-spacing:0.5px;">ড্রপ-অফ (Not Ordered)</span>';
            $html .= '</div>';
            
            $html .= '<div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px; margin-bottom:12px;">';
            $html .= '<div><span style="color:#64748b; font-size:11px; display:block; text-transform:uppercase; font-weight:600; margin-bottom:4px;">কাস্টমার নাম</span><strong style="color:#0f172a; font-size:14px;">' . esc_html($inc->billing_first_name . ' ' . $inc->billing_last_name ?: 'Unknown') . '</strong></div>';
            
            $html .= '<div><span style="color:#64748b; font-size:11px; display:block; text-transform:uppercase; font-weight:600; margin-bottom:4px;">মোবাইল নম্বর</span><div style="display:flex; align-items:center; gap:8px;"><strong style="font-family:monospace; color:#0f172a; font-size:14px;">' . esc_html($inc->billing_phone ?: 'N/A') . '</strong>';
            if ($clean_inc_phone) {
                $html .= '<a href="tel:' . esc_attr($clean_inc_phone) . '" style="background:#eff6ff; color:#2563eb; font-size:10px; font-weight:700; padding:4px 8px; border-radius:6px; text-decoration:none; transition:all 0.2s;">CALL</a>';
                $html .= '<a href="https://wa.me/88' . esc_attr($clean_inc_phone) . '" target="_blank" style="background:#dcfce7; color:#16a34a; font-size:10px; font-weight:700; padding:4px 8px; border-radius:6px; text-decoration:none; transition:all 0.2s;">WA</a>';
            }
            $html .= '</div></div>';
            $html .= '</div>';
            
            if ($inc->billing_address_1) {
                $html .= '<div style="margin-bottom:12px;"><span style="color:#64748b; font-size:11px; display:block; text-transform:uppercase; font-weight:600; margin-bottom:4px;">ঠিকানা</span><span style="color:#334155;">' . esc_html($inc->billing_address_1) . '</span></div>';
            }
            if ($inc->order_total > 0) {
                $html .= '<div style="font-weight:800; color:#0f172a; font-size:15px; margin-top:16px; padding-top:12px; border-top:1px dashed #fde047; display:flex; justify-content:space-between;"><span style="color:#64748b; font-size:13px; font-weight:600;">কার্ট টোটাল:</span>' . wc_price($inc->order_total) . '</div>';
            }
            
            $html .= '<div style="margin-top:16px; text-align:right;"><a href="' . admin_url('admin.php?page=fmb-engine-incomplete-orders') . '" target="_blank" style="background:#f59e0b; color:#ffffff; font-size:12px; font-weight:700; padding:8px 16px; border-radius:8px; text-decoration:none; display:inline-block; transition:all 0.2s; box-shadow: 0 2px 4px rgba(245,158,11,0.2);">ইনকমপ্লিট প্যানেলে দেখুন ↗</a></div>';
            $html .= '</div>';
        }
        $html .= '</div>';
    }
    }

    // -------------------------------------------------------------
    // 3. Visitor Action Timeline
    // -------------------------------------------------------------
    $html .= '<h4 style="margin: 32px 0 20px 0; font-size: 18px; font-weight: 800; color: #0f172a; padding-bottom: 12px; display:flex; align-items:center; gap:8px;">🧭 ভিজিটর অ্যাক্টিভিটি টাইমলাইন</h4>';
    $html .= '<ul style="margin: 0 0 0 10px; padding: 0; list-style: none; position: relative;">';
    $html .= '<div style="position: absolute; top: 0; bottom: 0; left: 6px; width: 2px; background: #e2e8f0; border-radius: 2px;"></div>';
    
    foreach ($logs as $log) {
        $duration = strtotime($log->last_activity) - strtotime($log->time_in);
        $dur_str = $duration > 0 ? gmdate("H:i:s", $duration) : "00:00:00";
        $time_str = date('h:i A', strtotime($log->time_in));
        
        $html .= '<li style="margin-bottom: 28px; padding-left: 32px; position: relative;">';
        $html .= '<div style="position: absolute; left: 0; top: 4px; width: 14px; height: 14px; background: #ffffff; border: 3px solid #3b82f6; border-radius: 50%; box-shadow: 0 0 0 4px #eff6ff;"></div>';
        
        $html .= '<div style="font-size:14px; font-weight:700; color:#0f172a; margin-bottom:6px; display:flex; flex-wrap:wrap; gap:8px; align-items:center;">';
        $html .= '<span style="color:#1e40af; background:#eff6ff; padding:2px 10px; border-radius:12px; font-size:11px;">' . esc_html($time_str) . '</span>';
        $html .= '<a href="'.esc_url($log->url).'" target="_blank" style="color:#2563eb; text-decoration:none; transition:color 0.2s;">' . esc_html($log->title ?: $log->url) . '</a>';
        $html .= '</div>';
        
        $html .= '<span style="color: #64748b; font-size: 12px; font-family:monospace; font-weight:600; display:block; margin-bottom:12px;">⏱️ সময় ব্যয়: ' . $dur_str . '</span>';
        
        $events = json_decode($log->events, true);
        if ($events && is_array($events)) {
            $html .= '<ul style="margin:8px 0 16px 0; padding:16px; list-style:none; font-size:13px; color:#334155; background:#f8fafc; border-radius:12px; border:1px solid #f1f5f9;">';
            foreach($events as $ev) {
                $type = $ev['type'] ?? '';
                $val = $ev['value'] ?? '';
                
                $icon = '🔹';
                $badge_bg = '#ffffff';
                $badge_color = '#334155';
                $border = '#e2e8f0';

                if ($type === 'scroll') {
                    $icon = '📜';
                    $badge_bg = '#f0fdf4';
                    $badge_color = '#166534';
                    $border = '#bbf7d0';
                } elseif ($type === 'click') {
                    $icon = '🔘';
                    $badge_bg = '#eff6ff';
                    $badge_color = '#1d4ed8';
                    $border = '#bfdbfe';
                } elseif ($type === 'input') {
                    $icon = '✍️';
                    $badge_bg = '#fef3c7';
                    $badge_color = '#b45309';
                    $border = '#fde047';
                }

                $html .= '<li style="margin-bottom: 8px; display:flex; align-items:flex-start; gap:8px;">';
                $html .= '<span style="opacity:0.8; font-size:14px; margin-top:2px;">' . $icon . '</span>';
                $html .= '<span style="background:' . $badge_bg . '; color:' . $badge_color . '; padding:4px 10px; border-radius:6px; border:1px solid ' . $border . '; font-weight:600; font-family:monospace; font-size:11px; word-break:break-word; line-height:1.4;">' . esc_html($val) . '</span>';
                $html .= '</li>';
            }
            $html .= '</ul>';
        }

        if ($log->referrer) {
            $html .= '<div style="color: #64748b; font-size: 12px; margin-top:8px; display:flex; gap:6px;"><strong style="text-transform:uppercase; font-size:10px;">Ref:</strong> <span style="color:#475569; word-break:break-all;">' . esc_html($log->referrer) . '</span></div>';
        }
        if ($log->campaign) {
            $html .= '<div style="color: #be123c; font-size: 11px; font-weight: 700; margin-top:8px; display:inline-block; background:#ffe4e6; border:1px solid #fecdd3; padding:4px 10px; border-radius:20px; text-transform:uppercase; letter-spacing:0.5px;">🎯 Campaign: ' . esc_html($log->campaign) . '</div>';
        }
        $html .= '</li>';
    }
    
    $html .= '</ul>';
    $html .= '</div>';
    wp_send_json_success(['html' => $html]);
}

function fmb_visitor_tracker_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'fmb_visitor_logs';
    $now_timestamp = current_time('timestamp');
    $today_start = date('Y-m-d 00:00:00', $now_timestamp);
    $active_threshold = date('Y-m-d H:i:s', strtotime('-3 minutes', $now_timestamp));

    // Stats
    $total_today = $wpdb->get_var("SELECT COUNT(DISTINCT ip_address) FROM $table_name WHERE time_in >= '$today_start'");
    $mobile = $wpdb->get_var("SELECT COUNT(DISTINCT ip_address) FROM $table_name WHERE device='Mobile' AND time_in >= '$today_start'");
    $desktop = $wpdb->get_var("SELECT COUNT(DISTINCT ip_address) FROM $table_name WHERE device='Desktop' AND time_in >= '$today_start'");
    $active_now = $wpdb->get_var("SELECT COUNT(DISTINCT ip_address) FROM $table_name WHERE last_activity >= '$active_threshold'");

    // Hourly Graph Data for Today
    $graph_data = [];
    $labels = [];
    for ($i = 23; $i >= 0; $i--) {
        $h_start = date('Y-m-d H:00:00', strtotime("-$i hours", $now_timestamp));
        $h_end   = date('Y-m-d H:59:59', strtotime("-$i hours", $now_timestamp));
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE time_in BETWEEN '$h_start' AND '$h_end'");
        $graph_data[] = intval($count);
    }
    for ($i = 23; $i >= 0; $i--) {
        $labels[] = date('ha', strtotime("-$i hours", $now_timestamp));
    }

    // Top Pages
    $top_pages = $wpdb->get_results("SELECT title, url, COUNT(*) as views FROM $table_name WHERE time_in >= '$today_start' GROUP BY url ORDER BY views DESC LIMIT 5");

    // Browsers
    $browsers = $wpdb->get_results("SELECT browser, COUNT(DISTINCT ip_address) as count FROM $table_name WHERE time_in >= '$today_start' GROUP BY browser ORDER BY count DESC LIMIT 5");

    // Active Visitors List (last 50)
    $active_list = $wpdb->get_results("
        SELECT * FROM $table_name 
        WHERE id IN (
            SELECT MAX(id) FROM $table_name GROUP BY ip_address
        )
        ORDER BY last_activity DESC LIMIT 50
    ");

    // Output UI
    ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .vt-wrap { font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; max-width: 1400px; margin: 20px auto; color: #1e293b; padding: 0 10px; animation: fadeIn 0.5s ease-out; }
        
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        @keyframes fadeUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        
        /* Premium Header with Mesh Gradient */
        .vt-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; padding: 32px 40px; border-radius: 24px; color: white; background: linear-gradient(135deg, #1e1b4b, #312e81, #1e40af); background-size: 200% 200%; animation: gradientShift 10s ease infinite; box-shadow: 0 20px 40px -10px rgba(49, 46, 129, 0.3); position: relative; overflow: hidden; }
        .vt-header::after { content: ''; position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: url('data:image/svg+xml;utf8,<svg viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg"><filter id="noise"><feTurbulence type="fractalNoise" baseFrequency="0.65" numOctaves="3" stitchTiles="stitch"/></filter><rect width="100%" height="100%" filter="url(%23noise)" opacity="0.05"/></svg>'); pointer-events: none; mix-blend-mode: overlay; }
        
        @keyframes gradientShift { 0% { background-position: 0% 50%; } 50% { background-position: 100% 50%; } 100% { background-position: 0% 50%; } }
        
        .vt-header h1 { font-size: 32px; font-weight: 800; color: #ffffff; margin: 0; letter-spacing: -1px; display: flex; align-items: center; gap: 16px; position: relative; z-index: 2; text-shadow: 0 2px 10px rgba(0,0,0,0.2); }
        .vt-refresh { background: rgba(255, 255, 255, 0.15); backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px); color: #ffffff; padding: 10px 20px; border-radius: 30px; font-size: 14px; font-weight: 600; display: flex; align-items: center; gap: 8px; border: 1px solid rgba(255,255,255,0.2); position: relative; z-index: 2; }
        
        /* Stats Grid - Glassy & Floating */
        .vt-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 24px; margin-bottom: 32px; }
        .vt-stat-card { background: #ffffff; border-radius: 24px; padding: 32px 24px; text-align: center; box-shadow: 0 10px 30px -5px rgba(59, 130, 246, 0.08), 0 4px 6px -4px rgba(59, 130, 246, 0.04); transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); position: relative; border: 1px solid rgba(241, 245, 249, 0.8); animation: fadeUp 0.6s ease-out backwards; }
        .vt-grid .vt-stat-card:nth-child(1) { animation-delay: 0.1s; }
        .vt-grid .vt-stat-card:nth-child(2) { animation-delay: 0.2s; }
        .vt-grid .vt-stat-card:nth-child(3) { animation-delay: 0.3s; }
        .vt-grid .vt-stat-card:nth-child(4) { animation-delay: 0.4s; }
        
        .vt-stat-card:hover { transform: translateY(-10px) scale(1.02); box-shadow: 0 25px 40px -10px rgba(59, 130, 246, 0.15); border-color: rgba(59, 130, 246, 0.1); }
        
        .vt-stat-icon { width: 56px; height: 56px; border-radius: 18px; display: flex; align-items: center; justify-content: center; font-size: 28px; margin: 0 auto 20px; box-shadow: 0 8px 16px -4px currentcolor; transition: transform 0.3s ease; }
        .vt-stat-card:hover .vt-stat-icon { transform: scale(1.1) rotate(5deg); }
        
        .vt-stat-card h2 { font-size: 46px; font-weight: 800; margin: 0; line-height: 1; letter-spacing: -2px; }
        .vt-stat-card p { font-size: 13px; font-weight: 700; color: #64748b; margin: 12px 0 0; text-transform: uppercase; letter-spacing: 1px; }
        
        /* Content Cards */
        .vt-card { background: #ffffff; border-radius: 28px; padding: 36px; box-shadow: 0 10px 40px -10px rgba(15, 23, 42, 0.05); margin-bottom: 36px; border: 1px solid #f1f5f9; animation: fadeUp 0.7s ease-out backwards; animation-delay: 0.5s; }
        .vt-card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 28px; }
        .vt-card h3 { color: #0f172a; font-size: 20px; font-weight: 800; margin: 0; display: flex; align-items: center; gap: 12px; letter-spacing: -0.5px; }
        
        .vt-flex-row { display: flex; gap: 36px; flex-wrap: wrap; }
        .vt-flex-col { flex: 1; min-width: 320px; }
        
        /* Modern Tables with Micro-interactions */
        .vt-table { width: 100%; border-collapse: separate; border-spacing: 0; }
        .vt-table th { color: #64748b; font-weight: 700; text-transform: uppercase; font-size: 12px; letter-spacing: 1px; padding: 18px; text-align: left; border-bottom: 2px solid #f1f5f9; background: #f8fafc; }
        .vt-table th:first-child { border-top-left-radius: 16px; border-bottom-left-radius: 16px; }
        .vt-table th:last-child { border-top-right-radius: 16px; border-bottom-right-radius: 16px; }
        .vt-table td { padding: 18px; font-size: 14px; color: #334155; border-bottom: 1px solid #f1f5f9; vertical-align: middle; transition: all 0.2s ease; }
        .vt-table tr:last-child td { border-bottom: none; }
        
        /* Row Hover Effect */
        .vt-table tbody tr { transition: transform 0.2s ease, background 0.2s ease; border-radius: 16px; }
        .vt-table tbody tr:hover { transform: translateX(4px); background: #f8fafc; }
        .vt-table tbody tr:hover td { border-bottom-color: transparent; }
        
        .vt-table a { color: #3b82f6; text-decoration: none; font-weight: 600; transition: color 0.2s; position: relative; }
        .vt-table a::after { content: ''; position: absolute; width: 100%; transform: scaleX(0); height: 2px; bottom: -2px; left: 0; background-color: #2563eb; transform-origin: bottom right; transition: transform 0.25s ease-out; }
        .vt-table a:hover::after { transform: scaleX(1); transform-origin: bottom left; }
        
        /* Badges & UI Elements */
        .vt-badge { display: inline-flex; align-items: center; justify-content: center; padding: 6px 14px; border-radius: 30px; font-size: 12px; font-weight: 800; letter-spacing: 0.5px; text-transform: uppercase; box-shadow: inset 0 0 0 1px currentcolor; }
        .vt-badge-active { background: #f0fdf4; color: #16a34a; }
        .vt-badge-inactive { background: #f8fafc; color: #64748b; }
        .vt-badge-campaign { background: #fef2f2; color: #e11d48; }
        
        .vt-pulse-dot { width: 10px; height: 10px; background: #22c55e; border-radius: 50%; display: inline-block; margin-right: 8px; box-shadow: 0 0 0 rgba(34,197,94,0.4); animation: vt-pulse 2s infinite; }
        @keyframes vt-pulse { 0% { box-shadow: 0 0 0 0 rgba(34,197,94,0.7); } 70% { box-shadow: 0 0 0 10px rgba(34,197,94,0); } 100% { box-shadow: 0 0 0 0 rgba(34,197,94,0); } }
        
        .vt-ip-btn { background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; padding: 8px 18px; border-radius: 12px; font-weight: 700; cursor: pointer; transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1); display: inline-flex; align-items: center; gap: 8px; font-size: 13px; font-family: monospace; box-shadow: 0 2px 4px rgba(37,99,235,0.05); }
        .vt-ip-btn:hover { background: #3b82f6; color: #ffffff; border-color: #2563eb; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(37,99,235,0.2); }
        .vt-ip-btn::after { content: '→'; font-size: 14px; transition: transform 0.2s; }
        .vt-ip-btn:hover::after { transform: translateX(3px); }
        
        /* Progress Bars */
        .vt-bar-wrap { width: 100%; background: #f1f5f9; border-radius: 12px; height: 10px; overflow: hidden; margin-top: 10px; border: 1px solid #e2e8f0; }
        .vt-bar-fill { height: 100%; background: linear-gradient(90deg, #3b82f6, #60a5fa); border-radius: 12px; position: relative; overflow: hidden; }
        .vt-bar-fill::after { content: ''; position: absolute; top: 0; left: 0; bottom: 0; right: 0; background: linear-gradient(90deg, rgba(255,255,255,0) 0%, rgba(255,255,255,0.3) 50%, rgba(255,255,255,0) 100%); animation: shimmer 2s infinite; }
        @keyframes shimmer { 0% { transform: translateX(-100%); } 100% { transform: translateX(100%); } }
        
        /* Modal Styles - Glassy */
        #vt-modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.3); z-index: 999999; backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); }
        .vt-modal-content { background: #ffffff; width: 800px; max-width: 95%; margin: 5vh auto; padding: 0; border-radius: 28px; position: relative; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.3), 0 0 0 1px rgba(255,255,255,0.2) inset; display: flex; flex-direction: column; max-height: 90vh; overflow: hidden; }
        .vt-modal-header { padding: 28px 36px; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; background: #ffffff; }
        .vt-modal-header h2 { margin: 0; color: #0f172a; font-size: 22px; font-weight: 800; display: flex; align-items: center; gap: 12px; letter-spacing: -0.5px; }
        .vt-close { cursor: pointer; width: 40px; height: 40px; background: #f1f5f9; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 20px; color: #64748b; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        .vt-close:hover { background: #fee2e2; color: #e11d48; transform: rotate(90deg) scale(1.1); }
        #vt-modal-body { padding: 36px; overflow-y: auto; flex: 1; background: #fafaf9; }
        
        /* Modern Scrollbar */
        ::-webkit-scrollbar { width: 10px; height: 10px; }
        ::-webkit-scrollbar-track { background: #f8fafc; border-radius: 10px; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; border: 2px solid #f8fafc; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
    </style>

    <div class="vt-wrap">
        <div class="vt-header">
            <h1><span style="font-size:36px; background: #ffffff; border-radius: 16px; padding: 6px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">📊</span> Visitor Analytics & Insights</h1>
            <div class="vt-refresh"><span class="vt-pulse-dot" style="background:#4ade80;"></span> Auto-updating (30s)</div>
        </div>

        <div class="vt-grid">
            <div class="vt-stat-card">
                <div class="vt-stat-icon" style="background: #dcfce7; color: #16a34a;">🟢</div>
                <h2 style="color: #15803d;"><?php echo $active_now; ?></h2>
                <p>Active Right Now</p>
            </div>
            <div class="vt-stat-card">
                <div class="vt-stat-icon" style="background: #eff6ff; color: #2563eb;">👥</div>
                <h2 style="color: #1e40af;"><?php echo $total_today; ?></h2>
                <p>Unique Visitors (Today)</p>
            </div>
            <div class="vt-stat-card">
                <div class="vt-stat-icon" style="background: #fef3c7; color: #d97706;">📱</div>
                <h2 style="color: #b45309;"><?php echo $mobile; ?></h2>
                <p>Mobile Users</p>
            </div>
            <div class="vt-stat-card">
                <div class="vt-stat-icon" style="background: #f3e8ff; color: #9333ea;">💻</div>
                <h2 style="color: #7e22ce;"><?php echo $desktop; ?></h2>
                <p>Desktop Users</p>
            </div>
        </div>

        <div class="vt-card">
            <div class="vt-flex-row">
                <div class="vt-flex-col">
                    <div class="vt-card-header">
                        <h3><span style="background:#fee2e2; padding:8px; border-radius:10px;">📉</span> Top Drop-off Pages</h3>
                    </div>
                    <table class="vt-table">
                        <?php 
                        $drop_offs = $wpdb->get_results("SELECT url, MAX(title) as title, COUNT(*) as drops FROM $table_name WHERE time_in >= '$today_start' GROUP BY url ORDER BY drops DESC LIMIT 4");
                        if($drop_offs) {
                            $max_drops = $drop_offs[0]->drops;
                            foreach($drop_offs as $d) {
                                $pct = ($d->drops / $max_drops) * 100;
                                echo '<tr>';
                                echo '<td>';
                                echo '<div style="font-weight:700; color:#0f172a; margin-bottom:4px; max-width:250px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">' . esc_html($d->title ?: 'Page') . '</div>';
                                echo '<a href="'.esc_url($d->url).'" target="_blank" style="font-size:11px; display:block; max-width:250px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; color:#64748b; margin-bottom:8px;">'.esc_url($d->url).'</a>';
                                echo '<div class="vt-bar-wrap"><div class="vt-bar-fill" style="width:'.$pct.'%; background:#ef4444;"></div></div></td>';
                                echo '<td width="60px" style="text-align:right; vertical-align:bottom;"><strong style="font-size:16px;">'.$d->drops.'</strong></td>';
                                echo '</tr>';
                            }
                        } else {
                            echo '<tr><td colspan="2" style="text-align:center; padding:30px; color:#94a3b8;">No drop-off data yet.</td></tr>';
                        }
                        ?>
                    </table>
                </div>
                <div class="vt-flex-col">
                    <div class="vt-card-header">
                        <h3><span style="background:#dcfce7; padding:8px; border-radius:10px;">🎯</span> Top Campaigns</h3>
                    </div>
                    <table class="vt-table">
                        <?php 
                        $best_camps = $wpdb->get_results("SELECT campaign, COUNT(DISTINCT ip_address) as visitors FROM $table_name WHERE campaign != '' AND time_in >= '$today_start' GROUP BY campaign ORDER BY visitors DESC LIMIT 4");
                        if($best_camps) {
                            $max_camp = $best_camps[0]->visitors;
                            foreach($best_camps as $c) {
                                $pct = ($c->visitors / $max_camp) * 100;
                                echo '<tr>';
                                echo '<td><span class="vt-badge vt-badge-campaign">'.esc_html($c->campaign).'</span>';
                                echo '<div class="vt-bar-wrap"><div class="vt-bar-fill" style="width:'.$pct.'%; background:#10b981;"></div></div></td>';
                                echo '<td width="60px" style="text-align:right;"><strong style="font-size:16px;">'.$c->visitors.'</strong></td>';
                                echo '</tr>';
                            }
                        } else {
                            echo '<tr><td colspan="2" style="text-align:center; padding:30px; color:#94a3b8;">No campaign data yet.</td></tr>';
                        }
                        ?>
                    </table>
                </div>
            </div>
        </div>

        <div class="vt-card">
            <div class="vt-card-header">
                <h3><span style="background:#eff6ff; padding:8px; border-radius:10px;">📈</span> 24-Hour Traffic Trend</h3>
            </div>
            <div style="height: 300px; position: relative;">
                <canvas id="trafficChart"></canvas>
            </div>
        </div>

        <div class="vt-flex-row" style="margin-bottom: 32px;">
            <div class="vt-flex-col vt-card" style="margin-bottom: 0;">
                <div class="vt-card-header">
                    <h3><span style="background:#fef3c7; padding:8px; border-radius:10px;">🔥</span> Top Pages Today</h3>
                </div>
                <table class="vt-table">
                    <?php 
                    if($top_pages) {
                        foreach($top_pages as $p) {
                            echo '<tr><td><a href="'.esc_url($p->url).'" target="_blank" style="font-weight:600;">'.esc_html($p->title ?: 'Page').'</a></td><td width="20%" style="text-align:right;"><strong style="font-size:16px; color:#0f172a;">'.$p->views.'</strong> <span style="font-size:11px; color:#64748b;">views</span></td></tr>';
                        }
                    } else {
                        echo '<tr><td colspan="2" style="text-align:center; color:#64748b; padding:30px;">No page views today.</td></tr>';
                    }
                    ?>
                </table>
            </div>
            <div class="vt-flex-col vt-card" style="margin-bottom: 0;">
                <div class="vt-card-header">
                    <h3><span style="background:#f3e8ff; padding:8px; border-radius:10px;">🌐</span> Top Browsers</h3>
                </div>
                <table class="vt-table">
                    <?php 
                    if($browsers) {
                        foreach($browsers as $b) {
                            echo '<tr><td><div style="display:flex; align-items:center; gap:10px;"><span style="font-size:20px;">🌍</span><span style="font-weight:600;">'.esc_html($b->browser).'</span></div></td><td width="20%" style="text-align:right;"><strong style="font-size:16px; color:#0f172a;">'.$b->count.'</strong> <span style="font-size:11px; color:#64748b;">users</span></td></tr>';
                        }
                    } else {
                        echo '<tr><td colspan="2" style="text-align:center; color:#64748b; padding:30px;">No browser data yet.</td></tr>';
                    }
                    ?>
                </table>
            </div>
        </div>

        <div class="vt-card">
            <div class="vt-card-header">
                <h3><span style="background:#dbeafe; padding:8px; border-radius:10px;">⚡</span> Live Customer Streams</h3>
                <span style="font-size:13px; color:#64748b; font-weight:500;">Click on any IP to view their journey</span>
            </div>
            <div style="overflow-x: auto;">
                <table class="vt-table">
                    <thead>
                        <tr>
                            <th>Customer IP</th>
                            <th>Status</th>
                            <th>Current/Last Page</th>
                            <th>Device Details</th>
                            <th>Source</th>
                            <th>Duration</th>
                            <th>Entry Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        if($active_list) {
                            foreach($active_list as $l) {
                                $is_active = (strtotime($l->last_activity) >= strtotime($active_threshold)) ? true : false;
                                $status_html = $is_active ? '<span class="vt-badge vt-badge-active"><span class="vt-pulse-dot"></span> Active</span>' : '<span class="vt-badge vt-badge-inactive">Left</span>';
                                $duration = strtotime($l->last_activity) - strtotime($l->time_in);
                                $dur_str = $duration > 0 ? gmdate("i\m s\s", $duration) : "00m 00s";
                                if($duration >= 3600) $dur_str = gmdate("H\h i\m s\s", $duration);
                                
                                echo '<tr>';
                                echo '<td><button class="vt-ip-btn" data-ip="'.esc_attr($l->ip_address).'" title="View Journey">'.esc_html($l->ip_address).'</button></td>';
                                echo '<td>'.$status_html.'</td>';
                                echo '<td><a href="'.esc_url($l->url).'" target="_blank" title="'.esc_attr($l->url).'" style="max-width:200px; display:block; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; font-weight:600;">'.esc_html($l->title ?: 'URL').'</a></td>';
                                echo '<td><div style="font-weight:600; color:#0f172a;">'.esc_html($l->device).'</div><div style="font-size:11px; color:#64748b; margin-top:2px;">'.esc_html($l->browser).'</div></td>';
                                
                                $ref = $l->referrer ? esc_html($l->referrer) : 'Direct';
                                echo '<td><div style="max-width:150px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; font-size:12px; color:#475569;" title="'.$ref.'">'.$ref.'</div>';
                                if($l->campaign) echo '<div style="margin-top:4px;"><span class="vt-badge vt-badge-campaign" style="font-size:10px; padding:2px 8px;">'.esc_html($l->campaign).'</span></div>';
                                echo '</td>';
                                
                                echo '<td><span style="font-weight:600; color:#334155;">'.$dur_str.'</span></td>';
                                echo '<td><span style="color:#64748b; font-size:13px;">'.date('h:i A', strtotime($l->time_in)).'</span></td>';
                                echo '</tr>';
                            }
                        } else {
                            echo '<tr><td colspan="7" style="text-align:center; padding: 60px; color:#64748b; font-size:15px;">No recent visitors found today.</td></tr>';
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Journey Modal -->
    <div id="vt-modal">
        <div class="vt-modal-content">
            <div class="vt-modal-header">
                <h2>📍 Customer Journey</h2>
                <div class="vt-close">&times;</div>
            </div>
            <div id="vt-modal-body">
                <div style="text-align:center; padding:50px; color:#64748b;">
                    <div style="font-size:40px; margin-bottom:10px;">⏳</div>
                    Loading Journey Data...
                </div>
            </div>
        </div>
    </div>

    <script>
    jQuery(document).ready(function($) {
        // Chart.js
        var ctx = document.getElementById('trafficChart').getContext('2d');
        
        // Gradient for chart
        var gradient = ctx.createLinearGradient(0, 0, 0, 300);
        gradient.addColorStop(0, 'rgba(59, 130, 246, 0.4)');
        gradient.addColorStop(1, 'rgba(59, 130, 246, 0.0)');
        
        var myChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($labels); ?>,
                datasets: [{
                    label: 'Page Views',
                    data: <?php echo json_encode($graph_data); ?>,
                    backgroundColor: gradient,
                    borderColor: '#3b82f6',
                    borderWidth: 3,
                    pointBackgroundColor: '#ffffff',
                    pointBorderColor: '#3b82f6',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: { 
                responsive: true,
                maintainAspectRatio: false,
                scales: { 
                    y: { 
                        beginAtZero: true,
                        grid: { color: '#f1f5f9', drawBorder: false }
                    },
                    x: {
                        grid: { display: false, drawBorder: false }
                    }
                }, 
                plugins: { 
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#0f172a',
                        padding: 12,
                        titleFont: { size: 13, family: 'Inter' },
                        bodyFont: { size: 14, weight: 'bold', family: 'Inter' },
                        cornerRadius: 8,
                        displayColors: false
                    }
                },
                interaction: {
                    intersect: false,
                    mode: 'index',
                },
            }
        });

        // Journey Modal
        $('.vt-ip-btn').on('click', function() {
            var ip = $(this).data('ip');
            $('#vt-modal .vt-modal-header h2').html('📍 Customer Journey: <span style="color:#3b82f6; font-family:monospace;">' + ip + '</span>');
            $('#vt-modal-body').html('<div style="text-align:center; padding:50px; color:#64748b;"><div style="font-size:40px; margin-bottom:10px;">⏳</div>Loading Journey Data...</div>');
            $('#vt-modal').fadeIn(200);
            
            // Add a small scale effect to the content
            $('.vt-modal-content').css({transform: 'scale(0.95)', opacity: 0}).animate({opacity: 1}, 200).css('transform', 'scale(1)');

            $.post(ajaxurl, {
                action: 'fmb_get_visitor_journey',
                ip: ip
            }, function(res) {
                if (res.success) {
                    $('#vt-modal-body').hide().html(res.data.html).fadeIn(300);
                } else {
                    $('#vt-modal-body').html('<div style="text-align:center; padding:40px; color:#ef4444; background:#fef2f2; border-radius:12px; margin:20px;">⚠️ Error loading journey data.</div>');
                }
            });
        });

        $('.vt-close, #vt-modal').on('click', function(e) {
            if (e.target === this) {
                $('#vt-modal').fadeOut(200);
            }
        });

        // Auto Refresh every 30s
        setTimeout(function() {
            if ($('#vt-modal').is(':hidden')) {
                window.location.reload();
            }
        }, 30000);
    });
    </script>
    <?php
}
