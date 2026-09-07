<?php
/**
 * Custom Thank You Page
 * Theme: FMB E-Com Store
 */
defined( 'ABSPATH' ) || exit;

get_header();
?>

<div class="fmb-thankyou-page bg-gray-50 min-h-screen py-12">
    <div class="max-w-4xl mx-auto px-4">

        <?php if ( $order ) : ?>

            <?php
            // Prepare Data for Facebook Pixel Purchase Event
            $order_total = $order->get_total();
            $order_currency = $order->get_currency();
            $product_ids = array();
            $product_names = array();
            foreach ( $order->get_items() as $item ) {
                $product_ids[] = $item->get_product_id();
                $product_names[] = $item->get_name();
            }
            ?>

            <div class="bg-white p-8 rounded-lg shadow-sm text-center mb-8 border-t-4 border-green-500">
                <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-green-100 mb-4">
                    <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                </div>
                <h1 class="text-3xl font-bold text-gray-800 mb-2">ধন্যবাদ! আপনার অর্ডারটি গৃহীত হয়েছে।</h1>
                <p class="text-gray-500">আমাদের প্রতিনিধি শীঘ্রই আপনাকে কল করে কনফার্ম করবেন।</p>
                
                <div class="mt-6 inline-block bg-gray-100 px-6 py-2 rounded-full text-sm font-semibold text-gray-700">
                    অর্ডার নাম্বার: #<?php echo $order->get_order_number(); ?>
                </div>
            </div>

            <div class="bg-white p-8 rounded-lg shadow-sm mb-12">
                <h3 class="text-xl font-bold text-gray-800 mb-6 border-b pb-2">অর্ডারের বিবরণ</h3>
                
                <ul class="space-y-4">
                    <?php foreach ( $order->get_items() as $item_id => $item ) : 
                        $product = $item->get_product();
                    ?>
                        <li class="flex justify-between items-center py-2 border-b border-gray-100 last:border-0">
                            <div class="flex items-center gap-4">
                                <div class="w-16 h-16 rounded overflow-hidden border">
                                    <?php echo $product->get_image('thumbnail'); ?>
                                </div>
                                <div>
                                    <div class="font-bold text-gray-800"><?php echo $item->get_name(); ?></div>
                                    <div class="text-sm text-gray-500">পরিমাণ: <?php echo $item->get_quantity(); ?></div>
                                </div>
                            </div>
                            <div class="font-bold text-gray-700">
                                <?php echo $order->get_formatted_line_subtotal( $item ); ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <div class="mt-6 pt-4 border-t border-gray-200 flex flex-col gap-2 items-end">
                    <div class="flex justify-between w-full md:w-1/2 text-sm text-gray-600">
                        <span>শিপিং চার্জ:</span>
                        <span><?php echo $order->get_shipping_total(); ?>৳</span>
                    </div>
                    <div class="flex justify-between w-full md:w-1/2 text-xl font-bold text-primary mt-2">
                        <span>সর্বমোট:</span>
                        <span><?php echo $order->get_formatted_order_total(); ?></span>
                    </div>
                </div>
            </div>

        <?php else : ?>
            <p class="text-center text-red-500">দুঃখিত, কোনো অর্ডার পাওয়া যায়নি।</p>
        <?php endif; ?>

        <div class="mt-16">
            <h2 class="text-2xl font-bold text-center text-gray-800 mb-8">
                আপনার জন্য আরও কিছু কালেকশন
            </h2>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
                <?php
                // র‍্যান্ডম ৪টি প্রডাক্ট দেখাবে
                $args = array(
                    'post_type'      => 'product',
                    'posts_per_page' => 4,
                    'orderby'        => 'rand', // Random Products
                    'post__not_in'   => array( $order ? $order->get_id() : 0 ), // কেনা প্রডাক্ট বাদ দিয়ে
                );
                $loop = new WP_Query( $args );

                if ( $loop->have_posts() ) {
                    while ( $loop->have_posts() ) : $loop->the_post();
                        wc_get_template_part( 'content', 'product' ); // আমাদের বানানো কার্ড ডিজাইন
                    endwhile;
                }
                wp_reset_postdata();
                ?>
            </div>
            
            <div class="text-center mt-10">
                <a href="<?php echo home_url('/shop'); ?>" class="inline-block bg-secondary text-white px-8 py-3 rounded-lg font-bold hover:bg-primary transition shadow-lg">
                    সব প্রডাক্ট দেখুন
                </a>
            </div>
        </div>

    </div>
</div>

<?php get_footer(); ?>