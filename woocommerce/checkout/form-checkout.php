<?php
/**
 * Custom Checkout Template (Modern 2027 High-Converting Design)
 * Theme: FMB E-Com Store
 */
defined( 'ABSPATH' ) || exit;

// যদি কার্ট খালি থাকে, আধুনিক এম্পটি কার্ট টেমপ্লেট দেখাবে
if ( ! WC()->cart || WC()->cart->is_empty() ) {
    wc_get_template( 'cart/cart-empty.php' );
    return;
}
?>

<div class="fmb-checkout-container max-w-7xl mx-auto px-3 sm:px-6 lg:px-8 py-6 sm:py-10 min-h-screen bg-[#F8FAFC]">
    
    <!-- 🧭 1. Modern Checkout Progress Stepper -->
    <div class="mb-6 sm:mb-8">
        <div class="flex items-center justify-center max-w-xl mx-auto">
            <!-- Step 1: Cart -->
            <a href="<?php echo esc_url(wc_get_cart_url()); ?>" class="flex items-center gap-1.5 sm:gap-2 text-[#0B1E67] font-bold text-xs sm:text-sm">
                <span class="w-7 h-7 sm:w-8 sm:h-8 rounded-full bg-blue-100 text-[#0B1E67] font-black flex items-center justify-center text-xs shadow-sm">✓</span>
                <span>১. কার্ট</span>
            </a>
            
            <div class="flex-grow border-t-2 border-dashed border-blue-300 mx-2 sm:mx-4 max-w-[50px] sm:max-w-[80px]"></div>
            
            <!-- Step 2: Checkout (Active) -->
            <div class="flex items-center gap-1.5 sm:gap-2 text-[#0B1E67] font-black text-xs sm:text-sm">
                <span class="w-7 h-7 sm:w-8 sm:h-8 rounded-full bg-[#0B1E67] text-white font-black flex items-center justify-center text-xs shadow-md ring-4 ring-blue-100">২</span>
                <span>২. ডেলিভারি ও পেমেন্ট</span>
            </div>
            
            <div class="flex-grow border-t-2 border-dashed border-gray-200 mx-2 sm:mx-4 max-w-[50px] sm:max-w-[80px]"></div>
            
            <!-- Step 3: Complete -->
            <div class="flex items-center gap-1.5 sm:gap-2 text-gray-400 font-bold text-xs sm:text-sm">
                <span class="w-7 h-7 sm:w-8 sm:h-8 rounded-full bg-gray-100 text-gray-400 font-black flex items-center justify-center text-xs">৩</span>
                <span>৩. সম্পন্ন</span>
            </div>
        </div>
    </div>

    <!-- 🚚 2. Free Delivery & Cash on Delivery Reassurance Banner -->
    <div class="fmb-cod-banner mb-5 sm:mb-6 bg-gradient-to-r from-[#0B1E67] via-[#1E3A8A] to-[#0B1E67] text-white rounded-xl sm:rounded-2xl p-3 sm:p-4 shadow-sm border border-blue-800/80 flex items-center justify-between gap-3">
        <div class="flex items-center gap-2.5 sm:gap-3 min-w-0 flex-1">
            <span class="text-xl sm:text-2xl shrink-0" aria-hidden="true">🚚</span>
            <div class="text-[12.5px] sm:text-sm font-medium leading-snug break-words">
                <strong class="font-extrabold text-amber-300">ক্যাশ অন ডেলিভারি সুবিধা!</strong>
                <span class="text-white/95"> পার্সেল হাতে পেয়ে দেখে নিশ্চিত হয়ে মূল্য পরিশোধ করুন।</span>
            </div>
        </div>
        <span class="hidden md:inline-flex items-center gap-1 bg-amber-400 text-gray-950 text-xs font-black px-3 py-1 rounded-full shadow shrink-0 whitespace-nowrap">
            ⚡ ১০০% নিরাপদ অর্ডার
        </span>
    </div>

    <form name="checkout" method="post" class="checkout woocommerce-checkout" action="<?php echo esc_url( wc_get_checkout_url() ); ?>" enctype="multipart/form-data">

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 lg:gap-8 items-start">
            
            <!-- LEFT COLUMN: Cart Items & Customer Address (7 Cols) -->
            <div class="lg:col-span-7 space-y-6">
                
                <!-- 📦 A. Order Items Review Card with Stepper -->
                <div class="bg-white p-5 sm:p-6 rounded-2xl sm:rounded-3xl shadow-sm border border-blue-100/80">
                    <div class="flex items-center justify-between mb-4 pb-3 border-b border-gray-100">
                        <h3 class="text-base sm:text-lg font-black text-gray-900 flex items-center gap-2">
                            <span class="w-2 h-5 bg-[#0B1E67] rounded-full"></span>
                            আপনার অর্ডার আইটেম
                        </h3>
                        <a href="<?php echo esc_url(wc_get_cart_url()); ?>" class="text-xs font-bold text-primary hover:underline">
                            কার্ট এডিট করুন →
                        </a>
                    </div>
                    
                    <div class="space-y-4">
                        <?php
                        foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
                            $_product   = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );
                            $product_id = apply_filters( 'woocommerce_cart_item_product_id', $cart_item['product_id'], $cart_item, $cart_item_key );

                            if ( $_product && $_product->exists() && $cart_item['quantity'] > 0 && apply_filters( 'woocommerce_checkout_cart_item_visible', true, $cart_item, $cart_item_key ) ) {
                                ?>
                                <div class="flex items-center justify-between border-b border-gray-100 pb-4 last:border-0 last:pb-0 gap-3">
                                    <div class="flex items-center gap-3 sm:gap-4 min-w-0">
                                        <div class="w-16 h-16 sm:w-20 sm:h-20 rounded-xl overflow-hidden bg-gray-50 shrink-0 border border-gray-100 shadow-sm">
                                            <?php echo apply_filters( 'woocommerce_cart_item_thumbnail', $_product->get_image(array(80, 80), array('class' => 'w-full h-full object-cover')), $cart_item, $cart_item_key ); ?>
                                        </div>
                                        <div class="min-w-0">
                                            <h4 class="text-xs sm:text-sm font-bold text-gray-900 leading-snug line-clamp-2">
                                                <?php echo apply_filters( 'woocommerce_cart_item_name', $_product->get_name(), $cart_item, $cart_item_key ); ?>
                                            </h4>
                                            <?php echo wc_get_formatted_cart_item_data( $cart_item ); ?>
                                            
                                            <!-- Interactive Quantity Stepper -->
                                            <div class="flex items-center gap-2 sm:gap-3 mt-2 text-xs text-gray-600">
                                                <span class="font-semibold">পরিমাণ:</span>
                                                <div class="flex items-center border border-gray-200 rounded-lg overflow-hidden h-7 sm:h-8 bg-gray-50 shadow-inner">
                                                    <button type="button" class="fmb-qty-btn minus bg-gray-100 hover:bg-gray-200 px-2.5 h-full font-black text-gray-700 transition-colors">-</button>
                                                    <input type="number" readonly class="fmb-qty-input w-9 h-full text-center border-0 focus:ring-0 p-0 text-xs sm:text-sm font-black text-gray-900 bg-white" data-cart-item-key="<?php echo esc_attr( $cart_item_key ); ?>" value="<?php echo esc_attr( $cart_item['quantity'] ); ?>">
                                                    <button type="button" class="fmb-qty-btn plus bg-gray-100 hover:bg-gray-200 px-2.5 h-full font-black text-gray-700 transition-colors">+</button>
                                                </div>
                                                <span class="font-black text-[#0B1E67] text-sm sm:text-base ml-1">
                                                    <?php echo apply_filters( 'woocommerce_cart_item_subtotal', WC()->cart->get_product_subtotal( $_product, $cart_item['quantity'] ), $cart_item, $cart_item_key ); ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Delete Item Button -->
                                    <div class="shrink-0">
                                        <?php
                                            echo apply_filters(
                                                'woocommerce_cart_item_remove_link',
                                                sprintf(
                                                    '<a href="%s" class="remove bg-red-50 hover:bg-red-500 hover:text-white text-red-500 w-8 h-8 rounded-full inline-flex items-center justify-center transition shadow-sm text-base font-bold" aria-label="%s" data-product_id="%s" data-product_sku="%s" title="মুছে ফেলুন">&times;</a>',
                                                    esc_url( wc_get_cart_remove_url( $cart_item_key ) ),
                                                    esc_attr( sprintf( __( 'Remove %s from cart', 'woocommerce' ), wp_strip_all_tags( $_product->get_name() ) ) ),
                                                    esc_attr( $product_id ),
                                                    esc_attr( $_product->get_sku() )
                                                ),
                                                $cart_item_key
                                            );
                                        ?>
                                    </div>
                                </div>
                                <?php
                            }
                        }
                        ?>
                    </div>
                </div>

                <!-- 📍 B. Delivery Information Card -->
                <div class="bg-white p-5 sm:p-6 rounded-2xl sm:rounded-3xl shadow-sm border border-blue-100/80">
                    <div class="flex items-center justify-between mb-4 pb-3 border-b border-gray-100">
                        <h3 class="text-base sm:text-lg font-black text-gray-900 flex items-center gap-2">
                            <span class="w-2 h-5 bg-[#0B1E67] rounded-full"></span>
                            ডেলিভারি ঠিকানা
                        </h3>
                        <span class="text-xs text-red-500 font-bold">* চিহ্নিত ফিল্ডগুলো আবশ্যক</span>
                    </div>
                    
                    <?php if ( $checkout->get_checkout_fields() ) : ?>
                        <?php do_action( 'woocommerce_checkout_before_customer_details' ); ?>
                        
                        <div id="customer_details">
                            <div class="fmb-checkout-billing-fields">
                                <?php do_action( 'woocommerce_checkout_billing' ); ?>
                            </div>
                        </div>

                        <?php do_action( 'woocommerce_checkout_after_customer_details' ); ?>
                    <?php endif; ?>
                </div>

                <!-- 📝 C. Order Notes / Special Instructions -->
                <div class="bg-white p-5 sm:p-6 rounded-2xl sm:rounded-3xl shadow-sm border border-blue-100/80" id="fmb_order_notes_wrapper">
                    <h3 class="text-sm font-black text-gray-800 mb-3 flex items-center gap-2">
                        <span>📝</span> বিশেষ কোনো নির্দেশনা থাকলে লিখুন (ঐচ্ছিক)
                    </h3>
                    <?php do_action( 'woocommerce_checkout_shipping' ); ?>
                </div>

            </div>

            <!-- RIGHT COLUMN: Payment & Totals (5 Cols) -->
            <div class="lg:col-span-5 space-y-6 sticky top-24">
                
                <!-- 💳 Payment & Order Review Card -->
                <div class="bg-white p-5 sm:p-6 rounded-2xl sm:rounded-3xl shadow-lg border border-blue-100/80 custom-payment-methods">
                    <div class="border-b border-gray-100 pb-3 mb-4 flex items-center justify-between">
                        <h3 class="text-base sm:text-lg font-black text-gray-900 flex items-center gap-2">
                            <span class="w-2 h-5 bg-[#0B1E67] rounded-full"></span>
                            অর্ডার সামারি ও পেমেন্ট
                        </h3>
                        <span class="text-xs font-bold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded-full border border-emerald-200">
                            ক্যাশ অন ডেলিভারি
                        </span>
                    </div>
                    
                    <div id="order_review" class="woocommerce-checkout-review-order">
                        <?php do_action( 'woocommerce_checkout_order_review' ); ?>
                    </div>
                </div>

                <!-- 🛡️ Trust Badges -->
                <div class="bg-white p-4 sm:p-5 rounded-2xl border border-blue-100/80 shadow-sm space-y-2.5 text-xs text-gray-600 font-semibold">
                    <div class="flex items-center gap-2.5">
                        <span class="text-emerald-600 text-base">🛡️</span>
                        <span>১০০% ক্যাশ অন ডেলিভারি — আগে কোনো অগ্রিম দিতে হবে না।</span>
                    </div>
                    <div class="flex items-center gap-2.5">
                        <span class="text-emerald-600 text-base">📦</span>
                        <span>ডেলিভারি ম্যানের সামনে প্যাকেট খুলে চেক করার সুবিধা।</span>
                    </div>
                    <div class="flex items-center gap-2.5">
                        <span class="text-emerald-600 text-base">📞</span>
                        <span>যেকোনো তথ্যের জন্য কল করুন: <span class="text-primary font-bold">01886-448064</span></span>
                    </div>
                </div>

            </div>

        </div>

    </form>
