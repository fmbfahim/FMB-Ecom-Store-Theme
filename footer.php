</main> <?php get_template_part('template-parts/footer/site-footer'); ?>

    <div id="fmb-quick-order-modal" class="fmb-modal-overlay fixed inset-0 bg-black/60 backdrop-blur-sm z-[9999] flex justify-center items-center p-4" style="display: none;">
        <div class="fmb-modal-content bg-white w-full max-w-lg rounded-2xl shadow-2xl overflow-hidden relative animate-slideUp">
            
            <div class="flex justify-between items-start p-5 border-b border-gray-100 bg-gray-50/50">
                <div>
                    <h3 class="text-xl font-bold text-gray-900 leading-tight">ক্যাশ অন ডেলিভারিতে</h3>
                    <p class="text-gray-600 text-sm">অর্ডার করতে আপনার তথ্য দিন</p>
                </div>
                <span class="fmb-close-modal cursor-pointer text-gray-400 hover:text-red-500 transition p-1">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                </span>
            </div>

            <form id="fmb-quick-order-form" class="p-6 space-y-6 max-h-[80vh] overflow-y-auto">
                <?php wp_nonce_field('fmb_multi_product_order_nonce', 'fmb_multi_nonce'); ?>
                
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-1">আপনার নাম <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd" /></svg>
                            </span>
                            <input type="text" name="popup_billing_first_name" id="popup_billing_first_name" required placeholder="আপনার নাম" autocomplete="name" class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary outline-none transition">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-1">ফোন নাম্বার <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path d="M2 3a1 1 0 011-1h2.153a1 1 0 01.986.836l.74 4.435a1 1 0 01-.54 1.06l-1.548.773a11.037 11.037 0 006.105 6.105l.774-1.548a1 1 0 011.059-.54l4.435.74a1 1 0 01.836.986V17a1 1 0 01-1 1h-2C7.82 18 2 12.18 2 5V3z" /></svg>
                            </span>
                            <input type="tel" name="popup_billing_phone" id="popup_billing_phone" required placeholder="ফোন নাম্বার" autocomplete="tel" class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary outline-none transition">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-1">ইমেইল এড্রেস <span class="text-xs text-gray-400 font-normal">(ঐচ্ছিক)</span></label>
                        <input type="email" name="popup_billing_email" id="popup_billing_email" placeholder="ইমেইল এড্রেস" autocomplete="email" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary outline-none transition">
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-1">এড্রেস <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3 pt-3 flex items-start text-gray-400">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd" /></svg>
                            </span>
                            <textarea name="popup_billing_address_1" id="popup_billing_address_1" required placeholder="বাসা নং, রোড নং, এলাকা..." autocomplete="street-address" rows="2" class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary outline-none transition resize-none"></textarea>
                        </div>
                    </div>
                    <input type="hidden" name="_fbp" class="fmb-hidden-fbp">
                    <input type="hidden" name="_fbc" class="fmb-hidden-fbc">
                </div>

                <div class="border border-gray-200 rounded-xl overflow-hidden">
                    <div id="fmb-delivery-options">
                    <?php
                    $fmb_popup_rates = function_exists( 'fmb_wc_cached_zone_delivery_rates' ) ? fmb_wc_cached_zone_delivery_rates() : array( 'methods' => array(), 'has' => false );
                    $is_first        = true;

                    if ( ! $fmb_popup_rates['has'] ) {
                         ?>
                        <label class="flex justify-between items-center p-4 border-b border-gray-200 cursor-pointer hover:bg-gray-50 transition bg-blue-50/50">
                            <div class="flex items-center">
                                <input type="radio" name="delivery_area" value="70" class="form-radio text-primary h-5 w-5 accent-primary" checked>
                                <span class="ml-3 font-medium text-gray-800">ঢাকা সিটির ভিতরে</span>
                            </div>
                            <span class="font-bold text-gray-900">Tk 70.00</span>
                        </label>
                        <label class="flex justify-between items-center p-4 border-b border-gray-200 cursor-pointer hover:bg-gray-50 transition">
                            <div class="flex items-center">
                                <input type="radio" name="delivery_area" value="130" class="form-radio text-primary h-5 w-5 accent-primary">
                                <span class="ml-3 font-medium text-gray-800">ঢাকার বাহিরে</span>
                            </div>
                            <span class="font-bold text-gray-900">Tk 130.00</span>
                        </label>
                         <?php
                    } else {
                        foreach ( $fmb_popup_rates['methods'] as $row ) {
                            $cost  = $row['cost'];
                            $label = $row['label'];
                            ?>
                                <label class="flex justify-between items-center p-4 border-b border-gray-200 cursor-pointer hover:bg-gray-50 transition <?php echo $is_first ? 'bg-blue-50/50' : ''; ?>">
                                    <div class="flex items-center">
                                        <input type="radio" name="delivery_area" value="<?php echo esc_attr( $cost ); ?>" class="form-radio text-primary h-5 w-5 accent-primary" <?php echo $is_first ? 'checked' : ''; ?>>
                                        <span class="ml-3 font-medium text-gray-800"><?php echo esc_html( $label ); ?></span>
                                    </div>
                                    <span class="font-bold text-gray-900">Tk <?php echo esc_html( $cost ); ?>.00</span>
                                </label>
                            <?php
                            $is_first = false;
                        }
                    }
                    ?>
                    </div>

                    <!-- Custom Delivery Charge Option (Hidden by Default) -->
                    <div id="fmb-custom-delivery-option" class="hidden">
                        <label class="flex justify-between items-center p-4 border-b border-gray-200 cursor-pointer bg-blue-50/50">
                            <div class="flex items-center">
                                <input type="radio" name="delivery_area" value="" class="form-radio text-primary h-5 w-5 accent-primary"> 
                                <span class="ml-3 font-medium text-gray-800">Delivery Charge</span>
                            </div>
                            <span class="font-bold text-gray-900" id="fmb-custom-charge-amount">0Tk</span>
                        </label>
                    </div>
                </div>

                <div class="bg-gray-50 p-4 rounded-xl border border-gray-200">
                    <h4 class="font-bold text-gray-700 mb-3 text-sm uppercase">অর্ডার লিস্ট:</h4>
                    
                    <div id="popup-order-items-list" class="space-y-3">
                        <!-- Dynamic Cart Items Will Be Rendered Here via JS -->
                    </div>

                    <!-- Upsell Section -->
                    <div id="popup-upsell-wrapper" class="hidden mt-4 pt-4 border-t border-gray-200 border-dashed">
                        <h5 class="font-bold text-gray-700 text-sm mb-2">এর সাথে আরও যা যা নিতে পারেন:</h5>
                        <div id="popup-upsell-list" class="grid grid-cols-2 gap-2">
                            <!-- Upsell Items JS -->
                        </div>
                    </div>

                    <div class="space-y-3 text-[15px] mt-4 border-t pt-3">
                        <div class="flex justify-between text-gray-600">
                            <span>সাব টোটাল</span>
                            <span class="font-medium" id="fmb-modal-subtotal">0৳</span>
                        </div>
                        <div class="flex justify-between text-gray-600">
                            <span>ডেলিভারি চার্জ</span>
                            <span class="font-medium text-primary" id="fmb-modal-shipping">+70৳</span>
                        </div>
                        <div class="flex justify-between text-xl font-extrabold text-gray-900 border-t border-gray-200 pt-3 mt-3">
                            <span>সর্বমোট</span>
                            <span class="text-primary" id="fmb-modal-total">0৳</span>
                        </div>
                    </div>
                </div>

                <!-- Hidden Inputs for Multi Product -->
                <input type="hidden" name="order_items" id="popup_hidden_order_items">
                <input type="hidden" name="is_popup" value="1">

                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">অর্ডার নোট (অপশনাল)</label>
                    <textarea name="order_note" placeholder="স্পেশাল কোনো নির্দেশনা থাকলে লিখুন..." rows="2" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary outline-none transition resize-none"></textarea>
                </div>

                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">পেমেন্ট মেথড</label>
                    <div class="bg-gray-50 p-3 rounded-lg border border-gray-200">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" name="payment_method" value="cod" checked class="form-radio text-primary h-5 w-5 accent-primary">
                            <span class="font-bold text-gray-800">ক্যাশ অন ডেলিভারি</span>
                        </label>
                        <p class="text-xs text-gray-500 mt-1 ml-7">পণ্য হাতে পেয়ে মূল্য পরিশোধ করবেন</p>
                    </div>
                </div>

                <div class="space-y-3 pt-2">
                    <button type="submit" class="fmb-submit-btn w-full text-white text-lg font-bold py-4 rounded-xl transition shadow-lg flex justify-center items-center gap-2" style="background: var(--btn-bg, var(--primary)) !important; color: var(--btn-text, #ffffff) !important;">
                        <span>অর্ডার কনফার্ম করতে ক্লিক করুন</span>
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" /></svg>
                    </button>
                    <p class="fmb-loading-msg text-center text-blue-600 hidden font-medium">অর্ডার প্রসেস হচ্ছে, অনুগ্রহ করে অপেক্ষা করুন...</p>
                    <p class="text-center text-green-600 text-sm font-semibold">
                        উপরের বাটনে ক্লিক করলে আপনার অর্ডারটি সাথে সাথে কনফার্ম হয়ে যাবে!
                    </p>
                </div>

            </form>
        </div>
    </div>

    <div id="fmb-quick-view-modal" class="fmb-modal-overlay fixed inset-0 bg-black/60 backdrop-blur-sm z-[9999] flex justify-center items-center p-4" style="display: none;">
        <div class="fmb-modal-content bg-white w-full max-w-4xl rounded-2xl shadow-2xl overflow-hidden relative animate-slideUp">
            
            <span class="fmb-close-qv cursor-pointer text-gray-400 hover:text-red-500 transition p-2 absolute top-3 right-3 z-50 bg-white rounded-full shadow-sm border border-gray-100">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
            </span>

            <div id="quick-view-content" class="max-h-[85vh] overflow-y-auto">
                <!-- AJAX Content will be loaded here -->
                <div class="p-12 flex justify-center items-center flex-col min-h-[300px]">
                    <svg class="animate-spin h-10 w-10 text-primary mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <p class="text-gray-500 font-medium">প্রোডাক্টের বিস্তারিত লোড হচ্ছে...</p>
                </div>
            </div>
        </div>
    </div>

    <?php if ( ! is_cart() && ! is_checkout() ) : ?>
    <!-- ===================================================== -->
    <!-- Sticky Rich Cart Bottom Bar (Global) -->
    <!-- ===================================================== -->
    <div id="fmb-sticky-rich-cart" class="fixed bottom-0 left-0 right-0 z-[9990] bg-white border-t border-gray-200 shadow-[0_-4px_20px_rgba(0,0,0,0.2)] transform translate-y-full transition-transform duration-500 ease-in-out">
        <div class="max-w-7xl mx-auto flex items-stretch h-[70px] md:h-[90px]">
            
            <!-- Left: Cart Icon Box -->
            <a href="<?php echo wc_get_cart_url(); ?>" class="bg-[#0f172a] text-white flex flex-col items-center justify-center w-[70px] md:w-[100px] shrink-0 hover:bg-[#1e293b] transition relative">
                <div class="relative">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 md:h-8 md:w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                    <span id="rich-cart-count" class="absolute -top-2 -right-2 bg-orange-500 text-white text-[10px] md:text-xs font-bold h-4 w-4 md:h-5 md:w-5 flex items-center justify-center rounded-full border border-[#0f172a]">0</span>
                </div>
                <span class="text-[9px] md:text-xs font-bold mt-1">কার্ট দেখুন</span>
            </a>

            <!-- Middle: Scrollable Cart Items -->
            <div class="flex-grow overflow-x-auto flex items-center gap-2 md:gap-4 px-2 md:px-4 custom-scrollbar min-w-0" id="rich-cart-items">
                <!-- Items will be injected via JS -->
            </div>

            <!-- Right: Totals and Order Button -->
            <div class="shrink-0 flex items-center gap-1.5 md:gap-6 px-1.5 md:px-6 border-l border-gray-100 bg-gray-50">
                <div class="flex flex-col items-end justify-center">
                    <span class="text-[10px] md:text-xs text-gray-500 font-bold uppercase leading-none hidden sm:block">মোট</span>
                    <span id="rich-cart-total" class="text-sm md:text-2xl font-black text-gray-900 leading-none mt-0.5 md:mt-1">0৳</span>
                    <span id="rich-cart-savings" class="mt-0.5 md:mt-1 bg-green-100 text-green-700 border border-green-300 text-[8px] md:text-[10px] font-bold px-1 py-0.5 rounded-full flex items-center gap-0.5 whitespace-nowrap" style="display:none;">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-2 w-2 md:h-2.5 md:w-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" /></svg>
                        <span class="savings-amt">0</span>৳ সাশ্রয়!
                    </span>
                </div>
                
                <a href="<?php echo wc_get_checkout_url(); ?>" class="fmb-btn-primary fmb-sticky-buy-btn text-white font-bold text-[11px] md:text-lg px-2.5 md:px-8 py-1.5 md:py-3 rounded-md md:rounded-xl shadow-sm md:shadow-lg transition flex items-center gap-1 whitespace-nowrap" style="background-color: var(--btn-bg, var(--primary)) !important; color: var(--btn-text, #ffffff) !important;">
                    <span>অর্ডার করুন</span>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 md:h-5 md:w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" /></svg>
                </a>
            </div>
            
        </div>
    </div>

    <style>
    .custom-scrollbar::-webkit-scrollbar { display: none; }
    .custom-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    </style>

    <script>
    jQuery(document).ready(function($) {
        var ajax_url = '<?php echo admin_url("admin-ajax.php"); ?>';
        
        window.fmbRefreshRichCart = function(showBar) {
            $.post(ajax_url, { action: 'fmb_get_rich_cart_data' }, function(response) {
                if (response.success) {
                    var data = response.data;
                    $('#rich-cart-count').text(data.count);
                    $('#rich-cart-total').text(data.total + '৳');
                    
                    if (data.savings > 0) {
                        $('#rich-cart-savings').show().find('.savings-amt').text(data.savings);
                    } else {
                        $('#rich-cart-savings').hide();
                    }
                    
                    var itemsHtml = '';
                    if (data.items && data.items.length > 0) {
                        data.items.forEach(function(item, index) {
                            itemsHtml += `
                            <div class="flex items-center gap-1.5 md:gap-2 bg-white p-1 md:p-2 rounded-lg border border-gray-100 shrink-0 relative w-[130px] md:w-[220px]">
                                <span class="absolute -top-1 -left-1 md:-top-1.5 md:-left-1.5 bg-[#0f172a] text-white text-[8px] md:text-[10px] font-bold h-3.5 w-3.5 md:h-5 md:w-5 flex items-center justify-center rounded-full border border-white z-10">${index+1}</span>
                                <img src="${item.img}" class="w-8 h-8 md:w-14 md:h-14 rounded bg-gray-50 border object-cover shrink-0">
                                <div class="flex flex-col overflow-hidden w-full">
                                    <span class="text-[8px] md:text-xs font-bold text-gray-700 truncate w-full" title="${item.name}">${item.name}</span>
                                    <span class="text-[9px] md:text-sm font-black text-gray-900 leading-tight">${item.price}</span>
                                    <div class="flex items-center bg-gray-50 border rounded mt-0.5 w-fit h-4 md:h-6">
                                        <button class="rich-qty-btn px-1 md:px-2 text-gray-500 font-bold text-[9px] md:text-sm hover:bg-gray-200 h-full flex items-center justify-center" data-key="${item.key}" data-val="-1">-</button>
                                        <span class="text-[8px] md:text-xs px-1 font-bold min-w-[12px] md:min-w-[20px] text-center">${item.qty}</span>
                                        <button class="rich-qty-btn px-1 md:px-2 text-gray-500 font-bold text-[9px] md:text-sm hover:bg-gray-200 h-full flex items-center justify-center" data-key="${item.key}" data-val="1">+</button>
                                    </div>
                                </div>
                            </div>`;
                        });
                        
                        if (showBar) {
                            $('#fmb-sticky-rich-cart').removeClass('translate-y-full').addClass('translate-y-0');
                        }
                    } else {
                        // Hide if empty
                        $('#fmb-sticky-rich-cart').removeClass('translate-y-0').addClass('translate-y-full');
                    }
                    $('#rich-cart-items').html(itemsHtml);
                }
            });
        };

        // Initialize if cart has items on load
        <?php if (WC()->cart && WC()->cart->get_cart_contents_count() > 0) : ?>
        window.fmbRefreshRichCart(true);
        <?php endif; ?>

        // Handle quantity changes inside rich cart
        $(document).on('click', '.rich-qty-btn', function(e) {
            e.preventDefault();
            var btn = $(this);
            var key = btn.data('key');
            var change = parseInt(btn.data('val'));
            var currentQty = parseInt(btn.siblings('span').text());
            var newQty = currentQty + change;
            
            btn.closest('.flex.items-center').css('opacity', '0.5'); // visual feedback
            
            $.post(ajax_url, {
                action: 'fmb_update_checkout_cart',
                cart_item_key: key,
                qty: newQty
            }, function(response) {
                window.fmbRefreshRichCart(true);
            });
        });

        // 🛒 প্রোডাক্ট কার্ড থেকে তাৎক্ষণিক AJAX অ্যাড টু কার্ট
        $(document).on('click', '.fmb-card-atc-btn', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var btn = $(this);
            var pid = btn.data('product-id');
            if (!pid || btn.prop('disabled')) return;
            
            var origHtml = btn.html();
            btn.prop('disabled', true).html('<svg class="animate-spin h-4 w-4" style="color: var(--primary);" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>');
            
            $.post(ajax_url, {
                action: 'fmb_ajax_add_to_cart',
                product_id: pid,
                quantity: 1
            }, function(res) {
                if (res.success) {
                    btn.html('<svg class="h-4 w-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" /></svg>');
                    btn.addClass('bg-emerald-50 border-emerald-300');
                    
                    setTimeout(function() {
                        btn.prop('disabled', false).html(origHtml).removeClass('bg-emerald-50 border-emerald-300');
                    }, 2500);
                    
                    if (typeof window.fmbRefreshRichCart === 'function') {
                        window.fmbRefreshRichCart(true);
                    }
                    
                    if (res.data && res.data.count) {
                        $('#header-cart-count, .header-cart-count, .cart-count').text(res.data.count).removeClass('hidden');
                    }
                } else {
                    btn.prop('disabled', false).html(origHtml);
                    alert(res.data && res.data.message ? res.data.message : 'কার্টে যোগ করা যায়নি।');
                }
            }).fail(function() {
                btn.prop('disabled', false).html(origHtml);
                alert('কার্টে যোগ করতে সমস্যা হয়েছে।');
            });
        });

        // 🚚 চেকআউট ফর্ম স্ক্রিনে দেখা গেলে স্টিকি কার্ট বার লুকিয়ে যাবে
        function fmbSetupCheckoutObserver() {
            var checkoutTargets = document.querySelectorAll('#checkout-area, #multi-product-checkout-form, .woocommerce-checkout, .fmb-checkout-container, #order_form, .fmb-order-bumps');
            if (!checkoutTargets || !checkoutTargets.length) return;

            if ('IntersectionObserver' in window) {
                var observer = new IntersectionObserver(function(entries) {
                    var isVisible = false;
                    checkoutTargets.forEach(function(el) {
                        var rect = el.getBoundingClientRect();
                        if (rect.top < window.innerHeight && rect.bottom > 0) {
                            isVisible = true;
                        }
                    });
                    if (isVisible) {
                        $('#fmb-sticky-rich-cart, #fmb-sticky-cta').addClass('fmb-hide-sticky');
                    } else {
                        $('#fmb-sticky-rich-cart, #fmb-sticky-cta').removeClass('fmb-hide-sticky');
                    }
                }, { threshold: 0.05, rootMargin: '0px 0px 50px 0px' });

                checkoutTargets.forEach(function(el) {
                    observer.observe(el);
                });
            }

            // Fallback on scroll
            $(window).on('scroll resize', function() {
                var isVisible = false;
                checkoutTargets.forEach(function(el) {
                    var rect = el.getBoundingClientRect();
                    if (rect.top < window.innerHeight && rect.bottom > 0) {
                        isVisible = true;
                    }
                });
                if (isVisible) {
                    $('#fmb-sticky-rich-cart, #fmb-sticky-cta').addClass('fmb-hide-sticky');
                } else {
                    $('#fmb-sticky-rich-cart, #fmb-sticky-cta').removeClass('fmb-hide-sticky');
                }
            });
        }
        fmbSetupCheckoutObserver();
    });
    </script>
    <?php endif; ?>

    <?php wp_footer(); ?>
</body>
</html>