<?php
$files = [
    'functions.php',
    'single-fmb_combo_offer.php',
    'archive-fmb_combo_offer.php',
    'single-fmb_sales_page.php',
    'woocommerce/archive-product.php',
    'inc/admin-panel.php',
    'inc/customizer.php',
    'inc/combo-manager.php',
    'inc/sales-funnel-manager.php',
    'inc/fmb-engine/incomplete-orders-tracker.php',
    'inc/fmb-engine/admin/incomplete-orders-dashboard.php',
    'template-parts/header/navbar.php',
    'template-parts/footer/site-footer.php'
];

$source = 'c:/xampp/htdocs/fmbecstore/wp-content/themes/fmb-ecom-store/';
$targets = [
    'c:/xampp/htdocs/fmbecstore/wp-content/themes/fmb-ecom-store 2027/',
    'd:/FMB/FMB-Theme/fmb-ecom-store1.3.0/'
];

foreach ($targets as $t) {
    if (!is_dir($t)) {
        echo "Target dir does not exist: $t\n";
        continue;
    }
    echo "Syncing to $t:\n";
    foreach ($files as $f) {
        $src_file = $source . $f;
        $dst_file = $t . $f;
        $dst_dir = dirname($dst_file);
        if (!is_dir($dst_dir)) {
            mkdir($dst_dir, 0777, true);
        }
        if (file_exists($src_file)) {
            copy($src_file, $dst_file);
            echo "  Copied $f\n";
        } else {
            echo "  Source not found: $f\n";
        }
    }
}

echo "Sync completed!\n";
