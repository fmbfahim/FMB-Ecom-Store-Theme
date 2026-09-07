<?php
$fTarget_functions = 'c:/xampp/htdocs/fmbecstore/wp-content/themes/fmb-ecom-store/functions.php';
$content = file_get_contents($fTarget_functions);

// 1. Add require for combo-manager and sales-funnel-manager if not present
if (strpos($content, 'inc/combo-manager.php') === false) {
    $needle = "require_once get_template_directory() . '/inc/admin-panel.php';";
    $add = "\nrequire_once get_template_directory() . '/inc/combo-manager.php';\nrequire_once get_template_directory() . '/inc/sales-funnel-manager.php';";
    $content = str_replace($needle, $needle . $add, $content);
    echo "Added combo-manager and sales-funnel-manager requires to functions.php\n";
} else {
    echo "combo-manager already required in functions.php\n";
}

// 2. Add fmb_combo_offer_landing_redirect if not present
if (strpos($content, 'fmb_combo_offer_landing_redirect') === false) {
    $redirect_code = <<<'CODE'

// ========================================================
// 11. Combo Offer Landing Page & Archive Template Redirect
// ========================================================
function fmb_combo_offer_landing_redirect() {
    if ( isset( $_GET['fmb_all_combos'] ) && $_GET['fmb_all_combos'] == '1' ) {
        $archive_template = get_template_directory() . '/archive-fmb_combo_offer.php';
        if ( file_exists( $archive_template ) ) {
            include $archive_template;
            exit;
        }
    }
    if ( isset( $_GET['fmb_combo_id'] ) ) {
        $combo_id = intval( $_GET['fmb_combo_id'] );
        if ( $combo_id > 0 ) {
            $combo_post = get_post( $combo_id );
            if ( $combo_post && $combo_post->post_type === 'fmb_combo_offer' ) {
                global $post;
                $post = $combo_post;
                setup_postdata( $post );
                $single_template = get_template_directory() . '/single-fmb_combo_offer.php';
                if ( file_exists( $single_template ) ) {
                    include $single_template;
                    exit;
                }
            }
        }
    }
}
add_action( 'template_redirect', 'fmb_combo_offer_landing_redirect' );

CODE;

    $content .= "\n" . $redirect_code;
    echo "Added fmb_combo_offer_landing_redirect to functions.php\n";
} else {
    echo "fmb_combo_offer_landing_redirect already exists in functions.php\n";
}

file_put_contents($fTarget_functions, $content);

// 3. Copy missing files to 2027 so it never crashes
$files_to_copy_2027 = [
    'inc/combo-manager.php',
    'inc/sales-funnel-manager.php',
    'inc/custom-orders-manager.php',
    'inc/admin-pages/courier-dashboard.php',
    'inc/ai-product-generator.php',
    'single-fmb_combo_offer.php',
    'archive-fmb_combo_offer.php'
];

foreach ($files_to_copy_2027 as $rel) {
    $s = "c:/xampp/htdocs/fmbecstore/wp-content/themes/fmb-ecom-store/$rel";
    $d = "c:/xampp/htdocs/fmbecstore/wp-content/themes/fmb-ecom-store 2027/$rel";
    if (file_exists($s)) {
        $d_dir = dirname($d);
        if (!is_dir($d_dir)) mkdir($d_dir, 0777, true);
        copy($s, $d);
        echo "Copied to 2027: $rel\n";
    }
}

echo "All combo and admin fixes applied successfully!\n";
