<?php
$source_dir = 'c:/xampp/htdocs/fmbecstore/wp-content/themes/fmb-ecom-store';
$targets = [
    'c:/xampp/htdocs/fmbecstore/wp-content/themes/fmb-ecom-store 2027',
    'D:/FMB/FMB-Theme/fmb-ecom-store1.3.0'
];

$files = [
    'woocommerce/content-product.php',
    'woocommerce/archive-product.php',
    'woocommerce/cart/cart-empty.php',
    'woocommerce/cart/cart.php',
    'woocommerce/cart/cart-totals.php',
    'woocommerce/checkout/form-checkout.php',
    'functions.php'
];

foreach ($targets as $target) {
    echo "=== SYNCING TO: $target ===\n";
    if (!is_dir($target)) {
        echo "Target directory does not exist, skipping.\n";
        continue;
    }
    
    foreach ($files as $rel) {
        $src = "$source_dir/$rel";
        $dst = "$target/$rel";
        
        if (!file_exists($src)) {
            echo "Source file missing: $rel\n";
            continue;
        }
        
        $dst_dir = dirname($dst);
        if (!is_dir($dst_dir)) {
            mkdir($dst_dir, 0777, true);
        }
        
        if (copy($src, $dst)) {
            echo "[COPIED] $rel (" . filesize($dst) . " bytes)\n";
        } else {
            echo "[FAILED] $rel\n";
        }
    }
}
echo "SYNC COMPLETE!\n";
