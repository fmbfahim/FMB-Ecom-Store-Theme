<?php
$f2027 = 'c:/xampp/htdocs/fmbecstore/wp-content/themes/fmb-ecom-store 2027/inc/customizer.php';
$content = file_get_contents($f2027);

$pos1 = strpos($content, "'fmb_homepage_hero'");
if ($pos1 !== false) {
    echo "=== fmb_homepage_hero ===\n";
    echo substr($content, $pos1 - 20, 1500) . "\n\n";
}

$pos2 = strpos($content, "'fmb_homepage_reviews'");
if ($pos2 !== false) {
    echo "=== fmb_homepage_reviews ===\n";
    echo substr($content, $pos2 - 20, 1500) . "\n\n";
}
