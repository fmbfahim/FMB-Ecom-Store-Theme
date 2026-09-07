<?php
$f2027_css = 'c:/xampp/htdocs/fmbecstore/wp-content/themes/fmb-ecom-store 2027/assets/css/main.css';
$fTarget_css = 'c:/xampp/htdocs/fmbecstore/wp-content/themes/fmb-ecom-store/assets/css/main.css';

$c2027 = file_exists($f2027_css) ? file_get_contents($f2027_css) : '';
$cTarget = file_exists($fTarget_css) ? file_get_contents($fTarget_css) : '';

echo "CSS 2027 length: " . strlen($c2027) . " bytes\n";
echo "CSS Target length: " . strlen($cTarget) . " bytes\n";

// Check keyframes fmbTruckDropDrive
if (strpos($c2027, 'fmbTruckDropDrive') !== false) {
    echo "2027 has fmbTruckDropDrive: yes\n";
} else {
    echo "2027 has fmbTruckDropDrive: no\n";
}

if (strpos($cTarget, 'fmbTruckDropDrive') !== false) {
    echo "Target has fmbTruckDropDrive: yes\n";
} else {
    echo "Target has fmbTruckDropDrive: no\n";
}

// Check other CSS files in assets/css/
$dir2027_css = 'c:/xampp/htdocs/fmbecstore/wp-content/themes/fmb-ecom-store 2027/assets/css';
$dirTarget_css = 'c:/xampp/htdocs/fmbecstore/wp-content/themes/fmb-ecom-store/assets/css';

echo "Files in 2027 assets/css:\n";
foreach (glob("$dir2027_css/*") as $f) {
    echo "  - " . basename($f) . " (" . filesize($f) . "b)\n";
}

echo "Files in Target assets/css:\n";
foreach (glob("$dirTarget_css/*") as $f) {
    echo "  - " . basename($f) . " (" . filesize($f) . "b)\n";
}
