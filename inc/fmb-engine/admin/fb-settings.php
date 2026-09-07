<?php
namespace fmb_engine\Admin;
use fmb_engine\Traits\Core;
class Fb_Settings {
    use Core;

    public static function ensure_facebook_loaded() {
        if (!class_exists('\fmb_engine\Facebook\Facebook') && defined('FMB_ENGINE_PATH')) {
            if (file_exists(FMB_ENGINE_PATH . 'facebook/class-settings.php')) {
                require_once FMB_ENGINE_PATH . 'facebook/class-settings.php';
            }
            if (file_exists(FMB_ENGINE_PATH . 'facebook/modules/facebook/function-helpers.php')) {
                require_once FMB_ENGINE_PATH . 'facebook/modules/facebook/function-helpers.php';
            }
            if (file_exists(FMB_ENGINE_PATH . 'facebook/modules/facebook/FacebookCapiEventHelper.php')) {
                require_once FMB_ENGINE_PATH . 'facebook/modules/facebook/FacebookCapiEventHelper.php';
            }
            if (file_exists(FMB_ENGINE_PATH . 'facebook/modules/facebook/facebook.php')) {
                require_once FMB_ENGINE_PATH . 'facebook/modules/facebook/facebook.php';
            }
            if (file_exists(FMB_ENGINE_PATH . 'facebook/modules/facebook/facebook-server.php')) {
                require_once FMB_ENGINE_PATH . 'facebook/modules/facebook/facebook-server.php';
            }
        }
    }

