<?php
/**
 * Template Name: Track Order
 * Template Post Type: page
 * Theme: FMB E-Com Store
 */
get_header();
$store_phone = get_theme_mod('fmb_global_phone', '');

// Handle form submission
$order_id    = isset($_POST['track_order_id']) ? trim(sanitize_text_field($_POST['track_order_id'])) : '';
$order_email = isset($_POST['track_order_email']) ? trim(sanitize_email($_POST['track_order_email'])) : '';
$order_phone = isset($_POST['track_order_phone']) ? trim(sanitize_text_field($_POST['track_order_phone'])) : '';
$found_order = null;
$error_msg   = '';

if (!empty($order_id) && isset($_POST['fmb_track_nonce']) && wp_verify_nonce($_POST['fmb_track_nonce'], 'fmb_track_order')) {
    $order = wc_get_order((int) $order_id);
    if ($order) {
        // Verify by phone or email
        $billing_phone = $order->get_billing_phone();
        $billing_email = $order->get_billing_email();
        $phone_clean   = preg_replace('/[^0-9]/', '', $billing_phone);
        $input_phone   = preg_replace('/[^0-9]/', '', $order_phone);

        if (
            (!empty($order_phone) && str_ends_with($phone_clean, substr($input_phone, -10))) ||
            (!empty($order_email) && strtolower($billing_email) === strtolower($order_email))
        ) {
            $found_order = $order;
        } else {
            $error_msg = 'তথ্য মিলছে না। আপনার সঠিক ফোন নম্বর বা ইমেইল দিন।';
        }
    } else {
        $error_msg = 'এই অর্ডার নম্বরটি পাওয়া যায়নি।';
    }
}

// Status Map
$status_steps = array(
    'pending'    => array('label' => 'অর্ডার পেন্ডিং',   'icon' => '🕐', 'step' => 1),
    'processing' => array('label' => 'প্রক্রিয়াধীন',     'icon' => '📦', 'step' => 2),
    'on-hold'    => array('label' => 'হোল্ডে আছে',       'icon' => '⏸️', 'step' => 2),
    'shipped'    => array('label' => 'শিপমেন্ট হয়েছে',  'icon' => '🚚', 'step' => 3),
    'completed'  => array('label' => 'ডেলিভারি সম্পন্ন', 'icon' => '✅', 'step' => 4),
    'cancelled'  => array('label' => 'বাতিল করা হয়েছে',  'icon' => '❌', 'step' => 0),
    'refunded'   => array('label' => 'রিফান্ড হয়েছে',    'icon' => '💸', 'step' => 0),
    'failed'     => array('label' => 'ব্যর্থ হয়েছে',     'icon' => '⛔', 'step' => 0),
);
?>

