<?php
$f2027_css = 'c:/xampp/htdocs/fmbecstore/wp-content/themes/fmb-ecom-store 2027/assets/css/main.css';
$fTarget_css = 'c:/xampp/htdocs/fmbecstore/wp-content/themes/fmb-ecom-store/assets/css/main.css';

$c2027 = file_get_contents($f2027_css);
$cTarget = file_get_contents($fTarget_css);

$pos_truck = strpos($c2027, '/* Delivery Truck Drop-Bounce');
if ($pos_truck !== false) {
    $pos_shop = strpos($c2027, '/* Shop Grid */');
    $truck_block = substr($c2027, $pos_truck, $pos_shop - $pos_truck);
    echo "Truck animation block length: " . strlen($truck_block) . "\n";
    echo $truck_block . "\n";
}
