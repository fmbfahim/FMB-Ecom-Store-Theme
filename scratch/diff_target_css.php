<?php
$f2027_css = 'c:/xampp/htdocs/fmbecstore/wp-content/themes/fmb-ecom-store 2027/assets/css/main.css';
$fTarget_css = 'c:/xampp/htdocs/fmbecstore/wp-content/themes/fmb-ecom-store/assets/css/main.css';

$c2027 = file_get_contents($f2027_css);
$cTarget = file_get_contents($fTarget_css);

$lines2027 = explode("\n", $c2027);
$linesTarget = explode("\n", $cTarget);

$diffTarget = array_diff($linesTarget, $lines2027);
echo "Lines in Target not in 2027 count: " . count($diffTarget) . "\n";
echo "Sample:\n" . implode("\n", array_slice($diffTarget, 0, 30)) . "\n";