    public static function render_settings() {
        self::ensure_facebook_loaded();
        $current_tab = isset($_GET['tab']) ? $_GET['tab'] : 'general';
        $base_url = admin_url('admin.php?page=fmb-engine-fb-settings');
        echo "<div class='fmb-engine-main-box'>";
        require_once __DIR__ . "/promo.php";
        if (function_exists('fmb_engine_render_header')) { fmb_engine_render_header(); }
        ?>

        <div class="fmb-engine-settings-container layout-sidebar">
            <!-- Left Sidebar Navigation -->
            <div class="fmb-engine-sidebar">
                <div class="sidebar-menu-item active" data-platform="facebook">
                    <svg viewBox="0 0 24 24"><path fill="#1877F2" d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                    Facebook
                </div>
                <div class="sidebar-menu-item" data-platform="tiktok">
                    <svg xmlns="http://www.w3.org/2000/svg" shape-rendering="geometricPrecision" text-rendering="geometricPrecision" image-rendering="optimizeQuality" fill-rule="evenodd" clip-rule="evenodd" viewBox="0 0 512 512"><path d="M256 0c141.385 0 256 114.615 256 256S397.385 512 256 512 0 397.385 0 256 114.615 0 256 0z"/><path fill="#2DCCD3" fill-rule="nonzero" d="M344.487 161.312c11.585 11.945 26.033 19.226 40.593 22.539v-8.971c-13.681-.969-27.993-5.274-40.593-13.568zm-83.689-59.166v200.601c0 26.281-18.888 43.185-41.855 43.185-7.619 0-14.854-1.781-21.142-5.071 7.979 10.188 20.578 16.048 34.395 16.048 22.968 0 41.855-16.905 41.855-43.208V113.1h36.401a100.278 100.278 0 01-2.434-10.954h-47.22zm-29.864 116.618v-9.939c-4.599-.766-9.196-1.015-13.006-1.015-51.818 0-95.206 41.586-95.206 93.155 0 33.855 16.476 62.795 41.517 79.926-17.445-17.31-28.264-41.54-28.264-68.971 0-51.48 43.253-93.043 94.959-93.156z"/><path fill="#F1204A" fill-rule="nonzero" d="M313.36 299.433c0 64.057-49.001 98.002-95.184 98.002-19.992 0-38.564-6.041-53.937-16.545 17.266 17.131 41.022 27.499 67.19 27.499 46.184 0 95.184-33.945 95.184-98.002v-104.38c-4.597-3.11-9.015-6.739-13.253-10.976v104.402zM197.801 340.86c-5.635-7.122-8.994-16.341-8.994-27.159 0-30.361 23.734-46.409 55.38-43.073v-50.849c-4.598-.766-9.196-1.014-13.028-1.014h-.226v40.886c-31.644-3.313-55.379 12.712-55.379 43.096 0 17.761 9.084 31.239 22.247 38.113zM385.08 183.851v37.979c-21.029 0-40.931-4.012-58.467-15.823 20.421 20.421 45.192 26.8 71.721 26.8v-46.972a82.367 82.367 0 01-13.254-1.984zm-40.593-22.54c-11.202-11.517-19.745-27.385-23.215-48.211h-10.819c6.176 22.517 18.888 38.227 34.034 48.211z"/><path fill="#fff" fill-rule="nonzero" d="M218.176 397.435c46.183 0 95.184-33.944 95.184-98.002V195.031c4.238 4.237 8.655 7.866 13.253 10.976 17.536 11.811 37.438 15.823 58.468 15.823v-37.979c-14.561-3.313-29.009-10.593-40.594-22.54-15.146-9.984-27.859-25.694-34.034-48.211h-36.402v200.601c0 26.303-18.888 43.208-41.856 43.208-13.816 0-26.415-5.86-34.394-16.048-13.163-6.875-22.247-20.353-22.247-38.114 0-30.384 23.734-46.409 55.379-43.096v-40.887c-51.705.113-94.958 41.676-94.958 93.156 0 27.431 10.819 51.661 28.264 68.971 15.372 10.503 33.945 16.544 53.937 16.544z"/></svg>
                    TikTok
                </div>
                <div class="sidebar-menu-item" data-platform="google">
                    <svg viewBox="0 0 24 24"><path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/><path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/><path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/><path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/></svg>
                    Google Ads
                </div>
                <div class="sidebar-menu-item" data-platform="google-analytics">
                    <svg viewBox="0 0 24 24"><path d="M5 19h4v-7H5v7zm6 0h4V6h-4v13zm6 0h4v-10h-4v10z" fill="#F4B400"/><path d="M11 19h4V6h-4v13z" fill="#E37400"/><path d="M17 19h4v-10h-4v10z" fill="#D93025"/></svg>
                    Google Analytics
                </div>
            </div>

            <!-- Right Content Area -->
            <div class="fmb-engine-main-content">
                <!-- Facebook Platform -->
                <div class="platform-container active" id="platform-facebook">
                    <div class="fmb-engine-settings-wrapper">
                        <div class="platform-tabs">
                            <a href="#tab-general" class="platform-tab active" data-tab="tab-general">FB Pixel Settings</a>
                            <a href="#tab-pixel-ids" class="platform-tab" data-tab="tab-pixel-ids">FB Pixel IDs</a>
                            <a href="#tab-custom-events" class="platform-tab" data-tab="tab-custom-events">FB Custom Event</a>
                        </div>
                        <div class="fmb-engine-settings-content">
                            <div id="tab-general" class="platform-tab-content" style="display: block;">
                                <?php self::render_fb_general_settings(); ?>
                            </div>
                            <div id="tab-pixel-ids" class="platform-tab-content" style="display: none;">
                                <?php self::render_fb_pixel_ids_settings(); ?>
                            </div>
                            <div id="tab-custom-events" class="platform-tab-content" style="display: none;">
                                <?php self::render_fb_custom_events_settings(); ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TikTok Platform -->
                <div class="platform-container" id="platform-tiktok" style="display: none;">
                    <div class="fmb-engine-settings-wrapper">
                        <div class="platform-tabs">
                            <a href="#tab-tiktok-general" class="platform-tab active" data-tab="tab-tiktok-general">TikTok Settings</a>
                            <a href="#tab-tiktok-pixels" class="platform-tab" data-tab="tab-tiktok-pixels">TikTok Pixel IDs</a>
                            <a href="#tab-tiktok-custom" class="platform-tab" data-tab="tab-tiktok-custom">TikTok Custom Events</a>
                        </div>
                        <div class="fmb-engine-settings-content">
                            <div id="tab-tiktok-general" class="platform-tab-content" style="display: block;">
                                <?php self::render_tiktok_general_settings(); ?>
                            </div>
                            <div id="tab-tiktok-pixels" class="platform-tab-content" style="display: none;">
                                <?php self::render_tiktok_pixels_settings(); ?>
                            </div>
                            <div id="tab-tiktok-custom" class="platform-tab-content" style="display: none;">
                                <div class="fmb-engine-service-section">
                                    <div class="fmb-engine-service-header">
                                        <strong>TikTok Custom Events</strong>
                                        <p class="description">Custom Events tracking for TikTok Pixel is coming soon.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Google Ads Platform -->
                <div class="platform-container" id="platform-google" style="display: none;">
                    <div class="fmb-engine-settings-wrapper" style="position: relative;">
                        <!-- Upcoming Overlay -->
                        <div class="upcoming-overlay">
                            <div class="upcoming-content">
                                <div class="upcoming-icon">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" />
                                    </svg>
                                </div>
                                <h3>Exciting New Features!</h3>
                                <p>Google Ads Settings are on the way.</p>
                                <span class="upcoming-badge">Upcoming</span>
                            </div>
                        </div>
                        <div class="platform-tabs">
                            <a href="#tab-google" class="platform-tab active" data-tab="tab-google">Google Ads Settings</a>
                        </div>
                        <div class="fmb-engine-settings-content">
                            <div id="tab-google" class="platform-tab-content" style="display: block;">
                                <?php self::render_google_settings(); ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Google Analytics Platform -->
                <div class="platform-container" id="platform-google-analytics" style="display: none;">
                    <div class="fmb-engine-settings-wrapper" style="position: relative;">
                        <!-- Upcoming Overlay -->
                        <div class="upcoming-overlay">
                            <div class="upcoming-content">
                                <div class="upcoming-icon">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" />
                                    </svg>
                                </div>
                                <h3>Exciting New Features!</h3>
                                <p>Google Analytics Settings are on the way.</p>
                                <span class="upcoming-badge">Upcoming</span>
                            </div>
                        </div>
                        <div class="platform-tabs">
                            <a href="#tab-google-analytics" class="platform-tab active" data-tab="tab-google-analytics">Google Analytics Settings</a>
                        </div>
                        <div class="fmb-engine-settings-content">
                            <div id="tab-google-analytics" class="platform-tab-content" style="display: block;">
                                <?php self::render_google_analytics_settings(); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <script>
        (function($) {
            "use strict";

            function initFbTabs() {
                $(document).off('click', '.fmb-engine-sidebar .sidebar-menu-item').on('click', '.fmb-engine-sidebar .sidebar-menu-item', function(e) {
                    e.preventDefault();
                    var $item = $(this);
                    var platform = $item.data('platform') || $item.attr('data-platform');
                    if (!platform) return;

                    $('.fmb-engine-sidebar .sidebar-menu-item').removeClass('active');
                    $item.addClass('active');

                    $('.fmb-engine-main-content .platform-container').removeClass('active').hide();
                    var $target = $('#platform-' + platform);
                    if ($target.length) {
                        $target.addClass('active').show();
                    }

                    if (history.replaceState) {
                        history.replaceState(null, null, '#platform-' + platform);
                    }
                });

                $(document).off('click', '.platform-tab').on('click', '.platform-tab', function(e) {
                    var $tab = $(this);
                    var tabId = $tab.data('tab') || $tab.attr('data-tab');
                    if (!tabId) return;
                    e.preventDefault();

                    var $container = $tab.closest('.platform-container');
                    if (!$container.length) return;

                    $container.find('.platform-tab').removeClass('active');
                    $container.find('.platform-tab-content').hide();

                    $tab.addClass('active');
                    $container.find('#' + tabId).show();

                    if (history.replaceState) {
                        history.replaceState(null, null, '#' + tabId);
                    }
                });

                function handleHash() {
                    if (!window.location.hash) return;
                    var hash = window.location.hash.substring(1);
                    if (!hash) return;

                    var cleanPlatform = hash.replace('platform-', '');
                    var $sidebarItem = $('.fmb-engine-sidebar .sidebar-menu-item[data-platform="' + cleanPlatform + '"]');
                    if ($sidebarItem.length) {
                        $sidebarItem.trigger('click');
                        return;
                    }

                    var $subTab = $('.platform-tab[data-tab="' + hash + '"]');
                    if ($subTab.length) {
                        var $container = $subTab.closest('.platform-container');
                        if ($container.length) {
                            var platformId = $container.attr('id').replace('platform-', '');
                            var $sItem = $('.fmb-engine-sidebar .sidebar-menu-item[data-platform="' + platformId + '"]');
                            if ($sItem.length) {
                                $('.fmb-engine-sidebar .sidebar-menu-item').removeClass('active');
                                $sItem.addClass('active');
                                $('.fmb-engine-main-content .platform-container').removeClass('active').hide();
                                $container.addClass('active').show();
                            }
                        }
                        $subTab.trigger('click');
                    }
                }

                handleHash();
                $(window).on('hashchange', handleHash);
            }

            $(document).ready(initFbTabs);
            if (document.readyState === 'interactive' || document.readyState === 'complete') {
                initFbTabs();
            }
        })(jQuery);
        </script>
        <?php
        echo "</div>";
    }

    public static function submit_fb_general_settings() {
        if( ! self::is_license_active() ) {
            return ;
        }
        self::ensure_facebook_loaded();
        if (isset($_POST['fmb_engine_save_settings']) && check_admin_referer('fmb_engine_save_settings')) {
            if (isset($_POST['FMB Engine']) && is_array($_POST['FMB Engine'])) {
                if (class_exists('\fmb_engine\Facebook\Facebook')) {
                    $facebook = \fmb_engine\Facebook\Facebook::instance();
                    $facebook->updateOptions(null);
                }
            }
        }
    }