</div>

<script>
jQuery(document).ready(function($) {
    if (typeof fmb_vars === 'undefined') {
        window.fmb_vars = { ajax_url: '<?php echo admin_url("admin-ajax.php"); ?>' };
    }
    
    // Quantity Stepper on Checkout Page
    $(document).on('click', '.fmb-qty-btn', function(e) {
        e.preventDefault();
        var btn = $(this);
        var input = btn.siblings('.fmb-qty-input');
        var current = parseInt(input.val(), 10) || 1;
        var key = input.data('cart-item-key');
        
        if (btn.hasClass('plus')) {
            current++;
        } else {
            current--;
        }
        if (current < 1) current = 1; 
        
        input.val(current);
        
        $('.woocommerce-checkout').addClass('processing').block({
            message: null,
            overlayCSS: { background: '#fff', opacity: 0.6 }
        });
        
        $.post(fmb_vars.ajax_url, {
            action: 'fmb_update_checkout_cart',
            cart_item_key: key,
            qty: current
        }, function(response) {
            $('body').trigger('update_checkout');
        });
    });
});
</script>

<style>
/* Hide the products list from the right column order review table since we display it cleanly on the left */
.woocommerce-checkout-review-order-table thead,
.woocommerce-checkout-review-order-table tbody {
    display: none !important;
}

