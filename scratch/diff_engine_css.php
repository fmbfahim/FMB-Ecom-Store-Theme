<?php
$f2027 = 'c:/xampp/htdocs/fmbecstore/wp-content/themes/fmb-ecom-store 2027/inc/fmb-engine/assets/css';
$fTarget = 'c:/xampp/htdocs/fmbecstore/wp-content/themes/fmb-ecom-store/inc/fmb-engine/assets/css';

foreach (['incomplete.css', 'setting.css'] as $file) {
    $c2027 = file_get_contents("$f2027/$file");
    $cTarget = file_get_contents("$fTarget/$file");
    
    echo "=== $file ===\n";
    echo "2027 length: " . strlen($c2027) . ", Target length: " . strlen($cTarget) . "\n";
    
    // Check diff
    $lines2027 = explode("\n", $c2027);
    $linesTarget = explode("\n", $cTarget);
    
    $diff2027 = array_diff($lines2027, $linesTarget);
    echo "Lines in 2027 not in Target: " . count($diff2027) . "\n";
    echo "Sample:\n" . implode("\n", array_slice($diff2027, 0, 15)) . "\n";
}
