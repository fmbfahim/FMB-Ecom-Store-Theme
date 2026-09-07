<?php
/**
 * Template Name: Front Page
 * Theme: FMB E-Com Store
 */
get_header();

// Slider Customizer Data
$theme_uri   = get_template_directory_uri();

$slide1      = get_theme_mod('fmb_hero_slide_1', $theme_uri . '/assets/images/banner1.png');
$slide1_link = get_theme_mod('fmb_hero_slide_1_link', home_url('/shop'));

$slide2      = get_theme_mod('fmb_hero_slide_2', $theme_uri . '/assets/images/banner2.png');
$slide2_link = get_theme_mod('fmb_hero_slide_2_link', home_url('/shop'));

$slide3      = get_theme_mod('fmb_hero_slide_3', $theme_uri . '/assets/images/banner3.png');
$slide3_link = get_theme_mod('fmb_hero_slide_3_link', home_url('/shop'));

$side_ad_img  = get_theme_mod('fmb_side_ad_image', $theme_uri . '/assets/images/side_ad.png');
$side_ad_link = get_theme_mod('fmb_side_ad_link', home_url('/shop'));
?>

<!-- ── 1. BANNER & SIDE PROMO AD SECTION ────────────────── -->
<section class="bg-gray-50 pt-1 sm:pt-2 pb-2 md:py-6 border-b border-gray-100">
    <div class="max-w-7xl mx-auto px-2 sm:px-4 lg:px-8">
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-4 items-stretch">
            
            <!-- Left 3 Columns: Interactive Image Slider (Responsive aspect ratio - Full width on Mobile) -->
            <div class="col-span-1 lg:col-span-3 relative rounded-xl sm:rounded-2xl overflow-hidden shadow-md bg-white group h-full flex flex-col justify-center"
                 x-data="{ active: 0, timer: null, total: <?php echo ($slide2 ? ($slide3 ? 3 : 2) : 1); ?> }"
                 x-init="if (total > 1) { timer = setInterval(() => { active = (active + 1) % total }, 4000); }"
                 @mouseenter="if (timer) clearInterval(timer)"
                 @mouseleave="if (total > 1) timer = setInterval(() => { active = (active + 1) % total }, 4000)">
                
                <!-- Responsive Ratio Frame (2.2:1 on mobile, 2.6:1 on desktop - Zero Cropping) -->
                <div class="relative w-full overflow-hidden aspect-[2.2/1] sm:aspect-[2.4/1] md:aspect-[2.6/1]">
                    
                    <!-- Horizontal Sliding Track -->
                    <div class="flex h-full w-full transition-transform duration-500 ease-in-out"
                         :style="'transform: translateX(-' + (active * 100) + '%)'">
                        
                        <!-- Slide 1 -->
                        <div class="w-full shrink-0 h-full relative">
                            <a href="<?php echo esc_url($slide1_link); ?>" class="block w-full h-full">
                                <?php if ($slide1) : ?>
                                    <img src="<?php echo esc_url($slide1); ?>" class="w-full h-full object-cover" alt="Banner 1">
                                <?php else : ?>
                                    <div class="w-full h-full bg-gradient-to-r from-blue-600 to-indigo-700 p-6 md:p-10 flex items-center justify-between text-white">
                                        <div class="space-y-2 md:space-y-4 max-w-lg">
                                            <span class="bg-yellow-400 text-gray-900 text-xs md:text-sm font-extrabold px-3 py-1 rounded-full uppercase">স্পেশাল অফার</span>
                                            <h2 class="text-2xl md:text-4xl font-extrabold leading-tight">সবচেয়ে জনপ্রিয় প্রডাক্ট সমূহে ৫০% পর্যন্ত ছাড়!</h2>
                                            <p class="text-xs md:text-base text-blue-100 line-clamp-2">আজই অর্ডার করুন এবং দ্রুত ফ্রি হোম ডেলিভারি গ্রহণ করুন।</p>
                                        </div>
                                        <div class="hidden sm:block">
                                            <span class="bg-white text-primary text-sm md:text-base font-extrabold px-6 py-3 rounded-xl shadow-lg hover:bg-gray-100 transition inline-block">অর্ডার করুন →</span>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </a>
                        </div>

                        <?php if ($slide2) : ?>
                        <!-- Slide 2 -->
                        <div class="w-full shrink-0 h-full relative">
                            <a href="<?php echo esc_url($slide2_link); ?>" class="block w-full h-full">
                                <img src="<?php echo esc_url($slide2); ?>" class="w-full h-full object-cover" alt="Banner 2">
                            </a>
                        </div>
                        <?php endif; ?>

                        <?php if ($slide3) : ?>
                        <!-- Slide 3 -->
                        <div class="w-full shrink-0 h-full relative">
                            <a href="<?php echo esc_url($slide3_link); ?>" class="block w-full h-full">
                                <img src="<?php echo esc_url($slide3); ?>" class="w-full h-full object-cover" alt="Banner 3">
                            </a>
                        </div>
                        <?php endif; ?>

                    </div>

                </div>

                <!-- Navigation Prev/Next Arrows -->
                <button @click="active = (active - 1 + total) % total" aria-label="Previous Slide" class="absolute left-2 top-1/2 -translate-y-1/2 bg-black/40 hover:bg-black/70 text-white w-7 h-7 sm:w-9 sm:h-9 rounded-full flex items-center justify-center transition opacity-75 sm:opacity-0 group-hover:opacity-100 z-10 font-bold text-xs sm:text-base">
                    ❮
                </button>
                <button @click="active = (active + 1) % total" aria-label="Next Slide" class="absolute right-2 top-1/2 -translate-y-1/2 bg-black/40 hover:bg-black/70 text-white w-7 h-7 sm:w-9 sm:h-9 rounded-full flex items-center justify-center transition opacity-75 sm:opacity-0 group-hover:opacity-100 z-10 font-bold text-xs sm:text-base">
                    ❯
                </button>

                <!-- Dots Pagination -->
                <div class="absolute bottom-2 left-1/2 -translate-x-1/2 flex items-center gap-1.5 z-10">
                    <template x-for="i in total" :key="i">
                        <button @click="active = i - 1" :class="active === i - 1 ? 'bg-white w-4 sm:w-6' : 'bg-white/50 w-2 sm:w-2.5'" class="h-1.5 sm:h-2.5 rounded-full transition-all duration-300"></button>
                    </template>
                </div>
            </div>

            <!-- Right 1 Column: Promotional Ad Banner (Hidden on Mobile, Visible on Desktop lg:flex) -->
            <div class="hidden lg:flex lg:col-span-1 rounded-2xl overflow-hidden shadow-md bg-white relative group h-full flex-col">
                <?php if ($side_ad_img) : ?>
                    <a href="<?php echo esc_url($side_ad_link); ?>" class="block w-full h-full flex-grow">
                        <img src="<?php echo esc_url($side_ad_img); ?>" class="w-full h-full object-cover rounded-2xl" alt="Promotional Ad">
                    </a>
                <?php else : ?>
                    <a href="<?php echo esc_url($side_ad_link); ?>" class="block w-full h-full bg-gradient-to-b from-blue-500 to-indigo-600 text-white p-5 flex flex-col justify-between space-y-4">
                        <div>
                            <span class="inline-block bg-white/20 text-white text-[11px] font-extrabold px-3 py-1 rounded-full uppercase tracking-wider mb-2">
                                স্পেশাল প্রোমো কোড
                            </span>
                            <h3 class="text-xl font-extrabold leading-snug">
                                ১ম অর্ডারে ফ্রি শিপিং ও ক্যাশব্যাক!
                            </h3>
                            <p class="text-xs text-blue-100 mt-2">
                                ব্যবহার করুন কুপন কোড: <span class="bg-white text-primary font-black px-2 py-0.5 rounded text-sm">FMB1ST</span>
                            </p>
                        </div>
                        <div class="bg-white/10 p-3 rounded-xl backdrop-blur-sm border border-white/20 text-center">
                            <div class="text-xs font-bold text-yellow-300">⚡ ক্যাশ অন ডেলিভারি সুবিধা</div>
                            <span class="mt-2 inline-block w-full bg-white text-primary font-extrabold py-2 px-4 rounded-lg text-sm shadow hover:bg-gray-100 transition">
                                এখনই অর্ডার করুন →
                            </span>
                        </div>
                    </a>
                <?php endif; ?>
            </div>

        </div>
    </div>
