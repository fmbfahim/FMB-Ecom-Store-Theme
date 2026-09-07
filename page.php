<?php
/**
 * The template for displaying all pages
 * Theme: FMB E-Com Store
 */

get_header(); 

// Logic to determine if Full Width layout is needed
$is_full_width = false;
if (have_posts()) {
    while (have_posts()) {
        the_post();
        if (class_exists('\Elementor\Plugin') && \Elementor\Plugin::$instance->db->is_built_with_elementor(get_the_ID())) {
            $is_full_width = true;
        }
        $is_wc_page = ( ( function_exists( 'is_checkout' ) && is_checkout() ) || ( function_exists( 'is_cart' ) && is_cart() ) || ( function_exists( 'is_account_page' ) && is_account_page() ) );
        if ( get_post_type() == 'cartflows_step' || $is_wc_page || is_front_page() || is_home() ) {
            $is_full_width = true;
        }
        $template_slug = get_page_template_slug();
        if (!empty($template_slug)) {
            $is_full_width = true;
        }
        rewind_posts(); 
        break; 
    }
}
?>

<main class="<?php echo $is_full_width ? 'w-full' : 'w-full py-10 bg-[#F9FAFB] min-h-screen'; ?>">
    <div class="<?php echo $is_full_width ? 'w-full' : 'max-w-7xl mx-auto px-4 sm:px-6 lg:px-8'; ?>">
        
        <?php
        while ( have_posts() ) :
            the_post();

            if ($is_full_width) :
                ?>
                <div class="w-full">
                    <?php the_content(); ?>
                </div>
                <?php
            else :
                ?>
                <div class="bg-white p-6 md:p-10 rounded-xl shadow-sm border border-gray-100">
                    <?php if ( ! ( function_exists('is_account_page') && is_account_page() ) ) : ?>
                        <h1 class="text-3xl font-bold text-gray-800 mb-6 border-b pb-4"><?php the_title(); ?></h1>
                    <?php endif; ?>
                    <div class="prose max-w-none text-gray-700">
                        <?php the_content(); ?>
                    </div>
                </div>
            <?php 
            endif;

        endwhile; 
        ?> 
        
    </div>
</main>

<?php 
get_footer(); 
?>