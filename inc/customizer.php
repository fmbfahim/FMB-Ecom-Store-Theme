<?php
// ==========================================
// Logo & Brand URL Helper Function
// ==========================================
if ( ! function_exists( 'fmb_get_site_logo_url' ) ) {
    function fmb_get_site_logo_url() {
        $logo = get_theme_mod( 'fmb_logo', '' );
        if ( ! empty( $logo ) ) {
            return $logo;
        }
        if ( function_exists( 'get_theme_mod' ) ) {
            $custom_logo_id = get_theme_mod( 'custom_logo' );
            if ( $custom_logo_id ) {
                $core_logo = wp_get_attachment_image_url( $custom_logo_id, 'full' );
                if ( $core_logo ) {
                    return $core_logo;
                }
            }
        }
        return '';
    }
}

function fmb_customize_register($wp_customize) {
    
    // ========================================================
    // ১. Site Identity (Logo & Branding Integrated)
    // ========================================================
    $title_tagline = $wp_customize->get_section('title_tagline');
    if ($title_tagline) {
        $title_tagline->title       = __('Site Identity & Branding', 'fmb-store');
        $title_tagline->description = __('আপনার সাইটের লোগো, সাইট টাইটেল (নাম), ট্যাগলাইন ও সাইট আইকন সেট করুন। লোগো যোগ না করলে স্বয়ংক্রিয়ভাবে সাইটের নাম হেডারে প্রদর্শিত হবে।', 'fmb-store');
        $title_tagline->priority    = 20;
    }

    // Logo Upload (Site Identity)
    $wp_customize->add_setting('fmb_logo', array(
        'default'           => '',
        'sanitize_callback' => 'esc_url_raw',
        'transport'         => 'refresh',
    ));
    $wp_customize->add_control(new WP_Customize_Image_Control($wp_customize, 'fmb_logo', array(
        'label'       => __('Site Logo', 'fmb-store'),
        'description' => __('লোগো আপলোড করুন। কোনো লোগো সিলেক্ট না করলে সাইটের নাম (Site Title) হেডারে দেখাবে।', 'fmb-store'),
        'section'     => 'title_tagline',
        'priority'    => 5,
    )));

    // Logo Width (Site Identity)
    $wp_customize->add_setting('fmb_logo_width', array('default' => 150, 'transport' => 'refresh'));
    $wp_customize->add_control('fmb_logo_width', array(
        'label'       => __('Logo Width (px)', 'fmb-store'),
        'description' => __('হেডারে লোগোর সর্বোচ্চ প্রস্থ (Width)', 'fmb-store'),
        'section'     => 'title_tagline',
        'type'        => 'range',
        'priority'    => 6,
        'input_attrs' => array('min' => 50, 'max' => 350, 'step' => 5),
    ));

    // Remove duplicate core custom_logo control if present to keep Site Identity clean & unified
    if ($wp_customize->get_control('custom_logo')) {
        $wp_customize->remove_control('custom_logo');
    }

    // Top Bar Announcement Toggle
    $wp_customize->add_setting('fmb_show_topbar', array('default' => true, 'transport' => 'refresh'));
    $wp_customize->add_control('fmb_show_topbar', array(
        'label'    => __('Show Top Bar Announcement', 'fmb-store'),
        'section'  => 'title_tagline',
        'type'     => 'checkbox',
        'priority' => 70,
    ));

    // Top Bar Text
    $wp_customize->add_setting('fmb_topbar_text', array('default' => 'Call for Order: +8801700000000', 'transport' => 'refresh'));
    $wp_customize->add_control('fmb_topbar_text', array(
        'label'    => __('Top Bar Announcement Text', 'fmb-store'),
        'section'  => 'title_tagline',
        'type'     => 'text',
        'priority' => 71,
    ));

    // Selective Refresh for Site Title
    if ( isset( $wp_customize->selective_refresh ) ) {
        $wp_customize->selective_refresh->add_partial( 'blogname', array(
            'selector'        => '.fmb-site-brand-text',
            'render_callback' => function() {
                return esc_html( get_bloginfo( 'name' ) );
            },
        ) );
    }

    // ==========================================
    // ২. প্যানেল: ডিজাইন সিস্টেম (Design System)
    // ==========================================
    $wp_customize->add_panel('fmb_design_system', array(
        'title'       => __('Design System & Global', 'fmb-store'),
        'description' => 'পুরো সাইটের কালার, ফন্ট এবং লেআউট কন্ট্রোল করুন',
        'priority'    => 30,
    ));


    // --- সেকশন: কালার প্যালেট (Colors) ---
    $wp_customize->add_section('fmb_colors', array(
        'title' => __('Color Palette', 'fmb-store'),
        'panel' => 'fmb_design_system',
    ));

    // Primary Color
    $wp_customize->add_setting('fmb_primary_color', array('default' => '#2563EB', 'transport' => 'refresh'));
    $wp_customize->add_control(new WP_Customize_Color_Control($wp_customize, 'fmb_primary_color', array(
        'label'   => __('Primary Brand Color', 'fmb-store'),
        'section' => 'fmb_colors',
    )));

    // Secondary/Accent Color
    $wp_customize->add_setting('fmb_secondary_color', array('default' => '#111827', 'transport' => 'refresh'));
    $wp_customize->add_control(new WP_Customize_Color_Control($wp_customize, 'fmb_secondary_color', array(
        'label'   => __('Secondary / Button Hover', 'fmb-store'),
        'section' => 'fmb_colors',
    )));

    // Background Color
    $wp_customize->add_setting('fmb_bg_color', array('default' => '#F9FAFB', 'transport' => 'refresh'));
    $wp_customize->add_control(new WP_Customize_Color_Control($wp_customize, 'fmb_bg_color', array(
        'label'   => __('Body Background Color', 'fmb-store'),
        'section' => 'fmb_colors',
    )));

    // Custom Button Background Color
    $wp_customize->add_setting('fmb_btn_bg_color', array('default' => '', 'transport' => 'refresh'));
    $wp_customize->add_control(new WP_Customize_Color_Control($wp_customize, 'fmb_btn_bg_color', array(
        'label'       => __('Custom Button Color (বাটন ব্যাকগ্রাউন্ড)', 'fmb-store'),
        'description' => __('খালি রাখলে ব্র্যান্ড কালার স্বয়ংক্রিয়ভাবে পাবে।', 'fmb-store'),
        'section'     => 'fmb_colors',
    )));

    // Custom Button Text Color
    $wp_customize->add_setting('fmb_btn_text_color', array('default' => '#FFFFFF', 'transport' => 'refresh'));
    $wp_customize->add_control(new WP_Customize_Color_Control($wp_customize, 'fmb_btn_text_color', array(
        'label'   => __('Button Text Color (বাটন টেক্সট কালার)', 'fmb-store'),
        'section' => 'fmb_colors',
    )));

    // Custom Button Hover Color
    $wp_customize->add_setting('fmb_btn_hover_color', array('default' => '', 'transport' => 'refresh'));
    $wp_customize->add_control(new WP_Customize_Color_Control($wp_customize, 'fmb_btn_hover_color', array(
        'label'   => __('Button Hover Color (বাটন হোভার কালার)', 'fmb-store'),
        'section' => 'fmb_colors',
    )));


    // --- সেকশন: টাইপোগ্রাফি (Typography) ---
    $wp_customize->add_section('fmb_typography', array(
        'title' => __('Typography (Fonts)', 'fmb-store'),
        'panel' => 'fmb_design_system',
    ));

    // Font Family Selection
    $wp_customize->add_setting('fmb_font_family', array('default' => 'Inter', 'transport' => 'refresh'));
    $wp_customize->add_control('fmb_font_family', array(
        'label'   => __('Select Font Family', 'fmb-store'),
        'section' => 'fmb_typography',
        'type'    => 'select',
        'choices' => array(
            'Inter'   => 'Inter (Modern & Clean)',
            'Roboto'  => 'Roboto (Standard)',
            'Poppins' => 'Poppins (Stylish)',
            'Lato'    => 'Lato (Friendly)',
            'Hind Siliguri' => 'Hind Siliguri (Bangla Best)',
        ),
    ));


    // --- সেকশন: শেপ ও লেআউট (Shape) ---
    $wp_customize->add_section('fmb_shape', array(
        'title' => __('Shape & Radius', 'fmb-store'),
        'panel' => 'fmb_design_system',
    ));

    // Border Radius
    $wp_customize->add_setting('fmb_border_radius', array('default' => 8, 'transport' => 'refresh'));
    $wp_customize->add_control('fmb_border_radius', array(
        'label'       => __('Border Radius (px)', 'fmb-store'),
        'description' => '0 = Sharp, 8 = Standard, 20 = Rounded',
        'section'     => 'fmb_shape',
        'type'        => 'range',
        'input_attrs' => array('min' => 0, 'max' => 30, 'step' => 1),
    ));

    // --- সেকশন: ফুটার (Footer) ---
    $wp_customize->add_section('fmb_footer', array(
        'title' => __('Footer Settings', 'fmb-store'),
        'panel' => 'fmb_design_system',
    ));

    // Copyright Text
    $wp_customize->add_setting('fmb_copyright_text', array(
        'default' => '© 2025 FMB Store. All rights reserved.',
        'transport' => 'refresh',
    ));
    $wp_customize->add_control('fmb_copyright_text', array(
        'label'   => __('Copyright Text', 'fmb-store'),
        'section' => 'fmb_footer',
        'type'    => 'text',
    ));

    // --- সেকশন: হোম পেজ হিরো (Hero Section) ---
    $wp_customize->add_section('fmb_hero_section', array(
        'title'    => __('Home Page Banner', 'fmb-store'),
        'panel'    => 'fmb_design_system',
    ));

    // Hero Title
    $wp_customize->add_setting('fmb_hero_title', array('default' => 'সেরা মানের প্রডাক্ট, সেরা দামে!'));
    $wp_customize->add_control('fmb_hero_title', array(
        'label'   => 'Hero Headline',
        'section' => 'fmb_hero_section',
        'type'    => 'text',
    ));

    // Hero Subtitle
    $wp_customize->add_setting('fmb_hero_subtitle', array('default' => 'আমাদের প্রিমিয়াম কালেকশন থেকে আপনার পছন্দের পণ্যটি বেছে নিন।'));
    $wp_customize->add_control('fmb_hero_subtitle', array(
        'label'   => 'Hero Subtitle',
        'section' => 'fmb_hero_section',
        'type'    => 'textarea',
    ));

    // Hero Image
    $wp_customize->add_setting('fmb_hero_image');
    $wp_customize->add_control(new WP_Customize_Image_Control($wp_customize, 'fmb_hero_image', array(
        'label'   => 'Banner Image (Right Side)',
        'section' => 'fmb_hero_section',
    )));

    // Button Text
    $wp_customize->add_setting('fmb_hero_btn_text', array('default' => 'শপিং শুরু করুন'));
    $wp_customize->add_control('fmb_hero_btn_text', array(
        'label'   => 'Button Text',
        'section' => 'fmb_hero_section',
        'type'    => 'text',
    ));



    // --- সেকশন: গ্লোবাল কন্টাক্ট ইনফো (Global Contact Info) ---
    // এখানে চেঞ্জ করলে পুরো সাইটে চেঞ্জ হবে
    $wp_customize->add_section('fmb_contact_global', array(
        'title'    => __('Global Contact Info', 'fmb-store'),
        'panel'    => 'fmb_design_system', // ডিজাইন প্যানেলের ভেতরেই থাকবে
        'priority' => 10, // সবার উপরে দেখাবে
    ));

    // ১. ফোন নাম্বার
    $wp_customize->add_setting('fmb_global_phone', array('default' => '+8801700000000'));
    $wp_customize->add_control('fmb_global_phone', array(
        'label'       => 'Mobile Number',
        'description' => 'এই নাম্বারটি হেডার, ফুটার এবং সব বাটনে আপডেট হবে।',
        'section'     => 'fmb_contact_global',
        'type'        => 'text',
    ));

    // ২. মেসেঞ্জার বা হোয়াটসঅ্যাপ লিংক
    $wp_customize->add_setting('fmb_messenger_url', array('default' => 'https://m.me/yourpage'));
    $wp_customize->add_control('fmb_messenger_url', array(
        'label'       => 'Messenger / WhatsApp Link',
        'description' => 'মেসেজ বাটনের লিংক (যেমন: https://wa.me/88017... অথবা https://m.me/...)',
        'section'     => 'fmb_contact_global',
        'type'        => 'url',
    ));

    // ৩. ইমেইল
    $wp_customize->add_setting('fmb_global_email', array('default' => 'info@example.com'));
    $wp_customize->add_control('fmb_global_email', array(
        'label'   => 'Email Address',
        'section' => 'fmb_contact_global',
        'type'    => 'text',
    ));

    // ৪. ঠিকানা
    $wp_customize->add_setting('fmb_global_address', array('default' => 'ঢাকা, বাংলাদেশ'));
    $wp_customize->add_control('fmb_global_address', array(
        'label'   => 'Office Address',
        'section' => 'fmb_contact_global',
        'type'    => 'textarea',
    ));









    // --- সেকশন: থিম ফিচারস (Feature Toggles) ---
    $wp_customize->add_section('fmb_theme_features', array(
        'title'       => __('Theme Features (On/Off)', 'fmb-store'),
        'description' => 'থিমের বিভিন্ন ফিচার চালু বা বন্ধ করুন',
        'panel'       => 'fmb_design_system',
        'priority'    => 20,
    ));

    $wp_customize->add_setting('fmb_show_fake_sales', array('default' => true));
    $wp_customize->add_control('fmb_show_fake_sales', array(
        'label'       => __('সোশ্যাল প্রুফ ব্যানার (ফেক গণনা)', 'fmb-store'),
        'description' => __('ডাটাবেজ নয় — দেখানো সংখ্যা ন্যূনতম ৭৯ এবং সময়ের সাথে ধীরে ধীরে কমতে থাকে। শুধু ডিসপ্লে।', 'fmb-store'),
        'section'     => 'fmb_theme_features',
        'type'        => 'checkbox',
    ));

    // Add to Cart Button Toggle
    $wp_customize->add_setting('fmb_show_add_to_cart_button', array('default' => true));
    $wp_customize->add_control('fmb_show_add_to_cart_button', array(
        'label'   => __('Show "Add to Cart" Button', 'fmb-store'),
        'section' => 'fmb_theme_features',
        'type'    => 'checkbox',
    ));

    // Call Button Toggle
    $wp_customize->add_setting('fmb_show_call_button', array('default' => true));
    $wp_customize->add_control('fmb_show_call_button', array(
        'label'   => __('Show "Call to Order" Button', 'fmb-store'),
        'section' => 'fmb_theme_features',
        'type'    => 'checkbox',
    ));

    // WhatsApp Button Toggle
    $wp_customize->add_setting('fmb_show_whatsapp_button', array('default' => true));
    $wp_customize->add_control('fmb_show_whatsapp_button', array(
        'label'   => __('Show "WhatsApp Order" Button', 'fmb-store'),
        'section' => 'fmb_theme_features',
        'type'    => 'checkbox',
    ));


    // ==========================================
    // --- সেকশন: ফ্রি ডেলিভারি ব্যানার ---
    // ==========================================
    $wp_customize->add_section('fmb_free_delivery_banner', array(
        'title'       => __('Free Delivery Banner', 'fmb-store'),
        'description' => 'প্রোডাক্ট পেজে ফ্রি ডেলিভারি ব্যানার কাস্টমাইজ করুন। ডেলিভারি চার্জ ০ হলে স্বয়ংক্রিয়ভাবে দেখাবে।',
        'panel'       => 'fmb_design_system',
        'priority'    => 25,
    ));

    // ১. কাস্টম ইমেজ আপলোড
    // সুপারিশকৃত ছবির আকার: প্রস্থ ৮০০px, উচ্চতা ১৬০px (Aspect Ratio 5:1), Format: PNG/WebP
    $wp_customize->add_setting('fmb_free_delivery_image', array('default' => ''));
    $wp_customize->add_control(new WP_Customize_Image_Control($wp_customize, 'fmb_free_delivery_image', array(
        'label'       => __('Free Delivery Banner Image', 'fmb-store'),
        'description' => '⚡ সুপারিশকৃত সাইজ: 800×160 px (PNG বা WebP)। ইমেজ না দিলে ডিফল্ট টেক্সট ব্যানার দেখাবে।',
        'section'     => 'fmb_free_delivery_banner',
    )));

    // ২. ডিফল্ট টেক্সট (ইমেজ না থাকলে)
    $wp_customize->add_setting('fmb_free_delivery_text', array('default' => '🚚 সারাদেশে ডেলিভারি সম্পূর্ণ বিনামূল্যে!'));
    $wp_customize->add_control('fmb_free_delivery_text', array(
        'label'       => __('Free Delivery Text (if no image)', 'fmb-store'),
        'description' => 'ইমেজ আপলোড না করলে এই টেক্সটটি ব্যানারে দেখাবে।',
        'section'     => 'fmb_free_delivery_banner',
        'type'        => 'text',
    ));

    // ৩. ব্যাকগ্রাউন্ড কালার (টেক্সট ব্যানারের জন্য)
    $wp_customize->add_setting('fmb_free_delivery_bg', array('default' => '#DCFCE7', 'transport' => 'refresh'));
    $wp_customize->add_control(new WP_Customize_Color_Control($wp_customize, 'fmb_free_delivery_bg', array(
        'label'   => __('Banner Background Color (Text mode)', 'fmb-store'),
        'section' => 'fmb_free_delivery_banner',
    )));

    // ৪. টেক্সট কালার
    $wp_customize->add_setting('fmb_free_delivery_color', array('default' => '#166534', 'transport' => 'refresh'));
    $wp_customize->add_control(new WP_Customize_Color_Control($wp_customize, 'fmb_free_delivery_color', array(
        'label'   => __('Banner Text Color', 'fmb-store'),
        'section' => 'fmb_free_delivery_banner',
    )));

    // ==========================================
    // --- সেকশন: হোমপেজ ব্যানার স্লাইডার ও সাইড এড ---
    // ==========================================
    $wp_customize->add_section('fmb_homepage_hero', array(
        'title'       => __('Homepage Banner & Promo Ad', 'fmb-store'),
        'description' => 'হোমপেজ স্লাইডার ইমেজ এবং ডানপাশের প্রমোশনাল এড ব্যানার কাস্টমাইজ করুন।',
        'panel'       => 'fmb_design_system',
        'priority'    => 20,
    ));

    // Slider Image 1
    $wp_customize->add_setting('fmb_hero_slide_1');
    $wp_customize->add_control(new WP_Customize_Image_Control($wp_customize, 'fmb_hero_slide_1', array(
        'label'    => __('Slider Image 1', 'fmb-store'),
        'section'  => 'fmb_homepage_hero',
    )));
    $wp_customize->add_setting('fmb_hero_slide_1_link', array('default' => ''));
    $wp_customize->add_control('fmb_hero_slide_1_link', array(
        'label'    => __('Slider 1 Link URL', 'fmb-store'),
        'section'  => 'fmb_homepage_hero',
        'type'     => 'url',
    ));

    // Slider Image 2
    $wp_customize->add_setting('fmb_hero_slide_2');
    $wp_customize->add_control(new WP_Customize_Image_Control($wp_customize, 'fmb_hero_slide_2', array(
        'label'    => __('Slider Image 2', 'fmb-store'),
        'section'  => 'fmb_homepage_hero',
    )));
    $wp_customize->add_setting('fmb_hero_slide_2_link', array('default' => ''));
    $wp_customize->add_control('fmb_hero_slide_2_link', array(
        'label'    => __('Slider 2 Link URL', 'fmb-store'),
        'section'  => 'fmb_homepage_hero',
        'type'     => 'url',
    ));

    // Slider Image 3
    $wp_customize->add_setting('fmb_hero_slide_3');
    $wp_customize->add_control(new WP_Customize_Image_Control($wp_customize, 'fmb_hero_slide_3', array(
        'label'    => __('Slider Image 3', 'fmb-store'),
        'section'  => 'fmb_homepage_hero',
    )));
    $wp_customize->add_setting('fmb_hero_slide_3_link', array('default' => ''));
    $wp_customize->add_control('fmb_hero_slide_3_link', array(
        'label'    => __('Slider 3 Link URL', 'fmb-store'),
        'section'  => 'fmb_homepage_hero',
        'type'     => 'url',
    ));

    // Side Promotional Ad Image
    $wp_customize->add_setting('fmb_side_ad_image');
    $wp_customize->add_control(new WP_Customize_Image_Control($wp_customize, 'fmb_side_ad_image', array(
        'label'       => __('Side Promotional Ad Image', 'fmb-store'),
        'description' => 'স্লাইডারের ডানপাশে দেখানোর জন্য প্রমোশনাল এড ইমেজ আপলোড করুন।',
        'section'     => 'fmb_homepage_hero',
    )));
    $wp_customize->add_setting('fmb_side_ad_link', array('default' => '#'));
    $wp_customize->add_control('fmb_side_ad_link', array(
        'label'    => __('Side Ad Link URL', 'fmb-store'),
        'section'  => 'fmb_homepage_hero',
        'type'     => 'url',
    ));

    // ==========================================
    // --- সেকশন: গ্রাহকদের রিভিউ ও ছবি ---
    // ==========================================
    $wp_customize->add_section('fmb_homepage_reviews', array(
        'title'       => __('Customer Reviews & Photos', 'fmb-store'),
        'description' => 'হোমপেজে দেখানো গ্রাহকদের রিভিউ, ছবি ও তথ্য কাস্টমাইজ করুন।',
        'panel'       => 'fmb_design_system',
        'priority'    => 22,
    ));

    // Review 1
    $wp_customize->add_setting('fmb_rev1_name', array('default' => 'আরিফুল ইসলাম'));
    $wp_customize->add_control('fmb_rev1_name', array('label' => __('Review 1 - Name', 'fmb-store'), 'section' => 'fmb_homepage_reviews', 'type' => 'text'));

    $wp_customize->add_setting('fmb_rev1_location', array('default' => 'ঢাকা'));
    $wp_customize->add_control('fmb_rev1_location', array('label' => __('Review 1 - Location', 'fmb-store'), 'section' => 'fmb_homepage_reviews', 'type' => 'text'));

    $wp_customize->add_setting('fmb_rev1_text', array('default' => 'অর্ডার করার পরদিনই ডেলিভারি পেয়েছি। প্রডাক্টের কোয়ালিটি ছবির মতোই একশতে একশ! ধন্যবাদ FMB Store কে।'));
    $wp_customize->add_control('fmb_rev1_text', array('label' => __('Review 1 - Text', 'fmb-store'), 'section' => 'fmb_homepage_reviews', 'type' => 'textarea'));

    $wp_customize->add_setting('fmb_rev1_avatar');
    $wp_customize->add_control(new WP_Customize_Image_Control($wp_customize, 'fmb_rev1_avatar', array(
        'label'    => __('Review 1 - Customer Profile Image (Avatar)', 'fmb-store'),
        'section'  => 'fmb_homepage_reviews',
    )));

    $wp_customize->add_setting('fmb_rev1_photo');
    $wp_customize->add_control(new WP_Customize_Image_Control($wp_customize, 'fmb_rev1_photo', array(
        'label'       => __('Review 1 - Received Product Photo (Review Attachment)', 'fmb-store'),
        'description' => 'কাস্টমারের রিসিভ করা প্রডাক্টের ছবি (Review Product Photo)',
        'section'     => 'fmb_homepage_reviews',
    )));

    // Review 2
    $wp_customize->add_setting('fmb_rev2_name', array('default' => 'সাদিয়া আক্তার'));
    $wp_customize->add_control('fmb_rev2_name', array('label' => __('Review 2 - Name', 'fmb-store'), 'section' => 'fmb_homepage_reviews', 'type' => 'text'));

    $wp_customize->add_setting('fmb_rev2_location', array('default' => 'চট্টগ্রাম'));
    $wp_customize->add_control('fmb_rev2_location', array('label' => __('Review 2 - Location', 'fmb-store'), 'section' => 'fmb_homepage_reviews', 'type' => 'text'));

    $wp_customize->add_setting('fmb_rev2_text', array('default' => 'ক্যাশ অন ডেলিভারিতে প্যাকেট খুলে পণ্য দেখে নেওয়ার সুবিধাটা সবচেয়ে ভালো লেগেছে। ফ্যানটি বেশ ভালো সার্ভিস দিচ্ছে।'));
    $wp_customize->add_control('fmb_rev2_text', array('label' => __('Review 2 - Text', 'fmb-store'), 'section' => 'fmb_homepage_reviews', 'type' => 'textarea'));

    $wp_customize->add_setting('fmb_rev2_avatar');
    $wp_customize->add_control(new WP_Customize_Image_Control($wp_customize, 'fmb_rev2_avatar', array(
        'label'    => __('Review 2 - Customer Profile Image (Avatar)', 'fmb-store'),
        'section'  => 'fmb_homepage_reviews',
    )));

    $wp_customize->add_setting('fmb_rev2_photo');
    $wp_customize->add_control(new WP_Customize_Image_Control($wp_customize, 'fmb_rev2_photo', array(
        'label'       => __('Review 2 - Received Product Photo (Review Attachment)', 'fmb-store'),
        'description' => 'কাস্টমারের রিসিভ করা প্রডাক্টের ছবি',
        'section'     => 'fmb_homepage_reviews',
    )));

    // Review 3
    $wp_customize->add_setting('fmb_rev3_name', array('default' => 'মাহমুদুল হাসান'));
    $wp_customize->add_control('fmb_rev3_name', array('label' => __('Review 3 - Name', 'fmb-store'), 'section' => 'fmb_homepage_reviews', 'type' => 'text'));

    $wp_customize->add_setting('fmb_rev3_location', array('default' => 'সিলেট'));
    $wp_customize->add_control('fmb_rev3_location', array('label' => __('Review 3 - Location', 'fmb-store'), 'section' => 'fmb_homepage_reviews', 'type' => 'text'));

    $wp_customize->add_setting('fmb_rev3_text', array('default' => 'দাম অনুযায়ী প্রোডাক্ট মান অনেক ভালো। ডেলিভারি বয় খুব দ্রুত দিয়ে গেছে। ইনশাআল্লাহ সামনে আবার কেনাকাটা করবো।'));
    $wp_customize->add_control('fmb_rev3_text', array('label' => __('Review 3 - Text', 'fmb-store'), 'section' => 'fmb_homepage_reviews', 'type' => 'textarea'));

    $wp_customize->add_setting('fmb_rev3_avatar');
    $wp_customize->add_control(new WP_Customize_Image_Control($wp_customize, 'fmb_rev3_avatar', array(
        'label'    => __('Review 3 - Customer Profile Image (Avatar)', 'fmb-store'),
        'section'  => 'fmb_homepage_reviews',
    )));

    $wp_customize->add_setting('fmb_rev3_photo');
    $wp_customize->add_control(new WP_Customize_Image_Control($wp_customize, 'fmb_rev3_photo', array(
        'label'       => __('Review 3 - Received Product Photo (Review Attachment)', 'fmb-store'),
        'description' => 'কাস্টমারের রিসিভ করা প্রডাক্টের ছবি',
        'section'     => 'fmb_homepage_reviews',
    )));

}
add_action('customize_register', 'fmb_customize_register');

