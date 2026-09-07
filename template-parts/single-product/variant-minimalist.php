<?php
/**
 * Single Product Variant 4: Clean Minimalist Bento-Grid & Storyteller
 */
if (!defined('ABSPATH')) exit;

$fmb_free_delivery_banner = fmb_is_effectively_free_delivery_catalog( $main_id );
$banner_image = get_theme_mod('fmb_free_delivery_image', '');
$banner_text  = get_theme_mod('fmb_free_delivery_text', '🚚 সারাদেশে ডেলিভারি সম্পূর্ণ বিনামূল্যে!');

$regular_price = $product->get_regular_price();
$sale_price = $product->get_sale_price();
$savings = ($regular_price && $sale_price && $regular_price > $sale_price) ? ($regular_price - $sale_price) : 0;
?>

<div class="fmb-v4-minimalist" style="background: #f1f5f9; color: #0f172a; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; padding-bottom: 30px;">
    
    <!-- Clean Delivery Badge -->
    <?php if ( $fmb_free_delivery_banner ) : ?>
    <div style="max-width: 1100px; margin: 0 auto; padding: 16px 16px 0;">
        <?php if (!empty($banner_image)) : ?>
            <img src="<?php echo esc_url($banner_image); ?>" alt="Free Delivery" style="width: 100%; height: auto; border-radius: 16px;">
        <?php else : ?>
            <div style="background: #ffffff; color: #334155; border: 1px solid #e2e8f0; padding: 12px 20px; border-radius: 30px; font-weight: 600; font-size: 13.5px; text-align: center; box-shadow: 0 1px 3px rgba(0,0,0,0.02); display: flex; align-items: center; justify-content: center; gap: 8px;">
                <span style="width: 8px; height: 8px; background: #10b981; border-radius: 50%; display: inline-block;"></span>
                <span><?php echo esc_html($banner_text); ?></span>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Minimalist Asymmetric Hero Box -->
    <div style="max-width: 1100px; margin: 20px auto; padding: 0 16px;">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 28px; align-items: start;">
            
            <!-- Left: Large Clean Frame -->
            <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 28px; padding: 24px; box-shadow: 0 1px 4px rgba(0,0,0,0.02); display: flex; flex-direction: column; gap: 14px;">
                <div style="background: #f8fafc; border-radius: 20px; padding: 20px; display: flex; align-items: center; justify-content: center; aspect-ratio: 1/1;">
                    <?php 
                    $main_img_url = wp_get_attachment_image_url($product->get_image_id(), 'large') ?: wc_placeholder_img_src();
                    ?>
                    <img id="fmb-main-product-image-minimalist" src="<?php echo esc_url($main_img_url); ?>" alt="<?php the_title(); ?>" style="width: 100%; height: 100%; object-fit: cover; border-radius: 10px;">
                </div>

                <!-- Gallery Row -->
                <?php 
                $gallery_ids = $product->get_gallery_image_ids();
                if($gallery_ids):
                    $gallery_ids = array_slice($gallery_ids, 0, 4);
                ?>
                <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px;">
                    <?php foreach($gallery_ids as $g_id): 
                        $g_img_thumb = wp_get_attachment_image_url($g_id, 'thumbnail');
                        $g_img_large = wp_get_attachment_image_url($g_id, 'large');
                    ?>
                        <div onclick="document.getElementById('fmb-main-product-image-minimalist').src='<?php echo esc_url($g_img_large); ?>'" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; aspect-ratio: 1/1; padding: 4px; cursor: pointer; transition: transform 0.2s ease;" onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform='scale(1)'">
                            <img src="<?php echo esc_url($g_img_thumb); ?>" alt="<?php the_title(); ?>" style="width: 100%; height: 100%; object-fit: cover; border-radius: 8px;">
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Right: Minimalist Storyboard -->
            <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 28px; padding: 32px; box-shadow: 0 1px 4px rgba(0,0,0,0.02); display: flex; flex-direction: column; gap: 18px;">
                
                <div>
                    <span style="font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; color: #4f46e5; background: #eef2ff; padding: 4px 12px; border-radius: 20px;">
                        MINIMALIST COLLECTION
                    </span>
                    <h1 style="font-size: 26px; font-weight: 800; color: #0f172a; margin: 12px 0 0; line-height: 1.35;">
                        <?php the_title(); ?>
                    </h1>
                </div>

                <!-- Price Block -->
                <div style="display: flex; align-items: baseline; gap: 12px; padding: 14px 0; border-top: 1px solid #f1f5f9; border-bottom: 1px solid #f1f5f9;">
                    <span style="font-size: 32px; font-weight: 900; color: #0f172a;">
                        <?php echo wc_price($product->get_price()); ?>
                    </span>
                    <?php if ($savings > 0) : ?>
                        <span style="font-size: 18px; color: #94a3b8; text-decoration: line-through; font-weight: 500;">
                            <?php echo wc_price($regular_price); ?>
                        </span>
                        <span style="background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; padding: 3px 10px; border-radius: 20px; font-size: 12px; font-weight: 700;">
                            Save <?php echo wc_price($savings); ?>
                        </span>
                    <?php endif; ?>
                </div>

                <!-- Sales Proof -->
                <?php if (get_theme_mod('fmb_show_fake_sales', true)) : ?>
                    <div><?php fmb_render_sales_proof_badge( $main_id, '', 'margin-top: 14px; margin-bottom: 6px;' ); ?></div>
                <?php endif; ?>

                <!-- 4 Value Pill Badges -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; font-size: 12px; color: #475569; font-weight: 600;">
                    <div style="background: #f8fafc; padding: 8px 12px; border-radius: 8px;">✓ ক্যাশ অন ডেলিভারি</div>
                    <div style="background: #f8fafc; padding: 8px 12px; border-radius: 8px;">✓ ৭ দিনের রিপ্লেসমেন্ট</div>
                    <div style="background: #f8fafc; padding: 8px 12px; border-radius: 8px;">✓ ১০০% অথেনটিক পণ্য</div>
                    <div style="background: #f8fafc; padding: 8px 12px; border-radius: 8px;">✓ নিরাপদ প্যাকেজিং</div>
                </div>

                <div style="font-size: 14px; color: #64748b; line-height: 1.65;">
                    <?php echo apply_filters('woocommerce_short_description', $product->get_short_description()); ?>
                </div>

                <!-- Quantity -->
                <div style="display: flex; align-items: center; justify-content: space-between; padding-top: 10px; border-top: 1px solid #f1f5f9;">
                    <strong style="font-size: 14px; color: #1e293b;">Quantity:</strong>
                    <div style="display: inline-flex; border: 1px solid #cbd5e1; border-radius: 10px; background: #f8fafc; overflow: hidden;">
                        <button type="button" onclick="changeTopQty(-1)" style="padding: 6px 14px; font-size: 16px; font-weight: 800; background: transparent; border: none; cursor: pointer;">-</button>
                        <input type="text" id="top-qty-input" value="1" readonly style="width: 40px; text-align: center; font-weight: 800; font-size: 15px; border: none; outline: none; background: transparent;">
                        <button type="button" onclick="changeTopQty(1)" style="padding: 6px 14px; font-size: 16px; font-weight: 800; background: transparent; border: none; cursor: pointer;">+</button>
                    </div>
                </div>

                <!-- Action Button -->
                <div style="display: flex; flex-direction: column; gap: 8px;">
                    <a href="#checkout-area" onclick="event.preventDefault(); fmbGoToCheckout();" class="fmb-order-now-btn" style="background: var(--btn-bg, var(--primary)) !important; color: var(--btn-text, #ffffff) !important; text-decoration: none; padding: 15px 24px; border-radius: 14px; font-size: 16px; font-weight: 800; text-align: center; box-shadow: 0 4px 12px rgba(0,0,0,0.12);">
                        অর্ডার করুন — ক্যাশ অন ডেলিভারি
                    </a>
                    <?php if (get_theme_mod('fmb_show_add_to_cart_button', true)) : ?>
                    <button type="button" id="fmb-add-to-cart-btn" class="fmb-single-atc-btn" style="background: #1e293b; color: #ffffff; border: none; padding: 12px; border-radius: 14px; font-size: 14px; font-weight: 700; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; transition: all 0.2s;">
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

                <div style="display: flex; justify-content: center; gap: 16px; font-size: 12px; color: #64748b; font-weight: 600; padding-top: 6px;">
                    <?php if (get_theme_mod('fmb_show_call_button', true)) : ?>
                    <a href="tel:<?php echo esc_attr($store_phone); ?>" style="text-decoration: none; color: inherit;">📞 Helpline</a>
                    <?php endif; ?>
                    <span>•</span>
                    <?php if (get_theme_mod('fmb_show_whatsapp_button', true)) : ?>
                    <a href="<?php echo esc_url($msg_url); ?>" target="_blank" style="text-decoration: none; color: inherit;">💬 WhatsApp</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Section 2: Bento Grid Benefits -->
    <?php 
    $cards_title = get_post_meta($main_id, '_fmb_cards_title', true) ?: 'কেন এটি সেরা?';
    if(!empty($cards) && is_array($cards)): 
    ?>
    <div style="max-width: 1100px; margin: 40px auto; padding: 0 16px;">
        <h3 style="font-size: 22px; font-weight: 800; text-align: center; color: #0f172a; margin-bottom: 24px;"><?php echo esc_html($cards_title); ?></h3>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px;">
            <?php foreach($cards as $card): 
                if(empty($card['title']) && empty($card['desc'])) continue;
            ?>
                <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 24px; padding: 26px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
                    <?php if(!empty($card['img'])): ?>
                        <img src="<?php echo esc_url($card['img']); ?>" alt="<?php echo esc_attr($card['title']); ?>" style="width: 55px; height: 55px; object-fit: contain; margin-bottom: 14px; border-radius: 10px;">
                    <?php else: ?>
                        <div style="width: 44px; height: 44px; background: #f8fafc; color: #4f46e5; font-size: 20px; border-radius: 12px; display: flex; align-items: center; justify-content: center; margin-bottom: 14px; border: 1px solid #e2e8f0;">
                            ✦
                        </div>
                    <?php endif; ?>
                    <h4 style="font-size: 16px; font-weight: 800; color: #0f172a; margin: 0 0 8px 0;"><?php echo esc_html($card['title']); ?></h4>
                    <p style="font-size: 13px; color: #64748b; line-height: 1.6; margin: 0;"><?php echo esc_html($card['desc']); ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Section 1: Timeline Steps -->
    <?php 
    $steps_title = get_post_meta($main_id, '_fmb_steps_title', true) ?: 'ব্যবহারের সহজ নিয়মাবলী';
    if(!empty($steps) && is_array($steps)): 
    ?>
    <div style="max-width: 1100px; margin: 40px auto; padding: 0 16px;">
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 28px; padding: 30px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
            <h3 style="font-size: 20px; font-weight: 800; color: #0f172a; margin: 0 0 20px 0;"><?php echo esc_html($steps_title); ?></h3>
            <div style="display: flex; flex-direction: column; gap: 14px;">
                <?php foreach($steps as $index => $step): if(empty($step)) continue; ?>
                    <div style="display: flex; align-items: flex-start; gap: 14px; padding-bottom: 12px; border-bottom: 1px solid #f1f5f9;">
                        <span style="font-size: 11px; font-weight: 800; color: #64748b; border: 1px solid #cbd5e1; width: 22px; height: 22px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; margin-top: 2px;">
                            <?php echo $index + 1; ?>
                        </span>
                        <div style="font-size: 14.5px; color: #334155; line-height: 1.6;">
                            <?php echo esc_html($step); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
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
    <div style="max-width: 1100px; margin: 40px auto; padding: 0 16px;">
        <h3 style="font-size: 20px; font-weight: 800; text-align: center; color: #0f172a; margin-bottom: 20px;"><?php echo esc_html($upsell_title); ?></h3>
        <style>
        .fmb-upsell-grid-m { display: grid; grid-template-columns: repeat(5, 1fr); gap: 10px; }
        @media (max-width: 767px) { .fmb-upsell-grid-m { grid-template-columns: repeat(3, 1fr); gap: 8px; } }
        @media (max-width: 480px) { .fmb-upsell-grid-m { grid-template-columns: repeat(2, 1fr); gap: 7px; } }
        </style>
        <div class="fmb-upsell-grid-m">
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
                <h4 style="font-size: 11px; font-weight: 700; color: #0f172a; margin: 0; line-height: 1.3; overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;"><?php the_title(); ?></h4>
                <div style="font-size: 13px; font-weight: 800; color: #0f172a;"><?php echo esc_html($u_price); ?>৳</div>
                <?php 
                    $logic_qty = $upsell_logic_map[$u_id]['qty'] ?? 0;
                    $logic_type = $upsell_logic_map[$u_id]['type'] ?? '';
                    $logic_value = $upsell_logic_map[$u_id]['value'] ?? 0;
                ?>
                <button type="button" class="btn-add-upsell" style="background: #f1f5f9; color: #0f172a; border: 1px solid #cbd5e1; padding: 6px 4px; border-radius: 6px; font-size: 11px; font-weight: 700; cursor: pointer; width: 100%;"
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

    <!-- Description (Accordion) -->
    <div style="max-width: 1100px; margin: 32px auto; padding: 0 16px;">
        <div style="background: #ffffff; border: 1.5px solid #e2e8f0; border-radius: 20px; overflow: hidden;">
            <button type="button" onclick="fmbToggleDescMin()" style="width: 100%; display: flex; align-items: center; justify-content: space-between; padding: 15px 22px; background: #f8fafc; border: none; cursor: pointer; font-size: 15px; font-weight: 800; color: #0f172a;">
                <span>📋 পণ্যের বিস্তারিত তথ্য</span>
                <span id="fmb-desc-arrow-m" style="font-size: 20px; transition: transform 0.3s;">▼</span>
            </button>
            <div id="fmb-desc-body-m" style="display: none; padding: 22px; font-size: 15px; line-height: 1.8; color: #334155; border-top: 1.5px solid #e2e8f0;">
                <?php the_content(); ?>
            </div>
        </div>
    </div>
    <script>
    function fmbToggleDescMin() {
        var body  = document.getElementById('fmb-desc-body-m');
        var arrow = document.getElementById('fmb-desc-arrow-m');
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
