<?php
/**
 * The Template for displaying product archives, including the main shop page which is a post type archive.
 * Theme: FMB E-Com Store (Modern 2027 Design)
 */

defined( 'ABSPATH' ) || exit;

get_header();

$current_cat_id = is_product_category() ? get_queried_object_id() : 0;
$categories = get_terms( array(
    'taxonomy'   => 'product_cat',
    'hide_empty' => true,
    'number'     => 12,
) );
?>

<main class="py-6 md:py-10 bg-[#F8FAFC] min-h-screen">
    <div class="max-w-7xl mx-auto px-3 sm:px-6 lg:px-8">

        <!-- 🛍️ Modern Shop Hero Banner -->
        <div class="relative overflow-hidden bg-gradient-to-r from-[#0B1E67] via-[#1E3A8A] to-[#0B1E67] rounded-2xl sm:rounded-3xl p-6 sm:p-8 md:p-10 mb-6 sm:mb-8 text-white shadow-lg">
            <div class="relative z-10 max-w-2xl">
                <div class="inline-flex items-center gap-2 bg-white/15 backdrop-blur-md px-3 py-1 rounded-full text-xs font-extrabold uppercase tracking-wider text-yellow-300 mb-3 border border-white/10">
                    <span>🛍️</span>
                    <span>অফিশিয়াল শপ কালেকশন</span>
                </div>
                
                <h1 class="text-2xl sm:text-3xl md:text-4xl font-extrabold tracking-tight text-white mb-2">
                    <?php woocommerce_page_title(); ?>
                </h1>
                
                <p class="text-xs sm:text-sm text-blue-100/90 leading-relaxed mb-4">
                    ১০০% জেনুইন ও প্রিমিয়াম কোয়ালিটি পণ্য, দ্রুততম হোম ডেলিভারি ও ক্যাশ অন ডেলিভারি সুবিধা।
                </p>

                <?php if ( woocommerce_product_loop() && wc_get_loop_prop( 'total' ) ) : ?>
                    <div class="inline-flex items-center gap-2 bg-white text-gray-900 font-extrabold text-xs px-3.5 py-1.5 rounded-full shadow">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                        <span>মোট <?php echo esc_html(wc_get_loop_prop( 'total' )); ?>টি পণ্য উপলব্ধ</span>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Subtle background decorative circle -->
            <div class="absolute -right-12 -bottom-12 w-64 h-64 bg-white/5 rounded-full blur-2xl pointer-events-none"></div>
        </div>

        <!-- 📂 Interactive Category Filter Pills -->
        <?php if ( ! empty( $categories ) && ! is_wp_error( $categories ) ) : ?>
            <div class="mb-6 overflow-x-auto no-scrollbar pb-2">
                <div class="flex items-center gap-2 min-w-max">
                    <!-- All Products Pill -->
                    <a href="<?php echo esc_url( get_permalink( wc_get_page_id( 'shop' ) ) ); ?>" 
                       class="px-4 py-2 rounded-full text-xs sm:text-sm font-extrabold transition shadow-sm border <?php echo (!is_product_category()) ? 'bg-[#0B1E67] text-white border-[#0B1E67]' : 'bg-white text-gray-700 border-gray-200 hover:bg-blue-50 hover:text-[#0B1E67]'; ?>">
                        🌐 সব পণ্য
                    </a>

                    <?php foreach ( $categories as $cat ) : 
                        $is_active = ($current_cat_id === $cat->term_id);
                    ?>
                        <a href="<?php echo esc_url( get_term_link( $cat ) ); ?>" 
                           class="px-4 py-2 rounded-full text-xs sm:text-sm font-bold transition shadow-sm border <?php echo $is_active ? 'bg-[#0B1E67] text-white border-[#0B1E67]' : 'bg-white text-gray-700 border-gray-200 hover:bg-blue-50 hover:text-[#0B1E67]'; ?>">
                            <?php echo esc_html( $cat->name ); ?>
                            <span class="<?php echo $is_active ? 'bg-white/20 text-white' : 'bg-gray-100 text-gray-500'; ?> text-[10px] font-black px-1.5 py-0.5 rounded-full ml-1">
                                <?php echo $cat->count; ?>
                            </span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if ( woocommerce_product_loop() ) : ?>

            <!-- ⚙️ Sorting & Result Count Bar -->
            <div class="mb-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 bg-white p-3 sm:p-4 rounded-xl border border-gray-200/80 shadow-sm">
                <div class="text-xs sm:text-sm font-semibold text-gray-600">
                    <?php woocommerce_result_count(); ?>
                </div>

                <div class="fmb-sorting w-full sm:w-auto">
                    <?php woocommerce_catalog_ordering(); ?>
                </div>
            </div>

            <!-- 🛍️ Modern Responsive Product Grid (2027 Style) -->
            <div class="grid grid-cols-2 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3 sm:gap-4 md:gap-6">
                <?php
                if ( wc_get_loop_prop( 'total' ) ) {
                    while ( have_posts() ) {
                        the_post();

                        /**
                         * Hook: woocommerce_shop_loop.
                         */
                        do_action( 'woocommerce_shop_loop' );

                        // আমাদের আপগ্রেডেড কাস্টম কার্ড টেমপ্লেট
                        wc_get_template_part( 'content', 'product' );
                    }
                }
                ?>
            </div>

            <!-- 📄 Modern Pagination -->
            <div class="mt-10 sm:mt-12 flex justify-center">
                <div class="fmb-pagination">
                    <?php
                    global $wp_query;
                    $total_pages = wc_get_loop_prop( 'total_pages' );
                    if ( empty( $total_pages ) && isset( $wp_query->max_num_pages ) ) {
                        $total_pages = $wp_query->max_num_pages;
                    } elseif ( empty( $total_pages ) && isset( $GLOBALS['wp_query']->max_num_pages ) ) {
                        $total_pages = $GLOBALS['wp_query']->max_num_pages;
                    }
                    $total_pages = ! empty( $total_pages ) ? intval( $total_pages ) : 1;

                    $args = array(
                        'total'   => $total_pages,
                        'current' => max( 1, get_query_var( 'paged' ) ),
                        'format'  => '?paged=%#%',
                        'show_all'     => false,
                        'type'         => 'plain',
                        'end_size'     => 1,
                        'mid_size'     => 1,
                        'prev_next'    => true,
                        'prev_text'    => '<span class="px-3.5 py-1.5 border border-gray-200 rounded-lg hover:bg-primary hover:text-white transition font-bold">← পূর্ববর্তী</span>',
                        'next_text'    => '<span class="px-3.5 py-1.5 border border-gray-200 rounded-lg hover:bg-primary hover:text-white transition font-bold">পরবর্তী →</span>',
                        'add_args'     => false,
                        'add_fragment' => '',
                    );
                    echo paginate_links( $args );
                    ?>
                </div>
            </div>

        <?php else : ?>

            <!-- 🔍 Empty State -->
            <div class="text-center py-16 sm:py-20 bg-white rounded-2xl border border-gray-200/80 shadow-sm p-6 max-w-lg mx-auto">
                <div class="w-16 h-16 bg-blue-50 text-blue-600 rounded-full flex items-center justify-center text-3xl mx-auto mb-4">
                    🔍
                </div>
                <h3 class="text-xl font-extrabold text-gray-900 mb-1">কোনো প্রোডাক্ট পাওয়া যায়নি</h3>
                <p class="text-sm text-gray-500 mb-6">এই ক্যাটাগরিতে বর্তমানে কোনো পণ্য স্টক নেই অথবা শীঘ্রই নতুন কালেকশন আসছে।</p>
                <a href="<?php echo esc_url( get_permalink( wc_get_page_id( 'shop' ) ) ); ?>" class="inline-flex items-center gap-2 px-6 py-3 bg-[#0B1E67] text-white font-extrabold text-xs sm:text-sm rounded-xl hover:bg-[#071344] transition shadow">
                    <span>🛍️ সকল পণ্য দেখুন</span>
                </a>
            </div>

        <?php endif; ?>

    </div>
