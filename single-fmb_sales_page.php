<?php
/**
 * Custom Sales Funnel Page Template - Premium Redesign
 */
get_header();

while ( have_posts() ) : the_post();
    $page_id = get_the_ID();
    $products = get_post_meta($page_id, '_fmb_sp_products', true) ?: array();
    $bump_trigger_ids = get_post_meta($page_id, '_fmb_sp_bump_trigger_ids', true) ?: array();
    $bump_is_default = get_post_meta($page_id, '_fmb_sp_bump_is_default', true) === 'yes';
    $bump_target_id = get_post_meta($page_id, '_fmb_sp_bump_target', true);
    $bump_target_price = get_post_meta($page_id, '_fmb_sp_bump_price', true);
    $bump_free_delivery = get_post_meta($page_id, '_fmb_sp_bump_free_delivery', true) === 'yes';
    $bump_free_product = get_post_meta($page_id, '_fmb_sp_bump_free_product', true) === 'yes';
    
    $free_shipping_trigger_ids = get_post_meta($page_id, '_fmb_sp_free_shipping_trigger_ids', true) ?: array();
    $bump_desc = get_post_meta($page_id, '_fmb_sp_bump_desc', true);
    $default_bump_desc = get_post_meta($page_id, '_fmb_sp_default_bump_desc', true);
    $free_shipping_desc = get_post_meta($page_id, '_fmb_sp_free_shipping_desc', true);
    $default_bump_ids = get_post_meta($page_id, '_fmb_sp_default_bump_ids', true) ?: array();

    $default_bump_products = array();
    foreach ($default_bump_ids as $d_id) {
        $_dprod = wc_get_product($d_id);
        if ($_dprod) {
            $default_bump_products[] = array(
                'id' => $d_id,
                'name' => $_dprod->get_name(),
                'price' => $_dprod->get_price(),
                'img' => wp_get_attachment_image_url($_dprod->get_image_id(), 'thumbnail') ?: wc_placeholder_img_src()
            );
        }
    }

    $bump_target_name = '';
    $bump_target_img = '';
    if ($bump_target_id) {
        $bump_target_name = get_the_title($bump_target_id);
        $bump_target_img = wp_get_attachment_image_url(get_post_thumbnail_id($bump_target_id), 'thumbnail') ?: wc_placeholder_img_src();
    }
