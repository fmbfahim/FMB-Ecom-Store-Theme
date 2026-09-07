<?php
/**
 * Single Product Variant 2: Modern Dark Luxe & Cyber Glassmorphism (Cinematic Edition)
 */
if (!defined('ABSPATH')) exit;

$fmb_free_delivery_banner = fmb_is_effectively_free_delivery_catalog( $main_id );
$banner_image = get_theme_mod('fmb_free_delivery_image', '');
$banner_text  = get_theme_mod('fmb_free_delivery_text', '🚚 সারাদেশে ডেলিভারি সম্পূর্ণ বিনামূল্যে!');

$regular_price = $product->get_regular_price();
$sale_price = $product->get_sale_price();
$savings = ($regular_price && $sale_price && $regular_price > $sale_price) ? ($regular_price - $sale_price) : 0;
$gallery_ids = $product->get_gallery_image_ids();
?>

<div class="fmb-v2-dark-luxe" style="background: #060913; color: #f8fafc; font-family: inherit; padding-bottom: 40px;">
    
    <!-- Scoped Dark Theme Override for Header and Full Page -->
    <style>
        #fmb-navbar, #fmb-main-header {
            background: #090e1d !important;
            border-bottom: 1px solid rgba(168, 85, 247, 0.25) !important;
        }
        #fmb-navbar a, #fmb-navbar span, #fmb-navbar svg, #fmb-navbar button {
            color: #ffffff !important;
        }
        .fmb-v2-dark-luxe * {
            box-sizing: border-box;
        }
        .fmb-neon-glow {
            box-shadow: 0 0 35px rgba(168, 85, 247, 0.35), inset 0 0 20px rgba(168, 85, 247, 0.15);
        }
        .fmb-gold-btn {
            background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 50%, #d97706 100%) !important;
            color: #000000 !important;
            font-weight: 900 !important;
            box-shadow: 0 0 30px rgba(245, 158, 11, 0.55) !important;
            transition: all 0.3s ease !important;
        }
        .fmb-gold-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 0 40px rgba(245, 158, 11, 0.8) !important;
        }
        .fmb-dark-glass-card {
            background: rgba(13, 19, 34, 0.85);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1.5px solid rgba(168, 85, 247, 0.25);
            border-radius: 24px;
            transition: all 0.3s ease;
        }
        .fmb-dark-glass-card:hover {
            border-color: rgba(245, 158, 11, 0.6);
            box-shadow: 0 10px 30px rgba(0,0,0,0.8), 0 0 20px rgba(245,158,11,0.2);
            transform: translateY(-3px);
        }
    </style>

    <!-- Top Free Delivery Banner -->
    <?php if ( $fmb_free_delivery_banner ) : ?>
    <div style="max-width: 1200px; margin: 0 auto; padding: 20px 16px 0;">
        <?php if (!empty($banner_image)) : ?>
            <img src="<?php echo esc_url($banner_image); ?>" alt="Free Delivery" style="width: 100%; height: auto; border-radius: 16px; border: 1.5px solid #a855f7; box-shadow: 0 0 25px rgba(168,85,247,0.3);">
        <?php else : ?>
            <div style="background: linear-gradient(90deg, rgba(16, 185, 129, 0.15), rgba(147, 51, 234, 0.2)); color: #34d399; border: 1.5px solid #10b981; padding: 14px 24px; border-radius: 16px; font-weight: 800; font-size: 14px; text-align: center; box-shadow: 0 0 20px rgba(16,185,129,0.25); display: flex; align-items: center; justify-content: center; gap: 10px;">
                <span style="font-size: 18px;">🚚</span>
                <span><?php echo esc_html($banner_text); ?></span>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- ==================================================== -->
    <!-- 👑 CINEMATIC HERO SECTION (Floating 3D Showcase & HUD) -->
    <!-- ==================================================== -->
    <div style="max-width: 1240px; margin: 24px auto 0; padding: 0 16px;">
        <div style="background: radial-gradient(circle at 50% 20%, #171130 0%, #090e1d 60%, #060913 100%); border: 1.5px solid rgba(168, 85, 247, 0.35); border-radius: 32px; padding: 36px; box-shadow: 0 25px 60px rgba(0,0,0,0.8), 0 0 40px rgba(147, 51, 234, 0.2); display: grid; grid-template-columns: repeat(auto-fit, minmax(340px, 1fr)); gap: 40px; align-items: center; position: relative; overflow: hidden;">
            
            <!-- Ambient Background Neon Halo -->
            <div style="position: absolute; top: -100px; left: -100px; width: 350px; height: 350px; background: radial-gradient(circle, rgba(168, 85, 247, 0.3) 0%, transparent 70%); filter: blur(50px); pointer-events: none;"></div>
            <div style="position: absolute; bottom: -100px; right: -100px; width: 350px; height: 350px; background: radial-gradient(circle, rgba(245, 158, 11, 0.2) 0%, transparent 70%); filter: blur(50px); pointer-events: none;"></div>

            <!-- Left: Floating 3D Showcase with Thumbnails -->
            <div style="display: flex; flex-direction: column; gap: 18px; position: relative; z-index: 10;">
                <div style="background: #0d1322; border: 2px solid rgba(168, 85, 247, 0.45); border-radius: 28px; padding: 12px; display: flex; align-items: center; justify-content: center; position: relative; box-shadow: 0 20px 40px rgba(0,0,0,0.7), inset 0 0 30px rgba(168,85,247,0.15); aspect-ratio: 1/1; overflow: hidden;">
                    
                    <div style="position: absolute; top: 16px; left: 16px; background: rgba(0,0,0,0.7); backdrop-filter: blur(10px); border: 1px solid rgba(251,191,36,0.6); color: #fbbf24; font-size: 11px; font-weight: 900; padding: 5px 14px; border-radius: 30px; box-shadow: 0 0 15px rgba(251,191,36,0.35); display: flex; align-items: center; gap: 6px; z-index: 5;">
                        <span>👑</span> LUXE EDITION
                    </div>

                    <?php 
                    $main_img_url = wp_get_attachment_image_url($product->get_image_id(), 'large') ?: wc_placeholder_img_src();
                    ?>
                    <img id="fmb-main-product-image" src="<?php echo esc_url($main_img_url); ?>" alt="<?php the_title(); ?>" style="width: 100%; height: 100%; object-fit: cover; border-radius: 20px; filter: drop-shadow(0 20px 30px rgba(0,0,0,0.9));">
                </div>

                <!-- Gallery Thumbnails -->
                <?php if($gallery_ids): 
                    $preview_ids = array_slice($gallery_ids, 0, 4);
                ?>
                <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px;">
                    <?php foreach($preview_ids as $g_id): 
                        $g_img_thumb = wp_get_attachment_image_url($g_id, 'thumbnail');
                        $g_img_large = wp_get_attachment_image_url($g_id, 'large');
                    ?>
                        <div onclick="document.getElementById('fmb-main-product-image').src='<?php echo esc_url($g_img_large); ?>'" style="background: #0d1322; border: 1.5px solid rgba(168,85,247,0.3); border-radius: 16px; overflow: hidden; aspect-ratio: 1/1; padding: 4px; box-shadow: 0 8px 20px rgba(0,0,0,0.5); cursor: pointer; transition: transform 0.2s ease;" onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform='scale(1)'">
                            <img src="<?php echo esc_url($g_img_thumb); ?>" alt="<?php the_title(); ?>" style="width: 100%; height: 100%; object-fit: cover; border-radius: 12px;">
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Right: Cyber Luxe HUD Control Center -->
            <div style="display: flex; flex-direction: column; gap: 20px; position: relative; z-index: 10;">
                
                <!-- Badges Row -->
                <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                    <span style="background: linear-gradient(135deg, #f59e0b 0%, #ea580c 100%); color: #000; font-size: 11px; font-weight: 900; padding: 5px 16px; border-radius: 30px; letter-spacing: 0.5px; box-shadow: 0 0 15px rgba(245,158,11,0.5);">
                        ★ ১০০% প্রিমিয়াম কোয়ালিটি
                    </span>
                    <span style="background: rgba(147, 51, 234, 0.25); color: #c084fc; border: 1.5px solid rgba(192, 132, 252, 0.45); font-size: 11px; font-weight: 700; padding: 4px 14px; border-radius: 30px;">
                        🛡️ অরিজিনাল গ্যারান্টি
                    </span>
                </div>

                <!-- Glowing Title -->
                <h1 style="font-size: 28px; font-weight: 900; color: #ffffff; margin: 0; line-height: 1.35; text-shadow: 0 2px 15px rgba(0,0,0,0.9);">
                    <?php the_title(); ?>
                </h1>

                <!-- Price Module -->
                <div style="background: rgba(8, 12, 22, 0.85); border: 2px solid rgba(245, 158, 11, 0.5); border-radius: 22px; padding: 20px 24px; box-shadow: 0 10px 30px rgba(0,0,0,0.6), inset 0 0 20px rgba(245,158,11,0.1); display: flex; align-items: baseline; justify-content: space-between; flex-wrap: wrap; gap: 14px;">
                    <div>
                        <div style="font-size: 11px; color: #94a3b8; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 4px;">SPECIAL VIP PRICE</div>
                        <div style="font-size: 38px; font-weight: 900; color: #fbbf24; text-shadow: 0 0 25px rgba(251,191,36,0.6); line-height: 1;">
                            <?php echo wc_price($product->get_price()); ?>
                        </div>
                    </div>
                    <?php if ($savings > 0) : ?>
                    <div style="text-align: right;">
                        <div style="font-size: 18px; color: #64748b; text-decoration: line-through; font-weight: 600;">
                            <?php echo wc_price($regular_price); ?>
                        </div>
                        <div style="background: rgba(16, 185, 129, 0.2); color: #34d399; border: 1px solid #10b981; padding: 4px 14px; border-radius: 20px; font-size: 12px; font-weight: 900; margin-top: 4px;">
                            সেভ ৳<?php echo esc_html($savings); ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Sales Proof -->
                <?php if (get_theme_mod('fmb_show_fake_sales', true)) : ?>
                    <?php fmb_render_sales_proof_badge( $main_id, '', 'margin-bottom: 12px;', true ); ?>
                <?php endif; ?>

                <!-- Short Description -->
                <div style="font-size: 14.5px; color: #cbd5e1; line-height: 1.75;">
                    <?php echo apply_filters('woocommerce_short_description', $product->get_short_description()); ?>
                </div>

                <!-- Quantity Stepper -->
                <div style="display: flex; align-items: center; justify-content: space-between; background: rgba(13, 19, 34, 0.7); border: 1.5px solid rgba(255,255,255,0.15); border-radius: 16px; padding: 12px 18px;">
                    <strong style="font-size: 14px; color: #e2e8f0;">প্রোডাক্ট পরিমাণ:</strong>
                    <div style="display: inline-flex; border: 1.5px solid rgba(255,255,255,0.25); border-radius: 12px; background: #080c16; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.5);">
                        <button type="button" onclick="changeTopQty(-1)" style="padding: 8px 18px; font-size: 18px; font-weight: 800; background: #1e293b; color: #fff; border: none; cursor: pointer;">-</button>
                        <input type="text" id="top-qty-input" value="1" readonly style="width: 50px; text-align: center; font-weight: 800; font-size: 16px; border: none; outline: none; background: transparent; color: #fff;">
                        <button type="button" onclick="changeTopQty(1)" style="padding: 8px 18px; font-size: 18px; font-weight: 800; background: #1e293b; color: #fff; border: none; cursor: pointer;">+</button>
                    </div>
                </div>

                <!-- Action CTA Buttons -->
                <div style="display: flex; flex-direction: column; gap: 12px; margin-top: 4px;">
                    <a href="#checkout-area" onclick="event.preventDefault(); fmbGoToCheckout();" class="fmb-gold-btn fmb-order-now-btn" style="text-decoration: none; padding: 18px 28px; border-radius: 18px; font-size: 18px; text-align: center; display: flex; align-items: center; justify-content: center; gap: 10px;">
                        ⚡ সরাসরি এখনই অর্ডার করুন
                    </a>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                        <?php if (get_theme_mod('fmb_show_add_to_cart_button', true)) : ?>
                        <button type="button" id="fmb-add-to-cart-btn" class="fmb-single-atc-btn" style="background: rgba(30, 41, 59, 0.9); color: #ffffff; border: 1.5px solid rgba(255,255,255,0.18); padding: 14px; border-radius: 14px; font-size: 14px; font-weight: 700; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; transition: all 0.2s;">
                            🛒 অ্যাড টু কার্ট
                        </button>
                        <?php endif; ?>

                        <?php
                        $store_phone = get_theme_mod('fmb_global_phone', '');
                        $wa_number = preg_replace('/[^0-9]/', '', $store_phone);
                        if (strpos($wa_number, '88') !== 0 && !empty($wa_number)) $wa_number = '88' . $wa_number;
                        $msg_url = get_theme_mod('fmb_messenger_url') ?: ('https://wa.me/' . $wa_number);
                        ?>

                        <?php if (get_theme_mod('fmb_show_whatsapp_button', true)) : ?>
                        <a href="<?php echo esc_url($msg_url); ?>" target="_blank" style="text-decoration: none; text-align: center; background: rgba(16, 185, 129, 0.2); color: #a7f3d0; border: 1.5px solid rgba(52, 211, 153, 0.4); padding: 14px; border-radius: 14px; font-weight: 700; font-size: 14px; display: flex; align-items: center; justify-content: center; gap: 6px;">
                            💬 হোয়াটসঅ্যাপ
                        </a>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- ==================================================== -->
    <!-- 📋 SECTION 1: USAGE STEPS (Glowing Cyber Timeline) -->
    <!-- ==================================================== -->
    <?php 
    $steps_title = get_post_meta($main_id, '_fmb_steps_title', true) ?: 'ব্যবহারের সহজ নিয়মাবলী';
    if(!empty($steps) && is_array($steps)): 
    ?>
    <div style="max-width: 900px; margin: 60px auto 0; padding: 0 16px;">
        <div style="text-align: center; margin-bottom: 30px;">
            <span style="color: #fbbf24; font-size: 11px; font-weight: 900; letter-spacing: 2px; text-transform: uppercase;">STEP BY STEP GUIDE</span>
            <h3 style="font-size: 26px; font-weight: 900; color: #ffffff; margin: 6px 0 0; text-shadow: 0 2px 12px rgba(0,0,0,0.9);"><?php echo esc_html($steps_title); ?></h3>
        </div>
        <div style="display: flex; flex-direction: column; gap: 16px;">
            <?php foreach($steps as $index => $step): if(empty($step)) continue; ?>
                <div class="fmb-dark-glass-card" style="padding: 22px 28px; display: flex; align-items: center; gap: 20px;">
                    <div style="background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%); color: #000; font-weight: 900; font-size: 16px; width: 40px; height: 40px; border-radius: 14px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; box-shadow: 0 0 15px rgba(251,191,36,0.4);">
                        <?php echo $index + 1; ?>
                    </div>
                    <div style="font-size: 16px; color: #e2e8f0; font-weight: 500; line-height: 1.65;">
                        <?php echo esc_html($step); ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- ==================================================== -->
    <!-- 🌟 SECTION 2: BENEFITS (3D Frosted Glass Grid) -->
    <!-- ==================================================== -->
    <?php 
    $cards_title = get_post_meta($main_id, '_fmb_cards_title', true) ?: 'কেন এটি সেরা?';
    if(!empty($cards) && is_array($cards)): 
    ?>
    <div style="max-width: 1240px; margin: 70px auto 0; padding: 0 16px;">
        <div style="text-align: center; margin-bottom: 36px;">
            <span style="color: #c084fc; font-size: 11px; font-weight: 900; letter-spacing: 2px; text-transform: uppercase;">EXCLUSIVE ADVANTAGES</span>
            <h3 style="font-size: 28px; font-weight: 900; color: #ffffff; margin: 6px 0 0; text-shadow: 0 2px 15px rgba(0,0,0,0.9);"><?php echo esc_html($cards_title); ?></h3>
        </div>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 24px;">
            <?php foreach($cards as $card): 
                if(empty($card['title']) && empty($card['desc'])) continue;
            ?>
                <div class="fmb-dark-glass-card" style="padding: 30px; text-align: center;">
                    <?php if(!empty($card['img'])): ?>
                        <img src="<?php echo esc_url($card['img']); ?>" alt="<?php echo esc_attr($card['title']); ?>" style="width: 80px; height: 80px; object-fit: contain; margin: 0 auto 18px; border-radius: 16px;">
                    <?php else: ?>
                        <div style="width: 60px; height: 60px; background: rgba(147, 51, 234, 0.25); color: #fbbf24; font-size: 28px; border-radius: 18px; display: flex; align-items: center; justify-content: center; margin: 0 auto 18px; border: 1.5px solid rgba(192, 132, 252, 0.5); box-shadow: 0 0 20px rgba(168,85,247,0.3);">
                            ✨
                        </div>
                    <?php endif; ?>
                    <h4 style="font-size: 18px; font-weight: 800; color: #ffffff; margin: 0 0 12px 0;"><?php echo esc_html($card['title']); ?></h4>
                    <p style="font-size: 14px; color: #94a3b8; line-height: 1.7; margin: 0;"><?php echo esc_html($card['desc']); ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- ==================================================== -->
    <!-- 🛍️ UPSELLS (Dark Glass Carousel Grid) -->
    <!-- ==================================================== -->
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
    <div style="max-width: 1240px; margin: 70px auto 0; padding: 0 16px;">
        <h3 style="font-size: 26px; font-weight: 900; text-align: center; color: #ffffff; margin-bottom: 28px; text-shadow: 0 2px 12px rgba(0,0,0,0.9);"><?php echo esc_html($upsell_title); ?></h3>
        <style>
        .fmb-upsell-grid-d { display: grid; grid-template-columns: repeat(5, 1fr); gap: 10px; }
        @media (max-width: 767px) { .fmb-upsell-grid-d { grid-template-columns: repeat(3, 1fr); gap: 8px; } }
        @media (max-width: 480px) { .fmb-upsell-grid-d { grid-template-columns: repeat(2, 1fr); gap: 7px; } }
        </style>
        <div class="fmb-upsell-grid-d">
            <?php while ($upsells_query->have_posts()) : $upsells_query->the_post(); 
                $u_prod = wc_get_product(get_the_ID());
                if(!$u_prod) continue;
                $u_id = $u_prod->get_id();
                $u_price = $u_prod->get_price();
                $u_img = wp_get_attachment_image_url($u_prod->get_image_id(), 'thumbnail') ?: wc_placeholder_img_src();
                $u_reg_price = $u_prod->get_regular_price();
                $u_sale_price = $u_prod->get_sale_price();
                $u_savings = ($u_reg_price && $u_sale_price && $u_reg_price > $u_sale_price) ? ($u_reg_price - $u_sale_price) : 0;
            ?>
            <div class="fmb-dark-glass-card" style="padding: 8px; text-align: center; display: flex; flex-direction: column; justify-content: space-between; gap: 6px;">
                <div style="aspect-ratio: 1/1; background: #000; border-radius: 8px; overflow: hidden; border: 1px solid rgba(255,255,255,0.1);">
                    <img src="<?php echo esc_url($u_img); ?>" alt="<?php the_title(); ?>" style="width: 100%; height: 100%; object-fit: cover; display: block;">
                </div>
                <h4 style="font-size: 11px; font-weight: 700; color: #e2e8f0; margin: 0; line-height: 1.3; overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;"><?php the_title(); ?></h4>
                <div style="font-size: 13px; font-weight: 900; color: #fbbf24;"><?php echo esc_html($u_price); ?>৳</div>
                <?php 
                    $logic_qty = $upsell_logic_map[$u_id]['qty'] ?? 0;
                    $logic_type = $upsell_logic_map[$u_id]['type'] ?? '';
                    $logic_value = $upsell_logic_map[$u_id]['value'] ?? 0;
                ?>
                <button type="button" class="btn-add-upsell" style="background: linear-gradient(135deg, #7c3aed 0%, #6366f1 100%); color: #fff; border: none; padding: 6px 4px; border-radius: 6px; font-size: 11px; font-weight: 800; cursor: pointer; width: 100%;"
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

    <!-- ==================================================== -->
    <!-- 📄 FULL DESCRIPTION (Dark Glass Panel) -->
    <!-- ==================================================== -->
    <style>
        .fmb-dark-description-area * {
            color: #cbd5e1 !important;
        }
        /* Override specifically AI generated white backgrounds */
        .fmb-dark-description-area div[style*="background: #ffffff"],
        .fmb-dark-description-area div[style*="background:#ffffff"],
        .fmb-dark-description-area div[style*="background: #f8fafc"],
        .fmb-dark-description-area div[style*="background: #f0fdf4"],
        .fmb-dark-description-area div[style*="background: #fef2f2"],
        .fmb-dark-description-area table, 
        .fmb-dark-description-area details {
            background: rgba(13, 19, 34, 0.6) !important;
            border-color: rgba(255,255,255,0.1) !important;
        }
        
        .fmb-dark-description-area table tr, 
        .fmb-dark-description-area table th, 
        .fmb-dark-description-area table td {
            background: transparent !important;
            border-color: rgba(255,255,255,0.1) !important;
            color: #e2e8f0 !important;
        }
        
        /* Table Headers */
        .fmb-dark-description-area div[style*="linear-gradient"] {
            background: linear-gradient(135deg, #1e1136 0%, #0f172a 100%) !important;
            color: #fbbf24 !important;
            border-bottom: 1px solid rgba(168, 85, 247, 0.3) !important;
        }

        /* FAQ summary & p */
        .fmb-dark-description-area summary {
            color: #f8fafc !important;
        }
        .fmb-dark-description-area p {
            color: #94a3b8 !important;
        }
        .fmb-dark-description-area h3, 
        .fmb-dark-description-area h4, 
        .fmb-dark-description-area strong {
            color: #fbbf24 !important;
        }

        /* Beautiful Responsive Table (Wrap Text, No Cutoff) */
        .fmb-dark-description-area table {
            width: 100% !important;
            table-layout: fixed !important;
        }
        .fmb-dark-description-area th, 
        .fmb-dark-description-area td {
            white-space: normal !important;
            word-wrap: break-word !important;
            overflow-wrap: break-word !important;
        }
    </style>
    <div style="max-width: 1240px; margin: 60px auto 0; padding: 0 16px;">
        <div style="border-radius: 18px; overflow: hidden; border: 1px solid rgba(168, 85, 247, 0.25);">
            <button type="button" onclick="fmbToggleDescDark()" style="width: 100%; display: flex; align-items: center; justify-content: space-between; padding: 16px 24px; background: rgba(30, 17, 54, 0.95); border: none; cursor: pointer; font-size: 15px; font-weight: 800; color: #fbbf24;">
                <span>📋 পণ্যের বিস্তারিত তথ্য</span>
                <span id="fmb-desc-arrow-d" style="font-size: 20px; transition: transform 0.3s; color: #a78bfa;">▼</span>
            </button>
            <div id="fmb-desc-body-d" style="display: none; border-top: 1px solid rgba(168,85,247,0.25);">
                <div class="fmb-dark-glass-card fmb-dark-description-area" style="border-radius: 0; border: none; padding: 28px; font-size: 15.5px; line-height: 1.85;">
                    <?php the_content(); ?>
                </div>
            </div>
        </div>
    </div>
    <script>
    function fmbToggleDescDark() {
        var body  = document.getElementById('fmb-desc-body-d');
        var arrow = document.getElementById('fmb-desc-arrow-d');
        if (!body) return;
        body.style.display = (body.style.display === 'none') ? 'block' : 'none';
        arrow.style.transform = (body.style.display === 'block') ? 'rotate(180deg)' : 'rotate(0deg)';
    }
    </script>
</div>

<script>
function changeTopQty(change) {
    var topInput = document.getElementById('top-qty-input');
    var realQtyInput = document.querySelector('form.cart input.qty, .woocommerce-checkout input.qty');
    
    var currentVal = parseInt(topInput.value) || 1;
    var newVal = currentVal + change;
    if (newVal < 1) newVal = 1;
    
    topInput.value = newVal;
    
    // Sync with WooCommerce form
    if (realQtyInput) {
        realQtyInput.value = newVal;
        // Trigger change event for WooCommerce to update order review
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
