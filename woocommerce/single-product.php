<?php
/**
 * Advanced Single Product Template (Multi-product Checkout with Dynamic Design Variants)
 */
get_header();

while ( have_posts() ) : the_post();
    global $product;
    $main_id = $product->get_id();
    $main_price = $product->get_price();
    $main_image = wp_get_attachment_image_url($product->get_image_id(), 'thumbnail');

    // মেটা ডাটা আনা
    $steps = get_post_meta($main_id, '_fmb_usage_steps', true);
    $cards = get_post_meta($main_id, '_fmb_info_cards', true);
    
    // Upsells
    $upsell_title = get_post_meta($main_id, '_fmb_upsell_title', true) ?: 'এর সাথে আরও যা যা নিতে পারেন';
    $upsell_ids_str = get_post_meta($main_id, '_fmb_upsell_ids', true);
    $upsell_data = get_post_meta($main_id, '_fmb_upsells_data', true);
    if (!is_array($upsell_data)) $upsell_data = [];
    
    // Migration fallback
    if (empty($upsell_data) && !empty($upsell_ids_str)) {
        $old_ids = array_map('trim', explode(',', $old_upsell_ids_str));
        foreach($old_ids as $id) {
            if($id) $upsell_data[] = ['id' => $id, 'logic' => ''];
        }
    }

    // Order Bumps
    $order_bumps = get_post_meta($main_id, '_fmb_order_bumps_data', true);
    if (!is_array($order_bumps)) $order_bumps = [];
    $bump_ids = [];
    foreach($order_bumps as $bump) {
        if (!empty($bump['id'])) $bump_ids[] = absint($bump['id']);
    }

    // 🎨 Product Template Variant
    $template_variant = isset($_GET['variant']) ? sanitize_key($_GET['variant']) : (get_post_meta($main_id, '_fmb_single_template_variant', true) ?: 'classic');
?>

