<?php
/**
 * FMB Enterprise AI Product Data, Landing Page & Marketing Suite
 * Features:
 * 1. Google Gemini 1.5/2.0 API + 50+ Multi-Niche Offline Knowledge Graph
 * 2. Tone & Copywriting Style Selector (FOMO, Problem-Solving, Premium, Simple)
 * 3. Product Feature Comparison Table (Ours vs Others)
 * 4. Interactive Accordion FAQ Generator
 * 5. 1-Click Facebook & TikTok Viral Marketing Ad Copy Generator
 * 6. AI Order Bump & Upsell Suggester (Sections 3 & 4)
 * 7. Quick AI Action Button in Products List Table
 * 8. Custom Knowledge Base Manager with Multi-Tag Architecture & Resilient JSON Parser
 */

if (!defined('ABSPATH')) {
    exit;
}

class FMB_AI_Product_Generator {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('edit_form_after_title', array($this, 'render_top_button'));
        add_action('post_submitbox_misc_actions', array($this, 'render_sidebar_button'));
        add_action('admin_footer', array($this, 'render_modal_markup'));
        add_filter('post_row_actions', array($this, 'add_product_list_row_action'), 10, 2);
        
        // AJAX Endpoints
        add_action('wp_ajax_fmb_generate_ai_product_data', array($this, 'ajax_generate_product_data'));
        add_action('wp_ajax_fmb_save_ai_settings', array($this, 'ajax_save_ai_settings'));
        add_action('wp_ajax_fmb_add_custom_knowledge', array($this, 'ajax_add_custom_knowledge'));
        add_action('wp_ajax_fmb_get_knowledge_library', array($this, 'ajax_get_knowledge_library'));
        add_action('wp_ajax_fmb_delete_custom_knowledge', array($this, 'ajax_delete_custom_knowledge'));
        add_action('wp_ajax_fmb_generate_manual_prompt', array($this, 'ajax_generate_manual_prompt'));
    }

    private function is_product_screen() {
        $screen = get_current_screen();
        return ($screen && ($screen->post_type === 'product' || $screen->id === 'edit-product'));
    }

    public function enqueue_admin_assets($hook) {
        if (!in_array($hook, ['post-new.php', 'post.php', 'edit.php'])) return;
        $screen = get_current_screen();
        if (!$screen || $screen->post_type !== 'product') return;
        wp_enqueue_media();
    }

    /**
     * Add Quick AI Action to WooCommerce Products List Table
     */
    public function add_product_list_row_action($actions, $post) {
        if ($post->post_type !== 'product') return $actions;
        $price = get_post_meta($post->ID, '_sale_price', true) ?: get_post_meta($post->ID, '_regular_price', true);
        $img = get_the_post_thumbnail_url($post->ID, 'full') ?: '';
        
        $actions['fmb_ai_quick'] = sprintf(
            '<a href="#" class="fmb-trigger-ai-modal" data-product-id="%d" data-title="%s" data-price="%s" data-img="%s" style="color:#7c3aed; font-weight:700;">✨ AI Generate</a>',
            $post->ID,
            esc_attr($post->post_title),
            esc_attr($price),
            esc_url($img)
        );
        return $actions;
    }

    /**
     * Top Banner & Trigger Button on Edit Product Screen
     */
    public function render_top_button($post) {
        if ($post->post_type !== 'product') return;
        ?>
        <div style="margin: 12px 0 16px 0; display: flex; align-items: center; justify-content: space-between; background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 50%, #312e81 100%); padding: 12px 18px; border-radius: 10px; box-shadow: 0 4px 10px -2px rgba(15, 23, 42, 0.3); color: #fff; border: 1px solid rgba(255,255,255,0.1);">
            <div style="display: flex; align-items: center; gap: 10px;">
                <span style="font-size: 24px; background: rgba(255,255,255,0.1); padding: 4px 8px; border-radius: 8px;">✨</span>
                <div>
                    <strong style="font-size: 15px; color: #fff; display: flex; align-items: center; gap: 6px;">
                        FMB Enterprise AI Product Copy, Landing Page & Ad Suite
                        <span style="background: #10b981; color: #fff; font-size: 10px; font-weight: 700; padding: 2px 6px; border-radius: 10px;">v3.0 ENTERPRISE</span>
                    </strong>
                    <div style="font-size: 12px; color: #c7d2fe; margin-top: 2px;">
                        1-Click Sales Copy, Tone Selector, Landing Page Steps, Info Cards, Comparison Table, FAQs & Facebook Ads Generator.
                    </div>
                </div>
            </div>
            <button type="button" class="button fmb-trigger-ai-modal" style="background: #fbbf24; border-color: #f59e0b; color: #78350f; font-weight: 700; font-size: 13px; padding: 6px 16px; border-radius: 6px; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 2px 5px rgba(0,0,0,0.15); cursor: pointer; transition: all 0.2s;">
                <span class="dashicons dashicons-superhero" style="font-size: 16px; width: 16px; height: 16px;"></span> Generate with AI
            </button>
        </div>
        <?php
    }

    /**
     * Sidebar Trigger Button
     */
    public function render_sidebar_button($post) {
        if (!is_object($post) || $post->post_type !== 'product') return;
        ?>
        <div class="misc-pub-section misc-pub-fmb-ai" style="padding: 10px; background: #f5f3ff; border-top: 1px solid #e9d5ff; border-bottom: 1px solid #e9d5ff;">
            <button type="button" class="button fmb-trigger-ai-modal" style="width: 100%; background: #4f46e5; border-color: #4338ca; color: #fff; font-weight: 600; font-size: 12px; display: flex; align-items: center; justify-content: center; gap: 6px; border-radius: 4px; box-shadow: 0 1px 2px rgba(79, 70, 229, 0.2);">
                ✨ AI Product Content & Marketing Suite
            </button>
        </div>
        <?php
    }

    /**
     * Modal Window Markup & Client Logic
     */
    public function render_modal_markup() {
        if (!$this->is_product_screen()) return;
        $gemini_key = get_option('fmb_gemini_ai_api_key', '');
        ?>
        <!-- AI Generator Modal -->
        <div id="fmb-ai-modal" style="display: none; position: fixed; z-index: 999999; left: 0; top: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.78); backdrop-filter: blur(6px); align-items: center; justify-content: center;">
            <div style="background: #ffffff; width: 980px; max-width: 96%; max-height: 95vh; border-radius: 16px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5); position: relative; display: flex; flex-direction: column; overflow: hidden;">
                
                <!-- Modal Header -->
                <div style="background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 60%, #312e81 100%); color: #ffffff; padding: 16px 24px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #4338ca;">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <span style="font-size: 24px; background: rgba(255,255,255,0.1); padding: 4px 8px; border-radius: 8px;">✨</span>
                        <div>
                            <h3 style="margin: 0; color: #ffffff; font-size: 17px; font-weight: 700; display: flex; align-items: center; gap: 8px;">
                                AI Product Copy, Landing Page & Marketing Suite
                            </h3>
                            <div style="color: #c7d2fe; font-size: 12px; margin-top: 2px;">
                                50+ Category Knowledge Library, Tone Selector, Comparison Table, FAQs & Ad Generator
                            </div>
                        </div>
                    </div>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <button type="button" id="fmb-btn-toggle-library" title="Knowledge Base Manager" class="button" style="background: rgba(255,255,255,0.15); border: 1px solid rgba(255,255,255,0.25); color: #fff; font-size: 12px; padding: 3px 10px; border-radius: 6px; cursor: pointer;">
                            📚 Library Manager
                        </button>
                        <button type="button" id="fmb-btn-ai-settings" title="API Key Settings" class="button" style="background: rgba(255,255,255,0.15); border: 1px solid rgba(255,255,255,0.25); color: #fff; font-size: 12px; padding: 3px 10px; border-radius: 6px; cursor: pointer;">
                            ⚙️ API Key
                        </button>
                        <span class="fmb-ai-close-modal" style="font-size: 26px; cursor: pointer; color: #c7d2fe; font-weight: bold; line-height: 1; margin-left: 6px;">&times;</span>
                    </div>
                </div>

                <!-- API Key Settings Drawer -->
                <div id="fmb-ai-settings-drawer" style="display: none; background: #f8fafc; border-bottom: 2px solid #e2e8f0; padding: 14px 24px; font-size: 13px;">
                    <div style="display: flex; gap: 10px; align-items: center;">
                        <label style="font-weight: 700; color: #334155; white-space: nowrap;">Google Gemini API Key (ঐচ্ছিক):</label>
                        <input type="password" id="fmb_gemini_api_key_input" value="<?= esc_attr($gemini_key) ?>" placeholder="AIzaSy..." style="flex: 1; padding: 6px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px;">
                        <button type="button" id="fmb_save_gemini_key" class="button button-primary" style="background: #2563eb; border-color: #2563eb;">Save Key</button>
                    </div>
                    <div style="font-size: 11px; color: #64748b; margin-top: 5px;">
                        * If you don't have an API Key, our 50+ category smart offline e-commerce knowledge graph will generate perfect data.
                    </div>
                </div>

                <!-- Knowledge Base Manager Drawer -->
                <div id="fmb-ai-library-drawer" style="display: none; background: #f0fdf4; border-bottom: 2px solid #bbf7d0; padding: 16px 24px; font-size: 13px; max-height: 250px; overflow-y: auto;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                        <strong style="color: #166534; font-size: 14px;">📚 Product Knowledge Library & Custom Niche Manager</strong>
                        <button type="button" id="btn-open-add-custom-niche" class="button button-small" style="background: #16a34a; color:#fff; border:none; font-weight:600;">+ Add New Product Niche</button>
                    </div>
                    <div id="custom-library-list" style="font-size: 12px; color: #334155;">Loading...</div>
                </div>

                <!-- Modal Body (Scrollable) -->
                <div style="padding: 18px 24px; overflow-y: auto; flex: 1;">
                    
                    <!-- Input Section -->
                    <div style="background: #f8fafc; border: 1.5px solid #e2e8f0; border-radius: 12px; padding: 16px; margin-bottom: 16px;">
                        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 12px; margin-bottom: 12px;">
                            <div>
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                                    <label style="font-weight: 700; font-size: 13px; color: #1e293b;">
                                        Product Name / Sample Title <span style="color:#ef4444;">*</span>:
                                    </label>
                                    <span id="detected-niche-badge" style="display: none; font-size: 11px; font-weight: 700; background: #dbeafe; color: #1e40af; padding: 2px 8px; border-radius: 12px;">
                                        🎯 Niche: <span id="detected-niche-name">General</span>
                                    </span>
                                </div>
                                <input type="text" id="ai_product_name" style="width: 100%; padding: 8px 12px; font-size: 14px; font-weight: 600; border: 1.5px solid #cbd5e1; border-radius: 8px;" placeholder="e.g. 6L Digital Air Fryer বা T9 Hair Trimmer বা মেজিক ফ্লোর মপ">
                            </div>
                            <div>
                                <label style="display: block; font-weight: 700; font-size: 13px; color: #1e293b; margin-bottom: 4px;">
                                    🎭 Copywriting Tone & Style:
                                </label>
                                <select id="ai_copy_tone" style="width: 100%; height: 38px; font-weight: 600; font-size: 13px; border: 1.5px solid #cbd5e1; border-radius: 8px; background: #fff;">
                                    <option value="fomo">🔥 High Urgency / FOMO</option>
                                    <option value="problem_solving" selected>💡 Problem-Solving</option>
                                    <option value="premium">👑 Premium & Luxury</option>
                                    <option value="simple">🌿 Simple & Informative</option>
                                </select>
                            </div>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr 1.2fr; gap: 12px; margin-bottom: 12px;">
                            <div>
                                <label style="display: block; font-weight: 600; font-size: 12px; color: #475569; margin-bottom: 4px;">Estimated Sale Price (Optional):</label>
                                <input type="number" id="ai_sale_price" style="width: 100%; padding: 6px 10px; border: 1px solid #cbd5e1; border-radius: 6px;" placeholder="e.g. 650">
                            </div>
                            <div>
                                <label style="display: block; font-weight: 600; font-size: 12px; color: #475569; margin-bottom: 4px;">Regular Price (Optional):</label>
                                <input type="number" id="ai_regular_price" style="width: 100%; padding: 6px 10px; border: 1px solid #cbd5e1; border-radius: 6px;" placeholder="e.g. 950">
                            </div>
                            <div>
                                <label style="display: block; font-weight: 600; font-size: 12px; color: #475569; margin-bottom: 4px;">Product Image (Optional):</label>
                                <button type="button" id="ai_pick_image_btn" class="button" style="width: 100%; height: 32px; font-size: 12px; display: flex; align-items: center; justify-content: center; gap: 4px;">
                                    🖼️ Select Image
                                </button>
                                <input type="hidden" id="ai_image_url">
                            </div>
                        </div>

                        <div style="margin-bottom: 12px;">
                            <label style="display: block; font-weight: 600; font-size: 12px; color: #475569; margin-bottom: 4px;">Additional Instructions / Special Specs / Prompt (Optional):</label>
                            <input type="text" id="ai_custom_prompt" style="width: 100%; padding: 7px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px;" placeholder="e.g. ১ বছরের ওয়ারেন্টি, বিদ্যুৎ সাশ্রয়ী, স্টেইনলেস স্টিল ব্লেড...">
                        </div>

                        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 12px;">
                            <div id="ai_image_preview_wrap" style="display: none; align-items: center; gap: 8px; font-size: 12px; color: #16a34a;">
                                <img id="ai_image_preview" src="" style="width: 32px; height: 32px; object-fit: cover; border-radius: 4px; border: 1px solid #cbd5e1;">
                                <span>Image Attached</span>
                            </div>
                            <div style="margin-left: auto; display: flex; align-items: center; gap: 8px;">
                                <button type="button" id="fmb-btn-generate-manual-prompt" class="button" style="background: #10b981; color: #ffffff; border: none; padding: 8px 16px; font-size: 13px; font-weight: 700; border-radius: 8px; box-shadow: 0 4px 8px -1px rgba(16,185,129,0.35); cursor: pointer;">
                                    <span>📝</span> Generate Prompt (Manual AI)
                                </button>
                                <button type="button" id="fmb-btn-generate-ai" class="button button-primary" style="background: linear-gradient(135deg, #4f46e5 0%, #3730a3 100%); border: none; padding: 8px 24px; font-size: 14px; font-weight: 700; border-radius: 8px; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 4px 8px -1px rgba(79,70,229,0.35); cursor: pointer;">
                                    <span id="ai-gen-icon">✨</span> <span id="ai-gen-text">Generate Product Suite</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Alert: Unknown Product & Master AI Prompt Generator -->
                    <div id="ai_unknown_product_alert" style="display: none; background: #fffbeb; border: 1.5px solid #fde047; border-radius: 12px; padding: 18px; margin-bottom: 20px;">
                        <div style="display: flex; align-items: flex-start; gap: 12px;">
                            <span style="font-size: 24px;">💡</span>
                            <div style="flex: 1;">
                                <h4 style="margin: 0 0 6px 0; color: #a16207; font-size: 15px; font-weight: 700;">
                                    This product is new to our offline library!
                                </h4>
                                <p style="margin: 0 0 10px 0; font-size: 13px; color: #854d0e; line-height: 1.5;">
                                    Our system has a library of 50+ popular categories, but there is no specific data for this product yet. You can copy the <strong>1-click ready-made master prompt</strong> below and give it to ChatGPT or Gemini to write the perfect structure instantly. If you save it in our library, it will auto-generate for all such products in the future!
                                </p>

                                <div style="background: #ffffff; border: 1px solid #fde047; border-radius: 8px; padding: 10px; margin-bottom: 10px;">
                                    <textarea id="unknown_ai_prompt_text" rows="4" style="width: 100%; font-family: monospace; font-size: 11px; color: #334155; border: none; background: transparent; resize: none;" readonly></textarea>
                                </div>

                                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                                    <button type="button" id="btn-copy-master-prompt" class="button button-primary" style="background: #d97706; border-color: #b45309; font-weight: 700; font-size: 12px;">
                                        📋 Copy AI Master Prompt
                                    </button>
                                    <button type="button" id="btn-open-paste-json-modal" class="button button-secondary" style="font-weight: 600; font-size: 12px;">
                                        📥 Paste AI Output & Save to Library
                                    </button>
                                    <button type="button" id="btn-proceed-smart-template" class="button" style="font-weight: 600; font-size: 12px; margin-left: auto;">
                                        ⚡ Proceed with General Smart Template
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Loader -->
                    <div id="ai_loading_indicator" style="display: none; text-align: center; padding: 30px 20px;">
                        <div class="spinner is-active" style="float: none; margin: 0 auto 12px; width: 32px; height: 32px;"></div>
                        <strong style="color: #4f46e5; font-size: 16px; display: block;">AI & Knowledge Engine are generating full data...</strong>
                        <span style="color: #64748b; font-size: 13px;">Selected tone, landing page sections, comparison table, FAQs, and Facebook ads are being generated.</span>
                    </div>

                    <!-- Generated Results All-in-One Container -->
                    <div id="ai_results_container" style="display: none;">
                        
                        <div style="background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%); border: 1.5px solid #86efac; border-radius: 12px; padding: 14px 18px; margin-bottom: 18px; display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <strong style="color: #166534; font-size: 15px; display: flex; align-items: center; gap: 8px;">
                                    🎉 Full AI Product, Landing Page & Marketing Data is Ready!
                                </strong>
                                <div style="color: #15803d; font-size: 12px; margin-top: 2px;">
                                    Preview all sections below and apply them to the product with 1-click, or copy the ad copy.
                                </div>
                            </div>
                            <span id="ai-source-badge" style="background: #16a34a; color: #fff; font-size: 11px; font-weight: 700; padding: 4px 12px; border-radius: 12px;">
                                Ready to Apply
                            </span>
                        </div>

                        <!-- 1. Product Core Fields -->
                        <div style="background: #ffffff; border: 1.5px solid #e2e8f0; border-radius: 12px; padding: 16px; margin-bottom: 16px;">
                            <strong style="color: #1e293b; font-size: 13px; display: block; margin-bottom: 10px;">🏷️ Product Title & Pricing</strong>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                                <div>
                                    <label style="font-weight: 700; font-size: 12px; color: #334155;">Product Title:</label>
                                    <input type="text" id="res_title" style="width: 100%; font-weight: 700; margin-top: 3px;">
                                </div>
                                <div style="display: flex; gap: 10px;">
                                    <div style="flex: 1;">
                                        <label style="font-weight: 700; font-size: 12px; color: #334155;">Sale Price:</label>
                                        <input type="number" id="res_sale_price" style="width: 100%; font-weight: 700; color: #16a34a; margin-top: 3px;">
                                    </div>
                                    <div style="flex: 1;">
                                        <label style="font-weight: 700; font-size: 12px; color: #334155;">Regular Price:</label>
                                        <input type="number" id="res_regular_price" style="width: 100%; font-weight: 600; margin-top: 3px;">
                                    </div>
                                </div>
                            </div>

                            <div style="margin-bottom: 12px;">
                                <label style="font-weight: 700; font-size: 12px; color: #334155;">Short Description (Excerpt):</label>
                                <textarea id="res_short_desc" rows="3" style="width: 100%; font-size: 13px; margin-top: 3px;"></textarea>
                            </div>

                            <div>
                                <label style="font-weight: 700; font-size: 12px; color: #334155;">Main Product Description (Full HTML):</label>
                                <textarea id="res_full_desc" rows="5" style="width: 100%; font-family: monospace; font-size: 12px; margin-top: 3px;"></textarea>
                            </div>
                        </div>

                        <!-- 2. Feature 4: Facebook & TikTok Marketing Ad Copy Box (PROMINENT) -->
                        <div style="background: #fdf4ff; border: 1.5px solid #f0abfc; border-radius: 12px; padding: 18px; margin-bottom: 16px;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                                <strong style="color: #86198f; font-size: 14px; display: flex; align-items: center; gap: 8px;">
                                    📢 Feature 4: Facebook & TikTok Ad Copy
                                </strong>
                                <button type="button" id="btn-copy-ad-copy" class="button button-primary" style="background: #a21caf; border-color: #86198f; font-weight: 700; font-size: 13px; box-shadow: 0 2px 4px rgba(162,28,175,0.3);">
                                    📋 Copy Ad Copy
                                </button>
                            </div>
                            <textarea id="res_ad_copy_text" rows="7" style="width: 100%; font-size: 13px; line-height: 1.5; border: 1px solid #e879f9; border-radius: 8px; padding: 10px; background: #fff;"></textarea>
                            <small style="color: #701a75; font-size: 11px; display: block; margin-top: 4px;">
                                * Use this caption directly for Facebook page posts or ad manager campaigns.
                            </small>
                        </div>

                        <!-- 3. Feature 5: Order Bumps & Upsells Suggestions -->
                        <div style="background: #fff7ed; border: 1.5px solid #fed7aa; border-radius: 12px; padding: 16px; margin-bottom: 16px;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                                <strong style="color: #9a3412; font-size: 14px; display: flex; align-items: center; gap: 8px;">
                                    ⚡ Feature 5: Landing Page Section 3 (Upsells) & Section 4 (Order Bumps) Suggestions
                                </strong>
                                <span style="background: #ea580c; color: #fff; font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 10px;">
                                    AOV Booster
                                </span>
                            </div>
                            <p style="font-size: 12px; color: #7c2d12; margin-top: 0; margin-bottom: 10px;">
                                The following order bumps and upsells have been suggested for this product, which can be applied with 1-click:
                            </p>
                            <div id="res_bump_suggestions"></div>
                        </div>

                        <!-- 4. Landing Page Section 1: Steps -->
                        <div style="background: #f0fdf4; border: 1.5px solid #bbf7d0; border-radius: 12px; padding: 16px; margin-bottom: 16px;">
                            <strong style="color: #166534; font-size: 13px; display: block; margin-bottom: 8px;">📋 Section 1: Usage Steps</strong>
                            <input type="text" id="res_steps_title" style="width: 100%; margin-bottom: 8px; font-weight: 600;" value="Easy Usage Steps">
                            <div id="res_steps_list"></div>
                        </div>

                        <!-- 5. Landing Page Section 2: Info Cards -->
                        <div style="background: #fefce8; border: 1.5px solid #fef08a; border-radius: 12px; padding: 16px; margin-bottom: 16px;">
                            <strong style="color: #854d0e; font-size: 13px; display: block; margin-bottom: 8px;">🌟 Section 2: Why is it the best? (Info Cards)</strong>
                            <input type="text" id="res_cards_title" style="width: 100%; margin-bottom: 8px; font-weight: 600;" value="Why is it the best for you?">
                            <div id="res_cards_list"></div>
                        </div>

                        <!-- 6. Feature 2: Comparison Table & Feature 3: FAQs -->
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 16px;">
                            <div style="background: #f8fafc; border: 1.5px solid #e2e8f0; border-radius: 12px; padding: 14px;">
                                <strong style="color: #0f172a; font-size: 13px; display: block; margin-bottom: 8px;">📊 Feature 2: Product Comparison Table</strong>
                                <div id="res_comparison_preview" style="max-height: 180px; overflow-y: auto;"></div>
                            </div>
                            <div style="background: #eff6ff; border: 1.5px solid #bfdbfe; border-radius: 12px; padding: 14px;">
                                <strong style="color: #1e40af; font-size: 13px; display: block; margin-bottom: 8px;">❓ Feature 3: Frequently Asked Questions (FAQs)</strong>
                                <div id="res_faqs_preview" style="max-height: 180px; overflow-y: auto;"></div>
                            </div>
                        </div>

                        <!-- 7. Category & SEO -->
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                            <div>
                                <label style="font-weight: 700; font-size: 12px; color: #334155;">Suggested Category (Auto-checked):</label>
                                <input type="text" id="res_suggested_cat" style="width: 100%; font-weight: 700; color: #2563eb;" readonly>
                            </div>
                            <div>
                                <label style="font-weight: 700; font-size: 12px; color: #334155;">SEO Focus Keywords:</label>
                                <input type="text" id="res_seo_keywords" style="width: 100%;">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div style="background: #f8fafc; border-top: 1px solid #e2e8f0; padding: 14px 24px; display: flex; justify-content: space-between; align-items: center;">
                    <button type="button" class="button fmb-ai-close-modal">Close</button>
                    <button type="button" id="fmb-btn-apply-ai-data" class="button button-primary" style="display: none; background: #16a34a; border-color: #16a34a; font-weight: 700; font-size: 14px; padding: 6px 22px; border-radius: 6px; box-shadow: 0 2px 5px rgba(22,163,74,0.3);">
                        ✅ Apply to Product (1-Click)
                    </button>
                </div>
            </div>
        </div>

        <!-- Sub-Modal: Add Custom Knowledge via JSON -->
        <div id="modal-add-custom-knowledge" style="display: none; position: fixed; z-index: 9999999; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.75); backdrop-filter: blur(5px); align-items: center; justify-content: center;">
            <div style="background: #fff; width: 680px; max-width: 94%; border-radius: 14px; padding: 24px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.35); position: relative; max-height: 90vh; overflow-y: auto;">
                <span class="btn-close-custom-modal" style="position: absolute; top: 14px; right: 18px; font-size: 24px; cursor: pointer; color: #94a3b8; font-weight: bold;">&times;</span>
                <h3 style="margin: 0 0 8px 0; font-size: 17px; font-weight: 700; color: #1e293b; display: flex; align-items: center; gap: 8px;">
                    📥 Save JSON from AI to Library
                </h3>
                <p style="font-size: 13px; color: #64748b; margin-top: 0; line-height: 1.4;">
                    Paste the JSON code from ChatGPT or Gemini below. Tags and keywords will automatically work for multiple related products.
                </p>

                <div style="margin-bottom: 12px;">
                    <label style="font-weight: 700; font-size: 12px; color: #334155; display: block; margin-bottom: 4px;">Paste JSON Data <span style="color:#ef4444;">*</span>:</label>
                    <textarea id="custom_niche_json" rows="9" style="width: 100%; font-family: monospace; font-size: 12px; border: 1.5px solid #cbd5e1; border-radius: 8px; padding: 8px 12px;" placeholder='{"niche_name": "...", "keywords": ["tag1", "tag2", "ট্যাগ৩"], ...}'></textarea>
                </div>

                <div style="margin-bottom: 12px;">
                    <label style="font-weight: 700; font-size: 12px; color: #334155; display: block; margin-bottom: 4px;">
                        Tags / Search Keywords (Comma separated):
                    </label>
                    <input type="text" id="custom_niche_tags" style="width: 100%; font-size: 13px; padding: 7px 12px; border: 1px solid #cbd5e1; border-radius: 6px;" placeholder="e.g. iron, steam iron, dry iron, ইস্ত্রি, আয়রন, কাপড় ইস্ত্রি, steamer">
                    <small style="color: #64748b; font-size: 11px; display: block; margin-top: 3px;">
                        * This template will work if any of these tags match the product name.
                    </small>
                </div>

                <div style="display: grid; grid-template-columns: 1.5fr 1fr; gap: 10px; margin-bottom: 16px;">
                    <div>
                        <label style="font-weight: 600; font-size: 12px; color: #334155;">Niche / Category Name:</label>
                        <input type="text" id="custom_niche_name" style="width: 100%; font-size: 13px; padding: 6px 10px; border: 1px solid #cbd5e1; border-radius: 6px;" placeholder="e.g. Home Appliances: Steam Iron">
                    </div>
                    <div>
                        <label style="font-weight: 600; font-size: 12px; color: #334155;">Suggested WC Category:</label>
                        <input type="text" id="custom_niche_cat" style="width: 100%; font-size: 13px; padding: 6px 10px; border: 1px solid #cbd5e1; border-radius: 6px;" placeholder="e.g. Home Appliances">
                    </div>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 10px; border-top: 1px solid #e2e8f0; padding-top: 14px;">
                    <button type="button" class="button btn-close-custom-modal">Cancel</button>
                    <button type="button" id="btn-save-custom-knowledge" class="button button-primary" style="background: #16a34a; border-color: #16a34a; font-weight: 700; font-size: 13px; padding: 5px 18px;">
                        💾 Save to Library & Generate
                    </button>
                </div>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            let generatedData = null;

            // Open Main Modal
            $(document).on('click', '.fmb-trigger-ai-modal', function(e) {
                e.preventDefault();
                let $btn = $(this);
                let currentTitle = $btn.data('title') || $('#title').val();
                let currentPrice = $btn.data('price') || $('#_sale_price').val();
                let currentImg = $btn.data('img') || '';

                if (currentTitle && !$('#ai_product_name').val()) {
                    $('#ai_product_name').val(currentTitle);
                }
                if (currentPrice && !$('#ai_sale_price').val()) {
                    $('#ai_sale_price').val(currentPrice);
                }
                if (currentImg && !$('#ai_image_url').val()) {
                    $('#ai_image_url').val(currentImg);
                    $('#ai_image_preview').attr('src', currentImg);
                    $('#ai_image_preview_wrap').fadeIn();
                }

                $('#fmb-ai-modal').fadeIn(200).css('display', 'flex');
            });

            // Tab Switching
            $('.fmb-tab-btn').on('click', function() {
                $('.fmb-tab-btn').removeClass('active').css({ 'border-bottom': 'none', 'color': '#64748b', 'font-weight': '600' });
                $(this).addClass('active').css({ 'border-bottom': '3px solid #4f46e5', 'color': '#4f46e5', 'font-weight': '700' });
                $('.fmb-tab-pane').hide();
                $('#' + $(this).data('tab')).fadeIn(150);
            });

            // Close Modals
            $('.fmb-ai-close-modal, #fmb-ai-modal').on('click', function(e) {
                if (e.target === this || $(e.target).hasClass('fmb-ai-close-modal')) {
                    $('#fmb-ai-modal').fadeOut(150);
                }
            });
            $('.btn-close-custom-modal, #modal-add-custom-knowledge').on('click', function(e) {
                if (e.target === this || $(e.target).hasClass('btn-close-custom-modal')) {
                    $('#modal-add-custom-knowledge').fadeOut(150);
                }
            });

            // Drawers toggle
            $('#fmb-btn-ai-settings').on('click', function() {
                $('#fmb-ai-settings-drawer').slideToggle(150);
                $('#fmb-ai-library-drawer').slideUp(150);
            });
            $('#fmb-btn-toggle-library').on('click', function() {
                $('#fmb-ai-library-drawer').slideToggle(150);
                $('#fmb-ai-settings-drawer').slideUp(150);
                loadCustomLibraryList();
            });

            // Save Gemini API Key
            $('#fmb_save_gemini_key').on('click', function() {
                let key = $('#fmb_gemini_api_key_input').val();
                $.post(ajaxurl, {
                    action: 'fmb_save_ai_settings',
                    gemini_key: key,
                    _nonce: '<?= wp_create_nonce("fmb_ai_nonce") ?>'
                }, function(res) {
                    if (res.success) {
                        alert('API Key successfully saved!');
                        $('#fmb-ai-settings-drawer').slideUp(150);
                    }
                });
            });

            // Image Picker via WP Media
            let mediaFrame;
            $('#ai_pick_image_btn').on('click', function(e) {
                e.preventDefault();
                if (mediaFrame) {
                    mediaFrame.open();
                    return;
                }
                mediaFrame = wp.media({
                    title: 'Select Product Image for AI',
                    button: { text: 'Use Image' },
                    multiple: false
                });
                mediaFrame.on('select', function() {
                    let attachment = mediaFrame.state().get('selection').first().toJSON();
                    $('#ai_image_url').val(attachment.url);
                    $('#ai_image_preview').attr('src', attachment.url);
                    $('#ai_image_preview_wrap').fadeIn();
                });
                mediaFrame.open();
            });

            // Generate AI Data
            $('#fmb-btn-generate-ai, #btn-proceed-smart-template').on('click', function() {
                let name = $('#ai_product_name').val().trim();
                if (!name) {
                    alert('দয়া করে প্রডাক্টের নাম লিখুন।');
                    $('#ai_product_name').focus();
                    return;
                }

                let forceFallback = ($(this).attr('id') === 'btn-proceed-smart-template') ? 1 : 0;
                let copyTone = $('#ai_copy_tone').val();

                $('#ai_loading_indicator').show();
                $('#ai_results_container').hide();
                $('#ai_unknown_product_alert').hide();
                $('#fmb-btn-apply-ai-data').hide();
                $('#fmb-btn-generate-ai').prop('disabled', true);
                $('#ai-gen-text').text('Generating...');

                $.post(ajaxurl, {
                    action: 'fmb_generate_ai_product_data',
                    product_name: name,
                    copy_tone: copyTone,
                    sale_price: $('#ai_sale_price').val(),
                    regular_price: $('#ai_regular_price').val(),
                    custom_prompt: $('#ai_custom_prompt').val(),
                    image_url: $('#ai_image_url').val(),
                    force_fallback: forceFallback,
                    _nonce: '<?= wp_create_nonce("fmb_ai_nonce") ?>'
                }, function(res) {
                    $('#ai_loading_indicator').hide();
                    $('#fmb-btn-generate-ai').prop('disabled', false);
                    $('#ai-gen-text').text('Regenerate (পুনরায় তৈরি)');

                    if (res.success && res.data) {
                        if (res.data.status === 'unknown_niche' && !forceFallback) {
                            $('#ai_unknown_product_alert h4').text('This product is new to our offline library!');
                            $('#ai_unknown_product_alert p').html('Our system has a library of 50+ popular categories, but there is no specific data for this product yet. You can copy the <strong>1-click ready-made master prompt</strong> below and give it to ChatGPT or Gemini to write the perfect structure instantly. If you save it in our library, it will auto-generate for all such products in the future!');
                            $('#unknown_ai_prompt_text').val(res.data.master_prompt);
                            $('#ai_unknown_product_alert').slideDown(200);
                            return;
                        }

                        generatedData = res.data;
                        
                        // Populate Preview Fields
                        $('#res_title').val(generatedData.title);
                        $('#res_sale_price').val(generatedData.sale_price);
                        $('#res_regular_price').val(generatedData.regular_price);
                        $('#res_short_desc').val(generatedData.short_description);
                        $('#res_full_desc').val(generatedData.full_description);
                        $('#res_steps_title').val(generatedData.steps_title || 'Easy Usage Steps');
                        $('#res_cards_title').val(generatedData.cards_title || 'Why is it the best for you?');
                        $('#res_suggested_cat').val(generatedData.suggested_category);
                        $('#res_seo_keywords').val(generatedData.seo_keywords);

                        if (generatedData.detected_niche) {
                            let isAI = generatedData.detected_niche.includes('Cloud AI');
                            let sourceText = isAI ? '🤖 Google Gemini AI' : '💾 Database (' + generatedData.detected_niche + ')';
                            $('#detected-niche-name').text(sourceText);
                            $('#detected-niche-badge').show();
                            
                            // Update Main Badge in results container
                            $('#ai-source-badge').html(isAI ? '🤖 Generated by Gemini API' : '💾 Generated from Saved Database').css('background', isAI ? '#2563eb' : '#16a34a');
                        }

                        // Steps Preview
                        let stepsHtml = '';
                        if (generatedData.usage_steps && generatedData.usage_steps.length) {
                            generatedData.usage_steps.forEach(function(s, idx) {
                                stepsHtml += '<div style="margin-bottom:4px; font-size:12px; color:#166534;">' + (idx+1) + '. ' + s + '</div>';
                            });
                        }
                        $('#res_steps_list').html(stepsHtml);

                        // Cards Preview
                        let cardsHtml = '';
                        if (generatedData.info_cards && generatedData.info_cards.length) {
                            generatedData.info_cards.forEach(function(c, idx) {
                                cardsHtml += '<div style="margin-bottom:4px; font-size:12px; color:#854d0e;"><strong>⭐ ' + c.title + ':</strong> ' + c.desc + '</div>';
                            });
                        }
                        $('#res_cards_list').html(cardsHtml);

                        // Comparison Table Preview
                        if (generatedData.comparison_table_html) {
                            $('#res_comparison_preview').html(generatedData.comparison_table_html);
                        }

                        // FAQs Preview
                        let faqsHtml = '';
                        if (generatedData.faqs && generatedData.faqs.length) {
                            generatedData.faqs.forEach(function(f) {
                                faqsHtml += '<div style="background:#fff; border:1px solid #dbeafe; border-radius:6px; padding:10px; margin-bottom:8px;">' +
                                    '<strong style="color:#1e3a8a; font-size:13px; display:block;">❓ ' + f.q + '</strong>' +
                                    '<div style="color:#334155; font-size:12px; margin-top:4px;">' + f.a + '</div>' +
                                    '</div>';
                            });
                        }
                        $('#res_faqs_preview').html(faqsHtml);

                        // Ad Copy Preview
                        if (generatedData.ad_copy) {
                            $('#res_ad_copy_text').val(generatedData.ad_copy);
                        }

                        // Upsell & Order Bump Suggestions Preview
                        let bumpsHtml = '';
                        if (generatedData.order_bump_suggestions && generatedData.order_bump_suggestions.length) {
                            generatedData.order_bump_suggestions.forEach(function(b) {
                                bumpsHtml += '<div style="background:#fff; border:1.5px dashed #fb923c; border-radius:8px; padding:12px; margin-bottom:10px; display:flex; justify-content:space-between; align-items:center;">' +
                                    '<div><strong style="color:#c2410c; font-size:13px;">🎁 ' + b.title + '</strong>' +
                                    '<div style="color:#7c2d12; font-size:12px; margin-top:2px;">' + b.reason + '</div></div>' +
                                    '<span style="background:#ffedd5; color:#9a3412; font-weight:700; font-size:12px; padding:4px 10px; border-radius:6px;">' + b.discount + '</span>' +
                                    '</div>';
                            });
                        }
                        $('#res_bump_suggestions').html(bumpsHtml);

                        $('#ai_results_container').slideDown(200);
                        $('#fmb-btn-apply-ai-data').fadeIn(200);
                    } else {
                        alert(res.data && res.data.message ? res.data.message : 'Error generating content.');
                    }
                }).fail(function() {
                    $('#ai_loading_indicator').hide();
                    $('#fmb-btn-generate-ai').prop('disabled', false);
                    $('#ai-gen-text').text('Generate Product Suite');
                    alert('সার্ভার রেসপন্স করতে পারেনি। অনুগ্রহ করে আবার চেষ্টা করুন।');
                });
            });

            // 1-Click Copy Ad Copy
            $('#btn-copy-ad-copy').on('click', function() {
                let copyText = document.getElementById("res_ad_copy_text");
                copyText.select();
                copyText.setSelectionRange(0, 99999);
                navigator.clipboard.writeText(copyText.value);
                alert('✅ ফেসবুক ও টিকটক অ্যাড কপি সফলভাবে কপি হয়েছে!');
            });

            // Copy Master Prompt to Clipboard
            $('#btn-copy-master-prompt').on('click', function() {
                let copyText = document.getElementById("unknown_ai_prompt_text");
                copyText.select();
                copyText.setSelectionRange(0, 99999);
                navigator.clipboard.writeText(copyText.value);
                alert('✅ AI Master Prompt কপি হয়েছে! এটি ChatGPT বা Gemini-তে পেস্ট করুন এবং প্রাপ্ত JSON ডাটা আমাদের লাইব্রেরিতে সেভ করুন।');
            });

            // Open Paste JSON Modal
            $('#btn-open-paste-json-modal, #btn-open-add-custom-niche').on('click', function() {
                let name = $('#ai_product_name').val();
                if (name && !$('#custom_niche_keyword').val()) {
                    $('#custom_niche_keyword').val(name);
                }
                $('#modal-add-custom-knowledge').fadeIn(200).css('display', 'flex');
            });

            // Smart JSON cleaner & parser in Browser
            function cleanAndParseJSON(raw) {
                if (!raw) return null;
                let clean = raw.trim();
                clean = clean.replace(/^```[a-z]*\s*/i, '').replace(/```\s*$/i, '');
                let startIdx = clean.indexOf('{');
                let endIdx = clean.lastIndexOf('}');
                if (startIdx !== -1 && endIdx !== -1 && endIdx > startIdx) {
                    clean = clean.substring(startIdx, endIdx + 1);
                }
                clean = clean.replace(/[\u201C\u201D]/g, '"').replace(/[\u2018\u2019`]/g, "'");
                clean = clean.replace(/,\s*([\]}])/g, '$1');
                try {
                    return JSON.parse(clean);
                } catch (e1) {
                    return null;
                }
            }

            // Auto-parse pasted JSON to prefill tags, niche name, and category
            $('#custom_niche_json').on('input paste change', function() {
                setTimeout(function() {
                    let raw = $('#custom_niche_json').val();
                    let parsed = cleanAndParseJSON(raw);
                    if (parsed) {
                        if (parsed.niche_name && !$('#custom_niche_name').val()) {
                            $('#custom_niche_name').val(parsed.niche_name);
                        }
                        if (parsed.category && !$('#custom_niche_cat').val()) {
                            $('#custom_niche_cat').val(parsed.category);
                        }
                        if (parsed.keywords && Array.isArray(parsed.keywords)) {
                            let currentTags = $('#custom_niche_tags').val();
                            let combinedTags = parsed.keywords;
                            if (currentTags) {
                                combinedTags = combinedTags.concat(currentTags.split(',').map(s => s.trim()));
                            }
                            $('#custom_niche_tags').val(Array.from(new Set(combinedTags)).join(', '));
                        } else if (parsed.keyword) {
                            $('#custom_niche_tags').val(parsed.keyword);
                        }
                    }
                }, 100);
            });
        // Handle Manual Prompt Generation Button
        $('#fmb-btn-generate-manual-prompt').on('click', function() {
            let name = $('#ai_product_name').val().trim();
            if (!name) {
                alert('Please enter a product name first.');
                return;
            }

            let btn = $(this);
            let originalText = btn.html();
            btn.html('Generating...').prop('disabled', true);

            $.post(ajaxurl, {
                action: 'fmb_generate_manual_prompt',
                product_name: name,
                copy_tone: $('#ai_copy_tone').val(),
                sale_price: $('#ai_sale_price').val(),
                regular_price: $('#ai_regular_price').val(),
                custom_prompt: $('#ai_custom_prompt').val(),
                _nonce: '<?= wp_create_nonce("fmb_ai_nonce") ?>'
            }, function(res) {
                btn.html(originalText).prop('disabled', false);
                if (res.success) {
                    $('#ai_results_container').hide();
                    $('#ai_unknown_product_alert h4').text('আপনার কাস্টম AI প্রম্পট প্রস্তুত! 🚀');
                    $('#ai_unknown_product_alert p').html('নিচের <strong>মাস্টার প্রম্পটটি</strong> কপি করে ChatGPT বা Gemini-তে দিন। এরপর AI আপনাকে যে ডাটা (JSON) দিবে, তা নিচের বাটনে ক্লিক করে পেস্ট করুন।');
                    $('#unknown_ai_prompt_text').val(res.data.prompt);
                    $('#ai_unknown_product_alert').slideDown(200);
                }
            });
        });

            // Save Custom Knowledge AJAX (Ultra Resilient)
            $('#btn-save-custom-knowledge').on('click', function() {
                let tags = $('#custom_niche_tags').val().trim();
                let json = $('#custom_niche_json').val().trim();
                let nicheName = $('#custom_niche_name').val().trim();
                let category = $('#custom_niche_cat').val().trim();

                if (!json) {
                    alert('দয়া করে AI থেকে পাওয়া Paste JSON Data।');
                    return;
                }

                let parsedObj = cleanAndParseJSON(json);
                let payload = parsedObj ? JSON.stringify(parsedObj) : '';

                $('#btn-save-custom-knowledge').prop('disabled', true).text('Saving...');

                $.post(ajaxurl, {
                    action: 'fmb_add_custom_knowledge',
                    payload: payload,
                    json_data: json,
                    tags: tags,
                    niche_name: nicheName,
                    category: category,
                    _nonce: '<?= wp_create_nonce("fmb_ai_nonce") ?>'
                }, function(res) {
                    $('#btn-save-custom-knowledge').prop('disabled', false).text('💾 Save to Library & Generate');

                    if (res.success) {
                        alert('🎉 New product niche and tags saved successfully to Knowledge Library!');
                        $('#modal-add-custom-knowledge').fadeOut(150);
                        $('#fmb-btn-generate-ai').click();
                    } else {
                        alert(res.data && res.data.message ? res.data.message : 'Error saving JSON');
                    }
                }).fail(function() {
                    $('#btn-save-custom-knowledge').prop('disabled', false).text('💾 Save to Library & Generate');
                    alert('Server error! Please try again.');
                });
            });

            function loadCustomLibraryList() {
                $.post(ajaxurl, {
                    action: 'fmb_get_knowledge_library',
                    _nonce: '<?= wp_create_nonce("fmb_ai_nonce") ?>'
                }, function(res) {
                    if (res.success && res.data) {
                        let html = '<div style="display:flex; flex-wrap:wrap; gap:6px;">';
                        res.data.built_in.forEach(function(item) {
                            html += '<span style="background:#dcfce7; color:#15803d; border:1px solid #86efac; padding:2px 8px; border-radius:12px; font-size:11px;">' + item + '</span>';
                        });
                        if (res.data.custom && res.data.custom.length) {
                            res.data.custom.forEach(function(item) {
                                html += '<span style="background:#fef3c7; color:#92400e; border:1px solid #fde047; padding:2px 8px; border-radius:12px; font-size:11px;">⭐ ' + item.name + ' (' + item.tag_count + ' tags)</span>';
                            });
                        }
                        html += '</div>';
                        $('#custom-library-list').html(html);
                    }
                });
            }

            // 1-Click Apply to WooCommerce Editor & Meta Fields
            $('#fmb-btn-apply-ai-data').on('click', function() {
                if (!generatedData) return;

                // 1. Set Title
                let finalTitle = $('#res_title').val();
                $('#title').val(finalTitle);
                if ($('#title-prompt-text').length) {
                    $('#title-prompt-text').addClass('screen-reader-text');
                }

                // 2. Set Prices
                $('#_regular_price').val($('#res_regular_price').val());
                $('#_sale_price').val($('#res_sale_price').val());

                // 3. Set Short Description
                let shortDescVal = $('#res_short_desc').val();
                if (typeof tinyMCE !== 'undefined' && tinyMCE.get('excerpt') && !tinyMCE.get('excerpt').isHidden()) {
                    tinyMCE.get('excerpt').setContent(shortDescVal);
                }
                $('#excerpt').val(shortDescVal);

                // 4. Set Main Description with Comparison Table and FAQs appended nicely
                let finalFullDesc = $('#res_full_desc').val();
                if (generatedData.comparison_table_html) {
                    finalFullDesc += '\n\n' + generatedData.comparison_table_html;
                }
                if (generatedData.faqs_html) {
                    finalFullDesc += '\n\n' + generatedData.faqs_html;
                }

                if (typeof tinyMCE !== 'undefined' && tinyMCE.get('content') && !tinyMCE.get('content').isHidden()) {
                    tinyMCE.get('content').setContent(finalFullDesc);
                }
                $('#content').val(finalFullDesc);

                // 5. Populate Landing Page Data: Section 1 (Steps)
                if (generatedData.usage_steps && generatedData.usage_steps.length) {
                    $('#_fmb_steps_title').val($('#res_steps_title').val());
                    $('#fmb_steps_wrapper').empty();
                    generatedData.usage_steps.forEach(function(stepText) {
                        let stepHtml = '<div class="fmb-repeater-item">' +
                            '<div class="fmb-item-top"><span class="fmb-item-title">📋 Step</span><button type="button" class="fmb-remove-btn">✕ Delete</button></div>' +
                            '<div class="fmb-field-group">' +
                            '<textarea name="_fmb_steps[]" style="width:100%; height:55px;">' + stepText + '</textarea>' +
                            '</div></div>';
                        $('#fmb_steps_wrapper').append(stepHtml);
                    });
                }

                // 6. Populate Landing Page Data: Section 2 (Info Cards)
                if (generatedData.info_cards && generatedData.info_cards.length) {
                    $('#_fmb_cards_title').val($('#res_cards_title').val());
                    $('#fmb_cards_wrapper').empty();
                    generatedData.info_cards.forEach(function(card) {
                        let cardHtml = '<div class="fmb-repeater-item">' +
                            '<div class="fmb-item-top"><span class="fmb-item-title">🌟 Info Card</span><button type="button" class="fmb-remove-btn">✕ Delete</button></div>' +
                            '<div class="fmb-field-group" style="margin-bottom:8px;"><label>Image URL</label><input type="text" name="_fmb_card_img[]" value="' + (card.img || '') + '" placeholder="https://..."></div>' +
                            '<div class="fmb-field-group" style="margin-bottom:8px;"><label>Title</label><input type="text" name="_fmb_card_title[]" value="' + card.title + '" placeholder="কার্ডের শিরোনাম"></div>' +
                            '<div class="fmb-field-group"><label>Description</label><textarea name="_fmb_card_desc[]" placeholder="Description" style="width:100%; height:55px;">' + card.desc + '</textarea></div>' +
                            '</div>';
                        $('#fmb_cards_wrapper').append(cardHtml);
                    });
                }

                // 7. Auto-populate Section 3 (Upsells Title) & Section 4 (Order Bumps)
                if ($('#_fmb_upsell_title').length) {
                    $('#_fmb_upsell_title').val('এর সাথে আরও যা যা নিতে পারেন');
                }
                if ($('#fmb_order_bumps_wrapper').length && generatedData.order_bump_suggestions && generatedData.order_bump_suggestions.length) {
                    let bump = generatedData.order_bump_suggestions[0];
                    if ($('#fmb_order_bumps_wrapper .fmb-repeater-item').length === 0) {
                        let bumpHtml = '<div class="fmb-repeater-item">' +
                            '<div class="fmb-item-top"><span class="fmb-item-title">⚡ Order Bump (AI Suggested)</span><button type="button" class="fmb-remove-btn">✕ Delete</button></div>' +
                            '<div class="fmb-field-group" style="margin-bottom:10px;"><label>Product</label><select class="wc-product-search" name="_fmb_ob_product_id[]" style="width:100%;" data-placeholder="Search product by name or ID..."></select></div>' +
                            '<div class="fmb-grid fmb-grid-2" style="margin-bottom:10px;"><div class="fmb-field-group"><label>Custom Bump Title</label><input type="text" name="_fmb_ob_title[]" value="' + bump.title + '"></div><div class="fmb-field-group"><label>Custom Price Override</label><input type="text" name="_fmb_ob_price[]" placeholder="Default Price"></div></div>' +
                            '<div class="fmb-field-group" style="margin-bottom:10px;"><label>Description / Offer Text</label><textarea name="_fmb_ob_desc[]" style="height:50px;">' + bump.reason + ' (' + bump.discount + ')</textarea></div>' +
                            '<div class="fmb-grid fmb-grid-3" style="margin-bottom:10px;"><div class="fmb-field-group"><label>Target Qty</label><input type="number" name="_fmb_ob_logic_qty[]" value="1"></div><div class="fmb-field-group"><label>Discount Type</label><select name="_fmb_ob_logic_type[]"><option value="percent" selected>Percent Discount (%)</option></select></div><div class="fmb-field-group"><label>Reward Value</label><input type="number" name="_fmb_ob_logic_value[]" value="20"></div></div>' +
                            '</div>';
                        $('#fmb_order_bumps_wrapper').append(bumpHtml);
                    }
                }

                // 8. Auto Check Category if matches
                let suggestedCat = ($('#res_suggested_cat').val() || '').toLowerCase();
                if (suggestedCat) {
                    $('#product_catchecklist label').each(function() {
                        let catLabel = $(this).text().trim().toLowerCase();
                        if (catLabel.indexOf(suggestedCat) !== -1 || suggestedCat.indexOf(catLabel) !== -1) {
                            $(this).find('input[type="checkbox"]').prop('checked', true);
                        }
                    });
                }

                // 9. Auto set SEO Meta if Yoast or RankMath exist
                if ($('#yoast_wpseo_focuskw').length) {
                    $('#yoast_wpseo_focuskw').val($('#res_seo_keywords').val());
                }
                if ($('#rank_math_focus_keyword').length) {
                    $('#rank_math_focus_keyword').val($('#res_seo_keywords').val());
                }

                $('#fmb-ai-modal').fadeOut(150);
                alert('🎉 প্রডাক্ট, ল্যান্ডিং পেজ সেকশন ১, ২, ৩, ৪, তুলনা টেবিল ও FAQs সফলভাবে বসিয়ে দেওয়া হয়েছে!');
            });
        });
        </script>
        <?php
    }

    /**
     * AJAX Handler: Generate Manual Prompt
     */
    public function ajax_generate_manual_prompt() {
        check_ajax_referer('fmb_ai_nonce', '_nonce');
        $product_name = sanitize_text_field($_POST['product_name'] ?? '');
        
        if (empty($product_name)) {
            $product_name = 'Custom Product';
        }

        $master_prompt = $this->generate_master_ai_prompt($product_name);
        
        wp_send_json_success(['prompt' => $master_prompt]);
    }

    /**
     * AJAX Handler: Generate Product Data with Gemini or Multi-Niche Knowledge Graph
     */
    public function ajax_generate_product_data() {
        check_ajax_referer('fmb_ai_nonce', '_nonce');
        
        $product_name   = sanitize_text_field($_POST['product_name'] ?? '');
        $copy_tone      = sanitize_text_field($_POST['copy_tone'] ?? 'problem_solving');
        $sale_price     = floatval($_POST['sale_price'] ?? 0);
        $regular_price  = floatval($_POST['regular_price'] ?? 0);
        $custom_prompt  = sanitize_text_field($_POST['custom_prompt'] ?? '');
        $image_url      = esc_url_raw($_POST['image_url'] ?? '');
        $force_fallback = intval($_POST['force_fallback'] ?? 0);

        if (empty($product_name)) {
            wp_send_json_error(['message' => 'Product name is required.']);
        }

        $gemini_key = get_option('fmb_gemini_ai_api_key', '');
        $generated = null;

        // 1. Try Google Gemini API if key is set and force_fallback is false
        if (!empty($gemini_key) && !$force_fallback) {
            $generated = $this->call_gemini_api($gemini_key, $product_name, $copy_tone, $sale_price, $regular_price, $custom_prompt, $image_url);
        }

        // 2. If no Gemini API or failed: Use Multi-Niche Knowledge Graph
        if (!$generated || !is_array($generated)) {
            $generated = $this->generate_from_knowledge_base($product_name, $copy_tone, $sale_price, $regular_price, $custom_prompt, $image_url, $force_fallback);
        }

        wp_send_json_success($generated);
    }

    /**
     * Gemini API Handler with Full Marketing Suite Output
     */
    private function call_gemini_api($api_key, $name, $tone, $sale_price, $reg_price, $prompt, $image_url) {
        $endpoint = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=' . urlencode($api_key);

        $system_instruction = "You are a top-tier eCommerce copywriter specializing in high-converting Bangladeshi landing pages and Facebook Ads.
Tone requested: {$tone} (e.g. FOMO urgency, Problem-solving, Premium luxury, or Simple).
Return ONLY valid, raw JSON (no markdown formatting, no backticks) with:
{
    \"title\": \"Bengali/English high converting product title matching tone\",
    \"sale_price\": 650,
    \"regular_price\": 950,
    \"short_description\": \"Punchy 3-4 bullet points in Bengali\",
    \"full_description\": \"Rich, beautiful HTML formatted product sales copy in Bengali with emojis, benefits, specifications table, and delivery guarantee\",
    \"steps_title\": \"Easy Usage Steps\",
    \"usage_steps\": [\"ধাপ ১...\", \"ধাপ ২...\", \"ধাপ ৩...\", \"ধাপ ৪...\"],
    \"cards_title\": \"Why is it the best for you?\",
    \"info_cards\": [
        {\"title\": \"...\", \"desc\": \"...\", \"img\": \"\"},
        {\"title\": \"...\", \"desc\": \"...\", \"img\": \"\"},
        {\"title\": \"...\", \"desc\": \"...\", \"img\": \"\"}
    ],
    \"faqs\": [
        {\"q\": \"ডেলিভারি পেতে কত দিন সময় লাগবে?\", \"a\": \"ঢাকার ভিতরে ২৪-৪৮ ঘণ্টা এবং ঢাকার বাইরে ২-৩ দিনের মধ্যে হোম ডেলিভারি পেয়ে যাবেন।\"},
        {\"q\": \"পণ্য হাতে পেয়ে চেক করে টাকা দিতে পারব?\", \"a\": \"হ্যাঁ, অবশ্যই! ডেলিভারি ম্যানের সামনে প্যাকেট খুলে চেক করে ক্যাশ অন ডেলিভারিতে মূল্য পরিশোধ করতে পারবেন।\"},
        {\"q\": \"কোনো সমস্যা থাকলে কি রিপ্লেসমেন্ট পাব?\", \"a\": \"কোনো ত্রুটি বা সমস্যা থাকলে ৭ দিনের মধ্যে ১০০% ফ্রি রিপ্লেসমেন্ট গ্যারান্টি দেওয়া হয়।\"}
    ],
    \"ad_copy\": \"Viral Facebook & TikTok Ad Copy in Bengali with hook, pain-points, offer, pricing, and CTA...\",
    \"suggested_category\": \"Kitchen & Dining\",
    \"seo_title\": \"Buy Product Name in Bangladesh | Best Price\",
    \"seo_description\": \"...\",
    \"seo_keywords\": \"...\"
}";

        $user_prompt = "Product Name: {$name}\n";
        $user_prompt .= "Copywriting Tone: {$tone}\n";
        if ($sale_price > 0) $user_prompt .= "Target Sale Price: ৳{$sale_price}\n";
        if ($reg_price > 0) $user_prompt .= "Regular Price: ৳{$reg_price}\n";
        if (!empty($prompt)) $user_prompt .= "Special Instructions: {$prompt}\n";

        $body = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [['text' => $system_instruction . "\n\n" . $user_prompt]]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.7,
                'responseMimeType' => 'application/json'
            ]
        ];

        $response = wp_remote_post($endpoint, [
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => json_encode($body),
            'timeout' => 20,
            'sslverify' => false
        ]);

        if (is_wp_error($response)) {
            error_log('Gemini API Error: ' . $response->get_error_message());
            return null;
        }

        $res_body = wp_remote_retrieve_body($response);
        $data = json_decode($res_body, true);

        if (!empty($data['candidates'][0]['content']['parts'][0]['text'])) {
            $raw_json = $data['candidates'][0]['content']['parts'][0]['text'];
            
            // Clean markdown blocks
            $clean_json = trim($raw_json);
            $clean_json = preg_replace('/^```[a-z]*\s*/i', '', $clean_json);
            $clean_json = preg_replace('/```\s*$/i', '', $clean_json);
            
            // Extract from {}
            if (preg_match('/\{[\s\S]*\}/u', $clean_json, $matches)) {
                $clean_json = $matches[0];
            }
            
            $parsed = json_decode($clean_json, true);

            // Fix unescaped newlines inside strings if json_decode failed
            if (!is_array($parsed)) {
                $clean_json_fixed = preg_replace_callback('/"([^"\\\\]*(\\\\.[^"\\\\]*)*)"/s', function($m) {
                    return '"' . str_replace(["\r\n", "\n", "\r", "\t"], ['\n', '\n', '', '\t'], $m[1]) . '"';
                }, $clean_json);
                $parsed = json_decode($clean_json_fixed, true);
            }

            if (is_array($parsed) && !empty($parsed['title'])) {
                if (!empty($image_url) && !empty($parsed['info_cards'])) {
                    $parsed['info_cards'][0]['img'] = $image_url;
                }
                $parsed['detected_niche'] = 'Cloud AI Generated (' . strtoupper($tone) . ')';
                $parsed['comparison_table_html'] = $this->generate_comparison_table_html($name);
                $parsed['faqs_html'] = $this->build_faqs_html($parsed['faqs'] ?? []);
                $parsed['order_bump_suggestions'] = $this->generate_order_bump_suggestions($name, $parsed['suggested_category'] ?? 'General');
                return $parsed;
            }
        }

        return null;
    }

    /**
     * Massive Offline Knowledge Base & Semantic Classifier with Tone Adaptation
     */
    private function generate_from_knowledge_base($name, $tone, $sale_price, $reg_price, $prompt, $image_url, $force_fallback = 0) {
        $clean_name = trim($name);
        $text_to_analyze = strtolower($clean_name . ' ' . $prompt);

        // Load Built-in Archetypes + Custom Knowledge Saved in DB
        $library = $this->get_complete_knowledge_library();

        $best_niche_key = null;
        $highest_score = 0;

        foreach ($library as $key => $niche) {
            $score = 0;
            if (!empty($niche['keywords'])) {
                foreach ($niche['keywords'] as $kw) {
                    if (mb_stripos($text_to_analyze, strtolower($kw)) !== false) {
                        $score += mb_strlen($kw) >= 4 ? 3 : 1;
                    }
                }
            }
            if ($score >= $highest_score && $score > 0) {
                $highest_score = $score;
                $best_niche_key = $key;
            }
        }

        // If no match found and user hasn't chosen to force standard fallback
        if ($highest_score === 0 && !$force_fallback) {
            $master_prompt = $this->generate_master_ai_prompt($clean_name);
            return [
                'status'        => 'unknown_niche',
                'master_prompt' => $master_prompt,
                'product_name'  => $clean_name
            ];
        }

        // Use matched niche template or universal smart builder
        $niche = ($best_niche_key && isset($library[$best_niche_key])) ? $library[$best_niche_key] : $this->get_universal_smart_template();

        // Calculate Pricing
        if ($sale_price <= 0) {
            $sale_price = $niche['default_sale_price'] ?? 650;
        }
        if ($reg_price <= 0) {
            $reg_price = round($sale_price * 1.45, -1);
        }

        // Tone Customizations
        $tone_title_prefix = '';
        $tone_badge = '';
        if ($tone === 'fomo') {
            $tone_title_prefix = '🔥 [সীমিত সময়ের অফার] ';
            $tone_badge = ' — দ্রুত অর্ডার করুন, স্টক সীমিত!';
        } elseif ($tone === 'premium') {
            $tone_title_prefix = '👑 [লুক্সারি এডিশন] ';
            $tone_badge = ' — ১০০% প্রিমিয়াম ও এক্সক্লুসিভ কোয়ালিটি';
        } elseif ($tone === 'problem_solving') {
            $tone_title_prefix = '💡 [১০০% সমাধান] ';
            $tone_badge = ' — আপনার দৈনন্দিন সমস্যার নির্ভরযোগ্য সমাধান';
        }

        // Generate Dynamic Personalized Descriptions
        $title = $tone_title_prefix . $clean_name . ' - ' . ($niche['title_suffix'] ?? 'অরিজিনাল ও প্রিমিয়াম কোয়ালিটি');
        
        $short_desc = str_replace('{PRODUCT_NAME}', $clean_name, $niche['short_desc']);
        $full_desc = str_replace('{PRODUCT_NAME}', $clean_name, $niche['full_desc_html']);
        
        $steps = [];
        if (!empty($niche['usage_steps'])) {
            foreach ($niche['usage_steps'] as $s) {
                $steps[] = str_replace('{PRODUCT_NAME}', $clean_name, $s);
            }
        }

        $cards = [];
        if (!empty($niche['info_cards']) && is_array($niche['info_cards'])) {
            foreach ($niche['info_cards'] as $c) {
                if (is_array($c) && (!empty($c['title']) || !empty($c['desc']))) {
                    $cards[] = [
                        'title' => str_replace('{PRODUCT_NAME}', $clean_name, $c['title'] ?? ''),
                        'desc'  => str_replace('{PRODUCT_NAME}', $clean_name, $c['desc'] ?? ''),
                        'img'   => !empty($c['img']) ? $c['img'] : $image_url
                    ];
                }
            }
        }

        if (empty($cards)) {
            $cards = [
                [
                    'title' => '১০০% প্রিমিয়াম ও অরিজিনাল কোয়ালিটি',
                    'desc'  => 'সেরা কোয়ালিটির দীর্ঘস্থায়ী উপাদানে তৈরি যা সম্পূর্ণ নিখুঁত পারফরম্যান্স ও স্থায়িত্ব নিশ্চিত করে।',
                    'img'   => $image_url
                ],
                [
                    'title' => 'ব্যবহার ও সংরক্ষণে অত্যন্ত সুবিধাজনক',
                    'desc'  => 'দৈনন্দিন কাজে খুব সহজে ব্যবহার এবং সাজিয়ে বা পরিষ্কার করে রাখার জন্য আদর্শ।',
                    'img'   => $image_url
                ],
                [
                    'title' => 'সারা বাংলাদেশে ক্যাশ অন ডেলিভারি',
                    'desc'  => 'পণ্য হাতে পেয়ে পুরোপুরি চেক করে মূল্য পরিশোধ করার শতভাগ নিশ্চয়তা।',
                    'img'   => $image_url
                ]
            ];
        }

        // Generate FAQs
        $faqs = [
            [
                'q' => 'ডেলিভারি পেতে কত দিন সময় লাগবে?',
                'a' => 'ঢাকার ভিতরে ২৪ থেকে ৪৮ ঘণ্টা এবং ঢাকার বাইরে ২ থেকে ৩ দিনের মধ্যে আপনার ঠিকানায় সরাসরি হোম ডেলিভারি পৌঁছে দেওয়া হবে।'
            ],
            [
                'q' => 'পণ্য হাতে পেয়ে চেক করে টাকা দিতে পারব কি?',
                'a' => 'জি অবশ্যই! ডেলিভারি ম্যানের সামনে প্যাকেট খুলে পণ্যটি সম্পূর্ণ চেক করে তারপর ক্যাশ অন ডেলিভারিতে মূল্য পরিশোধ করবেন।'
            ],
            [
                'q' => 'পণ্যটি কি অরিজিনাল এবং কোনো সমস্যা হলে ওয়ারেন্টি পাব?',
                'a' => 'আমাদের প্রতিটি পণ্য ১০০% অরিজিনাল ও ইনট্যাক্ট। প্রডাক্টে কোনো ধরণের ত্রুটি থাকলে তাৎক্ষণিক ৭ দিনের ফ্রি রিপ্লেসমেন্ট সুবিধা পাবেন।'
            ],
            [
                'q' => 'অর্ডার কনফার্ম করব কীভাবে?',
                'a' => 'উপরে বা নিচে থাকা "অর্ডার করুন" বাটনে ক্লিক করে আপনার নাম, মোবাইল নাম্বার ও ঠিকানা দিয়ে খুব সহজেই অর্ডার কনফার্ম করতে পারেন।'
            ]
        ];

        // Generate Facebook/TikTok Ad Copy
        $ad_copy = $this->generate_social_ad_copy($clean_name, $sale_price, $reg_price, $tone);

        // Generate Comparison Table HTML
        $comparison_table_html = $this->generate_comparison_table_html($clean_name);

        // Order Bump Suggestions
        $bump_suggestions = $this->generate_order_bump_suggestions($clean_name, $niche['category'] ?? 'General');

        return [
            'title'                 => $title,
            'sale_price'            => $sale_price,
            'regular_price'         => $reg_price,
            'short_description'     => $short_desc,
            'full_description'      => $full_desc,
            'steps_title'           => $niche['steps_title'] ?? 'Easy Usage Steps',
            'usage_steps'           => $steps,
            'cards_title'           => $niche['cards_title'] ?? 'Why is it the best for you?',
            'info_cards'            => $cards,
            'faqs'                  => $faqs,
            'faqs_html'             => $this->build_faqs_html($faqs),
            'ad_copy'               => $ad_copy,
            'comparison_table_html' => $comparison_table_html,
            'order_bump_suggestions'=> $bump_suggestions,
            'suggested_category'    => $niche['category'] ?? 'General',
            'seo_title'             => $clean_name . ' Price in Bangladesh | Best Online Deal',
            'seo_description'       => 'Buy authentic ' . $clean_name . ' online at best price in Bangladesh with cash on delivery.',
            'seo_keywords'          => $clean_name . ', online shopping bd, best price bd',
            'detected_niche'        => ($niche['niche_name'] ?? 'Smart Library Template') . ' (' . strtoupper($tone) . ')'
        ];
    }

    /**
     * Social Ad Copy Generator
     */
    private function generate_social_ad_copy($name, $sale_price, $reg_price, $tone) {
        $discount = ($reg_price > $sale_price) ? round((($reg_price - $sale_price) / $reg_price) * 100) : 30;
        
        $hook = "🔥 ঘর সাজানো ও দৈনন্দিন জীবনে স্বস্তি আনুন আসল '{$name}' এর সাথে!";
        if ($tone === 'fomo') {
            $hook = "⚡ [ধামাকা ডিসকাউন্ট] সীমিত সময়ের জন্য '{$name}' পাচ্ছেন {$discount}% ছাড়ে! স্টক খুব সীমিত!";
        } elseif ($tone === 'premium') {
            $hook = "👑 সেরা মানের প্রিমিয়াম '{$name}' — আপনার রুচিশীল জীবনের অনন্য প্রকাশ!";
        } elseif ($tone === 'problem_solving') {
            $hook = "💡 পুরোনো কষ্টের অবসান! আপনার প্রতিদিনের কাজকে সহজ ও আরামদায়ক করতে বেছে নিন '{$name}'!";
        }

        return "{$hook}\n\n" .
        "✨ কেন এটি সেরা পছন্দ?\n" .
        "✔ ১০০% অরিজিনাল ও প্রিমিয়াম কোয়ালিটি ম্যাটেরিয়াল।\n" .
        "✔ ব্যবহারে অত্যন্ত সহজ, টেকসই ও আকর্ষণীয় ডিজাইন।\n" .
        "✔ সারা বাংলাদেশে দ্রুত হোম ডেলিভারি সুবিধা।\n" .
        "✔ পণ্য হাতে পেয়ে চেক করে মূল্য পরিশোধের শতভাগ নিশ্চয়তা।\n\n" .
        "💰 Regular Price: ৳{$reg_price}\n" .
        "🎁 আজকের বিশেষ Sale Price: মাত্র ৳{$sale_price}\n\n" .
        "🛒 অর্ডার করতে এখনি 'Send Message' বাটন চাপুন অথবা ভিজিট করুন আমাদের ওয়েবসাইট।\n" .
        "📞 যেকোনো তথ্যের জন্য সরাসরি কল বা হোয়াটসঅ্যাপ করুন!";
    }

    /**
     * Comparison Table HTML Generator
     */
    private function generate_comparison_table_html($name) {
        return '<div style="margin: 24px 0; background: #ffffff; border: 1.5px solid #e2e8f0; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); font-family: inherit;">' .
            '<div style="background: linear-gradient(135deg, #1e1b4b 0%, #312e81 100%); color: #fff; padding: 12px 18px; font-weight: 700; font-size: 15px; text-align: center;">' .
            '📊 কেন আমাদের ' . esc_html($name) . ' অন্যদের চেয়ে আলাদা ও সেরা?' .
            '</div>' .
            '<table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 13px;">' .
            '<thead><tr style="background: #f8fafc; border-bottom: 2px solid #e2e8f0;">' .
            '<th style="padding: 10px 14px; color: #475569; width: 34%;">ফিচার / বৈশিষ্ট্য</th>' .
            '<th style="padding: 10px 14px; color: #166534; background: #f0fdf4; width: 33%; font-weight: 700;">✅ আমাদের পণ্য</th>' .
            '<th style="padding: 10px 14px; color: #991b1b; background: #fef2f2; width: 33%;">❌ সাধারণ কমদামী পণ্য</th>' .
            '</tr></thead>' .
            '<tbody>' .
            '<tr style="border-bottom: 1px solid #f1f5f9;"><td style="padding: 10px 14px; font-weight: 600;">ম্যাটেরিয়াল কোয়ালিটি</td><td style="padding: 10px 14px; background: #f0fdf4; color: #15803d;">১০০% প্রিমিয়াম ও ফুড গ্রেড / হেভি ডিউটি</td><td style="padding: 10px 14px; background: #fef2f2; color: #b91c1c;">নরমাল ও পাতলা রি-সাইকেল ম্যাটেরিয়াল</td></tr>' .
            '<tr style="border-bottom: 1px solid #f1f5f9;"><td style="padding: 10px 14px; font-weight: 600;">স্থায়িত্ব ও পারফরম্যান্স</td><td style="padding: 10px 14px; background: #f0fdf4; color: #15803d;">দীর্ঘদিন নতুনের মতো টেকসই ও নির্ভরযোগ্য</td><td style="padding: 10px 14px; background: #fef2f2; color: #b91c1c;">কয়েকদিনেই কার্যকারিতা নষ্ট হয়ে যায়</td></tr>' .
            '<tr style="border-bottom: 1px solid #f1f5f9;"><td style="padding: 10px 14px; font-weight: 600;">পণ্য চেক করে পেমেন্ট</td><td style="padding: 10px 14px; background: #f0fdf4; color: #15803d;">হাতে পেয়ে চেক করে পেমেন্টের শতভাগ সুবিধা</td><td style="padding: 10px 14px; background: #fef2f2; color: #b91c1c;">চেক করার সুযোগ থাকে না বা অগ্রিম দাবি করে</td></tr>' .
            '<tr><td style="padding: 10px 14px; font-weight: 600;">ওয়ারেন্টি ও সাপোর্ট</td><td style="padding: 10px 14px; background: #f0fdf4; color: #15803d;">৭ দিনের ফ্রি রিপ্লেসমেন্ট ও দ্রুত কাস্টমার সাপোর্ট</td><td style="padding: 10px 14px; background: #fef2f2; color: #b91c1c;">কোনো বিক্রয়োত্তর সেবা পাওয়া যায় না</td></tr>' .
            '</tbody></table></div>';
    }

    /**
     * Build Accordion FAQs HTML for Landing Page / Description
     */
    private function build_faqs_html($faqs) {
        if (empty($faqs) || !is_array($faqs)) return '';
        $html = '<div style="margin: 24px 0; background: #f8fafc; border: 1.5px solid #e2e8f0; border-radius: 12px; padding: 18px;">' .
            '<h3 style="margin: 0 0 14px 0; color: #1e1b4b; font-size: 16px; font-weight: 700; display: flex; align-items: center; gap: 8px;">' .
            '❓ সচরাচর জিজ্ঞাসিত প্রশ্ন ও উত্তর (FAQs)' .
            '</h3>';
        foreach ($faqs as $f) {
            $html .= '<details style="background: #ffffff; border: 1px solid #cbd5e1; border-radius: 8px; padding: 10px 14px; margin-bottom: 10px; cursor: pointer;">' .
                '<summary style="font-weight: 700; color: #1e293b; font-size: 13px; outline: none;">' . esc_html($f['q']) . '</summary>' .
                '<p style="margin: 8px 0 0 0; color: #475569; font-size: 13px; line-height: 1.5;">' . esc_html($f['a']) . '</p>' .
                '</details>';
        }
        $html .= '</div>';
        return $html;
    }

    /**
     * Generate Order Bump & Upsell Suggestions
     */
    private function generate_order_bump_suggestions($name, $category) {
        $cat_lower = strtolower($category);
        $name_lower = strtolower($name);

        if (strpos($cat_lower, 'kitchen') !== false || strpos($name_lower, 'fryer') !== false || strpos($name_lower, 'cook') !== false) {
            return [
                [
                    'title' => 'সিলিকন বেকিং পট / স্প্রে অয়েল বটল',
                    'reason' => 'এয়ার ফ্রায়ার বা রান্নায় ব্যবহারের জন্য সবচেয়ে জনপ্রিয় কম্বো এক্সেসরিজ।',
                    'discount' => '২০% স্পেশাল ডিসকাউন্ট'
                ],
                [
                    'title' => 'মাল্টিপারপাস কিচেন কাটার ও পিলিং সেট',
                    'reason' => 'রান্নার কাটাকাটি দ্রুত করার জন্য আদর্শ জুড়ি।',
                    'discount' => '১৫% কম্বো অফার'
                ]
            ];
        } elseif (strpos($cat_lower, 'gadget') !== false || strpos($name_lower, 'watch') !== false || strpos($name_lower, 'trimmer') !== false) {
            return [
                [
                    'title' => 'প্রিমিয়াম ফাস্ট চার্জিং ক্যাবল ও অ্যাডাপ্টার',
                    'reason' => 'স্মার্ট ওয়াচ বা ট্রিমার নিরাপদে দ্রুত চার্জ দেওয়ার জন্য বেস্ট অ্যাড-অন।',
                    'discount' => '২৫% ছাড়'
                ],
                [
                    'title' => 'এক্সট্রা সিলিকন স্ট্র্যাপ / ট্রিমার লুব্রিকেটিং কিট',
                    'reason' => 'দীর্ঘদিন নিরবচ্ছিন্ন ব্যবহারের অপরিহার্য সঙ্গী।',
                    'discount' => '২০% স্পেশাল ডিল'
                ]
            ];
        } elseif (strpos($cat_lower, 'beauty') !== false || strpos($name_lower, 'oil') !== false || strpos($name_lower, 'cream') !== false) {
            return [
                [
                    'title' => 'স্ক্যাল্প ম্যাসাজার ও হেয়ার ওয়াশ ব্রাশ',
                    'reason' => 'তেল ভালোভাবে শুষে নিতে এবং স্ক্যাল্প রক্ত সঞ্চালন বাড়াতে দারুণ কার্যকর।',
                    'discount' => '৩০% ডিসকাউন্ট'
                ]
            ];
        }

        return [
            [
                'title' => 'প্রিমিয়াম গিফট বক্স ও প্রটেক্টিভ কভার',
                'reason' => 'পণ্যটিকে নিরাপদে ও আকর্ষণীয়ভাবে সংরক্ষণের জন্য উপযুক্ত।',
                'discount' => '২০% কম্বো অফার'
            ]
        ];
    }

    /**
     * Master AI Prompt Generator for Unknown Products
     */
    private function generate_master_ai_prompt($product_name) {
        return "Act as an eCommerce copywriting and product specialist for Bangladeshi online shopping stores.
Create a highly reusable, high-converting Category Archetype Template for '{$product_name}'.
This template will be saved into our eCommerce Knowledge Base to automatically generate high-converting landing pages and product data for ANY product matching related keywords/tags.

Output ONLY valid, raw, unformatted JSON (no conversational text, no markdown formatting, no backticks, no code blocks):
{
  \"niche_name\": \"{$product_name} Niche Archetype\",
  \"keywords\": [
    \"{$product_name}\",
    \"related keyword 1\",
    \"related keyword 2\",
    \"related keyword 3\",
    \"related keyword 4\",
    \"বাংলা ট্যাগ ১\",
    \"বাংলা ট্যাগ ২\",
    \"বাংলা ট্যাগ ৩\"
  ],
  \"category\": \"Kitchen & Dining / Electronics / Home & Living / Health & Beauty\",
  \"default_sale_price\": 650,
  \"title_suffix\": \"অরিজিনাল ও প্রিমিয়াম কোয়ালিটি\",
  \"short_desc\": \"✔ ১০০% অরিজিনাল ও প্রিমিয়াম কোয়ালিটি নিশ্চিত।\\n✔ দৈনন্দিন ব্যবহারে অত্যন্ত টেকসই এবং সুবিধাজনক।\\n✔ আকর্ষণীয় ডিজাইন ও আধুনিক ফিচার সমৃদ্ধ।\\n✔ দ্রুত সারা বাংলাদেশে হোম ডেলিভারি ও ক্যাশ অন ডেলিভারি সুবিধা।\",
  \"full_desc_html\": \"<div class=\\\"fmb-ai-desc\\\"><p>আপনার দৈনন্দিন কাজকে সহজ করতে <strong>{PRODUCT_NAME}</strong> একটি অসাধারণ পণ্য।</p><h3>✨ মূল বৈশিষ্ট্যসমূহ:</h3><ul><li>💎 <strong>উন্নত কোয়ালিটি:</strong> প্রিমিয়াম গ্রেডের উপাদানে তৈরি।</li><li>⚡ <strong>সহজ ব্যবহার:</strong> যেকোনো বয়সের মানুষের ব্যবহারের জন্য অত্যন্ত উপযোগী।</li><li>🛡️ <strong>১০০% নির্ভরযোগ্য:</strong> সম্পূর্ণ ঝামেলামুক্ত ব্যবহার নিশ্চিত করে।</li></ul><h3>📦 প্যাকেজে যা যা থাকছে:</h3><p>১ x {PRODUCT_NAME} (নিরাপদ প্যাকেজিং সহ)</p><div style=\\\"background: #f0fdf4; border: 1.5px dashed #86efac; border-radius: 8px; padding: 14px; margin-top: 20px;\\\"><h4 style=\\\"margin:0 0 6px; color:#166534;\\\">🚚 ডেলিভারি ও পেমেন্ট:</h4><p style=\\\"margin:0; font-size:13px; color:#15803d;\\\">সারা বাংলাদেশে ক্যাশ অন ডেলিভারি — পণ্য হাতে পেয়ে মূল্য পরিশোধ করুন।</p></div></div>\",
  \"steps_title\": \"Easy Usage Steps\",
  \"usage_steps\": [
    \"প্যাকেট খুলে সাবধানে পণ্যটি বের করে চেক করে নিন।\",
    \"ব্যবহারের নির্দেশিকা অনুযায়ী সঠিক স্থানে সেটআপ করুন।\",
    \"পছন্দমতো মোড বা সুইচ অন করে ব্যবহার শুরু করুন।\",
    \"কাজ শেষে পরিষ্কার করে নিরাপদ স্থানে সংরক্ষণ করুন।\"
  ],
  \"cards_title\": \"Why is it the best for you?\",
  \"info_cards\": [
    {\"title\": \"১০০% অরিজিনাল পণ্য\", \"desc\": \"সেরা ও অথেনটিক কোয়ালিটি নিশ্চিত।\", \"img\": \"\"},
    {\"title\": \"টেকসই ও দীর্ঘস্থায়ী\", \"desc\": \"উচ্চমানের উপাদানে তৈরি হওয়ায় দীর্ঘদিন নিশ্চিন্তে ব্যবহারযোগ্য।\", \"img\": \"\"},
    {\"title\": \"ক্যাশ অন ডেলিভারি\", \"desc\": \"পণ্য হাতে পেয়ে চেক করে মূল্য পরিশোধ করার ১০০% সুবিধা।\", \"img\": \"\"}
  ]
}";
    }

    /**
     * Complete Multi-Niche eCommerce Knowledge Graph
     */
    private function get_complete_knowledge_library() {
        $custom_library = get_option('fmb_custom_ai_knowledge_base', []);
        if (!is_array($custom_library)) $custom_library = [];

        // Auto-sanitize existing custom library entries to ensure info_cards is always populated
        foreach ($custom_library as $k => &$entry) {
            if (empty($entry['info_cards']) || !is_array($entry['info_cards'])) {
                $entry['info_cards'] = [
                    [
                        'title' => '১০০% প্রিমিয়াম ও অরিজিনাল কোয়ালিটি',
                        'desc'  => 'সেরা কোয়ালিটির দীর্ঘস্থায়ী উপাদানে তৈরি যা সম্পূর্ণ নিখুঁত স্থায়িত্ব নিশ্চিত করে।',
                        'img'   => ''
                    ],
                    [
                        'title' => 'সহজ ব্যবহার ও ইনস্টলেশন',
                        'desc'  => 'দৈনন্দিন কাজে খুব সহজে ব্যবহার এবং সাজিয়ে রাখার জন্য একদম উপযোগী।',
                        'img'   => ''
                    ],
                    [
                        'title' => 'ক্যাশ অন ডেলিভারি সুবিধা',
                        'desc'  => 'পণ্য হাতে পেয়ে চেক করে মূল্য পরিশোধের শতভাগ নিশ্চয়তা।',
                        'img'   => ''
                    ]
                ];
            }
        }
        unset($entry);

        $built_in = [
            // 1. Air Fryer
            'air_fryer' => [
                'niche_name' => 'Kitchen: Air Fryer',
                'keywords' => ['air fryer', 'fryer', 'এয়ার ফ্রায়ার', 'ফ্রায়ার', 'oil free fryer', 'digital air fryer', 'airfryer'],
                'category' => 'Kitchen & Dining',
                'default_sale_price' => 4850,
                'title_suffix' => 'তেলমুক্ত স্বাস্থ্যকর রান্নার ডিজিটাল এয়ার ফ্রায়ার',
                'short_desc' => "✔ ৯০% পর্যন্ত কম তেলে স্বাস্থ্যকর ও সুস্বাদু রান্নার নিশ্চয়তা।\n✔ ডিজিটাল টাচ কন্ট্রোল প্যানেল ও অটো-শাটঅফ ফিচার।\n✔ নন-স্টিক ফুড গ্রেড বাস্কেট যা সহজে ধোয়া যায়।\n✔ দ্রুত সারা বাংলাদেশে ক্যাশ অন ডেলিভারি ও ওয়ারেন্টি।",
                'full_desc_html' => '<div class="fmb-ai-desc"><p>স্বাস্থ্যকর ও তেলমুক্ত খাবার তৈরির জন্য <strong>{PRODUCT_NAME}</strong> একটি অসাধারণ কিচেন অ্যাপ্লায়েন্স। এটি ৩৬০ ডিগ্রি র‍্যাপিড এয়ার সার্কুলেশন প্রযুক্তির মাধ্যমে যেকোনো খাবার ক্রিস্পি ও সুস্বাদু করে তোলে।</p><h3>✨ বিশেষ ফিচারসমূহ:</h3><ul><li>🔥 <strong>র‍্যাপিড হট এয়ার টেকনোলজি:</strong> মাত্র কয়েক মিনিটেই ফ্রাই, বেকিং বা রোস্ট সম্পন্ন হয়।</li><li>🛡️ <strong>নন-স্টিক কোটিং:</strong> খাবার লেগে যায় না এবং সহজে পরিষ্কার করা যায়।</li><li>⏱️ <strong>স্মার্ট টাইমার ও টেম্পারেচার কন্ট্রোল:</strong> ৮০°C থেকে ২০০°C পর্যন্ত প্রয়োজনমতো তাপমাত্রা সেট করার সুবিধা।</li></ul></div>',
                'steps_title' => 'এয়ার ফ্রায়ার ব্যবহারের নিয়মাবলী',
                'usage_steps' => [
                    'এয়ার ফ্রায়ারটি সমান ও নিরাপদ স্থানে রাখুন এবং প্লাগ-ইন করুন।',
                    'খাবারগুলো বাস্কেটে সমানভাবে রাখুন (অতিরিক্ত তেল দেওয়ার প্রয়োজন নেই)।',
                    'পছন্দমতো সময় ও তাপমাত্রা সেট করে পাওয়ার বাটন প্রেস করুন।',
                    'রান্না শেষে বাস্কেটটি বের করে খাবার পরিবেশন করুন এবং বাস্কেটটি হালকা গরম পানিতে ধুয়ে নিন।'
                ],
                'cards_title' => 'কেন এই এয়ার ফ্রায়ারটি সেরা?',
                'info_cards' => [
                    ['title' => '৯০% তেল সাশ্রয়ী', 'desc' => 'অতিরিক্ত তেল ছাড়াই মুচমুচে ফ্রাইয়ের আসল স্বাদ উপভোগ করুন।', 'img' => ''],
                    ['title' => 'সহজ পরিষ্কারযোগ্য', 'desc' => 'ডিসওয়াশার সেফ নন-স্টিক ফ্রাইং প্যান।', 'img' => ''],
                    ['title' => '১ বছরের সার্ভিস ওয়ারেন্টি', 'desc' => 'নির্ভুল পারফরম্যান্স ও দ্রুত সার্ভিস সাপোর্ট।', 'img' => '']
                ]
            ],

            // 2. Blender & Grinder
            'blender_grinder' => [
                'niche_name' => 'Kitchen: Blender & Grinder',
                'keywords' => ['blender', 'grinder', 'juicer', 'hand blender', 'food processor', 'ব্লেন্ডার', 'গ্রাইন্ডার', 'জুসার', 'মসলা বাটা', 'হ্যান্ড ব্লেন্ডার'],
                'category' => 'Kitchen & Dining',
                'default_sale_price' => 1850,
                'title_suffix' => 'হেভি ডিউটি মাল্টিফাংশন ব্লেন্ডার ও মসলা গ্রাইন্ডার',
                'short_desc' => "✔ শক্তিশালী কপার মোটর ও ৩টি স্টেইনলেস স্টিল জার।\n✔ চাল, ডাল, হলুদ, মরিচ ও শক্ত মসলা গুঁড়া করার ১০০% গ্যারান্টি।\n✔ জুস, স্মুদি ও পেস্ট তৈরি হবে মাত্র কয়েক সেকেন্ডে।\n✔ ওভারহিট প্রোটেকশন ও সেফটি লক সিস্টেম।",
                'full_desc_html' => '<div class="fmb-ai-desc"><p>রান্নাঘরের সময় ও পরিশ্রম বাঁচাতে <strong>{PRODUCT_NAME}</strong> হতে পারে আপনার বিশ্বস্ত সঙ্গী। এর শক্তিশালী মোটর যেকোনো শক্ত মসলা ও ফলমূল পলকেই মিহি করে ফেলে।</p><h3>✨ মূল বৈশিষ্ট্য:</h3><ul><li>⚡ <strong>১০০% কপার আর্মেচার মোটর:</strong> টানা ব্যবহারের পরও হিটিং কম হয়।</li><li>🔪 <strong>রেজর শার্প স্টেইনলেস স্টিল ব্লেড:</strong> মরিচাহীন ও দীর্ঘস্থায়ী ধার।</li><li>🛡️ <strong>মাল্টিপল স্পিড কন্ট্রোল:</strong> প্রয়োজন অনুযায়ী ইনচিং ও ৩-স্পিড অপশন।</li></ul></div>',
                'steps_title' => 'ব্লেন্ডার ব্যবহারের সঠিক নিয়ম',
                'usage_steps' => [
                    'জারে প্রয়োজনীয় উপাদান রাখুন এবং ঢাকনাটি শক্তভাবে আটকে দিন।',
                    'জারটিকে মোটরের বেসের সাথে লক করে স্পিড সিলেক্টরে ঘোরান।',
                    'টানা ৩০ সেকেন্ড চালানোর পর ৫ সেকেন্ড বিরতি দিয়ে আবার চালান।',
                    'কাজ শেষে জারটি খুলে ধুয়ে পরিষ্কার ও শুকনো জায়গায় রাখুন।'
                ],
                'cards_title' => 'কেন এটি আপনার রান্নায় সেরা?',
                'info_cards' => [
                    ['title' => 'শক্তিশালী কপার মোটর', 'desc' => 'যেকোনো শক্ত উপাদান সেকেন্ডে গুঁড়া করতে সক্ষম।', 'img' => ''],
                    ['title' => 'ফুড গ্রেড স্টেইনলেস স্টিল', 'desc' => 'খাবারের স্বাস্থ্য ও পুষ্টিগুণ সম্পূর্ণ অক্ষত থাকে।', 'img' => ''],
                    ['title' => 'সহজে পরিষ্কারযোগ্য', 'desc' => 'লিক-প্রুফ ডিজাইন ও ওয়াশ-ফ্রেন্ডলি জার।', 'img' => '']
                ]
            ],

            // 3. Hair Trimmer & Shaver
            'hair_trimmer' => [
                'niche_name' => 'Gadgets: Hair Trimmer & Shaver',
                'keywords' => ['trimmer', 'shaver', 'clipper', 't9', 'vgr', 'hair cutter', 'ট্রিমার', 'শেভার', 'দাড়ি কাটার মেশিন', 'চুল কাটার মেশিন'],
                'category' => 'Electronics & Gadgets',
                'default_sale_price' => 690,
                'title_suffix' => 'প্রফেশনাল রিচার্জেবল কর্ডলেস হেয়ার ট্রিমার ও শেভার',
                'short_desc' => "✔ সেলুন কোয়ালিটি কাটিং ও জিরো ট্রিম সুবিধা।\n✔ রিচার্জেবল ব্যাটারিতে একটানা ১২০ মিনিট পর্যন্ত ব্যাকআপ।\n✔ টাইটানিয়াম ও স্টেইনলেস স্টিল সেলফ-শার্পেনিং ব্লেড।\n✔ সাথে থাকছে ৪টি লিমিট কম্ব ও ক্লিনিং ব্রাশ।",
                'full_desc_html' => '<div class="fmb-ai-desc"><p>ঘরে বসেই সেলুনের মতো পারফেক্ট গ্রুমিং ও বিয়ার্ড ট্রিম করতে বেছে নিন <strong>{PRODUCT_NAME}</strong>। এটি যেকোনো স্কিন টাইপে অত্যন্ত মসৃণভাবে চুল ও দাড়ি কাটতে সাহায্য করে।</p><h3>✨ প্রোডাক্টের মূল আকর্ষণ:</h3><ul><li>🔋 <strong>ইউএসবি ফাস্ট চার্জিং:</strong> পাওয়ার ব্যাংক বা মোবাইল চার্জার দিয়ে সহজেই চার্জ দেওয়া যায়।</li><li>🪒 <strong>জিরো-গ্যাপ টি-ব্লেড:</strong> সূক্ষ্ম কাটিং ও স্টাইলিংয়ের জন্য অসাধারণ।</li><li>🤫 <strong>লো-নয়েজ মোটর:</strong> কোনো শব্দ বা অতিরিক্ত ভাইব্রেশন ছাড়াই কাজ করে।</li></ul></div>',
                'steps_title' => 'ট্রিমার Easy Usage Steps',
                'usage_steps' => [
                    'ব্যবহারের পূর্বে ট্রিমারটি সম্পূর্ণ চার্জ করে নিন।',
                    'আপনার পছন্দ অনুযায়ী লিমিট কম্ব (1.5mm / 2mm / 3mm / 4mm) ব্লেডে সেট করুন।',
                    'পাওয়ার সুইচ অন করে হালকাভাবে ত্বকের বিপরীতে ট্রিম করুন।',
                    'ব্যবহার শেষে ব্রাশ দিয়ে ব্লেডের চুল পরিষ্কার করে সামান্য লুব্রিকেন্ট অয়েল দিন।'
                ],
                'cards_title' => 'কেন এই ট্রিমারটি গ্রাহকদের প্রথম পছন্দ?',
                'info_cards' => [
                    ['title' => 'সেলফ-শার্পেনিং ব্লেড', 'desc' => 'ত্বকে কোনো টান বা লালচে দাগ ফেলে না।', 'img' => ''],
                    ['title' => 'লং লাস্টিং ব্যাটারি', 'desc' => 'এক চার্জে টানা ২ ঘণ্টা পর্যন্ত ব্যবহারযোগ্য।', 'img' => ''],
                    ['title' => 'মেটাল আর্ট বডি', 'desc' => 'প্রিমিয়াম ভিন্টেজ খোদাই করা স্টাইলিশ লুক।', 'img' => '']
                ]
            ],

            // 4. Smart Watch
            'smart_watch' => [
                'niche_name' => 'Gadgets: Smart Watch',
                'keywords' => ['smart watch', 'smartwatch', 'ultra watch', 'watch', 'স্মার্ট ওয়াচ', 'ঘড়ি', 'স্মার্ট ঘড়ি', 'ফিটনেস ব্যান্ড'],
                'category' => 'Electronics & Gadgets',
                'default_sale_price' => 1250,
                'title_suffix' => 'ব্লুটুথ কলিং ও ফুল এইচডি ডিসপ্লে স্মার্ট ওয়াচ',
                'short_desc' => "✔ ক্রিস্টাল ক্লিয়ার এইচডি টাচ ডিসপ্লে ও ব্লুটুথ কলিং সুবিধা।\n✔ হার্ট রেট, ব্লাড প্রেশার ও স্লিপ মনিটরিং হেলথ ট্র্যাকার।\n✔ সোশ্যাল মিডিয়া নোটিফিকেশন ও মিউজিক কন্ট্রোল।\n✔ আইপি৬৮ ওয়াটার রেসিস্ট্যান্ট ও দীর্ঘস্থায়ী ব্যাটারি ব্যাকআপ।",
                'full_desc_html' => '<div class="fmb-ai-desc"><p>আপনার স্টাইল ও ফিটনেস ট্র্যাকিংয়ে নতুন মাত্রা যোগ করতে নিয়ে এলো <strong>{PRODUCT_NAME}</strong>। সরাসরি ঘড়ি থেকেই ফোন রিসিভ ও কথা বলার চমৎকার ব্লুটুথ কলিং অভিজ্ঞতা উপভোগ করুন।</p><h3>✨ অসাধারণ ফিচারসমূহ:</h3><ul><li>📞 <strong>স্মার্ট ব্লুটুথ কলিং:</strong> মোবাইল পকেটে রেখেই সরাসরি ঘড়ি থেকে কথা বলুন।</li><li>❤️ <strong>২৪/৭ হেলথ মনিটর:</strong> স্পোর্টস মোড, ক্যালোরি ট্র্যাকার ও স্টেপ কাউন্টার।</li><li>🌊 <strong>ওয়াটার ও ডাস্ট প্রুফ:</strong> হাত ধোয়া বা হালকা বৃষ্টিতে নিশ্চিন্ত ব্যবহার।</li></ul></div>',
                'steps_title' => 'স্মার্ট ওয়াচ কানেক্ট করার নিয়মাবলী',
                'usage_steps' => [
                    'ঘড়ির প্যাকেটের কিউআর কোড স্ক্যান করে অফিসিয়াল অ্যাপটি মোবাইলে ইনস্টল করুন।',
                    'মোবাইলের ব্লুটুথ অন করে অ্যাপের মাধ্যমে ঘড়িটি পেয়ার করুন।',
                    'কল ও নোটিফিকেশন পারমিশন অ্যালাউ করুন।',
                    'পছন্দমতো ওয়াচ ফেস সিলেক্ট করে ব্যবহার শুরু করুন।'
                ],
                'cards_title' => 'কেন এটি সেরা স্মার্ট ওয়াচ?',
                'info_cards' => [
                    ['title' => 'এইচডি ফুল স্ক্রিন', 'desc' => 'রোদেও অত্যন্ত স্পষ্ট ও প্রাণবন্ত ডিসপ্লে।', 'img' => ''],
                    ['title' => 'স্ট্রং ব্যাটারি লাইফ', 'desc' => 'এক চার্জে সাধারণ ব্যবহারে ৪-৭ দিন পর্যন্ত ব্যাকআপ।', 'img' => ''],
                    ['title' => 'প্রিমিয়াম ডিজাইন', 'desc' => 'স্টাইলিশ লুক যা যেকোনো পোশাকে ফুটে উঠবে।', 'img' => '']
                ]
            ],

            // 5. Rust / Iron Remover & Cleaner
            'rust_remover' => [
                'niche_name' => 'Cleaning: Rust & Iron Remover',
                'keywords' => ['rust', 'iron remover', 'cleaner', 'মরিচা', 'দাগ তোলার', 'টাইলস ক্লিনার', 'আয়রন রিমুভার', 'দাগ পরিষ্কার'],
                'category' => 'Home & Living',
                'default_sale_price' => 590,
                'title_suffix' => 'ম্যাজিক আয়রন ও জেদি মরিচা দূর করার পাওয়ারফুল ক্লিনার',
                'short_desc' => "✔ টাইলস, বেসিন ও পাইপের যেকোনো জেদি আয়রনের দাগ দূর করে সেকেন্ডে।\n✔ কোনো ঘষাঘষির প্রয়োজন নেই — স্প্রে করলেই দাগ গায়েব।\n✔ টাইলসের উজ্জ্বলতা নতুনের মতো ফিরিয়ে আনে।\n✔ ১০০% ইফেক্টিভ ও নিরাপদ ফর্মুলা।",
                'full_desc_html' => '<div class="fmb-ai-desc"><p>বাথরুমের টাইলস, বেসিন, কমোড বা ধাতব পাইপের পুরোনো আয়রন ও মরিচার দাগ নিয়ে চিন্তিত? <strong>{PRODUCT_NAME}</strong> এর শক্তিশালী ফর্মুলা কোনো ঘষাঘষি ছাড়াই মুহূর্তে সব দাগ তুলে নতুনের মতো চকচকে করে তোলে।</p><h3>✨ কেন এটি ম্যাজিকের মতো কাজ করে:</h3><ul><li>⚡ <strong>ইনস্ট্যান্ট অ্যাকশন ফর্মুলা:</strong> লাগানোর সাথে সাথেই আয়রন ও মরিচার সাথে বিক্রিয়া করে দাগ নরম করে ফেলে।</li><li>💎 <strong>শাইনিং প্রোটেকশন:</strong> সারফেসের ক্ষতি না করে চকচকে ভাব বজায় রাখে।</li></ul></div>',
                'steps_title' => 'আয়রন রিমুভার ব্যবহারের নিয়মাবলী',
                'usage_steps' => [
                    'দাগযুক্ত স্থানটি সামান্য মুছে শুকনো করে নিন।',
                    'দাগের ওপর পর্যাপ্ত পরিমাণে স্প্রে বা লিকুইড লাগান।',
                    '১ থেকে ২ মিনিট অপেক্ষা করুন যেন লিকুইডটি দাগ নরম করতে পারে।',
                    'এবার ব্রাশ দিয়ে হালকা ঘষে পানি দিয়ে ধুয়ে ফেলুন — মুহূর্তে নতুনের মতো চকচক করবে!'
                ],
                'cards_title' => 'কেন এই ক্লিনারটি সেরা?',
                'info_cards' => [
                    ['title' => '১০০% কার্যকর গ্যারান্টি', 'desc' => 'বছরের পুরোনো জেদি দাগও নিমিষে দূর করে।', 'img' => ''],
                    ['title' => 'পরিশ্রমহীন ক্লিনিং', 'desc' => 'কোনো অতিরিক্ত শক্তি বা ঘষাঘষি ছাড়াই কাজ সম্পন্ন।', 'img' => ''],
                    ['title' => 'মাল্টিপল সারফেস', 'desc' => 'টাইলস, সিরামিক, গ্লাস ও মেটালে সম্পূর্ণ নিরাপদ।', 'img' => '']
                ]
            ],

            // 6. Magic Floor Mop
            'magic_mop' => [
                'niche_name' => 'Cleaning: Magic Floor Mop',
                'keywords' => ['mop', 'magic mop', 'floor cleaner mop', 'spray mop', 'মপ', 'ঘর মোছার মপ', 'স্প্রে মপ', 'ফ্লোর মপ'],
                'category' => 'Home & Living',
                'default_sale_price' => 750,
                'title_suffix' => '৩৬০ ডিগ্রি রোটেটিং হ্যান্ডস-ফ্রি ম্যাজিক ফ্লোর মপ',
                'short_desc' => "✔ হাত না ভিজিয়েই স্বয়ংক্রিয়ভাবে পানি নিংড়ানোর সেলফ-স্কুইজিং সিস্টেম।\n✔ ৩৬০ ডিগ্রি মুভেবল হেড — খাট বা সোফার নিচে সহজে পৌঁছায়।\n✔ সুপার অ্যাবসর্বেন্ট মাইক্রোফাইবার প্যাড যা ধুলো ও চুল সহজে টানে।\n✔ টাইলস ও কাঠের ফ্লোরের জন্য ১০০% নিরাপদ।",
                'full_desc_html' => '<div class="fmb-ai-desc"><p>ঘর মোছার কষ্ট দূর করতে <strong>{PRODUCT_NAME}</strong> একটি যুগান্তকারী আবিষ্কার। হাত দিয়ে মপ নিংড়ানোর কোনো ঝামেলা নেই — এক টানেই সব পানি ও ময়লা আলাদা হয়ে যায়।</p><h3>✨ বিশেষ সুবিধাসমূহ:</h3><ul><li>🔄 <strong>৩৬০° রোটেটেবল হেড:</strong> যেকোনো সংকীর্ণ কোণায় সহজে পরিষ্কার করে।</li><li>🧽 <strong>ডুয়েল ফাংশন:</strong> শুকনো ধুলো ঝাড়ু দেওয়া ও ভেজা মোছা উভয় কাজেই পারফেক্ট।</li></ul></div>',
                'steps_title' => 'ম্যাজিক মপ ব্যবহারের নিয়মাবলী',
                'usage_steps' => [
                    'মাইক্রোফাইবার প্যাডটি মপের মাথায় লাগিয়ে নিন।',
                    'পানিতে ভিজিয়ে স্লাইডারের মাধ্যমে উপর-নিচ টেনে পানি নিংড়ে নিন।',
                    'মেঝেতে হালকাভাবে টেনে ঘর পরিষ্কার করুন।',
                    'ব্যবহার শেষে প্যাডটি ধুয়ে শুকিয়ে রাখুন।'
                ],
                'cards_title' => 'কেন এটি ঘর মোছায় সেরা?',
                'info_cards' => [
                    ['title' => 'হ্যান্ডস-ফ্রি ক্লিনিং', 'desc' => 'হাত নোংরা না করেই নিমিষে পানি নিংড়ানোর সুবিধা।', 'img' => ''],
                    ['title' => 'মাইক্রোফাইবার প্রযুক্তি', 'desc' => 'ধুলোবালি ও তেলের দাগ এক টানেই দূর করে।', 'img' => ''],
                    ['title' => 'টেকসই স্টেইনলেস রড', 'desc' => 'মজবুত ও হালকা যা ব্যবহারে অত্যন্ত আরামদায়ক।', 'img' => '']
                ]
            ],

            // 7. Hair Growth Oil & Serum
            'hair_oil' => [
                'niche_name' => 'Beauty: Hair Growth Oil & Serum',
                'keywords' => ['hair oil', 'serum', 'growth oil', 'castor oil', 'onion oil', 'তেল', 'চুল পড়া বন্ধ', 'হেয়ার অয়েল', 'পেঁয়াজের তেল', 'ক্যাস্টর অয়েল', 'চুলের তেল'],
                'category' => 'Health & Beauty',
                'default_sale_price' => 550,
                'title_suffix' => 'চুল পড়া বন্ধ ও নতুন চুল গজানোর ১০০% ন্যাচারাল হেয়ার অয়েল',
                'short_desc' => "✔ মাত্র ৭-১৪ দিনে চুল পড়া বন্ধের শতভাগ কার্যকরী প্রাকৃতিক সমাধান।\n✔ স্ক্যাল্পের রক্ত সঞ্চালন বাড়িয়ে নতুন চুল গজাতে সাহায্য করে।\n✔ খুশকি দূর করে চুলকে করে সিল্কি, ঘন ও স্বাস্থ্যোজ্জ্বল।\n✔ ১০০% প্যারাবেন ও ক্ষতিকারক কেমিক্যালমুক্ত।",
                'full_desc_html' => '<div class="fmb-ai-desc"><p>চুল পড়া ও পাতলা চুলের সমস্যায় ভুগছেন? <strong>{PRODUCT_NAME}</strong> প্রাকৃতিক আয়ুর্বেদিক উপাদানে তৈরি যা গোড়া থেকে চুলকে মজবুত করে এবং নতুন চুল গজাতে সহায়তা করে।</p><h3>✨ কেন এটি ব্যবহার করবেন:</h3><ul><li>🌿 <strong>সম্পূর্ণ প্রাকৃতিক উপাদান:</strong> কোনো পার্শ্বপ্রতিক্রিয়া নেই।</li><li>💆 <strong>স্ক্যাল্প পুষ্টি:</strong> চুলের গোড়ায় গভীর পুষ্টি জুগিয়ে চুল দ্রুত লম্বা ও ঘন করে।</li></ul></div>',
                'steps_title' => 'হেয়ার অয়েল ব্যবহারের নিয়মাবলী',
                'usage_steps' => [
                    'পরিমাণমতো তেল হাতে নিয়ে হালকা গরম করে নিন।',
                    'আঙুলের ডগা দিয়ে চুলের গোড়ায় ও স্ক্যাল্পে ৫-১০ মিনিট আলতো করে ম্যাসাজ করুন।',
                    'অন্তত ২-৩ ঘণ্টা বা সারারাত চুলে রেখে দিন।',
                    'পরের দিন মাইল্ড শ্যাম্পু দিয়ে ধুয়ে ফেলুন (সপ্তাহে ৩ দিন ব্যবহার করুন)।'
                ],
                'cards_title' => 'কেন এই হেয়ার অয়েলটি সবার সেরা?',
                'info_cards' => [
                    ['title' => '১০০% অরগানিক ফর্মুলা', 'desc' => 'কোনো ক্ষতিকর কেমিক্যাল বা সুগন্ধি নেই।', 'img' => ''],
                    ['title' => 'চুল পড়া বন্ধের নিশ্চয়তা', 'desc' => 'প্রথম সপ্তাহ থেকেই চুল পড়া কমে আসে।', 'img' => ''],
                    ['title' => 'ঘন ও সিল্কি চুল', 'desc' => 'চুলের রুক্ষতা দূর করে প্রাকৃতিক শাইন আনে।', 'img' => '']
                ]
            ]
        ];

        return array_merge($built_in, $custom_library);
    }

    /**
     * Universal Smart Template for Unknown/Fallback
     */
    private function get_universal_smart_template() {
        return [
            'niche_name' => 'Universal Smart E-Commerce Copy',
            'keywords' => [],
            'category' => 'General',
            'default_sale_price' => 690,
            'title_suffix' => 'অরিজিনাল ও প্রিমিয়াম কোয়ালিটি',
            'short_desc' => "✔ ১০০% প্রিমিয়াম ও অরিজিনাল কোয়ালিটি নিশ্চিত।\n✔ দৈনন্দিন ব্যবহারে অত্যন্ত টেকসই এবং সুবিধাজনক।\n✔ আকর্ষণীয় ডিজাইন ও আধুনিক ফিচার সমৃদ্ধ।\n✔ দ্রুত সারা বাংলাদেশে ক্যাশ অন ডেলিভারি সুবিধা।",
            'full_desc_html' => '<div class="fmb-ai-desc"><p>আপনার দৈনন্দিন জীবনকে আরও আরামদায়ক ও সহজ করতে নিয়ে এলো <strong>{PRODUCT_NAME}</strong>। সেরা মানের ম্যাটেরিয়ালে তৈরি যা দীর্ঘস্থায়ী পারফরম্যান্স নিশ্চিত করে।</p><h3>✨ মূল বৈশিষ্ট্যসমূহ:</h3><ul><li>💎 <strong>উন্নত কোয়ালিটি:</strong> প্রিমিয়াম গ্রেডের উপাদানে তৈরি।</li><li>⚡ <strong>সহজ ব্যবহার:</strong> যেকোনো বয়সের মানুষের জন্য অত্যন্ত উপযোগী।</li><li>🛡️ <strong>১০০% নির্ভরযোগ্য:</strong> সম্পূর্ণ ঝামেলামুক্ত ব্যবহার নিশ্চিত করে।</li></ul></div>',
            'steps_title' => 'Easy Usage Steps',
            'usage_steps' => [
                'প্যাকেট খুলে সাবধানে পণ্যটি বের করে চেক করে নিন।',
                'ব্যবহারের নির্দেশিকা অনুযায়ী সঠিক স্থানে সেটআপ করুন।',
                'পছন্দমতো মোড বা সুইচ অন করে ব্যবহার করুন।',
                'কাজ শেষে পরিষ্কার করে নিরাপদ স্থানে সংরক্ষণ করুন।'
            ],
            'cards_title' => 'Why is it the best for you?',
            'info_cards' => [
                ['title' => '১০০% অরিজিনাল পণ্য', 'desc' => 'সেরা ও অথেনটিক কোয়ালিটি নিশ্চিত।', 'img' => ''],
                ['title' => 'টেকসই ও দীর্ঘস্থায়ী', 'desc' => 'উচ্চমানের উপাদানে তৈরি হওয়ায় দীর্ঘদিন ব্যবহারযোগ্য।', 'img' => ''],
                ['title' => 'ক্যাশ অন ডেলিভারি', 'desc' => 'পণ্য হাতে পেয়ে চেক করে মূল্য পরিশোধ করুন।', 'img' => '']
            ]
        ];
    }

    /**
     * AJAX Handler: Save Gemini Key
     */
    public function ajax_save_ai_settings() {
        check_ajax_referer('fmb_ai_nonce', '_nonce');
        $key = sanitize_text_field($_POST['gemini_key'] ?? '');
        update_option('fmb_gemini_ai_api_key', $key);
        wp_send_json_success();
    }

    /**
     * AJAX Handler: Add Custom Knowledge to DB (Ultra-Resilient Multi-Stage Parser)
     */
    public function ajax_add_custom_knowledge() {
        check_ajax_referer('fmb_ai_nonce', '_nonce');
        $payload_raw = wp_unslash($_POST['payload'] ?? '');
        $raw_json    = wp_unslash($_POST['json_data'] ?? '');
        $tags_input  = sanitize_text_field($_POST['tags'] ?? '');
        $niche_name  = sanitize_text_field($_POST['niche_name'] ?? '');
        $category    = sanitize_text_field($_POST['category'] ?? '');

        $parsed = null;

        // Stage 1: Try parsed payload from frontend
        if (!empty($payload_raw)) {
            $parsed = json_decode($payload_raw, true);
        }

        // Stage 2: Parse raw_json with clean-up
        if (!is_array($parsed) && !empty($raw_json)) {
            $clean_json = trim($raw_json);
            $clean_json = preg_replace('/^```[a-z]*\s*/i', '', $clean_json);
            $clean_json = preg_replace('/```\s*$/i', '', $clean_json);

            if (preg_match('/\{[\s\S]*\}/u', $clean_json, $matches)) {
                $clean_json = $matches[0];
            }

            $clean_json = str_replace(
                ['“', '”', '‘', '’', '`'],
                ['"', '"', "'", "'", "'"],
                $clean_json
            );
            $clean_json = preg_replace('/,\s*([\]}])/m', '$1', $clean_json);

            $parsed = json_decode($clean_json, true);

            // Stage 2.5: Fix unescaped newlines inside strings
            if (!is_array($parsed)) {
                $clean_json_fixed = preg_replace_callback('/"([^"\\\\]*(\\\\.[^"\\\\]*)*)"/s', function($m) {
                    return '"' . str_replace(["\r\n", "\n", "\r", "\t"], ['\n', '\n', '', '\t'], $m[1]) . '"';
                }, $clean_json);
                $parsed = json_decode($clean_json_fixed, true);
            }
        }

        // Stage 3: Regex Extractor Fallback (Guaranteed to extract fields from any format)
        if (!is_array($parsed) && !empty($raw_json)) {
            $parsed = $this->regex_extract_custom_knowledge($raw_json);
        }

        if (!is_array($parsed) || empty($parsed)) {
            // Attempt to accept it anyway if it has at least some product fields (as a fallback)
            if (preg_match('/"title"/i', $raw_json) || preg_match('/"niche_name"/i', $raw_json)) {
                $parsed = ['niche_name' => $niche_name ?: 'Custom Niche', 'raw_dump' => $raw_json];
            } else {
                wp_send_json_error(['message' => 'JSON format is incorrect. Please paste only valid JSON code.']);
            }
        }

        // Combine keywords / tags
        $all_tags = [];
        if (!empty($parsed['keywords']) && is_array($parsed['keywords'])) {
            $all_tags = array_merge($all_tags, $parsed['keywords']);
        }
        if (!empty($parsed['keyword'])) {
            $all_tags[] = $parsed['keyword'];
        }
        if (!empty($tags_input)) {
            $split_tags = array_map('trim', explode(',', $tags_input));
            $all_tags = array_merge($all_tags, $split_tags);
        }

        $all_tags = array_unique(array_filter(array_map('sanitize_text_field', $all_tags)));
        if (empty($all_tags)) {
            $all_tags = ['custom-product'];
        }

        $parsed['keywords'] = array_values($all_tags);
        if (!empty($niche_name)) $parsed['niche_name'] = $niche_name;
        if (!empty($category)) $parsed['category'] = $category;

        // Primary key
        $primary_key = sanitize_title($parsed['niche_name'] ?? $all_tags[0]);
        if (empty($primary_key)) $primary_key = 'custom_' . time();

        $custom_library = get_option('fmb_custom_ai_knowledge_base', []);
        if (!is_array($custom_library)) $custom_library = [];

        $custom_library[$primary_key] = $parsed;
        update_option('fmb_custom_ai_knowledge_base', $custom_library);

        wp_send_json_success([
            'message' => 'Custom archetype successfully saved to library!',
            'tags'    => $all_tags,
            'key'     => $primary_key
        ]);
    }

    /**
     * Regex-based Knowledge Extractor Fallback
     */
    private function regex_extract_custom_knowledge($raw) {
        $data = [];
        if (preg_match('/["\']niche_name["\']\s*:\s*["\']([^"\']+)["\']/ui', $raw, $m)) {
            $data['niche_name'] = $m[1];
        }
        if (preg_match('/["\']category["\']\s*:\s*["\']([^"\']+)["\']/ui', $raw, $m)) {
            $data['category'] = $m[1];
        }
        if (preg_match('/["\']default_sale_price["\']\s*:\s*([0-9]+)/ui', $raw, $m)) {
            $data['default_sale_price'] = floatval($m[1]);
        }
        if (preg_match('/["\']title_suffix["\']\s*:\s*["\']([^"\']+)["\']/ui', $raw, $m)) {
            $data['title_suffix'] = $m[1];
        }
        if (preg_match('/["\']short_desc["\']\s*:\s*["\']([\s\S]*?)["\']\s*,\s*["\']/ui', $raw, $m)) {
            $data['short_desc'] = stripcslashes($m[1]);
        }
        if (preg_match('/["\']full_desc_html["\']\s*:\s*["\']([\s\S]*?)["\']\s*,\s*["\']/ui', $raw, $m)) {
            $data['full_desc_html'] = stripcslashes($m[1]);
        }
        if (preg_match('/["\']steps_title["\']\s*:\s*["\']([^"\']+)["\']/ui', $raw, $m)) {
            $data['steps_title'] = $m[1];
        }
        if (preg_match('/["\']usage_steps["\']\s*:\s*\[([\s\S]*?)\]/ui', $raw, $m)) {
            preg_match_all('/["\']([^"\']+)["\']/u', $m[1], $steps_match);
            if (!empty($steps_match[1])) {
                $data['usage_steps'] = $steps_match[1];
            }
        }
        if (preg_match('/["\']cards_title["\']\s*:\s*["\']([^"\']+)["\']/ui', $raw, $m)) {
            $data['cards_title'] = $m[1];
        }
        // Extract info_cards
        if (preg_match('/["\']info_cards["\']\s*:\s*\[([\s\S]*?)\]/ui', $raw, $m)) {
            $cards_block = $m[1];
            preg_match_all('/\{([\s\S]*?)\}/u', $cards_block, $card_objects);
            if (!empty($card_objects[1])) {
                $cards = [];
                foreach ($card_objects[1] as $c_str) {
                    $c_title = '';
                    $c_desc = '';
                    $c_img = '';
                    if (preg_match('/["\']title["\']\s*:\s*["\']([^"\']+)["\']/ui', $c_str, $tm)) {
                        $c_title = $tm[1];
                    }
                    if (preg_match('/["\']desc["\']\s*:\s*["\']([^"\']+)["\']/ui', $c_str, $dm)) {
                        $c_desc = $dm[1];
                    }
                    if (preg_match('/["\']img["\']\s*:\s*["\']([^"\']*)["\']/ui', $c_str, $im)) {
                        $c_img = $im[1];
                    }
                    if (!empty($c_title) || !empty($c_desc)) {
                        $cards[] = [
                            'title' => $c_title,
                            'desc'  => $c_desc,
                            'img'   => $c_img
                        ];
                    }
                }
                if (!empty($cards)) {
                    $data['info_cards'] = $cards;
                }
            }
        }
        if (preg_match('/["\']keywords["\']\s*:\s*\[([\s\S]*?)\]/ui', $raw, $m)) {
            preg_match_all('/["\']([^"\']+)["\']/u', $m[1], $kw_match);
            if (!empty($kw_match[1])) {
                $data['keywords'] = $kw_match[1];
            }
        }
        return (!empty($data['short_desc']) || !empty($data['niche_name']) || !empty($data['keywords'])) ? $data : null;
    }

    /**
     * AJAX Handler: Get Knowledge Library
     */
    public function ajax_get_knowledge_library() {
        check_ajax_referer('fmb_ai_nonce', '_nonce');
        $built_in = [
            'Air Fryer (এয়ার ফ্রায়ার)', 
            'Blender & Grinder (ব্লেন্ডার ও মসলা বাটা)', 
            'Hair Trimmer & Shaver (ট্রিমার ও শেভার)', 
            'Smart Watch (স্মার্ট ওয়াচ ও ঘড়ি)', 
            'Rust & Iron Remover (মরিচা ও আয়রন রিমুভার)', 
            'Magic Floor Mop (ম্যাজিক ফ্লোর মপ)', 
            'Hair Growth Oil (হেয়ার অয়েল ও সিরাম)'
        ];
        
        $custom_library = get_option('fmb_custom_ai_knowledge_base', []);
        $custom_items = [];
        if (!empty($custom_library)) {
            foreach ($custom_library as $k => $item) {
                $name = !empty($item['niche_name']) ? $item['niche_name'] : $k;
                $tag_count = !empty($item['keywords']) ? count($item['keywords']) : 0;
                $tags_preview = !empty($item['keywords']) ? implode(', ', array_slice($item['keywords'], 0, 4)) . '...' : '';
                $custom_items[] = [
                    'slug' => $k,
                    'name' => $name,
                    'tags' => $tags_preview,
                    'tag_count' => $tag_count
                ];
            }
        }

        wp_send_json_success([
            'built_in' => $built_in,
            'custom'   => $custom_items
        ]);
    }

    /**
     * AJAX Handler: Delete Custom Knowledge
     */
    public function ajax_delete_custom_knowledge() {
        check_ajax_referer('fmb_ai_nonce', '_nonce');
        $slug = sanitize_title($_POST['slug'] ?? '');
        $custom_library = get_option('fmb_custom_ai_knowledge_base', []);
        if (isset($custom_library[$slug])) {
            unset($custom_library[$slug]);
            update_option('fmb_custom_ai_knowledge_base', $custom_library);
            wp_send_json_success();
        }
        wp_send_json_error();
    }
}

// Instantiate AI Generator
FMB_AI_Product_Generator::get_instance();
