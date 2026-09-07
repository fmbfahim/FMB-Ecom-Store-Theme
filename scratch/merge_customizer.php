<?php
$f2027 = 'c:/xampp/htdocs/fmbecstore/wp-content/themes/fmb-ecom-store 2027/inc/customizer.php';
$fTarget = 'c:/xampp/htdocs/fmbecstore/wp-content/themes/fmb-ecom-store/inc/customizer.php';

$c2027 = file_get_contents($f2027);
$cTarget = file_get_contents($fTarget);

// Extract section fmb_homepage_hero to end of customizer function
$pos_start = strpos($c2027, "\$wp_customize->add_section('fmb_homepage_hero'");
if ($pos_start !== false) {
    // find end of fmb_customize_register in 2027
    $end_pos = strrpos($c2027, '}');
    $extra_code = substr($c2027, $pos_start, $end_pos - $pos_start);
    echo "Extracted extra customizer code: " . strlen($extra_code) . " bytes\n";
    
    // Check if already in target
    if (strpos($cTarget, 'fmb_homepage_hero') === false) {
        // Insert right before the closing brace of fmb_customize_register in target
        $target_end = strrpos($cTarget, '}');
        $newTarget = substr($cTarget, 0, $target_end) . "\n\n    // === HOMEPAGE HERO & CUSTOMER REVIEWS (FROM 2027) ===\n    " . $extra_code . "\n}\n" . substr($cTarget, $target_end + 1);
        file_put_contents($fTarget, $newTarget);
        echo "Successfully merged fmb_homepage_hero & fmb_homepage_reviews into Target inc/customizer.php!\n";
    } else {
        echo "Already present in Target.\n";
    }
} else {
    echo "Could not find fmb_homepage_hero in 2027.\n";
}
