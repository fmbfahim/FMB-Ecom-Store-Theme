<?php
/**
 * The template for displaying 404 pages (not found)
 * Theme: FMB E-Com Store
 */

get_header();
?>

<div class="bg-gray-50 min-h-screen flex flex-col items-center justify-center py-16 px-4 sm:px-6 lg:px-8">
    
    <div class="max-w-md w-full text-center space-y-8">
        
        <div class="relative">
            <h1 class="text-9xl font-extrabold text-gray-200 tracking-widest">404</h1>
            <div class="absolute inset-0 flex items-center justify-center">
                <span class="bg-red-100 text-red-600 px-3 py-1 rounded text-sm font-bold shadow-sm transform -rotate-6">
                    পেজ পাওয়া যায়নি!
                </span>
            </div>
        </div>

        <h2 class="mt-6 text-3xl font-extrabold text-gray-900">
            দুঃখিত, আপনি ভুল রাস্তায় এসেছেন!
        </h2>
        <p class="mt-2 text-sm text-gray-600">
            আপনি যে পেজটি খুঁজছেন তা সরানো হয়েছে অথবা লিংকটি ভুল। নিচের বাটন দিয়ে শপ পেজে ফিরে যান।
        </p>

        <div class="mt-8 flex flex-col sm:flex-row gap-4 justify-center">
            <a href="<?php echo home_url(); ?>" class="flex items-center justify-center px-5 py-3 border border-transparent text-base font-medium rounded-md text-white bg-primary hover:bg-dark transition shadow-lg">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                হোম পেজ
            </a>
            <a href="<?php echo home_url('/shop'); ?>" class="flex items-center justify-center px-5 py-3 border border-gray-300 text-base font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 transition shadow">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path></svg>
                শপিং করুন
            </a>
        </div>

        <div class="mt-8">
            <form role="search" method="get" action="<?php echo home_url( '/' ); ?>" class="relative">
                <input type="search" class="w-full border border-gray-300 rounded-full py-3 pl-5 pr-12 shadow-sm focus:ring-2 focus:ring-primary focus:border-primary outline-none" placeholder="প্রডাক্ট সার্চ করুন..." value="<?php echo get_search_query(); ?>" name="s">
                <button type="submit" class="absolute right-2 top-2 bg-gray-100 p-2 rounded-full text-gray-500 hover:text-primary transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </button>
            </form>
        </div>
    </div>

    <div class="mt-20 w-full max-w-7xl">
        <div class="text-center mb-10">
            <h3 class="text-xl font-bold text-gray-800 uppercase tracking-wide">জনপ্রিয় কালেকশন</h3>
            <div class="w-16 h-1 bg-primary mx-auto mt-2 rounded"></div>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
            <?php
            $args = array(
                'post_type'      => 'product',
                'posts_per_page' => 4,
                'orderby'        => 'rand', // র‍্যান্ডম প্রডাক্ট
            );
            $loop = new WP_Query( $args );

            if ( $loop->have_posts() ) {
                while ( $loop->have_posts() ) : $loop->the_post();
                    // আমাদের তৈরি করা কার্ড ডিজাইন কল করছি
                    wc_get_template_part( 'content', 'product' );
                endwhile;
            } else {
                echo '<p class="text-center col-span-4 text-gray-500">কোনো প্রডাক্ট পাওয়া যায়নি</p>';
            }
            wp_reset_postdata();
            ?>
        </div>
    </div>

</div>

<?php
get_footer();