<div class="fmb-product-page fmb-single-theme-<?php echo esc_attr($template_variant); ?> min-h-screen pb-24">
    
    <!-- Top Quantity & Navigation Scripts -->
    <script>
    function changeTopQty(change) {
        let input = document.getElementById('top-qty-input');
        if (!input) return;
        let val = parseInt(input.value) || 1;
        let newVal = val + change;
        if (newVal >= 1) {
            input.value = newVal;
            if(typeof mainProduct !== 'undefined') {
                mainProduct.qty = newVal;
            }
        }
    }
    function syncTopQtyToCheckout() {
        let input = document.getElementById('top-qty-input');
        if (!input) return;
        let val = parseInt(input.value) || 1;
        let checkoutQtySpan = document.querySelector('#order-items-list .qty-inc')?.parentElement?.querySelector('span');
        if (checkoutQtySpan) {
            let currentQty = parseInt(checkoutQtySpan.textContent);
            if (currentQty !== val) {
                let diff = val - currentQty;
                let btnSelector = diff > 0 ? '.qty-inc' : '.qty-dec';
                let btn = document.querySelector('#order-items-list ' + btnSelector);
                for(let i=0; i<Math.abs(diff); i++) {
                    if(btn) btn.click();
                }
            }
        }
    }
    function fmbPulseCheckoutBanner() {
        var el = document.getElementById('checkout-area');
        if (!el) return;
        el.classList.add('ring-4', 'ring-primary', 'ring-offset-4', 'rounded-3xl');
        window.setTimeout(function () {
            el.classList.remove('ring-4', 'ring-primary', 'ring-offset-4', 'rounded-3xl');
        }, 1600);
    }
    function fmbGoToCheckout() {
        syncTopQtyToCheckout();
        var target = document.getElementById('checkout-area');
        if (target) {
            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            fmbPulseCheckoutBanner();
        }
    }
    function fmbHandleSingleAddToCart(btn) {
        if (!btn || btn.disabled) return;
        var originalContent = btn.dataset.originalHtml || btn.innerHTML;
        btn.dataset.originalHtml = originalContent;

        var topQtyInput = document.getElementById('top-qty-input');
        var qty = topQtyInput ? (parseInt(topQtyInput.value, 10) || 1) : 1;
        var productId = <?php echo absint($main_id); ?>;

        btn.disabled = true;
        btn.innerHTML = '<span>⏳ যোগ হচ্ছে...</span>';

        var formData = new FormData();
        formData.append('action', 'fmb_ajax_add_to_cart');
        formData.append('product_id', productId);
        formData.append('quantity', qty);

        fetch('<?php echo admin_url("admin-ajax.php"); ?>', {
            method: 'POST',
            body: formData
        })
        .then(function (res) { return res.json(); })
        .then(function (data) {
            if (data.success) {
                btn.innerHTML = '<span>✓ কার্টে যুক্ত হয়েছে!</span>';
                btn.style.setProperty('background-color', '#10B981', 'important');
                btn.style.setProperty('color', '#FFFFFF', 'important');

                setTimeout(function () {
                    btn.disabled = false;
                    btn.innerHTML = originalContent;
                    btn.style.removeProperty('background-color');
                    btn.style.removeProperty('color');
                }, 3000);

                // Update bottom sticky rich cart & slide up
                if (typeof window.fmbRefreshRichCart === 'function') {
                    window.fmbRefreshRichCart(true);
                }

                // Update header cart counter if present
                var headerCounters = document.querySelectorAll('#header-cart-count, .header-cart-count, .cart-count');
                headerCounters.forEach(function(el) {
                    if (data.data && typeof data.data.count !== 'undefined') {
                        el.textContent = data.data.count;
                        el.classList.remove('hidden');
                    }
                });

                if (typeof window.fmbTrackPixelAddToCartOnce === 'function') {
                    window.fmbTrackPixelAddToCartOnce();
                }
            } else {
                btn.disabled = false;
                btn.innerHTML = originalContent;
                alert(data.data && data.data.message ? data.data.message : 'কার্টে যোগ করতে সমস্যা হয়েছে।');
            }
        })
        .catch(function () {
            btn.disabled = false;
            btn.innerHTML = originalContent;
            alert('কার্টে যোগ করতে সমস্যা হয়েছে। অনুগ্রহ করে আবার চেষ্টা করুন।');
        });
    }

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('#fmb-add-to-cart-btn, .fmb-single-atc-btn');
        if (btn) {
            e.preventDefault();
            e.stopPropagation();
            fmbHandleSingleAddToCart(btn);
        }
    });
    </script>

    <?php if ($template_variant === 'dark_luxe') : ?>
    <!-- 👑 Full-Page Immersive Dark Mode Stylesheet -->
    <style>
        body, .fmb-product-page.fmb-single-theme-dark_luxe {
            background-color: #070a13 !important;
            color: #f8fafc !important;
        }
        .fmb-single-theme-dark_luxe #checkout-area > div {
            background: #0d1322 !important;
            border: 1.5px solid rgba(168, 85, 247, 0.4) !important;
            box-shadow: 0 0 35px rgba(0,0,0,0.9), 0 0 20px rgba(168,85,247,0.15) !important;
        }
        .fmb-single-theme-dark_luxe #checkout-area .bg-gradient-to-r {
            background: linear-gradient(135deg, #1e1136 0%, #0f172a 100%) !important;
            border-bottom: 1px solid rgba(168, 85, 247, 0.3) !important;
            color: #fbbf24 !important;
        }
        .fmb-single-theme-dark_luxe #checkout-area h3 {
            color: #fbbf24 !important;
            text-shadow: 0 0 10px rgba(251,191,36,0.3);
        }
        .fmb-single-theme-dark_luxe #multi-product-checkout-form {
            background: #0d1322 !important;
        }
        .fmb-single-theme-dark_luxe #multi-product-checkout-form .bg-gray-50 {
            background: #080c16 !important;
            border: 1px solid rgba(255,255,255,0.08) !important;
            color: #f8fafc !important;
        }
        .fmb-single-theme-dark_luxe #multi-product-checkout-form h4 {
            color: #fbbf24 !important;
        }
        .fmb-single-theme-dark_luxe #multi-product-checkout-form input[type="text"],
        .fmb-single-theme-dark_luxe #multi-product-checkout-form input[type="tel"],
        .fmb-single-theme-dark_luxe #multi-product-checkout-form textarea {
            background: #080c16 !important;
            border: 1.5px solid rgba(255,255,255,0.15) !important;
            color: #ffffff !important;
        }
        .fmb-single-theme-dark_luxe #multi-product-checkout-form input:focus,
        .fmb-single-theme-dark_luxe #multi-product-checkout-form textarea:focus {
            border-color: #a855f7 !important;
            box-shadow: 0 0 12px rgba(168,85,247,0.4) !important;
        }
        .fmb-single-theme-dark_luxe #multi-product-checkout-form .border.rounded-lg {
            background: #080c16 !important;
            border-color: rgba(255,255,255,0.1) !important;
        }
        .fmb-single-theme-dark_luxe #multi-product-checkout-form label.flex {
            background: #080c16 !important;
            border-color: rgba(255,255,255,0.08) !important;
            color: #e2e8f0 !important;
        }
        .fmb-single-theme-dark_luxe #multi-product-checkout-form .shipping-price-text {
            color: #fbbf24 !important;
        }
        .fmb-single-theme-dark_luxe #multi-product-checkout-form .bg-white {
            background: #080c16 !important;
            border-color: rgba(255,255,255,0.1) !important;
            color: #e2e8f0 !important;
        }
        .fmb-single-theme-dark_luxe #multi-product-checkout-form .text-gray-800,
        .fmb-single-theme-dark_luxe #multi-product-checkout-form .text-gray-700,
        .fmb-single-theme-dark_luxe #multi-product-checkout-form .text-gray-900 {
            color: #f8fafc !important;
        }
        .fmb-single-theme-dark_luxe #multi-product-checkout-form .text-gray-600,
        .fmb-single-theme-dark_luxe #multi-product-checkout-form .text-gray-500 {
            color: #94a3b8 !important;
        }
        .fmb-single-theme-dark_luxe #multi-product-checkout-form #summary-subtotal,
        .fmb-single-theme-dark_luxe #multi-product-checkout-form #summary-shipping {
            color: #e2e8f0 !important;
        }
        .fmb-single-theme-dark_luxe #multi-product-checkout-form #final-total {
            color: #fbbf24 !important;
            text-shadow: 0 0 10px rgba(251,191,36,0.4) !important;
        }
        .fmb-single-theme-dark_luxe #multi-product-checkout-form button[type="submit"] {
            background: linear-gradient(135deg, #f59e0b 0%, #ea580c 100%) !important;
            color: #000000 !important;
            font-weight: 900 !important;
            box-shadow: 0 0 25px rgba(245,158,11,0.5) !important;
        }
        .fmb-single-theme-dark_luxe .fmb-best-selling-wrapper {
            background: #070a13 !important;
            border-color: rgba(255,255,255,0.08) !important;
        }
        .fmb-single-theme-dark_luxe .fmb-best-selling-wrapper h2 {
            color: #ffffff !important;
        }
        .fmb-single-theme-dark_luxe .fmb-best-selling-card {
            background: #0d1322 !important;
            border: 1px solid rgba(255,255,255,0.1) !important;
            color: #ffffff !important;
        }
        .fmb-single-theme-dark_luxe .fmb-best-selling-card h4 {
            color: #e2e8f0 !important;
        }
        .fmb-single-theme-dark_luxe .fmb-best-selling-card .text-primary {
            color: #fbbf24 !important;
        }
        .fmb-single-theme-dark_luxe #fmb-sticky-cta {
            background: #080c16 !important;
            border-color: rgba(255,255,255,0.15) !important;
        }
        .fmb-single-theme-dark_luxe #fmb-sticky-cta a {
            background: linear-gradient(135deg, #f59e0b 0%, #ea580c 100%) !important;
            color: #000 !important;
            font-weight: 900 !important;
            box-shadow: 0 0 20px rgba(245,158,11,0.4) !important;
        }
    </style>
    <?php endif; ?>

    <?php
    // 🎨 Load Product-Specific Design Variant
    $file_variant_name = str_replace('_', '-', $template_variant);
    $variant_path = get_template_directory() . "/template-parts/single-product/variant-{$file_variant_name}.php";
    if (!file_exists($variant_path)) {
        $variant_path = locate_template("template-parts/single-product/variant-{$file_variant_name}.php");
    }
    if (!$variant_path || !file_exists($variant_path)) {
        $variant_path = get_template_directory() . "/template-parts/single-product/variant-classic.php";
    }
    if ($variant_path && file_exists($variant_path)) {
        include $variant_path;
    }
    ?>

    <div id="checkout-area" class="max-w-2xl mx-auto px-3 sm:px-4 mt-6 sm:mt-12 transition-shadow duration-300">
        <div class="bg-white rounded-2xl shadow-2xl overflow-hidden border border-gray-200">
            <div class="bg-gradient-to-r from-blue-700 to-blue-900 p-4 sm:p-5 text-white text-center">
                <h3 class="text-lg sm:text-xl font-bold">অর্ডার কনফার্ম করুন</h3>
            </div>

            <form id="multi-product-checkout-form" class="p-3.5 sm:p-6 md:p-8 space-y-4 sm:space-y-6">
                
                <div class="bg-gray-50 p-4 rounded-xl border border-gray-200">
                    <h4 class="font-bold text-gray-700 mb-3 text-sm uppercase">অর্ডার লিস্ট:</h4>
                    <div id="order-items-list" class="space-y-3">
                    </div>
                </div>

                <?php if (!empty($order_bumps)): ?>
                <div class="fmb-order-bumps space-y-3 mb-6">
                    <?php foreach($order_bumps as $bump): 
                        $b_id = absint($bump['id']);
                        if (!$b_id) continue;
                        $b_product = wc_get_product($b_id);
                        if (!$b_product) continue;
                        
                        $b_title = !empty($bump['title']) ? $bump['title'] : $b_product->get_name();
                        $b_desc = $bump['desc'] ?? '';
                        $b_price = (!empty($bump['price_override'])) ? floatval($bump['price_override']) : $b_product->get_price();
                        $b_logic_qty = absint($bump['logic_qty'] ?? 0);
                        $b_logic_type = sanitize_text_field($bump['logic_type'] ?? '');
                        $b_logic_value = floatval($bump['logic_value'] ?? 0);
                        $b_bg = !empty($bump['bg']) ? $bump['bg'] : '#fffbeb';
                        $b_border = !empty($bump['border']) ? $bump['border'] : '#f59e0b';
                        $b_img = wp_get_attachment_image_url($b_product->get_image_id(), 'thumbnail') ?: wc_placeholder_img_src();
                    ?>
                    <div class="order-bump-item border-2 rounded-xl p-4 relative transition-all shadow-md hover:shadow-lg" style="background-color: <?php echo esc_attr($b_bg); ?>; border-color: <?php echo esc_attr($b_border); ?>;">
                        <!-- Highlight Badge -->
                        <div class="absolute -top-3 right-4 bg-red-600 text-white text-[10px] md:text-xs font-bold px-3 py-1 rounded-full shadow-sm flex items-center gap-1 animate-pulse">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                            স্পেশাল অফার
                        </div>

                        <label class="flex items-start gap-4 cursor-pointer">
                            <div class="mt-1.5 flex-shrink-0">
                                <input type="checkbox" class="ob-checkbox w-6 h-6 text-blue-600 rounded border-gray-400 focus:ring-blue-500 shadow-sm" 
                                    data-id="<?php echo esc_attr($b_id); ?>"
                                    data-name="<?php echo esc_attr($b_title); ?>"
                                    data-price="<?php echo esc_attr($b_price); ?>"
                                    data-img="<?php echo esc_url($b_img); ?>"
                                    data-free-delivery="<?php echo fmb_is_effectively_free_delivery_catalog($b_id) ? '1' : '0'; ?>"
                                    data-logic-qty="<?php echo esc_attr($b_logic_qty); ?>"
                                    data-logic-type="<?php echo esc_attr($b_logic_type); ?>"
                                    data-logic-value="<?php echo esc_attr($b_logic_value); ?>">
                            </div>
                            <div class="flex-1">
                                <div class="flex items-center gap-3">
                                    <img src="<?php echo esc_url($b_img); ?>" class="w-12 h-12 object-contain rounded bg-white">
                                    <div>
                                        <h4 class="font-bold text-gray-800 text-sm md:text-base flex items-center flex-wrap gap-2">
                                            <?php echo esc_html($b_title); ?> 
                                            <span class="text-red-600 font-extrabold">
                                                (<?php echo esc_html($b_price); ?>৳)
                                            </span>
                                        </h4>
                                        <?php if($b_desc): ?>
                                            <p class="text-xs md:text-sm text-gray-700 mt-1 leading-snug"><?php echo nl2br(esc_html($b_desc)); ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </label>
                        <!-- Quantity Selector -->
                        <div class="ob-qty-wrapper hidden mt-3 pt-3 border-t border-dashed border-gray-400 flex items-center justify-between">
                            <span class="text-sm font-bold text-gray-800">পরিমাণ:</span>
                            <div class="flex items-center border border-gray-400 rounded bg-white overflow-hidden">
                                <button type="button" class="ob-qty-minus px-3 text-gray-600 hover:bg-gray-100 font-bold">-</button>
                                <input type="number" class="ob-qty-input w-12 text-center border-none focus:ring-0 text-sm p-1 m-0 pointer-events-none" value="1" min="1" readonly>
                                <button type="button" class="ob-qty-plus px-3 text-gray-600 hover:bg-gray-100 font-bold">+</button>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <div class="space-y-4">
                    <input type="text" name="landing_billing_first_name" id="landing_billing_first_name" required placeholder="আপনার নাম" autocomplete="name" class="w-full border p-3 rounded-lg bg-gray-50 focus:ring-2 focus:ring-blue-500 outline-none">
                    <input type="tel" name="landing_billing_phone" id="landing_billing_phone" required placeholder="মোবাইল নাম্বার" autocomplete="tel" class="w-full border p-3 rounded-lg bg-gray-50 focus:ring-2 focus:ring-blue-500 outline-none">
                    <input type="email" name="landing_billing_email" id="landing_billing_email" placeholder="ইমেইল এড্রেস (ঐচ্ছিক)" autocomplete="email" class="w-full border p-3 rounded-lg bg-gray-50 focus:ring-2 focus:ring-blue-500 outline-none">
                    <textarea name="landing_billing_address_1" id="landing_billing_address_1" required placeholder="ঠিকানা (বাসা নং, রোড, এলাকা)..." autocomplete="street-address" rows="2" class="w-full border p-3 rounded-lg bg-gray-50 focus:ring-2 focus:ring-blue-500 outline-none"></textarea>
                    <input type="hidden" name="_fbp" class="fmb-hidden-fbp">
                    <input type="hidden" name="_fbc" class="fmb-hidden-fbc">
                </div>

                <div class="border rounded-lg overflow-hidden">
                    <?php
                    // Check for custom delivery charge
                    $custom_charges = get_option('cdc_product_delivery_charges', array());
                    $p_id = $main_id;
                    $custom_amt = isset($custom_charges[$p_id]) ? floatval($custom_charges[$p_id]) : -1;

                    if ($custom_amt >= 0) {
                        // Custom Charge Found - Show Single Option
                        ?>
                        <label class="flex justify-between p-3 border-b bg-blue-50/50 cursor-pointer">
                            <span class="flex items-center gap-2">
                                <input type="radio" name="delivery_area" value="<?php echo esc_attr($custom_amt); ?>" checked> 
                                Delivery Charge
                            </span>
                            <span class="font-bold shipping-price-text"><?php echo esc_html($custom_amt); ?>৳</span>
                        </label>
                        <?php
                    } else {
                        $rates_cached = fmb_wc_cached_zone_delivery_rates();
                        $is_first     = true;

                        if ( ! $rates_cached['has'] ) {
                            ?>
                            <label class="flex justify-between p-3 border-b bg-blue-50/50 cursor-pointer">
                                <span class="flex items-center gap-2">
                                    <input type="radio" name="delivery_area" value="70" checked> ঢাকা সিটির ভিতরে
                                </span>
                                <span class="font-bold shipping-price-text">70৳</span>
                            </label>
                            <label class="flex justify-between p-3 border-b cursor-pointer hover:bg-gray-50">
                                <span class="flex items-center gap-2">
                                    <input type="radio" name="delivery_area" value="130"> ঢাকার বাহিরে
                                </span>
                                <span class="font-bold shipping-price-text">130৳</span>
                            </label>
                            <?php
                        } else {
                            foreach ( $rates_cached['methods'] as $row ) {
                                $cost  = $row['cost'];
                                $label = $row['label'];
                                ?>
                                    <label class="flex justify-between p-3 border-b bg-blue-50/50 cursor-pointer hover:bg-gray-100 transition">
                                        <span class="flex items-center gap-2">
                                            <input type="radio" name="delivery_area" value="<?php echo esc_attr( $cost ); ?>" <?php echo $is_first ? 'checked' : ''; ?>>
                                            <?php echo esc_html( $label ); ?>
                                        </span>
                                        <span class="font-bold shipping-price-text"><?php echo esc_html( $cost ); ?>৳</span>
                                    </label>
                                <?php
                                $is_first = false;
                            }
                        }
                    }
                    ?>
                </div>

                <div class="mt-4">
                    <h4 class="font-bold text-gray-700 mb-3 text-sm uppercase">পেমেন্ট মেথড:</h4>
                    <div class="bg-white p-3 rounded-lg border border-gray-200">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" name="payment_method" value="cod" checked class="form-radio text-primary h-5 w-5 accent-primary">
                            <span class="font-bold text-gray-800">ক্যাশ অন ডেলিভারি</span>
                        </label>
                        <p class="text-xs text-gray-500 mt-1 ml-7">পণ্য হাতে পেয়ে মূল্য পরিশোধ করবেন</p>
                    </div>
                </div>

                <!-- Invoice Summary -->
                <div class="mt-6 border-t-2 border-dashed border-gray-300 pt-4 space-y-2">
                    <div class="flex justify-between text-sm text-gray-600 font-medium">
                        <span>সাবটোটাল</span>
                        <span id="summary-subtotal" class="font-bold">0৳</span>
                    </div>
                    <div class="flex justify-between text-sm text-gray-600 font-medium pb-3 border-b border-gray-200">
                        <span>ডেলিভারি চার্জ</span>
                        <span id="summary-shipping" class="font-bold">0৳</span>
                    </div>
                    <div class="flex justify-between items-center text-xl font-bold pt-2 text-gray-900 mb-4">
                        <span>সর্বমোট:</span>
                        <span id="final-total" class="text-primary">0৳</span>
                    </div>
                </div>

                <input type="hidden" name="order_items" id="hidden_order_items">
                <?php wp_nonce_field('fmb_multi_product_order_nonce', 'fmb_multi_nonce'); ?>

                <button type="submit" id="btn-confirm-order" class="fmb-btn-primary fmb-order-now-btn w-full text-white text-xl font-bold py-4 rounded-xl transition shadow-lg flex justify-center items-center gap-2" style="background-color: var(--btn-bg, var(--primary)) !important; color: var(--btn-text, #ffffff) !important;">
                    অর্ডার কনফার্ম করুন <span id="btn-final-total" class="bg-black/20 px-3 py-1 rounded-full text-lg">0৳</span>
                </button>
            </form>
        </div>
    </div>

