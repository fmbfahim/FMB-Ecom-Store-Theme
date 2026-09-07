<?php
// We can use wp-load to login as admin or test through cookie
require_once 'c:/xampp/htdocs/fmbecstore/wp-load.php';

// Get admin user
$admins = get_users(['role' => 'administrator']);
if (!empty($admins)) {
    wp_set_current_user($admins[0]->ID);
}

$test_pages = [
    'Front Home' => home_url('/'),
    'Shop Archive' => wc_get_page_permalink('shop'),
    'Cart' => wc_get_page_permalink('cart'),
    'Checkout' => wc_get_page_permalink('checkout'),
];

echo "Testing frontend templates by simulation:\n";
foreach ($test_pages as $name => $url) {
    ob_start();
    // Simulate request
    $res = wp_remote_get($url);
    ob_end_clean();
    if (is_wp_error($res)) {
        echo "$name: WP_Error: " . $res->get_error_message() . "\n";
    } else {
        $body = wp_remote_retrieve_body($res);
        $code = wp_remote_retrieve_response_code($res);
        $has_err = preg_match('/(Fatal error|Warning|Parse error|Notice):/i', $body, $m);
        echo "$name ($code): " . ($has_err ? "FOUND: " . substr(strip_tags($m[0]), 0, 80) : "CLEAN") . "\n";
        if ($has_err) {
            preg_match_all('/(Fatal error|Warning|Parse error|Notice):[^\n<]+/i', $body, $matches);
            foreach ($matches[0] as $match) {
                echo "   -> $match\n";
            }
        }
    }
}