</section>

<!-- ── 2. AUTO-SLIDING CATEGORY SECTION ────────────────── -->
<?php
$categories = get_terms( array(
    'taxonomy'   => 'product_cat',
    'hide_empty' => true,
    'number'     => 12,
) );

if ( ! empty( $categories ) && ! is_wp_error( $categories ) ) :
?>
<section class="py-4 md:py-8 bg-white border-b border-gray-100 relative">
    <div class="max-w-7xl mx-auto px-3 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between mb-3 md:mb-4">
            <h2 class="text-lg md:text-2xl font-extrabold text-gray-900 flex items-center gap-1.5">
                <span class="text-primary">📂</span> পণ্য ক্যাটাগরি সমূহ
            </h2>
            <a href="<?php echo esc_url( get_permalink( wc_get_page_id( 'shop' ) ) ); ?>" class="text-xs md:text-sm font-bold text-primary hover:underline">
                সব ক্যাটাগরি →
            </a>
        </div>

        <!-- Step-by-Step Auto Sliding Categories Container -->
        <div x-data="{
                timer: null,
                isHovered: false,
                scrollNext() {
                    let container = $refs.catSlider;
                    if (!container) return;
                    if (container.scrollLeft + container.clientWidth >= container.scrollWidth - 15) {
                        container.scrollTo({ left: 0, behavior: 'smooth' });
                    } else {
                        container.scrollBy({ left: container.clientWidth * 0.75, behavior: 'smooth' });
                    }
                },
                scrollPrev() {
                    let container = $refs.catSlider;
                    if (!container) return;
                    if (container.scrollLeft <= 0) {
                        container.scrollTo({ left: container.scrollWidth, behavior: 'smooth' });
                    } else {
                        container.scrollBy({ left: -(container.clientWidth * 0.75), behavior: 'smooth' });
                    }
                },
                initTimer() {
                    this.timer = setInterval(() => {
                        if (!this.isHovered) {
                            this.scrollNext();
                        }
                    }, 4000);
                }
            }"
            x-init="initTimer()"
            @mouseenter="isHovered = true"
            @mouseleave="isHovered = false"
            class="relative group">

            <!-- Category Cards Track -->
            <div x-ref="catSlider" class="flex gap-2.5 sm:gap-4 overflow-x-auto scroll-smooth no-scrollbar py-1">
                <?php 
                foreach ( $categories as $cat ) : 
                    $thumbnail_id = get_term_meta( $cat->term_id, 'thumbnail_id', true );
                    $image_url    = $thumbnail_id ? wp_get_attachment_image_url( $thumbnail_id, 'thumbnail' ) : wc_placeholder_img_src();
                ?>
                    <a href="<?php echo esc_url( get_term_link( $cat ) ); ?>" class="shrink-0 w-28 sm:w-36 md:w-44 bg-[#EEF5FF] border border-blue-100/70 p-2 sm:p-3 rounded-xl sm:rounded-2xl text-center hover:shadow-md hover:-translate-y-1 transition duration-300 group/cat">
                        <div class="w-12 h-12 sm:w-16 sm:h-16 md:w-20 md:h-20 mx-auto rounded-full overflow-hidden bg-white p-1 border border-blue-200/60 shadow-sm mb-1.5 group-hover/cat:scale-105 transition">
                            <img src="<?php echo esc_url( $image_url ); ?>" alt="<?php echo esc_attr( $cat->name ); ?>" class="w-full h-full object-cover rounded-full">
                        </div>
                        <h4 class="font-bold text-gray-900 text-[11px] sm:text-xs md:text-sm truncate"><?php echo esc_html( $cat->name ); ?></h4>
                        <span class="text-[10px] sm:text-[11px] text-gray-500 font-medium"><?php echo $cat->count; ?> টি প্রোডাক্ট</span>
                    </a>
                <?php endforeach; ?>
            </div>

            <!-- Navigation Arrows -->
            <button @click="scrollPrev()" aria-label="Previous Categories" class="absolute -left-2 sm:-left-3 top-1/2 -translate-y-1/2 bg-white text-gray-800 shadow-md border border-gray-100 hover:bg-primary hover:text-white w-7 h-7 sm:w-9 sm:h-9 rounded-full flex items-center justify-center transition opacity-0 group-hover:opacity-100 z-10 font-bold text-xs sm:text-base">
                ❮
            </button>
            <button @click="scrollNext()" aria-label="Next Categories" class="absolute -right-2 sm:-right-3 top-1/2 -translate-y-1/2 bg-white text-gray-800 shadow-md border border-gray-100 hover:bg-primary hover:text-white w-7 h-7 sm:w-9 sm:h-9 rounded-full flex items-center justify-center transition opacity-0 group-hover:opacity-100 z-10 font-bold text-xs sm:text-base">
                ❯
            </button>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ── 2.5 SPECIAL PRODUCT COMBO OFFER SECTION ────────────────── -->