/* Style Order Review Totals Table in the right column */
.woocommerce-checkout-review-order-table {
    width: 100%;
    margin-top: 0.5rem;
    border-collapse: separate;
    border-spacing: 0;
}
.woocommerce-checkout-review-order-table tfoot th {
    text-align: left;
    padding: 10px 0;
    color: #4B5563;
    font-weight: 600;
    font-size: 13px;
    border-bottom: 1px dashed #E2E8F0;
}
.woocommerce-checkout-review-order-table tfoot td {
    text-align: right;
    padding: 10px 0;
    font-weight: 700;
    color: #0F172A;
    font-size: 14px;
    border-bottom: 1px dashed #E2E8F0;
}
.woocommerce-checkout-review-order-table tfoot tr.order-total th,
.woocommerce-checkout-review-order-table tfoot tr.order-total td {
    border-bottom: none;
    font-size: 1.15rem;
    color: #0B1E67;
    font-weight: 900;
    padding-top: 14px;
}
.woocommerce-checkout-review-order-table tfoot tr.shipping th {
    visibility: hidden;
    position: relative;
}
.woocommerce-checkout-review-order-table tfoot tr.shipping th::after {
    content: "ডেলিভারি চার্জ";
    visibility: visible;
    position: absolute;
    left: 0;
    font-weight: 600;
    color: #4B5563;
    font-size: 13px;
}
.woocommerce-checkout-review-order-table tfoot tr.shipping td {
    color: #0B1E67;
    font-weight: 700;
    font-size: 13px;
}

