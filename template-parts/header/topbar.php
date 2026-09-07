<?php 
// কাস্টমাইজার থেকে ডাটা আনা
$show_topbar = get_theme_mod('fmb_show_topbar', true);
$topbar_text = get_theme_mod('fmb_topbar_text', 'Call for Order: +8801700000000'); 
?>

<?php if ($show_topbar && $topbar_text) : ?>
    <div class="hidden md:block bg-primary text-white text-xs font-medium py-2.5 text-center tracking-wide">
        <div class="container mx-auto px-4">
            <?php echo esc_html($topbar_text); ?>
        </div>
    </div>
<?php endif; ?>