<?php
/**
 * Cart Totals (Modern 2027 Design)
 * Theme: FMB E-Com Store
 */
defined( 'ABSPATH' ) || exit;

?>
<div class="cart_totals <?php echo ( WC()->customer->has_calculated_shipping() ) ? 'calculated_shipping' : ''; ?>">

    <?php do_action( 'woocommerce_before_cart_totals' ); ?>

    <div class="border-b border-gray-100 pb-3 mb-4 flex items-center justify-between">
        <h2 class="text-lg sm:text-xl font-black text-gray-900 flex items-center gap-2 m-0">
            <span class="text-[#0B1E67]">🧾</span> অর্ডার সামারি
        </h2>
        <span class="text-xs font-bold text-gray-500 bg-gray-100 px-2 py-0.5 rounded-full">
            নিরাপদ চেকআউট
        </span>
    </div>

    <div class="bg-[#EEF5FF]/60 rounded-2xl p-4 sm:p-5 mb-5 border border-blue-100/70">
        <table cellspacing="0" class="shop_table shop_table_responsive w-full">

            <!-- Subtotal -->
            <tr class="cart-subtotal border-b border-blue-100/70">
                <th class="py-2.5 text-left font-bold text-gray-600 text-sm">সাবটোটাল</th>
                <td data-title="<?php esc_attr_e( 'Subtotal', 'woocommerce' ); ?>" class="py-2.5 text-right font-extrabold text-gray-900 text-sm">
                    <?php wc_cart_totals_subtotal_html(); ?>
                </td>
            </tr>

            <!-- Coupons -->
            <?php foreach ( WC()->cart->get_coupons() as $code => $coupon ) : ?>
                <tr class="cart-discount coupon-<?php echo esc_attr( sanitize_title( $code ) ); ?> border-b border-blue-100/70 bg-emerald-50 rounded-lg">
                    <th class="py-2.5 text-left font-bold text-emerald-700 text-sm px-2 rounded-l-lg"><?php wc_cart_totals_coupon_label( $coupon ); ?></th>
                    <td data-title="<?php esc_attr_e( 'Coupon: ' . $code, 'woocommerce' ); ?>" class="py-2.5 text-right font-bold text-emerald-700 text-sm px-2 rounded-r-lg">
                        <?php wc_cart_totals_coupon_html( $coupon ); ?>
                    </td>
                </tr>
            <?php endforeach; ?>

            <!-- Shipping -->
            <?php if ( WC()->cart->needs_shipping() && WC()->cart->show_shipping() ) : ?>

                <?php do_action( 'woocommerce_cart_totals_before_shipping' ); ?>

                <?php wc_cart_totals_shipping_html(); ?>

                <?php do_action( 'woocommerce_cart_totals_after_shipping' ); ?>

            <?php elseif ( WC()->cart->needs_shipping() && 'yes' === get_option( 'woocommerce_enable_shipping_calc' ) ) : ?>

                <tr class="shipping border-b border-blue-100/70">
                    <th class="py-2.5 text-left font-bold text-gray-600 text-sm">ডেলিভারি চার্জ</th>
                    <td data-title="<?php esc_attr_e( 'Shipping', 'woocommerce' ); ?>" class="py-2.5 text-right text-gray-900 font-bold text-sm">
                        <?php woocommerce_shipping_calculator(); ?>
                    </td>
                </tr>

            <?php endif; ?>

            <!-- Fees -->
            <?php foreach ( WC()->cart->get_fees() as $fee ) : ?>
                <tr class="fee border-b border-blue-100/70">
                    <th class="py-2.5 text-left font-bold text-gray-600 text-sm"><?php echo esc_html( $fee->name ); ?></th>
                    <td data-title="<?php echo esc_attr( $fee->name ); ?>" class="py-2.5 text-right font-bold text-gray-900 text-sm">
                        <?php wc_cart_totals_fee_html( $fee ); ?>
                    </td>
                </tr>
            <?php endforeach; ?>

            <!-- Tax -->
            <?php
            if ( wc_tax_enabled() && ! WC()->cart->display_prices_including_tax() ) {
                $taxable_address = WC()->customer->get_taxable_address();
                $estimated_text  = '';

                if ( WC()->customer->is_customer_outside_base() && ! WC()->customer->has_calculated_shipping() ) {
                    $estimated_text = sprintf( ' <small>' . esc_html__( '(estimated for %s)', 'woocommerce' ) . '</small>', WC()->countries->estimated_for_prefix( $taxable_address[0] ) . WC()->countries->countries[ $taxable_address[0] ] );
                }

                if ( 'itemized' === get_option( 'woocommerce_tax_total_display' ) ) {
                    foreach ( WC()->cart->get_tax_totals() as $code => $tax ) {
                        ?>
                        <tr class="tax-rate tax-rate-<?php echo esc_attr( sanitize_title( $code ) ); ?> border-b border-blue-100/70">
                            <th class="py-2.5 text-left font-bold text-gray-600 text-sm"><?php echo esc_html( $tax->label ) . $estimated_text; ?></th>
                            <td data-title="<?php echo esc_attr( $tax->label ); ?>" class="py-2.5 text-right font-bold text-gray-900 text-sm"><?php echo wp_kses_post( $tax->formatted_amount ); ?></td>
                        </tr>
                        <?php
                    }
                } else {
                    ?>
                    <tr class="tax-total border-b border-blue-100/70">
                        <th class="py-2.5 text-left font-bold text-gray-600 text-sm"><?php echo esc_html( WC()->countries->tax_or_vat() ) . $estimated_text; ?></th>
                        <td data-title="<?php echo esc_attr( WC()->countries->tax_or_vat() ); ?>" class="py-2.5 text-right font-bold text-gray-900 text-sm"><?php wc_cart_totals_taxes_total_html(); ?></td>
                    </tr>
                    <?php
                }
            }
            ?>

            <?php do_action( 'woocommerce_cart_totals_before_order_total' ); ?>

            <!-- Total -->
            <tr class="order-total">
                <th class="pt-4 pb-1 text-left font-black text-base sm:text-lg text-gray-900">সর্বমোট প্রদেয়</th>
                <td data-title="<?php esc_attr_e( 'Total', 'woocommerce' ); ?>" class="pt-4 pb-1 text-right font-black text-xl sm:text-2xl text-[#0B1E67]">
                    <?php wc_cart_totals_order_total_html(); ?>
                </td>
            </tr>

            <?php do_action( 'woocommerce_cart_totals_after_order_total' ); ?>

        </table>
    </div>

    <!-- Direct Proceed to Checkout CTA Button -->
    <div class="wc-proceed-to-checkout w-full">
        <a href="<?php echo esc_url( wc_get_checkout_url() ); ?>" 
           class="checkout-button block w-full text-center bg-gradient-to-r from-[#0B1E67] via-[#1E3A8A] to-[#0B1E67] hover:from-[#071344] hover:to-[#071344] text-white font-black text-sm sm:text-base py-3.5 px-6 rounded-xl shadow-lg hover:shadow-xl transition-all duration-300 transform active:scale-[0.98]">
            <span>অর্ডার সম্পন্ন করতে এগিয়ে যান</span>
            <span class="ml-1 text-lg">➔</span>
        </a>
    </div>
    
    <!-- Trust Badges Under Checkout Button -->
    <div class="mt-4 pt-4 border-t border-gray-100 space-y-2 text-[11px] text-gray-500 font-semibold">
        <div class="flex items-center gap-2">
            <span class="text-emerald-600 text-sm">✓</span>
            <span>ক্যাশ অন ডেলিভারি (পণ্য হাতে পেয়ে মূল্য পরিশোধ)</span>
        </div>
        <div class="flex items-center gap-2">
            <span class="text-emerald-600 text-sm">✓</span>
            <span>সারাদেশে দ্রুততম হোম ডেলিভারি সুবিধা</span>
        </div>
        <div class="flex items-center gap-2">
            <span class="text-emerald-600 text-sm">✓</span>
            <span>১০০% জেনুইন ও অরিজিনাল কোয়ালিটি নিশ্চয়তা</span>
        </div>
    </div>

    <div class="mt-4 text-center">
        <a href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>" class="text-xs font-bold text-gray-500 hover:text-[#0B1E67] transition inline-flex items-center gap-1">
            <span>← আরও পণ্য বাছাই করুন</span>
        </a>
    </div>

    <?php do_action( 'woocommerce_after_cart_totals' ); ?>

</div>
