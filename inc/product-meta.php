<?php
// ১. প্রডাক্ট এডিট পেজে কাস্টম ট্যাব তৈরি
add_filter('woocommerce_product_data_tabs', 'fmb_add_custom_product_tabs');
function fmb_add_custom_product_tabs($tabs) {
    $tabs['fmb_landing_data'] = array(
        'label'    => __('Landing Page Data', 'fmb-store'),
        'target'   => 'fmb_landing_product_data',
        'class'    => array('show_if_simple', 'show_if_variable'),
    );
    return $tabs;
}

// ২. ট্যাবের ভেতরের কন্টেন্ট (ইনপুট ফিল্ড)
add_action('woocommerce_product_data_panels', 'fmb_custom_product_data_fields');
function fmb_custom_product_data_fields() {
    global $post;
    
    // ডাটা রিট্রিভ
    $steps_title = get_post_meta($post->ID, '_fmb_steps_title', true) ?: 'ব্যবহারের নিয়মাবলী';
    $steps = get_post_meta($post->ID, '_fmb_usage_steps', true);
    
    $cards_title = get_post_meta($post->ID, '_fmb_cards_title', true) ?: 'কেন এটি সেরা?';
    $cards = get_post_meta($post->ID, '_fmb_info_cards', true); 
    
    $upsell_title = get_post_meta($post->ID, '_fmb_upsell_title', true) ?: 'এর সাথে আরও যা যা নিতে পারেন';
    $upsell_data = get_post_meta($post->ID, '_fmb_upsells_data', true);
    if (!is_array($upsell_data)) $upsell_data = [];
    $old_upsell_ids = get_post_meta($post->ID, '_fmb_upsell_ids', true);
    if (empty($upsell_data) && !empty($old_upsell_ids)) {
        $old_ids = array_map('trim', explode(',', $old_upsell_ids));
        foreach($old_ids as $id) {
            if($id) $upsell_data[] = ['id' => $id, 'logic_qty' => '', 'logic_type' => '', 'logic_value' => ''];
        }
    }

    $ob_data = get_post_meta($post->ID, '_fmb_order_bumps_data', true);
    if (!is_array($ob_data)) $ob_data = [];

    echo '<div id="fmb_landing_product_data" class="panel woocommerce_options_panel hidden">';
    ?>
    <style>
        #fmb_landing_product_data {
            padding: 20px 24px;
            background: #f8fafc;
            color: #1e293b;
        }
        #fmb_landing_product_data label {
            float: none !important;
            width: auto !important;
            margin: 0 !important;
            padding: 0 !important;
            text-align: left !important;
            display: block !important;
            clear: none !important;
        }
        #fmb_landing_product_data .fmb-variant-grid {
            display: grid !important;
            grid-template-columns: repeat(4, 1fr) !important;
            gap: 12px !important;
            margin-top: 10px !important;
        }
        @media (max-width: 1200px) {
            #fmb_landing_product_data .fmb-variant-grid {
                grid-template-columns: repeat(2, 1fr) !important;
            }
        }
        #fmb_landing_product_data .fmb-variant-card {
            float: none !important;
            width: 100% !important;
            margin: 0 !important;
            padding: 14px 16px !important;
            display: flex !important;
            flex-direction: column !important;
            gap: 6px !important;
            border: 2px solid #cbd5e1 !important;
            border-radius: 12px !important;
            background: #ffffff !important;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04) !important;
            cursor: pointer !important;
            transition: all 0.2s ease !important;
            box-sizing: border-box !important;
        }
        #fmb_landing_product_data .fmb-variant-card:hover {
            border-color: #94a3b8 !important;
            box-shadow: 0 4px 10px rgba(0,0,0,0.08) !important;
        }
        #fmb_landing_product_data .fmb-variant-card.is-active {
            border-color: #2563eb !important;
            background: #f0fdf4 !important;
            box-shadow: 0 4px 12px rgba(37,99,235,0.12) !important;
        }
        #fmb_landing_product_data .fmb-variant-card input[type="radio"] {
            float: none !important;
            margin: 0 8px 0 0 !important;
            display: inline-block !important;
            width: 18px !important;
            height: 18px !important;
            vertical-align: middle !important;
            flex-shrink: 0 !important;
        }
        #fmb_landing_product_data .fmb-section-box {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 24px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
        }
        #fmb_landing_product_data .fmb-section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-bottom: 14px;
            margin-bottom: 18px;
            border-bottom: 2px solid #f1f5f9;
        }
        #fmb_landing_product_data .fmb-section-header h3 {
            margin: 0;
            font-size: 15px;
            font-weight: 700;
            color: #0f172a;
            display: flex;
            align-items: center;
            gap: 8px;
            background: transparent;
            padding: 0;
        }
        #fmb_landing_product_data .fmb-repeater-item {
            background: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 14px;
            position: relative;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
            transition: border-color 0.2s;
        }
        #fmb_landing_product_data .fmb-repeater-item:hover {
            border-color: #94a3b8;
        }
        #fmb_landing_product_data .fmb-item-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
            padding-bottom: 8px;
            border-bottom: 1px dashed #e2e8f0;
        }
        #fmb_landing_product_data .fmb-item-title {
            font-size: 13px;
            font-weight: 700;
            color: #334155;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        #fmb_landing_product_data .fmb-remove-btn {
            background: #fee2e2;
            color: #ef4444;
            border: 1px solid #fecaca;
            border-radius: 6px;
            padding: 4px 10px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            transition: all 0.2s;
        }
        #fmb_landing_product_data .fmb-remove-btn:hover {
            background: #ef4444;
            color: #ffffff;
            border-color: #dc2626;
        }
        #fmb_landing_product_data .fmb-grid {
            display: grid;
            gap: 12px;
            margin-top: 10px;
        }
        #fmb_landing_product_data .fmb-grid-3 {
            grid-template-columns: 1fr 1.2fr 1fr;
        }
        #fmb_landing_product_data .fmb-grid-2 {
            grid-template-columns: 1fr 1fr;
        }
        #fmb_landing_product_data .fmb-field-group {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        #fmb_landing_product_data .fmb-field-group label {
            font-size: 12px;
            font-weight: 600;
            color: #475569;
            float: none !important;
            width: auto !important;
            margin: 0 !important;
            text-align: left;
        }
        #fmb_landing_product_data input[type="text"],
        #fmb_landing_product_data input[type="number"],
        #fmb_landing_product_data select,
        #fmb_landing_product_data textarea {
            width: 100% !important;
            max-width: 100% !important;
            float: none !important;
            margin: 0 !important;
            padding: 8px 12px !important;
            border: 1px solid #cbd5e1 !important;
            border-radius: 6px !important;
            background: #ffffff !important;
            font-size: 13px !important;
            color: #0f172a !important;
            line-height: 1.4 !important;
            height: auto !important;
            box-shadow: none !important;
        }
        #fmb_landing_product_data input:focus,
        #fmb_landing_product_data select:focus,
        #fmb_landing_product_data textarea:focus {
            border-color: #3b82f6 !important;
            outline: 2px solid #bfdbfe !important;
        }
        #fmb_landing_product_data .select2-container {
            width: 100% !important;
            float: none !important;
        }
        #fmb_landing_product_data .select2-container .select2-selection {
            min-height: 38px !important;
            border: 1px solid #cbd5e1 !important;
            border-radius: 6px !important;
            padding: 4px 8px !important;
        }
        #fmb_landing_product_data .fmb-add-btn {
            background: #2563eb;
            color: #ffffff;
            border: 1px solid #1d4ed8;
            border-radius: 6px;
            padding: 8px 16px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-top: 12px;
            transition: all 0.2s;
        }
        #fmb_landing_product_data .fmb-add-btn:hover {
            background: #1d4ed8;
            color: #ffffff;
        }
        #fmb_landing_product_data .fmb-header-input-wrap {
            margin-bottom: 15px;
        }
        #fmb_landing_product_data .fmb-header-input-wrap label {
            font-size: 13px;
            font-weight: 600;
            color: #334155;
            margin-bottom: 6px;
            display: block;
        }
    </style>
    <script>
    jQuery(document).ready(function($){
        // Steps Repeater
        $('#fmb_add_step').click(function(e){
            e.preventDefault();
            var html = '<div class="fmb-repeater-item">' +
                '<div class="fmb-item-top"><span class="fmb-item-title">📋 Step</span><button type="button" class="fmb-remove-btn">✕ Delete</button></div>' +
                '<div class="fmb-field-group">' +
                '<textarea name="_fmb_steps[]" placeholder="ধাপ বিবরণ লিখুন..." style="width:100%; height:55px;"></textarea>' +
                '</div></div>';
            $('#fmb_steps_wrapper').append(html);
        });

        // Cards Repeater
        $('#fmb_add_card').click(function(e){
            e.preventDefault();
            var html = '<div class="fmb-repeater-item">' +
                '<div class="fmb-item-top"><span class="fmb-item-title">🌟 Info Card</span><button type="button" class="fmb-remove-btn">✕ Delete</button></div>' +
                '<div class="fmb-field-group" style="margin-bottom:8px;"><label>Image URL</label><input type="text" name="_fmb_card_img[]" placeholder="https://..."></div>' +
                '<div class="fmb-field-group" style="margin-bottom:8px;"><label>Title</label><input type="text" name="_fmb_card_title[]" placeholder="কার্ডের শিরোনাম"></div>' +
                '<div class="fmb-field-group"><label>Description</label><textarea name="_fmb_card_desc[]" placeholder="সংক্ষিপ্ত বিবরণ..." style="height:55px;"></textarea></div>' +
                '</div>';
            $('#fmb_cards_wrapper').append(html);
        });

        // Remove Row
        $(document).on('click', '.fmb-remove-btn', function(e){
            e.preventDefault();
            $(this).closest('.fmb-repeater-item').remove();
        });

        // Upsells Repeater
        $('#fmb_add_upsell').click(function(e){
            e.preventDefault();
            var html = '<div class="fmb-repeater-item">' +
                '<div class="fmb-item-top"><span class="fmb-item-title">🛍️ Upsell Product</span><button type="button" class="fmb-remove-btn">✕ Delete</button></div>' +
                '<div class="fmb-field-group" style="margin-bottom:10px;">' +
                '<label>Product</label>' +
                '<select class="wc-product-search" name="_fmb_upsell_product_id[]" style="width:100%;" data-placeholder="Search product by name or ID..." data-action="woocommerce_json_search_products_and_variations"></select>' +
                '</div>' +
                '<div class="fmb-grid fmb-grid-3">' +
                '<div class="fmb-field-group"><label>Target Qty</label><input type="number" name="_fmb_upsell_logic_qty[]" placeholder="e.g. 2"></div>' +
                '<div class="fmb-field-group"><label>Discount / Reward Type</label><select name="_fmb_upsell_logic_type[]"><option value="">No Logic</option><option value="free_delivery">Free Delivery</option><option value="fixed">Fixed Discount (৳)</option><option value="percent">Percent Discount (%)</option></select></div>' +
                '<div class="fmb-field-group"><label>Reward Value</label><input type="number" name="_fmb_upsell_logic_value[]" placeholder="e.g. 50"></div>' +
                '</div>' +
                '</div>';
            $('#fmb_upsells_wrapper').append(html);
            $(document.body).trigger('wc-enhanced-select-init');
        });

        // Order Bump Repeater
        $('#fmb_add_order_bump').click(function(e){
            e.preventDefault();
            var html = '<div class="fmb-repeater-item">' +
                '<div class="fmb-item-top"><span class="fmb-item-title">⚡ Order Bump</span><button type="button" class="fmb-remove-btn">✕ Delete</button></div>' +
                '<div class="fmb-field-group" style="margin-bottom:10px;">' +
                '<label>Product</label>' +
                '<select class="wc-product-search" name="_fmb_ob_product_id[]" style="width:100%;" data-placeholder="Search product by name or ID..." data-action="woocommerce_json_search_products_and_variations"></select>' +
                '</div>' +
                '<div class="fmb-grid fmb-grid-2" style="margin-bottom:10px;">' +
                '<div class="fmb-field-group"><label>Custom Bump Title (Optional)</label><input type="text" name="_fmb_ob_title[]" placeholder="Default: Product Title"></div>' +
                '<div class="fmb-field-group"><label>Custom Price Override (Optional)</label><input type="text" name="_fmb_ob_price[]" placeholder="Default: Product Price"></div>' +
                '</div>' +
                '<div class="fmb-field-group" style="margin-bottom:10px;">' +
                '<label>Description / Offer Text</label>' +
                '<textarea name="_fmb_ob_desc[]" placeholder="অতিরিক্ত সুরক্ষায় বাড়তি নিয়ে রাখুন..." style="height:50px;"></textarea>' +
                '</div>' +
                '<div class="fmb-grid fmb-grid-3" style="margin-bottom:10px;">' +
                '<div class="fmb-field-group"><label>Target Qty</label><input type="number" name="_fmb_ob_logic_qty[]" placeholder="e.g. 2"></div>' +
                '<div class="fmb-field-group"><label>Discount / Reward Type</label><select name="_fmb_ob_logic_type[]"><option value="">No Logic</option><option value="free_delivery">Free Delivery</option><option value="fixed">Fixed Discount (৳)</option><option value="percent">Percent Discount (%)</option></select></div>' +
                '<div class="fmb-field-group"><label>Reward Value</label><input type="number" name="_fmb_ob_logic_value[]" placeholder="e.g. 50"></div>' +
                '</div>' +
                '<div class="fmb-grid fmb-grid-2">' +
                '<div class="fmb-field-group"><label>Card Background Color</label><input type="text" name="_fmb_ob_bg[]" placeholder="e.g. #fffbeb"></div>' +
                '<div class="fmb-field-group"><label>Card Border Color</label><input type="text" name="_fmb_ob_border[]" placeholder="e.g. #f59e0b"></div>' +
                '</div>' +
                '</div>';
            $('#fmb_order_bumps_wrapper').append(html);
            $(document.body).trigger('wc-enhanced-select-init');
        });
    });
    </script>
    <?php
    // --- 🎨 Single Product Design Variant Selector ---
    $selected_template = get_post_meta($post->ID, '_fmb_single_template_variant', true) ?: 'classic';
    echo '<div class="fmb-section-box" style="background: linear-gradient(135deg, #f0fdf4 0%, #dbeafe 100%); border: 1.5px solid #93c5fd;">';
    echo '<div class="fmb-section-header"><h3 style="color:#1e3a8a; font-size:15px;">🎨 Single Product Page Design Variant (ডিজাইন ভ্যারিয়েন্ট)</h3></div>';
    echo '<p style="font-size:12px; color:#475569; margin:2px 0 12px 0;">এই নির্দিষ্ট প্রোডাক্টটির জন্য কোন ধরণের সিঙ্গেল প্রোডাক্ট ল্যান্ডিং পেজ ডিজাইন ব্যবহার করতে চান তা সিলেক্ট করুন:</p>';
    echo '<div class="fmb-variant-grid">';
    
    $variants = [
        'classic' => [
            'title' => '🌟 Default (আগের অরিজিনাল ডিজাইন)',
            'desc' => 'সিঙ্গেল-প্রোডাক্ট পেজের আগের ডিফল্ট হাই-ট্রাস্ট লেআউট',
            'color' => '#2563eb'
        ],
        'dark_luxe' => [
            'title' => '👑 Modern Dark Luxe',
            'desc' => 'মডার্ন ডার্ক গ্রেডিয়েন্ট, গ্লাস ইফেক্ট ও লাক্সারি ভাইব',
            'color' => '#7c3aed'
        ],
        'fomo_deal' => [
            'title' => '🔥 Bold FOMO Deal',
            'desc' => 'কাউন্টডাউন টাইমার, স্টক প্রগ্রেস বার ও ফ্ল্যাশ ডিল',
            'color' => '#dc2626'
        ],
        'minimalist' => [
            'title' => '🌿 Clean Minimalist',
            'desc' => 'অ্যাপল স্টাইল ক্লিন টাইপোগ্রাফি ও টাইমলাইন স্টেপস',
            'color' => '#059669'
        ]
    ];

    foreach($variants as $v_key => $v_data) {
        $is_checked = ($selected_template === $v_key);
        $active_class = $is_checked ? 'is-active' : '';
        $border_color = $is_checked ? $v_data['color'] : '#cbd5e1';
        echo '<label class="fmb-variant-card '.esc_attr($active_class).'" style="border-color:'.esc_attr($border_color).' !important;">';
        echo '<div style="display:flex; align-items:center; gap:8px;">';
        echo '<input type="radio" name="_fmb_single_template_variant" value="'.esc_attr($v_key).'" '.($is_checked ? 'checked' : '').'>';
        echo '<strong style="color:#0f172a; font-size:13px;">'.esc_html($v_data['title']).'</strong>';
        echo '</div>';
        echo '<span style="font-size:11px; color:#64748b; line-height:1.3; margin-left:26px;">'.esc_html($v_data['desc']).'</span>';
        echo '</label>';
    }
    echo '</div>';
    echo '</div>';
    ?>
    <script>
    jQuery(document).ready(function($) {
        $(document).on('click', '.fmb-variant-card', function(e) {
            var $card = $(this);
            var $radio = $card.find('input[type="radio"]');
            $('.fmb-variant-card').removeClass('is-active').css('border-color', '#cbd5e1');
            $card.addClass('is-active').css('border-color', '#2563eb');
            $radio.prop('checked', true);
        });
        $('.fmb-variant-card input[type="radio"]').on('change', function() {
            $('.fmb-variant-card').removeClass('is-active').css('border-color', '#cbd5e1');
            $(this).closest('.fmb-variant-card').addClass('is-active').css('border-color', '#2563eb');
        });
    });
    </script>
    <?php

    // --- সেকশন ১: ব্যবহারের নিয়মাবলী ---
    echo '<div class="fmb-section-box">';
    echo '<div class="fmb-section-header"><h3>📋 সেকশন ১: ব্যবহারের নিয়মাবলী</h3></div>';
    echo '<div class="fmb-header-input-wrap">';
    echo '<label for="_fmb_steps_title">Section Title</label>';
    echo '<input type="text" id="_fmb_steps_title" name="_fmb_steps_title" value="' . esc_attr($steps_title) . '">';
    echo '</div>';
    
    echo '<div id="fmb_steps_wrapper">';
    if(!empty($steps) && is_array($steps)) {
        foreach($steps as $step) {
            if(empty($step)) continue;
            echo '<div class="fmb-repeater-item">';
            echo '<div class="fmb-item-top"><span class="fmb-item-title">📋 Step</span><button type="button" class="fmb-remove-btn">✕ Delete</button></div>';
            echo '<div class="fmb-field-group">';
            echo '<textarea name="_fmb_steps[]" style="width:100%; height:55px;">'.esc_textarea($step).'</textarea>';
            echo '</div></div>';
        }
    }
    echo '</div>';
    echo '<button type="button" class="fmb-add-btn" id="fmb_add_step">+ Add Step</button>';
    echo '</div>';


    // --- সেকশন ২: ইনফো কার্ড ---
    echo '<div class="fmb-section-box">';
    echo '<div class="fmb-section-header"><h3>🌟 সেকশন ২: ইনফো কার্ড</h3></div>';
    echo '<div class="fmb-header-input-wrap">';
    echo '<label for="_fmb_cards_title">Section Title</label>';
    echo '<input type="text" id="_fmb_cards_title" name="_fmb_cards_title" value="' . esc_attr($cards_title) . '">';
    echo '</div>';

    echo '<div id="fmb_cards_wrapper">';
    if(!empty($cards) && is_array($cards)) {
        foreach($cards as $card) {
            $img = isset($card['img']) ? $card['img'] : '';
            $title = isset($card['title']) ? $card['title'] : '';
            $desc = isset($card['desc']) ? $card['desc'] : '';
            
            echo '<div class="fmb-repeater-item">';
            echo '<div class="fmb-item-top"><span class="fmb-item-title">🌟 Info Card</span><button type="button" class="fmb-remove-btn">✕ Delete</button></div>';
            echo '<div class="fmb-field-group" style="margin-bottom:8px;"><label>Image URL</label><input type="text" name="_fmb_card_img[]" value="'.esc_attr($img).'" placeholder="https://..."></div>';
            echo '<div class="fmb-field-group" style="margin-bottom:8px;"><label>Title</label><input type="text" name="_fmb_card_title[]" value="'.esc_attr($title).'" placeholder="কার্ডের শিরোনাম"></div>';
            echo '<div class="fmb-field-group"><label>Description</label><textarea name="_fmb_card_desc[]" placeholder="Description" style="width:100%; height:55px;">'.esc_textarea($desc).'</textarea></div>';
            echo '</div>';
        }
    }
    echo '</div>';
    echo '<button type="button" class="fmb-add-btn" id="fmb_add_card">+ Add Card</button>';
    echo '</div>';

    // --- সেকশন ৩: Upsells ---
    echo '<div class="fmb-section-box">';
    echo '<div class="fmb-section-header"><h3>🛍️ সেকশন ৩: Upsells</h3></div>';
    echo '<div class="fmb-header-input-wrap">';
    echo '<label for="_fmb_upsell_title">Section Title</label>';
    echo '<input type="text" id="_fmb_upsell_title" name="_fmb_upsell_title" value="' . esc_attr($upsell_title) . '">';
    echo '</div>';
    
    echo '<div id="fmb_upsells_wrapper">';
    foreach($upsell_data as $u) {
        $product_id = absint($u['id'] ?? 0);
        if (!$product_id) continue;
        $product = wc_get_product($product_id);
        if (!$product) continue;
        $title = $product->get_formatted_name();
        $logic_qty = esc_attr($u['logic_qty'] ?? '');
        $logic_type = esc_attr($u['logic_type'] ?? '');
        $logic_value = esc_attr($u['logic_value'] ?? '');
        
        echo '<div class="fmb-repeater-item">';
        echo '<div class="fmb-item-top"><span class="fmb-item-title">🛍️ Upsell Product</span><button type="button" class="fmb-remove-btn">✕ Delete</button></div>';
        echo '<div class="fmb-field-group" style="margin-bottom:10px;">';
        echo '<label>Product</label>';
        echo '<select class="wc-product-search" name="_fmb_upsell_product_id[]" style="width:100%;" data-placeholder="Search product..." data-action="woocommerce_json_search_products_and_variations">';
        echo '<option value="'.esc_attr($product_id).'" selected>'.esc_html($title).'</option>';
        echo '</select>';
        echo '</div>';
        echo '<div class="fmb-grid fmb-grid-3">';
        echo '<div class="fmb-field-group"><label>Target Qty</label><input type="number" name="_fmb_upsell_logic_qty[]" value="'.$logic_qty.'" placeholder="e.g. 2"></div>';
        echo '<div class="fmb-field-group"><label>Discount / Reward Type</label><select name="_fmb_upsell_logic_type[]">';
        echo '<option value="" '.selected($logic_type, '', false).'>No Logic</option>';
        echo '<option value="free_delivery" '.selected($logic_type, 'free_delivery', false).'>Free Delivery</option>';
        echo '<option value="fixed" '.selected($logic_type, 'fixed', false).'>Fixed Discount (৳)</option>';
        echo '<option value="percent" '.selected($logic_type, 'percent', false).'>Percent Discount (%)</option>';
        echo '</select></div>';
        echo '<div class="fmb-field-group"><label>Reward Value</label><input type="number" name="_fmb_upsell_logic_value[]" value="'.$logic_value.'" placeholder="e.g. 50"></div>';
        echo '</div>';
        echo '</div>';
    }
    echo '</div>';
    echo '<button type="button" class="fmb-add-btn" id="fmb_add_upsell">+ Add Upsell</button>';
    echo '</div>';


    // --- সেকশন ৪: Order Bumps ---
    echo '<div class="fmb-section-box">';
    echo '<div class="fmb-section-header"><h3>⚡ সেকশন ৪: Order Bumps</h3></div>';
    echo '<div id="fmb_order_bumps_wrapper">';
    foreach($ob_data as $ob) {
        $product_id = absint($ob['id'] ?? 0);
        if (!$product_id) continue;
        $product = wc_get_product($product_id);
        if (!$product) continue;
        $title_opt = $product->get_formatted_name();
        
        $logic_qty = esc_attr($ob['logic_qty'] ?? '');
        $logic_type = esc_attr($ob['logic_type'] ?? '');
        $logic_value = esc_attr($ob['logic_value'] ?? '');
        
        echo '<div class="fmb-repeater-item">';
        echo '<div class="fmb-item-top"><span class="fmb-item-title">⚡ Order Bump</span><button type="button" class="fmb-remove-btn">✕ Delete</button></div>';
        echo '<div class="fmb-field-group" style="margin-bottom:10px;">';
        echo '<label>Product</label>';
        echo '<select class="wc-product-search" name="_fmb_ob_product_id[]" style="width:100%;" data-placeholder="Search product..." data-action="woocommerce_json_search_products_and_variations">';
        echo '<option value="'.esc_attr($product_id).'" selected>'.esc_html($title_opt).'</option>';
        echo '</select>';
        echo '</div>';
        echo '<div class="fmb-grid fmb-grid-2" style="margin-bottom:10px;">';
        echo '<div class="fmb-field-group"><label>Custom Bump Title (Optional)</label><input type="text" name="_fmb_ob_title[]" value="'.esc_attr($ob['title'] ?? '').'" placeholder="Default: Product Title"></div>';
        echo '<div class="fmb-field-group"><label>Custom Price Override (Optional)</label><input type="text" name="_fmb_ob_price[]" value="'.esc_attr($ob['price_override'] ?? '').'" placeholder="Default: Product Price"></div>';
        echo '</div>';
        echo '<div class="fmb-field-group" style="margin-bottom:10px;">';
        echo '<label>Description / Offer Text</label>';
        echo '<textarea name="_fmb_ob_desc[]" placeholder="Description" style="width:100%; height:50px;">'.esc_textarea($ob['desc'] ?? '').'</textarea>';
        echo '</div>';
        echo '<div class="fmb-grid fmb-grid-3" style="margin-bottom:10px;">';
        echo '<div class="fmb-field-group"><label>Target Qty</label><input type="number" name="_fmb_ob_logic_qty[]" value="'.$logic_qty.'" placeholder="e.g. 2"></div>';
        echo '<div class="fmb-field-group"><label>Discount / Reward Type</label><select name="_fmb_ob_logic_type[]">';
        echo '<option value="" '.selected($logic_type, '', false).'>No Logic</option>';
        echo '<option value="free_delivery" '.selected($logic_type, 'free_delivery', false).'>Free Delivery</option>';
        echo '<option value="fixed" '.selected($logic_type, 'fixed', false).'>Fixed Discount (৳)</option>';
        echo '<option value="percent" '.selected($logic_type, 'percent', false).'>Percent Discount (%)</option>';
        echo '</select></div>';
        echo '<div class="fmb-field-group"><label>Reward Value</label><input type="number" name="_fmb_ob_logic_value[]" value="'.$logic_value.'" placeholder="e.g. 50"></div>';
        echo '</div>';
        echo '<div class="fmb-grid fmb-grid-2">';
        echo '<div class="fmb-field-group"><label>Card Background Color</label><input type="text" name="_fmb_ob_bg[]" value="'.esc_attr($ob['bg'] ?? '').'" placeholder="e.g. #fffbeb"></div>';
        echo '<div class="fmb-field-group"><label>Card Border Color</label><input type="text" name="_fmb_ob_border[]" value="'.esc_attr($ob['border'] ?? '').'" placeholder="e.g. #f59e0b"></div>';
        echo '</div>';
        echo '</div>';
    }
    echo '</div>';
    echo '<button type="button" class="fmb-add-btn" id="fmb_add_order_bump">+ Add Order Bump</button>';
    echo '</div>';

    echo '</div>'; // End panel
}

