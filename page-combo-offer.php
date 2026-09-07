<?php
/**
 * Template Name: Product Combo Offer Landing Page
 * High-Converting Dedicated Landing Page for Product Combos
 */

get_header();

$theme_uri          = get_template_directory_uri();
$store_phone        = get_theme_mod('fmb_global_phone', '01700000000');
$combo_badge        = get_theme_mod('fmb_combo_badge', '🔥 মেগা কম্বো অফার - সাশ্রয়ী বান্ডেল');
$combo_title        = get_theme_mod('fmb_combo_title', 'স্মার্ট গ্যাজেট ও হোম কম্বো প্যাকেজ');
$combo_subtitle     = get_theme_mod('fmb_combo_subtitle', '৩টি সেরা প্রোডাক্ট একসাথে অর্ডার করুন এবং পান ১,০১০৳ বিশেষ ছাড়!');

$p1_title = get_theme_mod('fmb_combo_p1_title', 'সোলার পাওয়ার রিচার্জেবল ফ্যান');
$p1_price = floatval(get_theme_mod('fmb_combo_p1_price', '1500'));
$p1_img   = get_theme_mod('fmb_combo_p1_img', $theme_uri . '/assets/images/banner1.png');

$p2_title = get_theme_mod('fmb_combo_p2_title', 'মাল্টি-ফাংশন ফুড চপার & ব্লেন্ডার');
$p2_price = floatval(get_theme_mod('fmb_combo_p2_price', '1200'));
$p2_img   = get_theme_mod('fmb_combo_p2_img', $theme_uri . '/assets/images/banner2.png');

$p3_title = get_theme_mod('fmb_combo_p3_title', 'সোলার রিচার্জেবল নাইট লণ্ঠন');
$p3_price = floatval(get_theme_mod('fmb_combo_p3_price', '800'));
$p3_img   = get_theme_mod('fmb_combo_p3_img', $theme_uri . '/assets/images/banner3.png');

$combo_regular_total = $p1_price + $p2_price + $p3_price;
$combo_offer_price   = floatval(get_theme_mod('fmb_combo_offer_price', '2490'));
$combo_savings       = max(0, $combo_regular_total - $combo_offer_price);
$combo_product_id    = intval(get_theme_mod('fmb_combo_product_id', 0));
?>