?>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');

    .sp-page * { font-family: 'Inter', sans-serif; box-sizing: border-box; }
    
    /* Hero */
    .sp-hero {
        background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #334155 100%);
        position: relative; overflow: hidden;
    }
    .sp-hero::before {
        content: ''; position: absolute; top: -50%; right: -20%; width: 600px; height: 600px;
        background: radial-gradient(circle, rgba(99,102,241,0.15) 0%, transparent 70%);
        border-radius: 50%;
    }
    .sp-hero::after {
        content: ''; position: absolute; bottom: -30%; left: -10%; width: 400px; height: 400px;
        background: radial-gradient(circle, rgba(244,63,94,0.1) 0%, transparent 70%);
        border-radius: 50%;
    }
    .sp-hero-content { position: relative; z-index: 2; padding: 60px 20px; max-width: 900px; margin: 0 auto; text-align: center; }
    .sp-hero h1 {
        font-size: 2.25rem; font-weight: 800; color: #fff;
        line-height: 1.3; margin: 0; text-shadow: 0 4px 15px rgba(0,0,0,0.2);
    }
    .sp-hero-desc {
        color: #e2e8f0; font-size: 1.1rem; line-height: 1.7;
        margin-top: 16px; font-weight: 400;
    }

    /* Product Grid */
    .sp-products-section { background: #f8fafc; padding: 60px 20px; }
    .sp-section-title {
        font-size: 1.5rem; font-weight: 800; color: #0f172a;
        margin-bottom: 24px; text-align: center;
    }
    
    .sp-product-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; max-width: 1100px; margin: 0 auto; }
    @media(min-width: 768px) { .sp-product-grid { grid-template-columns: repeat(4, 1fr); gap: 24px; } }

    .sp-product-card {
        background: #fff; border-radius: 16px; overflow: hidden;
        border: 1px solid #e2e8f0; position: relative;
        box-shadow: 0 4px 20px rgba(0,0,0,0.03);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        display: flex; flex-direction: column;
    }
    .sp-product-card:hover {
        transform: translateY(-8px);
        box-shadow: 0 20px 40px rgba(99,102,241,0.12);
        border-color: #c7d2fe;
    }
    .sp-product-img-wrap {
        aspect-ratio: 1; background: #f1f5f9; position: relative;
        overflow: hidden; display: flex; align-items: center; justify-content: center;
        padding: 24px;
    }
    .sp-product-img-wrap img {
        width: 100%; height: 100%; object-fit: contain;
        transition: transform 0.5s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .sp-product-card:hover .sp-product-img-wrap img { transform: scale(1.1); }
    
    .sp-badge {
        position: absolute; top: 12px; z-index: 5;
        padding: 6px 12px; border-radius: 8px;
        font-size: 11px; font-weight: 800; color: #fff;
        text-transform: uppercase; letter-spacing: 0.5px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    }
    .sp-badge-offer { left: 12px; background: linear-gradient(135deg, #f43f5e, #f97316); }
    .sp-badge-free { right: 12px; background: linear-gradient(135deg, #10b981, #059669); }

    .sp-product-info { padding: 20px; flex-grow: 1; display: flex; flex-direction: column; }
    .sp-product-name {
        font-size: 1rem; font-weight: 700; color: #1e293b;
        line-height: 1.5; min-height: 3em;
        display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
    }
    .sp-product-price {
        font-size: 1.25rem; font-weight: 800; color: #4f46e5;
        margin-top: 8px;
    }
    .sp-add-btn { margin-top: 16px; }
    .sp-add-btn button {
        width: 100%; padding: 12px; border: none; border-radius: 10px;
        background: #0f172a; color: #fff; font-weight: 700; font-size: 0.95rem;
        cursor: pointer; transition: all 0.3s ease;
        display: flex; align-items: center; justify-content: center; gap: 8px;
    }
    .sp-add-btn button:hover {
        background: #4f46e5; transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(79,70,229,0.3);
    }
    .sp-add-btn button.added {
        background: #10b981 !important; box-shadow: 0 8px 20px rgba(16,185,129,0.3) !important;
    }

    /* Checkout Area */
    .sp-checkout-section { padding: 60px 20px 100px; background: #fff; }
    .sp-checkout-wrap {
        max-width: 1100px; margin: 0 auto;
        background: #fff; border-radius: 24px;
        box-shadow: 0 10px 50px rgba(0,0,0,0.06);
        border: 1px solid #e2e8f0; overflow: hidden;
    }
    .sp-checkout-header {
        background: #0f172a; padding: 24px 32px;
        text-align: center; border-bottom: 1px solid #334155;
    }
    .sp-checkout-header h3 { color: #fff; font-size: 1.5rem; font-weight: 800; margin: 0; }
    .sp-checkout-header p { color: #94a3b8; font-size: 0.95rem; margin-top: 8px; }
    
    /* Split Layout */
    .sp-form-body { display: flex; flex-direction: column; }
    @media(min-width: 992px) {
        .sp-form-body { flex-direction: row; }
        .sp-checkout-left { flex: 1.2; padding: 40px; border-right: 1px solid #e2e8f0; }
        .sp-checkout-right { flex: 1; padding: 40px; background: #f8fafc; }
    }
    @media(max-width: 991px) {
        .sp-checkout-left, .sp-checkout-right { padding: 24px; }
        .sp-checkout-right { background: #f8fafc; border-top: 1px solid #e2e8f0; }
    }

    .sp-delivery-title {
        font-size: 1rem; font-weight: 800; color: #0f172a;
        text-transform: uppercase; letter-spacing: 0.5px;
        margin-bottom: 16px; display: flex; align-items: center; gap: 10px;
    }

    /* Form Inputs */
    .sp-input-group { display: flex; flex-direction: column; gap: 16px; }
    .sp-input {
        width: 100%; padding: 14px 16px; border: 1px solid #cbd5e1;
        border-radius: 12px; font-size: 1rem; color: #0f172a;
        background: #fff; outline: none; transition: all 0.3s;
        box-shadow: 0 2px 4px rgba(0,0,0,0.02) inset;
    }
    .sp-input:focus {
        border-color: #4f46e5; box-shadow: 0 0 0 4px rgba(79,70,229,0.1);
    }
    .sp-input::placeholder { color: #94a3b8; }
    textarea.sp-input { resize: vertical; min-height: 100px; }

    /* Radio Options (Delivery/Payment) */
    .sp-radio-wrap {
        border-radius: 12px; overflow: hidden; border: 1px solid #cbd5e1;
        background: #fff;
    }
    .sp-radio-opt {
        display: flex; justify-content: space-between; align-items: center;
        padding: 16px; border-bottom: 1px solid #e2e8f0;
        cursor: pointer; transition: all 0.2s;
    }
    .sp-radio-opt:last-child { border-bottom: none; }
    .sp-radio-opt:hover { background: #f1f5f9; }
    .sp-radio-opt input[type="radio"] { accent-color: #4f46e5; width: 18px; height: 18px; }
    .sp-radio-label { display: flex; align-items: center; gap: 12px; font-weight: 600; color: #1e293b; }
    .sp-radio-price { font-weight: 800; color: #0f172a; }
    .sp-delivery-free { background: #ecfdf5 !important; }
    .sp-delivery-free .sp-radio-price { color: #059669 !important; }

    /* Bump Offers */
    .sp-bump-card {
        border-radius: 16px; padding: 20px; position: relative;
        margin-bottom: 20px; overflow: hidden;
        transition: all 0.3s ease;
    }
    .sp-bump-triggered { background: #fffbeb; border: 2px solid #f59e0b; box-shadow: 0 4px 15px rgba(245,158,11,0.1); }
    .sp-bump-default { background: #eff6ff; border: 2px solid #3b82f6; box-shadow: 0 4px 15px rgba(59,130,246,0.1); }
    
    .sp-bump-badge {
        position: absolute; top: 0; right: 0;
        padding: 6px 16px; border-radius: 0 14px 0 14px;
        font-size: 10px; font-weight: 800; color: #fff;
        text-transform: uppercase; letter-spacing: 1px;
    }
    .sp-bump-triggered .sp-bump-badge { background: #f59e0b; }
    .sp-bump-default .sp-bump-badge { background: #3b82f6; }

    .sp-bump-inner { display: flex; align-items: flex-start; gap: 16px; }
    .sp-bump-img {
        width: 80px; height: 80px; border-radius: 12px;
        object-fit: cover; border: 1px solid rgba(0,0,0,0.1);
        background: #fff; flex-shrink: 0;
    }
    .sp-bump-info { flex-grow: 1; }
    .sp-bump-name { font-weight: 800; font-size: 1rem; color: #0f172a; line-height: 1.3; }
    .sp-bump-desc { font-size: 0.85rem; color: #475569; margin-top: 6px; }
    .sp-bump-price-line { font-size: 0.9rem; font-weight: 600; color: #334155; margin-top: 8px; }
    .sp-bump-price-line strong { color: #e11d48; font-size: 1.1rem; font-weight: 800; }
    
    .sp-bump-check {
        display: inline-flex; align-items: center; gap: 10px;
        margin-top: 12px; padding: 10px 20px; border-radius: 8px;
        cursor: pointer; transition: all 0.2s; font-weight: 700; font-size: 0.9rem;
    }
    .sp-bump-triggered .sp-bump-check { background: #fff; border: 2px solid #f59e0b; color: #b45309; }
    .sp-bump-triggered .sp-bump-check:hover { background: #fef3c7; }
    .sp-bump-default .sp-bump-check { background: #fff; border: 2px solid #3b82f6; color: #1d4ed8; }
    .sp-bump-default .sp-bump-check:hover { background: #dbeafe; }
    .sp-bump-check input { width: 18px; height: 18px; accent-color: currentColor; }

    /* Cart Items */
    .sp-cart-section {
        background: #fff; border-radius: 16px; padding: 24px;
        border: 1px solid #e2e8f0; box-shadow: 0 4px 15px rgba(0,0,0,0.02);
    }
    .sp-cart-title {
        font-size: 1rem; font-weight: 800; color: #0f172a;
        text-transform: uppercase; margin-bottom: 16px;
        display: flex; justify-content: space-between; align-items: center;
    }
    .sp-cart-empty { color: #ef4444; font-size: 0.85rem; }
    
    .sp-cart-item {
        display: flex; align-items: center; gap: 16px;
        padding: 16px 0; border-bottom: 1px solid #f1f5f9;
        position: relative;
    }
    .sp-cart-item:last-child { border-bottom: none; padding-bottom: 0; }
    .sp-cart-item img {
        width: 60px; height: 60px; border-radius: 10px;
        object-fit: cover; border: 1px solid #e2e8f0;
    }
    .sp-cart-item-info { flex-grow: 1; padding-right: 30px; }
    .sp-cart-item-name { font-size: 0.95rem; font-weight: 700; color: #1e293b; line-height: 1.3; }
    .sp-cart-item-bottom { display: flex; justify-content: space-between; align-items: center; margin-top: 8px; }
    .sp-cart-item-price { font-size: 0.95rem; font-weight: 800; color: #4f46e5; }
    
    .sp-qty-control {
        display: inline-flex; align-items: center;
        border: 1px solid #cbd5e1; border-radius: 8px;
        background: #fff;
    }
    .sp-qty-control button {
        width: 32px; height: 32px; border: none; background: transparent;
        font-weight: 700; color: #64748b; cursor: pointer; font-size: 16px;
        display: flex; align-items: center; justify-content: center; transition: background 0.2s;
    }
    .sp-qty-control button:hover { background: #f1f5f9; color: #0f172a; }
    .sp-qty-control span { padding: 0 12px; font-size: 0.9rem; font-weight: 800; color: #0f172a; }
    
    .sp-cart-remove {
        position: absolute; top: 16px; right: 0;
        width: 24px; height: 24px; border-radius: 50%;
        border: none; background: #fee2e2; color: #ef4444;
        cursor: pointer; display: flex; align-items: center; justify-content: center;
        transition: all 0.2s; opacity: 0;
    }
    .sp-cart-item:hover .sp-cart-remove { opacity: 1; }
    .sp-cart-remove:hover { background: #ef4444; color: #fff; }

    /* Summary */
    .sp-summary {
        margin-top: 24px; padding-top: 24px;
        border-top: 2px solid #e2e8f0;
    }
    .sp-summary-row {
        display: flex; justify-content: space-between; align-items: center;
        padding: 6px 0; font-size: 1rem; color: #475569; font-weight: 500;
    }
    .sp-summary-row span:last-child { font-weight: 700; color: #0f172a; }
    .sp-summary-total {
        display: flex; justify-content: space-between; align-items: center;
        padding: 16px 0 0; margin-top: 12px; border-top: 2px dashed #cbd5e1;
    }
    .sp-summary-total span:first-child { font-size: 1.25rem; font-weight: 800; color: #0f172a; }
    .sp-summary-total span:last-child {
        font-size: 1.75rem; font-weight: 900; color: #4f46e5;
    }

    /* Submit Button */
    .sp-submit-btn {
        width: 100%; margin-top: 32px; padding: 20px;
        border: none; border-radius: 16px;
        background: linear-gradient(135deg, #4f46e5, #3b82f6);
        color: #fff; font-size: 1.25rem; font-weight: 800;
        cursor: pointer; transition: all 0.3s ease;
        display: flex; align-items: center; justify-content: center; gap: 12px;
        box-shadow: 0 10px 25px rgba(79,70,229,0.3);
    }
    .sp-submit-btn:hover {
        transform: translateY(-3px); box-shadow: 0 15px 35px rgba(79,70,229,0.4);
    }
    .sp-submit-btn:disabled {
        opacity: 0.6; cursor: not-allowed; transform: none; box-shadow: none;
    }
    .sp-btn-price { background: rgba(0,0,0,0.2); padding: 6px 16px; border-radius: 30px; font-size: 1.1rem; }

    /* Floating Cart */
    .sp-float-cart {
        position: fixed; bottom: 24px; right: 24px; z-index: 999;
        width: 64px; height: 64px; border-radius: 50%;
        background: #4f46e5; color: #fff; display: none; align-items: center; justify-content: center;
        box-shadow: 0 8px 25px rgba(79,70,229,0.4); cursor: pointer; transition: all 0.3s;
    }
    .sp-float-cart:hover { transform: scale(1.05) translateY(-5px); }
    .sp-cart-count {
        position: absolute; top: 0; right: 0;
        width: 24px; height: 24px; border-radius: 50%;
        background: #ef4444; font-size: 12px; font-weight: 800;
        display: flex; align-items: center; justify-content: center; border: 2px solid #fff;
    }

    /* Trust Badges */
    .sp-trust {
        display: flex; justify-content: center; gap: 24px; margin-top: 32px; flex-wrap: wrap;
    }
    .sp-trust-item {
        display: flex; align-items: center; gap: 8px; font-size: 0.9rem; color: #64748b; font-weight: 600;
    }
    .sp-trust-item svg { width: 20px; height: 20px; color: #10b981; fill: none; stroke: currentColor; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }

    /* SVG Icons */
    .sp-icon { width: 24px; height: 24px; stroke-width: 2; fill: none; stroke: currentColor; stroke-linecap: round; stroke-linejoin: round; }
    .sp-icon-sm { width: 16px; height: 16px; stroke-width: 2.5; fill: none; stroke: currentColor; }

    @keyframes fadeInUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
    .sp-animate { animation: fadeInUp 0.6s cubic-bezier(0.4, 0, 0.2, 1) both; }
    .sp-animate-d1 { animation-delay: 0.1s; }
    .sp-animate-d2 { animation-delay: 0.2s; }
</style>

<div class="sp-page">
    <div class="sp-float-cart" id="sp-float-cart" onclick="document.getElementById('checkout-area').scrollIntoView({behavior:'smooth',block:'start'})">
        <svg class="sp-icon" viewBox="0 0 24 24"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"></path><line x1="3" y1="6" x2="21" y2="6"></line><path d="M16 10a4 4 0 01-8 0"></path></svg>
        <div class="sp-cart-count" id="sp-cart-count">0</div>
    </div>

    <!-- Hero Section -->
    <div class="sp-hero">
        <div class="sp-hero-content">
            <h1 class="sp-animate"><?php the_title(); ?></h1>
            <?php if (get_the_content()) : ?>
            <div class="sp-hero-desc sp-animate sp-animate-d1">
                <?php the_content(); ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Products Section -->
    <?php if (!empty($products)) : ?>
    <div class="sp-products-section">
        <div class="sp-section-title sp-animate">
            Choose Your Products
        </div>
        <?php if (!empty($free_shipping_desc)) : ?>
        <div class="sp-animate" style="text-align:center; font-size:1rem; color:#059669; font-weight:700; margin-top:-10px; margin-bottom:30px;">
            <?php echo esc_html($free_shipping_desc); ?>
        </div>
        <?php endif; ?>
        <div class="sp-product-grid">
            <?php 
            $card_index = 0;
            foreach ($products as $p_id) : 
                $_product = wc_get_product($p_id);
                if (!$_product) continue;
                $p_img = wp_get_attachment_image_url($_product->get_image_id(), 'medium') ?: wc_placeholder_img_src();
                $p_price = $_product->get_price();
                $p_name = $_product->get_name();
                $card_index++;
            ?>
            <div class="sp-product-card sp-animate" style="animation-delay:<?php echo ($card_index * 0.1); ?>s;">
                <?php if ($p_id == $bump_target_id || in_array($p_id, $default_bump_ids)): ?>
                <span class="sp-badge sp-badge-offer">Special Offer</span>
                <?php endif; ?>
                <?php if (in_array($p_id, $free_shipping_trigger_ids) || fmb_is_effectively_free_delivery_catalog($p_id)): ?>
                <span class="sp-badge sp-badge-free">Free Delivery</span>
                <?php endif; ?>
                
                <div class="sp-product-img-wrap">
                    <img src="<?php echo esc_url($p_img); ?>" alt="<?php echo esc_attr($p_name); ?>">
                </div>
                <div class="sp-product-info">
                    <div class="sp-product-name"><?php echo esc_html($p_name); ?></div>
                    <div class="sp-product-price">Tk <?php echo esc_html($p_price); ?></div>
                    <div class="sp-add-btn">
                        <button type="button" class="btn-sp-add-product"
                            data-id="<?php echo esc_attr($p_id); ?>"
                            data-name="<?php echo esc_attr($p_name); ?>"
                            data-price="<?php echo esc_attr($p_price); ?>"
                            data-img="<?php echo esc_url($p_img); ?>"
                            data-free-delivery="<?php echo fmb_is_effectively_free_delivery_catalog($p_id) ? '1' : '0'; ?>">
                            <svg class="sp-icon-sm" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
                            Add to Cart
                        </button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Checkout Section -->
    <div class="sp-checkout-section" id="checkout-area">
        <div class="sp-checkout-wrap sp-animate sp-animate-d2">
            <div class="sp-checkout-header">
                <h3>Complete Your Order</h3>
                <p>Please fill out the details below to finalize your purchase.</p>
            </div>

            <form id="multi-product-checkout-form" class="sp-form-body">
                
                <!-- Left Side: Form & Shipping -->
                <div class="sp-checkout-left">
                    <div class="sp-delivery-title">
                        <svg class="sp-icon" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                        Your Information
                    </div>
                    <div class="sp-input-group">
                        <input type="text" name="landing_billing_first_name" required placeholder="Full Name" class="sp-input">
                        <input type="tel" name="landing_billing_phone" required placeholder="Mobile Number" class="sp-input">
                        <textarea name="landing_billing_address_1" required placeholder="Full Address (House, Road, Area)..." class="sp-input"></textarea>
                    </div>

                    <div class="sp-delivery-title" style="margin-top: 40px;">
                        <svg class="sp-icon" viewBox="0 0 24 24"><path d="M5 17h14v-9l-3-4H8l-3 4v9zm0 0v4h14v-4m-14 0h14"></path></svg>
                        Delivery Area
                    </div>
                    <div class="sp-radio-wrap" id="delivery-options-container">
                        <?php
                        $rates_cached = fmb_wc_cached_zone_delivery_rates();
                        $is_first = true;
                        if ( ! $rates_cached['has'] ) {
                            ?>
                            <label class="sp-radio-opt">
                                <span class="sp-radio-label">
                                    <input type="radio" name="delivery_area" value="70" checked> Inside Dhaka City
                                </span>
                                <span class="sp-radio-price">Tk 70</span>
                            </label>
                            <label class="sp-radio-opt">
                                <span class="sp-radio-label">
                                    <input type="radio" name="delivery_area" value="130"> Outside Dhaka
                                </span>
                                <span class="sp-radio-price">Tk 130</span>
                            </label>
                            <?php
                        } else {
                            foreach ( $rates_cached['methods'] as $row ) {
                                $cost  = $row['cost'];
                                $label = $row['label'];
                                ?>
                                <label class="sp-radio-opt delivery-normal">
                                    <span class="sp-radio-label">
                                        <input type="radio" name="delivery_area" value="<?php echo esc_attr( $cost ); ?>" <?php echo $is_first ? 'checked' : ''; ?>>
                                        <?php echo esc_html( $label ); ?>
                                    </span>
                                    <span class="sp-radio-price">Tk <?php echo esc_html( $cost ); ?></span>
                                </label>
                                <?php
                                $is_first = false;
                            }
                        }
                        ?>
                        <label id="delivery-free-option" class="sp-radio-opt sp-delivery-free" style="display:none;">
                            <span class="sp-radio-label">
                                <input type="radio" name="delivery_area" value="0" id="radio-free-shipping"> Free Delivery Applicable!
                            </span>
                            <span class="sp-radio-price">Free</span>
                        </label>
                    </div>

                    <div class="sp-delivery-title" style="margin-top: 40px;">
                        <svg class="sp-icon" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                        Payment Method
                    </div>
                    <div class="sp-radio-wrap">
                        <label class="sp-radio-opt">
                            <span class="sp-radio-label">
                                <input type="radio" name="payment_method" value="cod" checked>
                                Cash on Delivery (Pay when you receive)
                            </span>
                        </label>
                    </div>
                </div>

                <!-- Right Side: Order Summary -->
                <div class="sp-checkout-right">
                    <div id="sp-bump-offer-container"></div>

                    <div class="sp-cart-section">
                        <div class="sp-cart-title">
                            Order Summary
                            <span id="empty-cart-msg" class="sp-cart-empty" style="display:none;">(Empty)</span>
                        </div>
                        <div id="order-items-list"></div>
                        
                        <div class="sp-summary">
                            <div class="sp-summary-row">
                                <span>Subtotal</span>
                                <span id="summary-subtotal">Tk 0</span>
                            </div>
                            <div class="sp-summary-row">
                                <span>Delivery Charge</span>
                                <span id="summary-shipping">Tk 0</span>
                            </div>
                            <div class="sp-summary-total">
                                <span>Total:</span>
                                <span id="final-total">Tk 0</span>
                            </div>
                        </div>
                    </div>

                    <input type="hidden" name="order_items" id="hidden_order_items">
                    <?php wp_nonce_field('fmb_multi_product_order_nonce', 'fmb_multi_nonce'); ?>

                    <button type="submit" id="sp-submit-btn" class="sp-submit-btn" disabled>
                        Confirm Order <span class="sp-btn-price" id="btn-final-total">Tk 0</span>
                    </button>

                    <div class="sp-trust">
                        <div class="sp-trust-item">
                            <svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
                            Secure Checkout
                        </div>
                        <div class="sp-trust-item">
                            <svg viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                            Verified Products
                        </div>
                        <div class="sp-trust-item">
                            <svg viewBox="0 0 24 24"><path d="M5 17h14v-9l-3-4H8l-3 4v9zm0 0v4h14v-4m-14 0h14"></path></svg>
                            Fast Delivery
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
window.spCart = [];
const bumpTriggerIds = <?php echo wp_json_encode($bump_trigger_ids); ?>;
const bumpIsDefault = <?php echo $bump_is_default ? 'true' : 'false'; ?>;
const bumpTargetId = <?php echo (int) $bump_target_id; ?>;
const bumpTargetPrice = <?php echo (float) $bump_target_price; ?>;
const bumpFreeDelivery = <?php echo $bump_free_delivery ? 'true' : 'false'; ?>;
const bumpFreeProduct = <?php echo $bump_free_product ? 'true' : 'false'; ?>;
const bumpTargetName = <?php echo wp_json_encode($bump_target_name); ?>;
const bumpTargetImg = <?php echo wp_json_encode($bump_target_img); ?>;
const freeShippingTriggerIds = <?php echo wp_json_encode($free_shipping_trigger_ids); ?>;
const bumpDesc = <?php echo wp_json_encode($bump_desc); ?>;
const defaultBumpDesc = <?php echo wp_json_encode($default_bump_desc); ?>;
const defaultBumpProducts = <?php echo wp_json_encode($default_bump_products); ?>;

document.addEventListener('DOMContentLoaded', function() {
    const $ = jQuery;
    if (typeof fbq === 'function') fbq('track', 'ViewContent');

    $('.btn-sp-add-product').on('click', function(e) {
        e.preventDefault();
        let btn = $(this);
        let id = btn.data('id');
        
        if (typeof fbq === 'function') {
            fbq('track', 'AddToCart', {
                content_ids: [String(id)],
                content_type: 'product',
                value: btn.data('price'),
                currency: 'BDT'
            });
        }

        let existing = spCart.find(item => item.id == id);
        if (existing) {
            existing.qty += 1;
            btn.html('<svg class="sp-icon-sm" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"></polyline></svg> Qty Increased').addClass('added');
        } else {
            spCart.push({
                id: id, name: btn.data('name'), price: btn.data('price'),
                img: btn.data('img'), qty: 1, freeDelivery: btn.data('free-delivery') == '1'
            });
            btn.html('<svg class="sp-icon-sm" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"></polyline></svg> Added to Cart').addClass('added');
        }
        
        setTimeout(() => {
            btn.html('<svg class="sp-icon-sm" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg> Add to Cart').removeClass('added');
        }, 1500);
        
        renderSpCart();
        $('html, body').animate({ scrollTop: $("#checkout-area").offset().top - 100 }, 500);
    });

    window.renderSpCart = function() {
        let listHTML = ''; let subtotal = 0;
        let hasBumpTrigger = false; let hasFreeShippingTrigger = false;

        if (spCart.length === 0) $('#empty-cart-msg').show();
        else $('#empty-cart-msg').hide();

        let totalItems = spCart.reduce((sum, item) => sum + item.qty, 0);
        if (totalItems > 0) {
            $('#sp-float-cart').css('display', 'flex');
            $('#sp-cart-count').text(totalItems);
        } else {
            $('#sp-float-cart').css('display', 'none');
        }

        spCart.forEach((item, index) => {
            if (bumpTriggerIds.includes(String(item.id)) || bumpTriggerIds.includes(parseInt(item.id))) hasBumpTrigger = true;
            if (freeShippingTriggerIds.includes(String(item.id)) || freeShippingTriggerIds.includes(parseInt(item.id))) hasFreeShippingTrigger = true;
            if (item.freeDelivery) hasFreeShippingTrigger = true;
            if (item.isBump && bumpFreeDelivery) hasFreeShippingTrigger = true;

            let itemPrice = parseFloat(item.price);
            let itemSubtotal = itemPrice * item.qty;
            subtotal += itemSubtotal;

            listHTML += `
                <div class="sp-cart-item">
                    <img src="${item.img}" alt="">
                    <div class="sp-cart-item-info">
                        <div class="sp-cart-item-name">${item.name}</div>
                        <div class="sp-cart-item-bottom">
                            <span class="sp-cart-item-price">${itemPrice === 0 ? 'FREE' : (item.qty > 1 ? `<span style="font-size:0.8rem;color:#94a3b8;font-weight:600;">Tk ${itemPrice} &times; ${item.qty} = </span>` : '') + 'Tk ' + itemSubtotal}</span>
                            <div class="sp-qty-control">
                                <button type="button" class="sp-qty-dec" data-index="${index}">−</button>
                                <span>${item.qty}</span>
                                <button type="button" class="sp-qty-inc" data-index="${index}">+</button>
                            </div>
                        </div>
                    </div>
                    <button type="button" class="sp-cart-remove sp-item-remove" data-index="${index}">
                        <svg class="sp-icon-sm" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                    </button>
                </div>
            `;
        });
        $('#order-items-list').html(listHTML);

        let bumpHTML = '';
        if ((bumpIsDefault || hasBumpTrigger) && bumpTargetId) {
            bumpHTML += `
                <div class="sp-bump-card sp-bump-triggered">
                    <div class="sp-bump-badge">Special Offer</div>
                    <div class="sp-bump-inner">
                        <img src="${bumpTargetImg}" class="sp-bump-img" alt="">
                        <div class="sp-bump-info">
                            <div class="sp-bump-name">${bumpTargetName}</div>
                            ${bumpDesc ? `<div class="sp-bump-desc">${bumpDesc}</div>` : ''}
                            <div class="sp-bump-price-line">Offer Price: <strong>Tk ${bumpTargetPrice}</strong></div>
                            <label class="sp-bump-check">
                                <input type="checkbox" class="sp-bump-checkbox" data-id="${bumpTargetId}" data-price="${bumpTargetPrice}" data-name="${bumpTargetName}" data-img="${bumpTargetImg}" data-is-triggered="true">
                                <span>Yes, I want this!</span>
                            </label>
                        </div>
                    </div>
                </div>
            `;
        } else {
            spCart = spCart.filter(item => !(item.id == bumpTargetId && item.isTriggeredBump === true));
        }

        defaultBumpProducts.forEach(prod => {
            bumpHTML += `
                <div class="sp-bump-card sp-bump-default">
                    <div class="sp-bump-badge">Recommended</div>
                    <div class="sp-bump-inner">
                        <img src="${prod.img}" class="sp-bump-img" alt="">
                        <div class="sp-bump-info">
                            <div class="sp-bump-name">${prod.name}</div>
                            ${defaultBumpDesc ? `<div class="sp-bump-desc">${defaultBumpDesc}</div>` : ''}
                            <div class="sp-bump-price-line">Price: <strong>Tk ${prod.price}</strong></div>
                            <label class="sp-bump-check">
                                <input type="checkbox" class="sp-bump-checkbox" data-id="${prod.id}" data-price="${prod.price}" data-name="${prod.name}" data-img="${prod.img}" data-is-triggered="false">
                                <span>Yes, add this!</span>
                            </label>
                        </div>
                    </div>
                </div>
            `;
        });

        $('#sp-bump-offer-container').html(bumpHTML);
        
        $('.sp-bump-checkbox').each(function() {
            let id = $(this).data('id');
            let isChecked = spCart.some(item => item.id == id && item.isBump);
            $(this).prop('checked', isChecked);
        });

        if (hasFreeShippingTrigger) {
            $('.delivery-normal').addClass('hidden').hide();
            $('#delivery-free-option').show().css('display', 'flex');
            $('#radio-free-shipping').prop('checked', true);
        } else {
            $('.delivery-normal').removeClass('hidden').show();
            $('#delivery-free-option').hide();
            if ($('#multi-product-checkout-form input[name="delivery_area"]:checked').val() == "0") {
                $('.delivery-normal input[type="radio"]').first().prop('checked', true);
            }
        }

        let shipping = parseFloat($('#multi-product-checkout-form input[name="delivery_area"]:checked').val()) || 0;
        let total = subtotal + shipping;
        
        $('#summary-subtotal').text('Tk ' + subtotal);
        $('#summary-shipping').text(shipping === 0 ? 'Free' : '+ Tk ' + shipping);
        $('#final-total').text('Tk ' + total);
        $('#btn-final-total').text('Tk ' + total);
        $('#hidden_order_items').val(JSON.stringify(spCart));
        
        if (spCart.length === 0) $('#sp-submit-btn').prop('disabled', true);
        else $('#sp-submit-btn').prop('disabled', false);
    };

    $('#order-items-list').on('click', '.sp-qty-inc', function() {
        let index = $(this).data('index'); spCart[index].qty += 1; renderSpCart();
    });
    $('#order-items-list').on('click', '.sp-qty-dec', function() {
        let index = $(this).data('index');
        if (spCart[index].qty > 1) { spCart[index].qty -= 1; renderSpCart(); } 
        else {
            let removedId = spCart[index].id;
            let isBump = spCart[index].isBump;
            spCart.splice(index, 1);
            if (isBump) {
                let cb = $('.sp-bump-checkbox').filter(function() { return $(this).data('id') == removedId; });
                if(cb.length) cb.prop('checked', false);
            }
            renderSpCart();
        }
    });
    $('#order-items-list').on('click', '.sp-item-remove', function() {
        let index = $(this).data('index');
        let removedId = spCart[index].id;
        let isBump = spCart[index].isBump;
        spCart.splice(index, 1);
        if (isBump) {
            let cb = $('.sp-bump-checkbox').filter(function() { return $(this).data('id') == removedId; });
            if(cb.length) cb.prop('checked', false);
        }
        renderSpCart();
    });

    $('#multi-product-checkout-form input[name="delivery_area"]').on('change', renderSpCart);

    $('#sp-bump-offer-container').on('change', '.sp-bump-checkbox', function() {
        let cb = $(this); let id = cb.data('id'); let price = cb.data('price');
        let name = cb.data('name'); let img = cb.data('img'); let isTriggered = cb.data('is-triggered');

        if (cb.is(':checked')) {
            let existing = spCart.find(item => item.id == id && item.isBump);
            if (!existing) {
                let finalPrice = (isTriggered && bumpFreeProduct) ? 0 : price;
                spCart.push({
                    id: id, name: "✓ " + name, price: finalPrice, img: img, qty: 1, isBump: true, isTriggeredBump: isTriggered
                });
            }
        } else {
            spCart = spCart.filter(item => !(item.id == id && item.isBump));
        }
        renderSpCart();
    });

    $('#multi-product-checkout-form').on('submit', function(e) {
        e.preventDefault();
        if (spCart.length === 0) return alert('Please select at least one product.');
        
        if (typeof fbq === 'function') {
            let itemIds = spCart.map(item => String(item.id));
            let totalValue = parseFloat($('#final-total').text().replace(/[^0-9.]/g, ''));
            fbq('track', 'InitiateCheckout', { content_ids: itemIds, content_type: 'product', value: totalValue, currency: 'BDT' });
        }

        let form = $(this); let btn = $('#sp-submit-btn'); let originalText = btn.html();
        btn.prop('disabled', true).html('Processing... <span class="sp-btn-price" style="animation:pulse 1s infinite">Please wait</span>');

        $.post((typeof fmb_vars !== 'undefined' ? fmb_vars.ajax_url : '<?php echo admin_url("admin-ajax.php"); ?>'), {
            action: 'fmb_multi_product_order', data: form.serialize()
        }, function(res) {
            if (res.success && res.data && res.data.redirect_url) {
                btn.html('✓ Order Confirmed! Redirecting...');
                window.location.replace(res.data.redirect_url);
            } else {
                alert(res.data.message || 'Something went wrong. Please try again.');
                btn.prop('disabled', false).html(originalText);
            }
        }).fail(function() {
            alert('Server error. Please try again.');
            btn.prop('disabled', false).html(originalText);
        });
    });

    renderSpCart();
});
</script>

<?php endwhile; get_footer(); ?>
