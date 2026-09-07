<?php
$f2027 = 'c:/xampp/htdocs/fmbecstore/wp-content/themes/fmb-ecom-store 2027/inc/customizer.php';
$fTarget = 'c:/xampp/htdocs/fmbecstore/wp-content/themes/fmb-ecom-store/inc/customizer.php';

$c2027 = file_get_contents($f2027);

// Extract the fmb_customizer_css_output from 2027
$pos1 = strpos($c2027, 'function fmb_customizer_css_output');
echo "2027 css output:\n" . substr($c2027, $pos1) . "\n";