</div>

    <?php 
    // Best Selling / Related products below checkout
    $best_selling_args = array(
        'post_type' => 'product',
        'posts_per_page' => 10,
        'post__not_in' => array($main_id),
        'post_status' => 'publish',
        'meta_key' => 'total_sales',
        'orderby' => 'meta_value_num'
    );
    $best_selling_query = new WP_Query($best_selling_args);
    
    if($best_selling_query->have_posts()): 
    ?>
    <div class="fmb-best-selling-wrapper py-12 bg-gray-50 border-t border-b border-gray-200 mt-12">
        <div class="max-w-7xl mx-auto px-4">
            <h2 class="text-2xl font-bold text-center mb-8 text-gray-800">বেশি বিক্রিত ও জনপ্রিয় প্রডাক্টসমূহ</h2>
            <div class="grid grid-cols-2 md:grid-cols-5 gap-3 md:gap-4">
                <?php while($best_selling_query->have_posts()): $best_selling_query->the_post(); 
                    global $product;
                    $u_id = $product->get_id();
                    $u_img = wp_get_attachment_image_url($product->get_image_id(), 'thumbnail');
                    if (!$u_img) $u_img = wc_placeholder_img_src();
                    $u_price = $product->get_price() ?: '0';
                    $u_reg_price = $product->get_regular_price() ?: '0';
                    $u_sale_price = $product->get_sale_price() ?: '0';
                    $u_savings = ($u_reg_price > $u_sale_price) ? (floatval($u_reg_price) - floatval($u_sale_price)) : 0;
                ?>
                <div class="fmb-best-selling-card bg-white p-3 md:p-4 rounded-xl shadow border border-gray-200 text-center relative hover:shadow-lg transition group flex flex-col">
                    <?php if($u_savings > 0): ?>
                        <div class="absolute top-2 left-2 bg-red-500 text-white text-[10px] md:text-xs font-bold px-2 py-1 rounded shadow-sm z-10">
                            সেভ <?php echo esc_html($u_savings); ?>৳
                        </div>
                    <?php endif; ?>
                    <?php if(fmb_is_effectively_free_delivery_catalog($u_id)) : ?>
                        <div class="absolute top-2 right-2 bg-green-500 text-white text-[10px] md:text-xs font-bold px-2 py-1 rounded shadow-sm z-10">
                            ফ্রি ডেলিভারি
                        </div>
                    <?php endif; ?>
                    
                    <!-- Hover Overlay with 2 options (চোখ + ডিটেলস) -->
                    <div class="relative mb-3 group/bsimg">
                        <img src="<?php echo esc_url($u_img); ?>" alt="<?php echo esc_attr(get_the_title()); ?>" class="w-full h-24 md:h-32 object-contain relative z-0">
                        <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex justify-center items-center gap-2 rounded pointer-events-none group-hover:pointer-events-auto z-10">
                            <!-- Quick View -->
                            <button type="button" class="quick-view-trigger bg-white text-gray-800 h-8 w-8 rounded-full flex items-center justify-center hover:bg-primary hover:text-white transition-colors shadow" data-product-id="<?php echo $u_id; ?>" title="Quick View">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                            </button>
                            <!-- Details -->
                            <a href="<?php echo esc_url(get_permalink($u_id)); ?>" class="bg-white text-gray-800 h-8 w-8 rounded-full flex items-center justify-center hover:bg-primary hover:text-white transition-colors shadow" title="বিস্তারিত">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </a>
                        </div>
                    </div>
                    
                    <h4 class="font-bold text-xs md:text-sm mb-1 line-clamp-2 leading-tight h-8 md:h-10 text-gray-800" title="<?php echo esc_attr(get_the_title()); ?>"><?php the_title(); ?></h4>
                    <div class="text-primary font-bold text-sm md:text-base mb-3 mt-auto"><?php echo esc_html($u_price); ?>৳</div>
                    
                    <button type="button" class="btn-add-upsell w-full bg-gray-800 text-white text-[11px] md:text-xs py-2 md:py-2.5 rounded hover:bg-black transition font-bold"
                        data-id="<?php echo $u_id; ?>"
                        data-name="<?php echo esc_attr(get_the_title()); ?>"
                        data-price="<?php echo esc_attr($u_price); ?>"
                        data-discount="<?php echo esc_attr($u_savings); ?>"
                        data-img="<?php echo esc_url($u_img); ?>"
                        data-free-delivery="<?php echo fmb_is_effectively_free_delivery_catalog($u_id) ? '1' : '0'; ?>">
                        + এড করুন
                    </button>
                </div>
                <?php endwhile; wp_reset_postdata(); ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