<div class="fmb-combo-landing-page bg-gray-50 min-h-screen pb-24">

    <!-- Top Announcement Banner -->
    <div class="bg-gradient-to-r from-blue-600 via-indigo-600 to-blue-700 text-white text-center py-3 px-4 shadow-md">
        <div class="max-w-7xl mx-auto flex items-center justify-center gap-2 font-bold text-xs sm:text-sm md:text-base">
            <span class="animate-truck-drop-drive">🚚</span>
            <span>মেগা কম্বো প্যাকেজে ফ্রি ক্যাশ অন ডেলিভারি সুবিধা ও বিশেষ ডিসকাউন্ট!</span>
        </div>
    </div>

    <!-- ── 1. HERO PRODUCT SECTION ────────────────────────── -->
    <div class="bg-white shadow-sm border-b pb-12 pt-6 sm:pt-10">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 grid grid-cols-1 lg:grid-cols-12 gap-8 md:gap-12 items-center">
            
            <!-- Left: Combo Visual Showcase Card (7 cols) -->
            <div class="lg:col-span-7 bg-gradient-to-br from-blue-50 to-indigo-50 border-2 border-blue-100 rounded-3xl p-4 sm:p-6 shadow-xl relative overflow-hidden">
                <div class="absolute top-3 right-3 bg-red-500 text-white font-extrabold text-xs px-3 py-1 rounded-full uppercase tracking-wider shadow">
                    SAVE <?php echo number_format($combo_savings); ?>৳
                </div>

                <h3 class="text-center font-extrabold text-gray-900 text-base sm:text-lg mb-4">
                    📦 প্যাকেজে যা যা পাচ্ছেন (৩টি সেরা পণ্য)
                </h3>

                <div class="grid grid-cols-3 gap-2 sm:gap-4 items-center">
                    <!-- Item 1 -->
                    <div class="bg-white rounded-2xl p-2.5 sm:p-4 text-center shadow-md border border-gray-100 flex flex-col justify-between h-full">
                        <div class="w-full aspect-square rounded-xl overflow-hidden mb-2 bg-gray-50">
                            <img src="<?php echo esc_url($p1_img); ?>" class="w-full h-full object-cover" alt="<?php echo esc_attr($p1_title); ?>">
                        </div>
                        <h4 class="font-bold text-xs sm:text-sm text-gray-900 line-clamp-2"><?php echo esc_html($p1_title); ?></h4>
                        <div class="text-[11px] sm:text-xs text-gray-400 line-through mt-1"><?php echo number_format($p1_price); ?>৳</div>
                    </div>

                    <!-- Item 2 -->
                    <div class="bg-white rounded-2xl p-2.5 sm:p-4 text-center shadow-md border border-gray-100 flex flex-col justify-between h-full">
                        <div class="w-full aspect-square rounded-xl overflow-hidden mb-2 bg-gray-50">
                            <img src="<?php echo esc_url($p2_img); ?>" class="w-full h-full object-cover" alt="<?php echo esc_attr($p2_title); ?>">
                        </div>
                        <h4 class="font-bold text-xs sm:text-sm text-gray-900 line-clamp-2"><?php echo esc_html($p2_title); ?></h4>
                        <div class="text-[11px] sm:text-xs text-gray-400 line-through mt-1"><?php echo number_format($p2_price); ?>৳</div>
                    </div>

                    <!-- Item 3 -->
                    <div class="bg-white rounded-2xl p-2.5 sm:p-4 text-center shadow-md border border-gray-100 flex flex-col justify-between h-full">
                        <div class="w-full aspect-square rounded-xl overflow-hidden mb-2 bg-gray-50">
                            <img src="<?php echo esc_url($p3_img); ?>" class="w-full h-full object-cover" alt="<?php echo esc_attr($p3_title); ?>">
                        </div>
                        <h4 class="font-bold text-xs sm:text-sm text-gray-900 line-clamp-2"><?php echo esc_html($p3_title); ?></h4>
                        <div class="text-[11px] sm:text-xs text-gray-400 line-through mt-1"><?php echo number_format($p3_price); ?>৳</div>
                    </div>
                </div>
            </div>

            <!-- Right: Title, Price & Order Action (5 cols) -->
            <div class="lg:col-span-5 flex flex-col justify-center">
                <span class="bg-blue-100 text-primary text-xs font-black px-3.5 py-1 rounded-full uppercase tracking-wider w-fit mb-3">
                    <?php echo esc_html($combo_badge); ?>
                </span>

                <h1 class="text-2xl sm:text-3xl md:text-4xl font-black text-gray-900 leading-tight mb-3">
                    <?php echo esc_html($combo_title); ?>
                </h1>

                <p class="text-sm md:text-base text-gray-600 mb-6 leading-relaxed">
                    <?php echo esc_html($combo_subtitle); ?>
                </p>

                <!-- Price Box -->
                <div class="bg-blue-50/70 border border-blue-100 rounded-2xl p-4 sm:p-5 mb-6">
                    <div class="flex items-baseline gap-3">
                        <span class="text-3xl sm:text-4xl md:text-5xl font-black text-primary">
                            <?php echo number_format($combo_offer_price); ?>৳
                        </span>
                        <span class="text-lg text-gray-400 line-through font-bold">
                            <?php echo number_format($combo_regular_total); ?>৳
                        </span>
                    </div>

                    <?php if ($combo_savings > 0) : ?>
                        <div class="mt-2 inline-block bg-emerald-100 text-emerald-800 text-xs sm:text-sm font-extrabold px-3 py-1 rounded-full border border-emerald-200">
                            🎉 কম্বোতে সরাসরি বেঁচে যাবে <?php echo number_format($combo_savings); ?>৳!
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Fake Order Counter -->
                <div class="flex items-center gap-2 text-xs sm:text-sm text-gray-700 mb-6 bg-red-50 p-3 rounded-xl border border-red-100">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-red-500 animate-pulse shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 18.657A8 8 0 016.343 7.343S7 9 9 10c0-2 .5-5 2.986-7C14 5 16.09 5.777 17.656 7.343A7.975 7.975 0 0120 13a7.975 7.975 0 01-2.343 5.657z" />
                    </svg>
                    <span class="font-medium">গত ২৪ ঘণ্টায় <span class="text-red-600 font-bold">১৬৮ জন</span> এই কম্বো অফারটি নিয়েছেন!</span>
                </div>

                <!-- Order Action Buttons -->
                <div class="flex flex-col gap-3">
                    <button onclick="document.getElementById('checkout-area').scrollIntoView({ behavior: 'smooth' });"
                            class="w-full bg-gradient-to-r from-blue-600 to-indigo-700 hover:from-blue-700 hover:to-indigo-800 text-white font-extrabold text-base sm:text-lg py-4 px-6 rounded-2xl shadow-xl hover:shadow-2xl transition duration-300 transform hover:-translate-y-0.5 text-center">
                        অর্ডার করুন (নিচে সরাসরি ফর্ম) ⬇
                    </button>

                    <?php if ($store_phone) : ?>
                        <a href="tel:<?php echo esc_attr($store_phone); ?>"
                           class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-sm sm:text-base py-3 px-6 rounded-2xl shadow transition text-center flex items-center justify-center gap-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                            কল করতে চাপুন: <?php echo esc_html($store_phone); ?>
                        </a>
                    <?php endif; ?>
                </div>

            </div>

        </div>
    </div>

    <!-- ── 2. INCLUDED PRODUCTS DETAIL SHOWCASE ───────────────── -->
    <div class="py-12 bg-white border-b">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="text-2xl sm:text-3xl font-extrabold text-center text-gray-900 mb-8">
                কেন এই কম্বো অফারটি আপনার জন্য সেরা?
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Item 1 Detail Card -->
                <div class="bg-gray-50 border border-gray-100 rounded-3xl p-6 shadow-sm flex flex-col justify-between">
                    <div>
                        <img src="<?php echo esc_url($p1_img); ?>" class="w-full h-48 object-cover rounded-2xl mb-4 bg-white" alt="<?php echo esc_attr($p1_title); ?>">
                        <span class="text-xs font-bold text-primary uppercase bg-blue-100 px-2.5 py-0.5 rounded-full">পণ্য ১</span>
                        <h3 class="text-lg font-bold text-gray-900 mt-2 mb-2"><?php echo esc_html($p1_title); ?></h3>
                        <p class="text-xs sm:text-sm text-gray-600">উন্নত মানের সোলার চার্জিং ব্যাকআপ সহ লং-লাস্টিং ব্যাটারি ও হাই স্পিড এয়ার ফ্লো।</p>
                    </div>
                    <div class="mt-4 pt-3 border-t text-sm font-bold text-gray-500">
                        রেগুলার দাম: <span class="line-through text-red-500"><?php echo number_format($p1_price); ?>৳</span>
                    </div>
                </div>

                <!-- Item 2 Detail Card -->
                <div class="bg-gray-50 border border-gray-100 rounded-3xl p-6 shadow-sm flex flex-col justify-between">
                    <div>
                        <img src="<?php echo esc_url($p2_img); ?>" class="w-full h-48 object-cover rounded-2xl mb-4 bg-white" alt="<?php echo esc_attr($p2_title); ?>">
                        <span class="text-xs font-bold text-orange-600 uppercase bg-orange-100 px-2.5 py-0.5 rounded-full">পণ্য ২</span>
                        <h3 class="text-lg font-bold text-gray-900 mt-2 mb-2"><?php echo esc_html($p2_title); ?></h3>
                        <p class="text-xs sm:text-sm text-gray-600">রান্নাঘরের কাজ নিমেষেই সহজ করতে স্টেইনলেস স্টিল ৪-ব্লেড শার্প কিচেন চপার।</p>
                    </div>
                    <div class="mt-4 pt-3 border-t text-sm font-bold text-gray-500">
                        রেগুলার দাম: <span class="line-through text-red-500"><?php echo number_format($p2_price); ?>৳</span>
                    </div>
                </div>

                <!-- Item 3 Detail Card -->
                <div class="bg-gray-50 border border-gray-100 rounded-3xl p-6 shadow-sm flex flex-col justify-between">
                    <div>
                        <img src="<?php echo esc_url($p3_img); ?>" class="w-full h-48 object-cover rounded-2xl mb-4 bg-white" alt="<?php echo esc_attr($p3_title); ?>">
                        <span class="text-xs font-bold text-emerald-600 uppercase bg-emerald-100 px-2.5 py-0.5 rounded-full">পণ্য ৩</span>
                        <h3 class="text-lg font-bold text-gray-900 mt-2 mb-2"><?php echo esc_html($p3_title); ?></h3>
                        <p class="text-xs sm:text-sm text-gray-600">লোডশেডিং বা আউটডোরে শক্তিশালী পোর্টেবল সোলার লাইট ব্যাকআপ।</p>
                    </div>
                    <div class="mt-4 pt-3 border-t text-sm font-bold text-gray-500">
                        রেগুলার দাম: <span class="line-through text-red-500"><?php echo number_format($p3_price); ?>৳</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ── 3. BOTTOM HIGH-CONVERTING FAST ORDER FORM ───────────── -->
    <div id="checkout-area" class="max-w-4xl mx-auto px-4 pt-12">
        <div class="bg-white rounded-3xl shadow-2xl border-2 border-primary/30 overflow-hidden">
            
            <!-- Order Form Header Banner -->
            <div class="bg-gradient-to-r from-blue-600 to-indigo-700 text-white p-6 sm:p-8 text-center">
                <span class="bg-yellow-400 text-gray-950 text-xs font-black px-3.5 py-1 rounded-full uppercase tracking-wider inline-block mb-2 shadow">
                    ক্যাশ অন ডেলিভারি
                </span>
                <h2 class="text-2xl sm:text-3xl font-extrabold">
                    অর্ডার সম্পন্ন করতে নিচের ফর্মটি পূরণ করুন
                </h2>
                <p class="text-xs sm:text-sm text-blue-100 mt-2">
                    পণ্য হাতে পেয়ে টাকা পরিশোধ করার সুবিধা! কোনো অগ্রিম পেমেন্টের প্রয়োজন নেই।
                </p>
            </div>

            <!-- Order Form Body -->
            <form id="fmb-combo-quick-checkout-form" class="p-6 sm:p-8 space-y-6">
                <input type="hidden" name="action" value="fmb_process_quick_checkout">
                <input type="hidden" name="product_id" value="<?php echo $combo_product_id; ?>">
                <input type="hidden" name="combo_custom_title" value="<?php echo esc_attr($combo_title); ?>">
                <input type="hidden" name="combo_price" value="<?php echo $combo_offer_price; ?>">

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-bold text-gray-800 mb-1.5">আপনার নাম <span class="text-red-500">*</span></label>
                        <input type="text" name="billing_first_name" required placeholder="আপনার সম্পূর্ণ নাম লিখুন"
                               class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-primary focus:outline-none text-sm transition">
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-800 mb-1.5">মোবাইল নম্বর <span class="text-red-500">*</span></label>
                        <input type="tel" name="billing_phone" required placeholder="১১ ডিজিটের মোবাইল নম্বর লিখুন"
                               class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-primary focus:outline-none text-sm transition">
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-800 mb-1.5">পূর্ণাঙ্গ ঠিকানা <span class="text-red-500">*</span></label>
                        <textarea name="billing_address_1" required rows="2" placeholder="আপনার জেলা, থানা ও এলাকা/বাড়ির নম্বর লিখুন"
                                  class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-primary focus:outline-none text-sm transition"></textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-800 mb-2">ডেলিভারি এলাকা নির্বাচন করুন <span class="text-red-500">*</span></label>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <label class="flex items-center gap-3 p-3.5 border-2 border-primary bg-blue-50/50 rounded-xl cursor-pointer">
                                <input type="radio" name="shipping_method_choice" value="dhaka" checked class="text-primary focus:ring-primary h-4 w-4">
                                <span class="text-sm font-bold text-gray-900">ঢাকায় (ডেলিভারি চার্জ ৳৬০)</span>
                            </label>
                            <label class="flex items-center gap-3 p-3.5 border-2 border-gray-200 hover:border-primary rounded-xl cursor-pointer">
                                <input type="radio" name="shipping_method_choice" value="outside" class="text-primary focus:ring-primary h-4 w-4">
                                <span class="text-sm font-bold text-gray-900">ঢাকার বাইরে (ডেলিভারি চার্জ ৳১২০)</span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Order Summary Breakdown -->
                <div class="bg-gray-50 border border-gray-200 rounded-2xl p-5 space-y-3">
                    <div class="flex justify-between text-sm font-semibold text-gray-700">
                        <span>প্যাকেজ অফার প্রাইস:</span>
                        <span class="font-bold text-gray-900"><?php echo number_format($combo_offer_price); ?>৳</span>
                    </div>
                    <div class="flex justify-between text-sm font-semibold text-gray-700">
                        <span>ডেলিভারি চার্জ:</span>
                        <span id="combo-shipping-cost-display" class="font-bold text-gray-900">৬০৳</span>
                    </div>
                    <div class="border-t pt-3 flex justify-between text-base sm:text-lg font-black text-gray-900">
                        <span>সর্বমোট মূল্য:</span>
                        <span id="combo-grand-total-display" class="text-primary font-black"><?php echo number_format($combo_offer_price + 60); ?>৳</span>
                    </div>
                </div>

                <!-- Submit Button -->
                <button type="submit"
                        class="w-full bg-gradient-to-r from-emerald-600 to-green-600 hover:from-emerald-700 hover:to-green-700 text-white font-black text-lg py-4 px-6 rounded-2xl shadow-xl hover:shadow-2xl transition duration-300 text-center">
                    অর্ডার কনফার্ম করুন (৳<span id="combo-btn-total-display"><?php echo number_format($combo_offer_price + 60); ?></span>) ➔
                </button>

                <p class="text-center text-xs text-gray-500 font-medium">
                    🔒 আপনার দেওয়া তথ্য আমাদের কাছে সম্পূর্ণ নিরাপদ ও সুরক্ষিত থাকবে।
                </p>
            </form>

        </div>
    </div>

