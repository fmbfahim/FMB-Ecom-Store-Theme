<?php
$f2027 = 'c:/xampp/htdocs/fmbecstore/wp-content/themes/fmb-ecom-store 2027/woocommerce/single-product.php';
$fClassic = 'c:/xampp/htdocs/fmbecstore/wp-content/themes/fmb-ecom-store/template-parts/single-product/variant-classic.php';
$fTargetSingle = 'c:/xampp/htdocs/fmbecstore/wp-content/themes/fmb-ecom-store/woocommerce/single-product.php';

echo "Target Single Product lines 41 to 100:\n";
$target_lines = file($fTargetSingle);
for ($i = 40; $i < min(100, count($target_lines)); $i++) {
    echo ($i+1) . ": " . $target_lines[$i];
}
