<?php
/**
 * Dynamic Product Combo Offer Management System
 * Adds "কম্বো অফার" Submenu under WooCommerce Products in WP Admin.
 */

if (!defined('ABSPATH')) {
    exit;
}

class FMB_Combo_Manager {

    public static function init() {
        add_action('init', array(__CLASS__, 'register_cpt'));
        add_action('add_meta_boxes', array(__CLASS__, 'add_meta_boxes'));
        add_action('save_post_fmb_combo_offer', array(__CLASS__, 'save_meta'));
        add_filter('manage_fmb_combo_offer_posts_columns', array(__CLASS__, 'custom_columns'));
        add_action('manage_fmb_combo_offer_posts_custom_column', array(__CLASS__, 'custom_column_content'), 10, 2);
    }

    /**
     * Register Custom Post Type under Products menu
     */
    public static function register_cpt() {
        $labels = array(
            'name'               => 'কম্বো অফার সমূহ',
            'singular_name'      => 'কম্বো অফার',
            'menu_name'          => 'কম্বো অফার',
            'add_new'            => 'নতুন কম্বো তৈরি করুন',
            'add_new_item'       => 'নতুন কম্বো অফার যোগ করুন',
            'edit_item'          => 'কম্বো অফার এডিট করুন',
            'new_item'           => 'নতুন কম্বো',
            'view_item'          => 'কম্বো দেখুন',
            'search_items'       => 'কম্বো খুঁজুন',
            'not_found'          => 'কোনো কম্বো পাওয়া যায়নি',
            'not_found_in_trash' => 'ট্র্যাশে কোনো কম্বো নেই',
        );

        $args = array(
            'labels'             => $labels,
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => 'edit.php?post_type=product', // Submenu under WooCommerce Products
            'query_var'          => true,
            'rewrite'            => array('slug' => 'combo-offer'),
            'capability_type'    => 'post',
            'has_archive'        => true,
            'hierarchical'       => false,
            'menu_position'      => 10,
            'supports'           => array('title', 'thumbnail'),
        );

        register_post_type('fmb_combo_offer', $args);
    }

    /**
     * Add Meta Boxes for Combo Customization
     */
    public static function add_meta_boxes() {
        add_meta_box(
            'fmb_combo_details_meta',
            '🔥 কম্বো অফার কাস্টমাইজেশন ও প্রডাক্ট সিলেকশন',
            array(__CLASS__, 'render_meta_box'),
            'fmb_combo_offer',
            'normal',
            'high'
        );
    }

