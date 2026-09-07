<?php
/**
 * Single Template for Dynamic Combo Offers (fmb_combo_offer CPT)
 * High-Converting Dedicated Landing Page with Bottom Cash-on-Delivery Order Form
 */

get_header();

$combo_id           = get_the_ID();
$store_phone        = get_theme_mod('fmb_global_phone', '01700000000');
$combo_badge        = get_post_meta($combo_id, '_fmb_combo_badge', true) ?: '🔥 মেগা কম্বো অফার - সাশ্রয়ী বান্ডেল';
$combo_title        = get_the_title();
$combo_subtitle     = get_post_meta($combo_id, '_fmb_combo_subtitle', true) ?: 'প্যাকেজের পণ্যগুলো একসাথে অর্ডার করুন এবং বিশেষ ডিসকাউন্ট পান!';
$combo_offer_price  = floatval(get_post_meta($combo_id, '_fmb_combo_price', true));
$free_shipping      = get_post_meta($combo_id, '_fmb_combo_free_shipping', true) === 'yes';

$selected_pids = get_post_meta($combo_id, '_fmb_combo_product_ids', true) ?: array();
if (!is_array($selected_pids)) {
    $selected_pids = array_filter(explode(',', $selected_pids));
}
$product_qtys   = get_post_meta($combo_id, '_fmb_combo_product_qtys', true) ?: array();
$free_gift_id   = intval(get_post_meta($combo_id, '_fmb_combo_free_gift_id', true) ?: 0);
$free_gift_name = get_post_meta($combo_id, '_fmb_combo_free_gift_name', true) ?: '';
$free_gift_val  = floatval(get_post_meta($combo_id, '_fmb_combo_free_gift_val', true) ?: 0);

if ($free_gift_id > 0) {
    $gprod = wc_get_product($free_gift_id);
    if ($gprod) {
        if (empty($free_gift_name)) $free_gift_name = $gprod->get_name();
        if ($free_gift_val <= 0) $free_gift_val = floatval($gprod->get_regular_price() ?: $gprod->get_price());
        $gift_img = get_the_post_thumbnail_url($free_gift_id, 'large') ?: wc_placeholder_img_src();
    }
}
if (empty($gift_img)) {
    $gift_img = get_template_directory_uri() . '/assets/images/gift-box.png';
}

$combo_regular_total = 0;
$combo_items = array();

foreach ($selected_pids as $pid) {
    $prod = wc_get_product($pid);
    if ($prod) {
        $regular_price = floatval($prod->get_regular_price() ?: $prod->get_price());
        // Check for custom add price metadata, if empty, use the current active sale price
        $custom_add_price = get_post_meta($combo_id, "_fmb_combo_add_price_{$pid}", true);
        $sale_price       = ($custom_add_price !== '') ? floatval($custom_add_price) : floatval($prod->get_price());
        
        $qty           = isset($product_qtys[$pid]) ? max(1, intval($product_qtys[$pid])) : 1;
        $item_total    = $regular_price * $qty;
        $combo_regular_total += $item_total;
        $thumb = get_the_post_thumbnail_url($pid, 'large') ?: wc_placeholder_img_src();
        $combo_items[] = array(
            'id'            => $pid,
            'title'         => $prod->get_name(),
            'price'         => $regular_price,
            'sale_price'    => $sale_price,
            'qty'           => $qty,
            'item_total'    => $item_total,
            'img'           => $thumb,
            'is_free_gift'  => false,
            'short_desc'    => get_the_excerpt($pid) ?: 'উচ্চমানের অরিজিনাল অফিশিয়াল প্রডাক্ট।',
        );
    }
}

if (!empty($free_gift_name)) {
    $combo_regular_total += $free_gift_val;
    $combo_items[] = array(
        'id'            => $free_gift_id,
        'title'         => $free_gift_name,
        'price'         => $free_gift_val,
        'qty'           => 1,
        'item_total'    => $free_gift_val,
        'img'           => $gift_img,
        'is_free_gift'  => true,
        'short_desc'    => 'বিশেষ স্পেশাল কম্বো অফারে ১টি ১০০% ফ্রি উপহার!',
    );
}

$combo_savings = max(0, $combo_regular_total - $combo_offer_price);
?>

