<?php
/**
 * Template Name: About Us
 * Template Post Type: page
 * Theme: FMB E-Com Store
 */
get_header();
$store_name  = get_bloginfo('name');
$store_phone = get_theme_mod('fmb_global_phone', '');
$store_email = get_theme_mod('fmb_contact_email', get_option('admin_email'));
$store_desc  = get_bloginfo('description');
?>

<div class="bg-gray-50 min-h-screen">

    <!-- Hero Section -->
    <div class="relative bg-gradient-to-br from-gray-900 via-gray-800 to-primary overflow-hidden">
        <div class="absolute inset-0 opacity-10" style="background-image: url('data:image/svg+xml,%3Csvg width=\'60\' height=\'60\' viewBox=\'0 0 60 60\' xmlns=\'http://www.w3.org/2000/svg\'%3E%3Cg fill=\'none\' fill-rule=\'evenodd\'%3E%3Cg fill=\'%23ffffff\' fill-opacity=\'0.4\'%3E%3Cpath d=\'M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z\'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E');"></div>
        <div class="relative max-w-5xl mx-auto px-4 py-20 md:py-28 text-center text-white">
            <span class="inline-block bg-white/10 text-white text-xs font-bold px-4 py-1.5 rounded-full uppercase tracking-widest mb-6">আমাদের সম্পর্কে</span>
            <h1 class="text-4xl md:text-6xl font-extrabold mb-6 leading-tight">
                <?php echo esc_html($store_name); ?>
            </h1>
            <?php if ($store_desc) : ?>
            <p class="text-xl text-gray-300 max-w-2xl mx-auto leading-relaxed">
                <?php echo esc_html($store_desc); ?>
            </p>
            <?php else : ?>
            <p class="text-xl text-gray-300 max-w-2xl mx-auto leading-relaxed">
                আমরা বিশ্বাসযোগ্য পণ্য ও সেরা সেবা নিয়ে আপনার পাশে আছি।
            </p>
            <?php endif; ?>
        </div>
    </div>

    <div class="max-w-5xl mx-auto px-4 py-16 space-y-16">

        <!-- Our Story -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-10 items-center">
            <div>
                <span class="text-primary font-bold text-sm uppercase tracking-widest">আমাদের গল্প</span>
                <h2 class="text-3xl font-extrabold text-gray-900 mt-2 mb-5">কীভাবে শুরু হয়েছিল?</h2>
                <?php
                // Show page custom content if exists, otherwise default
                if (have_posts()) {
                    while (have_posts()) { the_post(); }
                    $content = get_the_content();
                }
                if (!empty($content)) {
                    echo '<div class="prose max-w-none text-gray-600 leading-relaxed">' . apply_filters('the_content', $content) . '</div>';
                } else {
                ?>
                <div class="space-y-4 text-gray-600 leading-relaxed">
                    <p><?php echo esc_html($store_name); ?> শুরু হয়েছিল একটি সহজ লক্ষ্য নিয়ে — বাংলাদেশের মানুষদের কাছে মানসম্পন্ন পণ্য সহজে ও সাশ্রয়ী মূল্যে পৌঁছে দেওয়া।</p>
                    <p>আমরা বিশ্বাস করি প্রতিটি গ্রাহক সেরা অভিজ্ঞতা পাওয়ার যোগ্য। তাই আমাদের প্রতিটি পণ্য যত্ন সহকারে বাছাই করা হয়।</p>
                    <p>আমাদের লক্ষ্য শুধু পণ্য বিক্রি নয় — দীর্ঘমেয়াদী বিশ্বাস তৈরি করা।</p>
                </div>
                <?php } ?>
            </div>
            <div class="relative">
                <?php
                $logo = get_theme_mod('fmb_logo', '');
                if ($logo) :
                ?>
                <div class="bg-gradient-to-br from-primary/10 to-blue-50 rounded-3xl p-10 flex items-center justify-center min-h-[280px]">
                    <img src="<?php echo esc_url($logo); ?>" alt="<?php echo esc_attr($store_name); ?>" class="max-h-40 object-contain">
                </div>
                <?php else : ?>
                <div class="bg-gradient-to-br from-primary/10 to-blue-50 rounded-3xl p-10 flex items-center justify-center min-h-[280px]">
                    <span class="text-5xl font-extrabold text-primary/40"><?php echo esc_html($store_name); ?></span>
                </div>
                <?php endif; ?>
                <!-- Floating badge -->
                <div class="absolute -bottom-4 -right-4 bg-white rounded-2xl shadow-lg p-4 border border-gray-100">
                    <div class="flex items-center gap-2">
                        <span class="text-2xl">⭐</span>
                        <div>
                            <p class="font-extrabold text-gray-800 leading-none">৪.৯/৫</p>
                            <p class="text-xs text-gray-400">গ্রাহক রেটিং</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Values / Features -->
        <div>
            <div class="text-center mb-10">
                <span class="text-primary font-bold text-sm uppercase tracking-widest">আমাদের মূল্যবোধ</span>
                <h2 class="text-3xl font-extrabold text-gray-900 mt-2">কেন আমাদের বেছে নেবেন?</h2>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-6">
                <?php
                $values = array(
                    array('icon' => '🏆', 'color' => 'yellow', 'title' => 'মানসম্পন্ন পণ্য', 'desc' => 'প্রতিটি পণ্য যত্ন সহকারে বাছাই ও মান নিশ্চিত করা হয়।'),
                    array('icon' => '🚚', 'color' => 'blue',   'title' => 'দ্রুত ডেলিভারি',  'desc' => 'সারাদেশে দ্রুততার সাথে ডেলিভারি নিশ্চিত করা হয়।'),
                    array('icon' => '💳', 'color' => 'green',  'title' => 'ক্যাশ অন ডেলিভারি', 'desc' => 'পণ্য হাতে পেয়ে টাকা দিন — কোনো আগাম পেমেন্ট নেই।'),
                    array('icon' => '🔄', 'color' => 'purple', 'title' => 'রিটার্ন পলিসি',  'desc' => 'পণ্যে সমস্যা হলে সহজ রিটার্ন ও রিপ্লেসমেন্টের সুবিধা।'),
                    array('icon' => '📞', 'color' => 'red',    'title' => '২৪/৭ সাপোর্ট',   'desc' => 'যেকোনো সমস্যায় আমাদের টিম সবসময় সাহায্য করতে প্রস্তুত।'),
                    array('icon' => '🛡️', 'color' => 'gray',  'title' => 'বিশ্বস্ততা',      'desc' => 'হাজারো সন্তুষ্ট গ্রাহকের বিশ্বাস আমাদের সবচেয়ে বড় সম্পদ।'),
                );
                $clr = array(
                    'yellow' => 'bg-yellow-100 text-yellow-600',
                    'blue'   => 'bg-blue-100 text-blue-600',
                    'green'  => 'bg-green-100 text-green-600',
                    'purple' => 'bg-purple-100 text-purple-600',
                    'red'    => 'bg-red-100 text-red-600',
                    'gray'   => 'bg-gray-100 text-gray-600',
                );
                foreach ($values as $val) : ?>
                <div class="bg-white rounded-2xl p-6 border border-gray-100 shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all duration-300">
                    <div class="w-12 h-12 <?php echo $clr[$val['color']]; ?> rounded-xl flex items-center justify-center text-2xl mb-4">
                        <?php echo $val['icon']; ?>
                    </div>
                    <h3 class="font-bold text-gray-800 mb-2"><?php echo $val['title']; ?></h3>
                    <p class="text-gray-500 text-sm leading-relaxed"><?php echo $val['desc']; ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Stats -->
        <div class="bg-gradient-to-r from-primary to-secondary rounded-3xl p-8 md:p-12 text-white">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-6 text-center">
                <?php
                $stats = array(
                    array('num' => '১০০০+', 'label' => 'সন্তুষ্ট গ্রাহক'),
                    array('num' => '৫০০+',  'label' => 'পণ্যের সংগ্রহ'),
                    array('num' => '৪৮ ঘন্টা', 'label' => 'ডেলিভারি সময়'),
                    array('num' => '৯৯%',  'label' => 'সন্তুষ্টির হার'),
                );
                foreach ($stats as $stat) : ?>
                <div>
                    <p class="text-3xl md:text-4xl font-extrabold"><?php echo $stat['num']; ?></p>
                    <p class="text-white/70 text-sm mt-1"><?php echo $stat['label']; ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- CTA -->
        <div class="text-center bg-white rounded-2xl border border-gray-100 shadow-sm p-10">
            <h2 class="text-2xl font-extrabold text-gray-900 mb-3">আমাদের সাথে যোগাযোগ করুন</h2>
            <p class="text-gray-500 mb-6 max-w-md mx-auto">কোনো প্রশ্ন বা সাহায্য দরকার? আমরা সবসময় আপনার পাশে আছি।</p>
            <div class="flex flex-wrap justify-center gap-4">
                <?php if ($store_phone) : ?>
                <a href="tel:<?php echo esc_attr($store_phone); ?>"
                   class="inline-flex items-center gap-2 bg-primary text-white font-bold px-6 py-3 rounded-xl hover:bg-opacity-90 transition shadow-md">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                    কল করুন
                </a>
                <?php endif; ?>
                <a href="<?php echo get_permalink(get_page_by_path('contact')); ?>"
                   class="inline-flex items-center gap-2 bg-gray-100 text-gray-800 font-bold px-6 py-3 rounded-xl hover:bg-gray-200 transition">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                    বার্তা পাঠান
                </a>
            </div>
        </div>

    </div>
</div>

<?php get_footer(); ?>
