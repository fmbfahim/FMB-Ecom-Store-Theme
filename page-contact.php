<?php
/**
 * Template Name: Contact Us
 * Template Post Type: page
 * Theme: FMB E-Com Store
 */
get_header();
$store_name  = get_bloginfo('name');
$store_phone = get_theme_mod('fmb_global_phone', '');
$store_email = get_theme_mod('fmb_contact_email', get_option('admin_email'));
$store_addr  = get_theme_mod('fmb_store_address', '');
$fb_url      = get_theme_mod('fmb_messenger_url', '');

// Handle form
$sent = false;
$form_error = '';
if (isset($_POST['fmb_contact_nonce']) && wp_verify_nonce($_POST['fmb_contact_nonce'], 'fmb_contact_form')) {
    $c_name    = sanitize_text_field($_POST['c_name'] ?? '');
    $c_phone   = sanitize_text_field($_POST['c_phone'] ?? '');
    $c_subject = sanitize_text_field($_POST['c_subject'] ?? '');
    $c_message = sanitize_textarea_field($_POST['c_message'] ?? '');
    if ($c_name && $c_phone && $c_message) {
        $body = "নাম: $c_name\nফোন: $c_phone\nবিষয়: $c_subject\n\nবার্তা:\n$c_message";
        wp_mail($store_email, 'নতুন যোগাযোগ: ' . $c_subject, $body);
        
        // Save to DB
        $post_id = wp_insert_post(array(
            'post_title'   => sanitize_text_field($c_subject ? $c_subject : 'New Message from ' . $c_name),
            'post_content' => $c_message,
            'post_status'  => 'publish',
            'post_type'    => 'fmb_contact_msg',
        ));
        if ($post_id) {
            update_post_meta($post_id, '_c_name', $c_name);
            update_post_meta($post_id, '_c_phone', $c_phone);
        }
        
        $sent = true;
    } else {
        $form_error = 'সব তারকাচিহ্নিত (*) ঘর পূরণ করুন।';
    }
}
?>

