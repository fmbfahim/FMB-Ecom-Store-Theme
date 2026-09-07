<?php
$f2027 = 'c:/xampp/htdocs/fmbecstore/wp-content/themes/fmb-ecom-store 2027/woocommerce/single-product.php';
$fTarget = 'c:/xampp/htdocs/fmbecstore/wp-content/themes/fmb-ecom-store/woocommerce/single-product.php';

$c2027 = file_get_contents($f2027);
$cTarget = file_get_contents($fTarget);

$lines2027 = explode("\n", $c2027);
$linesTarget = explode("\n", $cTarget);

echo "2027 last 100 lines:\n";
for ($i = max(0, count($lines2027) - 100); $i < count($lines2027); $i++) {
    echo "$i: " . substr($lines2027[$i], 0, 80) . "\n";
}
