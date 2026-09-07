<?php
// ডাইনামিকভাবে পেজের লিংক আনা
function fmb_page_url($slug) {
    $page = get_page_by_path($slug);
    return $page ? get_permalink($page->ID) : home_url('/' . $slug . '/');
}

$store_name  = get_bloginfo('name');
$store_phone = get_theme_mod('fmb_global_phone', '');
$store_addr  = get_theme_mod('fmb_global_address', 'ঢাকা, বাংলাদেশ');
$store_email = get_theme_mod('fmb_global_email', get_option('admin_email'));
$fb_url      = get_theme_mod('fmb_messenger_url', '');
$copyright   = get_theme_mod('fmb_copyright_text', '© ' . date('Y') . ' ' . $store_name . '. সর্বস্বত্ব সংরক্ষিত।');
?>

<footer class="bg-secondary text-gray-300 pt-14 pb-0 border-t border-gray-800">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-8 pb-12">

            <!-- Col 1: Brand -->
            <div class="sm:col-span-2 md:col-span-1">
                <?php if (is_active_sidebar('footer-1')) : ?>
                    <?php dynamic_sidebar('footer-1'); ?>
                <?php else : ?>
                    <a href="<?php echo esc_url( home_url( '/' ) ); ?>">
                        <?php 
                        $fmb_footer_logo = function_exists('fmb_get_site_logo_url') ? fmb_get_site_logo_url() : get_theme_mod('fmb_logo', '');
                        if ( ! empty( $fmb_footer_logo ) ) : 
                        ?>
                            <img src="<?php echo esc_url( $fmb_footer_logo ); ?>"
                                 alt="<?php echo esc_attr( $store_name ); ?>"
                                 class="h-10 w-auto object-contain mb-4 opacity-90">
                        <?php else : ?>
                            <h2 class="text-white text-xl font-bold mb-4 hover:text-primary transition-colors">
                                <?php echo esc_html( $store_name ); ?>
                            </h2>
                        <?php endif; ?>
                    </a>
                    <p class="text-sm leading-relaxed text-gray-400 mb-5">
                        মানসম্পন্ন পণ্য ও সেরা সেবা নিয়ে আমরা সবসময় আপনার পাশে আছি।
                    </p>
                    <!-- Social Icons -->
                    <div class="flex items-center gap-3">
                        <?php if ($fb_url && $fb_url !== 'https://m.me/yourpage') : ?>
                        <a href="<?php echo esc_url($fb_url); ?>" target="_blank"
                           class="w-9 h-9 bg-gray-700 hover:bg-[#1877F2] rounded-lg flex items-center justify-center transition">
                            <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                        </a>
                        <?php endif; ?>
                        <?php if ($store_phone) : ?>
                        <a href="https://wa.me/88<?php echo preg_replace('/[^0-9]/', '', $store_phone); ?>" target="_blank"
                           class="w-9 h-9 bg-gray-700 hover:bg-[#25D366] rounded-lg flex items-center justify-center transition">
                            <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                        </a>
                        <?php endif; ?>
                        <?php if ($store_phone) : ?>
                        <a href="tel:<?php echo esc_attr($store_phone); ?>"
                           class="w-9 h-9 bg-gray-700 hover:bg-primary rounded-lg flex items-center justify-center transition">
                            <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                        </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Col 2: Quick Links -->
            <div>
                <?php if (is_active_sidebar('footer-2')) : ?>
                    <?php dynamic_sidebar('footer-2'); ?>
                <?php else : ?>
                    <h3 class="text-white text-base font-bold mb-5 relative pb-3 after:absolute after:bottom-0 after:left-0 after:w-8 after:h-0.5 after:bg-primary">প্রয়োজনীয় লিংক</h3>
                    <ul class="space-y-3 text-sm">
                        <li>
                            <a href="<?php echo home_url('/shop'); ?>" class="flex items-center gap-2 text-gray-400 hover:text-white transition hover:translate-x-1 transform duration-200">
                                <span class="text-primary">›</span> শপ
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo esc_url(fmb_page_url('about')); ?>" class="flex items-center gap-2 text-gray-400 hover:text-white transition hover:translate-x-1 transform duration-200">
                                <span class="text-primary">›</span> আমাদের সম্পর্কে
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo esc_url(fmb_page_url('contact')); ?>" class="flex items-center gap-2 text-gray-400 hover:text-white transition hover:translate-x-1 transform duration-200">
                                <span class="text-primary">›</span> যোগাযোগ
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo esc_url(fmb_page_url('track-order')); ?>" class="flex items-center gap-2 text-gray-400 hover:text-white transition hover:translate-x-1 transform duration-200">
                                <span class="text-primary">›</span> অর্ডার ট্র্যাক করুন
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo esc_url(fmb_page_url('privacy-policy')); ?>" class="flex items-center gap-2 text-gray-400 hover:text-white transition hover:translate-x-1 transform duration-200">
                                <span class="text-primary">›</span> গোপনীয়তা নীতি
                            </a>
                        </li>
                    </ul>
                <?php endif; ?>
            </div>

            <!-- Col 3: Customer Service -->
            <div>
                <h3 class="text-white text-base font-bold mb-5 relative pb-3 after:absolute after:bottom-0 after:left-0 after:w-8 after:h-0.5 after:bg-primary">গ্রাহক সেবা</h3>
                <ul class="space-y-3 text-sm">
                    <li>
                        <a href="<?php echo esc_url(fmb_page_url('track-order')); ?>" class="flex items-center gap-2 text-gray-400 hover:text-white transition hover:translate-x-1 transform duration-200">
                            <svg class="w-4 h-4 text-primary flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                            অর্ডার ট্র্যাক করুন
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo esc_url(fmb_page_url('contact')); ?>" class="flex items-center gap-2 text-gray-400 hover:text-white transition hover:translate-x-1 transform duration-200">
                            <svg class="w-4 h-4 text-primary flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                            বার্তা পাঠান
                        </a>
                    </li>
                    <?php if (class_exists('WooCommerce') && get_option('woocommerce_myaccount_page_id')) : ?>
                    <li>
                        <a href="<?php echo wc_get_account_endpoint_url('orders'); ?>" class="flex items-center gap-2 text-gray-400 hover:text-white transition hover:translate-x-1 transform duration-200">
                            <svg class="w-4 h-4 text-primary flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                            আমার অ্যাকাউন্ট
                        </a>
                    </li>
                    <?php endif; ?>
                    <li>
                        <a href="<?php echo esc_url(fmb_page_url('privacy-policy')); ?>" class="flex items-center gap-2 text-gray-400 hover:text-white transition hover:translate-x-1 transform duration-200">
                            <svg class="w-4 h-4 text-primary flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                            গোপনীয়তা নীতি
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Col 4: Contact Info -->
            <div>
                <?php if (is_active_sidebar('footer-3')) : ?>
                    <?php dynamic_sidebar('footer-3'); ?>
                <?php else : ?>
                    <h3 class="text-white text-base font-bold mb-5 relative pb-3 after:absolute after:bottom-0 after:left-0 after:w-8 after:h-0.5 after:bg-primary">যোগাযোগ</h3>
                    <ul class="space-y-4 text-sm">
                        <?php if ($store_addr) : ?>
                        <li class="flex items-start gap-3 text-gray-400">
                            <svg class="w-4 h-4 text-primary mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            <?php echo esc_html($store_addr); ?>
                        </li>
                        <?php endif; ?>
                        <?php if ($store_phone) : ?>
                        <li>
                            <a href="tel:<?php echo esc_attr($store_phone); ?>" class="flex items-center gap-3 text-gray-400 hover:text-white transition">
                                <svg class="w-4 h-4 text-primary flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                                <?php echo esc_html($store_phone); ?>
                            </a>
                        </li>
                        <?php endif; ?>
                        <?php if ($store_email) : ?>
                        <li>
                            <a href="mailto:<?php echo esc_attr($store_email); ?>" class="flex items-center gap-3 text-gray-400 hover:text-white transition">
                                <svg class="w-4 h-4 text-primary flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                <?php echo esc_html($store_email); ?>
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>
                <?php endif; ?>
            </div>

        </div><!-- /grid -->

        <!-- Bottom Bar -->
        <div class="border-t border-gray-700/60 py-6 flex flex-col sm:flex-row justify-between items-center gap-4 text-xs text-gray-500">
            <p><?php echo esc_html($copyright); ?></p>

            <div class="flex flex-wrap items-center gap-4">
                <!-- Quick bottom links -->
                <a href="<?php echo esc_url(fmb_page_url('privacy-policy')); ?>" class="hover:text-gray-300 transition">গোপনীয়তা নীতি</a>
                <span>·</span>
                <a href="<?php echo esc_url(fmb_page_url('track-order')); ?>" class="hover:text-gray-300 transition">অর্ডার ট্র্যাক</a>
                <span>·</span>
                <a href="<?php echo esc_url(fmb_page_url('contact')); ?>" class="hover:text-gray-300 transition">যোগাযোগ</a>
            </div>

            <!-- Payment badges -->
            <div class="flex items-center gap-2">
                <span class="bg-gray-700 text-gray-300 px-2 py-1 rounded text-[10px] font-bold">bKash</span>
                <span class="bg-gray-700 text-gray-300 px-2 py-1 rounded text-[10px] font-bold">Nagad</span>
                <span class="bg-gray-700 text-gray-300 px-2 py-1 rounded text-[10px] font-bold">COD</span>
            </div>
        </div>

    </div>
</footer>

<style>
    .widget ul { margin: 0; padding: 0; list-style: none; }
    .widget ul li { margin-bottom: 8px; font-size: 0.9rem; }
    .widget ul li a { color: #9ca3af; text-decoration: none; transition: 0.2s; }
    .widget ul li a:hover { color: white; padding-left: 4px; }
    .widget-title { color: white; font-size: 1rem; font-weight: 700; margin-bottom: 1.25rem; padding-bottom: 0.75rem; border-bottom: 2px solid var(--primary); display: inline-block; }
</style>