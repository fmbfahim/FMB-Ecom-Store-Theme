<?php
/**
 * Template Name: Privacy Policy
 * Template Post Type: page
 * Theme: FMB E-Com Store
 */
get_header();
$store_name  = get_bloginfo('name');
$store_email = get_theme_mod('fmb_contact_email', get_option('admin_email'));
$store_phone = get_theme_mod('fmb_global_phone', '');
$today_year  = date('Y');
?>

<div class="bg-gray-50 min-h-screen py-12">
    <div class="max-w-4xl mx-auto px-4 sm:px-6">

        <!-- Hero Banner -->
        <div class="bg-gradient-to-r from-primary to-secondary rounded-2xl p-8 mb-8 text-white text-center shadow-lg">
            <div class="inline-flex items-center justify-center w-16 h-16 bg-white/20 rounded-full mb-4">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                </svg>
            </div>
            <h1 class="text-3xl md:text-4xl font-extrabold mb-2">গোপনীয়তা নীতি</h1>
            <p class="text-white/80 text-sm">সর্বশেষ আপডেট: <?php echo $today_year; ?></p>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">

            <!-- Intro -->
            <div class="p-6 md:p-10 border-b border-gray-100 bg-blue-50/50">
                <p class="text-gray-700 leading-relaxed text-base">
                    <strong><?php echo esc_html($store_name); ?></strong> আপনার গোপনীয়তাকে অত্যন্ত গুরুত্ব দেয়। এই নীতিমালা ব্যাখ্যা করে যে আমরা কীভাবে আপনার ব্যক্তিগত তথ্য সংগ্রহ, ব্যবহার এবং সুরক্ষিত রাখি।
                </p>
            </div>

            <div class="p-6 md:p-10 space-y-8">

                <!-- Section 1 -->
                <div class="flex gap-5">
                    <div class="flex-shrink-0 w-10 h-10 bg-blue-100 text-blue-600 rounded-xl flex items-center justify-center font-bold text-lg">১</div>
                    <div>
                        <h2 class="text-xl font-bold text-gray-800 mb-3">কোন তথ্য সংগ্রহ করা হয়?</h2>
                        <ul class="space-y-2 text-gray-600">
                            <li class="flex items-start gap-2"><span class="text-primary mt-1">✔</span> নাম ও যোগাযোগের তথ্য (ফোন নম্বর, ঠিকানা)</li>
                            <li class="flex items-start gap-2"><span class="text-primary mt-1">✔</span> অর্ডারের তথ্য (পণ্য, পরিমাণ, ডেলিভারি ঠিকানা)</li>
                            <li class="flex items-start gap-2"><span class="text-primary mt-1">✔</span> ওয়েবসাইট ব্যবহারের ডেটা (ব্রাউজার, IP ঠিকানা)</li>
                        </ul>
                    </div>
                </div>

                <hr class="border-gray-100">

                <!-- Section 2 -->
                <div class="flex gap-5">
                    <div class="flex-shrink-0 w-10 h-10 bg-green-100 text-green-600 rounded-xl flex items-center justify-center font-bold text-lg">২</div>
                    <div>
                        <h2 class="text-xl font-bold text-gray-800 mb-3">তথ্য কীভাবে ব্যবহার করা হয়?</h2>
                        <ul class="space-y-2 text-gray-600">
                            <li class="flex items-start gap-2"><span class="text-green-500 mt-1">✔</span> আপনার অর্ডার প্রক্রিয়া ও ডেলিভারি নিশ্চিত করতে</li>
                            <li class="flex items-start gap-2"><span class="text-green-500 mt-1">✔</span> অর্ডার সম্পর্কিত আপডেট ও তথ্য জানাতে</li>
                            <li class="flex items-start gap-2"><span class="text-green-500 mt-1">✔</span> আমাদের সেবা উন্নত করতে</li>
                            <li class="flex items-start gap-2"><span class="text-red-400 mt-1">✗</span> আপনার তথ্য তৃতীয় পক্ষের কাছে বিক্রি করা হবে না</li>
                        </ul>
                    </div>
                </div>

                <hr class="border-gray-100">

                <!-- Section 3 -->
                <div class="flex gap-5">
                    <div class="flex-shrink-0 w-10 h-10 bg-purple-100 text-purple-600 rounded-xl flex items-center justify-center font-bold text-lg">৩</div>
                    <div>
                        <h2 class="text-xl font-bold text-gray-800 mb-3">তথ্য সুরক্ষা</h2>
                        <p class="text-gray-600 leading-relaxed">আমরা আপনার তথ্য সুরক্ষার জন্য শিল্প-মানের নিরাপত্তা ব্যবস্থা ব্যবহার করি। তবে ইন্টারনেটে সম্পূর্ণ নিরাপত্তা নিশ্চিত করা সম্ভব নয় — আমরা সর্বোচ্চ প্রচেষ্টা করি।</p>
                    </div>
                </div>

                <hr class="border-gray-100">

                <!-- Section 4 -->
                <div class="flex gap-5">
                    <div class="flex-shrink-0 w-10 h-10 bg-orange-100 text-orange-600 rounded-xl flex items-center justify-center font-bold text-lg">৪</div>
                    <div>
                        <h2 class="text-xl font-bold text-gray-800 mb-3">কুকিজ (Cookies)</h2>
                        <p class="text-gray-600 leading-relaxed">আমাদের ওয়েবসাইট কুকিজ ব্যবহার করে যা আপনার ব্রাউজিং অভিজ্ঞতা উন্নত করতে সাহায্য করে। আপনি চাইলে ব্রাউজার সেটিংসে কুকিজ বন্ধ করতে পারেন।</p>
                    </div>
                </div>

                <hr class="border-gray-100">

                <!-- Section 5 -->
                <div class="flex gap-5">
                    <div class="flex-shrink-0 w-10 h-10 bg-red-100 text-red-600 rounded-xl flex items-center justify-center font-bold text-lg">৫</div>
                    <div>
                        <h2 class="text-xl font-bold text-gray-800 mb-3">আপনার অধিকার</h2>
                        <p class="text-gray-600 leading-relaxed mb-3">আপনি যেকোনো সময় আপনার সম্পর্কে সংরক্ষিত তথ্য দেখতে, সংশোধন করতে বা মুছে ফেলার অনুরোধ করতে পারেন।</p>
                        <?php if ($store_email) : ?>
                        <a href="mailto:<?php echo esc_attr($store_email); ?>"
                           class="inline-flex items-center gap-2 text-primary font-semibold hover:underline">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                            <?php echo esc_html($store_email); ?>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>

            </div><!-- /content -->

            <!-- Footer note -->
            <div class="px-6 md:px-10 py-5 bg-gray-50 border-t border-gray-100 text-sm text-gray-500 text-center">
                &copy; <?php echo $today_year; ?> <?php echo esc_html($store_name); ?> — সর্বস্বত্ব সংরক্ষিত।
            </div>
        </div>

    </div>
</div>

<?php get_footer(); ?>
