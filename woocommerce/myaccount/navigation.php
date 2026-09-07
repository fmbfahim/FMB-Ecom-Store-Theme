<?php
defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_before_account_navigation' );
?>

<nav class="woocommerce-MyAccount-navigation bg-white rounded-lg shadow-sm border border-gray-100 overflow-hidden">
    <ul class="flex flex-col">
        <?php foreach ( wc_get_account_menu_items() as $endpoint => $label ) : ?>
            <li class="<?php echo wc_get_account_menu_item_classes( $endpoint ); ?> border-b border-gray-50 last:border-0">
                <a href="<?php echo esc_url( wc_get_account_endpoint_url( $endpoint ) ); ?>" 
                   class="block px-6 py-4 text-gray-600 hover:bg-gray-50 hover:text-primary transition font-medium flex justify-between items-center group">
                    <?php echo esc_html( $label ); ?>
                    <span class="opacity-0 group-hover:opacity-100 transition">→</span>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
</nav>

<?php do_action( 'woocommerce_after_account_navigation' ); ?>