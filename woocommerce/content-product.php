<?php
/**
 * Custom Product Loop Item (Modern 2027 Design)
 * Theme: FMB E-Com Store
 */

defined( 'ABSPATH' ) || exit;

global $product;

// প্রডাক্ট না থাকলে রিটার্ন করবে
if ( empty( $product ) || ! $product->is_visible() ) {
    return;
}

$product_id = $product->get_id();

// ১. কাস্টম ল্যান্ডিং বা রিডাইরেক্ট লিংক লজিক
$redirect_url = get_post_meta($product_id, '_landing_redirect_url', true);
$is_redirect  = get_post_meta($product_id, '_is_landing_redirect', true);

if ( $is_redirect === 'yes' && !empty($redirect_url) ) {
    $link = $redirect_url;
} else {
    $link = get_permalink();
}

// ২. প্রাইসিং ও ডিসকাউন্ট হিসাব
$regular_price   = (float) $product->get_regular_price();
$active_price    = (float) $product->get_price(); // সেল প্রাইস থাকলে সেল প্রাইস, না থাকলে রেগুলার প্রাইস
$discount_amount = 0;

if ( $product->is_on_sale() && $regular_price > $active_price ) {
    $discount_amount = $regular_price - $active_price;
}

// ৩. কাস্টম ডেলিভারি চার্জ
$custom_charges = get_option('cdc_product_delivery_charges', array());
$custom_amt     = isset($custom_charges[$product_id]) ? floatval($custom_charges[$product_id]) : -1;
$is_free_del    = function_exists('fmb_is_effectively_free_delivery_catalog') && fmb_is_effectively_free_delivery_catalog($product_id);
?>

<div <?php wc_product_class( 'fmb-card h-full flex flex-col bg-[#EEF5FF] border border-blue-100/70 rounded-2xl p-2.5 sm:p-3 shadow-sm hover:shadow-md transition-all duration-300 relative group', $product ); ?>>
    
    <!-- Product Image (Aspect Square with Zero Inner Padding & Zoom on Hover) -->
    <a href="<?php echo esc_url($link); ?>" class="fmb-card-img w-full aspect-square relative rounded-xl overflow-hidden block group/img shadow-sm bg-white">
        <?php 
        if ( has_post_thumbnail() ) {
            echo $product->get_image('woocommerce_thumbnail', array(
                'class'   => 'w-full h-full aspect-square object-cover rounded-xl transition-transform duration-500 group-hover/img:scale-105',
                'width'   => '300',
                'height'  => '300',
                'loading' => 'lazy'
            )); 
        } else {
            echo '<img src="' . esc_url(wc_placeholder_img_src()) . '" alt="' . esc_attr($product->get_name()) . '" width="300" height="300" class="w-full h-full aspect-square object-cover rounded-xl">';
        }
        ?>

        <!-- Sale Badge (Top Left) -->
        <?php if ( $product->is_on_sale() ) : ?>
             <span class="absolute top-2 left-2 bg-gradient-to-r from-red-600 to-rose-600 text-white text-[10px] font-extrabold px-2 py-0.5 rounded-full uppercase tracking-wider shadow-md z-10">
                 Sale
             </span>
        <?php endif; ?>

        <!-- Free Delivery Badge (Top Right) -->
        <?php if ( $is_free_del ) : ?>
             <span class="absolute top-2 right-2 bg-emerald-600 text-white text-[10px] font-extrabold px-2 py-0.5 rounded-full uppercase tracking-wider shadow-md z-10 flex items-center gap-1">
                 <span>🚚</span> ফ্রি ডেলিভারি
             </span>
        <?php endif; ?>
    </a>

    <!-- Product Title & Info -->
    <div class="mt-2.5 flex flex-col flex-grow text-center px-1">
        
        <h3 class="fmb-product-title-source font-bold text-gray-900 text-sm md:text-base leading-snug line-clamp-2 min-h-[38px] mb-1">
            <a href="<?php echo esc_url($link); ?>" class="hover:text-primary transition-colors">
                <?php echo esc_html(get_the_title()); ?>
            </a>
        </h3>

        <!-- Price Line -->
        <div class="mt-1 mb-3 flex items-center justify-center flex-wrap gap-1.5 text-xs sm:text-sm">
            <?php if ( $discount_amount > 0 && $regular_price > 0 ) : ?>
                <span class="line-through text-[#EF4444] font-bold text-xs sm:text-sm mr-0.5">
                    <?php echo esc_html(number_format($regular_price)); ?>৳
                </span>
                <span class="font-extrabold text-[#0F172A] text-base sm:text-lg mr-0.5">
                    <?php echo esc_html(number_format($active_price)); ?>৳
                </span>
                <span class="bg-[#FF8A00] text-white text-[10px] sm:text-[11px] font-extrabold px-2 py-0.5 rounded-full shadow-sm whitespace-nowrap">
                    Save <?php echo esc_html(number_format($discount_amount)); ?>৳
                </span>
            <?php else : ?>
                <span class="font-extrabold text-[#0F172A] text-base sm:text-lg">
                    <?php echo esc_html(number_format($active_price)); ?>৳
                </span>
            <?php endif; ?>
        </div>
        
        <!-- Action Buttons (Add to Cart & Order Now) -->
        <div class="mt-auto relative z-20 flex items-center gap-2">
            <button type="button" 
                    class="fmb-card-atc-btn flex-none p-2.5 rounded-xl border border-gray-200 hover:border-gray-400 bg-gray-50 hover:bg-white text-gray-700 transition-all duration-200 cursor-pointer flex items-center justify-center active:scale-95 shadow-sm" 
                    title="কার্টে যোগ করুন"
                    aria-label="কার্টে যোগ করুন"
                    data-product-id="<?php echo esc_attr($product_id); ?>">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                </svg>
            </button>
            <button type="button" 
                    class="qs-order-popup-trigger fmb-card-order-btn flex-1 text-white text-xs sm:text-sm font-extrabold py-2.5 px-3 rounded-xl transition-all duration-200 cursor-pointer shadow-md flex items-center justify-center gap-1.5 active:scale-[0.98]" 
                    style="background: var(--btn-bg, var(--primary)) !important; color: var(--btn-text, #ffffff) !important;"
                    data-product-id="<?php echo esc_attr($product_id); ?>"
                    data-price="<?php echo esc_attr($active_price); ?>" 
                    data-discount="<?php echo esc_attr($discount_amount); ?>"
                    data-custom-delivery="<?php echo esc_attr($custom_amt); ?>"
                    data-free-delivery="<?php echo $is_free_del ? '1' : '0'; ?>">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                </svg>
                <span>অর্ডার করুন</span>
            </button>
        </div>

    </div>
</div>