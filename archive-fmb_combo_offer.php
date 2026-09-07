<?php
/**
 * Archive Template for Dynamic Combo Offers (fmb_combo_offer CPT)
 * Displays all active published Combo Offers in a beautiful, high-converting layout.
 */

get_header();
?>

<div class="fmb-all-combos-archive bg-gray-50 min-h-screen py-10 sm:py-14">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <!-- Header Banner -->
        <div class="text-center max-w-3xl mx-auto mb-10 md:mb-14">
            <span class="bg-gradient-to-r from-amber-400 to-yellow-500 text-gray-950 text-xs sm:text-sm font-black px-4 py-1.5 rounded-full uppercase tracking-wider shadow inline-block mb-3">
                🔥 মেগা কম্বো কালেকশন
            </span>
            <h1 class="text-3xl sm:text-4xl md:text-5xl font-black text-gray-900 leading-tight">
                সকল স্পেশাল কম্বো অফার সমূহ
            </h1>
            <p class="text-sm sm:text-base md:text-lg text-gray-600 mt-3 leading-relaxed">
                সেরা প্রোডাক্টগুলো নিয়ে তৈরি আমাদের স্পেশাল কম্বো প্যাকেজগুলো থেকে আপনার পছন্দের বান্ডেলটি বেছে নিন এবং পান বড় ছাড় ও ফ্রি উপহার!
            </p>
        </div>

        <?php
        $combo_query = new WP_Query(array(
            'post_type'      => 'fmb_combo_offer',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
        ));

        if ($combo_query->have_posts()) :
        ?>
            <div class="space-y-8 md:space-y-12">
                <?php
                while ($combo_query->have_posts()) : $combo_query->the_post();
                    $combo_id          = get_the_ID();
                    $combo_badge       = get_post_meta($combo_id, '_fmb_combo_badge', true) ?: '🔥 মেগা কম্বো অফার';
                    $combo_title       = get_the_title();
                    $combo_subtitle    = get_post_meta($combo_id, '_fmb_combo_subtitle', true) ?: '';
                    $combo_offer_price = floatval(get_post_meta($combo_id, '_fmb_combo_price', true));
                    $free_shipping     = get_post_meta($combo_id, '_fmb_combo_free_shipping', true) === 'yes';

                    $selected_pids = get_post_meta($combo_id, '_fmb_combo_product_ids', true) ?: array();
                    if (!is_array($selected_pids)) {
                        $selected_pids = array_filter(explode(',', $selected_pids));
                    }

                    $product_qtys   = get_post_meta($combo_id, '_fmb_combo_product_qtys', true) ?: array();
                    $free_gift_id   = intval(get_post_meta($combo_id, '_fmb_combo_free_gift_id', true) ?: 0);
                    $free_gift_name = get_post_meta($combo_id, '_fmb_combo_free_gift_name', true) ?: '';
                    $free_gift_val  = floatval(get_post_meta($combo_id, '_fmb_combo_free_gift_val', true) ?: 0);

                    $gift_img = '';
                    if ($free_gift_id > 0) {
                        $gprod = wc_get_product($free_gift_id);
                        if ($gprod) {
                            if (empty($free_gift_name)) $free_gift_name = $gprod->get_name();
                            if ($free_gift_val <= 0) $free_gift_val = floatval($gprod->get_regular_price() ?: $gprod->get_price());
                            $gift_img = get_the_post_thumbnail_url($free_gift_id, 'medium') ?: wc_placeholder_img_src();
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
                            $reg_price  = floatval($prod->get_regular_price() ?: $prod->get_price());
                            $qty        = isset($product_qtys[$pid]) ? max(1, intval($product_qtys[$pid])) : 1;
                            $item_total = $reg_price * $qty;
                            $combo_regular_total += $item_total;
                            $thumb      = get_the_post_thumbnail_url($pid, 'medium') ?: wc_placeholder_img_src();

                            $combo_items[] = array(
                                'title'        => $prod->get_name(),
                                'reg_price'    => $reg_price,
                                'qty'          => $qty,
                                'item_total'   => $item_total,
                                'img'          => $thumb,
                                'is_free_gift' => false,
                            );
                        }
                    }

                    if (!empty($free_gift_name)) {
                        $combo_regular_total += $free_gift_val;
                        $combo_items[] = array(
                            'title'        => $free_gift_name,
                            'reg_price'    => $free_gift_val,
                            'qty'          => 1,
                            'item_total'   => $free_gift_val,
                            'img'          => $gift_img,
                            'is_free_gift' => true,
                        );
                    }

                    $combo_savings = max(0, $combo_regular_total - $combo_offer_price);
                    $combo_link    = add_query_arg('fmb_combo_id', $combo_id, home_url('/'));
                ?>

                <!-- Single Combo Offer Showcase Card -->
                <div class="bg-gradient-to-br from-blue-900 via-indigo-900 to-slate-900 text-white rounded-3xl p-6 sm:p-8 md:p-10 shadow-2xl relative overflow-hidden border border-blue-800">
                    
                    <!-- Decorative Ambient Glow -->
                    <div class="absolute -top-24 -left-24 w-96 h-96 bg-blue-500/20 rounded-full blur-3xl pointer-events-none"></div>

                    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 md:gap-8 items-center relative z-10">
                        
                        <!-- Left 7 Columns: Header & Included Items -->
                        <div class="lg:col-span-7 flex flex-col justify-between h-full">
                            <div>
                                <div class="flex flex-wrap items-center gap-2 mb-3">
                                    <span class="bg-gradient-to-r from-yellow-400 to-amber-500 text-gray-950 text-xs font-black px-3 py-1 rounded-full uppercase tracking-wider shadow">
                                        <?php echo esc_html($combo_badge); ?>
                                    </span>
                                    <?php if ($free_shipping) : ?>
                                        <span class="bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 text-xs font-bold px-3 py-1 rounded-full">
                                            🚚 ফ্রি ডেলিভারি
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <h2 class="text-2xl sm:text-3xl md:text-4xl font-black text-white leading-tight">
                                    <?php echo esc_html($combo_title); ?>
                                </h2>
                                
                                <?php if (!empty($combo_subtitle)) : ?>
                                    <p class="text-xs sm:text-sm text-blue-200 mt-2">
                                        <?php echo esc_html($combo_subtitle); ?>
                                    </p>
                                <?php endif; ?>
                            </div>

                            <!-- Included Items Flow -->
                            <div class="mt-6 bg-white/10 backdrop-blur-md border border-white/15 rounded-2xl p-4">
                                <div class="text-xs font-bold text-blue-200 uppercase tracking-wider mb-3">
                                    📦 প্যাকেজের ভেতরে যা যা পাচ্ছেন (<?php echo count($combo_items); ?>টি আইটেম):
                                </div>
                                
                                <div class="grid grid-cols-<?php echo min(count($combo_items), 4); ?> gap-2 sm:gap-3">
                                    <?php foreach ($combo_items as $c_item) : ?>
                                        <div class="bg-white text-gray-900 rounded-xl p-2.5 text-center shadow-md relative hover:scale-105 transition">
                                            <?php if (!empty($c_item['is_free_gift'])) : ?>
                                                <span class="absolute -top-2 -right-1 bg-amber-500 text-white font-extrabold text-[9px] px-1.5 py-0.5 rounded-full shadow">
                                                    FREE GIFT 🎁
                                                </span>
                                            <?php elseif (!empty($c_item['qty']) && $c_item['qty'] > 1) : ?>
                                                <span class="absolute -top-2 -right-1 bg-blue-600 text-white font-extrabold text-[9px] px-1.5 py-0.5 rounded-full shadow">
                                                    x<?php echo $c_item['qty']; ?>
                                                </span>
                                            <?php endif; ?>

                                            <div class="w-14 h-14 sm:w-16 sm:h-16 mx-auto rounded-lg overflow-hidden mb-1.5 bg-gray-50 border flex items-center justify-center">
                                                <?php if (!empty($c_item['is_free_gift'])) : ?>
                                                    <span class="text-2xl">🎁</span>
                                                <?php else : ?>
                                                    <img src="<?php echo esc_url($c_item['img']); ?>" class="w-full h-full object-cover" alt="<?php echo esc_attr($c_item['title']); ?>">
                                                <?php endif; ?>
                                            </div>

                                            <h4 class="font-bold text-[11px] sm:text-xs text-gray-900 line-clamp-1"><?php echo esc_html($c_item['title']); ?></h4>
                                            <div class="text-[10px] text-gray-400 line-through mt-0.5">
                                                <?php echo !empty($c_item['is_free_gift']) ? 'FREE' : number_format($c_item['item_total']) . '৳'; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Right 5 Columns: Price & Action -->
                        <div class="lg:col-span-5 bg-white text-gray-900 rounded-3xl p-6 sm:p-8 shadow-2xl border border-blue-100 flex flex-col justify-between">
                            <div>
                                <div class="flex items-center justify-between border-b border-gray-100 pb-3">
                                    <span class="text-xs font-bold uppercase text-gray-500 tracking-wider">রেগুলার মোট মূল্য:</span>
                                    <span class="text-base font-bold text-red-500 line-through"><?php echo number_format($combo_regular_total); ?>৳</span>
                                </div>

                                <div class="my-4 text-center">
                                    <span class="text-xs font-bold text-gray-500 uppercase tracking-wider block">বিশেষ কম্বো বান্ডেল অফার প্রাইস</span>
                                    <div class="text-3xl sm:text-4xl font-black text-primary mt-1">
                                        <?php echo number_format($combo_offer_price); ?> <span class="text-xl font-bold">৳</span>
                                    </div>
                                    <?php if ($combo_savings > 0) : ?>
                                        <div class="inline-block bg-emerald-100 text-emerald-800 text-xs font-extrabold px-3 py-1 rounded-full mt-2 border border-emerald-200">
                                            🎉 সরাসরি সাশ্রয় হবে <?php echo number_format($combo_savings); ?>৳!
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <a href="<?php echo esc_url($combo_link); ?>"
                               class="block w-full bg-gradient-to-r from-blue-600 to-indigo-700 hover:from-blue-700 hover:to-indigo-800 text-white font-black text-base sm:text-lg text-center py-3.5 px-6 rounded-2xl shadow-xl hover:shadow-2xl transition duration-300 transform hover:-translate-y-0.5">
                                অর্ডার করুন (কম্বো অফার) →
                            </a>
                        </div>

                    </div>

                </div>

                <?php
                endwhile;
                wp_reset_postdata();
                ?>
            </div>
        <?php else : ?>
            <div class="text-center py-16 bg-white rounded-3xl shadow p-8 border">
                <div class="text-4xl mb-3">📦</div>
                <h3 class="text-xl font-bold text-gray-800">বর্তমানে কোনো কম্বো অফার এভেইলএবল নেই।</h3>
                <p class="text-gray-500 text-sm mt-1">এডমিন প্যানেল থেকে খুব শীঘ্রই নতুন কম্বো অফার যোগ করা হবে।</p>
                <a href="<?php echo home_url('/'); ?>" class="inline-block mt-4 bg-primary text-white font-bold px-6 py-2.5 rounded-xl hover:bg-secondary transition">
                    হোমপেজে ফিরে যান →
                </a>
            </div>
        <?php endif; ?>

    </div>
</div>

<?php get_footer(); ?>