</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    var comboOfferPrice = <?php echo (float) $combo_offer_price; ?>;
    var dhakaShipping = 60;
    var outsideShipping = 120;

    var radios = document.querySelectorAll('input[name="shipping_method_choice"]');
    var shipCostDisplay = document.getElementById('combo-shipping-cost-display');
    var grandTotalDisplay = document.getElementById('combo-grand-total-display');
    var btnTotalDisplay = document.getElementById('combo-btn-total-display');

    function updateComboTotals() {
        var selectedShipping = 60;
        radios.forEach(function(radio) {
            if (radio.checked && radio.value === 'outside') {
                selectedShipping = 120;
            }
        });

        var total = comboOfferPrice + selectedShipping;
        if (shipCostDisplay) shipCostDisplay.textContent = selectedShipping + '৳';
        if (grandTotalDisplay) grandTotalDisplay.textContent = total.toLocaleString() + '৳';
        if (btnTotalDisplay) btnTotalDisplay.textContent = total.toLocaleString();
    }

    radios.forEach(function(radio) {
        radio.addEventListener('change', updateComboTotals);
    });

    var form = document.getElementById('fmb-combo-quick-checkout-form');
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            var btn = form.querySelector('button[type="submit"]');
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = "অর্ডার প্রসেস হচ্ছে...";
            }

            var formData = new FormData(form);
            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method: 'POST',
                body: formData
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (data.success && data.data && data.data.redirect_url) {
                    window.location.href = data.data.redirect_url;
                } else if (data.success && data.data && data.data.order_id) {
                    alert('ধন্যবাদ! আপনার কম্বো অর্ডারটি সফলভাবে গৃহীত হয়েছে। অর্ডার আইডি: #' + data.data.order_id);
                    window.location.href = '<?php echo home_url(); ?>';
                } else {
                    alert(data.data || 'অর্ডার প্রসেস করার সময় সমস্যা হয়েছে। আবার চেষ্টা করুন।');
                    if (btn) {
                        btn.disabled = false;
                        btn.innerHTML = 'অর্ডার কনফার্ম করুন ➔';
                    }
                }
            })
            .catch(function(err) {
                alert('নেটওয়ার্ক সমস্যা। দয়া করে আবার চেষ্টা করুন।');
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = 'অর্ডার কনফার্ম করুন ➔';
                }
            });
        });
    }
});
</script>

<?php get_footer(); ?>
