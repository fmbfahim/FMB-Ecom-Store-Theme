<?php
/**
 * Single Product Variant 3: Bold Direct-Response & FOMO Flash Deal
 */
if (!defined('ABSPATH')) exit;

$fmb_free_delivery_banner = fmb_is_effectively_free_delivery_catalog( $main_id );
$banner_image = get_theme_mod('fmb_free_delivery_image', '');
$banner_text  = get_theme_mod('fmb_free_delivery_text', '🚚 সারাদেশে ডেলিভারি সম্পূর্ণ বিনামূল্যে!');

$regular_price = $product->get_regular_price();
$sale_price = $product->get_sale_price();
$savings = ($regular_price && $sale_price && $regular_price > $sale_price) ? ($regular_price - $sale_price) : 0;
$discount_pct = ($regular_price && $savings) ? round(($savings / $regular_price) * 100) : 35;
?>

<style>
/* FOMO Deal Scoped Responsive Layout, Spacing & Padding System */
.fmb-v3-fomo {
    box-sizing: border-box;
}
.fmb-v3-fomo * {
    box-sizing: border-box;
}

/* Outer Section Wrappers */
.fmb-v3-fomo .fmb-fomo-container {
    max-width: 1200px;
    margin: 12px auto;
    padding: 0 10px;
}
@media (min-width: 640px) {
    .fmb-v3-fomo .fmb-fomo-container {
        margin: 20px auto;
        padding: 0 16px;
    }
}
@media (min-width: 1024px) {
    .fmb-v3-fomo .fmb-fomo-container {
        margin: 28px auto;
        padding: 0 20px;
    }
}

/* Hero Box Card */
.fmb-v3-fomo .fmb-fomo-hero-card {
    background: #ffffff;
    border: 1.5px solid #fecdd3;
    border-radius: 16px;
    padding: 12px;
    box-shadow: 0 6px 20px rgba(220, 38, 38, 0.05);
    display: grid;
    grid-template-columns: 1fr;
    gap: 16px;
    align-items: start;
    position: relative;
}
@media (min-width: 768px) {
    .fmb-v3-fomo .fmb-fomo-hero-card {
        border-width: 2.5px;
        border-radius: 24px;
        padding: 26px 28px;
        grid-template-columns: repeat(2, 1fr);
        gap: 32px;
        align-items: center;
    }
}

/* Left Image Box */
.fmb-v3-fomo .fmb-fomo-img-wrap {
    display: flex;
    flex-direction: column;
    gap: 10px;
    position: relative;
}
@media (min-width: 768px) {
    .fmb-v3-fomo .fmb-fomo-img-wrap {
        gap: 14px;
    }
}

.fmb-v3-fomo .fmb-fomo-img-box {
    background: #fff1f2;
    border: 1.5px solid #fecdd3;
    border-radius: 14px;
    padding: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    aspect-ratio: 1/1;
    overflow: hidden;
}
@media (min-width: 768px) {
    .fmb-v3-fomo .fmb-fomo-img-box {
        border-width: 2px;
        border-radius: 20px;
        padding: 8px;
    }
}

