<?php
$f2027_css = 'c:/xampp/htdocs/fmbecstore/wp-content/themes/fmb-ecom-store 2027/assets/css/main.css';
$fTarget_css = 'c:/xampp/htdocs/fmbecstore/wp-content/themes/fmb-ecom-store/assets/css/main.css';

$c2027 = file_get_contents($f2027_css);
$cTarget = file_get_contents($fTarget_css);

// If we append the truck block to target, what else is different?
$cTargetWithTruck = str_replace(':root {', $truck_block . "\n:root {", $cTarget);

// Compare lengths and non-whitespace differences
echo "2027 length: " . strlen($c2027) . "\n";
echo "Target length: " . strlen($cTarget) . "\n";

// Let's check diff lines
$lines2027 = explode("\n", $c2027);
$linesTarget = explode("\n", $cTarget);

$diff = array_diff($lines2027, $linesTarget);
echo "Lines in 2027 but not in Target count: " . count($diff) . "\n";
echo "Sample lines in 2027 not in Target:\n";
echo implode("\n", array_slice($diff, 0, 30)) . "\n";
