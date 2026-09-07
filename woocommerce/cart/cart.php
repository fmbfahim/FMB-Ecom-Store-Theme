<?php
/**
 * Custom Cart Template (Modern 2027 Design)
 * Theme: FMB E-Com Store
 */

defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_before_cart' ); ?>

<div class="fmb-cart-container max-w-7xl mx-auto px-3 sm:px-6 lg:px-8 py-6 sm:py-10 min-h-screen bg-[#F8FAFC]">
    
    <!-- Cart Header Banner -->
    <div class="flex items-center justify-between gap-3 mb-6 sm:mb-8 bg-white p-4 sm:p-5 rounded-2xl border border-blue-100/80 shadow-sm">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 sm:w-12 sm:h-12 bg-[#EEF5FF] text-[#0B1E67] rounded-xl flex items-center justify-center text-xl sm:text-2xl shadow-sm">
                🛒
            </div>
            <div>
                <h1 class="text-xl sm:text-2xl font-extrabold text-gray-900 m-0">
                    আপনার শপিং কার্ট
                </h1>
                <p class="text-xs text-gray-500 m-0 mt-0.5">
                    মোট <?php echo esc_html(WC()->cart->get_cart_contents_count()); ?>টি আইটেম যুক্ত রয়েছে
                </p>
            </div>
        </div>

        <a href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>" class="text-xs sm:text-sm font-bold text-primary hover:underline hidden sm:inline-flex items-center gap-1">
            <span>← আরও পণ্য দেখুন</span>
        </a>
    </div>

    <form class="woocommerce-cart-form" action="<?php echo esc_url( wc_get_cart_url() ); ?>" method="post">
        
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 lg:gap-8 items-start">
            
            <!-- LEFT COLUMN: Cart Items -->
            <div class="lg:col-span-8">
                <div class="bg-white border border-blue-100/80 rounded-2xl sm:rounded-3xl shadow-sm overflow-hidden">
                    
                    <div class="p-4 sm:p-6 border-b border-gray-100 flex items-center justify-between">
                        <h2 class="font-extrabold text-gray-800 text-base sm:text-lg flex items-center gap-2">
                            <span class="w-1.5 h-4 bg-[#0B1E67] rounded-full"></span>
                            নির্বাচিত পণ্যসমূহ
                        </h2>
                        <span class="text-xs font-bold text-gray-500 bg-gray-100 px-2.5 py-1 rounded-full">
                            <?php echo esc_html(WC()->cart->get_cart_contents_count()); ?> টি আইটেম
                        </span>
                    </div>

                    <div class="divide-y divide-gray-100">
                        <?php do_action( 'woocommerce_before_cart_contents' ); ?>

                        <?php
                        foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
                            $_product   = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );
                            $product_id = apply_filters( 'woocommerce_cart_item_product_id', $cart_item['product_id'], $cart_item, $cart_item_key );

                            if ( $_product && $_product->exists() && $cart_item['quantity'] > 0 && apply_filters( 'woocommerce_cart_item_visible', true, $cart_item, $cart_item_key ) ) {
                                $product_permalink = apply_filters( 'woocommerce_cart_item_permalink', $_product->is_visible() ? $_product->get_permalink( $cart_item ) : '', $cart_item, $cart_item_key );
                                ?>
                                <div class="woocommerce-cart-form__cart-item <?php echo esc_attr( apply_filters( 'woocommerce_cart_item_class', 'cart_item', $cart_item, $cart_item_key ) ); ?> p-4 sm:p-6 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 hover:bg-blue-50/30 transition duration-200">
                                    
                                    <!-- Image + Title -->
                                    <div class="flex items-center gap-3 sm:gap-4 w-full sm:w-auto flex-grow min-w-0">
                                        <!-- Thumbnail -->
                                        <div class="w-18 h-18 sm:w-20 sm:h-20 rounded-xl overflow-hidden border border-gray-100 shadow-sm bg-white shrink-0 aspect-square">
                                            <?php
                                            $thumbnail = apply_filters( 'woocommerce_cart_item_thumbnail', $_product->get_image(array(80, 80), array('class' => 'w-full h-full object-cover')), $cart_item, $cart_item_key );
                                            if ( ! $product_permalink ) {
                                                echo $thumbnail;
                                            } else {
                                                printf( '<a href="%s" class="block w-full h-full">%s</a>', esc_url( $product_permalink ), $thumbnail );
                                            }
                                            ?>
                                        </div>

                                        <!-- Title & Meta -->
                                        <div class="min-w-0 flex-grow">
                                            <h3 class="font-bold text-gray-900 text-sm sm:text-base leading-snug line-clamp-2">
                                                <?php
                                                if ( ! $product_permalink ) {
                                                    echo wp_kses_post( apply_filters( 'woocommerce_cart_item_name', $_product->get_name(), $cart_item, $cart_item_key ) );
                                                } else {
                                                    echo wp_kses_post( apply_filters( 'woocommerce_cart_item_name', sprintf( '<a href="%s" class="hover:text-primary transition">%s</a>', esc_url( $product_permalink ), $_product->get_name() ), $cart_item, $cart_item_key ) );
                                                }
                                                ?>
                                            </h3>

                                            <div class="text-xs text-gray-500 mt-1">
                                                একক মূল্য: <span class="font-bold text-gray-700"><?php echo apply_filters( 'woocommerce_cart_item_price', WC()->cart->get_product_price( $_product ), $cart_item, $cart_item_key ); ?></span>
                                            </div>

                                            <?php echo wc_get_formatted_cart_item_data( $cart_item ); ?>
                                        </div>
                                    </div>

                                    <!-- Stepper Quantity & Subtotal & Remove -->
                                    <div class="flex items-center justify-between sm:justify-end gap-3 sm:gap-6 w-full sm:w-auto border-t sm:border-t-0 pt-3 sm:pt-0 border-gray-100">
                                        <!-- Responsive Stepper Buttons -->
                                        <div class="flex items-center border border-gray-200 rounded-xl overflow-hidden h-9 bg-gray-50 shadow-inner">
                                            <button type="button" class="fmb-cart-step-btn minus px-3 h-full font-black text-gray-600 hover:bg-gray-200 transition-colors" data-key="<?php echo esc_attr($cart_item_key); ?>">-</button>
                                            <input type="number" 
                                                   name="cart[<?php echo esc_attr( $cart_item_key ); ?>][qty]" 
                                                   value="<?php echo esc_attr( $cart_item['quantity'] ); ?>" 
                                                   min="1" 
                                                   max="<?php echo esc_attr( $_product->get_max_purchase_quantity() > 0 ? $_product->get_max_purchase_quantity() : '' ); ?>" 
                                                   class="fmb-cart-qty-input w-12 h-full text-center border-0 focus:ring-0 p-0 text-sm font-black text-gray-900 bg-white" 
                                                   readonly>
                                            <button type="button" class="fmb-cart-step-btn plus px-3 h-full font-black text-gray-600 hover:bg-gray-200 transition-colors" data-key="<?php echo esc_attr($cart_item_key); ?>">+</button>
                                        </div>

                                        <!-- Line Total -->
                                        <div class="text-right min-w-[75px]">
                                            <span class="font-black text-[#0B1E67] text-base sm:text-lg">
                                                <?php echo apply_filters( 'woocommerce_cart_item_subtotal', WC()->cart->get_product_subtotal( $_product, $cart_item['quantity'] ), $cart_item, $cart_item_key ); ?>
                                            </span>
                                        </div>

                                        <!-- Remove Button -->
                                        <div class="shrink-0">
                                            <?php
                                                echo apply_filters( 
                                                    'woocommerce_cart_item_remove_link',
                                                    sprintf(
                                                        '<a href="%s" class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-red-50 text-red-500 hover:bg-red-500 hover:text-white font-bold transition duration-200 shadow-sm text-base" aria-label="%s" data-product_id="%s" data-product_sku="%s" title="মুছে ফেলুন">&times;</a>',
                                                        esc_url( wc_get_cart_remove_url( $cart_item_key ) ),
                                                        esc_html__( 'Remove this item', 'woocommerce' ),
                                                        esc_attr( $product_id ),
                                                        esc_attr( $_product->get_sku() )
                                                    ),
                                                    $cart_item_key
                                                );
                                            ?>
                                        </div>
                                    </div>

                                </div>
                                <?php
                            }
                        }
                        ?>

                        <?php do_action( 'woocommerce_after_cart_contents' ); ?>
                    </div>

                    <!-- Actions & Coupons -->
                    <div class="p-4 sm:p-6 bg-gray-50/70 border-t border-gray-100 flex flex-col sm:flex-row justify-between items-center gap-4">
                        <?php if ( wc_coupons_enabled() ) { ?>
                            <div class="coupon flex items-center gap-2 w-full sm:w-auto">
                                <input type="text" name="coupon_code" class="input-text border border-gray-200 rounded-xl px-3.5 py-2.5 text-xs sm:text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary w-full sm:w-52 bg-white shadow-sm font-medium" id="coupon_code" value="" placeholder="কুপন কোড থাকলে লিখুন..." /> 
                                <button type="submit" class="button bg-[#0B1E67] text-white px-4 py-2.5 rounded-xl font-bold text-xs sm:text-sm hover:bg-[#071344] transition shadow whitespace-nowrap" name="apply_coupon" value="প্রয়োগ">
                                    প্রয়োগ করুন
                                </button>
                            </div>
                        <?php } ?>

                        <div class="flex items-center gap-2 w-full sm:w-auto justify-end">
                            <button type="submit" class="button bg-white border border-gray-200 text-gray-700 px-4 py-2.5 rounded-xl font-bold text-xs sm:text-sm hover:bg-gray-100 transition shadow-sm ml-auto whitespace-nowrap" name="update_cart" value="<?php esc_attr_e( 'Update cart', 'woocommerce' ); ?>" id="fmb_update_cart_btn">
                                🔄 কার্ট আপডেট
                            </button>
                        </div>

                        <?php do_action( 'woocommerce_cart_actions' ); ?>
                        <?php wp_nonce_field( 'woocommerce-cart', 'woocommerce-cart-nonce' ); ?>
                    </div>

                </div>
            </div>

            <!-- RIGHT COLUMN: Order Summary -->
            <div class="lg:col-span-4">
                <div class="bg-white border border-blue-100/80 rounded-2xl sm:rounded-3xl shadow-lg p-5 sm:p-6 sticky top-24">
                    <?php
                        woocommerce_cart_totals();
                    ?>
                </div>
            </div>

        </div>
    </form>
</div>

<script>
jQuery(document).ready(function($) {
    // Interactive Quantity Stepper for Cart
    $(document).on('click', '.fmb-cart-step-btn', function(e) {
        e.preventDefault();
        var btn = $(this);
        var input = btn.siblings('.fmb-cart-qty-input');
        var val = parseInt(input.val(), 10) || 1;
        var max = input.attr('max') ? parseInt(input.attr('max'), 10) : 9999;
        var min = input.attr('min') ? parseInt(input.attr('min'), 10) : 1;

        if (btn.hasClass('plus')) {
            if (val < max) val++;
        } else if (btn.hasClass('minus')) {
            if (val > min) val--;
        }

        input.val(val);
        // Automatically submit / trigger update cart
        $('#fmb_update_cart_btn').removeAttr('disabled').trigger('click');
    });
});
</script>

<?php do_action( 'woocommerce_after_cart' ); ?>