<!-- ── 2.5 SPECIAL PRODUCT COMBO OFFER SECTION ────────────────── -->
<?php
$combo_query = new WP_Query(array(
    'post_type'      => 'fmb_combo_offer',
    'posts_per_page' => 3,
    'post_status'    => 'publish',
));

if ($combo_query->have_posts()) :
    while ($combo_query->have_posts()) : $combo_query->the_post();
        $combo_id          = get_the_ID();
        $combo_badge       = get_post_meta($combo_id, '_fmb_combo_badge', true) ?: '🔥 মেগা কম্বো অফার - সাশ্রয়ী বান্ডেল';
        $combo_title       = get_the_title();
        $combo_subtitle    = get_post_meta($combo_id, '_fmb_combo_subtitle', true) ?: 'প্যাকেজের প্রডাক্টগুলো একসাথে অর্ডার করুন এবং পান বিশেষ ছাড়!';
        $combo_offer_price = floatval(get_post_meta($combo_id, '_fmb_combo_price', true));
        $free_shipping     = get_post_meta($combo_id, '_fmb_combo_free_shipping', true) === 'yes';
        
        $selected_pids = get_post_meta($combo_id, '_fmb_combo_product_ids', true) ?: array();
        if (!is_array($selected_pids)) {
            $selected_pids = array_filter(explode(',', $selected_pids));
        }

        $product_qtys   = get_post_meta($combo_id, '_fmb_combo_product_qtys', true) ?: array();
        $free_gift_name = get_post_meta($combo_id, '_fmb_combo_free_gift_name', true) ?: '';
        $free_gift_val  = floatval(get_post_meta($combo_id, '_fmb_combo_free_gift_val', true) ?: 0);

        $combo_regular_total = 0;
        $combo_items = array();

        foreach ($selected_pids as $pid) {
            $prod = wc_get_product($pid);
            if ($prod) {
                $regular_price = floatval($prod->get_regular_price() ?: $prod->get_price());
                $qty           = isset($product_qtys[$pid]) ? max(1, intval($product_qtys[$pid])) : 1;
                $item_total    = $regular_price * $qty;
                $combo_regular_total += $item_total;
                $thumb         = get_the_post_thumbnail_url($pid, 'medium') ?: wc_placeholder_img_src();

                $combo_items[] = array(
                    'id'            => $pid,
                    'title'         => $prod->get_name(),
                    'regular_price' => $regular_price,
                    'qty'           => $qty,
                    'item_total'    => $item_total,
                    'img'           => $thumb,
                    'is_free_gift'  => false,
                );
            }
        }

        if (!empty($free_gift_name)) {
            $combo_regular_total += $free_gift_val;
            $combo_items[] = array(
                'id'            => 0,
                'title'         => $free_gift_name,
                'regular_price' => $free_gift_val,
                'qty'           => 1,
                'item_total'    => $free_gift_val,
                'img'           => get_template_directory_uri() . '/assets/images/gift-box.png',
                'is_free_gift'  => true,
            );
        }

        $combo_savings = max(0, $combo_regular_total - $combo_offer_price);
        $combo_link    = add_query_arg('fmb_combo_id', $combo_id, home_url('/'));
        $display_title = !empty($combo_title) ? $combo_title : 'মেগা কম্বো স্পেশাল প্যাকেজ অফার';
        $enable_timer  = get_post_meta($combo_id, '_fmb_combo_enable_timer', true) ?: 'yes';
        $timer_hours   = intval(get_post_meta($combo_id, '_fmb_combo_timer_hours', true) ?: 24);

        // Separate Main Items and Free Gift
        $main_items     = array();
        $free_gift_item = null;
        foreach ($combo_items as $ci) {
            if (!empty($ci['is_free_gift'])) {
                $free_gift_item = $ci;
            } else {
                $main_items[] = $ci;
            }
        }

        $total_main = count($main_items);
        $visible_main = $main_items;
        $has_more = false;
        $more_count = 0;

        if ($total_main > 5) {
            $visible_main = array_slice($main_items, 0, 4);
            $has_more = true;
            $more_count = $total_main - 4;
        }