/* COD Reassurance Banner Mobile Responsiveness */
.fmb-cod-banner {
    box-sizing: border-box;
    width: 100%;
}
@media (max-width: 640px) {
    .fmb-cod-banner {
        padding: 10px 14px !important;
        border-radius: 14px !important;
    }
}

/* Modern & Compact Payment Review Section (Minimizes Excessive Gaps) */
.woocommerce-checkout #payment {
    background: #F8FAFC !important;
    border: 1px solid #E2E8F0 !important;
    border-radius: 16px !important;
    padding: 12px 14px !important;
    margin-top: 14px !important;
}
#payment ul.payment_methods {
    display: grid !important;
    grid-template-columns: 1fr !important;
    gap: 8px !important;
    padding: 0 !important;
    margin: 0 0 10px 0 !important;
    border: none !important;
    background: transparent !important;
}
#payment ul.payment_methods li.wc_payment_method {
    margin: 0 !important;
    padding: 0 !important;
    list-style: none !important;
}
#payment ul.payment_methods li.wc_payment_method > input.input-radio {
    display: none !important;
}
#payment ul.payment_methods li.wc_payment_method > label {
    display: flex !important;
    align-items: center !important;
    gap: 10px !important;
    padding: 12px 14px !important;
    background: #EEF5FF !important;
    border: 2px solid #0B1E67 !important;
    border-radius: 12px !important;
    cursor: pointer !important;
    font-size: 0 !important; /* Hide English text */
    font-weight: 800 !important;
    color: #0B1E67 !important;
    transition: all 0.2s !important;
}
#payment ul.payment_methods li.wc_payment_method > label::before {
    content: "💵 ক্যাশ অন ডেলিভারি (পণ্য হাতে পেয়ে মূল্য পরিশোধ)";
    font-size: 13px !important;
    font-weight: 800 !important;
    color: #0B1E67 !important;
}
#payment ul.payment_methods li.wc_payment_method > label::after {
    content: "✓";
    margin-left: auto !important;
    background: #0B1E67 !important;
    color: white !important;
    width: 22px !important;
    height: 22px !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    border-radius: 50% !important;
    font-size: 12px !important;
    font-weight: 900 !important;
}
#payment div.payment_box {
    display: none !important;
}