    public static function render_fb_general_settings() {
        self::ensure_facebook_loaded();
        if (!class_exists('\fmb_engine\Facebook\Facebook')) {
            echo '<div class="notice notice-error"><p>Facebook Module could not be loaded.</p></div>';
            return;
        }

        if (isset($_POST['fmb_engine_save_settings']) && check_admin_referer('fmb_engine_save_settings')) {
            if (isset($_POST['FMB Engine']) && is_array($_POST['FMB Engine'])) {
                $facebook = \fmb_engine\Facebook\Facebook::instance();
                $facebook->updateOptions(null);
                echo '<div class="notice notice-success"><p>Settings saved successfully!</p></div>';
            }
        }

        $facebook = \fmb_engine\Facebook\Facebook::instance();
        ?>
        <div class="fmb-engine-section">
            <div class="fmb-engine-option-heading">
                <h2 class="fmb-engine-section-title">Facebook General Settings</h2>
                <p class="fmb-engine-subtitle">Manage your Facebook Pixel settings.</p>
            </div>
            <form method="post" enctype="multipart/form-data" id="fmb-engine-fb-settings-form">
                <?php wp_nonce_field('fmb_engine_save_settings'); ?>
                <div class="fmb-engine-form-container">
                    <div class="fmb-engine-field-row">
                        <div class="fmb-engine-field-label">Enable Pixel</div>
                        <div class="fmb-engine-field-input">
                            <label class="fmb-engine-toggle-switch">
                                <input type="hidden" name="fmb_engine[fmb_engine_facebook][fmb_engine_enabled]" value="0">
                                <input type="checkbox" name="fmb_engine[fmb_engine_facebook][fmb_engine_enabled]" value="1" <?php checked( $facebook->getOption( 'fmb_engine_enabled' ), true ); ?>>
                                <span class="fmb-engine-slider"></span>
                            </label>
                        </div>
                    </div>

                    <div class="fmb-engine-field-row">
                        <div class="fmb-engine-field-label">Enable Conversion API</div>
                            <div class="fmb-engine-field-input">
                            <label class="fmb-engine-toggle-switch">
                                <input type="hidden" name="fmb_engine[fmb_engine_facebook][fmb_engine_use_server_api]" value="0">
                                <input type="checkbox" name="fmb_engine[fmb_engine_facebook][fmb_engine_use_server_api]" value="1" <?php checked( $facebook->getOption( 'fmb_engine_use_server_api' ), true ); ?>>
                                <span class="fmb-engine-slider"></span>
                            </label>
                            </div>
                        </div>

                    <div class="fmb-engine-field-row">
                        <div class="fmb-engine-field-label">Enable Advanced Matching</div>
                            <div class="fmb-engine-field-input">
                            <label class="fmb-engine-toggle-switch">
                                <input type="hidden" name="fmb_engine[fmb_engine_facebook][fmb_engine_advanced_matching_enabled]" value="0">
                                <input type="checkbox" name="fmb_engine[fmb_engine_facebook][fmb_engine_advanced_matching_enabled]" value="1" <?php checked( $facebook->getOption( 'fmb_engine_advanced_matching_enabled' ), true ); ?>>
                                <span class="fmb-engine-slider"></span>
                            </label>
                            </div>
                        </div>

                    <?php
                    require_once FMB_ENGINE_PATH . 'facebook/modules/facebook/views/html-settings.php';
                    ?>

                    <?php self::addMetaTagFields( $facebook, "https://www.facebook.com/business/help" ); ?>
                            </div>
                <button type="submit" class="fmb-engine-submit-button" name="fmb_engine_save_settings">
                    Save Settings
                </button>
            </form>
        </div>
        <?php
    }

    public static function render_fb_pixel_ids_settings() {
        self::ensure_facebook_loaded();
        if (!class_exists('\fmb_engine\Facebook\Facebook')) {
            echo '<div class="notice notice-error"><p>Facebook Module could not be loaded.</p></div>';
            return;
        }

        if (isset($_POST['fmb_engine_save_pixel_ids']) && check_admin_referer('fmb_engine_save_settings')) {
            if (isset($_POST['FMB Engine']) && is_array($_POST['FMB Engine'])) {
                $facebook = \fmb_engine\Facebook\Facebook::instance();
                $facebook->updateOptions(null);
                echo '<div class="notice notice-success"><p>Pixel IDs saved successfully!</p></div>';
            }
        }

        $facebook = \fmb_engine\Facebook\Facebook::instance();
        $pixels = (array) $facebook->getOption( 'fmb_engine_pixel_id' );
        if ( empty( $pixels ) ) {
            $pixels = [ '' ]; // Ensure at least one empty pixel input is shown
        }
        ?>
        <div class="fmb-engine-section">
            <div class="fmb-engine-option-heading">
                <h2 class="fmb-engine-section-title">Facebook Pixel IDs</h2>
                <p class="fmb-engine-subtitle">Add and manage your Meta Pixel IDs and Conversion APIs.</p>
            </div>
            <form method="post" enctype="multipart/form-data" id="fmb-engine-fb-pixel-ids-form">
                <?php wp_nonce_field('fmb_engine_save_settings'); ?>
                <div class="fmb-engine-form-container" id="pixel-groups-container">
                    
                    <?php foreach ( $pixels as $index => $pixel_id ) : ?>
                    <div class="fmb-engine-event-section pixel-group" style="padding: 30px; position: relative;">
                        <button type="button" class="remove-pixel-group" style="position: absolute; top: 15px; right: 15px; color: #dc3232; border: 1px solid #dc3232; border-radius: 5px; background: #fff; cursor: pointer; font-weight: bold; width: 24px; height: 24px; display: flex; align-items: center; justify-content: center; padding: 0; z-index: 10;">X</button>
                        
                        <div class="fmb-engine-field-row" style="border: none; padding: 0 0 20px 0;">
                            <div class="fmb-engine-field-label" style="width: 200px;">Meta Pixel ID</div>
                            <div class="fmb-engine-field-input" style="flex: 1;">
                                <?php $facebook->render_pixel_id( 'fmb_engine_pixel_id', 'Meta Pixel ID', $index ); ?>
                            </div>
                        </div>

                        <div class="fmb-engine-field-row" style="border: none; padding: 0 0 20px 0;">
                            <div class="fmb-engine-field-label" style="width: 200px;">Conversion API Token</div>
                            <div class="fmb-engine-field-input" style="flex: 1;">
                                <?php $facebook->render_text_area_array_item( "fmb_engine_server_access_api_token", "Api token", $index ); ?>
                            </div>
                        </div>

                        <div class="fmb-engine-field-row" style="border: none; padding: 0;">
                            <div class="fmb-engine-field-label" style="width: 200px;">Test Event Code</div>
                            <div class="fmb-engine-field-input" style="flex: 1;">
                                <?php $facebook->render_text_input_array_item( "fmb_engine_test_api_event_code", "Code", $index ); ?>
                                <?php $facebook->render_text_input_array_item( "fmb_engine_test_api_event_code_expiration_at", "", $index, true ); ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    
                </div>
                
                <div style="display: flex; justify-content: flex-end; margin-bottom: 10px;">
                    <button type="button" class="button fmb-engine-add-pixel-button">Another Pixel</button>
                </div>
                <button type="submit" class="fmb-engine-submit-button" name="fmb_engine_save_pixel_ids">
                    Save Pixel IDs
                </button>
            </form>
        </div>

        <script>
        jQuery(document).ready(function($) {
            // Function to clone pixel group
            $('.fmb-engine-add-pixel-button').on('click', function(e) {
                e.preventDefault();
                
                const container = $('#pixel-groups-container');
                const pixelGroups = container.find('.pixel-group');
                const lastGroup = pixelGroups.last();
                
                // Clone the last group
                const newGroup = lastGroup.clone();
                
                // Determine new index
                const newIndex = pixelGroups.length;
                
                // Update IDs and names in the cloned group
                newGroup.find('input, textarea').each(function() {
                    const el = $(this);
                    
                    // Clear values
                    if(el.attr('type') !== 'hidden' || el.attr('name').indexOf('expiration') !== -1) {
                        el.val('');
                    }
                    
                    // Update IDs (replace _0, _1, etc with _newIndex)
                    if (el.attr('id')) {
                        el.attr('id', el.attr('id').replace(/_\d+$/, '_' + newIndex));
                    }
                });
                
                // Append the new group
                container.append(newGroup);
                
                // Toggle remove buttons visibility
                updateRemoveButtons();
            });
            
            // Remove pixel group
            $(document).on('click', '.remove-pixel-group', function(e) {
                e.preventDefault();
                const container = $('#pixel-groups-container');
                if (container.find('.pixel-group').length > 1) {
                    $(this).closest('.pixel-group').remove();
                    updateRemoveButtons();
                } else {
                    // Just clear the fields if it's the last one
                    $(this).closest('.pixel-group').find('input, textarea').val('');
                }
            });
            
            function updateRemoveButtons() {
                const groups = $('.pixel-group');
                if (groups.length === 1) {
                    groups.find('.remove-pixel-group').hide();
                } else {
                    groups.find('.remove-pixel-group').show();
                }
            }
            
            // Initialize
            updateRemoveButtons();
        });
        </script>
        <?php
    }

