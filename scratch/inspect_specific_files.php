<?php
$f2027 = 'c:/xampp/htdocs/fmbecstore/wp-content/themes/fmb-ecom-store 2027/';
$fTarget = 'c:/xampp/htdocs/fmbecstore/wp-content/themes/fmb-ecom-store/';

echo "=== CHECKOUT FORM DIFF ===\n";
echo "--- 2027 checkout (lines 1 to 50) ---\n";
$c2027_chk = file($f2027 . 'woocommerce/checkout/form-checkout.php');
for ($i = 0; $i < min(50, count($c2027_chk)); $i++) {
    echo $c2027_chk[$i];
}
echo "\n--- Target checkout (entire file) ---\n";
echo file_get_contents($fTarget . 'woocommerce/checkout/form-checkout.php');

echo "\n\n=== FOOTER DIFF ===\n";
$foot2027 = file_get_contents($f2027 . 'footer.php');
$footTarget = file_get_contents($fTarget . 'footer.php');
// Find what's after line 190 in 2027 footer
$foot2027_lines = file($f2027 . 'footer.php');
echo "2027 footer lines 180 to 260:\n";
for ($i = 180; $i < min(260, count($foot2027_lines)); $i++) {
    echo $foot2027_lines[$i];
}

echo "\n\n=== NAVBAR DIFF ===\n";
$nav2027_lines = file($f2027 . 'template-parts/header/navbar.php');
echo "2027 navbar lines 350 to end:\n";
for ($i = 350; $i < count($nav2027_lines); $i++) {
    echo $nav2027_lines[$i];
}
