<?php
echo '=== AJAX Handler Check ===' . PHP_EOL;
$content = file_get_contents('c:/xampp/htdocs/fmbecstore/wp-content/themes/fmb-ecom-store/functions.php');
echo 'fmb_ajax_add_to_cart: ' . (strpos($content, 'function fmb_ajax_add_to_cart') !== false ? 'OK' : 'MISSING') . PHP_EOL;
echo 'wp_ajax_fmb_ajax_add_to_cart: ' . (strpos($content, 'wp_ajax_fmb_ajax_add_to_cart') !== false ? 'OK' : 'MISSING') . PHP_EOL;
echo 'Bengali notices filter: ' . (strpos($content, 'woocommerce_add_to_cart_message_html') !== false ? 'OK' : 'MISSING') . PHP_EOL;

echo PHP_EOL . '=== Sticky Cart Logic Check ===' . PHP_EOL;
$footer = file_get_contents('c:/xampp/htdocs/fmbecstore/wp-content/themes/fmb-ecom-store/footer.php');
echo 'IntersectionObserver: ' . (strpos($footer, 'IntersectionObserver') !== false ? 'OK' : 'MISSING') . PHP_EOL;
echo 'fmb-hide-sticky class: ' . (strpos($footer, 'fmb-hide-sticky') !== false ? 'OK' : 'MISSING') . PHP_EOL;
echo 'fmbSetupCheckoutObserver: ' . (strpos($footer, 'fmbSetupCheckoutObserver') !== false ? 'OK' : 'MISSING') . PHP_EOL;
echo 'fmb-card-atc-btn handler: ' . (strpos($footer, 'fmb-card-atc-btn') !== false ? 'OK' : 'MISSING') . PHP_EOL;
echo 'fmb-submit-btn styled: ' . (strpos($footer, 'fmb-submit-btn') !== false ? 'OK' : 'MISSING') . PHP_EOL;

echo PHP_EOL . '=== Customizer CSS Variables ===' . PHP_EOL;
$cust = file_get_contents('c:/xampp/htdocs/fmbecstore/wp-content/themes/fmb-ecom-store/inc/customizer.php');
echo 'fmb_btn_bg_color control: ' . (strpos($cust, 'fmb_btn_bg_color') !== false ? 'OK' : 'MISSING') . PHP_EOL;
echo 'fmb_btn_text_color control: ' . (strpos($cust, 'fmb_btn_text_color') !== false ? 'OK' : 'MISSING') . PHP_EOL;
echo 'fmb_btn_hover_color control: ' . (strpos($cust, 'fmb_btn_hover_color') !== false ? 'OK' : 'MISSING') . PHP_EOL;
echo '--btn-bg CSS var: ' . (strpos($cust, '--btn-bg') !== false ? 'OK' : 'MISSING') . PHP_EOL;
echo '--btn-text CSS var: ' . (strpos($cust, '--btn-text') !== false ? 'OK' : 'MISSING') . PHP_EOL;
echo 'fmb-hide-sticky CSS rule: ' . (strpos($cust, 'fmb-hide-sticky') !== false ? 'OK' : 'MISSING') . PHP_EOL;
echo 'woocommerce-notices styled: ' . (strpos($cust, 'woocommerce-notices-wrapper') !== false ? 'OK' : 'MISSING') . PHP_EOL;
echo 'woocommerce-message styled: ' . (strpos($cust, 'woocommerce-message') !== false ? 'OK' : 'MISSING') . PHP_EOL;

echo PHP_EOL . '=== Single Product ATC ===' . PHP_EOL;
$sp = file_get_contents('c:/xampp/htdocs/fmbecstore/wp-content/themes/fmb-ecom-store/woocommerce/single-product.php');
echo 'fmbHandleSingleAddToCart: ' . (strpos($sp, 'fmbHandleSingleAddToCart') !== false ? 'OK' : 'MISSING') . PHP_EOL;
echo 'event delegation: ' . (strpos($sp, 'e.target.closest') !== false ? 'OK' : 'MISSING') . PHP_EOL;
echo 'fetch AJAX: ' . (strpos($sp, 'fmb_ajax_add_to_cart') !== false ? 'OK' : 'MISSING') . PHP_EOL;
echo 'mobile sticky CTA (fmb-order-now-btn): ' . (strpos($sp, 'fmb-order-now-btn') !== false ? 'OK' : 'MISSING') . PHP_EOL;

echo PHP_EOL . '=== Content-Product Buttons ===' . PHP_EOL;
$cp = file_get_contents('c:/xampp/htdocs/fmbecstore/wp-content/themes/fmb-ecom-store/woocommerce/content-product.php');
echo 'fmb-card-atc-btn: ' . (strpos($cp, 'fmb-card-atc-btn') !== false ? 'OK' : 'MISSING') . PHP_EOL;
echo 'qs-order-popup-trigger: ' . (strpos($cp, 'qs-order-popup-trigger') !== false ? 'OK' : 'MISSING') . PHP_EOL;
echo 'var(--btn-bg on order btn: ' . (strpos($cp, 'var(--btn-bg') !== false ? 'OK' : 'MISSING') . PHP_EOL;

echo PHP_EOL . '=== Variant Templates ===' . PHP_EOL;
$variants = ['variant-classic.php', 'variant-fomo-deal.php', 'variant-minimalist.php', 'variant-dark-luxe.php'];
foreach ($variants as $f) {
    $v = file_get_contents('c:/xampp/htdocs/fmbecstore/wp-content/themes/fmb-ecom-store/template-parts/single-product/' . $f);
    $has_order_cls = strpos($v, 'fmb-order-now-btn') !== false;
    $has_atc_id    = strpos($v, 'fmb-add-to-cart-btn') !== false;
    $has_atc_cls   = strpos($v, 'fmb-single-atc-btn') !== false;
    $has_btn_var   = strpos($v, 'var(--btn-bg') !== false || strpos($v, 'fmb-order-now-btn') !== false;
    echo $f . ': order_class=' . ($has_order_cls ? 'OK' : 'MISSING') . ', atc_id=' . ($has_atc_id ? 'OK' : 'MISSING') . ', atc_class=' . ($has_atc_cls ? 'OK' : 'MISSING') . ', dynamic_color=' . ($has_btn_var ? 'OK' : 'MISSING') . PHP_EOL;
}
echo PHP_EOL . 'All checks complete.' . PHP_EOL;