?>
<section class="py-5 sm:py-10 bg-gradient-to-br from-slate-950 via-blue-950 to-indigo-950 text-white relative overflow-hidden my-3 sm:my-6 border-y border-blue-800/40">
    <!-- Decorative Ambient Glow -->
    <div class="absolute -top-32 -left-32 w-80 h-80 bg-blue-500/20 rounded-full blur-3xl pointer-events-none"></div>
    <div class="absolute -bottom-32 -right-32 w-80 h-80 bg-indigo-500/20 rounded-full blur-3xl pointer-events-none"></div>

    <div class="max-w-7xl mx-auto px-3 sm:px-6 lg:px-8 relative z-10">
        
        <!-- Header -->
        <div class="text-center max-w-3xl mx-auto mb-4 sm:mb-8">
            <div class="inline-flex items-center gap-1.5 bg-amber-400/10 border border-amber-400/30 text-amber-300 text-[11px] sm:text-xs font-black px-3 py-1 rounded-full uppercase tracking-wider shadow-lg mb-1.5">
                <span class="animate-pulse">🔥</span>
                <span><?php echo esc_html($combo_badge); ?></span>
            </div>
            <h2 class="text-xl sm:text-3xl md:text-4xl font-black text-white leading-tight tracking-tight drop-shadow">
                <?php echo esc_html($display_title); ?>
            </h2>
            <?php if (!empty($combo_subtitle)) : ?>
                <p class="text-[11px] sm:text-xs md:text-sm text-blue-200/90 mt-1 max-w-xl mx-auto line-clamp-1">
                    <?php echo esc_html($combo_subtitle); ?>
                </p>
            <?php endif; ?>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-4 lg:gap-6 items-stretch">
            
            <!-- Left 7 Columns: Items Visual Showcase (3 items per line) -->
            <div class="lg:col-span-7 bg-white/10 backdrop-blur-xl border border-white/20 rounded-2xl p-3 sm:p-5 shadow-2xl flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between border-b border-white/15 pb-2 mb-3">
                        <span class="text-[11px] sm:text-xs font-extrabold uppercase tracking-wider text-blue-200 flex items-center gap-1.5">
                            <span>📦</span>
                            <span>প্যাকেজ প্রডাক্ট (<?php echo count($main_items); ?>টি আইটেম)</span>
                        </span>
                        <span class="text-[10px] sm:text-[11px] font-bold bg-amber-400/20 text-amber-300 px-2 py-0.5 rounded-full border border-amber-400/30">
                            সাশ্রয়ী বান্ডেল
                        </span>
                    </div>

                    <!-- 3 Items Per Line on Mobile, 4 Items Per Line on Desktop -->
                    <div class="grid grid-cols-3 sm:grid-cols-4 gap-2 sm:gap-3.5 items-stretch">
                        <?php foreach ($visible_main as $item) : ?>
                            <div class="bg-white text-gray-900 rounded-xl p-2 text-center shadow-md border border-blue-50/50 hover:shadow-xl transition duration-300 relative flex flex-col justify-between h-full">
                                <?php if (!empty($item['qty']) && $item['qty'] > 1) : ?>
                                    <span class="absolute -top-2 -right-1 bg-blue-600 text-white font-black text-[8px] sm:text-[9px] px-1.5 py-0.5 rounded-full shadow-md z-10">
                                        x<?php echo $item['qty']; ?>
                                    </span>
                                <?php endif; ?>

                                <div class="w-full aspect-square rounded-lg overflow-hidden mb-1 bg-gray-50 border border-gray-100 flex items-center justify-center">
                                    <img src="<?php echo esc_url($item['img']); ?>" class="w-full h-full object-cover" alt="<?php echo esc_attr($item['title']); ?>">
                                </div>
                                
                                <h4 class="font-bold text-[10px] sm:text-xs text-gray-900 line-clamp-2 leading-tight" title="<?php echo esc_attr($item['title']); ?>">
                                    <?php echo esc_html($item['title']); ?>
                                </h4>
                                
                                <div class="text-[10px] font-bold text-red-500 line-through mt-0.5">
                                    <?php echo number_format($item['item_total']); ?>৳
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <!-- If more than 5 items, show remaining card -->
                        <?php if ($has_more) : ?>
                            <a href="<?php echo esc_url($combo_link); ?>" 
                               class="bg-blue-900/60 hover:bg-blue-800/80 border-2 border-dashed border-blue-400/40 text-white rounded-xl p-2 text-center flex flex-col items-center justify-center transition duration-300 group">
                                <span class="text-xl sm:text-2xl font-black text-amber-300 group-hover:scale-110 transition">+<?php echo $more_count; ?></span>
                                <span class="text-[9px] sm:text-[10px] font-bold text-blue-200 mt-0.5 leading-tight">আরও প্রডাক্ট (ডিটেইলসে দেখুন ➔)</span>
                            </a>
                        <?php endif; ?>
                    </div>

                    <!-- Free Gift Shown Separately -->
                    <?php if (!empty($free_gift_item)) : ?>
                        <div class="mt-3 bg-gradient-to-r from-amber-500/20 via-yellow-500/20 to-amber-500/20 border border-amber-400/40 rounded-xl p-2 sm:p-2.5 flex items-center justify-between gap-2.5 text-amber-200">
                            <div class="flex items-center gap-2 min-w-0">
                                <div class="w-10 h-10 sm:w-11 sm:h-11 rounded-lg bg-amber-400/20 border border-amber-300/40 shrink-0 flex items-center justify-center overflow-hidden">
                                    <?php if (!empty($free_gift_item['img']) && strpos($free_gift_item['img'], 'gift-box.png') === false) : ?>
                                        <img src="<?php echo esc_url($free_gift_item['img']); ?>" class="w-full h-full object-cover" alt="">
                                    <?php else : ?>
                                        <span class="text-xl">🎁</span>
                                    <?php endif; ?>
                                </div>
                                <div class="truncate">
                                    <span class="bg-amber-400 text-gray-950 text-[9px] font-black px-1.5 py-0.5 rounded uppercase tracking-wider block w-fit mb-0.5">
                                        🎁 FREE GIFT (১০০% ফ্রি উপহার)
                                    </span>
                                    <h5 class="text-xs font-bold text-white truncate"><?php echo esc_html($free_gift_item['title']); ?></h5>
                                </div>
                            </div>
                            <span class="shrink-0 bg-amber-400 text-gray-950 font-black text-xs px-2.5 py-1 rounded-full shadow">
                                FREE ৳0
                            </span>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="mt-2.5 pt-2 border-t border-white/10 text-center text-[10px] text-blue-200/80 font-medium">
                    ✨ কম্বো অফারে সবগুলো প্রডাক্ট অর্ডারে পান বিশেষ মূল্যছাড় সুবিধা!
                </div>
            </div>

            <!-- Right 5 Columns: Minimal Compact Price & Order Box -->
            <div class="lg:col-span-5 bg-white text-gray-900 rounded-2xl p-4 sm:p-6 shadow-2xl border border-blue-100 flex flex-col justify-between">
                
                <div>
                    <!-- Large High-Converting Countdown Timer Box -->
                    <?php if ($enable_timer === 'yes') : ?>
                        <div class="bg-gradient-to-r from-red-600 via-rose-600 to-red-700 text-white rounded-2xl p-3 sm:p-4 text-center mb-4 shadow-xl border border-red-500/30">
                            <div class="text-xs sm:text-sm font-extrabold uppercase tracking-wider mb-2 flex items-center justify-center gap-1.5 text-yellow-300">
                                <span class="animate-pulse text-base">⏳</span>
                                <span>অফারের মেয়াদ শেষ হতে বাকি:</span>
                            </div>
                            <div class="flex items-center justify-center gap-2 sm:gap-3 text-gray-900 font-black">
                                <div class="bg-white/95 rounded-xl px-2.5 sm:px-3 py-1.5 min-w-[54px] sm:min-w-[64px] shadow text-center border border-gray-100">
                                    <span class="text-xl sm:text-2xl font-black text-red-600 block leading-none font-mono" id="combo-th-<?php echo $combo_id; ?>">00</span>
                                    <span class="text-[9px] font-bold text-gray-500 uppercase block mt-1">ঘণ্টা</span>
                                </div>
                                <span class="text-white text-xl font-bold mb-3">:</span>
                                <div class="bg-white/95 rounded-xl px-2.5 sm:px-3 py-1.5 min-w-[54px] sm:min-w-[64px] shadow text-center border border-gray-100">
                                    <span class="text-xl sm:text-2xl font-black text-red-600 block leading-none font-mono" id="combo-tm-<?php echo $combo_id; ?>">00</span>
                                    <span class="text-[9px] font-bold text-gray-500 uppercase block mt-1">মিনিট</span>
                                </div>
                                <span class="text-white text-xl font-bold mb-3">:</span>
                                <div class="bg-white/95 rounded-xl px-2.5 sm:px-3 py-1.5 min-w-[54px] sm:min-w-[64px] shadow text-center border border-gray-100">
                                    <span class="text-xl sm:text-2xl font-black text-red-600 block leading-none font-mono" id="combo-ts-<?php echo $combo_id; ?>">00</span>
                                    <span class="text-[9px] font-bold text-gray-500 uppercase block mt-1">সেকেন্ড</span>
                                </div>
                            </div>
                        </div>

                        <script>
                        (function() {
                            var elH = document.getElementById('combo-th-<?php echo $combo_id; ?>');
                            var elM = document.getElementById('combo-tm-<?php echo $combo_id; ?>');
                            var elS = document.getElementById('combo-ts-<?php echo $combo_id; ?>');
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

                    <div class="flex items-center justify-between border-b border-gray-100 pb-2 mb-2">
                        <span class="text-[11px] font-bold uppercase text-gray-500 tracking-wider">প্যাকেজ রেগুলার মূল্য:</span>
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-bold text-red-500 line-through"><?php echo number_format($combo_regular_total); ?>৳</span>
                            <?php if ($free_shipping) : ?>
                                <span class="bg-emerald-50 text-emerald-700 border border-emerald-200 font-extrabold text-[10px] px-2 py-0.5 rounded-full">
                                    🚚 ফ্রি ডেলিভারি!
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="my-2.5 text-center">
                        <span class="text-[11px] font-bold text-gray-500 uppercase tracking-wider block">বিশেষ অফার প্রাইস</span>
                        <div class="text-3xl sm:text-4xl font-black text-primary mt-0.5 flex items-baseline justify-center gap-1">
                            <span><?php echo number_format($combo_offer_price); ?></span>
                            <span class="text-xl font-bold">৳</span>
                        </div>
                        <?php if ($combo_savings > 0) : ?>
                            <div class="inline-block bg-emerald-100 text-emerald-800 text-[11px] font-black px-3 py-0.5 rounded-full mt-1.5 border border-emerald-200">
                                🎉 কম্বোতে সরাসরি বাঁচবে <?php echo number_format($combo_savings); ?>৳!
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <a href="<?php echo esc_url($combo_link); ?>"
                   class="block w-full bg-gradient-to-r from-blue-600 via-indigo-600 to-blue-700 hover:from-blue-700 hover:to-indigo-800 text-white font-black text-sm text-center py-3.5 px-5 rounded-xl shadow-xl transition duration-300 mt-3">
                    অর্ডার করুন (কম্বো অফার) ➔
                </a>
            </div>

        </div>

        <!-- View All Combos Page Button -->
        <div class="text-center mt-5 sm:mt-8">
            <a href="<?php echo esc_url(add_query_arg('fmb_all_combos', '1', home_url('/'))); ?>"
               class="inline-flex items-center gap-2 bg-gradient-to-r from-yellow-400 via-amber-400 to-yellow-500 text-gray-950 font-black text-xs px-5 py-2.5 rounded-full shadow-lg hover:shadow-xl transition border border-amber-300">
                <span>🎁 সকল কম্বো অফার একসাথে দেখুন</span>
                <span class="text-sm">➔</span>
            </a>
        </div>

    </div>
</section>
<?php 
    endwhile;
    wp_reset_postdata();
endif;
?>

<!-- ── 3. NEW PRODUCTS SECTION ("নতুন প্রডাক্ট") ────────────────── -->
<section class="py-8 md:py-12 bg-gray-50">
    <div class="max-w-7xl mx-auto px-3 sm:px-6 lg:px-8">
        <div class="text-center max-w-xl mx-auto mb-6 md:mb-8">
            <span class="bg-blue-100 text-primary text-xs font-bold px-3 py-1 rounded-full uppercase tracking-wider inline-block">New Arrival</span>
            <h2 class="text-2xl md:text-3xl font-extrabold text-gray-900 mt-1.5">
                নতুন প্রডাক্ট
            </h2>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-4 gap-2.5 sm:gap-4 md:gap-6">
            <?php
            $new_args = array(
                'post_type'      => 'product',
                'posts_per_page' => 8,
                'orderby'        => 'date',
                'order'          => 'DESC',
            );
            $new_query = new WP_Query( $new_args );

            if ( $new_query->have_posts() ) {
                while ( $new_query->have_posts() ) : $new_query->the_post();
                    wc_get_template_part( 'content', 'product' );
                endwhile;
                wp_reset_postdata();
            } else {
                echo '<p class="col-span-4 text-center text-gray-500">কোনো প্রডাক্ট পাওয়া যায়নি।</p>';
            }
            ?>
        </div>

        <!-- Bottom View All Button (Compact Small) -->
        <div class="mt-4 md:mt-6 text-center">
            <a href="<?php echo esc_url( get_permalink( wc_get_page_id( 'shop' ) ) ); ?>" class="inline-flex items-center gap-1 bg-white border border-primary text-primary hover:bg-primary hover:text-white text-xs font-bold px-3.5 py-1.5 rounded-full transition shadow-sm hover:shadow">
                সব নতুন প্রডাক্ট দেখুন →
            </a>
        </div>
    </div>
</section>

<!-- ── 4. POPULAR PRODUCTS SECTION ("জনপ্রিয় কালেকশন") ────────────────── -->
<section class="py-8 md:py-12 bg-white border-t border-b border-gray-100">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center max-w-xl mx-auto mb-6 md:mb-8">
            <span class="bg-orange-100 text-orange-600 text-xs font-bold px-3 py-1 rounded-full uppercase tracking-wider inline-block">Top Rated</span>
            <h2 class="text-2xl md:text-3xl font-extrabold text-gray-900 mt-1.5">
                জনপ্রিয় কালেকশন
            </h2>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-4 gap-2.5 sm:gap-4 md:gap-6">
            <?php
            $pop_args = array(
                'post_type'      => 'product',
                'posts_per_page' => 8,
                'meta_key'       => 'total_sales',
                'orderby'        => 'meta_value_num',
                'order'          => 'DESC',
            );
            $pop_query = new WP_Query( $pop_args );

            if ( $pop_query->have_posts() && $pop_query->post_count > 0 ) {
                while ( $pop_query->have_posts() ) : $pop_query->the_post();
                    wc_get_template_part( 'content', 'product' );
                endwhile;
                wp_reset_postdata();
            } else {
                // Fallback if total_sales meta is empty
                $fallback_query = new WP_Query( array( 'post_type' => 'product', 'posts_per_page' => 8 ) );
                while ( $fallback_query->have_posts() ) : $fallback_query->the_post();
                    wc_get_template_part( 'content', 'product' );
                endwhile;
                wp_reset_postdata();
            }
            ?>
        </div>

        <!-- Bottom View All Button (Compact Small) -->
        <div class="mt-4 md:mt-6 text-center">
            <a href="<?php echo esc_url( get_permalink( wc_get_page_id( 'shop' ) ) ); ?>" class="inline-flex items-center gap-1 bg-primary text-white text-xs font-bold px-4 py-1.5 rounded-full hover:bg-blue-700 transition shadow-sm hover:shadow">
                সব প্রডাক্ট দেখুন →
            </a>
        </div>
    </div>
</section>

<!-- ── 5. CUSTOMER REVIEWS SECTION ("গ্রাহকদের রিভিউ") ────────────────── -->
<?php
$rev1_name     = get_theme_mod('fmb_rev1_name', 'আরিফুল ইসলাম');
$rev1_location = get_theme_mod('fmb_rev1_location', 'ঢাকা');
$rev1_text     = get_theme_mod('fmb_rev1_text', 'অর্ডার করার পরদিনই ডেলিভারি পেয়েছি। প্রডাক্টের কোয়ালিটি ছবির মতোই একশতে একশ! ধন্যবাদ FMB Store কে।');
$rev1_avatar   = get_theme_mod('fmb_rev1_avatar', '');
$rev1_photo    = get_theme_mod('fmb_rev1_photo', '');

$rev2_name     = get_theme_mod('fmb_rev2_name', 'সাদিয়া আক্তার');
$rev2_location = get_theme_mod('fmb_rev2_location', 'চট্টগ্রাম');
$rev2_text     = get_theme_mod('fmb_rev2_text', 'ক্যাশ অন ডেলিভারিতে প্যাকেট খুলে পণ্য দেখে নেওয়ার সুবিধাটা সবচেয়ে ভালো লেগেছে। ফ্যানটি বেশ ভালো সার্ভিস দিচ্ছে।');
$rev2_avatar   = get_theme_mod('fmb_rev2_avatar', '');
$rev2_photo    = get_theme_mod('fmb_rev2_photo', '');

$rev3_name     = get_theme_mod('fmb_rev3_name', 'মাহমুদুল হাসান');
$rev3_location = get_theme_mod('fmb_rev3_location', 'সিলেট');
$rev3_text     = get_theme_mod('fmb_rev3_text', 'দাম অনুযায়ী প্রোডাক্ট মান অনেক ভালো। ডেলিভারি বয় খুব দ্রুত দিয়ে গেছে। ইনশাআল্লাহ সামনে আবার কেনাকাটা করবো।');
$rev3_avatar   = get_theme_mod('fmb_rev3_avatar', '');
$rev3_photo    = get_theme_mod('fmb_rev3_photo', '');
?>
<section class="py-16 bg-[#EEF5FF]">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center max-w-2xl mx-auto mb-10">
            <span class="bg-blue-200/60 text-primary text-xs font-bold px-3 py-1 rounded-full uppercase tracking-wider">Testimonials</span>
            <h2 class="text-2xl md:text-4xl font-extrabold text-gray-900 mt-2">
                আমাদের সন্তুষ্ট গ্রাহকদের রিভিউ
            </h2>
            <p class="text-sm md:text-base text-gray-600 mt-2">
                ১০০% জেনুইন প্রডাক্ট ও বিশ্বস্ত সার্ভিসের নিশ্চয়তা পাচ্ছেন প্রতিটি অর্ডারে।
            </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            
            <!-- Review 1 Card -->
            <div class="bg-white p-6 rounded-2xl shadow-sm border border-blue-100 flex flex-col justify-between">
                <div>
                    <div class="text-yellow-400 text-lg mb-2">★★★★★</div>
                    <p class="text-gray-700 text-sm italic leading-relaxed">
                        "<?php echo esc_html($rev1_text); ?>"
                    </p>

                    <?php if ($rev1_photo) : ?>
                        <div class="mt-3 rounded-xl overflow-hidden border border-gray-100 shadow-inner">
                            <img src="<?php echo esc_url($rev1_photo); ?>" alt="Review Product Photo" class="w-full h-40 object-cover hover:scale-105 transition duration-300">
                        </div>
                    <?php endif; ?>
                </div>
                <div class="mt-6 flex items-center gap-3 border-t pt-4 border-gray-100">
                    <?php if ($rev1_avatar) : ?>
                        <img src="<?php echo esc_url($rev1_avatar); ?>" class="w-10 h-10 rounded-full object-cover border border-blue-200" alt="<?php echo esc_attr($rev1_name); ?>">
                    <?php else : ?>
                        <div class="w-10 h-10 rounded-full bg-primary text-white font-bold flex items-center justify-center text-sm">
                            <?php echo esc_html(mb_substr($rev1_name, 0, 1)); ?>
                        </div>
                    <?php endif; ?>
                    <div>
                        <h4 class="font-bold text-gray-900 text-sm"><?php echo esc_html($rev1_name); ?></h4>
                        <span class="text-xs text-green-600 font-semibold">✔ Verified Buyer (<?php echo esc_html($rev1_location); ?>)</span>
                    </div>
                </div>
            </div>

            <!-- Review 2 Card -->
            <div class="bg-white p-6 rounded-2xl shadow-sm border border-blue-100 flex flex-col justify-between">
                <div>
                    <div class="text-yellow-400 text-lg mb-2">★★★★★</div>
                    <p class="text-gray-700 text-sm italic leading-relaxed">
                        "<?php echo esc_html($rev2_text); ?>"
                    </p>

                    <?php if ($rev2_photo) : ?>
                        <div class="mt-3 rounded-xl overflow-hidden border border-gray-100 shadow-inner">
                            <img src="<?php echo esc_url($rev2_photo); ?>" alt="Review Product Photo" class="w-full h-40 object-cover hover:scale-105 transition duration-300">
                        </div>
                    <?php endif; ?>
                </div>
                <div class="mt-6 flex items-center gap-3 border-t pt-4 border-gray-100">
                    <?php if ($rev2_avatar) : ?>
                        <img src="<?php echo esc_url($rev2_avatar); ?>" class="w-10 h-10 rounded-full object-cover border border-blue-200" alt="<?php echo esc_attr($rev2_name); ?>">
                    <?php else : ?>
                        <div class="w-10 h-10 rounded-full bg-indigo-600 text-white font-bold flex items-center justify-center text-sm">
                            <?php echo esc_html(mb_substr($rev2_name, 0, 1)); ?>
                        </div>
                    <?php endif; ?>
                    <div>
                        <h4 class="font-bold text-gray-900 text-sm"><?php echo esc_html($rev2_name); ?></h4>
                        <span class="text-xs text-green-600 font-semibold">✔ Verified Buyer (<?php echo esc_html($rev2_location); ?>)</span>
                    </div>
                </div>
            </div>

            <!-- Review 3 Card -->
            <div class="bg-white p-6 rounded-2xl shadow-sm border border-blue-100 flex flex-col justify-between">
                <div>
                    <div class="text-yellow-400 text-lg mb-2">★★★★★</div>
                    <p class="text-gray-700 text-sm italic leading-relaxed">
                        "<?php echo esc_html($rev3_text); ?>"
                    </p>

                    <?php if ($rev3_photo) : ?>
                        <div class="mt-3 rounded-xl overflow-hidden border border-gray-100 shadow-inner">
                            <img src="<?php echo esc_url($rev3_photo); ?>" alt="Review Product Photo" class="w-full h-40 object-cover hover:scale-105 transition duration-300">
                        </div>
                    <?php endif; ?>
                </div>
                <div class="mt-6 flex items-center gap-3 border-t pt-4 border-gray-100">
                    <?php if ($rev3_avatar) : ?>
                        <img src="<?php echo esc_url($rev3_avatar); ?>" class="w-10 h-10 rounded-full object-cover border border-blue-200" alt="<?php echo esc_attr($rev3_name); ?>">
                    <?php else : ?>
                        <div class="w-10 h-10 rounded-full bg-emerald-600 text-white font-bold flex items-center justify-center text-sm">
                            <?php echo esc_html(mb_substr($rev3_name, 0, 1)); ?>
                        </div>
                    <?php endif; ?>
                    <div>
                        <h4 class="font-bold text-gray-900 text-sm"><?php echo esc_html($rev3_name); ?></h4>
                        <span class="text-xs text-green-600 font-semibold">✔ Verified Buyer (<?php echo esc_html($rev3_location); ?>)</span>
                    </div>
                </div>
            </div>

        </div>
    </div>
</section>

<!-- Auto Ticker CSS Keyframes -->
<style>
@keyframes fmbTicker {
    0% { transform: translateX(0); }
    100% { transform: translateX(-50%); }
}
.animate-fmb-ticker {
    display: flex;
    width: max-content;
    animation: fmbTicker 25s linear infinite;
}
</style>

<?php
get_footer();