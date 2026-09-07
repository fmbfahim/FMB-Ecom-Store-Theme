<?php
require 'c:/xampp/htdocs/fmbecstore/wp-load.php';

$rules = get_option('rewrite_rules');
echo "Searching rewrite rules before flush:\n";
$found = 0;
if (is_array($rules)) {
    foreach ($rules as $k => $v) {
        if (strpos($k, 'sales-page') !== false || strpos($v, 'sales_page') !== false || strpos($k, 'combo-offer') !== false) {
            echo "$k => $v\n";
            $found++;
        }
    }
}
echo "Found $found rules.\n";

if ($found === 0) {
    echo "Flushing rewrite rules now...\n";
    flush_rewrite_rules();
    echo "Flushed!\n";
    $new_rules = get_option('rewrite_rules');
    foreach ($new_rules as $k => $v) {
        if (strpos($k, 'sales-page') !== false || strpos($v, 'sales_page') !== false || strpos($k, 'combo-offer') !== false) {
            echo "$k => $v\n";
        }
    }
}