    public static function submit_fb_custom_events_settings() {
        if( ! self::is_license_active() ) {
            return ;
        }

        // Link Events
        $link_events = [];
        if (isset($_POST['link_selectors'], $_POST['link_event_names'])) {
            foreach ($_POST['link_selectors'] as $i => $selector) {
                if (!empty($selector)) {
                    $link_events[] = [
                        'selector' => sanitize_text_field($selector),
                        'event_name' => sanitize_text_field($_POST['link_event_names'][$i])
                    ];
                }
            }
        }
        update_option('ads_fb_link_events', $link_events);

        // Scroll Events
        $scroll_events = [];
        if (isset($_POST['scroll_percentages'], $_POST['scroll_event_names'])) {
            foreach ($_POST['scroll_percentages'] as $i => $percentage) {
                if ($percentage !== '') {
                    $scroll_events[] = [
                        'percentage' => (int) sanitize_text_field($percentage),
                        'event_name' => sanitize_text_field($_POST['scroll_event_names'][$i])
                    ];
                }
            }
        }
        update_option('ads_fb_scroll_events', $scroll_events);

        // Click Events
        $click_events = [];
        if (isset($_POST['click_selectors'], $_POST['click_event_names'])) {
            foreach ($_POST['click_selectors'] as $i => $selector) {
                if (!empty($selector)) {
                    $click_events[] = [
                        'selector' => sanitize_text_field($selector),
                        'event_name' => sanitize_text_field($_POST['click_event_names'][$i])
                    ];
                }
            }
        }
        update_option('ads_fb_click_events', $click_events);

        // Time Events
        $time_events = [];
        if (isset($_POST['time_seconds'], $_POST['time_event_names'])) {
            foreach ($_POST['time_seconds'] as $i => $seconds) {
                if ($seconds !== '') {
                    $time_events[] = [
                        'seconds' => (int) sanitize_text_field($seconds),
                        'event_name' => sanitize_text_field($_POST['time_event_names'][$i])
                    ];
                }
            }
        }
        update_option('ads_fb_time_events', $time_events);

        echo '<div class="notice notice-success"><p>Custom Event settings saved successfully!</p></div>';
    }

