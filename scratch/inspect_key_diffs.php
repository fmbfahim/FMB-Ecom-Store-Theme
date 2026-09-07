<?php
$f2027 = 'c:/xampp/htdocs/fmbecstore/wp-content/themes/fmb-ecom-store 2027/';
$fTarget = 'c:/xampp/htdocs/fmbecstore/wp-content/themes/fmb-ecom-store/';

$check_files = array(
    'front-page.php',
    'woocommerce/single-product.php',
    'woocommerce/checkout/form-checkout.php',
    'inc/customizer.php',
    'footer.php',
    'template-parts/header/navbar.php',
    'functions.php',
    'inc/fmb-engine/init.php'
);

foreach ($check_files as $cf) {
    echo "=======================================================\n";
    echo "FILE: $cf\n";
    echo "=======================================================\n";
    $c2027 = file_get_contents($f2027 . $cf);
    $cTarget = file_get_contents($fTarget . $cf);
    
    // Check lines
    $lines2027 = explode("\n", $c2027);
    $linesTarget = explode("\n", $cTarget);
    echo "2027: " . count($lines2027) . " lines | Target: " . count($linesTarget) . " lines\n";
    
    // Sample head or differences
    echo "First 10 lines of 2027:\n";
    for ($i = 0; $i < min(10, count($lines2027)); $i++) {
        echo "  [2027] " . $lines2027[$i] . "\n";
    }
    echo "First 10 lines of Target:\n";
    for ($i = 0; $i < min(10, count($linesTarget)); $i++) {
        echo "  [Target] " . $linesTarget[$i] . "\n";
    }
    echo "\n";
}