    /**
     * Render Admin Meta Box UI
     */
    public static function render_meta_box($post) {
        wp_nonce_field('fmb_combo_meta_save', 'fmb_combo_meta_nonce');

        $selected_products = get_post_meta($post->ID, '_fmb_combo_product_ids', true) ?: array();
        if (!is_array($selected_products)) {
            $selected_products = array_filter(explode(',', $selected_products));
        }
        $product_qtys    = get_post_meta($post->ID, '_fmb_combo_product_qtys', true) ?: array();
        $free_gift_id    = intval(get_post_meta($post->ID, '_fmb_combo_free_gift_id', true) ?: 0);
        $free_gift_name  = get_post_meta($post->ID, '_fmb_combo_free_gift_name', true) ?: '';
        $free_gift_val   = get_post_meta($post->ID, '_fmb_combo_free_gift_val', true) ?: '';

        $combo_price    = get_post_meta($post->ID, '_fmb_combo_price', true) ?: '';
        $combo_badge    = get_post_meta($post->ID, '_fmb_combo_badge', true) ?: '🔥 মেগা কম্বো অফার - সাশ্রয়ী বান্ডেল';
        $combo_subtitle = get_post_meta($post->ID, '_fmb_combo_subtitle', true) ?: '৩টি সেরা প্রোডাক্ট একসাথে অর্ডার করুন এবং পান বিশেষ ছাড়!';
        $free_shipping  = get_post_meta($post->ID, '_fmb_combo_free_shipping', true) ?: 'no';
        $enable_timer   = get_post_meta($post->ID, '_fmb_combo_enable_timer', true) ?: 'yes';
        $timer_hours    = get_post_meta($post->ID, '_fmb_combo_timer_hours', true) ?: '24';

        // Fetch all WooCommerce products
        $wc_products = get_posts(array(
            'post_type'      => 'product',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'orderby'        => 'title',
            'order'          => 'ASC',
        ));
        ?>
        <style>
            .fmb-admin-combo-box { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
            .fmb-admin-field { margin-bottom: 20px; }
            .fmb-admin-field label { display: block; font-weight: 700; margin-bottom: 6px; font-size: 14px; color: #1d2327; }
            .fmb-admin-field input[type="text"], .fmb-admin-field input[type="number"], .fmb-admin-field select, .fmb-admin-field textarea { width: 100%; padding: 8px 12px; border: 1px solid #ccd0d4; border-radius: 6px; font-size: 14px; }
            .fmb-prod-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 12px; max-height: 360px; overflow-y: auto; padding: 12px; background: #f8f9fa; border: 1px solid #e2e8f0; border-radius: 8px; }
            .fmb-prod-item { display: flex; align-items: center; gap: 8px; background: #fff; padding: 8px 10px; border-radius: 6px; border: 1px solid #e2e8f0; transition: all 0.2s; }
            .fmb-prod-item:hover { border-color: #2563eb; }
            .fmb-prod-item input[type="checkbox"] { margin: 0; }
            .fmb-prod-thumb { width: 36px; height: 36px; object-fit: cover; border-radius: 4px; background: #f1f5f9; }
            .fmb-prod-info { flex: 1; min-width: 0; }
            .fmb-prod-name { font-size: 12px; font-weight: 600; color: #1e293b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
            .fmb-prod-price { font-size: 11px; color: #2563eb; font-weight: bold; }
            .fmb-qty-input { width: 50px !important; padding: 2px 6px !important; text-align: center; font-size: 12px !important; font-weight: bold; border-radius: 4px !important; border: 1px solid #cbd5e1 !important; }
        </style>

        <div class="fmb-admin-combo-box">
            
            <!-- Live Calculation Preview Card -->
            <div style="background: #0f172a; color: #fff; padding: 16px 20px; border-radius: 12px; margin-bottom: 20px; box-shadow: 0 4px 12px rgba(0,0,0,0.15);">
                <div style="font-size: 14px; font-weight: bold; color: #93c5fd; margin-bottom: 12px; display: flex; justify-content: space-between; align-items: center;">
                    <span>📊 কম্বো অফার লাইভ প্রাইস হিসাব (Live Admin Summary)</span>
                    <span style="font-size: 11px; background: #1e293b; padding: 2px 8px; border-radius: 12px; color: #cbd5e1;">অটো রিয়েল-টাইম হিসাব</span>
                </div>
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; text-align: center;">
                    <div style="background: rgba(255,255,255,0.06); padding: 10px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.1);">
                        <span style="display: block; font-size: 11px; color: #94a3b8; font-weight: 600; margin-bottom: 2px;">সিলেক্টেড রেগুলার মোট:</span>
                        <strong id="fmb-preview-reg-total" style="font-size: 20px; color: #f8fafc;">0৳</strong>
                    </div>
                    <div style="background: rgba(255,255,255,0.06); padding: 10px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.1);">
                        <span style="display: block; font-size: 11px; color: #94a3b8; font-weight: 600; margin-bottom: 2px;">কম্বো অফার প্রাইস:</span>
                        <strong id="fmb-preview-offer-price" style="font-size: 20px; color: #60a5fa;">0৳</strong>
                    </div>
                    <div style="background: rgba(255,255,255,0.06); padding: 10px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.1);">
                        <span style="display: block; font-size: 11px; color: #94a3b8; font-weight: 600; margin-bottom: 2px;">কাস্টমারের মোট সেভিংস:</span>
                        <strong id="fmb-preview-savings" style="font-size: 20px; color: #4ade80;">0৳</strong>
                    </div>
                </div>
            </div>

            <div class="fmb-admin-field">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
                    <label style="margin: 0;">১. কম্বো অফারে অন্তর্ভুক্ত প্রডাক্ট নির্বাচন ও পরিমাণ (Quantity) সেট করুন:</label>
                    <span id="fmb-selected-count-badge" style="background: #2563eb; color: #fff; font-size: 11px; padding: 2px 10px; border-radius: 12px; font-weight: bold;">0টি প্রডাক্ট সিলেক্টেড</span>
                </div>

                <!-- Instant Search & Quick Filter Bar -->
                <div style="display: flex; gap: 8px; margin-bottom: 10px;">
                    <input type="text" id="fmb-admin-prod-search" placeholder="🔍 প্রডাক্টের নাম, আইডি (ID) বা SKU লিখে সার্চ করুন..." style="flex: 1; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px;">
                    <button type="button" id="fmb-filter-all-btn" style="padding: 6px 12px; background: #2563eb; color: #fff; border: none; border-radius: 6px; font-size: 12px; font-weight: bold; cursor: pointer;">সব প্রডাক্ট</button>
                    <button type="button" id="fmb-filter-selected-btn" style="padding: 6px 12px; background: #e2e8f0; color: #1e293b; border: none; border-radius: 6px; font-size: 12px; font-weight: bold; cursor: pointer;">শুধুমাত্র সিলেক্টেড</button>
                </div>

                <div class="fmb-prod-grid" id="fmb-admin-prod-grid-container">
                    <?php if (!empty($wc_products)) : ?>
                        <?php foreach ($wc_products as $prod_post) : 
                            $prod_id = $prod_post->ID;
                            $_prod = wc_get_product($prod_id);
                            $price = $_prod ? ($_prod->get_regular_price() ?: $_prod->get_price()) : 0;
                            $sku   = $_prod ? $_prod->get_sku() : '';
                            $thumb = get_the_post_thumbnail_url($prod_id, 'thumbnail') ?: wc_placeholder_img_src();
                            $checked = in_array($prod_id, $selected_products) ? 'checked' : '';
                            $item_qty = isset($product_qtys[$prod_id]) ? intval($product_qtys[$prod_id]) : 1;
                        ?>
                            <div class="fmb-prod-item" data-name="<?php echo esc_attr(mb_strtolower($prod_post->post_title)); ?>" data-id="<?php echo $prod_id; ?>" data-sku="<?php echo esc_attr(mb_strtolower($sku)); ?>">
                                <input type="checkbox" class="fmb-prod-checkbox" name="fmb_combo_product_ids[]" value="<?php echo $prod_id; ?>" data-price="<?php echo $price; ?>" <?php echo $checked; ?>>
                                <img src="<?php echo esc_url($thumb); ?>" class="fmb-prod-thumb" alt="">
                                <div class="fmb-prod-info">
                                    <div class="fmb-prod-name" title="<?php echo esc_attr($prod_post->post_title); ?>"><?php echo esc_html($prod_post->post_title); ?></div>
                                    <div class="fmb-prod-price">
                                        <?php echo number_format($price); ?>৳
                                        <?php if (!empty($sku)) : ?>
                                            <span style="font-size: 10px; color: #64748b; font-weight: normal; margin-left: 4px;">(SKU: <?php echo esc_html($sku); ?>)</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div style="display: flex; align-items: center; gap: 4px;" title="প্রোডাক্টের পরিমাণ (Qty)">
                                    <span style="font-size: 11px; color: #64748b; font-weight: bold;">Qty:</span>
                                    <input type="number" name="fmb_combo_product_qtys[<?php echo $prod_id; ?>]" value="<?php echo $item_qty; ?>" min="1" max="99" class="fmb-qty-input fmb-prod-qty-field">
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <p style="color: #64748b;">কোনো WooCommerce প্রডাক্ট পাওয়া যায়নি। প্রথমে কিছু প্রডাক্ট যুক্ত করুন।</p>
                    <?php endif; ?>
                </div>
                <p class="description" style="margin-top: 6px; font-size: 12px; color: #64748b;">উপরে প্রডাক্টের নাম, আইডি (ID) বা SKU লিখে সার্চ করে টিক দিন এবং প্রয়োজনীয় পরিমাণ (Qty) সেট করুন।</p>
            </div>

            <!-- Free Product / Gift Box -->
            <div style="background: #eff6ff; border: 1px dashed #3b82f6; padding: 16px; border-radius: 10px; margin-bottom: 20px;">
                <label style="color: #1d4ed8; font-size: 15px; margin-bottom: 10px;">🎁 ফ্রি প্রডাক্ট / ফ্রি গিফট যুক্ত করুন (Optional):</label>
                
                <div style="margin-bottom: 12px;">
                    <span style="font-size: 12px; font-weight: bold; color: #334155; display: block; margin-bottom: 4px;">WooCommerce প্রডাক্ট তালিকা থেকে ফ্রি উপহার সিলেক্ট করুন:</span>
                    <select name="fmb_combo_free_gift_id" id="fmb_combo_free_gift_id_select">
                        <option value="0" data-name="" data-price="0">-- কোনো ফ্রি প্রডাক্ট নেই (অথবা নিচে কাস্টম উপহার লিখুন) --</option>
                        <?php foreach ($wc_products as $gp) : 
                            $_gprod = wc_get_product($gp->ID);
                            $gprice = $_gprod ? ($_gprod->get_regular_price() ?: $_gprod->get_price()) : 0;
                            $gsku   = $_gprod ? $_gprod->get_sku() : '';
                            $gselected = ($gp->ID == $free_gift_id) ? 'selected' : '';
                        ?>
                            <option value="<?php echo $gp->ID; ?>" data-name="<?php echo esc_attr($gp->post_title); ?>" data-price="<?php echo $gprice; ?>" <?php echo $gselected; ?>>
                                <?php echo esc_html($gp->post_title); ?> <?php echo !empty($gsku) ? '(SKU: ' . esc_html($gsku) . ')' : ''; ?> - <?php echo number_format($gprice); ?>৳
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 12px;">
                    <div>
                        <span style="font-size: 12px; font-weight: bold; color: #334155; display: block; margin-bottom: 4px;">উপহারের নাম (Free Gift Name):</span>
                        <input type="text" name="fmb_combo_free_gift_name" id="fmb_combo_free_gift_name_input" value="<?php echo esc_attr($free_gift_name); ?>" placeholder="উদাহরণ: প্রিমিয়াম ওয়াটারপ্রুফ র কব্জি ঘড়ি">
                    </div>
                    <div>
                        <span style="font-size: 12px; font-weight: bold; color: #334155; display: block; margin-bottom: 4px;">উপহারের আনুমানিক মূল্য (৳):</span>
                        <input type="number" name="fmb_combo_free_gift_val" id="fmb_combo_free_gift_val_input" value="<?php echo esc_attr($free_gift_val); ?>" placeholder="উদাহরণ: 350">
                    </div>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div class="fmb-admin-field">
                    <label>২. বিশেষ কম্বো বান্ডেল অফার প্রাইস (৳):</label>
                    <input type="number" name="fmb_combo_price" id="fmb_combo_price_input" value="<?php echo esc_attr($combo_price); ?>" placeholder="উদাহরণ: 2490">
                    <p class="description" style="font-size: 12px; color: #64748b;">সবগুলো পণ্য একসাথে নিলে কাস্টমার যে ডিসকাউন্টেড প্রাইস দেবেন।</p>
                </div>

                <div class="fmb-admin-field">
                    <label>৩. কম্বো ব্যাজ / অফার ট্যাগলাইন:</label>
                    <input type="text" name="fmb_combo_badge" value="<?php echo esc_attr($combo_badge); ?>" placeholder="উদাহরণ: 🔥 মেগা কম্বো অফার - ৪০% ছাড়">
                </div>
            </div>

            <div class="fmb-admin-field">
                <label>৪. কম্বো ডেসক্রিপশন / সাব-টাইটেল:</label>
                <textarea name="fmb_combo_subtitle" rows="2"><?php echo esc_textarea($combo_subtitle); ?></textarea>
            </div>

            <!-- Countdown Timer Options -->
            <div style="background: #fff7ed; border: 1px solid #ffedd5; padding: 14px 16px; border-radius: 8px; margin-bottom: 20px;">
                <label style="color: #c2410c; font-size: 14px; margin-bottom: 8px; display: block; font-weight: bold;">⏳ কম্বো অফার কাউন্টডাউন টাইমার (Countdown Timer):</label>
                <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 16px;">
                    <label style="font-size: 13px; font-weight: bold; cursor: pointer;">
                        <input type="checkbox" name="fmb_combo_enable_timer" value="yes" <?php checked($enable_timer, 'yes'); ?>>
                        কাউন্টডাউন টাইমার অন করুন (Show Countdown Timer)
                    </label>
                    <div style="display: flex; align-items: center; gap: 6px;">
                        <span style="font-size: 12px; color: #475569;">টাইমার স্থায়িত্ব (Hours):</span>
                        <input type="number" name="fmb_combo_timer_hours" value="<?php echo esc_attr($timer_hours); ?>" placeholder="24" min="1" max="168" style="width: 75px !important; padding: 4px 8px; font-size: 13px;">
                        <span style="font-size: 12px; color: #64748b;">ঘণ্টা</span>
                    </div>
                </div>
            </div>

            <div class="fmb-admin-field">
                <label>
                    <input type="checkbox" name="fmb_combo_free_shipping" value="yes" <?php checked($free_shipping, 'yes'); ?>>
                    এই কম্বো অর্ডারে ফ্রি ডেলিভারি অফার প্রজোয্য
                </label>
            </div>

        </div>

        <script>
        document.addEventListener("DOMContentLoaded", function() {
            var giftSelect = document.getElementById('fmb_combo_free_gift_id_select');
            var giftNameInput = document.getElementById('fmb_combo_free_gift_name_input');
            var giftValInput = document.getElementById('fmb_combo_free_gift_val_input');
            var offerPriceInput = document.getElementById('fmb_combo_price_input');

            var previewReg = document.getElementById('fmb-preview-reg-total');
            var previewOffer = document.getElementById('fmb-preview-offer-price');
            var previewSavings = document.getElementById('fmb-preview-savings');
            var selectedBadge = document.getElementById('fmb-selected-count-badge');

            var searchInput = document.getElementById('fmb-admin-prod-search');
            var filterAllBtn = document.getElementById('fmb-filter-all-btn');
            var filterSelBtn = document.getElementById('fmb-filter-selected-btn');

            function updateAdminComboPreview() {
                var regTotal = 0;
                var selectedCount = 0;
                var items = document.querySelectorAll('.fmb-prod-item');

                items.forEach(function(item) {
                    var cb = item.querySelector('.fmb-prod-checkbox');
                    var qtyInput = item.querySelector('.fmb-prod-qty-field');
                    if (cb && cb.checked) {
                        selectedCount++;
                        var p = parseFloat(cb.getAttribute('data-price')) || 0;
                        var q = parseInt(qtyInput ? qtyInput.value : 1, 10) || 1;
                        regTotal += (p * q);
                    }
                });

                var giftVal = parseFloat(giftValInput ? giftValInput.value : 0) || 0;
                regTotal += giftVal;

                var offerPrice = parseFloat(offerPriceInput ? offerPriceInput.value : 0) || 0;
                var savings = Math.max(0, regTotal - offerPrice);

                if (previewReg) previewReg.textContent = Math.round(regTotal).toLocaleString() + '৳';
                if (previewOffer) previewOffer.textContent = Math.round(offerPrice).toLocaleString() + '৳';
                if (previewSavings) previewSavings.textContent = (savings > 0 ? '🎉 ' : '') + Math.round(savings).toLocaleString() + '৳';
                if (selectedBadge) selectedBadge.textContent = selectedCount + 'টি প্রডাক্ট সিলেক্টেড';
            }

            // ── Live Instant Product Search (Name, ID, or SKU) ──
            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    var q = searchInput.value.toLowerCase().trim();
                    var items = document.querySelectorAll('.fmb-prod-item');
                    items.forEach(function(item) {
                        var name = item.getAttribute('data-name') || '';
                        var id = item.getAttribute('data-id') || '';
                        var sku = item.getAttribute('data-sku') || '';
                        if (name.indexOf(q) !== -1 || id.indexOf(q) !== -1 || sku.indexOf(q) !== -1) {
                            item.style.display = 'flex';
                        } else {
                            item.style.display = 'none';
                        }
                    });
                });
            }

            // ── Filter Toggles ──
            if (filterAllBtn && filterSelBtn) {
                filterAllBtn.addEventListener('click', function() {
                    filterAllBtn.style.background = '#2563eb';
                    filterAllBtn.style.color = '#fff';
                    filterSelBtn.style.background = '#e2e8f0';
                    filterSelBtn.style.color = '#1e293b';

                    if (searchInput) searchInput.value = '';
                    document.querySelectorAll('.fmb-prod-item').forEach(function(item) {
                        item.style.display = 'flex';
                    });
                });

                filterSelBtn.addEventListener('click', function() {
                    filterSelBtn.style.background = '#2563eb';
                    filterSelBtn.style.color = '#fff';
                    filterAllBtn.style.background = '#e2e8f0';
                    filterAllBtn.style.color = '#1e293b';

                    document.querySelectorAll('.fmb-prod-item').forEach(function(item) {
                        var cb = item.querySelector('.fmb-prod-checkbox');
                        if (cb && cb.checked) {
                            item.style.display = 'flex';
                        } else {
                            item.style.display = 'none';
                        }
                    });
                });
            }

            if (giftSelect) {
                giftSelect.addEventListener('change', function() {
                    var selected = giftSelect.options[giftSelect.selectedIndex];
                    if (selected && selected.value !== '0') {
                        var name = selected.getAttribute('data-name');
                        var price = selected.getAttribute('data-price');
                        if (giftNameInput) giftNameInput.value = name;
                        if (giftValInput) giftValInput.value = price;
                    }
                    updateAdminComboPreview();
                });
            }

            document.querySelectorAll('.fmb-prod-checkbox, .fmb-prod-qty-field').forEach(function(el) {
                el.addEventListener('change', updateAdminComboPreview);
                el.addEventListener('input', updateAdminComboPreview);
            });

            if (offerPriceInput) offerPriceInput.addEventListener('input', updateAdminComboPreview);
            if (giftValInput) giftValInput.addEventListener('input', updateAdminComboPreview);

            // Initial Calculation
            updateAdminComboPreview();
        });
        </script>
        <?php
    }

    /**
     * Save Meta Box Data
     */
    public static function save_meta($post_id) {
        if (!isset($_POST['fmb_combo_meta_nonce']) || !wp_verify_nonce($_POST['fmb_combo_meta_nonce'], 'fmb_combo_meta_save')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $product_ids = isset($_POST['fmb_combo_product_ids']) && is_array($_POST['fmb_combo_product_ids']) ? array_map('intval', $_POST['fmb_combo_product_ids']) : array();
        update_post_meta($post_id, '_fmb_combo_product_ids', $product_ids);

        $product_qtys = isset($_POST['fmb_combo_product_qtys']) && is_array($_POST['fmb_combo_product_qtys']) ? array_map('intval', $_POST['fmb_combo_product_qtys']) : array();
        update_post_meta($post_id, '_fmb_combo_product_qtys', $product_qtys);

        $free_gift_id = isset($_POST['fmb_combo_free_gift_id']) ? intval($_POST['fmb_combo_free_gift_id']) : 0;
        update_post_meta($post_id, '_fmb_combo_free_gift_id', $free_gift_id);

        if (isset($_POST['fmb_combo_free_gift_name'])) {
            update_post_meta($post_id, '_fmb_combo_free_gift_name', sanitize_text_field($_POST['fmb_combo_free_gift_name']));
        }
        if (isset($_POST['fmb_combo_free_gift_val'])) {
            update_post_meta($post_id, '_fmb_combo_free_gift_val', sanitize_text_field($_POST['fmb_combo_free_gift_val']));
        }

        if (isset($_POST['fmb_combo_price'])) {
            update_post_meta($post_id, '_fmb_combo_price', sanitize_text_field($_POST['fmb_combo_price']));
        }
        if (isset($_POST['fmb_combo_badge'])) {
            update_post_meta($post_id, '_fmb_combo_badge', sanitize_text_field($_POST['fmb_combo_badge']));
        }
        if (isset($_POST['fmb_combo_subtitle'])) {
            update_post_meta($post_id, '_fmb_combo_subtitle', sanitize_textarea_field($_POST['fmb_combo_subtitle']));
        }
        $free_ship = isset($_POST['fmb_combo_free_shipping']) ? 'yes' : 'no';
        update_post_meta($post_id, '_fmb_combo_free_shipping', $free_ship);

        $enable_timer = isset($_POST['fmb_combo_enable_timer']) ? 'yes' : 'no';
        update_post_meta($post_id, '_fmb_combo_enable_timer', $enable_timer);

        if (isset($_POST['fmb_combo_timer_hours'])) {
            update_post_meta($post_id, '_fmb_combo_timer_hours', max(1, intval($_POST['fmb_combo_timer_hours'])));
        }
    }

    /**
     * Custom List Columns
     */
    public static function custom_columns($columns) {
        $new_cols = array();
        $new_cols['cb'] = $columns['cb'];
        $new_cols['title'] = 'কম্বো শিরোনাম';
        $new_cols['combo_products'] = 'অন্তর্ভুক্ত প্রডাক্ট সমূহ';
        $new_cols['combo_price'] = 'কম্বো অফার মূল্য';
        $new_cols['combo_regular'] = 'রেগুলার মোট মূল্য';
        $new_cols['combo_savings'] = 'কাস্টমার ছাড় (Savings)';
        $new_cols['date'] = $columns['date'];
        return $new_cols;
    }

    public static function custom_column_content($column, $post_id) {
        $product_ids = get_post_meta($post_id, '_fmb_combo_product_ids', true) ?: array();
        if (!is_array($product_ids)) {
            $product_ids = array_filter(explode(',', $product_ids));
        }

        $combo_price = floatval(get_post_meta($post_id, '_fmb_combo_price', true));
        $regular_total = 0;
        $prod_titles = array();

        foreach ($product_ids as $pid) {
            $prod = wc_get_product($pid);
            if ($prod) {
                $regular_total += floatval($prod->get_regular_price() ?: $prod->get_price());
                $prod_titles[] = $prod->get_name();
            }
        }

        $savings = max(0, $regular_total - $combo_price);

        switch ($column) {
            case 'combo_products':
                if (!empty($prod_titles)) {
                    echo '<strong>' . count($prod_titles) . ' টি প্রডাক্ট:</strong><br><span style="color:#64748b; font-size:12px;">' . esc_html(implode(', ', $prod_titles)) . '</span>';
                } else {
                    echo '<span style="color:#ef4444;">কোনো প্রডাক্ট সিলেক্ট করা হয়নি</span>';
                }
                break;
            case 'combo_price':
                echo '<strong style="color:#2563eb; font-size:14px;">' . number_format($combo_price) . '৳</strong>';
                break;
            case 'combo_regular':
                echo '<span style="text-decoration:line-through; color:#94a3b8;">' . number_format($regular_total) . '৳</span>';
                break;
            case 'combo_savings':
                if ($savings > 0) {
                    echo '<span style="background:#dcfce7; color:#166534; padding:2px 8px; border-radius:12px; font-weight:bold; font-size:12px;">' . number_format($savings) . '৳ সেভিং</span>';
                } else {
                    echo '-';
                }
                break;
        }
    }
}

FMB_Combo_Manager::init();