<div class="fmb-combo-landing-page bg-gray-50 min-h-screen pb-24">

    <!-- Top Announcement Banner -->
    <div class="bg-gradient-to-r from-blue-600 via-indigo-600 to-blue-700 text-white text-center py-3 px-4 shadow-md">
        <div class="max-w-7xl mx-auto flex items-center justify-center gap-2 font-bold text-xs sm:text-sm md:text-base">
            <span class="animate-truck-drop-drive">🚚</span>
            <span><?php echo $free_shipping ? 'এই কম্বো প্যাকেজে সারাদেশে ডেলিভারি সম্পূর্ণ বিনামূল্যে!' : 'মেগা কম্বো প্যাকেজে ফ্রি ক্যাশ অন ডেলিভারি সুবিধা ও বিশেষ ডিসকাউন্ট!'; ?></span>
        </div>
    </div>

    <!-- ── 1. HERO PRODUCT SECTION ────────────────────────── -->
    <div class="bg-gray-50/70 border-b border-gray-200/80 py-8 sm:py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 grid grid-cols-1 lg:grid-cols-12 gap-8 md:gap-10 items-stretch">
            
            <!-- Left: Combo Featured Image & Product Gallery Viewer (7 cols) -->
            <div class="lg:col-span-7 bg-white rounded-3xl overflow-hidden shadow-sm relative flex flex-col justify-between">
                <div>
                    <?php if ($combo_savings > 0) : ?>
                        <div class="absolute top-5 right-5 bg-gradient-to-r from-red-600 to-rose-600 text-white font-black text-xs px-3.5 py-1.5 rounded-full uppercase tracking-wider shadow-md z-20">
                            SAVE <?php echo number_format($combo_savings); ?>৳
                        </div>
                    <?php endif; ?>

                    <?php 
                    $gallery_images = array();
                    $combo_feat_img = get_the_post_thumbnail_url($combo_id, 'large');
                    if ($combo_feat_img) {
                        $gallery_images[] = array(
                            'url'   => $combo_feat_img,
                            'title' => get_the_title($combo_id),
                        );
                    }

                    foreach ($combo_items as $item) {
                        // Include all items except the generic gift-box placeholder
                        $img_url = $item['img'] ?? '';
                        $is_placeholder = strpos($img_url, 'gift-box.png') !== false;
                        if (!empty($img_url) && !$is_placeholder) {
                            $gallery_images[] = array(
                                'url'   => $img_url,
                                'title' => $item['title'],
                                'is_free_gift' => !empty($item['is_free_gift']),
                            );
                        }
                    }

                    if (empty($gallery_images)) {
                        $gallery_images[] = array(
                            'url'   => wc_placeholder_img_src(),
                            'title' => get_the_title($combo_id),
                        );
                    }

                    $main_img_url = $gallery_images[0]['url'];
                    ?>

                    <!-- Main Featured Image Display Box -->
                    <div class="w-full aspect-square sm:aspect-[3/3] overflow-hidden relative flex items-center justify-center bg-white">
                        <img id="fmb-combo-main-img" src="<?php echo esc_url($main_img_url); ?>" 
                             class="w-full h-full object-cover transition-all duration-300 hover:scale-105" 
                             alt="<?php echo esc_attr(get_the_title($combo_id)); ?>">
                    </div>

                    <!-- Gallery Thumbnails Strip -->
                    <?php if (count($gallery_images) > 1) : ?>
                        <div class="flex items-center gap-2.5 overflow-x-auto px-4 pb-3 pt-3 scrollbar-thin">
                            <?php 
                            $g_idx = 0;
                            foreach ($gallery_images as $g_item) : 
                                $g_idx++;
                                $active_cls = ($g_idx === 1) ? 'ring-2 ring-primary border-primary opacity-100' : 'border-gray-200 opacity-70 hover:opacity-100';
                            ?>
                                <button type="button" 
                                        onclick="document.getElementById('fmb-combo-main-img').src='<?php echo esc_url($g_item['url']); ?>'; document.querySelectorAll('.fmb-combo-thumb-btn').forEach(b => { b.classList.remove('ring-2', 'ring-primary', 'border-primary', 'opacity-100'); b.classList.add('border-gray-200', 'opacity-70'); }); this.classList.add('ring-2', 'ring-primary', 'border-primary', 'opacity-100');"
                                        class="fmb-combo-thumb-btn w-16 h-16 sm:w-20 sm:h-20 rounded-xl overflow-hidden bg-white p-1 border-2 shrink-0 transition duration-200 focus:outline-none shadow-sm <?php echo $active_cls; ?>"
                                        title="<?php echo esc_attr($g_item['title']); ?>">
                                    <img src="<?php echo esc_url($g_item['url']); ?>" class="w-full h-full object-cover rounded-lg" alt="">
                                </button>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right: Title, Price & Order Action (5 cols) -->
            <div class="lg:col-span-5 bg-white text-gray-900 rounded-3xl p-6 sm:p-8 shadow-sm border border-gray-200/80 flex flex-col justify-between">
                <div>
                    <span class="inline-flex items-center gap-1.5 bg-blue-50 border border-blue-200 text-blue-800 text-xs font-bold px-3.5 py-1.5 rounded-full uppercase tracking-wider w-fit mb-3">
                        <span>🔥</span>
                        <span><?php echo esc_html($combo_badge); ?></span>
                    </span>

                    <h1 class="text-2xl sm:text-3xl lg:text-4xl font-extrabold text-gray-900 leading-tight mb-2.5">
                        <?php echo esc_html($combo_title); ?>
                    </h1>

                    <p class="text-xs sm:text-sm text-gray-600 mb-5 leading-relaxed">
                        <?php echo esc_html($combo_subtitle); ?>
                    </p>

                    <!-- Optional Live Countdown Timer Box -->
                    <?php 
                    $enable_timer = get_post_meta($combo_id, '_fmb_combo_enable_timer', true) ?: 'yes';
                    $timer_hours  = intval(get_post_meta($combo_id, '_fmb_combo_timer_hours', true) ?: 24);
                    if ($enable_timer === 'yes') : 
                    ?>
                        <div class="bg-gradient-to-r from-red-600 via-rose-600 to-red-700 text-white rounded-2xl p-3.5 sm:p-4 text-center mb-5 shadow-sm border border-red-500/20">
                            <div class="text-xs font-extrabold uppercase tracking-wider mb-2 flex items-center justify-center gap-1.5 text-yellow-300">
                                <span class="animate-pulse text-base">⏳</span>
                                <span>অফারের মেয়াদ শেষ হতে বাকি:</span>
                            </div>
                            <div class="flex items-center justify-center gap-2 sm:gap-3 text-gray-900 font-black">
                                <div class="bg-white/95 rounded-xl px-2.5 sm:px-3 py-1.5 min-w-[50px] sm:min-w-[60px] shadow-sm text-center border border-gray-100">
                                    <span class="text-lg sm:text-2xl font-black text-red-600 block leading-none font-mono" id="single-combo-th">00</span>
                                    <span class="text-[9px] font-bold text-gray-500 uppercase block mt-1">ঘণ্টা</span>
                                </div>
                                <span class="text-white text-xl font-bold mb-3">:</span>
                                <div class="bg-white/95 rounded-xl px-2.5 sm:px-3 py-1.5 min-w-[50px] sm:min-w-[60px] shadow-sm text-center border border-gray-100">
                                    <span class="text-lg sm:text-2xl font-black text-red-600 block leading-none font-mono" id="single-combo-tm">00</span>
                                    <span class="text-[9px] font-bold text-gray-500 uppercase block mt-1">মিনিট</span>
                                </div>
                                <span class="text-white text-xl font-bold mb-3">:</span>
                                <div class="bg-white/95 rounded-xl px-2.5 sm:px-3 py-1.5 min-w-[50px] sm:min-w-[60px] shadow-sm text-center border border-gray-100">
                                    <span class="text-lg sm:text-2xl font-black text-red-600 block leading-none font-mono" id="single-combo-ts">00</span>
                                    <span class="text-[9px] font-bold text-gray-500 uppercase block mt-1">সেকেন্ড</span>
                                </div>
                            </div>
                        </div>

                        <script>
                        (function() {
                            var elH = document.getElementById('single-combo-th');
                            var elM = document.getElementById('single-combo-tm');
                            var elS = document.getElementById('single-combo-ts');
                            var storageKey = 'fmb_combo_timer_end_<?php echo $combo_id; ?>';
                            var savedEnd = localStorage.getItem(storageKey);
                            var endTime;
                            var hoursInMs = <?php echo $timer_hours; ?> * 3600 * 1000;

                            if (savedEnd && parseInt(savedEnd, 10) > new Date().getTime()) {
                                endTime = parseInt(savedEnd, 10);
                            } else {
                                endTime = new Date().getTime() + hoursInMs;
                                localStorage.setItem(storageKey, endTime);
                            }

                            function tick() {
                                var now = new Date().getTime();
                                var diff = endTime - now;

                                if (diff <= 0) {
                                    endTime = new Date().getTime() + hoursInMs;
                                    localStorage.setItem(storageKey, endTime);
                                    diff = hoursInMs;
                                }

                                var h = Math.floor(diff / (1000 * 60 * 60));
                                var m = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
                                var s = Math.floor((diff % (1000 * 60)) / 1000);

                                if (elH) elH.textContent = (h < 10 ? '0' : '') + h;
                                if (elM) elM.textContent = (m < 10 ? '0' : '') + m;
                                if (elS) elS.textContent = (s < 10 ? '0' : '') + s;
                            }

                            tick();
                            setInterval(tick, 1000);
                        })();
                        </script>
                    <?php endif; ?>

                    <!-- Price Box -->
                    <div class="bg-blue-50/70 border border-blue-100 rounded-2xl p-4 sm:p-5 mb-5">
                        <div class="flex items-center justify-between border-b border-blue-100 pb-2.5 mb-2.5">
                            <span class="text-xs font-bold uppercase text-gray-500 tracking-wider">প্যাকেজ রেগুলার মূল্য:</span>
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-bold text-red-500 line-through"><?php echo number_format($combo_regular_total); ?>৳</span>
                                <?php if ($free_shipping) : ?>
                                    <span class="bg-emerald-100 text-emerald-800 border border-emerald-200 font-extrabold text-[10px] px-2.5 py-0.5 rounded-full">
                                        🚚 ফ্রি ডেলিভারি!
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="text-center my-2">
                            <span class="text-xs font-bold text-gray-500 uppercase tracking-wider block">বিশেষ অফার প্রাইস</span>
                            <div class="text-3xl sm:text-4xl font-black text-primary mt-0.5 flex items-baseline justify-center gap-1">
                                <span><?php echo number_format($combo_offer_price); ?></span>
                                <span class="text-xl font-bold">৳</span>
                            </div>
                            <?php if ($combo_savings > 0) : ?>
                                <div class="inline-block bg-emerald-100 text-emerald-800 text-xs font-black px-3.5 py-1 rounded-full mt-2 border border-emerald-200 shadow-sm">
                                    🎉 কম্বোতে সরাসরি বেঁচে যাবে <?php echo number_format($combo_savings); ?>৳!
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Order Action Buttons -->
                <div class="flex flex-col gap-3">
                    <button onclick="document.getElementById('checkout-area').scrollIntoView({ behavior: 'smooth' });"
                            class="w-full bg-gradient-to-r from-blue-600 via-indigo-600 to-blue-700 hover:from-blue-700 hover:to-indigo-800 text-white font-black text-base sm:text-lg py-4 px-6 rounded-2xl shadow-lg hover:shadow-xl transition duration-300 transform hover:-translate-y-0.5 text-center">
                        অর্ডার করুন (নিচে সরাসরি ফর্ম) ⬇
                    </button>

                    <?php if ($store_phone) : ?>
                        <a href="tel:<?php echo esc_attr($store_phone); ?>"
                           class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-sm sm:text-base py-3.5 px-6 rounded-2xl shadow transition text-center flex items-center justify-center gap-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                            কল করতে চাপুন: <?php echo esc_html($store_phone); ?>
                        </a>
                    <?php endif; ?>
                </div>

            </div>

        </div>
    </div>

    <!-- ── 2. INCLUDED PRODUCTS DETAIL SHOWCASE ───────────────── -->
    <?php if (!empty($combo_items)) : ?>
    <div class="py-14 bg-white border-b border-gray-100 relative overflow-hidden">
        <!-- Decorative background dots -->
        <div class="absolute inset-0 opacity-[0.03]" style="background-image: radial-gradient(circle, #3b82f6 1px, transparent 1px); background-size: 28px 28px;"></div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative">
            <!-- Section Header -->
            <div class="text-center mb-10">
                <span class="inline-flex items-center gap-2 bg-blue-600 text-white text-xs font-black px-4 py-1.5 rounded-full uppercase tracking-widest mb-3 shadow-md">
                    <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path d="M5 3a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2V5a2 2 0 00-2-2H5zM5 11a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2v-2a2 2 0 00-2-2H5zM11 5a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V5zM14 11a1 1 0 011 1v1h1a1 1 0 110 2h-1v1a1 1 0 11-2 0v-1h-1a1 1 0 110-2h1v-1a1 1 0 011-1z"/></svg>
                    প্যাকেজে যা যা পাচ্ছেন
                </span>
                <h2 class="text-2xl sm:text-3xl lg:text-4xl font-black text-gray-900 leading-tight">
                    <?php echo count($combo_items); ?>টি প্রিমিয়াম পণ্যের মেগা প্যাকেজ
                </h2>
                <p class="text-sm text-gray-500 mt-2 max-w-xl mx-auto">
                    প্রতিটি পণ্য আলাদাভাবে কিনলে গুনতে হবে অনেক বেশি — কিন্তু এই কম্বোতে পাচ্ছেন সর্বোচ্চ ছাড়ে!
                </p>
            </div>

            <?php 
            // Separate free gifts from main items
            $main_items = array_filter($combo_items, fn($i) => empty($i['is_free_gift']));
            $gift_items = array_filter($combo_items, fn($i) => !empty($i['is_free_gift']));
            $idx = 0;
            ?>

            <!-- Main Products Grid (up to 5 per row) -->
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4 mb-6">
                <?php foreach ($main_items as $item) : 
                    $idx++;
                    $accent_colors = [
                        1 => ['bg' => 'from-blue-500 to-indigo-600',    'badge' => 'bg-blue-600'],
                        2 => ['bg' => 'from-violet-500 to-purple-600',  'badge' => 'bg-violet-600'],
                        3 => ['bg' => 'from-pink-500 to-rose-600',      'badge' => 'bg-pink-600'],
                        4 => ['bg' => 'from-amber-500 to-orange-600',   'badge' => 'bg-amber-500'],
                        5 => ['bg' => 'from-teal-500 to-emerald-600',   'badge' => 'bg-teal-600'],
                    ];
                    $ac = $accent_colors[$idx] ?? $accent_colors[1];
                ?>
                <div class="group bg-white rounded-3xl overflow-hidden shadow-md border border-gray-100 hover:shadow-xl hover:-translate-y-1.5 transition-all duration-300 flex flex-col">
                    <!-- Image with gradient overlay and step number -->
                    <div class="relative overflow-hidden aspect-[4/3] bg-gray-50">
                        <img src="<?php echo esc_url($item['img']); ?>" 
                             class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" 
                             alt="<?php echo esc_attr($item['title']); ?>">
                        <!-- Gradient overlay bottom -->
                        <div class="absolute inset-x-0 bottom-0 h-20 bg-gradient-to-t from-black/50 to-transparent"></div>
                        <!-- Step number badge -->
                        <div class="absolute top-3.5 left-3.5 bg-gradient-to-r <?php echo $ac['bg']; ?> text-white w-9 h-9 rounded-2xl flex items-center justify-center font-black text-sm shadow-lg">
                            <?php echo $idx; ?>
                        </div>
                        <!-- Qty badge (always show) -->
                        <div class="absolute top-3.5 right-3.5 bg-gray-900/80 text-white text-xs font-black px-2.5 py-1 rounded-xl backdrop-blur-sm">
                            <?php echo $item['qty']; ?>টি
                        </div>
                        <!-- Price on image -->
                        <div class="absolute bottom-2.5 right-3 text-white text-xs font-black drop-shadow">
                            <span class="line-through opacity-70"><?php echo number_format($item['item_total']); ?>৳</span>
                        </div>
                    </div>

                    <!-- Card Body -->
                    <div class="p-4 flex-grow flex flex-col justify-between">
                        <div>
                            <h3 class="text-base font-extrabold text-gray-900 mb-1.5 leading-snug line-clamp-2"><?php echo esc_html($item['title']); ?></h3>
                            <?php if (!empty($item['short_desc'])) : ?>
                                <p class="text-xs text-gray-500 line-clamp-2 mb-2"><?php echo wp_strip_all_tags($item['short_desc']); ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="flex items-center justify-between pt-3 border-t border-dashed border-gray-100 mt-auto">
                            <span class="text-xs text-gray-400 font-medium">পরিমাণ: <strong class="text-gray-700"><?php echo $item['qty']; ?>টি</strong></span>
                            <span class="text-sm font-black text-red-500 line-through"><?php echo number_format($item['item_total']); ?>৳</span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Free Gift Items (Highlighted Separately) -->
            <?php if (!empty($gift_items)) : ?>
                <div class="relative mt-6">
                    <!-- Glowing background -->  
                    <div class="absolute -inset-1 bg-gradient-to-r from-amber-400 via-yellow-300 to-orange-400 rounded-[28px] opacity-20 blur-md"></div>
                    <div class="relative bg-gradient-to-br from-yellow-50 via-amber-50 to-orange-50 border-2 border-amber-300/70 rounded-3xl overflow-hidden">
                        <!-- Top decorative ribbon -->
                        <div class="bg-gradient-to-r from-amber-500 to-orange-500 text-white px-6 py-3 flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span class="text-xl">🎁</span>
                                <div>
                                    <h3 class="text-sm font-black tracking-wide">ফ্রি বোনাস উপহার</h3>
                                    <p class="text-[10px] text-amber-100">এই কম্বোতে সম্পূর্ণ বিনামূল্যে পাচ্ছেন</p>
                                </div>
                            </div>
                            <span class="flex items-center gap-1 bg-white text-amber-600 text-[11px] font-black px-3 py-1.5 rounded-full shadow">
                                <span class="relative flex h-2 w-2">
                                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
                                    <span class="relative inline-flex rounded-full h-2 w-2 bg-amber-500"></span>
                                </span>
                                FREE GIFT
                            </span>
                        </div>
                        <!-- Gift items -->
                        <div class="p-5">
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                                <?php foreach ($gift_items as $gift) : ?>
                                    <div class="group flex items-center gap-3 bg-white rounded-2xl p-3 border border-amber-100 shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all duration-200">
                                        <div class="relative w-16 h-16 rounded-xl overflow-hidden border-2 border-amber-200 shrink-0 bg-amber-50">
                                            <?php 
                                            $g_img = $gift['img'] ?? '';
                                            $g_is_placeholder = !empty($g_img) && strpos($g_img, 'gift-box.png') !== false;
                                            if (!empty($g_img) && !$g_is_placeholder) : ?>
                                                <img src="<?php echo esc_url($g_img); ?>" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-300" alt="<?php echo esc_attr($gift['title']); ?>">
                                            <?php else : ?>
                                                <div class="w-full h-full flex items-center justify-center"><span class="text-2xl">🎁</span></div>
                                            <?php endif; ?>
                                            <!-- FREE ribbon on image -->
                                            <div class="absolute bottom-0 inset-x-0 bg-amber-500 text-white text-[8px] font-black text-center py-0.5">FREE</div>
                                        </div>
                                        <div class="min-w-0">
                                            <h4 class="text-sm font-extrabold text-gray-900 line-clamp-2 leading-snug"><?php echo esc_html($gift['title']); ?></h4>
                                            <p class="text-[10px] text-amber-600 font-bold mt-0.5">মূল্য: ১০০% FREE 🎉</p>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Premium Summary Total Bar -->
            <?php 
            $discount_pct = ($combo_regular_total > 0) ? round((($combo_savings) / $combo_regular_total) * 100) : 0;
            ?>
            <div class="mt-8 relative overflow-hidden rounded-3xl shadow-2xl">
                <!-- Background -->
                <div class="absolute inset-0 bg-gradient-to-br from-gray-950 via-gray-900 to-slate-900"></div>
                <!-- Decorative blobs -->
                <div class="absolute -top-6 -right-6 w-40 h-40 bg-emerald-500/10 rounded-full blur-2xl"></div>
                <div class="absolute -bottom-6 -left-6 w-40 h-40 bg-blue-500/10 rounded-full blur-2xl"></div>

                <div class="relative p-6 sm:p-8">
                    <!-- Top label -->
                    <div class="flex items-center justify-center mb-5">
                        <div class="h-px flex-grow bg-white/10"></div>
                        <span class="mx-4 text-[11px] text-gray-400 font-bold uppercase tracking-widest whitespace-nowrap">মূল্য তুলনা</span>
                        <div class="h-px flex-grow bg-white/10"></div>
                    </div>

                    <div class="grid grid-cols-3 gap-3 items-center">
                        <!-- Regular price -->
                        <div class="text-center bg-white/5 border border-white/10 rounded-2xl p-4">
                            <p class="text-[10px] text-gray-400 font-bold uppercase tracking-wider mb-1">রেগুলার মূল্য</p>
                            <p class="text-xl sm:text-2xl font-black text-red-400 line-through decoration-2"><?php echo number_format($combo_regular_total); ?>৳</p>
                        </div>

                        <!-- Discount % center badge -->
                        <div class="flex flex-col items-center justify-center">
                            <?php if ($discount_pct > 0) : ?>
                            <div class="bg-gradient-to-b from-emerald-400 to-emerald-600 text-white rounded-2xl w-16 h-16 sm:w-20 sm:h-20 flex flex-col items-center justify-center shadow-lg shadow-emerald-900/40">
                                <span class="text-lg sm:text-2xl font-black leading-none"><?php echo $discount_pct; ?>%</span>
                                <span class="text-[9px] font-bold tracking-wider opacity-80">ছাড়</span>
                            </div>
                            <?php else : ?>
                            <div class="text-white text-3xl">→</div>
                            <?php endif; ?>
                        </div>

                        <!-- Offer price -->
                        <div class="text-center bg-gradient-to-br from-emerald-600/20 to-emerald-500/10 border border-emerald-500/30 rounded-2xl p-4">
                            <p class="text-[10px] text-emerald-400 font-bold uppercase tracking-wider mb-1">অফার মূল্য</p>
                            <p class="text-xl sm:text-2xl font-black text-white"><?php echo number_format($combo_offer_price); ?>৳</p>
                        </div>
                    </div>

                    <!-- Savings banner -->
                    <?php if ($combo_savings > 0) : ?>
                    <div class="mt-5 bg-gradient-to-r from-emerald-500 to-teal-500 rounded-2xl p-4 flex items-center gap-3 shadow-lg shadow-emerald-900/30">
                        <span class="text-2xl">🎉</span>
                        <div>
                            <p class="text-white/80 text-[11px] font-bold uppercase tracking-wider">আপনার মোট সাশ্রয়</p>
                            <p class="text-white text-2xl sm:text-3xl font-black leading-none"><?php echo number_format($combo_savings); ?>৳</p>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ── 3. BOTTOM HIGH-CONVERTING FAST ORDER FORM ───────────── -->
    <div id="checkout-area" class="max-w-4xl mx-auto px-4 pt-12">
        <div class="bg-white rounded-3xl shadow-2xl border-2 border-primary/30 overflow-hidden">
            
            <!-- Order Form Header Banner -->
            <div class="bg-gradient-to-r from-blue-600 to-indigo-700 text-white p-6 sm:p-8 text-center">
                <span class="bg-yellow-400 text-gray-950 text-xs font-black px-3.5 py-1 rounded-full uppercase tracking-wider inline-block mb-2 shadow">
                    ক্যাশ অন ডেলিভারি
                </span>
                <h2 class="text-xl sm:text-2xl lg:text-3xl font-extrabold leading-tight mb-1">
                    <?php echo esc_html($combo_title); ?>
                </h2>
                <p class="text-xs sm:text-sm text-blue-100 mt-1 max-w-lg mx-auto leading-relaxed">
                    <?php echo esc_html($combo_subtitle); ?>
                </p>
                <div class="mt-3 border-t border-white/20 pt-3 text-xs text-blue-200 font-medium">
                    অর্ডার সম্পন্ন করতে নিচের ফর্মটি পূরণ করুন 👇
                </div>
            </div>

            <!-- Order Form Body -->
            <form id="fmb-combo-quick-checkout-form" class="p-6 sm:p-8 space-y-6">
                <input type="hidden" name="action" value="fmb_process_quick_checkout">
                <input type="hidden" name="combo_id" value="<?php echo $combo_id; ?>">
                <input type="hidden" name="combo_custom_title" value="<?php echo esc_attr($combo_title); ?>">
                <input type="hidden" name="combo_price" value="<?php echo $combo_offer_price; ?>">

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-bold text-gray-800 mb-1.5">আপনার নাম <span class="text-red-500">*</span></label>
                        <input type="text" name="billing_first_name" id="combo_billing_first_name" required placeholder="আপনার সম্পূর্ণ নাম লিখুন"
                               class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-primary focus:outline-none text-sm transition">
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-800 mb-1.5">মোবাইল নম্বর <span class="text-red-500">*</span></label>
                        <input type="tel" name="billing_phone" id="combo_billing_phone" required placeholder="১১ ডিজিটের মোবাইল নম্বর লিখুন"
                               class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-primary focus:outline-none text-sm transition">
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-800 mb-1.5">পূর্ণাঙ্গ ঠিকানা <span class="text-red-500">*</span></label>
                        <textarea name="billing_address_1" id="combo_billing_address_1" required rows="2" placeholder="আপনার জেলা, থানা ও এলাকা/বাড়ির নম্বর লিখুন"
                                  class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-primary focus:outline-none text-sm transition"></textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-800 mb-2">ডেলিভারি এলাকা নির্বাচন করুন <span class="text-red-500">*</span></label>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <label class="flex items-center gap-3 p-3.5 border-2 border-primary bg-blue-50/50 rounded-xl cursor-pointer">
                                <input type="radio" name="shipping_method_choice" value="dhaka" checked class="text-primary focus:ring-primary h-4 w-4">
                                <span class="text-sm font-bold text-gray-900"><?php echo $free_shipping ? 'ঢাকায় (ফ্রি ডেলিভারি)' : 'ঢাকায় (ডেলিভারি চার্জ ৳৬০)'; ?></span>
                            </label>
                            <label class="flex items-center gap-3 p-3.5 border-2 border-gray-200 hover:border-primary rounded-xl cursor-pointer">
                                <input type="radio" name="shipping_method_choice" value="outside" class="text-primary focus:ring-primary h-4 w-4">
                                <span class="text-sm font-bold text-gray-900"><?php echo $free_shipping ? 'ঢাকার বাইরে (ফ্রি ডেলিভারি)' : 'ঢাকার বাইরে (ডেলিভারি চার্জ ৳১২০)'; ?></span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Complete Itemized Order Summary Breakdown -->
                <div class="bg-gray-50 border border-gray-200 rounded-2xl p-5 space-y-4">
                    <h4 class="font-extrabold text-gray-800 text-sm uppercase border-b pb-2 tracking-wider">📦 প্যাকেজে অন্তর্ভুক্ত পণ্যসমূহের তালিকা:</h4>
                    
                    <div class="space-y-2 max-h-72 overflow-y-auto pr-1">
                        <?php foreach ($combo_items as $ci => $c_item) : 
                            $is_gift = !empty($c_item['is_free_gift']);
                        ?>
                            <div class="fmb-item-row flex items-center gap-2 bg-white p-2.5 rounded-xl border <?php echo $is_gift ? 'border-amber-300 bg-amber-50/50' : 'border-gray-100'; ?>">
                                <!-- Thumbnail -->
                                <?php if ($is_gift) : ?>
                                    <span class="text-xl shrink-0">🎁</span>
                                <?php else : ?>
                                    <img src="<?php echo esc_url($c_item['img']); ?>" class="w-10 h-10 object-cover rounded-lg shrink-0 border">
                                <?php endif; ?>

                                <!-- Title + badges -->
                                <div class="flex-grow min-w-0">
                                    <p class="text-xs font-bold text-gray-800 truncate"><?php echo esc_html($c_item['title']); ?></p>
                                    <?php if ($is_gift) : ?>
                                        <span class="inline-block bg-amber-100 text-amber-900 text-[10px] font-black px-1.5 py-0.5 rounded-md">১০০% ফ্রি উপহার</span>
                                    <?php else : ?>
                                        <span class="text-[10px] text-gray-400">একক মূল্য: <?php echo number_format($c_item['price']); ?>৳</span>
                                    <?php endif; ?>
                                </div>

                                <?php if ($is_gift) : ?>
                                    <!-- Free gift — fixed qty 1, no controls -->
                                    <input type="hidden" name="item_qty[<?php echo $c_item['id']; ?>]" value="1">
                                    <span class="shrink-0 text-xs font-black text-amber-600 bg-amber-100 px-2 py-1 rounded-lg">FREE</span>
                                <?php else : ?>
                                    <!-- Qty stepper -->
                                    <div class="shrink-0 flex items-center gap-1">
                                        <button type="button"
                                                onclick="fmbChangeQty(this, -1)"
                                                class="fmb-qty-btn w-7 h-7 rounded-lg bg-gray-100 hover:bg-red-100 hover:text-red-600 text-gray-700 font-black text-base flex items-center justify-center transition"
                                                data-price="<?php echo $c_item['sale_price']; ?>">−</button>
                                        <input  type="number"
                                                name="item_qty[<?php echo $c_item['id']; ?>]"
                                                value="<?php echo intval($c_item['qty']); ?>"
                                                min="1" max="20"
                                                class="fmb-qty-input w-9 h-7 text-center text-xs font-black border border-gray-200 rounded-lg focus:outline-none focus:border-primary"
                                                data-price="<?php echo $c_item['sale_price']; ?>"
                                                data-orig-qty="<?php echo intval($c_item['qty']); ?>"
                                                data-orig-subtotal="<?php echo floatval($c_item['item_total']); ?>"
                                                onchange="fmbRecalcTotal()">
                                        <button type="button"
                                                onclick="fmbChangeQty(this, 1)"
                                                class="fmb-qty-btn w-7 h-7 rounded-lg bg-gray-100 hover:bg-green-100 hover:text-green-700 text-gray-700 font-black text-base flex items-center justify-center transition"
                                                data-price="<?php echo $c_item['sale_price']; ?>">+</button>
                                    </div>
                                    <!-- Item subtotal -->
                                    <span class="fmb-item-subtotal shrink-0 text-xs font-black text-gray-700 w-16 text-right">
                                        <?php echo number_format($c_item['item_total']); ?>৳
                                    </span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="border-t border-dashed pt-3 space-y-2 text-xs sm:text-sm">
                        <div class="flex justify-between text-gray-600 font-medium">
                            <span>প্যাকেজ রেগুলার মোট মূল্য:</span>
                            <span class="line-through text-red-500 font-bold"><?php echo number_format($combo_regular_total); ?>৳</span>
                        </div>

                        <?php if ($combo_savings > 0) : ?>
                            <div class="flex justify-between text-emerald-700 font-extrabold bg-emerald-50 p-2 rounded-lg border border-emerald-100">
                                <span>🎉 মোট ডিসকাউন্ট / সেভিংস:</span>
                                <span>-<?php echo number_format($combo_savings); ?>৳</span>
                            </div>
                        <?php endif; ?>

                        <div class="flex justify-between text-gray-800 font-bold pt-1">
                            <span>প্যাকেজ অফার প্রাইস:</span>
                            <span id="fmb-combo-grand-total" class="text-primary font-extrabold text-base"><?php echo number_format($combo_offer_price); ?>৳</span>
                        </div>

                        <div class="flex justify-between text-gray-700 font-medium">
                            <span>ডেলিভারি চার্জ:</span>
                            <span id="combo-shipping-cost-display" class="font-bold text-gray-900"><?php echo $free_shipping ? '0৳' : '60৳'; ?></span>
                        </div>
                    </div>

                    <div class="border-t-2 border-gray-200 pt-3 flex justify-between text-base sm:text-lg font-black text-gray-900">
                        <span>সর্বমোট মূল্য:</span>
                        <span id="combo-grand-total-display" class="text-primary font-black"><?php echo number_format($combo_offer_price + ($free_shipping ? 0 : 60)); ?>৳</span>
                    </div>
                </div>

                <!-- Submit Button -->
                <button type="submit"
                        class="w-full bg-gradient-to-r from-emerald-600 to-green-600 hover:from-emerald-700 hover:to-green-700 text-white font-black text-lg py-4 px-6 rounded-2xl shadow-xl hover:shadow-2xl transition duration-300 text-center">
                    অর্ডার কনফার্ম করুন (৳<span id="combo-btn-total-display"><?php echo number_format($combo_offer_price + ($free_shipping ? 0 : 60)); ?></span>) ➔
                </button>

                <p class="text-center text-xs text-gray-500 font-medium">
                    🔒 আপনার দেওয়া তথ্য আমাদের কাছে সম্পূর্ণ নিরাপদ ও সুরক্ষিত থাকবে।
                </p>
            </form>

        </div>
    </div>

