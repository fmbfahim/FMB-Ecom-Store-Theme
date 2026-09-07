<?php
/**
 * Single Product Variant 1: Classic Conversion (High-Trust Bangladeshi eCommerce)
 */
if (!defined('ABSPATH')) exit;

$fmb_free_delivery_banner = fmb_is_effectively_free_delivery_catalog( $main_id );
$banner_image = get_theme_mod('fmb_free_delivery_image', '');
$banner_text  = get_theme_mod('fmb_free_delivery_text', '🚚 সারাদেশে ডেলিভারি সম্পূর্ণ বিনামূল্যে!');

$regular_price = $product->get_regular_price();
$sale_price = $product->get_sale_price();
$savings = ($regular_price && $sale_price && $regular_price > $sale_price) ? ($regular_price - $sale_price) : 0;
?>

<div class="fmb-v1-classic" style="background:#f8fafc; font-family: inherit; color:#1e293b; padding-bottom: 20px;">
    
    <!-- Top Delivery Banner -->
    <?php if ( $fmb_free_delivery_banner ) : ?>
    <div style="max-width: 1200px; margin: 0 auto; padding: 16px 16px 0;">
        <?php if (!empty($banner_image)) : ?>
            <img src="<?php echo esc_url($banner_image); ?>" alt="Free Delivery" style="width: 100%; height: auto; border-radius: 12px; display: block;">
        <?php else : ?>
            <div style="background: #dcfce7; color: #166534; border: 1.5px solid #86efac; padding: 12px 20px; border-radius: 12px; font-weight: 700; font-size: 14px; text-align: center; display: flex; align-items: center; justify-content: center; gap: 8px;">
                <span>🚚</span>
                <span><?php echo esc_html($banner_text); ?></span>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Main Hero Card -->
    <div style="max-width: 1200px; margin: 20px auto; padding: 0 16px;">
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 20px; padding: 24px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 32px; align-items: center;">
            
            <!-- Left: Main Image -->
            <div style="background: #f1f5f9; border-radius: 16px; overflow: hidden; padding: 16px; display: flex; align-items: center; justify-content: center; border: 1px solid #cbd5e1; aspect-ratio: 1/1;">
                <?php 
                $main_img_url = wp_get_attachment_image_url($product->get_image_id(), 'large') ?: wc_placeholder_img_src();
                ?>
                <img id="fmb-main-product-image-classic" src="<?php echo esc_url($main_img_url); ?>" alt="<?php the_title(); ?>" style="width: 100%; height: 100%; object-fit: cover; border-radius: 10px;">
            </div>

            <!-- Right: Product Info -->
            <div>
                <div style="display: inline-block; background: #e0e7ff; color: #3730a3; font-size: 12px; font-weight: 700; padding: 4px 12px; border-radius: 20px; margin-bottom: 12px;">
                    ⭐ অরিজিনাল ও প্রিমিয়াম প্রোডাক্ট
                </div>

                <h1 style="font-size: 26px; font-weight: 800; color: #0f172a; margin: 0 0 14px 0; line-height: 1.35;">
                    <?php the_title(); ?>
                </h1>

                <!-- Price Box -->
                <div style="background: #f8fafc; border: 1.5px solid #e2e8f0; border-radius: 14px; padding: 16px 20px; margin-bottom: 20px; display: flex; align-items: baseline; gap: 14px; flex-wrap: wrap;">
                    <span style="font-size: 32px; font-weight: 900; color: #2563eb;">
                        <?php echo wc_price($product->get_price()); ?>
                    </span>
                    <?php if ($savings > 0) : ?>
                        <span style="font-size: 18px; color: #94a3b8; text-decoration: line-through; font-weight: 600;">
                            <?php echo wc_price($regular_price); ?>
                        </span>
                        <span style="background: #dcfce7; color: #15803d; border: 1px solid #86efac; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 800;">
                            সেভ করুন <?php echo wc_price($savings); ?>
                        </span>
                    <?php endif; ?>
                </div>

                <!-- Sales Proof -->
                <?php if (get_theme_mod('fmb_show_fake_sales', true)) : ?>
                    <?php fmb_render_sales_proof_badge( $main_id, '', 'margin-bottom: 18px;' ); ?>
                <?php endif; ?>

                <div style="font-size: 14px; color: #475569; line-height: 1.6; margin-bottom: 20px;">
                    <?php echo apply_filters('woocommerce_short_description', $product->get_short_description()); ?>
                </div>

                <!-- Quantity -->
                <div style="display: flex; align-items: center; gap: 14px; margin-bottom: 18px;">
                    <strong style="font-size: 14px; color: #334155;">পরিমাণ:</strong>
                    <div style="display: inline-flex; border: 1.5px solid #cbd5e1; border-radius: 10px; background: #ffffff; overflow: hidden;">
                        <button type="button" onclick="changeTopQty(-1)" style="padding: 6px 16px; font-size: 18px; font-weight: 800; background: #f1f5f9; border: none; cursor: pointer;">-</button>
                        <input type="text" id="top-qty-input" value="1" readonly style="width: 50px; text-align: center; font-weight: 800; font-size: 16px; border: none; outline: none; background: #fff;">
                        <button type="button" onclick="changeTopQty(1)" style="padding: 6px 16px; font-size: 18px; font-weight: 800; background: #f1f5f9; border: none; cursor: pointer;">+</button>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 10px; margin-bottom: 12px;">
                    <?php if (get_theme_mod('fmb_show_add_to_cart_button', true)) : ?>
                    <button type="button" id="fmb-add-to-cart-btn" class="fmb-single-atc-btn" style="background: #1e293b; color: #ffffff; border: none; padding: 14px 20px; border-radius: 12px; font-size: 16px; font-weight: 700; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; transition: background 0.2s;">
                        🛒 অ্যাড টু কার্ট
                    </button>
                    <?php endif; ?>
                    
                    <a href="#checkout-area" onclick="event.preventDefault(); fmbGoToCheckout();" class="fmb-order-now-btn" style="background: var(--btn-bg, var(--primary)) !important; color: var(--btn-text, #ffffff) !important; text-decoration: none; padding: 14px 20px; border-radius: 12px; font-size: 16px; font-weight: 800; text-align: center; display: flex; align-items: center; justify-content: center; gap: 8px; box-shadow: 0 4px 14px rgba(0,0,0,0.15);">
                        ⚡ সরাসরি অর্ডার করুন
                    </a>
                </div>

                <?php
                $store_phone = get_theme_mod('fmb_global_phone', '');
                $wa_number = preg_replace('/[^0-9]/', '', $store_phone);
                if (strpos($wa_number, '88') !== 0 && !empty($wa_number)) $wa_number = '88' . $wa_number;
                $msg_url = get_theme_mod('fmb_messenger_url') ?: ('https://wa.me/' . $wa_number);
                ?>

                <div style="display: flex; gap: 10px; font-size: 13px;">
                    <?php if (get_theme_mod('fmb_show_call_button', true)) : ?>
                    <a href="tel:<?php echo esc_attr($store_phone); ?>" style="flex: 1; text-decoration: none; text-align: center; background: #faf5ff; color: #7e22ce; border: 1px solid #e9d5ff; padding: 10px; border-radius: 10px; font-weight: 700;">
                        📞 কল করুন
                    </a>
                    <?php endif; ?>
                    <?php if (get_theme_mod('fmb_show_whatsapp_button', true)) : ?>
                    <a href="<?php echo esc_url($msg_url); ?>" target="_blank" style="flex: 1; text-decoration: none; text-align: center; background: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0; padding: 10px; border-radius: 10px; font-weight: 700;">
                        💬 হোয়াটসঅ্যাপ
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Gallery Thumbnails -->
    <?php 
    $gallery_ids = $product->get_gallery_image_ids();
    if($gallery_ids):
        $gallery_ids = array_slice($gallery_ids, 0, 4);
    ?>
    <div style="max-width: 1200px; margin: 0 auto 20px; padding: 0 16px;">
        <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px;">
            <?php foreach($gallery_ids as $g_id): 
                $g_img = wp_get_attachment_image_url($g_id, 'large');
            ?>
                <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; overflow: hidden; aspect-ratio: 1/1; padding: 8px;">
                    <img src="<?php echo esc_url($g_img); ?>" alt="<?php the_title(); ?>" style="width: 100%; height: 100%; object-fit: cover; border-radius: 8px;">
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Section 1: Steps -->
    <?php 
    $steps_title = get_post_meta($main_id, '_fmb_steps_title', true) ?: 'ব্যবহারের সহজ নিয়মাবলী';
    if(!empty($steps) && is_array($steps)): 
    ?>
    <div style="max-width: 1200px; margin: 30px auto; padding: 0 16px;">
        <h3 style="font-size: 22px; font-weight: 800; text-align: center; color: #0f172a; margin-bottom: 20px;">
            📋 <?php echo esc_html($steps_title); ?>
        </h3>
        <div style="display: flex; flex-direction: column; gap: 12px;">
            <?php foreach($steps as $index => $step): if(empty($step)) continue; ?>
                <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 16px 20px; display: flex; align-items: center; gap: 14px; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
                    <div style="background: #2563eb; color: #ffffff; font-weight: 800; width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                        <?php echo $index + 1; ?>
                    </div>
                    <div style="font-size: 15px; color: #334155; font-weight: 500; line-height: 1.5;">
                        <?php echo esc_html($step); ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Section 2: Info Cards -->
    <?php 
    $cards_title = get_post_meta($main_id, '_fmb_cards_title', true) ?: 'কেন এটি সেরা?';
    if(!empty($cards) && is_array($cards)): 
    ?>
    <div style="max-width: 1200px; margin: 40px auto; padding: 0 16px;">
        <h3 style="font-size: 24px; font-weight: 800; text-align: center; color: #0f172a; margin-bottom: 24px;">
            🌟 <?php echo esc_html($cards_title); ?>
        </h3>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px;">
            <?php foreach($cards as $card): 
                if(empty($card['title']) && empty($card['desc'])) continue;
            ?>
                <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 24px; text-align: center; box-shadow: 0 2px 5px rgba(0,0,0,0.03);">
                    <?php if(!empty($card['img'])): ?>
                        <img src="<?php echo esc_url($card['img']); ?>" alt="<?php echo esc_attr($card['title']); ?>" style="width: 70px; height: 70px; object-fit: contain; margin: 0 auto 12px; border-radius: 10px;">
                    <?php else: ?>
                        <div style="width: 50px; height: 50px; background: #eff6ff; color: #2563eb; font-size: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 12px;">
                            ⭐
                        </div>
                    <?php endif; ?>
                    <h4 style="font-size: 16px; font-weight: 800; color: #0f172a; margin: 0 0 8px 0;"><?php echo esc_html($card['title']); ?></h4>
                    <p style="font-size: 13px; color: #64748b; line-height: 1.5; margin: 0;"><?php echo esc_html($card['desc']); ?></p>
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
    <div style="max-width: 1200px; margin: 40px auto; padding: 0 16px;">
        <h3 style="font-size: 22px; font-weight: 800; text-align: center; color: #0f172a; margin-bottom: 20px;"><?php echo esc_html($upsell_title); ?></h3>
        <style>
        .fmb-upsell-grid-c { display: grid; grid-template-columns: repeat(5, 1fr); gap: 10px; }
        @media (max-width: 767px) { .fmb-upsell-grid-c { grid-template-columns: repeat(3, 1fr); gap: 8px; } }
        @media (max-width: 480px) { .fmb-upsell-grid-c { grid-template-columns: repeat(2, 1fr); gap: 7px; } }
        </style>
        <div class="fmb-upsell-grid-c">
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
            <div style="background: #ffffff; border: 1.5px solid #e2e8f0; border-radius: 12px; padding: 8px; text-align: center; display: flex; flex-direction: column; justify-content: space-between; gap: 6px;">
                <div style="aspect-ratio: 1/1; background: #f8fafc; border-radius: 8px; overflow: hidden;">
                    <img src="<?php echo esc_url($u_img); ?>" alt="<?php the_title(); ?>" style="width: 100%; height: 100%; object-fit: cover; display: block;">
                </div>
                <h4 style="font-size: 11px; font-weight: 700; color: #1e293b; margin: 0; line-height: 1.3; overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;"><?php the_title(); ?></h4>
                <div style="font-size: 13px; font-weight: 800; color: #2563eb;"><?php echo esc_html($u_price); ?>৳</div>
                <?php 
                    $logic_qty = $upsell_logic_map[$u_id]['qty'] ?? 0;
                    $logic_type = $upsell_logic_map[$u_id]['type'] ?? '';
                    $logic_value = $upsell_logic_map[$u_id]['value'] ?? 0;
                ?>
                <button type="button" class="btn-add-upsell" style="background: #1e293b; color: #fff; border: none; padding: 6px 4px; border-radius: 6px; font-size: 11px; font-weight: 700; cursor: pointer; width: 100%;"
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

    <!-- Full Description (Accordion) -->
    <div style="max-width: 1200px; margin: 32px auto; padding: 0 16px;">
        <div style="background: #ffffff; border: 1.5px solid #e2e8f0; border-radius: 18px; overflow: hidden;">
            <button type="button" onclick="fmbToggleDescClassic()" style="width: 100%; display: flex; align-items: center; justify-content: space-between; padding: 15px 22px; background: #f8fafc; border: none; cursor: pointer; font-size: 15px; font-weight: 800; color: #1e293b;">
                <span>📋 পণ্যের বিস্তারিত তথ্য</span>
                <span id="fmb-desc-arrow-c" style="font-size: 20px; transition: transform 0.3s;">▼</span>
            </button>
            <div id="fmb-desc-body-c" style="display: none; padding: 22px; font-size: 15px; line-height: 1.7; color: #334155; border-top: 1.5px solid #e2e8f0;">
                <?php the_content(); ?>
            </div>
        </div>
    </div>
    <script>
    function fmbToggleDescClassic() {
        var body  = document.getElementById('fmb-desc-body-c');
        var arrow = document.getElementById('fmb-desc-arrow-c');
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
