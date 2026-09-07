<?php
if (!defined('ABSPATH')) {
    exit;
}

// 1. Admin Notice to start setup
add_action('admin_notices', 'fmb_setup_wizard_notice');
function fmb_setup_wizard_notice() {
    // Only show to admins and if not completed
    if (!current_user_can('manage_options') || get_option('fmb_setup_completed')) {
        return;
    }
    // Don't show on the setup page itself
    if (isset($_GET['page']) && $_GET['page'] === 'fmb-setup') {
        return;
    }
    
    $setup_url = admin_url('admin.php?page=fmb-setup');
    ?>
    <div class="notice notice-info is-dismissible" style="border-left-color: #4f46e5; background: #fff; border-radius: 8px; padding: 20px 24px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); border-width: 1px 1px 1px 4px;">
        <p style="font-size: 16px; margin-top: 0;"><strong>🎉 Welcome to FMB E-Com Store Theme!</strong></p>
        <p style="font-size: 14px; color: #475569;">To get the most out of your new theme, please run the setup wizard to install required plugins and configure your store.</p>
        <p style="margin-bottom: 0;">
            <a href="<?php echo esc_url($setup_url); ?>" class="button button-primary" style="background: #4f46e5; border-color: #4338ca; padding: 4px 16px; font-weight: 600;">Start Setup Wizard</a>
        </p>
    </div>
    <?php
}

// 2. Register Setup Wizard Page
add_action('admin_menu', 'fmb_register_setup_wizard', 99);
function fmb_register_setup_wizard() {
    add_submenu_page(
        null, // Hide from menu
        'Theme Setup Wizard',
        'Theme Setup',
        'manage_options',
        'fmb-setup',
        'fmb_setup_wizard_page_html'
    );
}