<div class="bg-gray-50 min-h-screen py-12">
    <div class="max-w-6xl mx-auto px-4">

        <!-- Hero -->
        <div class="bg-gradient-to-r from-purple-600 to-purple-800 rounded-2xl p-8 mb-10 text-white text-center shadow-lg">
            <div class="inline-flex items-center justify-center w-16 h-16 bg-white/20 rounded-full mb-4">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                </svg>
            </div>
            <h1 class="text-3xl md:text-4xl font-extrabold mb-2">যোগাযোগ করুন</h1>
            <p class="text-purple-100 text-sm">আমরা আপনার সেবায় সর্বদা প্রস্তুত</p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-5 gap-8">

            <!-- Contact Info Cards -->
            <div class="lg:col-span-2 space-y-4">

                <?php if ($store_phone) : ?>
                <a href="tel:<?php echo esc_attr($store_phone); ?>"
                   class="flex items-center gap-4 bg-white p-5 rounded-2xl border border-gray-100 shadow-sm hover:border-purple-200 hover:shadow-md transition group">
                    <div class="w-12 h-12 bg-purple-100 group-hover:bg-purple-600 rounded-xl flex items-center justify-center transition flex-shrink-0">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-purple-600 group-hover:text-white transition" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400 font-medium uppercase tracking-wide mb-0.5">ফোন / কল করুন</p>
                        <p class="font-bold text-gray-800"><?php echo esc_html($store_phone); ?></p>
                    </div>
                </a>
                <?php endif; ?>

                <?php if ($store_email) : ?>
                <a href="mailto:<?php echo esc_attr($store_email); ?>"
                   class="flex items-center gap-4 bg-white p-5 rounded-2xl border border-gray-100 shadow-sm hover:border-purple-200 hover:shadow-md transition group">
                    <div class="w-12 h-12 bg-blue-100 group-hover:bg-blue-600 rounded-xl flex items-center justify-center transition flex-shrink-0">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-blue-600 group-hover:text-white transition" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400 font-medium uppercase tracking-wide mb-0.5">ইমেইল</p>
                        <p class="font-bold text-gray-800"><?php echo esc_html($store_email); ?></p>
                    </div>
                </a>
                <?php endif; ?>

                <?php if ($store_addr) : ?>
                <div class="flex items-center gap-4 bg-white p-5 rounded-2xl border border-gray-100 shadow-sm">
                    <div class="w-12 h-12 bg-green-100 rounded-xl flex items-center justify-center flex-shrink-0">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400 font-medium uppercase tracking-wide mb-0.5">ঠিকানা</p>
                        <p class="font-bold text-gray-800"><?php echo esc_html($store_addr); ?></p>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($fb_url && $fb_url !== 'https://m.me/yourpage') : ?>
                <a href="<?php echo esc_url($fb_url); ?>" target="_blank"
                   class="flex items-center gap-4 bg-white p-5 rounded-2xl border border-gray-100 shadow-sm hover:border-blue-300 hover:shadow-md transition group">
                    <div class="w-12 h-12 bg-blue-100 group-hover:bg-[#1877F2] rounded-xl flex items-center justify-center transition flex-shrink-0">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-[#1877F2] group-hover:text-white transition" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400 font-medium uppercase tracking-wide mb-0.5">Facebook Messenger</p>
                        <p class="font-bold text-gray-800">মেসেজ করুন</p>
                    </div>
                </a>
                <?php endif; ?>

                <!-- Business Hours -->
                <div class="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-10 h-10 bg-orange-100 rounded-xl flex items-center justify-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-orange-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                        <h3 class="font-bold text-gray-800">সেবার সময়</h3>
                    </div>
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between text-gray-600"><span>শনি — বৃহঃ</span><span class="font-semibold text-gray-800">সকাল ৯টা — রাত ৯টা</span></div>
                        <div class="flex justify-between text-gray-600"><span>শুক্রবার</span><span class="font-semibold text-gray-800">বিকাল ২টা — রাত ৯টা</span></div>
                    </div>
                </div>

            </div>

            <!-- Contact Form -->
            <div class="lg:col-span-3">
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 md:p-8">
                    <?php if ($sent) : ?>
                        <div class="text-center py-10">
                            <div class="inline-flex items-center justify-center w-16 h-16 bg-green-100 rounded-full mb-4">
                                <svg class="h-8 w-8 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            </div>
                            <h3 class="text-xl font-bold text-gray-800 mb-2">বার্তা পাঠানো হয়েছে!</h3>
                            <p class="text-gray-600 mb-6">আমরা শীঘ্রই আপনার সাথে যোগাযোগ করব।</p>
                            <a href="" class="text-purple-600 hover:underline font-medium">← আরো বার্তা পাঠান</a>
                        </div>
                    <?php else : ?>
                        <h2 class="text-xl font-bold text-gray-800 mb-6">বার্তা পাঠান</h2>

                        <?php if ($form_error) : ?>
                            <div class="mb-5 p-4 bg-red-50 border border-red-200 rounded-xl text-red-700 text-sm font-medium">
                                <?php echo esc_html($form_error); ?>
                            </div>
                        <?php endif; ?>

                        <form method="post" class="space-y-5">
                            <?php wp_nonce_field('fmb_contact_form', 'fmb_contact_nonce'); ?>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-bold text-gray-700 mb-1.5">নাম <span class="text-red-500">*</span></label>
                                    <input type="text" name="c_name" required
                                           value="<?php echo esc_attr($_POST['c_name'] ?? ''); ?>"
                                           placeholder="আপনার নাম"
                                           class="w-full px-4 py-3 border border-gray-200 rounded-xl bg-gray-50 focus:outline-none focus:ring-2 focus:ring-purple-400 focus:border-purple-400 transition text-sm">
                                </div>
                                <div>
                                    <label class="block text-sm font-bold text-gray-700 mb-1.5">ফোন নম্বর <span class="text-red-500">*</span></label>
                                    <input type="tel" name="c_phone" required
                                           value="<?php echo esc_attr($_POST['c_phone'] ?? ''); ?>"
                                           placeholder="আপনার ফোন নম্বর"
                                           class="w-full px-4 py-3 border border-gray-200 rounded-xl bg-gray-50 focus:outline-none focus:ring-2 focus:ring-purple-400 focus:border-purple-400 transition text-sm">
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-1.5">বিষয়</label>
                                <input type="text" name="c_subject"
                                       value="<?php echo esc_attr($_POST['c_subject'] ?? ''); ?>"
                                       placeholder="বার্তার বিষয়"
                                       class="w-full px-4 py-3 border border-gray-200 rounded-xl bg-gray-50 focus:outline-none focus:ring-2 focus:ring-purple-400 focus:border-purple-400 transition text-sm">
                            </div>

                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-1.5">বার্তা <span class="text-red-500">*</span></label>
                                <textarea name="c_message" required rows="5"
                                          placeholder="আপনার বার্তা লিখুন..."
                                          class="w-full px-4 py-3 border border-gray-200 rounded-xl bg-gray-50 focus:outline-none focus:ring-2 focus:ring-purple-400 focus:border-purple-400 transition text-sm resize-none"><?php echo esc_textarea($_POST['c_message'] ?? ''); ?></textarea>
                            </div>

                            <button type="submit"
                                    class="w-full bg-purple-600 hover:bg-purple-700 text-white font-bold py-3.5 rounded-xl transition shadow-md flex items-center justify-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                                বার্তা পাঠান
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>
</div>

<?php get_footer(); ?>