</main>

<style>
    /* পেজিনেশন ডিজাইন */
    .fmb-pagination .page-numbers {
        display: inline-block;
        padding: 6px 14px;
        margin: 0 3px;
        border: 1px solid #E2E8F0;
        border-radius: 10px;
        color: #1E293B;
        text-decoration: none;
        font-weight: 700;
        font-size: 13px;
        transition: 0.2s ease;
        background: white;
    }
    .fmb-pagination .page-numbers.current {
        background-color: #0B1E67;
        color: white;
        border-color: #0B1E67;
        box-shadow: 0 2px 4px rgba(11, 30, 103, 0.2);
    }
    .fmb-pagination .page-numbers:hover:not(.current) {
        background-color: #EEF5FF;
        color: #0B1E67;
        border-color: #93C5FD;
    }

    /* উকমার্স সর্টিং ড্রপডাউন ডিজাইন */
    .woocommerce-ordering select {
        padding: 8px 32px 8px 14px;
        border: 1px solid #E2E8F0;
        border-radius: 10px;
        background-color: white;
        color: #1E293B;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        outline: none;
        transition: border-color 0.2s;
    }
    .woocommerce-ordering select:focus {
        border-color: #0B1E67;
        box-shadow: 0 0 0 2px rgba(11, 30, 103, 0.15);
    }
</style>

<?php
get_footer();