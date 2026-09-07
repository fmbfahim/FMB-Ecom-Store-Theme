<?php
$f2027 = 'c:/xampp/htdocs/fmbecstore/wp-content/themes/fmb-ecom-store 2027/inc/customizer.php';
$fTarget = 'c:/xampp/htdocs/fmbecstore/wp-content/themes/fmb-ecom-store/inc/customizer.php';

// In 2027, the whole file is the full customizer registration + fmb_customizer_css_output!
// Let's check if 2027 customizer has everything target has + the new sections.
$c2027 = file_get_contents($f2027);

file_put_contents($fTarget, $c2027);
echo "Overwritten Target inc/customizer.php with complete 2027 customizer.\n";
