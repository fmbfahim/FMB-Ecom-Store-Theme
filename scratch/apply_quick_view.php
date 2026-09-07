<?php
$file = 'c:/xampp/htdocs/fmbecstore/wp-content/themes/fmb-ecom-store/functions.php';
$content = file_get_contents($file);

$search_start = "// 6. Quick View Fetcher";
$search_end = "remove_action('wp_head', 'wp_site_icon', 99);";

$pos_start = strpos($content, $search_start);
$pos_end = strpos($content, $search_end);

if ($pos_start !== false && $pos_end !== false) {
    $replacement = <<<'PHP'
// 6. Quick View Fetcher
add_action('wp_ajax_fmb_quick_view', 'fmb_ajax_quick_view');
add_action('wp_ajax_nopriv_fmb_quick_view', 'fmb_ajax_quick_view');

function fmb_ajax_quick_view() {
    $product_id = intval($_POST['product_id']);
    if (!$product_id) {
        wp_send_json_error();
    }

    $product = wc_get_product($product_id);
    if (!$product) {
        wp_send_json_error();
    }

    $active_price = (float) $product->get_price();
    $regular_price = (float) $product->get_regular_price();
    $discount_amount = 0;
    if ($product->is_on_sale() && $regular_price > $active_price) {
        $discount_amount = $regular_price - $active_price;
    }

    $custom_charges = get_option('cdc_product_delivery_charges', array());
    $custom_amt = isset($custom_charges[$product_id]) ? floatval($custom_charges[$product_id]) : -1;
    
    $link = get_permalink($product_id);
    $redirect_url = get_post_meta($product_id, '_landing_redirect_url', true);
    $is_redirect = get_post_meta($product_id, '_is_landing_redirect', true);
    if ( $is_redirect === 'yes' && !empty($redirect_url) ) {
        $link = $redirect_url;
    }

    // Start output buffer
    ob_start();
    ?>
    <div class="flex flex-col md:flex-row bg-white w-full">
        <!-- Product Image -->
        <div class="md:w-1/2 p-6 flex justify-center items-center bg-gray-50 border-b md:border-b-0 md:border-r border-gray-100">
            <?php 
            if ( has_post_thumbnail( $product_id ) ) {
                echo $product->get_image('large', array('class' => 'max-w-full h-auto object-contain max-h-[400px] rounded'));
            } else {
                echo '<img src="' . wc_placeholder_img_src() . '" alt="Placeholder" class="max-w-full h-auto object-contain max-h-[400px] rounded">';
            }
            ?>
        </div>
        
        <!-- Product Details -->
        <div class="md:w-1/2 p-6 md:p-8 flex flex-col justify-center">
            <h2 class="text-2xl font-bold text-gray-900 mb-2 leading-tight"><?php echo esc_html($product->get_name()); ?></h2>
            <div class="text-2xl font-extrabold text-primary mb-3">
                <?php echo $product->get_price_html(); ?>
            </div>
            
            <?php fmb_render_sales_proof_badge( $product_id, '', 'margin-bottom: 14px; width: fit-content;' ); ?>
            
            <div class="text-gray-600 text-sm mb-6 leading-relaxed max-h-32 overflow-y-auto pr-2 custom-scrollbar">
                <?php echo apply_filters('woocommerce_short_description', $product->get_short_description()); ?>
            </div>

            <div class="flex gap-4 mt-auto pt-4 border-t border-gray-100">
                <button type="button" class="qs-order-popup-trigger flex-1 bg-secondary hover:bg-primary text-white font-bold py-3 px-4 rounded transition-colors flex items-center justify-center gap-2"
                    data-product-id="<?php echo $product->get_id(); ?>"
                    data-price="<?php echo $active_price; ?>" 
                    data-discount="<?php echo $discount_amount; ?>"
                    data-custom-delivery="<?php echo esc_attr($custom_amt); ?>"
                    data-free-delivery="<?php echo fmb_is_effectively_free_delivery_catalog($product->get_id()) ? '1' : '0'; ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                    অর্ডার করুন
                </button>
                <a href="<?php echo esc_url($link); ?>" class="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-800 font-bold py-3 px-4 rounded transition-colors text-center flex items-center justify-center">
                    বিস্তারিত দেখুন
                </a>
            </div>
        </div>
    </div>
    <?php
    $html = ob_get_clean();

    wp_send_json_success(array('html' => $html));
}

PHP;
    $new_content = substr($content, 0, $pos_start) . $replacement . "\n\n" . substr($content, $pos_end);
    file_put_contents($file, $new_content);
    echo "Updated fmb_ajax_quick_view successfully!\n";
} else {
    echo "Could not find start/end markers.\n";
}
