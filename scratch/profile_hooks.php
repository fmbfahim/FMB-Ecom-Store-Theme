<?php
define('WP_USE_THEMES', false);
require_once 'c:/xampp/htdocs/fmbecstore/wp-load.php';

$order = wc_create_order();
$order->add_product(wc_get_product(153), 1);
$order->set_payment_method('cod');

// List of hooks to time
$hook_timings = [];
$start_hook = function($tag) use (&$hook_timings) {
    global $wp_actions;
    $t = microtime(true);
    // trace
};

// Trace all actions added to woocommerce_order_status_processing, woocommerce_order_status_changed, etc.
global $wp_filter;
$target_hooks = [
    'woocommerce_order_status_processing',
    'woocommerce_order_status_changed',
    'woocommerce_new_order',
    'woocommerce_payment_complete',
    'woocommerce_order_actions'
];

foreach ($target_hooks as $tag) {
    if (isset($wp_filter[$tag])) {
        foreach ($wp_filter[$tag]->callbacks as $priority => $callbacks) {
            foreach ($callbacks as $idx => $cb_data) {
                $cb = $cb_data['function'];
                $cb_name = is_string($cb) ? $cb : (is_array($cb) ? (is_object($cb[0]) ? get_class($cb[0]) : $cb[0]) . '::' . $cb[1] : 'closure');
                // Wrap callback with timer
                $wp_filter[$tag]->callbacks[$priority][$idx]['function'] = function(...$args) use ($cb, $cb_name, $tag) {
                    $t1 = microtime(true);
                    $res = call_user_func_array($cb, $args);
                    $elapsed = microtime(true) - $t1;
                    if ($elapsed > 0.01) {
                        echo "[SLOW HOOK on $tag] $cb_name took " . number_format($elapsed, 4) . "s" . PHP_EOL;
                    }
                    return $res;
                };
            }
        }
    }
}

echo "Starting update_status('processing')..." . PHP_EOL;
$t0 = microtime(true);
$order->update_status('processing', 'Profile Test');
echo "Done in " . number_format(microtime(true) - $t0, 4) . "s" . PHP_EOL;

$order->delete(true);
