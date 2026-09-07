<?php
$f2027_css = 'c:/xampp/htdocs/fmbecstore/wp-content/themes/fmb-ecom-store 2027/assets/css/main.css';
$fTarget_css = 'c:/xampp/htdocs/fmbecstore/wp-content/themes/fmb-ecom-store/assets/css/main.css';

$c2027 = file_get_contents($f2027_css);
$cTarget = file_get_contents($fTarget_css);

// Find where they diverge or what is at the end of 2027
$pos = strpos($c2027, 'fmbTruckDropDrive');
if ($pos !== false) {
    // Show 200 chars before and 800 chars after
    $start = max(0, $pos - 200);
    echo "Around fmbTruckDropDrive in 2027:\n" . substr($c2027, $start, 1500) . "\n";
}