/* Floating Discount Badge */
.fmb-v3-fomo .fmb-fomo-discount-badge {
    position: absolute;
    top: 8px;
    left: 8px;
    z-index: 12;
    background: linear-gradient(135deg, #e11d48 0%, #be123c 100%);
    color: #ffffff;
    font-size: 11.5px;
    font-weight: 900;
    padding: 4px 12px;
    border-radius: 30px;
    box-shadow: 0 4px 12px rgba(225, 29, 72, 0.35);
    transform: rotate(-2deg);
}
@media (min-width: 768px) {
    .fmb-v3-fomo .fmb-fomo-discount-badge {
        top: 10px;
        left: 10px;
        font-size: 13px;
        padding: 5px 14px;
        transform: rotate(-3deg);
    }
}

/* Price Box */
.fmb-v3-fomo .fmb-fomo-price-card {
    background: linear-gradient(135deg, #fff1f2 0%, #fef3c7 100%);
    border: 1.5px solid #fca5a5;
    border-radius: 14px;
    padding: 12px 14px;
    margin-bottom: 14px;
}
@media (min-width: 768px) {
    .fmb-v3-fomo .fmb-fomo-price-card {
        border-width: 2px;
        border-radius: 16px;
        padding: 18px 22px;
        margin-bottom: 20px;
    }
}

/* Reusable Content Cards */
.fmb-v3-fomo .fmb-fomo-card {
    background: #ffffff;
    border: 1.5px solid #fecdd3;
    border-radius: 16px;
    padding: 14px 12px;
    box-shadow: 0 4px 15px rgba(220, 38, 38, 0.04);
}
@media (min-width: 768px) {
    .fmb-v3-fomo .fmb-fomo-card {
        border-width: 2px;
        border-radius: 22px;
        padding: 24px 28px;
    }
}
</style>

<div class="fmb-v3-fomo" style="background: #fff7ed; color: #1e293b; font-family: inherit; padding-bottom: 30px;">
    
    <!-- Top Flash Sale Alert & Countdown Timer Bar -->
    <div style="background: linear-gradient(135deg, #dc2626 0%, #b91c1c 50%, #991b1b 100%); color: #ffffff; padding: 9px 12px; text-align: center; font-weight: 800; box-shadow: 0 4px 12px rgba(220,38,38,0.35); display: flex; align-items: center; justify-content: center; gap: 8px; flex-wrap: wrap;">
        <span style="font-size: 15px; flex-shrink: 0;">⚡</span>
        <span style="font-size: clamp(12px, 3vw, 15px); line-height: 1.3;">সীমিত সময়ের স্পেশাল ধামাকা অফার!</span>
        <div style="display: inline-flex; align-items: center; gap: 4px; font-family: monospace; font-size: clamp(12px, 3vw, 14px); background: rgba(0,0,0,0.4); padding: 3px 10px; border-radius: 30px; border: 1px solid rgba(255,255,255,0.3); flex-shrink: 0;">
            <span id="fomo-h-box" style="background:#fff; color:#b91c1c; padding:2px 5px; border-radius:4px; font-weight:900; min-width:24px; text-align:center;">02</span><span style="opacity:.7">h</span>
            <span style="opacity:.5; margin:0 2px;">:</span>
            <span id="fomo-m-box" style="background:#fff; color:#b91c1c; padding:2px 5px; border-radius:4px; font-weight:900; min-width:24px; text-align:center;">45</span><span style="opacity:.7">m</span>
            <span style="opacity:.5; margin:0 2px;">:</span>
            <span id="fomo-s-box" style="background:#fff; color:#b91c1c; padding:2px 5px; border-radius:4px; font-weight:900; min-width:24px; text-align:center;">30</span><span style="opacity:.7">s</span>
        </div>
    </div>

    <!-- Main Flash Deal Hero Box -->
    <div class="fmb-fomo-container">
        <div class="fmb-fomo-hero-card">
            
            <!-- Left: Main Image with Floating Discount Badge and Thumbnails -->
            <div class="fmb-fomo-img-wrap">
                <div class="fmb-fomo-img-box">
                    <div class="fmb-fomo-discount-badge">
                        🔥 <?php echo esc_html($discount_pct); ?>% স্পেশাল ছাড়!
                    </div>
                    <?php 
                    $main_img_url = wp_get_attachment_image_url($product->get_image_id(), 'large') ?: wc_placeholder_img_src();
                    ?>
                    <img id="fmb-main-product-image-fomo" src="<?php echo esc_url($main_img_url); ?>" alt="<?php the_title(); ?>" style="width: 100%; height: 100%; object-fit: cover; border-radius: 10px;">
                </div>

                <!-- Gallery Thumbnails -->
                <?php 
                $gallery_ids = $product->get_gallery_image_ids();
                if($gallery_ids): 
                    $preview_ids = array_slice($gallery_ids, 0, 4);
                ?>
                <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px;">
                    <?php foreach($preview_ids as $g_id): 
                        $g_img_thumb = wp_get_attachment_image_url($g_id, 'thumbnail');
                        $g_img_large = wp_get_attachment_image_url($g_id, 'large');
                    ?>
                        <div onclick="document.getElementById('fmb-main-product-image-fomo').src='<?php echo esc_url($g_img_large); ?>'" style="background: #fff1f2; border: 1.5px solid #fecdd3; border-radius: 10px; overflow: hidden; aspect-ratio: 1/1; padding: 3px; box-shadow: 0 3px 8px rgba(220,38,38,0.08); cursor: pointer; transition: transform 0.2s ease;" onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform='scale(1)'">
                            <img src="<?php echo esc_url($g_img_thumb); ?>" alt="<?php the_title(); ?>" style="width: 100%; height: 100%; object-fit: cover; border-radius: 6px;">
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Right: High Urgency Details -->
            <div>
                <div style="display: flex; gap: 6px; align-items: center; margin-bottom: 8px; flex-wrap: wrap;">
                    <span style="background: #fee2e2; color: #b91c1c; font-size: 11.5px; font-weight: 800; padding: 3px 10px; border-radius: 6px;">
                        🔥 মেগা ডিল অফার
                    </span>
                    <span style="background: #fef3c7; color: #92400e; font-size: 11.5px; font-weight: 700; padding: 3px 10px; border-radius: 6px;">
                        ⭐ ৪.৯/৫ কাস্টমার রেটিং
                    </span>
                </div>

                <h1 style="font-size: clamp(18px, 4.2vw, 25px); font-weight: 900; color: #0f172a; margin: 0 0 12px 0; line-height: 1.35;">
                    <?php the_title(); ?>
                </h1>

                <!-- Price Box with Stock Meter -->
                <div class="fmb-fomo-price-card">
                    <div style="display: flex; align-items: baseline; gap: 10px; flex-wrap: wrap;">
                        <span style="font-size: clamp(26px, 6vw, 34px); font-weight: 900; color: #dc2626; line-height: 1.1;">
                            <?php echo wc_price($product->get_price()); ?>
                        </span>
                        <?php if ($savings > 0) : ?>
                            <span style="font-size: clamp(15px, 3.5vw, 19px); color: #94a3b8; text-decoration: line-through; font-weight: 600;">
                                <?php echo wc_price($regular_price); ?>
                            </span>
                            <span style="background: #dc2626; color: #ffffff; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 900;">
                                সরাসরি ৳<?php echo esc_html($savings); ?> ছাড়
                            </span>
                        <?php endif; ?>
                    </div>

                    <!-- Stock Progress Meter -->
                    <div style="margin-top: 10px; padding-top: 10px; border-top: 1px dashed rgba(220,38,38,0.25);">
                        <?php
                        $manage_stock = $product->managing_stock();
                        $stock_qty    = $manage_stock ? $product->get_stock_quantity() : null;
                        // Calculate bar fill & urgency label
                        if ($manage_stock && is_numeric($stock_qty)) {
                            $max_ref   = max($stock_qty, 50);
                            $bar_pct   = round(($stock_qty / $max_ref) * 100);
                            $bar_pct   = max(5, min($bar_pct, 95));
                            $stock_lbl = 'মাত্র ' . intval($stock_qty) . ' টি প্রোডাক্ট বাকি';
                        } else {
                            $bar_pct   = 78;
                            $stock_lbl = 'সীমিত স্টক বাকি!';
                        }
                        ?>
                        <div style="display: flex; justify-content: space-between; font-size: 11.5px; font-weight: 800; color: #991b1b; margin-bottom: 5px;">
                            <span>⚠️ দ্রুত অর্ডার করুন, স্টক শেষের পথে!</span>
                            <span><?php echo esc_html($stock_lbl); ?></span>
                        </div>
                        <div style="width: 100%; height: 8px; background: #e2e8f0; border-radius: 10px; overflow: hidden;">
                            <div style="width: <?php echo esc_attr($bar_pct); ?>%; height: 100%; background: linear-gradient(90deg, #f59e0b 0%, #dc2626 100%); border-radius: 10px;"></div>
                        </div>
                    </div>
                </div>

                <!-- Sales Proof Badge -->
                <div style="margin-top: 14px; margin-bottom: 14px;">
                    <?php fmb_render_sales_proof_badge( $main_id ); ?>
                </div>

                <div style="font-size: 13.5px; color: #475569; line-height: 1.6; margin-bottom: 14px;">
                    <?php echo apply_filters('woocommerce_short_description', $product->get_short_description()); ?>
                </div>

                <!-- Quantity -->
                <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 14px;">
                    <strong style="font-size: 13.5px; color: #1e293b;">পরিমাণ সিলেক্ট করুন:</strong>
                    <div style="display: inline-flex; border: 1.5px solid #fca5a5; border-radius: 8px; background: #ffffff; overflow: hidden;">
                        <button type="button" onclick="changeTopQty(-1)" style="padding: 6px 14px; font-size: 16px; font-weight: 800; background: #fee2e2; color: #b91c1c; border: none; cursor: pointer;">-</button>
                        <input type="text" id="top-qty-input" value="1" readonly style="width: 44px; text-align: center; font-weight: 800; font-size: 15px; border: none; outline: none;">
                        <button type="button" onclick="changeTopQty(1)" style="padding: 6px 14px; font-size: 16px; font-weight: 800; background: #fee2e2; color: #b91c1c; border: none; cursor: pointer;">+</button>
                    </div>
                </div>

                <!-- Action Button (High Energy) -->
                <div style="display: flex; flex-direction: column; gap: 8px; margin-bottom: 12px;">
                    <a href="#checkout-area" onclick="event.preventDefault(); fmbGoToCheckout();" class="fmb-order-now-btn" style="background: var(--btn-bg, var(--primary)) !important; color: var(--btn-text, #ffffff) !important; text-decoration: none; padding: 14px 20px; border-radius: 12px; font-size: clamp(16px, 4vw, 18px); font-weight: 900; text-align: center; box-shadow: 0 6px 20px rgba(0,0,0,0.15); display: flex; align-items: center; justify-content: center; gap: 8px;">
                        🔥 অফার মূল্যে অর্ডার কনফার্ম করুন
                    </a>
                    
                    <?php if (get_theme_mod('fmb_show_add_to_cart_button', true)) : ?>
                    <button type="button" id="fmb-add-to-cart-btn" class="fmb-single-atc-btn" style="background: #1e293b; color: #fff; border: none; padding: 12px 14px; border-radius: 10px; font-size: 14px; font-weight: 700; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; transition: all 0.2s;">
                        🛒 অ্যাড টু কার্ট
                    </button>
                    <?php endif; ?>
                </div>

                <?php
                $store_phone = get_theme_mod('fmb_global_phone', '');
                $wa_number = preg_replace('/[^0-9]/', '', $store_phone);
                if (strpos($wa_number, '88') !== 0 && !empty($wa_number)) $wa_number = '88' . $wa_number;
                $msg_url = get_theme_mod('fmb_messenger_url') ?: ('https://wa.me/' . $wa_number);
                ?>

                <div style="display: flex; gap: 8px; font-size: 12.5px;">
                    <?php if (get_theme_mod('fmb_show_call_button', true)) : ?>
                    <a href="tel:<?php echo esc_attr($store_phone); ?>" style="flex: 1; text-decoration: none; text-align: center; background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; padding: 9px; border-radius: 8px; font-weight: 700;">
                        📞 ফোন কল
                    </a>
                    <?php endif; ?>
                    <?php if (get_theme_mod('fmb_show_whatsapp_button', true)) : ?>
                    <a href="<?php echo esc_url($msg_url); ?>" target="_blank" style="flex: 1; text-decoration: none; text-align: center; background: #dcfce7; color: #15803d; border: 1px solid #86efac; padding: 9px; border-radius: 8px; font-weight: 700;">
                        💬 হোয়াটসঅ্যাপ
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Section 1: Steps (FOMO Cards) -->
    <?php 
    $steps_title = get_post_meta($main_id, '_fmb_steps_title', true) ?: 'ব্যবহারের সহজ নিয়মাবলী';
    if(!empty($steps) && is_array($steps)): 
    ?>
    <div class="fmb-fomo-container">
        <div class="fmb-fomo-card">
            <h3 style="font-size: clamp(17px, 3.5vw, 22px); font-weight: 900; text-align: center; color: #991b1b; margin: 0 0 16px 0;">
                ⚡ <?php echo esc_html($steps_title); ?>
            </h3>
            <div style="display: flex; flex-direction: column; gap: 10px;">
                <?php foreach($steps as $index => $step): if(empty($step)) continue; ?>
                    <div style="background: #fff1f2; border: 1px solid #fecdd3; border-radius: 10px; padding: 10px 14px; display: flex; align-items: center; gap: 10px;">
                        <span style="background: #dc2626; color: #fff; font-weight: 900; font-size: 12px; width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                            <?php echo $index + 1; ?>
                        </span>
                        <span style="font-size: 13.5px; color: #881337; font-weight: 600;">
                            <?php echo esc_html($step); ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Section 2: Info Cards (Direct Response) -->
    <?php 
    $cards_title = get_post_meta($main_id, '_fmb_cards_title', true) ?: 'কেন এটি সেরা?';
    if(!empty($cards) && is_array($cards)): 
    ?>
    <div class="fmb-fomo-container">
        <h3 style="font-size: clamp(18px, 4vw, 24px); font-weight: 900; text-align: center; color: #0f172a; margin-bottom: 16px;">
            🔥 <?php echo esc_html($cards_title); ?>
        </h3>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 12px;">
            <?php foreach($cards as $card): 
                if(empty($card['title']) && empty($card['desc'])) continue;
            ?>
                <div style="background: #ffffff; border: 1.5px solid #fed7aa; border-radius: 16px; padding: 16px 14px; text-align: center; box-shadow: 0 4px 12px rgba(245,158,11,0.06);">
                    <?php if(!empty($card['img'])): ?>
                        <img src="<?php echo esc_url($card['img']); ?>" alt="<?php echo esc_attr($card['title']); ?>" style="width: 55px; height: 55px; object-fit: contain; margin: 0 auto 10px; border-radius: 10px;">
                    <?php else: ?>
                        <div style="width: 44px; height: 44px; background: #ffedd5; color: #ea580c; font-size: 20px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 10px;">
                            🔥
                        </div>
                    <?php endif; ?>
                    <h4 style="font-size: 15px; font-weight: 800; color: #9a3412; margin: 0 0 6px 0;"><?php echo esc_html($card['title']); ?></h4>
                    <p style="font-size: 12.5px; color: #475569; line-height: 1.5; margin: 0;"><?php echo esc_html($card['desc']); ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Upsells -->
    <?php 
    if (!empty($upsell_data) && is_array($upsell_data)) :
        $upsell_post_ids = [];
        $upsell_logic_map = [];
        foreach($upsell_data as $u_item) {
            if (!empty($u_item['id'])) {
                $u_id = absint($u_item['id']);
                $upsell_post_ids[] = $u_id;
                $upsell_logic_map[$u_id] = [
                    'qty'   => absint($u_item['logic_qty'] ?? 0),
                    'type'  => sanitize_text_field($u_item['logic_type'] ?? ''),
                    'value' => floatval($u_item['logic_value'] ?? 0),
                ];
            }
        }

        if (!empty($upsell_post_ids)) :
            $upsells_query = new WP_Query([
                'post_type' => 'product',
                'post__in' => $upsell_post_ids,
                'posts_per_page' => -1,
                'orderby' => 'post__in'
            ]);
            if ($upsells_query->have_posts()) :
    ?>
    <div class="fmb-fomo-container">
        <h3 style="font-size: clamp(16px, 3.5vw, 20px); font-weight: 900; text-align: center; color: #991b1b; margin-bottom: 12px;"><?php echo esc_html($upsell_title); ?></h3>
        <style>
        .fmb-upsell-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 10px; }
        @media (max-width: 767px) { .fmb-upsell-grid { grid-template-columns: repeat(3, 1fr); gap: 8px; } }
        @media (max-width: 480px) { .fmb-upsell-grid { grid-template-columns: repeat(2, 1fr); gap: 7px; } }
        </style>
        <div class="fmb-upsell-grid">
            <?php while ($upsells_query->have_posts()) : $upsells_query->the_post(); 
                $u_prod = wc_get_product(get_the_ID());
                if(!$u_prod) continue;
                $u_id = $u_prod->get_id();
                $u_price = $u_prod->get_price();
                $u_img = wp_get_attachment_image_url($u_prod->get_image_id(), 'thumbnail') ?: wc_placeholder_img_src();
                $u_reg_price = $u_prod->get_regular_price();
                $u_sale_price = $u_prod->get_sale_price();
                $u_savings = ($u_reg_price && $u_sale_price && $u_reg_price > $u_sale_price) ? ($u_reg_price - $u_sale_price) : 0;
                $logic_qty   = $upsell_logic_map[$u_id]['qty'] ?? 0;
                $logic_type  = $upsell_logic_map[$u_id]['type'] ?? '';
                $logic_value = $upsell_logic_map[$u_id]['value'] ?? 0;
            ?>
            <div style="background: #ffffff; border: 1.5px solid #fecdd3; border-radius: 12px; padding: 8px; text-align: center; display: flex; flex-direction: column; justify-content: space-between; gap: 6px;">
                <div style="aspect-ratio: 1/1; background: #fff1f2; border-radius: 8px; overflow: hidden;">
                    <img src="<?php echo esc_url($u_img); ?>" alt="<?php the_title(); ?>" style="width: 100%; height: 100%; object-fit: cover; display: block;">
                </div>
                <h4 style="font-size: 11px; font-weight: 700; color: #1e293b; margin: 0; line-height: 1.3; overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;"><?php the_title(); ?></h4>
                <div style="font-size: 13px; font-weight: 900; color: #dc2626;"><?php echo esc_html($u_price); ?>৳</div>
                <button type="button" class="btn-add-upsell" style="background: #dc2626; color: #fff; border: none; padding: 6px 4px; border-radius: 6px; font-size: 11px; font-weight: 800; cursor: pointer; width: 100%;"
                    data-id="<?php echo $u_id; ?>"
                    data-name="<?php echo esc_attr(get_the_title()); ?>"
                    data-price="<?php echo esc_attr($u_price); ?>"
                    data-discount="<?php echo esc_attr($u_savings); ?>"
                    data-img="<?php echo esc_url($u_img); ?>"
                    data-free-delivery="<?php echo fmb_is_effectively_free_delivery_catalog($u_id) ? '1' : '0'; ?>"
                    data-logic-qty="<?php echo esc_attr($logic_qty); ?>"
                    data-logic-type="<?php echo esc_attr($logic_type); ?>"
                    data-logic-value="<?php echo esc_attr($logic_value); ?>">
                    + নিন
                </button>
            </div>
            <?php endwhile; wp_reset_postdata(); ?>
        </div>
    </div>
    <?php endif; endif; endif; ?>

    <!-- Free Delivery Banner (if applicable) -->
    <?php if ($fmb_free_delivery_banner) : ?>
    <div class="fmb-fomo-container" style="margin-top: 14px;">
        <div style="background: linear-gradient(90deg, #065f46 0%, #047857 100%); color: #fff; border-radius: 12px; padding: 10px 16px; display: flex; align-items: center; justify-content: center; gap: 8px; font-weight: 800; font-size: clamp(12.5px, 2.8vw, 15px);">
            <?php if ($banner_image) : ?>
                <img src="<?php echo esc_url($banner_image); ?>" alt="Free Delivery" style="height: 28px; object-fit: contain;">
            <?php else : ?>
                <span style="font-size: 18px;">🚚</span>
            <?php endif; ?>
            <span><?php echo esc_html($banner_text); ?></span>
        </div>
    </div>
    <?php endif; ?>

    <!-- Description (Accordion) -->
    <div class="fmb-fomo-container">
        <div style="background: #ffffff; border: 1.5px solid #fecdd3; border-radius: 16px; overflow: hidden;">
            <button type="button" id="fmb-desc-toggle" onclick="fmbToggleDesc()" style="width: 100%; display: flex; align-items: center; justify-content: space-between; padding: 14px 16px; background: #fff1f2; border: none; cursor: pointer; font-size: 14px; font-weight: 800; color: #991b1b;">
                <span>📋 পণ্যের বিস্তারিত তথ্য</span>
                <span id="fmb-desc-arrow" style="font-size: 18px; transition: transform 0.3s;">▼</span>
            </button>
            <div id="fmb-desc-body" style="display: none; padding: 16px; font-size: 14px; line-height: 1.65; color: #334155; border-top: 1.5px solid #fecdd3;">
                <?php the_content(); ?>
            </div>
        </div>
    </div>
    <script>
    function fmbToggleDesc() {
        var body  = document.getElementById('fmb-desc-body');
        var arrow = document.getElementById('fmb-desc-arrow');
        if (!body) return;
        if (body.style.display === 'none') {
            body.style.display = 'block';
            arrow.style.transform = 'rotate(180deg)';
        } else {
            body.style.display = 'none';
            arrow.style.transform = 'rotate(0deg)';
        }
    }
    </script>

    <!-- Scripts -->
    <script>
    (function() {
        let h = 2, m = 45, s = 30;
        setInterval(function() {
            s--;
            if (s < 0) { s = 59; m--; }
            if (m < 0) { m = 59; h--; }
            if (h < 0) { h = 2; m = 45; s = 0; }
            let hEl = document.getElementById('fomo-h-box');
            let mEl = document.getElementById('fomo-m-box');
            let sEl = document.getElementById('fomo-s-box');
            if (hEl) hEl.textContent = String(h).padStart(2, '0');
            if (mEl) mEl.textContent = String(m).padStart(2, '0');
            if (sEl) sEl.textContent = String(s).padStart(2, '0');
        }, 1000);
    })();

    function changeTopQty(change) {
        var topInput = document.getElementById('top-qty-input');
        var realQtyInput = document.querySelector('form.cart input.qty, .woocommerce-checkout input.qty');
        
        var currentVal = parseInt(topInput.value) || 1;
        var newVal = currentVal + change;
        if (newVal < 1) newVal = 1;
        
        topInput.value = newVal;
        
        if (realQtyInput) {
            realQtyInput.value = newVal;
            var event = new Event('change', { bubbles: true });
            realQtyInput.dispatchEvent(event);
            if (typeof jQuery !== 'undefined') {
                jQuery("[name='update_cart']").prop("disabled", false).trigger("click");
                jQuery('body').trigger('update_checkout');
            }
        }
    }

    document.addEventListener("DOMContentLoaded", function() {
        var realQtyInput = document.querySelector('form.cart input.qty, .woocommerce-checkout input.qty');
        if (realQtyInput) {
            document.getElementById('top-qty-input').value = realQtyInput.value;
            realQtyInput.addEventListener('change', function() {
                document.getElementById('top-qty-input').value = this.value;
            });
        }
    });
    </script>
</div>