    public static function render_fb_custom_events_settings() {
        if (isset($_POST['ads_save_fb_custom_events_settings'])) {
            check_admin_referer('ads_fb_custom_events_settings_form_nonce')
                ? self::submit_fb_custom_events_settings()
                : self::render_error('Security check failed');
        }
        $link_events = get_option('ads_fb_link_events', []);
        $scroll_events = get_option('ads_fb_scroll_events', []);
        $click_events = get_option('ads_fb_click_events', []);
        $time_events = get_option('ads_fb_time_events', []);
        ?>
        <div class="fmb-engine-section">
            <div class="fmb-engine-option-heading">
                <h2 class="fmb-engine-section-title">Facebook Custom Events</h2>
                <p class="fmb-engine-subtitle">Set up tracking for custom events on your website.</p>
            </div>
            <form method="post">
                <?php wp_nonce_field('ads_fb_custom_events_settings_form_nonce'); ?>
                <div class="fmb-engine-form-container">
                    <!-- Link Events -->
                    <div class="fmb-engine-event-section">
                        <h3>Link Events (Class/ID)</h3>
                        <div class="event-container">
                            <?php foreach ($link_events as $event): ?>
                                <div class="event-row">
                                    <input type="text" name="link_selectors[]" value="<?= esc_attr($event['selector']) ?>" placeholder=".class / #id">
                                    <input type="text" name="link_event_names[]" value="<?= esc_attr($event['event_name']) ?>" placeholder="Event Name">
                                    <button type="button" class="delete-event-row">Delete</button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" class="button add-event-button fmb-engine-add-event-button" data-type="link">+ Add Link Event</button>
                    </div>
                    <!-- Scroll Events -->
                    <div class="fmb-engine-event-section">
                        <h3>Scroll Events</h3>
                        <div class="event-container">
                            <?php foreach ($scroll_events as $event): ?>
                                <div class="event-row">
                                    <input type="number" name="scroll_percentages[]" value="<?= esc_attr($event['percentage'] ?? '') ?>" placeholder="Scroll Percentage (e.g. 50)">
                                    <input type="text" name="scroll_event_names[]" value="<?= esc_attr($event['event_name']) ?>" placeholder="Event Name">
                                    <button type="button" class="delete-event-row">Delete</button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" class="button add-event-button fmb-engine-add-event-button" data-type="scroll">+ Add Scroll Event</button>
                    </div>
                    <!-- Click Events -->
                    <div class="fmb-engine-event-section">
                        <h3>Click Events</h3>
                        <div class="event-container">
                            <?php foreach ($click_events as $event): ?>
                                <div class="event-row">
                                    <input type="text" name="click_selectors[]" value="<?= esc_attr($event['selector']) ?>" placeholder=".class or #id">
                                    <input type="text" name="click_event_names[]" value="<?= esc_attr($event['event_name']) ?>" placeholder="Event Name">
                                    <button type="button" class="delete-event-row">Delete</button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" class="button add-event-button fmb-engine-add-event-button" data-type="click">+ Add Click Event</button>
                    </div>
                    <!-- Time Events -->
                    <div class="fmb-engine-event-section">
                        <h3>Time Events</h3>
                        <div class="event-container">
                            <?php foreach ($time_events as $event): ?>
                                <div class="event-row">
                                    <input type="number" name="time_seconds[]" value="<?= esc_attr($event['seconds']) ?>" placeholder="Time in seconds">
                                    <input type="text" name="time_event_names[]" value="<?= esc_attr($event['event_name']) ?>" placeholder="Event Name">
                                    <button type="button" class="delete-event-row">Delete</button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" class="button add-event-button fmb-engine-add-event-button" data-type="time">+ Add Time Event</button>
                    </div>
                </div>
                <button type="submit" class="fmb-engine-submit-button" name="ads_save_fb_custom_events_settings">
                    Save Settings
                </button>
            </form>
        </div>
        <script>
            jQuery(document).ready(function($) {
                // Add event row (delegated to document for robustness)
                $(document).on('click', '.fmb-engine-add-event-button', function(e) {
                    e.preventDefault();

                    const type = String($(this).data('type') || 'default').trim();
                    const section = $(this).closest('.fmb-engine-event-section');
                    const container = section.find('.event-container');

                    if (!container.length) {
                        console.warn('No .event-container found inside .fmb-engine-event-section for button:', this);
                        return;
                    }

                    const row = $('<div/>', { class: 'event-row' });
                    const input1 = $('<input/>', { type: 'text', class: 'fmb-engine-input', 'aria-label': `${type} selector` });
                    const input2 = $('<input/>', { type: 'text', class: 'fmb-engine-input', 'aria-label': `${type} event info` });
                    let input3 = null;

                    if (type === 'link') {
                        input1.attr('name', 'link_selectors[]').attr('placeholder', '.class / #id');
                        input2.attr('name', 'link_event_names[]').attr('placeholder', 'Event Name');
                    } else if (type === 'time') {
                        input1.attr('name', 'time_seconds[]').attr('placeholder', 'Time in seconds').attr('type', 'number');
                        input2.attr('name', 'time_event_names[]').attr('placeholder', 'Event Name');
                    } else if (type === 'scroll') {
                        input1.attr('name', 'scroll_percentages[]').attr('placeholder', 'Scroll Percentage (e.g. 50)').attr('type', 'number');
                        input2.attr('name', 'scroll_event_names[]').attr('placeholder', 'Event Name');
                    } else {
                        // generic fallback for other types (e.g., 'click')
                        input1.attr('name', `${type}_selectors[]`).attr('placeholder', '.class or #id');
                        input2.attr('name', `${type}_event_names[]`).attr('placeholder', 'Event Name');
                    }

                    const delBtn = $('<button/>', {
                        type: 'button',
                        class: 'delete-event-row',
                        'aria-label': 'Delete event row'
                    }).text('Delete');

                    // Append inputs in order
                    row.append(input1, input2);
                    if (input3) row.append(input3);
                    row.append(delBtn);

                    container.append(row);

                    // Focus the first input of the newly added row
                    row.find('input:first').focus();
                });

                // Delete event row (delegated)
                $(document).on('click', '.delete-event-row', function(e) {
                    e.preventDefault();
                    $(this).closest('.event-row').remove();
                });
            });
        </script>

        <?php
    }

    private static function render_error($message) {
        echo '<div class="notice notice-error"><p>' . esc_html($message) . '</p></div>';
    }

    private static function addMetaTagFields($pixel, $url) { 
        $metaTags = (array) $pixel->getOption( 'verify_meta_tag' );
        ?>
        <div class="fmb-engine-field-row">
            <div class="fmb-engine-field-label">Domain Verification
                <p class="ads-settings-description">Enter the verification meta-tag provided by Facebook for domain verification</p>
            </div>
            <div class="fmb-engine-field-input">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <div style="flex: 1;">
                        <?php $pixel->render_text_input_array_item( 'verify_meta_tag', 'Add the verification meta-tag there' ); ?>
                    </div>
                    <button type="button" class="button" id="fmb_engine_add_<?= $pixel->getSlug() ?>_meta_tag" style="white-space: nowrap; padding: 0 15px; height: 38px;">
                        Add another verification meta-tag
                    </button>
                </div>
            </div>
        </div>

        <?php
        foreach ( $metaTags as $index => $val ) :
            if ( $index == 0 ) continue; ?>
            <div class="fmb-engine-field-row">
                <div class="fmb-engine-field-label"></div>
                <div class="fmb-engine-field-input">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <div style="flex: 1;">
                            <?php $pixel->render_text_input_array_item( 'verify_meta_tag', 'Add the verification meta-tag there', $index ); ?>
                        </div>
                        <button type="button" class="button remove-meta-row" data-index="<?php echo esc_attr($index); ?>">Remove</button>
                    </div>
                </div>
            </div>
        <?php
        endforeach;
        ?>

        <script>
            jQuery( document ).ready( function ( $ ) {
                $( '#fmb_engine_add_<?=$pixel->getSlug()?>_meta_tag' ).click( function ( e ) {
                    e.preventDefault();
                    let newField = '<div class="fmb-engine-field-row"><div class="fmb-engine-field-label"></div><div class="fmb-engine-field-input"><div style="display: flex; align-items: center; gap: 10px;"><div style="flex: 1;"><input type="text" placeholder="Add the verification meta-tag there" name="fmb_engine[<?=$pixel->getSlug()?>][verify_meta_tag][]" value="" class="input-standard"></div><button type="button" class="button remove-meta-row" style="border: 1px solid #dc2626; color: #dc2626; border-radius: 5px;">Remove</button></div></div></div>';
                    
                    let container = $(this).closest('.fmb-engine-field-row').parent();
                    let lastRow = container.find('.remove-meta-row').last().closest('.fmb-engine-field-row');
                    if (lastRow.length === 0) {
                        lastRow = $(this).closest('.fmb-engine-field-row');
                    }
                    lastRow.after( newField );
                } );
                $( document ).on( 'click', '.remove-meta-row', function() {
                    $( this ).closest( '.fmb-engine-field-row' ).remove();
                } );
            } );
        </script>
        <?php
    }

    // --- TIKTOK MULTI-PIXEL AND 3-TAB SETTINGS ---

    public static function submit_tiktok_general_settings() {
        if( ! self::is_license_active() ) {
            return;
        }

        update_option('ofls_tiktok_enabled', !empty($_POST['ofls_tiktok_enabled']));
        update_option('ofls_tiktok_use_server_api', !empty($_POST['ofls_tiktok_use_server_api']));
        update_option('ofls_tiktok_advanced_matching_enabled', !empty($_POST['ofls_tiktok_advanced_matching_enabled']));
        update_option('ofls_tiktok_immediate_purchase', !empty($_POST['ofls_tiktok_immediate_purchase']));

        if (isset($_POST['ofls_tiktok_courier_threshold'])) {
            update_option('ofls_tiktok_courier_threshold', sanitize_text_field($_POST['ofls_tiktok_courier_threshold']));
        }

        echo '<div class="notice notice-success"><p>TikTok General Settings saved successfully!</p></div>';
    }

