<?php
$dir_2027 = 'c:/xampp/htdocs/fmbecstore/wp-content/themes/fmb-ecom-store 2027';

$all_files = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir_2027));
foreach ($it as $file) {
    if ($file->isDir()) continue;
    $rel = substr($file->getPathname(), strlen($dir_2027) + 1);
    if (strpos($rel, 'scratch') !== false || strpos($rel, '.git') !== false) continue;
    if (preg_match('/(cart|checkout|shop|product)/i', $rel)) {
        $all_files[] = $rel;
    }
}
echo "Files related to cart/checkout/shop/product in 2027:\n";
foreach ($all_files as $f) {
    echo "- $f (" . filesize("$dir_2027/$f") . " bytes)\n";
}
