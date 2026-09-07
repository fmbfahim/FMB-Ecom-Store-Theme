<?php
$f1 = 'c:/xampp/htdocs/fmbecstore/wp-content/themes/fmb-ecom-store/woocommerce/content-product.php';
$f2 = 'c:/xampp/htdocs/fmbecstore/wp-content/themes/fmb-ecom-store 2027/woocommerce/content-product.php';

echo "=== ACTIVE CONTENT-PRODUCT.PHP ===\n";
echo file_get_contents($f1);
echo "\n\n=== 2027 CONTENT-PRODUCT.PHP ===\n";
echo file_get_contents($f2);
