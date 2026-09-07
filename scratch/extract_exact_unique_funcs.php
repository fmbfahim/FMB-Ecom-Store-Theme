<?php
$f2027 = 'c:/xampp/htdocs/fmbecstore/wp-content/themes/fmb-ecom-store 2027/functions.php';
$c2027 = file_get_contents($f2027);

$target_funcs = [
    'fmb_enable_webp_upload_mimes',
    'fmb_check_webp_filetype_and_ext',
    'fmb_displayable_image_webp',
    'fmb_force_classic_cart_checkout',
    'fmb_custom_checkout_fields',
    'fmb_custom_default_address_fields',
    'fmb_remove_billing_details_heading',
    'fmb_update_checkout_cart',
    'fmb_get_rich_cart_data',
    'fmb_ajax_live_search',
];

$extracted = [];

foreach ($target_funcs as $fn) {
    $pos = strpos($c2027, "function $fn");
    if ($pos === false) {
        echo "NOT FOUND: $fn\n";
        continue;
    }
    
    // Find preceding comment if any
    $start_pos = $pos;
    $prev_newline = strrpos(substr($c2027, 0, $pos), "\n//");
    if ($prev_newline !== false && ($pos - $prev_newline) < 150) {
        $start_pos = $prev_newline + 1;
    }
    
    // Find the end of function: matching braces
    $brace_start = strpos($c2027, '{', $pos);
    if ($brace_start === false) continue;
    
    $len = strlen($c2027);
    $depth = 0;
    $func_end = $brace_start;
    for ($i = $brace_start; $i < $len; $i++) {
        if ($c2027[$i] === '{') $depth++;
        else if ($c2027[$i] === '}') {
            $depth--;
            if ($depth === 0) {
                $func_end = $i + 1;
                break;
            }
        }
    }
    
    // Now look ahead for associated add_action or add_filter
    $after_func = substr($c2027, $func_end, 500);
    $extra_end = $func_end;
    // Check if add_action/filter refers to this func
    $pattern = '/^(\s*(add_action|add_filter)\s*\(\s*[\'"][a-zA-Z0-9_\-]+[\'"]\s*,\s*[\'"]' . $fn . '[\'"].*?\);)/s';
    if (preg_match($pattern, $after_func, $m)) {
        $extra_end = $func_end + strlen($m[1]);
        // Also check if there is a nopriv hook or second hook
        $after_func2 = substr($c2027, $extra_end, 500);
        if (preg_match($pattern, $after_func2, $m2)) {
            $extra_end += strlen($m2[1]);
        }
    }
    
    $code = trim(substr($c2027, $start_pos, $extra_end - $start_pos));
    $extracted[$fn] = $code;
    echo "Extracted $fn (" . strlen($code) . " bytes)\n";
}

// Write preview
file_put_contents('c:/xampp/htdocs/fmbecstore/wp-content/themes/fmb-ecom-store/scratch/preview_extracted.php', "<?php\n" . implode("\n\n", $extracted));
echo "Preview saved.\n";
