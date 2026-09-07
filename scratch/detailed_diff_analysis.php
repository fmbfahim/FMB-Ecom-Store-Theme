<?php
$f2027 = 'c:/xampp/htdocs/fmbecstore/wp-content/themes/fmb-ecom-store 2027/';
$fTarget = 'c:/xampp/htdocs/fmbecstore/wp-content/themes/fmb-ecom-store/';

echo "=== 1. front-page.php ===\n";
$fp2027 = file_get_contents($f2027 . 'front-page.php');
$fpTarget = file_get_contents($fTarget . 'front-page.php');
echo "2027 has sections: \n";
preg_match_all('/<!--\s*([^-]+)\s*-->/', $fp2027, $m1);
print_r(array_unique(array_map('trim', $m1[1])));
echo "Target has sections: \n";
preg_match_all('/<!--\s*([^-]+)\s*-->/', $fpTarget, $m2);
print_r(array_unique(array_map('trim', $m2[1])));

echo "\n=== 2. woocommerce/checkout/form-checkout.php ===\n";
$chk2027 = file_get_contents($f2027 . 'woocommerce/checkout/form-checkout.php');
$chkTarget = file_get_contents($fTarget . 'woocommerce/checkout/form-checkout.php');
echo "2027 checkout length: " . strlen($chk2027) . " | Target: " . strlen($chkTarget) . "\n";
echo "2027 keywords: \n";
foreach (['otp', 'fraud', 'partial', 'delivery', 'city', 'district', 'summary', 'coupon', 'upsell', 'rush', 'guarantee'] as $kw) {
    echo "  $kw: 2027=" . substr_count(strtolower($chk2027), $kw) . ", Target=" . substr_count(strtolower($chkTarget), $kw) . "\n";
}

echo "\n=== 3. inc/customizer.php ===\n";
$cust2027 = file_get_contents($f2027 . 'inc/customizer.php');
$custTarget = file_get_contents($fTarget . 'inc/customizer.php');
preg_match_all('/add_section\(\s*[\'"]([^\'"]+)[\'"]/', $cust2027, $cs1);
preg_match_all('/add_section\(\s*[\'"]([^\'"]+)[\'"]/', $custTarget, $cs2);
echo "2027 Sections: " . implode(', ', $cs1[1]) . "\n";
echo "Target Sections: " . implode(', ', $cs2[1]) . "\n";

echo "\n=== 4. footer.php ===\n";
$foot2027 = file_get_contents($f2027 . 'footer.php');
$footTarget = file_get_contents($fTarget . 'footer.php');
echo "2027 footer length: " . strlen($foot2027) . " | Target: " . strlen($footTarget) . "\n";

echo "\n=== 5. functions.php ===\n";
$fn2027 = file_get_contents($f2027 . 'functions.php');
$fnTarget = file_get_contents($fTarget . 'functions.php');
preg_match_all('/function\s+([a-zA-Z0-9_]+)\s*\(/', $fn2027, $fnm1);
preg_match_all('/function\s+([a-zA-Z0-9_]+)\s*\(/', $fnTarget, $fnm2);
$diff_funcs = array_diff($fnm1[1], $fnm2[1]);
echo "Functions in 2027 but NOT in Target: " . implode(', ', $diff_funcs) . "\n";
$diff_funcs_reverse = array_diff($fnm2[1], $fnm1[1]);
echo "Functions in Target but NOT in 2027: " . implode(', ', $diff_funcs_reverse) . "\n";

echo "\n=== 6. inc/fmb-engine/init.php ===\n";
$init2027 = file_get_contents($f2027 . 'inc/fmb-engine/init.php');
$initTarget = file_get_contents($fTarget . 'inc/fmb-engine/init.php');
preg_match_all('/require[_\s]+once[^\'"]*[\'"]([^\'"]+)[\'"]/', $init2027, $req1);
preg_match_all('/require[_\s]+once[^\'"]*[\'"]([^\'"]+)[\'"]/', $initTarget, $req2);
echo "Requires in 2027 but NOT in Target: " . implode(', ', array_diff($req1[1], $req2[1])) . "\n";
echo "Requires in Target but NOT in 2027: " . implode(', ', array_diff($req2[1], $req1[1])) . "\n";
