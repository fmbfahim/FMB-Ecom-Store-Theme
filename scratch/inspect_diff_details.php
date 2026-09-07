<?php
$f2027 = 'c:/xampp/htdocs/fmbecstore/wp-content/themes/fmb-ecom-store 2027';
$fTarget = 'c:/xampp/htdocs/fmbecstore/wp-content/themes/fmb-ecom-store';

echo "=== topbar.php ===\n";
echo "2027:\n" . file_get_contents("$f2027/template-parts/header/topbar.php") . "\n";
echo "Target:\n" . file_get_contents("$fTarget/template-parts/header/topbar.php") . "\n";

echo "=== page.php ===\n";
echo "2027:\n" . file_get_contents("$f2027/page.php") . "\n";
echo "Target:\n" . file_get_contents("$fTarget/page.php") . "\n";