<div class="bg-gray-50 min-h-screen py-12">
    <div class="max-w-2xl mx-auto px-4">

        <!-- Hero -->
        <div class="bg-gradient-to-r from-blue-600 to-blue-800 rounded-2xl p-8 mb-8 text-white text-center shadow-lg">
            <div class="inline-flex items-center justify-center w-16 h-16 bg-white/20 rounded-full mb-4">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
                </svg>
            </div>
            <h1 class="text-3xl md:text-4xl font-extrabold mb-2">অর্ডার ট্র্যাক করুন</h1>
            <p class="text-blue-100 text-sm">আপনার অর্ডার নম্বর ও ফোন নম্বর দিয়ে স্ট্যাটাস জানুন</p>
        </div>

        <!-- Track Form -->
        <?php if (!$found_order) : ?>
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 md:p-8 mb-6">
            <?php if ($error_msg) : ?>
                <div class="mb-5 p-4 bg-red-50 border border-red-200 rounded-xl flex items-center gap-3 text-red-700">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span class="font-medium"><?php echo esc_html($error_msg); ?></span>
                </div>
            <?php endif; ?>

            <form method="post" class="space-y-5">
                <?php wp_nonce_field('fmb_track_order', 'fmb_track_nonce'); ?>

                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1.5">অর্ডার নম্বর <span class="text-red-500">*</span></label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14"/></svg>
                        </span>
                        <input type="number" name="track_order_id" required
                               value="<?php echo esc_attr($order_id); ?>"
                               placeholder="উদা: 1234"
                               class="w-full pl-10 pr-4 py-3 border border-gray-200 rounded-xl bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1.5">ফোন নম্বর <span class="text-red-500">*</span></label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path d="M2 3a1 1 0 011-1h2.153a1 1 0 01.986.836l.74 4.435a1 1 0 01-.54 1.06l-1.548.773a11.037 11.037 0 006.105 6.105l.774-1.548a1 1 0 011.059-.54l4.435.74a1 1 0 01.836.986V17a1 1 0 01-1 1h-2C7.82 18 2 12.18 2 5V3z"/></svg>
                        </span>
                        <input type="tel" name="track_order_phone"
                               value="<?php echo esc_attr($order_phone); ?>"
                               placeholder="অর্ডারের সময় দেওয়া ফোন নম্বর"
                               class="w-full pl-10 pr-4 py-3 border border-gray-200 rounded-xl bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition">
                    </div>
                </div>

                <button type="submit"
                        class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3.5 rounded-xl transition shadow-md flex items-center justify-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    অর্ডার খুঁজুন
                </button>
            </form>
        </div>
        <?php endif; ?>

        <!-- Order Result -->
        <?php if ($found_order) :
            $status     = $found_order->get_status();
            $status_info = $status_steps[$status] ?? array('label' => ucfirst($status), 'icon' => '📋', 'step' => 1);
            $step        = $status_info['step'];
            $is_cancelled = in_array($status, ['cancelled', 'refunded', 'failed']);
        ?>
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden mb-6">

            <!-- Order Header -->
            <div class="p-5 bg-blue-50 border-b border-blue-100 flex justify-between items-center">
                <div>
                    <p class="text-sm text-gray-500">অর্ডার নম্বর</p>
                    <p class="text-xl font-extrabold text-gray-800">#<?php echo $found_order->get_id(); ?></p>
                </div>
                <span class="px-4 py-1.5 rounded-full text-sm font-bold
                    <?php echo $is_cancelled ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700'; ?>">
                    <?php echo $status_info['icon'] . ' ' . esc_html($status_info['label']); ?>
                </span>
            </div>

            <!-- Progress Steps -->
            <?php if (!$is_cancelled) : ?>
            <div class="p-6 border-b border-gray-100">
                <div class="flex items-center justify-between relative">
                    <?php
                    $steps_list = array(
                        array('label' => 'অর্ডার হয়েছে', 'icon' => '📋'),
                        array('label' => 'প্রক্রিয়াধীন',  'icon' => '📦'),
                        array('label' => 'শিপমেন্ট',      'icon' => '🚚'),
                        array('label' => 'ডেলিভারি',      'icon' => '✅'),
                    );
                    $total = count($steps_list);
                    foreach ($steps_list as $i => $st) :
                        $stepNum  = $i + 1;
                        $done     = $step >= $stepNum;
                        $current  = $step === $stepNum;
                    ?>
                    <div class="flex flex-col items-center flex-1 relative z-10">
                        <div class="w-10 h-10 rounded-full flex items-center justify-center text-lg font-bold border-2 transition
                            <?php echo $done ? 'bg-blue-600 border-blue-600 text-white' : 'bg-white border-gray-300 text-gray-400'; ?>
                            <?php echo $current ? 'ring-4 ring-blue-100' : ''; ?>">
                            <?php echo $done ? ($stepNum < $step ? '✓' : $st['icon']) : $stepNum; ?>
                        </div>
                        <p class="text-[11px] mt-2 font-medium text-center <?php echo $done ? 'text-blue-600' : 'text-gray-400'; ?>">
                            <?php echo $st['label']; ?>
                        </p>
                    </div>
                    <?php if ($i < $total - 1) : ?>
                    <div class="absolute top-5 left-0 right-0 flex items-center px-5 z-0" style="left:<?php echo (100/$total/2); ?>%; right:<?php echo (100/$total/2); ?>%">
                        <div class="h-0.5 w-full <?php echo $step > $i + 1 ? 'bg-blue-500' : 'bg-gray-200'; ?>"></div>
                    </div>
                    <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Order Details -->
            <div class="p-6 space-y-4">
                <h3 class="font-bold text-gray-700 text-sm uppercase tracking-wide">অর্ডার তথ্য</h3>
                <div class="space-y-3">
                    <?php foreach ($found_order->get_items() as $item) : ?>
                    <div class="flex justify-between items-center py-2 border-b border-gray-100 last:border-0">
                        <span class="text-gray-700 text-sm"><?php echo esc_html($item->get_name()); ?> &times; <?php echo $item->get_quantity(); ?></span>
                        <span class="font-bold text-gray-800"><?php echo wc_price($item->get_total()); ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="pt-3 border-t-2 border-dashed border-gray-200 space-y-1">
                    <div class="flex justify-between text-sm text-gray-600">
                        <span>ডেলিভারি চার্জ</span>
                        <span><?php echo wc_price($found_order->get_shipping_total()); ?></span>
                    </div>
                    <div class="flex justify-between font-extrabold text-gray-900 text-base">
                        <span>সর্বমোট</span>
                        <span class="text-blue-600"><?php echo wc_price($found_order->get_total()); ?></span>
                    </div>
                </div>
            </div>

            <!-- Billing -->
            <div class="px-6 py-4 bg-gray-50 border-t border-gray-100 text-sm text-gray-600 flex flex-wrap gap-4">
                <div><span class="font-semibold">নাম:</span> <?php echo esc_html($found_order->get_billing_first_name()); ?></div>
                <div><span class="font-semibold">ফোন:</span> <?php echo esc_html($found_order->get_billing_phone()); ?></div>
                <div><span class="font-semibold">ঠিকানা:</span> <?php echo esc_html($found_order->get_billing_address_1()); ?></div>
            </div>

            <!-- Track Again -->
            <div class="p-5 text-center border-t border-gray-100">
                <a href="" class="text-blue-600 hover:underline text-sm font-medium">← অন্য অর্ডার খুঁজুন</a>
            </div>
        </div>

        <?php elseif ($order_id && !$error_msg) : ?>
        <!-- Logged-in user my-account fallback already handled above -->
        <?php endif; ?>

        <!-- Help Box -->
        <?php if ($store_phone) : ?>
        <div class="bg-blue-50 rounded-2xl border border-blue-100 p-5 flex items-center gap-4">
            <div class="w-12 h-12 bg-blue-600 rounded-xl flex items-center justify-center flex-shrink-0">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
            </div>
            <div class="flex-grow">
                <p class="font-bold text-gray-800">সাহায্য দরকার?</p>
                <p class="text-gray-600 text-sm">অর্ডার সম্পর্কে জানতে আমাদের কল করুন</p>
            </div>
            <a href="tel:<?php echo esc_attr($store_phone); ?>"
               class="flex-shrink-0 bg-blue-600 text-white font-bold text-sm px-4 py-2 rounded-xl hover:bg-blue-700 transition">
                কল করুন
            </a>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php get_footer(); ?>
