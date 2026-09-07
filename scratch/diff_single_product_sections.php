<?php
$f2027 = 'c:/xampp/htdocs/fmbecstore/wp-content/themes/fmb-ecom-store 2027/woocommerce/single-product.php';
$fTarget = 'c:/xampp/htdocs/fmbecstore/wp-content/themes/fmb-ecom-store/woocommerce/single-product.php';

$c2027 = file_get_contents($f2027);
$cTarget = file_get_contents($fTarget);

// Look for HTML sections/tags in 2027 that are not in Target
preg_match_all('/<section[^>]*class=["\']([^"\']+)["\']|<div[^>]*class=["\']([^"\']+)["\']/i', $c2027, $m2027);
$classes2027 = array_unique(array_filter(array_merge($m2027[1], $m2027[2])));

preg_match_all('/<section[^>]*class=["\']([^"\']+)["\']|<div[^>]*class=["\']([^"\']+)["\']/i', $cTarget, $mTarget);
$classesTarget = array_unique(array_filter(array_merge($mTarget[1], $mTarget[2])));

$unique_in_2027 = array_diff($classes2027, $classesTarget);
echo "Classes in 2027 single-product not in Target (" . count($unique_in_2027) . "):\n";
foreach ($unique_in_2027 as $cls) {
    echo "  - $cls\n";
}

// Check where 2027 diverges
// Check line 500 onwards in 2027
$lines2027 = explode("\n", $c2027);
echo "\nLines 500 to 550 in 2027:\n";
for ($i = 500; $i < 550 && $i < count($lines2027); $i++) {
    echo "$i: " . $lines2027[$i] . "\n";
}
