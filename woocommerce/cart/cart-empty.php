<?php
/**
 * Empty Cart Page Template (Modern 2027 High-Converting Design)
 * Theme: FMB E-Com Store
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="fmb-empty-cart-page py-10 sm:py-16 bg-[#F8FAFC] min-h-[70vh]">
    <div class="max-w-4xl mx-auto px-4 text-center">

        <!-- 🛒 Modern Empty Cart Card -->
        <div class="bg-white rounded-3xl p-8 sm:p-12 shadow-sm border border-blue-100/70 max-w-xl mx-auto">
            <div class="w-24 h-24 bg-[#EEF5FF] text-[#0B1E67] rounded-3xl flex items-center justify-center text-5xl mx-auto mb-6 shadow-inner">
                🛒
            </div>
            
            <h1 class="text-2xl sm:text-3xl font-extrabold text-gray-900 mb-2">
                আপনার শপিং কার্ট বর্তমানে খালি!
            </h1>
            
            <p class="text-sm text-gray-500 leading-relaxed max-w-md mx-auto mb-8">
                আপনি এখনও কার্টে কোনো পণ্য যুক্ত করেননি। আমাদের আকর্ষণীয় কালেকশন থেকে আপনার পছন্দের প্রোডাক্টটি বেছে নিন।
            </p>

            <a href="<?php echo esc_url( apply_filters( 'woocommerce_return_to_shop_redirect', wc_get_page_permalink( 'shop' ) ) ); ?>" 
               class="inline-flex items-center justify-center gap-2 w-full sm:w-auto bg-[#0B1E67] hover:bg-[#071344] text-white font-extrabold text-sm sm:text-base py-3.5 px-8 rounded-xl shadow-lg hover:shadow-xl transition-all duration-300">
                <span>🛍️ শপিং শুরু করুন</span>
                <span class="text-lg">➔</span>
            </a>
        </div>

        <!-- 🔥 Popular Products Showcase Below Empty Cart -->
        <?php
        $popular_query = new WP_Query( array(
            'post_type'      => 'product',
            'posts_per_page' => 4,
            'meta_key'       => 'total_sales',
            'orderby'        => 'meta_value_num',
            'order'          => 'DESC',
        ) );

        if ( ! $popular_query->have_posts() || $popular_query->post_count === 0 ) {
            $popular_query = new WP_Query( array(
                'post_type'      => 'product',
                'posts_per_page' => 4,
                'orderby'        => 'date',
                'order'          => 'DESC'
            ) );
        }

        if ( $popular_query->have_posts() ) :
        ?>
            <div class="mt-14 text-left">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <span class="bg-orange-100 text-orange-600 text-xs font-black px-3 py-1 rounded-full uppercase tracking-wider">🔥 ট্রেন্ডিং কালেকশন</span>
                        <h2 class="text-xl sm:text-2xl font-extrabold text-gray-900 mt-1">জনপ্রিয় কিছু প্রোডাক্ট</h2>
                    </div>
                    <a href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>" class="text-xs sm:text-sm font-bold text-primary hover:underline">
                        সবগুলো দেখুন →
                    </a>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-2 md:grid-cols-4 gap-3 sm:gap-4 md:gap-6">
                    <?php
                    while ( $popular_query->have_posts() ) : $popular_query->the_post();
                        wc_get_template_part( 'content', 'product' );
                    endwhile;
                    wp_reset_postdata();
                    ?>
                </div>
            </div>
        <?php endif; ?>

    </div>
</div>
