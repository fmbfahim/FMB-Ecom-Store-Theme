<?php
$f2027 = 'c:/xampp/htdocs/fmbecstore/wp-content/themes/fmb-ecom-store 2027/functions.php';
$fTarget = 'c:/xampp/htdocs/fmbecstore/wp-content/themes/fmb-ecom-store/functions.php';

$c2027 = file_get_contents($f2027);
$cTarget = file_get_contents($fTarget);

$functions_to_add = [
    'fmb_force_classic_cart_checkout',
    'fmb_enable_webp_upload_mimes',
    'fmb_custom_checkout_fields',
    'fmb_custom_default_address_fields',
    'fmb_remove_billing_details_heading',
    'fmb_update_checkout_cart',
    'fmb_get_rich_cart_data',
    'fmb_ajax_live_search',
    'fmb_combo_offer_landing_redirect'
];

$code_blocks = array();

// Extract WebP support block
$pos_webp = strpos($c2027, 'function fmb_enable_webp_upload_mimes');
if ($pos_webp !== false) {
    // find up to next section
    $pos_end_webp = strpos($c2027, 'function fmb_custom_checkout_fields');
    if ($pos_end_webp !== false) {
        $code_blocks['webp'] = substr($c2027, $pos_webp - 60, $pos_end_webp - ($pos_webp - 60));
    }
}

// Extract Classic Cart & Checkout Fix block
$pos_classic = strpos($c2027, 'function fmb_force_classic_cart_checkout');
if ($pos_classic !== false) {
    $pos_end_classic = strpos($c2027, 'add_action(\'admin_init\', \'fmb_force_classic_cart_checkout\');');
    if ($pos_end_classic !== false) {
        $end_len = strlen('add_action(\'admin_init\', \'fmb_force_classic_cart_checkout\');');
        $code_blocks['classic'] = substr($c2027, $pos_classic, ($pos_end_classic + $end_len) - $pos_classic);
    }
}

// Extract Checkout Fields & AJAX Cart Update block
$pos_chk_fields = strpos($c2027, 'function fmb_custom_checkout_fields');
if ($pos_chk_fields !== false) {
    $pos_end_chk_fields = strpos($c2027, 'function fmb_ajax_live_search');
    if ($pos_end_chk_fields !== false) {
        $code_blocks['chk_fields'] = substr($c2027, $pos_chk_fields, $pos_end_chk_fields - $pos_chk_fields);
    }
}

// Extract Live Search AJAX handler
$pos_search = strpos($c2027, 'function fmb_ajax_live_search');
if ($pos_search !== false) {
    // find end of fmb_ajax_live_search block
    $pos_end_search = strpos($c2027, 'function fmb_combo_offer_landing_redirect');
    if ($pos_end_search !== false) {
        $code_blocks['live_search'] = substr($c2027, $pos_search, $pos_end_search - $pos_search);
    } else {
        $code_blocks['live_search'] = substr($c2027, $pos_search);
    }
}

echo "Extracted blocks:\n";
foreach ($code_blocks as $k => $block) {
    echo "- $k: " . strlen($block) . " bytes\n";
}

// Append to Target if not already present
$to_append = "\n\n// ==========================================================================\n// === FEATURES BROUGHT FROM 2027 THEME ===\n// ==========================================================================\n";

foreach ($code_blocks as $k => $block) {
    // Check if function already in Target
    $func_name = '';
    if (preg_match('/function\s+([a-zA-Z0-9_]+)/', $block, $m)) {
        $func_name = $m[1];
    }
    if ($func_name && strpos($cTarget, "function $func_name") === false) {
        $to_append .= "\n" . trim($block) . "\n";
        echo "Adding block: $k ($func_name)\n";
    } else {
        echo "Skipping block $k (already exists in target: $func_name)\n";
    }
}

if (strlen($to_append) > 200) {
    file_put_contents($fTarget, $cTarget . $to_append);
    echo "Updated Target functions.php successfully!\n";
} else {
    echo "Nothing new to append.\n";
}