// ৩. ডাটা সেভ করা
add_action('woocommerce_process_product_meta', 'fmb_save_custom_fields');
function fmb_save_custom_fields($post_id) {
    // Template Variant
    if(isset($_POST['_fmb_single_template_variant'])) {
        update_post_meta($post_id, '_fmb_single_template_variant', sanitize_text_field($_POST['_fmb_single_template_variant']));
    }

    // Titles
    if(isset($_POST['_fmb_steps_title'])) update_post_meta($post_id, '_fmb_steps_title', sanitize_text_field($_POST['_fmb_steps_title']));
    if(isset($_POST['_fmb_cards_title'])) update_post_meta($post_id, '_fmb_cards_title', sanitize_text_field($_POST['_fmb_cards_title']));
    if(isset($_POST['_fmb_upsell_title'])) update_post_meta($post_id, '_fmb_upsell_title', sanitize_text_field($_POST['_fmb_upsell_title']));

    // Steps (Array Save)
    if(isset($_POST['_fmb_steps'])) {
        $steps = array_map('sanitize_textarea_field', $_POST['_fmb_steps']);
        update_post_meta($post_id, '_fmb_usage_steps', array_values($steps));
    } else {
        delete_post_meta($post_id, '_fmb_usage_steps');
    }

    // Cards (Complex Array Save)
    if(isset($_POST['_fmb_card_title'])) {
        $cards = [];
        $imgs = isset($_POST['_fmb_card_img']) ? $_POST['_fmb_card_img'] : [];
        $titles = $_POST['_fmb_card_title'];
        $descs = isset($_POST['_fmb_card_desc']) ? $_POST['_fmb_card_desc'] : [];

        for($i=0; $i<count($titles); $i++) {
            if(!empty($titles[$i])) {
                $cards[] = [
                    'img' => sanitize_text_field($imgs[$i] ?? ''),
                    'title' => sanitize_text_field($titles[$i]),
                    'desc' => sanitize_textarea_field($descs[$i] ?? '')
                ];
            }
        }
        update_post_meta($post_id, '_fmb_info_cards', $cards);
    } else {
        delete_post_meta($post_id, '_fmb_info_cards');
    }

    // Upsell
    if(isset($_POST['_fmb_upsell_product_id'])) {
        $upsells = [];
        $u_ids = $_POST['_fmb_upsell_product_id'];
        $u_logic_qty = $_POST['_fmb_upsell_logic_qty'] ?? [];
        $u_logic_type = $_POST['_fmb_upsell_logic_type'] ?? [];
        $u_logic_value = $_POST['_fmb_upsell_logic_value'] ?? [];
        for($i=0; $i<count($u_ids); $i++) {
            if(!empty($u_ids[$i])) {
                $upsells[] = [
                    'id' => absint($u_ids[$i]),
                    'logic_qty' => absint($u_logic_qty[$i] ?? 0),
                    'logic_type' => sanitize_text_field($u_logic_type[$i] ?? ''),
                    'logic_value' => floatval($u_logic_value[$i] ?? 0)
                ];
            }
        }
        update_post_meta($post_id, '_fmb_upsells_data', $upsells);
    } else {
        delete_post_meta($post_id, '_fmb_upsells_data');
    }

    // Order Bumps Save
    if(isset($_POST['_fmb_ob_product_id'])) {
        $bumps = [];
        $b_ids = $_POST['_fmb_ob_product_id'];
        $b_titles = $_POST['_fmb_ob_title'] ?? [];
        $b_descs = $_POST['_fmb_ob_desc'] ?? [];
        $b_prices = $_POST['_fmb_ob_price'] ?? [];
        $b_logic_qty = $_POST['_fmb_ob_logic_qty'] ?? [];
        $b_logic_type = $_POST['_fmb_ob_logic_type'] ?? [];
        $b_logic_value = $_POST['_fmb_ob_logic_value'] ?? [];
        $b_bgs = $_POST['_fmb_ob_bg'] ?? [];
        $b_borders = $_POST['_fmb_ob_border'] ?? [];

        for($i=0; $i<count($b_ids); $i++) {
            if(!empty($b_ids[$i])) {
                $bumps[] = [
                    'id' => absint($b_ids[$i]),
                    'title' => sanitize_text_field($b_titles[$i] ?? ''),
                    'desc' => sanitize_textarea_field($b_descs[$i] ?? ''),
                    'price_override' => sanitize_text_field($b_prices[$i] ?? ''),
                    'logic_qty' => absint($b_logic_qty[$i] ?? 0),
                    'logic_type' => sanitize_text_field($b_logic_type[$i] ?? ''),
                    'logic_value' => floatval($b_logic_value[$i] ?? 0),
                    'bg' => sanitize_text_field($b_bgs[$i] ?? ''),
                    'border' => sanitize_text_field($b_borders[$i] ?? '')
                ];
            }
        }
        update_post_meta($post_id, '_fmb_order_bumps_data', $bumps);
    } else {
        delete_post_meta($post_id, '_fmb_order_bumps_data');
    }
}