// 3. The Setup Wizard UI
function fmb_setup_wizard_page_html() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    $is_woo_active = in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')));
    
    // For media uploader
    wp_enqueue_media();
    ?>
    <style>
        .fmb-setup-wrap {
            max-width: 800px; margin: 40px auto; background: #fff;
            border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            overflow: hidden;
        }
        .fmb-setup-header {
            background: #0f172a; color: #fff; padding: 30px; text-align: center;
        }
        .fmb-setup-header h1 { color: #fff; margin: 0; font-size: 24px; font-weight: 800; }
        .fmb-setup-header p { color: #94a3b8; font-size: 15px; margin-top: 8px; }
        .fmb-setup-body { padding: 40px; }
        
        .fmb-step { display: none; animation: fadeIn 0.4s ease; }
        .fmb-step.active { display: block; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        
        .fmb-step-title { font-size: 20px; font-weight: 700; color: #1e293b; margin-bottom: 15px; }
        .fmb-step-desc { font-size: 15px; color: #475569; margin-bottom: 25px; line-height: 1.5; }
        
        .fmb-btn {
            background: #4f46e5; color: #fff; border: none; padding: 12px 24px;
            font-size: 15px; font-weight: 600; border-radius: 6px; cursor: pointer;
            transition: all 0.2s; display: inline-flex; align-items: center; gap: 8px; text-decoration: none;
        }
        .fmb-btn:hover { background: #4338ca; transform: translateY(-1px); color: #fff; }
        .fmb-btn:disabled { background: #94a3b8; cursor: not-allowed; transform: none; }
        
        .fmb-form-group { margin-bottom: 20px; }
        .fmb-form-group label { display: block; font-weight: 600; color: #334155; margin-bottom: 8px; }
        .fmb-form-control {
            width: 100%; padding: 10px 12px; border: 1px solid #cbd5e1;
            border-radius: 6px; font-size: 15px; color: #0f172a;
        }
        .fmb-form-control:focus { border-color: #4f46e5; outline: none; box-shadow: 0 0 0 3px rgba(79,70,229,0.1); }
        
        .fmb-img-preview {
            width: 120px; height: 120px; border: 2px dashed #cbd5e1;
            border-radius: 8px; display: flex; align-items: center; justify-content: center;
            margin-bottom: 10px; overflow: hidden; background: #f8fafc;
        }
        .fmb-img-preview img { max-width: 100%; max-height: 100%; object-fit: contain; }
        .fmb-loading { display: none; }
        
        .fmb-stepper { display: flex; justify-content: center; gap: 20px; margin-bottom: 30px; }
        .fmb-step-dot {
            width: 30px; height: 30px; border-radius: 50%; background: #e2e8f0;
            color: #64748b; display: flex; align-items: center; justify-content: center;
            font-weight: 700; font-size: 14px; transition: all 0.3s;
        }
        .fmb-step-dot.completed { background: #10b981; color: #fff; }
        .fmb-step-dot.current { background: #4f46e5; color: #fff; box-shadow: 0 0 0 4px rgba(79,70,229,0.2); }
    </style>

    <div class="fmb-setup-wrap">
        <div class="fmb-setup-header">
            <h1>Theme Setup Wizard</h1>
            <p>Get your store ready in just a few clicks.</p>
        </div>
        
        <div class="fmb-setup-body">
            <div class="fmb-stepper">
                <div class="fmb-step-dot current" id="dot-1">1</div>
                <div class="fmb-step-dot" id="dot-2">2</div>
                <div class="fmb-step-dot" id="dot-3">3</div>
            </div>

            <!-- Step 1: Plugins -->
            <div class="fmb-step active" id="step-1">
                <div class="fmb-step-title">Required Plugins</div>
                <div class="fmb-step-desc">
                    FMB E-Com Store requires <strong>WooCommerce</strong> to function properly. 
                    <?php if ($is_woo_active): ?>
                        <br><span style="color: #10b981; font-weight:600;">✓ WooCommerce is already active!</span>
                    <?php else: ?>
                        <br>Click the button below to install and activate it automatically.
                    <?php endif; ?>
                </div>
                
                <?php if ($is_woo_active): ?>
                    <button class="fmb-btn fmb-next-step" data-next="2">Continue to Next Step →</button>
                <?php else: ?>
                    <button class="fmb-btn" id="btn-install-plugins">
                        Install & Activate WooCommerce
                        <span class="fmb-loading">⏳</span>
                    </button>
                <?php endif; ?>
            </div>

            <!-- Step 2: Site Info -->
            <div class="fmb-step" id="step-2">
                <div class="fmb-step-title">Site Information</div>
                <div class="fmb-step-desc">Let's set up your store's basic identity.</div>
                
                <form id="fmb-setup-info-form">
                    <div class="fmb-form-group">
                        <label>Site Logo</label>
                        <div class="fmb-img-preview" id="setup-logo-preview">
                            <?php 
                            $custom_logo_id = get_theme_mod('custom_logo');
                            if ($custom_logo_id) {
                                echo wp_get_attachment_image($custom_logo_id, 'full');
                            } else {
                                echo '<span style="color:#94a3b8; font-size:12px;">No Logo</span>';
                            }
                            ?>
                        </div>
                        <input type="hidden" name="fmb_logo_id" id="fmb_logo_id" value="<?php echo esc_attr($custom_logo_id); ?>">
                        <button type="button" class="button" id="btn-upload-logo">Upload Logo</button>
                    </div>
                    
                    <div class="fmb-form-group">
                        <label>Site Title</label>
                        <input type="text" name="fmb_site_title" class="fmb-form-control" value="<?php echo esc_attr(get_option('blogname')); ?>" required>
                    </div>
                    
                    <div class="fmb-form-group">
                        <label>Tagline</label>
                        <input type="text" name="fmb_site_desc" class="fmb-form-control" value="<?php echo esc_attr(get_option('blogdescription')); ?>">
                    </div>

                    <button type="submit" class="fmb-btn" id="btn-save-info">
                        Save & Continue
                        <span class="fmb-loading">⏳</span>
                    </button>
                </form>
            </div>

            <!-- Step 3: Finish -->
            <div class="fmb-step" id="step-3">
                <div class="fmb-step-title">🎉 You're All Set!</div>
                <div class="fmb-step-desc">
                    Your theme has been successfully configured. Default pages have been generated and you're ready to start selling!
                </div>
                
                <button class="fmb-btn" id="btn-finish-setup" style="background:#10b981;">
                    Go to Dashboard
                    <span class="fmb-loading">⏳</span>
                </button>
            </div>
        </div>
    </div>

    <script>
    jQuery(document).ready(function($) {
        let ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
        
        function goToStep(step) {
            $('.fmb-step').removeClass('active');
            $('#step-' + step).addClass('active');
            
            $('.fmb-step-dot').removeClass('current');
            $('#dot-' + step).addClass('current');
            for(let i=1; i<step; i++) {
                $('#dot-' + i).addClass('completed');
            }
        }

        $('.fmb-next-step').on('click', function() {
            goToStep($(this).data('next'));
        });

        // Upload Logo
        $('#btn-upload-logo').on('click', function(e) {
            e.preventDefault();
            var frame = wp.media({
                title: 'Select or Upload Logo',
                button: { text: 'Use this media' },
                multiple: false
            });
            frame.on('select', function() {
                var attachment = frame.state().get('selection').first().toJSON();
                $('#fmb_logo_id').val(attachment.id);
                $('#setup-logo-preview').html('<img src="'+attachment.url+'" style="max-width:100%;max-height:100%;object-fit:contain;">');
            });
            frame.open();
        });

        // Step 1: Install Plugins
        $('#btn-install-plugins').on('click', function() {
            let btn = $(this);
            btn.prop('disabled', true).find('.fmb-loading').show();
            
            $.post(ajaxurl, { action: 'fmb_setup_install_plugin' }, function(res) {
                if (res.success) {
                    btn.html('✓ Installed Successfully!').css('background', '#10b981');
                    setTimeout(() => goToStep(2), 1000);
                } else {
                    alert(res.data.message || 'Installation failed. Please install WooCommerce manually.');
                    btn.prop('disabled', false).find('.fmb-loading').hide();
                }
            }).fail(function() {
                alert('Server Error. Please install WooCommerce manually.');
                btn.prop('disabled', false).find('.fmb-loading').hide();
            });
        });

        // Step 2: Save Info
        $('#fmb-setup-info-form').on('submit', function(e) {
            e.preventDefault();
            let form = $(this);
            let btn = $('#btn-save-info');
            btn.prop('disabled', true).find('.fmb-loading').show();

            $.post(ajaxurl, {
                action: 'fmb_setup_save_info',
                data: form.serialize()
            }, function(res) {
                if(res.success) {
                    btn.html('✓ Saved!').css('background', '#10b981');
                    setTimeout(() => goToStep(3), 1000);
                } else {
                    alert('Failed to save.');
                    btn.prop('disabled', false).find('.fmb-loading').hide();
                }
            });
        });

        // Step 3: Finish
        $('#btn-finish-setup').on('click', function() {
            let btn = $(this);
            btn.prop('disabled', true).find('.fmb-loading').show();

            $.post(ajaxurl, { action: 'fmb_setup_finish' }, function(res) {
                window.location.replace('<?php echo admin_url(); ?>');
            });
        });
    });
    </script>
    <?php
}

// 4. AJAX Handlers

// Install WooCommerce
add_action('wp_ajax_fmb_setup_install_plugin', 'fmb_ajax_setup_install_plugin');
function fmb_ajax_setup_install_plugin() {
    if (!current_user_can('manage_options')) wp_send_json_error(array('message' => 'Unauthorized'));

    // Check if already active
    if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
        wp_send_json_success();
    }
    
    // Check if installed but inactive
    if (file_exists(WP_PLUGIN_DIR . '/woocommerce/woocommerce.php')) {
        activate_plugin('woocommerce/woocommerce.php');
        wp_send_json_success();
    }
    
    // Download and Install
    include_once(ABSPATH . 'wp-admin/includes/plugin-install.php');
    include_once(ABSPATH . 'wp-admin/includes/class-wp-upgrader.php');
    
    $api = plugins_api('plugin_information', array('slug' => 'woocommerce', 'fields' => array('sections' => false)));
    if (is_wp_error($api)) wp_send_json_error(array('message' => 'Could not connect to WP API'));
    
    $upgrader = new Plugin_Upgrader(new Automatic_Upgrader_Skin());
    $result = $upgrader->install($api->download_link);
    
    if (is_wp_error($result) || is_null($result)) {
        wp_send_json_error(array('message' => 'Failed to install plugin'));
    }
    
    activate_plugin('woocommerce/woocommerce.php');
    wp_send_json_success();
}

// Save Info
add_action('wp_ajax_fmb_setup_save_info', 'fmb_ajax_setup_save_info');
function fmb_ajax_setup_save_info() {
    if (!current_user_can('manage_options')) wp_send_json_error();
    
    parse_str($_POST['data'], $data);
    
    if (!empty($data['fmb_logo_id'])) {
        set_theme_mod('custom_logo', intval($data['fmb_logo_id']));
    }
    if (!empty($data['fmb_site_title'])) {
        update_option('blogname', sanitize_text_field($data['fmb_site_title']));
    }
    if (isset($data['fmb_site_desc'])) {
        update_option('blogdescription', sanitize_text_field($data['fmb_site_desc']));
    }
    
    wp_send_json_success();
}

// Finish
add_action('wp_ajax_fmb_setup_finish', 'fmb_ajax_setup_finish');
function fmb_ajax_setup_finish() {
    if (!current_user_can('manage_options')) wp_send_json_error();
    
    update_option('fmb_setup_completed', true);
    
    // Trigger default pages generation
    if (function_exists('fmb_create_default_pages')) {
        fmb_create_default_pages();
    }
    
    wp_send_json_success();
}