<script>
    var mainProduct = {
        id: <?php echo $main_id; ?>,
        name: "<?php echo esc_js(get_the_title()); ?>",
        price: <?php echo $main_price ? $main_price : '0'; ?>,
        img: "<?php echo $main_image; ?>",
        qty: 1,
        freeDelivery: <?php echo fmb_is_effectively_free_delivery_catalog($main_id) ? 'true' : 'false'; ?>
    };

    // ── Safe Meta Pixel Caller with Console Debug Logger ──
    function safeFbq(type, eventName, params) {
        if (typeof window.fbq === 'function') {
            window.fbq(type, eventName, params);
            console.log(
                '%c[Meta Pixel: ' + eventName + ' Fired] %c(' + type + ')',
                'background: #1877F2; color: #FFFFFF; font-weight: bold; padding: 3px 8px; border-radius: 4px;',
                'color: #666; font-size: 11px;',
                params
            );
        } else {
            var attempts = 0;
            var retryInterval = setInterval(function() {
                attempts++;
                if (typeof window.fbq === 'function') {
                    clearInterval(retryInterval);
                    window.fbq(type, eventName, params);
                    console.log(
                        '%c[Meta Pixel: ' + eventName + ' Fired (Async Loaded)] %c(' + type + ')',
                        'background: #1877F2; color: #FFFFFF; font-weight: bold; padding: 3px 8px; border-radius: 4px;',
                        'color: #666; font-size: 11px;',
                        params
                    );
                }
                if (attempts > 20) {
                    clearInterval(retryInterval);
                    console.warn('[Meta Pixel] fbq function not found for event:', eventName);
                }
            }, 500);
        }
    }

    // ── Facebook Pixel: AddToCart (Fires Once) ──
    window.fmbTrackPixelAddToCartOnce = function () {
        if (window.__fmbPixelAddToCartDone) return;
        window.__fmbPixelAddToCartDone = true;
        
        var q = parseInt(mainProduct.qty, 10) || 1;
        var unit = parseFloat(mainProduct.price) || 0;
        safeFbq('track', 'AddToCart', {
            content_ids: [String(mainProduct.id)],
            content_type: 'product',
            value: unit * q,
            currency: 'BDT',
            content_name: mainProduct.name,
            num_items: q
        });
    };

    // ── Facebook Pixel: InitiateCheckout (Fires Once when Checkout Form is Visible) ──
    var initiateCheckoutFired = false;
    function fireInitiateCheckout() {
        if (initiateCheckoutFired) return;
        initiateCheckoutFired = true;

        var q = parseInt(mainProduct.qty, 10) || 1;
        var unit = parseFloat(mainProduct.price) || 0;
        var totalVal = unit * q;

        var finalTotalEl = document.getElementById('final-total');
        if (finalTotalEl) {
            var parsedTotal = parseFloat(finalTotalEl.textContent.replace(/[^\d.]/g, ''));
            if (!isNaN(parsedTotal) && parsedTotal > 0) {
                totalVal = parsedTotal;
            }
        }

        safeFbq('track', 'InitiateCheckout', {
            content_ids: [String(mainProduct.id)],
            content_type: 'product',
            value: totalVal,
            currency: 'BDT',
            content_name: mainProduct.name,
            num_items: q
        });
    }

    // ── Scroll & Visibility Tracking Initialization ──
    function initProductTracking() {
        var scrollFired = false;
        var addToCartScrollFired = false;
        var checkoutArea = document.getElementById('checkout-area');

        function getCheckoutDistance() {
            if (!checkoutArea) return 0;
            var rect = checkoutArea.getBoundingClientRect();
            var scrollTop = window.pageYOffset || document.documentElement.scrollTop || 0;
            return rect.top + scrollTop;
        }

        function handleScrollTracking() {
            var scrollTop = window.pageYOffset || document.documentElement.scrollTop || 0;
            var checkoutTop = getCheckoutDistance();

            // পেজের শুরু থেকে চেকআউট ফর্ম পর্যন্ত দূরত্বকে ১০০ দিয়ে ভাগ করে তার ২০% স্ক্রল হলে
            if (checkoutTop > 0) {
                var threshold20 = (checkoutTop / 100) * 20;
                if (scrollTop >= threshold20) {
                    if (!scrollFired) {
                        safeFbq('trackCustom', 'Scroll', { percent: 20 });
                        scrollFired = true;
                    }
                    if (!addToCartScrollFired) {
                        window.fmbTrackPixelAddToCartOnce();
                        addToCartScrollFired = true;
                    }
                }
            }

            // চেকআউট ফর্ম স্ক্রিনে ভিজিবল হলে InitiateCheckout ট্রিগার (Fallback Check)
            if (!initiateCheckoutFired && checkoutArea) {
                var rect = checkoutArea.getBoundingClientRect();
                var windowHeight = window.innerHeight || document.documentElement.clientHeight || 0;
                if (rect.top <= (windowHeight - 20) && rect.bottom >= 0) {
                    fireInitiateCheckout();
                }
            }
        }

        window.addEventListener('scroll', handleScrollTracking, { passive: true });
        window.addEventListener('resize', handleScrollTracking, { passive: true });

        // IntersectionObserver for Instant & Accurate InitiateCheckout
        if (checkoutArea && 'IntersectionObserver' in window) {
            var observer = new IntersectionObserver(function(entries) {
                if (entries[0].isIntersecting && !initiateCheckoutFired) {
                    fireInitiateCheckout();
                }
            }, {
                threshold: 0,
                rootMargin: '0px 0px 50px 0px'
            });
            observer.observe(checkoutArea);
        }

        // Populate CAPI cookies into hidden form fields
        function getCookie(name) {
            var m = document.cookie.match(new RegExp('(^|;\\s*)' + name + '=([^;]*)'));
            return m ? decodeURIComponent(m[2]) : '';
        }
        var fbpVal = getCookie('_fbp');
        var fbcVal = getCookie('_fbc');
        if (!fbcVal && window.location.search) {
            var urlParams = new URLSearchParams(window.location.search);
            var fbclid = urlParams.get('fbclid');
            if (fbclid) fbcVal = 'fb.1.' + Math.floor(Date.now() / 1000) + '.' + fbclid;
        }
        document.querySelectorAll('.fmb-hidden-fbp').forEach(function(el) { el.value = fbpVal; });
        document.querySelectorAll('.fmb-hidden-fbc').forEach(function(el) { el.value = fbcVal; });

        // Real-time Advanced Matching Sync on customer input
        var phoneEl = document.getElementById('landing_billing_phone');
        var nameEl  = document.getElementById('landing_billing_first_name');
        var emailEl = document.getElementById('landing_billing_email');

        function syncUserAdvancedMatching() {
            var rawPhone = phoneEl ? phoneEl.value.replace(/\D/g, '') : '';
            if (rawPhone.length === 11 && rawPhone.startsWith('01')) {
                rawPhone = '88' + rawPhone;
            }
            var rawName = nameEl ? nameEl.value.trim() : '';
            var parts = rawName ? rawName.split(/\s+/) : [];
            var fn = parts[0] || '';
            var ln = parts.slice(1).join(' ') || fn;
            var rawEmail = emailEl ? emailEl.value.trim().toLowerCase() : '';

            var userMatch = { country: 'bd' };
            if (rawPhone) userMatch.ph = rawPhone;
            if (fn) userMatch.fn = fn.toLowerCase();
            if (ln) userMatch.ln = ln.toLowerCase();
            if (rawEmail) userMatch.em = rawEmail;

            if (typeof fbq === 'function' && (rawPhone || fn || rawEmail)) {
                var pixelId = '<?php echo esc_js(get_option("fmb_fb_pixel_id")); ?>';
                if (pixelId) {
                    fbq('setUserProperties', pixelId, userMatch);
                }
            }
        }

        if (phoneEl) {
            phoneEl.addEventListener('blur', syncUserAdvancedMatching);
            phoneEl.addEventListener('change', syncUserAdvancedMatching);
        }
        if (nameEl) {
            nameEl.addEventListener('blur', syncUserAdvancedMatching);
        }
        if (emailEl) {
            emailEl.addEventListener('blur', syncUserAdvancedMatching);
        }

        // Initial check on page load
        handleScrollTracking();
    }

    if (document.readyState === 'loading') {
        document.addEventListener("DOMContentLoaded", initProductTracking);
    } else {
        initProductTracking();
    }
