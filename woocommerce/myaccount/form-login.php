<?php
/**
 * Login Form
 * Theme: FMB E-Com Store
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; 
}

do_action( 'woocommerce_before_customer_login_form' ); 
?>

<div class="max-w-6xl mx-auto py-16 px-4" id="customer_login">
    
    <div class="grid grid-cols-1 md:grid-cols-2 gap-12 bg-white shadow-xl rounded-2xl overflow-hidden border border-gray-100">
        
        <div class="p-8 md:p-12">
            <h2 class="text-3xl font-bold text-gray-800 mb-6">লগইন করুন</h2>
            
            <form class="woocommerce-form woocommerce-form-login login space-y-5" method="post">
                <?php do_action( 'woocommerce_login_form_start' ); ?>

                <div>
                    <label class="block text-gray-700 font-bold mb-2">ইউজারনেম বা ইমেইল <span class="required">*</span></label>
                    <input type="text" class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-primary focus:outline-none transition" name="username" id="username" autocomplete="username" value="<?php echo ( ! empty( $_POST['username'] ) ) ? esc_attr( wp_unslash( $_POST['username'] ) ) : ''; ?>" />
                </div>

                <div>
                    <label class="block text-gray-700 font-bold mb-2">পাসওয়ার্ড <span class="required">*</span></label>
                    <input class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-primary focus:outline-none transition" type="password" name="password" id="password" autocomplete="current-password" />
                </div>

                <?php do_action( 'woocommerce_login_form' ); ?>

                <div class="flex items-center justify-between">
                    <label class="woocommerce-form__label woocommerce-form__label-for-checkbox inline-flex items-center cursor-pointer">
                        <input class="woocommerce-form__input woocommerce-form__input-checkbox h-4 w-4 text-primary border-gray-300 rounded" name="rememberme" type="checkbox" id="rememberme" value="forever" /> 
                        <span class="ml-2 text-gray-600 text-sm">মনে রাখুন</span>
                    </label>
                    
                    <a href="<?php echo esc_url( wp_lostpassword_url() ); ?>" class="text-sm text-primary hover:underline">পাসওয়ার্ড ভুলে গেছেন?</a>
                </div>

                <button type="submit" class="w-full bg-primary text-white font-bold py-3 rounded-lg hover:bg-dark transition shadow-lg" name="login" value="<?php esc_attr_e( 'Log in', 'woocommerce' ); ?>">লগইন করুন</button>

                <?php wp_nonce_field( 'woocommerce-login', 'woocommerce-login-nonce' ); ?>
                <?php do_action( 'woocommerce_login_form_end' ); ?>
            </form>
        </div>

        <?php if ( 'yes' === get_option( 'woocommerce_enable_myaccount_registration' ) ) : ?>

        <div class="p-8 md:p-12 bg-gray-50 border-l border-gray-100">
            <h2 class="text-3xl font-bold text-gray-800 mb-6">নতুন একাউন্ট খুলুন</h2>

            <form method="post" class="woocommerce-form woocommerce-form-register register space-y-5" <?php do_action( 'woocommerce_register_form_tag' ); ?> >
                <?php do_action( 'woocommerce_register_form_start' ); ?>

                <?php if ( 'no' === get_option( 'woocommerce_registration_generate_username' ) ) : ?>
                    <div>
                        <label class="block text-gray-700 font-bold mb-2">ইউজারনেম <span class="required">*</span></label>
                        <input type="text" class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-primary focus:outline-none transition" name="username" id="reg_username" autocomplete="username" value="<?php echo ( ! empty( $_POST['username'] ) ) ? esc_attr( wp_unslash( $_POST['username'] ) ) : ''; ?>" />
                    </div>
                <?php endif; ?>

                <div>
                    <label class="block text-gray-700 font-bold mb-2">ইমেইল এড্রেস <span class="required">*</span></label>
                    <input type="email" class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-primary focus:outline-none transition" name="email" id="reg_email" autocomplete="email" value="<?php echo ( ! empty( $_POST['email'] ) ) ? esc_attr( wp_unslash( $_POST['email'] ) ) : ''; ?>" />
                </div>

                <?php if ( 'no' === get_option( 'woocommerce_registration_generate_password' ) ) : ?>
                    <div>
                        <label class="block text-gray-700 font-bold mb-2">পাসওয়ার্ড <span class="required">*</span></label>
                        <input type="password" class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-primary focus:outline-none transition" name="password" id="reg_password" autocomplete="new-password" />
                    </div>
                <?php endif; ?>

                <?php do_action( 'woocommerce_register_form' ); ?>

                <button type="submit" class="w-full bg-secondary text-white font-bold py-3 rounded-lg hover:bg-dark transition shadow-lg" name="register" value="<?php esc_attr_e( 'Register', 'woocommerce' ); ?>">রেজিস্টার করুন</button>

                <p class="text-xs text-gray-500 mt-4 text-center">
                    আপনার ব্যক্তিগত তথ্য আমাদের প্রাইভেসি পলিসি অনুযায়ী সংরক্ষিত থাকবে।
                </p>

                <?php do_action( 'woocommerce_register_form_end' ); ?>
            </form>
        </div>

        <?php endif; ?>

    </div>
</div>

<?php do_action( 'woocommerce_after_customer_login_form' ); ?>