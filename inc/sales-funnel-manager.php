<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class FMB_Sales_Funnel_Manager {

    public function __construct() {
        add_action('init', array($this, 'register_sales_page_cpt'));
        add_action('edit_form_after_title', array($this, 'render_custom_editor_after_title'));
        add_action('save_post', array($this, 'save_sales_page_meta'));
    }

    public function register_sales_page_cpt() {
        $labels = array(
            'name'                  => 'Sales Pages',
            'singular_name'         => 'Sales Page',
            'menu_name'             => 'Sales Pages',
            'name_admin_bar'        => 'Sales Page',
            'add_new'               => 'Add New',
            'add_new_item'          => 'Add New Sales Page',
            'new_item'              => 'New Sales Page',
            'edit_item'             => 'Edit Sales Page',
            'view_item'             => 'View Sales Page',
            'all_items'             => 'All Sales Pages',
            'search_items'          => 'Search Sales Pages',
            'not_found'             => 'No sales pages found.',
            'not_found_in_trash'    => 'No sales pages found in Trash.'
        );

        $args = array(
            'labels'             => $labels,
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => true,
            'rewrite'            => array('slug' => 'sales-page'),
            'capability_type'    => 'post',
            'has_archive'        => false,
            'hierarchical'       => false,
            'menu_position'      => 30,
            'menu_icon'          => 'dashicons-cart',
            'supports'           => array('title', 'editor', 'thumbnail'),
            'show_in_rest'       => false, // Disable Gutenberg for custom classic editor feel
        );

        register_post_type('fmb_sales_page', $args);

        // Auto flush rewrite rules if option is not set (to fix 404 issue)
        if (!get_option('fmb_sales_page_flushed')) {
            flush_rewrite_rules();
            update_option('fmb_sales_page_flushed', true);
        }
    }

    public function render_custom_editor_after_title($post) {
        if ($post->post_type !== 'fmb_sales_page') return;
        $this->render_sales_page_metabox($post);
    }

    private function get_product_preview_data($ids) {
        if (empty($ids)) return array();
        $data = array();
        foreach ($ids as $id) {
            $_product = wc_get_product($id);
            if ($_product) {
                $data[] = array(
                    'id' => $id,
                    'title' => $_product->get_name(),
                    'price' => $_product->get_price(),
                    'img' => wp_get_attachment_image_url($_product->get_image_id(), 'thumbnail') ?: wc_placeholder_img_src()
                );
            }
        }
        return $data;
    }

    public function render_sales_page_metabox($post) {
        wp_nonce_field('fmb_sales_page_save', 'fmb_sales_page_nonce');

        // Main Products
        $selected_products = get_post_meta($post->ID, '_fmb_sp_products', true) ?: array();
        $selected_products_data = $this->get_product_preview_data($selected_products);

        // Bump Offer
        $bump_trigger_ids = get_post_meta($post->ID, '_fmb_sp_bump_trigger_ids', true) ?: array();
        $bump_trigger_data = $this->get_product_preview_data($bump_trigger_ids);
        
        $bump_is_default = get_post_meta($post->ID, '_fmb_sp_bump_is_default', true) === 'yes';
        
        $bump_target_id = get_post_meta($post->ID, '_fmb_sp_bump_target', true);
        $bump_target_data = $this->get_product_preview_data($bump_target_id ? array($bump_target_id) : array());

        $bump_target_price = get_post_meta($post->ID, '_fmb_sp_bump_price', true);
        $bump_free_delivery = get_post_meta($post->ID, '_fmb_sp_bump_free_delivery', true) === 'yes';
        $bump_free_product = get_post_meta($post->ID, '_fmb_sp_bump_free_product', true) === 'yes';

        // Free Shipping Offer
        $free_shipping_trigger_ids = get_post_meta($post->ID, '_fmb_sp_free_shipping_trigger_ids', true) ?: array();
        $free_shipping_trigger_data = $this->get_product_preview_data($free_shipping_trigger_ids);
        
        // Bump Description & Default Bumps
        // Bump Description & Default Bumps
        $bump_desc = get_post_meta($post->ID, '_fmb_sp_bump_desc', true);
        $default_bump_desc = get_post_meta($post->ID, '_fmb_sp_default_bump_desc', true);
        $free_shipping_desc = get_post_meta($post->ID, '_fmb_sp_free_shipping_desc', true);
        $default_bump_ids = get_post_meta($post->ID, '_fmb_sp_default_bump_ids', true) ?: array();
        $default_bump_data = $this->get_product_preview_data($default_bump_ids);
        $free_shipping_trigger_data = $this->get_product_preview_data($free_shipping_trigger_ids);

        ?>
        <style>
            .fmb-custom-editor-wrap {
                background: #fff; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04);
                margin: 20px 0; border-radius: 6px; overflow: hidden;
            }
            .fmb-main-title {
                margin: 0; padding: 15px 20px; background: #f8f9fa; border-bottom: 1px solid #ccd0d4;
                font-size: 16px; font-weight: 600; color: #1d2327; display: flex; align-items: center; gap: 10px;
            }
            .fmb-editor-body { padding: 20px; display: flex; flex-wrap: wrap; gap: 30px; }
            .fmb-col-left { flex: 1 1 350px; }
            .fmb-col-right { flex: 1 1 450px; display: flex; flex-direction: column; gap: 20px; }
            .fmb-sp-section { background: #fcfcfc; border: 1px solid #e2e4e7; border-radius: 5px; padding: 15px; }
            .fmb-sp-title { font-size: 14px; font-weight: 600; margin-bottom: 8px; color: #1d2327; border-bottom: 1px solid #eee; padding-bottom: 8px;}
            
            .fmb-sp-row { display: flex; flex-direction: column; gap: 4px; margin-bottom: 12px; }
            .fmb-sp-row label { font-weight: 600; font-size: 13px; }
            .fmb-sp-row input[type="text"], .fmb-sp-row input[type="number"] { width: 100%; max-width: 100%; }
            .description { font-size: 12px; color: #646970; font-style: italic; margin-top: 2px !important; }
            
            /* Search Component UI */
            .fmb-search-box { position: relative; }
            .fmb-search-input { width: 100%; padding: 8px 12px; border: 1px solid #8c8f94; border-radius: 4px; font-size: 14px; }
            .fmb-search-results { position: absolute; top: 100%; left: 0; right: 0; background: #fff; border: 1px solid #ccd0d4; max-height: 250px; overflow-y: auto; z-index: 100; box-shadow: 0 4px 6px rgba(0,0,0,0.1); display: none; }
            .fmb-search-result-item { display: flex; align-items: center; gap: 10px; padding: 8px; border-bottom: 1px solid #f0f0f1; cursor: pointer; transition: background 0.2s; }
            .fmb-search-result-item:hover { background: #f0f6fc; }
            .fmb-search-result-item img { width: 30px; height: 30px; object-fit: cover; border-radius: 3px; border: 1px solid #eee; }
            .fmb-search-result-info { display: flex; flex-direction: column; }
            .fmb-search-result-title { font-size: 13px; font-weight: 600; color: #1d2327; }
            .fmb-search-result-price { font-size: 11px; color: #d63638; font-weight: bold; }
            
            .fmb-selected-list { margin-top: 10px; display: flex; flex-direction: column; gap: 8px; }
            .fmb-selected-item { display: flex; align-items: center; gap: 10px; background: #fff; border: 1px solid #dcdcde; padding: 8px; border-radius: 4px; }
            .fmb-selected-item img { width: 40px; height: 40px; object-fit: cover; border-radius: 3px; border: 1px solid #eee; }
            .fmb-selected-item-info { flex-grow: 1; display: flex; flex-direction: column; }
            .fmb-selected-item-title { font-size: 13px; font-weight: 600; }
            .fmb-selected-item-price { font-size: 12px; color: #d63638; font-weight: bold; }
            .fmb-remove-item { color: #d63638; cursor: pointer; font-size: 20px; line-height: 1; padding: 0 5px; }
            .fmb-remove-item:hover { color: #a00; }

            .fmb-checkbox-row { display: flex; align-items: center; gap: 8px; margin-top: 5px; }
        </style>

        <div class="fmb-custom-editor-wrap">
            <h2 class="fmb-main-title">
                <span class="dashicons dashicons-cart"></span> 
                Sales Funnel / Landing Page Builder
            </h2>
            
            <div class="fmb-editor-body">
                <!-- Left Column: Products -->
                <div class="fmb-col-left">
                    <div class="fmb-sp-section">
                        <div class="fmb-sp-title">1. Select Products for this Page</div>
                        <p class="description">Search and add the products you want to display.</p>
                        
                        <div class="fmb-search-component" data-input-name="fmb_sp_products" data-max="999">
                            <div class="fmb-search-box">
                                <input type="text" class="fmb-search-input" placeholder="Search by name...">
                                <div class="fmb-search-results"></div>
                            </div>
                            <div class="fmb-selected-list"></div>
                            <input type="hidden" name="fmb_sp_products" class="fmb-hidden-value" value="<?php echo esc_attr(implode(',', $selected_products)); ?>">
                            <script type="application/json" class="fmb-init-data"><?php echo wp_json_encode($selected_products_data); ?></script>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Offers -->
                <div class="fmb-col-right">
                    <div class="fmb-sp-section">
                        <div class="fmb-sp-title">2. Bump Offer (Upsell) Settings</div>
                        
                        <div class="fmb-sp-row">
                            <label>Trigger Condition:</label>
                            <div class="fmb-checkbox-row">
                                <input type="checkbox" name="fmb_sp_bump_is_default" id="fmb_sp_bump_is_default" value="yes" <?php checked($bump_is_default); ?>>
                                <label for="fmb_sp_bump_is_default">Default (Always show this bump offer)</label>
                            </div>
                        </div>

                        <div class="fmb-sp-row" id="bump-trigger-wrap" style="<?php echo $bump_is_default ? 'display:none;' : ''; ?>">
                            <label>Trigger Products (Max 6):</label>
                            <p class="description">If customer adds any of these, show the bump offer.</p>
                            <div class="fmb-search-component" data-input-name="fmb_sp_bump_trigger_ids" data-max="6">
                                <div class="fmb-search-box">
                                    <input type="text" class="fmb-search-input" placeholder="Search trigger product...">
                                    <div class="fmb-search-results"></div>
                                </div>
                                <div class="fmb-selected-list"></div>
                                <input type="hidden" name="fmb_sp_bump_trigger_ids" class="fmb-hidden-value" value="<?php echo esc_attr(implode(',', $bump_trigger_ids)); ?>">
                                <script type="application/json" class="fmb-init-data"><?php echo wp_json_encode($bump_trigger_data); ?></script>
                            </div>
                        </div>

                        <div class="fmb-sp-row" style="margin-top:20px; border-top:1px dashed #ccc; padding-top:15px;">
                            <label>Target Offer Product (Select 1):</label>
                            <div class="fmb-search-component" data-input-name="fmb_sp_bump_target" data-max="1">
                                <div class="fmb-search-box">
                                    <input type="text" class="fmb-search-input" placeholder="Search target product...">
                                    <div class="fmb-search-results"></div>
                                </div>
                                <div class="fmb-selected-list"></div>
                                <input type="hidden" name="fmb_sp_bump_target" class="fmb-hidden-value" value="<?php echo esc_attr($bump_target_id); ?>">
                                <script type="application/json" class="fmb-init-data"><?php echo wp_json_encode($bump_target_data); ?></script>
                            </div>
                        </div>

                        <div class="fmb-sp-row" style="margin-top: 15px;">
                            <label>Offer Perks:</label>
                            
                            <div class="fmb-checkbox-row" style="margin-bottom: 5px;">
                                <input type="checkbox" name="fmb_sp_bump_free_product" id="fmb_sp_bump_free_product" value="yes" <?php checked($bump_free_product); ?>>
                                <label for="fmb_sp_bump_free_product">Make it totally FREE (Buy 1 Get 1 equivalent)</label>
                            </div>

                            <div class="fmb-checkbox-row" style="margin-bottom: 10px;">
                                <input type="checkbox" name="fmb_sp_bump_free_delivery" id="fmb_sp_bump_free_delivery" value="yes" <?php checked($bump_free_delivery); ?>>
                                <label for="fmb_sp_bump_free_delivery">Also give Free Delivery for the whole order</label>
                            </div>
                            
                            <div id="bump-price-wrap" style="<?php echo $bump_free_product ? 'display:none;' : ''; ?>">
                                <label>Special Discount Price (Tk):</label>
                                <input type="number" name="fmb_sp_bump_price" value="<?php echo esc_attr($bump_target_price); ?>" placeholder="e.g. 500">
                                <p class="description">Leave empty to use regular price.</p>
                            </div>
                            
                            <div class="fmb-sp-row" style="margin-top: 15px;">
                                <label>Why add this? (Reason / Description):</label>
                                <input type="text" name="fmb_sp_bump_desc" value="<?php echo esc_attr($bump_desc); ?>" placeholder="e.g. Special combo discount valid for today!">
                                <p class="description">This text will be shown under the bump product title.</p>
                            </div>
                        </div>
                    </div>

                    <div class="fmb-sp-section" style="margin-top: 20px;">
                        <div class="fmb-sp-title">3. Default Bump Offers (Always Show)</div>
                        <p class="description">These products will ALWAYS show as bump offers in the checkout at their regular price. (Max 6)</p>
                        <div class="fmb-search-component" data-input-name="fmb_sp_default_bump_ids" data-max="6">
                            <div class="fmb-search-box">
                                <input type="text" class="fmb-search-input" placeholder="Search default bump product...">
                            <div class="fmb-search-results"></div>
                        </div>
                        <div class="fmb-selected-list"></div>
                        <input type="hidden" name="fmb_sp_default_bump_ids" class="fmb-hidden-value" value="<?php echo esc_attr(implode(',', $default_bump_ids)); ?>">
                        <script type="application/json" class="fmb-init-data"><?php echo wp_json_encode($default_bump_data); ?></script>
                    </div>
                    
                    <div class="fmb-sp-row" style="margin-top: 10px;">
                        <label>Description:</label>
                        <input type="text" name="fmb_sp_default_bump_desc" value="<?php echo esc_attr($default_bump_desc); ?>" placeholder="e.g. Recommended combo offers for you">
                    </div>
                </div>

                    <div class="fmb-sp-section" style="margin-top: 20px;">
                        <div class="fmb-sp-title">4. Free Shipping Offer</div>
                        <p class="description">If they add any of these products (Max 6), delivery is free.</p>
                        <div class="fmb-search-component" data-input-name="fmb_sp_free_shipping_trigger_ids" data-max="6">
                            <div class="fmb-search-box">
                                <input type="text" class="fmb-search-input" placeholder="Search free shipping product...">
                            <div class="fmb-search-results"></div>
                        </div>
                        <div class="fmb-selected-list"></div>
                        <input type="hidden" name="fmb_sp_free_shipping_trigger_ids" class="fmb-hidden-value" value="<?php echo esc_attr(implode(',', $free_shipping_trigger_ids)); ?>">
                        <script type="application/json" class="fmb-init-data"><?php echo wp_json_encode($free_shipping_trigger_data); ?></script>
                    </div>

                    <div class="fmb-sp-row" style="margin-top: 10px;">
                        <label>Description:</label>
                        <input type="text" name="fmb_sp_free_shipping_desc" value="<?php echo esc_attr($free_shipping_desc); ?>" placeholder="e.g. Add these to your cart to get FREE Shipping!">
                    </div>
                </div>
                </div> <!-- /fmb-col-right -->
            </div> <!-- /fmb-editor-body -->
        </div> <!-- End .fmb-custom-editor-wrap -->

        <script>
        jQuery(document).ready(function($) {
            
            // Toggle Bump Triggers visibility based on "Default" checkbox
            $('#fmb_sp_bump_is_default').on('change', function() {
                if ($(this).is(':checked')) {
                    $('#bump-trigger-wrap').slideUp();
                } else {
                    $('#bump-trigger-wrap').slideDown();
                }
            });

            // Toggle Price input based on "Free Product" checkbox
            $('#fmb_sp_bump_free_product').on('change', function() {
                if ($(this).is(':checked')) {
                    $('#bump-price-wrap').slideUp();
                } else {
                    $('#bump-price-wrap').slideDown();
                }
            });

            // Search Component Logic
            $('.fmb-search-component').each(function() {
                var $comp = $(this);
                var $input = $comp.find('.fmb-search-input');
                var $results = $comp.find('.fmb-search-results');
                var $list = $comp.find('.fmb-selected-list');
                var $hidden = $comp.find('.fmb-hidden-value');
                var maxItems = parseInt($comp.data('max')) || 999;
                
                var selectedItems = [];
                
                // Init from JSON
                try {
                    var initData = JSON.parse($comp.find('.fmb-init-data').text() || '[]');
                    if(Array.isArray(initData)) {
                        selectedItems = initData;
                        renderList();
                    }
                } catch(e) { console.error(e); }

                function renderList() {
                    $list.empty();
                    var ids = [];
                    selectedItems.forEach(function(item, index) {
                        ids.push(item.id);
                        var html = `
                            <div class="fmb-selected-item">
                                <img src="${item.img}" alt="">
                                <div class="fmb-selected-item-info">
                                    <span class="fmb-selected-item-title">${item.title}</span>
                                    <span class="fmb-selected-item-price">${item.price}৳</span>
                                </div>
                                <span class="fmb-remove-item" data-index="${index}">&times;</span>
                            </div>
                        `;
                        $list.append(html);
                    });
                    $hidden.val(ids.join(','));
                }

                $list.on('click', '.fmb-remove-item', function() {
                    var index = $(this).data('index');
                    selectedItems.splice(index, 1);
                    renderList();
                });

                var timer = null;
                $input.on('input', function() {
                    var term = $(this).val().trim();
                    clearTimeout(timer);
                    if (term.length < 2) {
                        $results.hide().empty();
                        return;
                    }
                    timer = setTimeout(function() {
                        $.ajax({
                            url: ajaxurl,
                            data: { action: 'fmb_live_search', term: term },
                            dataType: 'json',
                            success: function(res) {
                                if (res.success && res.data.products && res.data.products.length > 0) {
                                    $results.empty();
                                    res.data.products.forEach(function(p) {
                                        var pPrice = p.price_html || p.price;
                                        var html = `
                                            <div class="fmb-search-result-item" data-id="${p.id}" data-title="${p.title}" data-price="${p.price}" data-img="${p.thumbnail}">
                                                <img src="${p.thumbnail}">
                                                <div class="fmb-search-result-info">
                                                    <span class="fmb-search-result-title">${p.title}</span>
                                                    <span class="fmb-search-result-price">${pPrice}</span>
                                                </div>
                                            </div>
                                        `;
                                        $results.append(html);
                                    });
                                    $results.show();
                                } else {
                                    $results.html('<div style="padding:10px;">No products found</div>').show();
                                }
                            }
                        });
                    }, 400);
                });

                $results.on('click', '.fmb-search-result-item', function() {
                    var $item = $(this);
                    var id = $item.data('id');
                    
                    // Check if already exists
                    if (selectedItems.find(i => i.id == id)) {
                        alert("Product already selected.");
                        return;
                    }

                    if (selectedItems.length >= maxItems) {
                        if (maxItems === 1) {
                            // Replace the first item if max is 1 (like Target Product)
                            selectedItems = [];
                        } else {
                            alert("You can only select up to " + maxItems + " products.");
                            return;
                        }
                    }

                    selectedItems.push({
                        id: id,
                        title: $item.data('title'),
                        price: $item.data('price'),
                        img: $item.data('img')
                    });

                    renderList();
                    $input.val('');
                    $results.hide().empty();
                });

                // Hide results on outside click
                $(document).on('click', function(e) {
                    if (!$(e.target).closest($comp).length) {
                        $results.hide();
                    }
                });
            });
        });
        </script>
        <?php
    }

    public function save_sales_page_meta($post_id) {
        if (!isset($_POST['fmb_sales_page_nonce']) || !wp_verify_nonce($_POST['fmb_sales_page_nonce'], 'fmb_sales_page_save')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;

        $save_comma_list = function($key) use ($post_id) {
            if (isset($_POST[$key])) {
                $ids = array_filter(array_map('intval', explode(',', sanitize_text_field($_POST[$key]))));
                update_post_meta($post_id, '_' . $key, $ids);
            }
        };

        $save_comma_list('fmb_sp_products');
        $save_comma_list('fmb_sp_bump_trigger_ids');
        $save_comma_list('fmb_sp_free_shipping_trigger_ids');
        $save_comma_list('fmb_sp_default_bump_ids');

        $bump_target = isset($_POST['fmb_sp_bump_target']) ? intval($_POST['fmb_sp_bump_target']) : '';
        update_post_meta($post_id, '_fmb_sp_bump_target', $bump_target);

        update_post_meta($post_id, '_fmb_sp_bump_is_default', isset($_POST['fmb_sp_bump_is_default']) ? 'yes' : 'no');
        update_post_meta($post_id, '_fmb_sp_bump_free_product', isset($_POST['fmb_sp_bump_free_product']) ? 'yes' : 'no');
        update_post_meta($post_id, '_fmb_sp_bump_price', isset($_POST['fmb_sp_bump_price']) ? sanitize_text_field($_POST['fmb_sp_bump_price']) : '');
        update_post_meta($post_id, '_fmb_sp_bump_desc', isset($_POST['fmb_sp_bump_desc']) ? sanitize_text_field($_POST['fmb_sp_bump_desc']) : '');
        update_post_meta($post_id, '_fmb_sp_default_bump_desc', isset($_POST['fmb_sp_default_bump_desc']) ? sanitize_text_field($_POST['fmb_sp_default_bump_desc']) : '');
        update_post_meta($post_id, '_fmb_sp_free_shipping_desc', isset($_POST['fmb_sp_free_shipping_desc']) ? sanitize_text_field($_POST['fmb_sp_free_shipping_desc']) : '');
    }
}

new FMB_Sales_Funnel_Manager();