</script>

<!-- ===================================================== -->
<!-- Mobile Sticky Bottom CTA Button -->
<!-- ===================================================== -->
<div id="fmb-sticky-cta" class="md:hidden fixed bottom-0 left-0 right-0 z-[9990] p-3 bg-white border-t border-gray-200 shadow-[0_-4px_20px_rgba(0,0,0,0.1)] transition-transform duration-300 translate-y-0">
    <a href="#checkout-area" onclick="event.preventDefault(); fmbGoToCheckout();" class="fmb-order-now-btn flex items-center justify-center gap-3 w-full text-white text-lg font-bold py-4 rounded-xl shadow-lg transition-colors" style="background: var(--btn-bg, var(--primary)) !important; color: var(--btn-text, #ffffff) !important;">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
        </svg>
        এখনই অর্ডার করুন
    </a>
</div>

<script>
    // Hide Sticky CTA when checkout form is in view
    (function() {
        var stickyCta = document.getElementById('fmb-sticky-cta');
        var checkoutSection = document.getElementById('checkout-area');

        if (stickyCta && checkoutSection) {
            var observer = new IntersectionObserver(function(entries) {
                entries.forEach(function(entry) {
                    if (entry.isIntersecting) {
                        stickyCta.style.transform = 'translateY(100%)';
                        stickyCta.style.opacity = '0';
                        stickyCta.style.pointerEvents = 'none';
                    } else {
                        stickyCta.style.transform = 'translateY(0)';
                        stickyCta.style.opacity = '1';
                        stickyCta.style.pointerEvents = 'auto';
                    }
                });
            }, { threshold: 0.05 });

            observer.observe(checkoutSection);
        }
    })();
</script>

<?php endwhile; get_footer(); ?>