<?php
$src = 'c:/xampp/htdocs/fmbecstore/wp-content/themes/fmb-ecom-store';
$dst = 'D:/FMB/FMB-Theme/fmb-ecom-store1.3.0';

$files_to_sync = [
    'front-page.php',
    'functions.php',
    'woocommerce/checkout/form-checkout.php',
    'woocommerce/cart/cart.php',
    'woocommerce/cart/cart-totals.php',
    'inc/customizer.php',
    'footer.php',
    'template-parts/header/navbar.php',
    'template-parts/header/topbar.php',
    'page.php',
    'assets/css/main.css',
    'inc/fmb-engine/assets/css/incomplete.css',
    'inc/fmb-engine/assets/css/setting.css',
    'inc/fmb-engine/courier/class-fmb-courier-db.php',
    'inc/admin-pages/order-manager.php',
    'inc/admin-pages/courier-dashboard.php'
];

foreach ($files_to_sync as $rel) {
    $s = "$src/$rel";
    $d = "$dst/$rel";
    if (file_exists($s)) {
        $d_dir = dirname($d);
        if (!is_dir($d_dir)) {
            mkdir($d_dir, 0777, true);
        }
        copy($s, $d);
        echo "Synced $rel\n";
    }
}

echo "Backup sync completed successfully!\n";
