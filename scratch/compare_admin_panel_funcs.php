<?php
$f2027 = 'c:/xampp/htdocs/fmbecstore/wp-content/themes/fmb-ecom-store 2027/inc/admin-panel.php';
$fTarget = 'c:/xampp/htdocs/fmbecstore/wp-content/themes/fmb-ecom-store/inc/admin-panel.php';

$c2027 = file_get_contents($f2027);
$cTarget = file_get_contents($fTarget);

// Find functions declared in 2027 admin-panel.php
preg_match_all('/function\s+([a-zA-Z0-9_]+)/', $c2027, $m2027);
preg_match_all('/function\s+([a-zA-Z0-9_]+)/', $cTarget, $mTarget);

$funcs2027 = array_unique($m2027[1]);
$funcsTarget = array_unique($mTarget[1]);

echo "2027 admin-panel functions:\n";
foreach ($funcs2027 as $fn) {
    echo "  - $fn (" . (in_array($fn, $funcsTarget) ? 'in target' : 'MISSING IN TARGET') . ")\n";
}