    public static function render_tiktok_general_settings() {
        if (isset($_POST['ads_save_tiktok_general_settings'])) {
            check_admin_referer('ads_tiktok_general_settings_form_nonce')
                ? self::submit_tiktok_general_settings()
                : self::render_error('Security check failed');
        }

        $enabled = get_option('ofls_tiktok_enabled', false);
        $use_server_api = get_option('ofls_tiktok_use_server_api', false);
        $advanced_matching = get_option('ofls_tiktok_advanced_matching_enabled', false);
        $immediate_purchase = get_option('ofls_tiktok_immediate_purchase', false);
        $courier_threshold = get_option('ofls_tiktok_courier_threshold', '0.3');
        ?>
        <div class="fmb-engine-section">
            <div class="fmb-engine-option-heading">
                <h2 class="fmb-engine-section-title">TikTok General Settings</h2>
                <p class="fmb-engine-subtitle">Configure your TikTok general tracking parameters.</p>
            </div>
            <form method="post">
                <?php wp_nonce_field('ads_tiktok_general_settings_form_nonce'); ?>
                <div class="fmb-engine-form-container">
                    <div class="fmb-engine-field-row">
                        <div class="fmb-engine-field-label">Enable TikTok Tracking</div>
                        <div class="fmb-engine-field-input">
                            <label class="fmb-engine-toggle-switch">
                                <input type="checkbox" name="ofls_tiktok_enabled" value="1" <?= checked($enabled, true, false) ?>>
                                <span class="fmb-engine-slider"></span>
                            </label>
                        </div>
                    </div>
                    <div class="fmb-engine-field-row">
                        <div class="fmb-engine-field-label">Enable Conversion API</div>
                        <div class="fmb-engine-field-input">
                            <label class="fmb-engine-toggle-switch">
                                <input type="checkbox" name="ofls_tiktok_use_server_api" value="1" <?= checked($use_server_api, true, false) ?>>
                                <span class="fmb-engine-slider"></span>
                            </label>
                        </div>
                    </div>
                    <div class="fmb-engine-field-row">
                        <div class="fmb-engine-field-label">Enable Advanced Matching</div>
                        <div class="fmb-engine-field-input">
                            <label class="fmb-engine-toggle-switch">
                                <input type="checkbox" name="ofls_tiktok_advanced_matching_enabled" value="1" <?= checked($advanced_matching, true, false) ?>>
                                <span class="fmb-engine-slider"></span>
                            </label>
                        </div>
                    </div>
                    <div class="fmb-engine-field-row">
                        <div class="fmb-engine-field-label">Immediately Send TikTok Purchase Event
                            <p class="ads-settings-description">If enabled, TikTok purchase events will be sent immediately when an order is placed (if the courier ratio is above the threshold). If disabled, purchase events will be saved and sent only when the order status changes to 'ads-purchase'.</p>
                        </div>
                        <div class="fmb-engine-field-input">
                            <label class="fmb-engine-toggle-switch">
                                <input type="checkbox" id="ofls_tiktok_immediate_purchase" name="ofls_tiktok_immediate_purchase" value="1" <?= checked($immediate_purchase, true, false) ?>>
                                <span class="fmb-engine-slider"></span>
                            </label>
                        </div>
                    </div>
                    <div class="fmb-engine-field-row" id="ofls_tiktok_courier_threshold_panel" style="<?= $immediate_purchase ? 'display: flex;' : 'display: none;' ?>">
                        <div class="fmb-engine-field-label">Courier Ratio Threshold (%)
                            <p class="ads-settings-description">If courier ratio is above this threshold, purchase event will be sent immediately. If below threshold, event will be saved and sent when order status changes to "ads-purchase".</p>
                        </div>
                        <div class="fmb-engine-field-input">
                            <input type="number" step="0.01" min="0" max="100" name="ofls_tiktok_courier_threshold" value="<?= esc_attr($courier_threshold) ?>" class="input-standard">
                        </div>
                    </div>
                </div>
                <button type="submit" name="ads_save_tiktok_general_settings" class="fmb-engine-submit-button">Save TikTok Settings</button>
            </form>
        </div>
        <script>
        jQuery(document).ready(function($) {
            $('#ofls_tiktok_immediate_purchase').on('change', function() {
                if ($(this).is(':checked')) {
                    $('#ofls_tiktok_courier_threshold_panel').css('display', 'flex');
                } else {
                    $('#ofls_tiktok_courier_threshold_panel').hide();
                }
            });
        });
        </script>
        <?php
    }

    public static function submit_tiktok_pixels_settings() {
        if( ! self::is_license_active() ) {
            return;
        }

        if ( isset( $_POST['ofls_tiktok_pixel_ids'] ) && is_array( $_POST['ofls_tiktok_pixel_ids'] ) ) {
            $pixel_ids = array_map( 'sanitize_text_field', $_POST['ofls_tiktok_pixel_ids'] );
            update_option( 'ofls_tiktok_pixel_ids', $pixel_ids );

            // Also keep the first pixel saved in the singular option 'ofls_tiktok_pixel_id' for backwards compatibility
            $first_pixel = ! empty( $pixel_ids[0] ) ? $pixel_ids[0] : '';
            update_option( 'ofls_tiktok_pixel_id', $first_pixel );
        }

        if ( isset( $_POST['ofls_tiktok_access_tokens'] ) && is_array( $_POST['ofls_tiktok_access_tokens'] ) ) {
            $access_tokens = array_map( 'sanitize_textarea_field', $_POST['ofls_tiktok_access_tokens'] );
            update_option( 'ofls_tiktok_access_tokens', $access_tokens );

            $first_token = ! empty( $access_tokens[0] ) ? $access_tokens[0] : '';
            update_option( 'ofls_tiktok_access_token', $first_token );
        }

        if ( isset( $_POST['ofls_tiktok_test_event_codes'] ) && is_array( $_POST['ofls_tiktok_test_event_codes'] ) ) {
            $test_event_codes = array_map( 'sanitize_text_field', $_POST['ofls_tiktok_test_event_codes'] );
            update_option( 'ofls_tiktok_test_event_codes', $test_event_codes );

            $first_test = ! empty( $test_event_codes[0] ) ? $test_event_codes[0] : '';
            update_option( 'ofls_tiktok_test_event_code', $first_test );
        }

        echo '<div class="notice notice-success"><p>TikTok Pixel IDs saved successfully!</p></div>';
    }

