<?php
defined( 'ABSPATH' ) || exit;
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 min-h-screen">
    
    <div class="mb-8 border-b pb-4">
        <h1 class="text-3xl font-bold text-gray-800">My Account</h1>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
        
        <div class="col-span-1">
            <?php
            /**
             * My Account navigation.
             * @since 2.6.0
             */
            do_action( 'woocommerce_account_navigation' );
            ?>
        </div>

        <div class="col-span-1 md:col-span-3 bg-white p-6 rounded-lg shadow-sm border border-gray-100">
            <?php
            /**
             * My Account content.
             * @since 2.6.0
             */
            do_action( 'woocommerce_account_content' );
            ?>
        </div>
        
    </div>
</div>