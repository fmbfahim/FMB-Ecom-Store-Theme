<?php
$f2027 = 'c:/xampp/htdocs/fmbecstore/wp-content/themes/fmb-ecom-store 2027/woocommerce/single-product.php';
$lines = file($f2027);

echo "2027 single-product.php lines 600 to 755:\n";
for ($i = 600; $i < count($lines); $i++) {
    echo ($i+1) . ": " . $lines[$i];
}