    public static function render_tiktok_pixels_settings() {
        if (isset($_POST['ads_save_tiktok_pixels_settings'])) {
            check_admin_referer('ads_tiktok_pixels_settings_form_nonce')
                ? self::submit_tiktok_pixels_settings()
                : self::render_error('Security check failed');
        }

        $pixel_ids = get_option('ofls_tiktok_pixel_ids', array());
        $access_tokens = get_option('ofls_tiktok_access_tokens', array());
        $test_event_codes = get_option('ofls_tiktok_test_event_codes', array());

        if ( ! is_array( $pixel_ids ) ) {
            $pixel_ids = array();
        }
        if ( ! is_array( $access_tokens ) ) {
            $access_tokens = array();
        }
        if ( ! is_array( $test_event_codes ) ) {
            $test_event_codes = array();
        }

        // Fallback for backward compatibility
        if ( empty( $pixel_ids ) ) {
            $single_pixel = get_option('ofls_tiktok_pixel_id', '');
            if ( ! empty( $single_pixel ) ) {
                $pixel_ids = array( $single_pixel );
                $single_token = get_option('ofls_tiktok_access_token', '');
                $access_tokens = array( $single_token );
                $single_test = get_option('ofls_tiktok_test_event_code', '');
                $test_event_codes = array( $single_test );
            }
        }

        if ( empty( $pixel_ids ) ) {
            $pixel_ids = array( '' ); // Ensure at least one empty group is shown
        }

        $use_server_api = get_option('ofls_tiktok_use_server_api', false);
        ?>
        <div class="fmb-engine-section">
            <div class="fmb-engine-option-heading">
                <h2 class="fmb-engine-section-title">TikTok Pixel IDs</h2>
                <p class="fmb-engine-subtitle">Add and manage your TikTok Pixel IDs and Conversion APIs.</p>
            </div>
            <form method="post" id="fmb-engine-tiktok-pixel-ids-form">
                <?php wp_nonce_field('ads_tiktok_pixels_settings_form_nonce'); ?>
                <div class="fmb-engine-form-container" id="tiktok-pixel-groups-container">
                    
                    <?php foreach ( $pixel_ids as $index => $pixel_id ) : 
                        $token = isset( $access_tokens[ $index ] ) ? $access_tokens[ $index ] : '';
                        $test_code = isset( $test_event_codes[ $index ] ) ? $test_event_codes[ $index ] : '';
                    ?>
                    <div class="fmb-engine-event-section tiktok-pixel-group" style="padding: 30px; position: relative; margin-bottom: 20px; border: 1px solid #e2e8f0; border-radius: 8px; background: #fff;">
                        <button type="button" class="remove-tiktok-pixel-group" style="position: absolute; top: 15px; right: 15px; color: #dc3232; border: 1px solid #dc3232; border-radius: 5px; background: #fff; cursor: pointer; font-weight: bold; width: 24px; height: 24px; display: flex; align-items: center; justify-content: center; padding: 0; z-index: 10;">X</button>
                        
                        <div class="fmb-engine-field-row" style="border: none; padding: 0 0 20px 0;">
                            <div class="fmb-engine-field-label" style="width: 200px;">TikTok Pixel ID</div>
                            <div class="fmb-engine-field-input" style="flex: 1;">
                                <input type="text" name="ofls_tiktok_pixel_ids[]" value="<?= esc_attr($pixel_id) ?>" placeholder="Enter TikTok Pixel ID" class="input-standard">
                            </div>
                        </div>

                        <div class="fmb-engine-field-row" style="border: none; padding: 0 0 20px 0;">
                            <div class="fmb-engine-field-label" style="width: 200px;">Conversion API Token</div>
                            <div class="fmb-engine-field-input" style="flex: 1;">
                                <textarea name="ofls_tiktok_access_tokens[]" placeholder="Api token" class="textarea-standard"><?= esc_attr($token) ?></textarea>
                            </div>
                        </div>

                        <div class="fmb-engine-field-row" style="border: none; padding: 0;">
                            <div class="fmb-engine-field-label" style="width: 200px;">Test Event Code</div>
                            <div class="fmb-engine-field-input" style="flex: 1;">
                                <input type="text" name="ofls_tiktok_test_event_codes[]" value="<?= esc_attr($test_code) ?>" placeholder="Code" class="input-standard">
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    
                </div>
                
                <div style="display: flex; justify-content: flex-end; margin-bottom: 20px;">
                    <button type="button" class="button fmb-engine-add-tiktok-pixel-button">Another Pixel</button>
                </div>
                <button type="submit" class="fmb-engine-submit-button" name="ads_save_tiktok_pixels_settings">
                    Save Pixel IDs
                </button>
            </form>
        </div>

        <script>
        jQuery(document).ready(function($) {
            // Function to clone TikTok pixel group
            $('.fmb-engine-add-tiktok-pixel-button').on('click', function(e) {
                e.preventDefault();
                
                const container = $('#tiktok-pixel-groups-container');
                const pixelGroups = container.find('.tiktok-pixel-group');
                const lastGroup = pixelGroups.last();
                
                // Clone the last group
                const newGroup = lastGroup.clone();
                
                // Clear values in clone
                newGroup.find('input, textarea').val('');
                
                // Append the new group
                container.append(newGroup);
                
                // Toggle remove buttons visibility
                updateTiktokRemoveButtons();
            });
            
            // Remove TikTok pixel group
            $(document).on('click', '.remove-tiktok-pixel-group', function(e) {
                e.preventDefault();
                const container = $('#tiktok-pixel-groups-container');
                if (container.find('.tiktok-pixel-group').length > 1) {
                    $(this).closest('.tiktok-pixel-group').remove();
                    updateTiktokRemoveButtons();
                } else {
                    // Just clear the fields if it's the last one
                    $(this).closest('.tiktok-pixel-group').find('input, textarea').val('');
                }
            });
            
            function updateTiktokRemoveButtons() {
                const groups = $('.tiktok-pixel-group');
                if (groups.length === 1) {
                    groups.find('.remove-tiktok-pixel-group').hide();
                } else {
                    groups.find('.remove-tiktok-pixel-group').show();
                }
            }
            
            // Initialize
            updateTiktokRemoveButtons();
        });
        </script>
        <?php
    }

    public static function submit_google_settings() {
        if( ! self::is_license_active() ) {
            return;
        }

        update_option('ofls_google_enabled', !empty($_POST['ofls_google_enabled']));
        update_option('ofls_google_immediate_purchase', !empty($_POST['ofls_google_immediate_purchase']));
        
        if (isset($_POST['ofls_google_conversion_id'])) {
            update_option('ofls_google_conversion_id', sanitize_text_field($_POST['ofls_google_conversion_id']));
        }
        
        if (isset($_POST['ofls_google_purchase_label'])) {
            update_option('ofls_google_purchase_label', sanitize_text_field($_POST['ofls_google_purchase_label']));
        }

        if (isset($_POST['ofls_google_courier_threshold'])) {
            update_option('ofls_google_courier_threshold', sanitize_text_field($_POST['ofls_google_courier_threshold']));
        }

        echo '<div class="notice notice-success"><p>Google Ads Settings saved successfully!</p></div>';
    }