</div>

<!-- Facebook Pixel & Incomplete Order Lead Tracking -->
<script>
var comboProductData = {
    id: <?php echo (int) $combo_id; ?>,
    name: <?php echo wp_json_encode($combo_title); ?>,
    price: <?php echo (float) $combo_offer_price; ?>,
    num_items: <?php echo count($combo_items); ?>
};

document.addEventListener("DOMContentLoaded", function() {
    var comboOfferPrice = <?php echo (float) $combo_offer_price; ?>;
    var isFreeShipping = <?php echo $free_shipping ? 'true' : 'false'; ?>;
    var dhakaShipping = isFreeShipping ? 0 : 60;
    var outsideShipping = isFreeShipping ? 0 : 120;

    var radios = document.querySelectorAll('input[name="shipping_method_choice"]');
    var shipCostDisplay = document.getElementById('combo-shipping-cost-display');
    var grandTotalDisplay = document.getElementById('combo-grand-total-display');
    var btnTotalDisplay = document.getElementById('combo-btn-total-display');

    function updateComboTotals() {
        var selectedShipping = dhakaShipping;
        radios.forEach(function(radio) {
            if (radio.checked && radio.value === 'outside') {
                selectedShipping = outsideShipping;
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

    // ── Facebook Pixel Event Tracking (Guaranteed No Duplicates) ──
    function checkPixel(callback) {
        if (typeof fbq === 'function') {
            callback();
        } else {
            var retry = 0;
            var interval = setInterval(function() {
                if (typeof fbq === 'function') {
                    clearInterval(interval);
                    callback();
                }
                if (++retry > 10) clearInterval(interval);
            }, 500);
        }
    }

    checkPixel(function() {
        // Track AddToCart once
        window.fmbTrackComboAddToCartOnce = function () {
            if (window.__fmbPixelAddToCartDone) return;
            window.__fmbPixelAddToCartDone = true;
            
            fbq('track', 'AddToCart', {
                content_ids: [String(comboProductData.id)],
                content_type: 'product',
                value: comboProductData.price,
                currency: 'BDT',
                content_name: comboProductData.name,
                num_items: comboProductData.num_items
            });
        };

        // Track on scroll 20% of distance to checkout-area
        var scrollFired = false;
        var addToCartScrollFired = false;
        var initiateCheckoutFired = false;
        var checkoutArea = document.getElementById('checkout-area');

        function getComboCheckoutDistance() {
            if (!checkoutArea) return 0;
            var rect = checkoutArea.getBoundingClientRect();
            var scrollTop = window.pageYOffset || document.documentElement.scrollTop || 0;
            return rect.top + scrollTop;
        }

        function fireComboInitiateCheckout() {
            if (initiateCheckoutFired) return;
            initiateCheckoutFired = true;

            fbq('track', 'InitiateCheckout', {
                content_ids: [String(comboProductData.id)],
                content_type: 'product',
                value: comboProductData.price,
                currency: 'BDT',
                content_name: comboProductData.name,
                num_items: comboProductData.num_items
            });
            console.log('%c[Meta Pixel: InitiateCheckout Fired]', 'background: #1877F2; color: #fff; font-weight: bold; padding: 3px 8px; border-radius: 4px;', comboProductData);
        }

        function handleComboScrollTracking() {
            var scrollTop = window.pageYOffset || document.documentElement.scrollTop || 0;
            var checkoutTop = getComboCheckoutDistance();

            if (checkoutTop > 0) {
                var threshold20 = (checkoutTop / 100) * 20;
                if (scrollTop >= threshold20) {
                    if (!scrollFired) {
                        fbq('trackCustom', 'Scroll', { percent: 20 });
                        console.log('%c[Meta Pixel: Scroll Fired]', 'background: #1877F2; color: #fff; font-weight: bold; padding: 3px 8px; border-radius: 4px;', { percent: 20 });
                        scrollFired = true;
                    }
                    if (!addToCartScrollFired) {
                        window.fmbTrackComboAddToCartOnce();
                        addToCartScrollFired = true;
                    }
                }
            }

            // Visible check fallback
            if (!initiateCheckoutFired && checkoutArea) {
                var rect = checkoutArea.getBoundingClientRect();
                var windowHeight = window.innerHeight || document.documentElement.clientHeight || 0;
                if (rect.top <= (windowHeight - 20) && rect.bottom >= 0) {
                    fireComboInitiateCheckout();
                }
            }
        }

        window.addEventListener('scroll', handleComboScrollTracking, { passive: true });
        window.addEventListener('resize', handleComboScrollTracking, { passive: true });

        // InitiateCheckout (checkout form visible)
        if (checkoutArea && 'IntersectionObserver' in window) {
            var observer = new IntersectionObserver(function(entries) {
                if (entries[0].isIntersecting && !initiateCheckoutFired) {
                    fireComboInitiateCheckout();
                }
            }, {
                threshold: 0,
                rootMargin: '0px 0px 50px 0px'
            });
            observer.observe(checkoutArea);
        }

        // Initial check on load
        handleComboScrollTracking();
    });

    // ── Incomplete Order Lead Autosave (Parity with single-product.php) ──
    var nameInput = document.getElementById('combo_billing_first_name');
    var phoneInput = document.getElementById('combo_billing_phone');
    var addrInput = document.getElementById('combo_billing_address_1');
    var autosaveTimer = null;

    function triggerIncompleteLeadAutosave() {
        if (!phoneInput) return;
        var phoneVal = phoneInput.value.replace(/[^\d]/g, '');
        if (phoneVal.length < 11) return;

        clearTimeout(autosaveTimer);
        autosaveTimer = setTimeout(function() {
            var formData = new FormData();
            formData.append('action', 'fmb_save_lead');
            formData.append('billing_first_name', nameInput ? nameInput.value : '');
            formData.append('billing_phone', phoneInput ? phoneInput.value : '');
            formData.append('billing_address_1', addrInput ? addrInput.value : '');
            formData.append('combo_id', comboProductData.id);
            formData.append('combo_title', comboProductData.name);

            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method: 'POST',
                body: formData
            }).catch(function(e){});
        }, 1000);
    }

    [nameInput, phoneInput, addrInput].forEach(function(el) {
        if (el) {
            el.addEventListener('input', triggerIncompleteLeadAutosave);
            el.addEventListener('change', triggerIncompleteLeadAutosave);
        }
    });

    // ── Form Submission ──
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
                if (typeof fbq === 'function') {
                    fbq('track', 'Purchase', {
                        content_ids: [String(comboProductData.id)],
                        content_type: 'product',
                        value: comboProductData.price,
                        currency: 'BDT',
                        content_name: comboProductData.name,
                        num_items: comboProductData.num_items
                    });
                }

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
    // ── Qty Stepper ──────────────────────────────────────────────
    // Base = combo offer price. Extra qty = add that item's sale price.
    var fmbBaseComboPrice = <?php echo floatval($combo_offer_price); ?>;
    var fmbFreeShipping   = <?php echo $free_shipping ? 'true' : 'false'; ?>;

    // ── Change qty +/- ────────────────────────────────────────────
    window.fmbChangeQty = function(btn, delta) {
        var stepperWrap = btn.parentElement;
        var input       = stepperWrap.querySelector('.fmb-qty-input');
        var itemRow     = stepperWrap.closest('.fmb-item-row');
        var subtotalEl  = itemRow ? itemRow.querySelector('.fmb-item-subtotal') : null;

        if (!input) return;

        var val   = parseInt(input.value) || 1;
        val = Math.max(1, Math.min(99, val + delta));
        input.value = val;

        var price   = parseFloat(input.dataset.price) || 0;
        var origQty = parseInt(input.dataset.origQty) || 1;
        var origSub = parseFloat(input.dataset.origSubtotal) || 0;
        
        if (subtotalEl) {
            var diff = val - origQty;
            var newSubtotal = origSub + (diff * price);
            subtotalEl.textContent = Math.round(newSubtotal).toLocaleString('en-BD') + '৳';
        }
        fmbRecalcTotal();
    };

    // ── Full Recalculation ────────────────────────────────────────
    // Price = combo offer price + (extra qty × item sale price)
    window.fmbRecalcTotal = function() {
        var extraCost = 0;
        document.querySelectorAll('.fmb-qty-input').forEach(function(inp) {
            var currQty  = parseInt(inp.value) || 1;
            var origQty  = parseInt(inp.dataset.origQty) || 1;
            var price    = parseFloat(inp.dataset.price) || 0;
            var diff     = currQty - origQty;   // positive = added more, negative = removed
            extraCost   += diff * price;
        });

        var itemsTotal = Math.max(0, Math.round(fmbBaseComboPrice + extraCost));

        // Shipping
        var shippingRadio = document.querySelector('input[name="shipping_method_choice"]:checked');
        var shippingZone  = shippingRadio ? shippingRadio.value : 'dhaka';
        var shippingCost  = 0;
        if (!fmbFreeShipping) {
            shippingCost = (shippingZone === 'dhaka') ? 60 : 120;
        }
        var grandTotal = itemsTotal + shippingCost;

        // ─ Update DOM ─
        var offerEl = document.getElementById('fmb-combo-grand-total');
        if (offerEl) offerEl.textContent = itemsTotal.toLocaleString('en-BD') + '৳';

        var shippingEl = document.getElementById('combo-shipping-cost-display');
        if (shippingEl) shippingEl.textContent = shippingCost + '৳';

        var grandEl = document.getElementById('combo-grand-total-display');
        if (grandEl) grandEl.textContent = grandTotal.toLocaleString('en-BD') + '৳';

        var btnSpan = document.getElementById('combo-btn-total-display');
        if (btnSpan) btnSpan.textContent = grandTotal.toLocaleString('en-BD');

        var hiddenPrice = document.querySelector('input[name="combo_price"]');
        if (hiddenPrice) hiddenPrice.value = itemsTotal;
    };

    // Recalc on shipping change only (NOT on page load — PHP values are correct)
    document.querySelectorAll('input[name="shipping_method_choice"]').forEach(function(r) {
        r.addEventListener('change', fmbRecalcTotal);
    });
</script>

<?php get_footer(); ?>
