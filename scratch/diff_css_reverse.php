<?php
$f2027 = 'c:/xampp/htdocs/fmbecstore/wp-content/themes/fmb-ecom-store 2027/inc/fmb-engine/assets/css';
$fTarget = 'c:/xampp/htdocs/fmbecstore/wp-content/themes/fmb-ecom-store/inc/fmb-engine/assets/css';

foreach (['incomplete.css', 'setting.css'] as $file) {
    $c2027 = file_get_contents("$f2027/$file");
    $cTarget = file_get_contents("$fTarget/$file");
    
    $lines2027 = explode("\n", $c2027);
    $linesTarget = explode("\n", $cTarget);
    
    $diffTarget = array_diff($linesTarget, $lines2027);
    echo "=== $file: Lines in Target not in 2027 (" . count($diffTarget) . ") ===\n";
    echo implode("\n", array_slice($diffTarget, 0, 15)) . "\n";
}