    public static function render_google_settings() {
        if (isset($_POST['ads_save_google_settings'])) {
            check_admin_referer('ads_google_settings_form_nonce')
                ? self::submit_google_settings()
                : self::render_error('Security check failed');
        }

        $enabled = get_option('ofls_google_enabled', false);
        $conversion_id = get_option('ofls_google_conversion_id', '');
        $purchase_label = get_option('ofls_google_purchase_label', '');
        $immediate_purchase = get_option('ofls_google_immediate_purchase', false);
        $courier_threshold = get_option('ofls_google_courier_threshold', '0.3');
        ?>
        <div class="fmb-engine-section">
            <div class="fmb-engine-option-heading">
                <h2 class="fmb-engine-section-title">Google Ads Settings</h2>
                <p class="fmb-engine-subtitle">Configure your Google Ads Conversion Tracking.</p>
            </div>
            <form method="post">
                <?php wp_nonce_field('ads_google_settings_form_nonce'); ?>
                <div class="fmb-engine-form-container">
                    <div class="fmb-engine-field-row">
                        <div class="fmb-engine-field-label">Enable Google Tracking</div>
                        <div class="fmb-engine-field-input">
                            <label class="fmb-engine-toggle-switch">
                                <input type="checkbox" name="ofls_google_enabled" value="1" <?= checked($enabled, true, false) ?>>
                                <span class="fmb-engine-slider"></span>
                            </label>
                        </div>
                    </div>
                    <div class="fmb-engine-field-row">
                        <div class="fmb-engine-field-label">Google Conversion ID</div>
                        <div class="fmb-engine-field-input">
                            <input type="text" name="ofls_google_conversion_id" value="<?= esc_attr($conversion_id) ?>" placeholder="AW-123456789" class="input-standard">
                        </div>
                    </div>
                    <div class="fmb-engine-field-row">
                        <div class="fmb-engine-field-label">Conversion Label</div>
                        <div class="fmb-engine-field-input">
                            <input type="text" name="ofls_google_purchase_label" value="<?= esc_attr($purchase_label) ?>" placeholder="Enter alphanumeric label" class="input-standard">
                        </div>
                    </div>
                    <div class="fmb-engine-field-row">
                        <div class="fmb-engine-field-label">Immediately Send Google Purchase Event
                            <p class="ads-settings-description">If enabled, Google Ads purchase events will be sent immediately when an order is placed (if the courier ratio is above the threshold). If disabled, purchase events will be saved and sent only when the order status changes to 'ads-purchase'.</p>
                        </div>
                        <div class="fmb-engine-field-input">
                            <label class="fmb-engine-toggle-switch">
                                <input type="checkbox" id="ofls_google_immediate_purchase" name="ofls_google_immediate_purchase" value="1" <?= checked($immediate_purchase, true, false) ?>>
                                <span class="fmb-engine-slider"></span>
                            </label>
                        </div>
                    </div>
                    <div class="fmb-engine-field-row" id="ofls_google_courier_threshold_panel" style="<?= $immediate_purchase ? 'display: flex;' : 'display: none;' ?>">
                        <div class="fmb-engine-field-label">Courier Ratio Threshold (%)
                            <p class="ads-settings-description">If courier ratio is above this threshold, purchase event will be sent immediately. If below threshold, event will be saved and sent when order status changes to "ads-purchase".</p>
                        </div>
                        <div class="fmb-engine-field-input">
                            <input type="number" step="0.01" min="0" max="1" name="ofls_google_courier_threshold" value="<?= esc_attr($courier_threshold) ?>" class="input-standard">
                        </div>
                    </div>
                </div>
                <button type="submit" name="ads_save_google_settings" class="fmb-engine-submit-button">Save Google Settings</button>
            </form>
        </div>
        <script>
        jQuery(document).ready(function($) {
            $('#ofls_google_immediate_purchase').on('change', function() {
                if ($(this).is(':checked')) {
                    $('#ofls_google_courier_threshold_panel').css('display', 'flex');
                } else {
                    $('#ofls_google_courier_threshold_panel').hide();
                }
            });
        });
        </script>
        <?php
    }

    public static function submit_google_analytics_settings() {
        if( ! self::is_license_active() ) {
            return;
        }

        update_option('ofls_ga_enabled', !empty($_POST['ofls_ga_enabled']));
        
        if (isset($_POST['ofls_ga_measurement_id'])) {
            update_option('ofls_ga_measurement_id', sanitize_text_field($_POST['ofls_ga_measurement_id']));
        }
        
        if (isset($_POST['ofls_ga_api_secret'])) {
            update_option('ofls_ga_api_secret', sanitize_text_field($_POST['ofls_ga_api_secret']));
        }

        echo '<div class="notice notice-success"><p>Google Analytics Settings saved successfully!</p></div>';
    }

    public static function render_google_analytics_settings() {
        if (isset($_POST['ads_save_ga_settings'])) {
            check_admin_referer('ads_ga_settings_form_nonce')
                ? self::submit_google_analytics_settings()
                : self::render_error('Security check failed');
        }

        $enabled = get_option('ofls_ga_enabled', false);
        $measurement_id = get_option('ofls_ga_measurement_id', '');
        $api_secret = get_option('ofls_ga_api_secret', '');
        ?>
        <div class="fmb-engine-section">
            <div class="fmb-engine-option-heading">
                <h2 class="fmb-engine-section-title">Google Analytics Settings</h2>
                <p class="fmb-engine-subtitle">Configure your Google Analytics (GA4) Tracking.</p>
            </div>
            <form method="post">
                <?php wp_nonce_field('ads_ga_settings_form_nonce'); ?>
                <div class="fmb-engine-form-container">
                    <div class="fmb-engine-field-row">
                        <div class="fmb-engine-field-label">Enable Analytics Tracking</div>
                        <div class="fmb-engine-field-input">
                            <label class="fmb-engine-toggle-switch">
                                <input type="checkbox" name="ofls_ga_enabled" value="1" <?= checked($enabled, true, false) ?>>
                                <span class="fmb-engine-slider"></span>
                            </label>
                        </div>
                    </div>
                    <div class="fmb-engine-field-row">
                        <div class="fmb-engine-field-label">Measurement ID</div>
                        <div class="fmb-engine-field-input">
                            <input type="text" name="ofls_ga_measurement_id" value="<?= esc_attr($measurement_id) ?>" placeholder="G-XXXXXXXXXX" class="input-standard">
                        </div>
                    </div>
                    <div class="fmb-engine-field-row">
                        <div class="fmb-engine-field-label">Measurement Protocol API Secret
                            <p class="ads-settings-description">Required for sending server-side/offline conversion events. Generate this in GA4 Admin > Data Streams > Choose your stream > Measurement Protocol API secrets.</p>
                        </div>
                        <div class="fmb-engine-field-input">
                            <input type="text" name="ofls_ga_api_secret" value="<?= esc_attr($api_secret) ?>" placeholder="Enter API Secret" class="input-standard">
                        </div>
                    </div>
                </div>
                <button type="submit" name="ads_save_ga_settings" class="fmb-engine-submit-button">Save Analytics Settings</button>
            </form>
        </div>
        <?php
    }
}