function fmb_clean_site_identity_controls($wp_customize) {
    if ($wp_customize->get_control('custom_logo')) {
        $wp_customize->remove_control('custom_logo');
    }
}
add_action('customize_register', 'fmb_clean_site_identity_controls', 999);


// ২. CSS ভেরিয়েবল ও গ্লোবাল স্টাইল আউটপুট
function fmb_customizer_css_output() {
    $primary    = get_theme_mod('fmb_primary_color', '#2563EB');
    $secondary  = get_theme_mod('fmb_secondary_color', '#111827');
    $bg_color   = get_theme_mod('fmb_bg_color', '#F9FAFB');
    $radius     = get_theme_mod('fmb_border_radius', 8);
    $logo_w     = get_theme_mod('fmb_logo_width', 150);
    $font       = get_theme_mod('fmb_font_family', 'Inter');

    // বাটন কাস্টম কালার (না থাকলে স্বয়ংক্রিয়ভাবে থিম প্রাইমারি কালার পাবে)
    $btn_bg     = get_theme_mod('fmb_btn_bg_color', '');
    if (empty($btn_bg)) $btn_bg = $primary;

    $btn_text   = get_theme_mod('fmb_btn_text_color', '#FFFFFF');
    if (empty($btn_text)) $btn_text = '#FFFFFF';

    $btn_hover  = get_theme_mod('fmb_btn_hover_color', '');
    if (empty($btn_hover)) $btn_hover = $secondary;
    ?>
    <style type="text/css">
        :root {
            --primary: <?php echo esc_attr($primary); ?>;
            --secondary: <?php echo esc_attr($secondary); ?>;
            --bg-body: <?php echo esc_attr($bg_color); ?>;
            --radius: <?php echo intval($radius); ?>px;
            --logo-w: <?php echo intval($logo_w); ?>px;
            --font-main: '<?php echo esc_attr($font); ?>', sans-serif;
            --btn-bg: <?php echo esc_attr($btn_bg); ?>;
            --btn-text: <?php echo esc_attr($btn_text); ?>;
            --btn-hover: <?php echo esc_attr($btn_hover); ?>;
        }
        
        body {
            background-color: var(--bg-body);
            font-family: var(--font-main);
        }

        /* 🔘 থিমের সকল অ্যাকশন ও অর্ডার বাটন ডায়নামিক কালার */
        .qs-order-popup-trigger,
        .fmb-card-order-btn,
        .fmb-order-now-btn,
        .fmb-sticky-buy-btn,
        .fmb-submit-btn,
        #fmb-sticky-rich-cart a[href*="checkout"],
        #fmb-sticky-cta a,
        .wc-proceed-to-checkout a.checkout-button,
        #payment .place-order .button,
        #place_order,
        #btn-confirm-order,
        .fmb-direct-checkout-btn,
        .fmb-btn-primary,
        .woocommerce a.button.alt,
        .woocommerce button.button.alt,
        .woocommerce input.button.alt,
        .woocommerce #respond input#submit.alt {
            background: var(--btn-bg) !important;
            background-color: var(--btn-bg) !important;
            color: var(--btn-text) !important;
            border-color: var(--btn-bg) !important;
        }

        .qs-order-popup-trigger:hover,
        .fmb-card-order-btn:hover,
        .fmb-order-now-btn:hover,
        .fmb-sticky-buy-btn:hover,
        .fmb-submit-btn:hover,
        #fmb-sticky-rich-cart a[href*="checkout"]:hover,
        #fmb-sticky-cta a:hover,
        .wc-proceed-to-checkout a.checkout-button:hover,
        #payment .place-order .button:hover,
        #place_order:hover,
        #btn-confirm-order:hover,
        .fmb-direct-checkout-btn:hover,
        .fmb-btn-primary:hover,
        .woocommerce a.button.alt:hover,
        .woocommerce button.button.alt:hover,
        .woocommerce input.button.alt:hover,
        .woocommerce #respond input#submit.alt:hover {
            background: var(--btn-hover) !important;
            background-color: var(--btn-hover) !important;
            color: var(--btn-text) !important;
            border-color: var(--btn-hover) !important;
        }

        /* 🚚 চেকআউট ফর্ম স্ক্রিনে দৃশ্যমান হলে নিচের স্টিকি বার হাইড হবে */
        #fmb-sticky-rich-cart.fmb-hide-sticky,
        #fmb-sticky-cta.fmb-hide-sticky {
            transform: translateY(150%) !important;
            opacity: 0 !important;
            pointer-events: none !important;
            transition: transform 0.4s cubic-bezier(0.16, 1, 0.3, 1), opacity 0.3s ease !important;
        }

        /* 🔔 woocommerce-notices-wrapper সুন্দর ও আধুনিক ডিজাইন */
        .woocommerce-notices-wrapper {
            max-width: 80rem;
            margin: 1.25rem auto;
            padding: 0 1rem;
            width: 100%;
            box-sizing: border-box;
            clear: both;
        }
        @media (min-width: 640px) {
            .woocommerce-notices-wrapper {
                padding: 0 1.5rem;
            }
        }
        @media (min-width: 1024px) {
            .woocommerce-notices-wrapper {
                padding: 0 2rem;
            }
        }

        .woocommerce-message,
        .woocommerce-error,
        .woocommerce-info {
            display: flex !important;
            align-items: center !important;
            justify-content: space-between !important;
            flex-wrap: wrap !important;
            gap: 12px !important;
            padding: 14px 20px !important;
            border-radius: 16px !important;
            font-size: 14px !important;
            font-weight: 600 !important;
            line-height: 1.5 !important;
            margin-bottom: 1rem !important;
            box-shadow: 0 4px 15px -2px rgba(0, 0, 0, 0.06), 0 2px 6px -1px rgba(0, 0, 0, 0.03) !important;
            list-style: none !important;
        }

        /* Success Notice */
        .woocommerce-message {
            background-color: #F0FDF4 !important;
            color: #166534 !important;
            border: 1px solid #86EFAC !important;
            border-left: 5px solid #10B981 !important;
        }

        /* Error Notice */
        .woocommerce-error {
            background-color: #FEF2F2 !important;
            color: #991B1B !important;
            border: 1px solid #FECACA !important;
            border-left: 5px solid #EF4444 !important;
        }

        /* Info Notice */
        .woocommerce-info {
            background-color: #EFF6FF !important;
            color: #1E40AF !important;
            border: 1px solid #BFDBFE !important;
            border-left: 5px solid #3B82F6 !important;
        }

        /* Buttons inside notices (View cart, Undo, etc.) */
        .woocommerce-message .button,
        .woocommerce-error .button,
        .woocommerce-info .button {
            display: inline-flex !important;
            align-items: center !important;
            gap: 6px !important;
            background-color: var(--btn-bg) !important;
            color: var(--btn-text) !important;
            font-size: 13px !important;
            font-weight: 800 !important;
            padding: 8px 18px !important;
            border-radius: 10px !important;
            border: none !important;
            box-shadow: 0 2px 6px rgba(0,0,0,0.1) !important;
            transition: all 0.2s ease !important;
            text-decoration: none !important;
            order: 2 !important;
            margin-left: auto !important;
            float: none !important;
        }

        .woocommerce-message .button:hover,
        .woocommerce-error .button:hover,
        .woocommerce-info .button:hover {
            background-color: var(--btn-hover) !important;
            transform: translateY(-1px) !important;
            box-shadow: 0 4px 10px rgba(0,0,0,0.15) !important;
        }
    </style>
    <?php
}
add_action('wp_head', 'fmb_customizer_css_output');