/* Tightly Spaced Place Order Area */
#payment .place-order {
    padding: 0 !important;
    margin: 0 !important;
    border: none !important;
    background: transparent !important;
    float: none !important;
}
#payment .woocommerce-terms-and-conditions-wrapper {
    margin: 0 !important;
    padding: 0 !important;
}
.woocommerce-privacy-policy-text {
    font-size: 10.5px !important;
    color: #64748B !important;
    line-height: 1.4 !important;
    margin: 4px 0 8px 0 !important;
    padding: 0 !important;
}
.woocommerce-privacy-policy-text p {
    margin: 0 0 6px 0 !important;
    padding: 0 !important;
    line-height: 1.4 !important;
}

#payment .place-order .button,
#place_order {
    width: 100% !important;
    background: linear-gradient(135deg, #0B1E67 0%, #1E3A8A 50%, #0B1E67 100%) !important;
    color: white !important;
    font-size: 15px !important;
    font-weight: 900 !important;
    padding: 14px 18px !important;
    border-radius: 14px !important;
    border: none !important;
    cursor: pointer !important;
    transition: all 0.3s ease !important;
    display: block !important;
    text-align: center !important;
    box-shadow: 0 10px 20px -5px rgba(11, 30, 103, 0.35) !important;
    letter-spacing: 0.3px !important;
    margin: 2px 0 0 0 !important;
}
#payment .place-order .button:hover,
#place_order:hover {
    box-shadow: 0 14px 24px -5px rgba(11, 30, 103, 0.45) !important;
    transform: translateY(-1px) !important;
}
#payment .place-order .button:active,
#place_order:active {
    transform: scale(0.98) !important;
}

/* Form Inputs Styling */
.fmb-checkout-billing-fields input[type="text"],
.fmb-checkout-billing-fields input[type="tel"],
.fmb-checkout-billing-fields select,
#fmb_order_notes_wrapper textarea {
    width: 100%;
    padding: 12px 16px;
    border: 1px solid #CBD5E1;
    border-radius: 12px;
    font-size: 13px;
    font-weight: 600;
    color: #1E293B;
    background-color: #FFFFFF;
    transition: border-color 0.2s, box-shadow 0.2s;
    outline: none;
    box-sizing: border-box;
}
.fmb-checkout-billing-fields input[type="text"]:focus,
.fmb-checkout-billing-fields input[type="tel"]:focus,
.fmb-checkout-billing-fields select:focus,
#fmb_order_notes_wrapper textarea:focus {
    border-color: #0B1E67;
    box-shadow: 0 0 0 3px rgba(11, 30, 103, 0.12);
}

.fmb-checkout-billing-fields .form-row {
    margin-bottom: 14px;
}

/* Name, Phone, and Address: strictly 100% full width */
.fmb-checkout-billing-fields .form-row-wide,
.fmb-checkout-billing-fields #billing_first_name_field,
.fmb-checkout-billing-fields #billing_phone_field,
.fmb-checkout-billing-fields #billing_address_1_field {
    width: 100% !important;
    float: none !important;
    clear: both !important;
    display: block !important;
}

/* State (District) and City (Thana): side-by-side 50/50 on desktop and mobile */
.fmb-checkout-billing-fields .form-row-first {
    width: 48.5% !important;
    float: left !important;
    clear: none !important;
}
.fmb-checkout-billing-fields .form-row-last {
    width: 48.5% !important;
    float: right !important;
    clear: none !important;
}

.fmb-checkout-billing-fields::after {
    content: "";
    display: table;
    clear: both;
}

/* Phone Number Prefix 88 */
#billing_phone_field .woocommerce-input-wrapper {
    position: relative;
    display: block;
}
#billing_phone_field .woocommerce-input-wrapper::before {
    content: "88";
    position: absolute;
    left: 1px;
    top: 1px;
    bottom: 1px;
    width: 44px;
    background: #F1F5F9;
    border-top-left-radius: 11px;
    border-bottom-left-radius: 11px;
    border-right: 1px solid #CBD5E1;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 13px;
    color: #475569;
    font-weight: 800;
    pointer-events: none;
    z-index: 10;
}
#billing_phone {
    padding-left: 54px !important;
}

#ship-to-different-address,
.woocommerce-shipping-fields,
#order_comments_field label {
    display: none !important;
}
</style>