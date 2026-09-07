<?php
$store_phone = get_theme_mod('fmb_global_phone', '');
$show_topbar = get_theme_mod('fmb_show_topbar', true);
$topbar_text = get_theme_mod('fmb_topbar_text', 'Call for Order: +8801700000000'); 
$topbar_h    = ($show_topbar && $topbar_text) ? 40 : 0;
$fmb_logo_url  = function_exists('fmb_get_site_logo_url') ? fmb_get_site_logo_url() : get_theme_mod('fmb_logo', '');
$fmb_site_name = get_bloginfo('name');
if ( empty($fmb_site_name) ) {
    $fmb_site_name = 'FMB Store';
}
?>

<!-- =============================================
     ALPINE WRAPPER — shares state across nav,
     backdrop, drawer & search modal
     ============================================= -->
<div x-data="{ mobileMenuOpen: false, searchOpen: false }">

    <!-- ── HEADER WRAPPER ───────────────────────── -->
    <header id="fmb-main-header"
            class="fixed left-0 right-0 top-0 z-[9980] transition-transform duration-300 bg-white">
        
        <?php get_template_part('template-parts/header/topbar'); ?>

        <nav id="fmb-navbar" class="bg-white shadow-sm border-b border-gray-100">

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-14 sm:h-16 md:h-20 items-center">

                <!-- Logo & Site Title -->
                <div class="flex-shrink-0 flex items-center">
                    <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="flex items-center gap-2 group py-1">
                        <?php if ( ! empty( $fmb_logo_url ) ) : ?>
                            <img style="width: var(--logo-w); max-height: 52px; height: auto;"
                                 class="object-contain transition duration-200 group-hover:opacity-95"
                                 src="<?php echo esc_url( $fmb_logo_url ); ?>"
                                 alt="<?php echo esc_attr( $fmb_site_name ); ?>">
                        <?php else : ?>
                            <span class="fmb-site-brand-text text-xl sm:text-2xl md:text-2xl font-black text-gray-900 tracking-tight transition-colors group-hover:text-primary">
                                <?php echo esc_html( $fmb_site_name ); ?>
                            </span>
                        <?php endif; ?>
                    </a>
                </div>

                <!-- Desktop Visible Real-Time Search Bar -->
                <div class="fmb-search-wrapper flex-1 max-w-md mx-6 relative hidden md:block z-30">
                    <form role="search" method="get" action="<?php echo home_url('/'); ?>" class="relative">
                        <input type="search" name="s" autocomplete="off"
                               placeholder="পণ্য খুঁজুন (Real-time live search)..."
                               value="<?php echo get_search_query(); ?>"
                               class="fmb-live-search-input w-full pl-4 pr-10 py-2 border border-gray-200 rounded-full bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary text-sm transition">
                        <input type="hidden" name="post_type" value="product">
                        <button type="submit" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-primary transition">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        </button>
                    </form>
                    <!-- Live Search Dropdown -->
                    <div class="fmb-live-search-results absolute left-0 right-0 top-full mt-2 bg-white rounded-xl shadow-2xl border border-gray-100 hidden z-50 max-h-96 overflow-y-auto"></div>
                </div>

                <!-- Desktop Nav Menu -->
                <div class="hidden lg:block">
                    <?php
                    wp_nav_menu(array(
                        'theme_location' => 'primary',
                        'container'      => false,
                        'menu_class'     => 'flex items-center space-x-6 fmb-desktop-menu',
                        'fallback_cb'    => false,
                    ));
                    ?>
                    <?php if (!has_nav_menu('primary')) : ?>
                        <a href="<?php echo admin_url('nav-menus.php'); ?>" class="text-red-500 text-sm">Please Assign a Menu</a>
                    <?php endif; ?>
                </div>

                <!-- Desktop Right Icons -->
                <div class="hidden md:flex items-center space-x-3">

                    <?php if (function_exists('WC') && null !== WC()->cart) : ?>
                        <?php $cart_count = WC()->cart->get_cart_contents_count(); ?>
                        <a href="<?php echo wc_get_cart_url(); ?>" class="relative text-gray-500 hover:text-primary transition p-2 rounded-lg hover:bg-gray-50">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                            <?php if ($cart_count > 0) : ?>
                                <span class="absolute top-1 right-1 flex h-4 w-4 items-center justify-center rounded-full bg-primary text-[10px] font-bold text-white">
                                    <?php echo $cart_count; ?>
                                </span>
                            <?php endif; ?>
                        </a>
                    <?php endif; ?>

                    <?php if ($store_phone) : ?>
                        <a href="tel:<?php echo esc_attr($store_phone); ?>"
                           class="hidden lg:flex items-center gap-2 bg-primary text-white text-sm font-semibold px-4 py-2 rounded-lg hover:bg-opacity-90 transition">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                            <?php echo esc_html($store_phone); ?>
                        </a>
                    <?php endif; ?>
                </div>

                <!-- Mobile: Search + Cart + Hamburger -->
                <div class="md:hidden flex items-center gap-1">
                    <button @click="searchOpen = true; $nextTick(() => $refs.searchInput.focus())"
                            class="text-gray-600 hover:text-primary p-2 rounded-lg transition"
                            aria-label="Search">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </button>
                    <?php if (function_exists('WC') && null !== WC()->cart) : ?>
                        <?php $mobile_cart_count = WC()->cart->get_cart_contents_count(); ?>
                        <a href="<?php echo wc_get_cart_url(); ?>" class="relative text-gray-500 p-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                            <?php if ($mobile_cart_count > 0) : ?>
                                <span class="absolute top-1 right-1 flex h-4 w-4 items-center justify-center rounded-full bg-primary text-[10px] font-bold text-white">
                                    <?php echo $mobile_cart_count; ?>
                                </span>
                            <?php endif; ?>
                        </a>
                    <?php endif; ?>

                    <button @click="mobileMenuOpen = true"
                            class="text-gray-600 hover:text-primary p-2 rounded-lg transition"
                            aria-label="Open Menu">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                        </svg>
                    </button>
                </div>

            </div>
        </div>
    </nav>
</header>

    <!-- Spacer so content isn't hidden under fixed nav (All Devices) -->
    <div id="fmb-header-spacer" class="fmb-nav-spacer block"></div>

    <!-- ── MOBILE BACKDROP (outside nav!) ───────── -->
    <div x-show="mobileMenuOpen"
         x-transition:enter="transition-opacity ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition-opacity ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         @click="mobileMenuOpen = false"
         class="fixed inset-0 bg-black/60 backdrop-blur-sm z-[9985] md:hidden"
         style="display:none;"
         aria-hidden="true"></div>

    <!-- ── MOBILE SIDE DRAWER (outside nav!) ─────── -->
    <div x-show="mobileMenuOpen"
         x-transition:enter="transition ease-out duration-300 transform"
         x-transition:enter-start="-translate-x-full"
         x-transition:enter-end="translate-x-0"
         x-transition:leave="transition ease-in duration-250 transform"
         x-transition:leave-start="translate-x-0"
         x-transition:leave-end="-translate-x-full"
         class="fixed top-0 left-0 bottom-0 w-[285px] bg-white z-[9986] flex flex-col shadow-2xl md:hidden"
         style="display:none;">

        <!-- Drawer Header -->
        <div class="flex items-center justify-between px-5 py-4 bg-gradient-to-r from-gray-50 to-white border-b border-gray-100">
            <a href="<?php echo esc_url( home_url( '/' ) ); ?>" @click="mobileMenuOpen = false" class="flex items-center gap-2 group">
                <?php if ( ! empty( $fmb_logo_url ) ) : ?>
                    <img src="<?php echo esc_url( $fmb_logo_url ); ?>"
                         alt="<?php echo esc_attr( $fmb_site_name ); ?>"
                         class="h-8 w-auto object-contain">
                <?php else : ?>
                    <span class="fmb-site-brand-text text-lg font-black text-gray-900 tracking-tight group-hover:text-primary transition-colors">
                        <?php echo esc_html( $fmb_site_name ); ?>
                    </span>
                <?php endif; ?>
            </a>
            <button @click="mobileMenuOpen = false"
                    class="p-1.5 rounded-lg text-gray-400 hover:text-red-500 hover:bg-red-50 transition"
                    aria-label="Close Menu">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <!-- Search Bar -->
        <div class="px-4 py-3 border-b border-gray-100">
            <form role="search" method="get" action="<?php echo home_url('/'); ?>" class="relative">
                <input type="search" name="s"
                       placeholder="পণ্য খুঁজুন..."
                       value="<?php echo get_search_query(); ?>"
                       class="w-full pl-4 pr-10 py-2.5 text-sm border border-gray-200 rounded-xl bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition">
                <input type="hidden" name="post_type" value="product">
                <button type="submit" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-primary transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </button>
            </form>
        </div>

        <!-- Nav Links (scrollable) -->
        <div class="flex-grow overflow-y-auto px-3 py-3">
            <?php
            wp_nav_menu(array(
                'theme_location' => 'primary',
                'container'      => false,
                'menu_class'     => 'fmb-mobile-drawer-menu',
                'fallback_cb'    => false,
                'walker'         => null,
            ));
            ?>
        </div>

        <!-- Drawer Footer -->
        <div class="px-4 py-4 border-t border-gray-100 bg-gray-50 space-y-3">
            <?php if ($store_phone) : ?>
                <a href="tel:<?php echo esc_attr($store_phone); ?>"
                   class="flex items-center justify-center gap-2 w-full bg-primary text-white text-sm font-bold py-3 rounded-xl hover:bg-opacity-90 transition shadow-md">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                    <?php echo esc_html($store_phone); ?>
                </a>
            <?php endif; ?>
            <?php if (class_exists('WooCommerce') && !empty(WC()->cart)) : ?>
                <?php $drawer_cart_count = WC()->cart->get_cart_contents_count(); ?>
                <a href="<?php echo wc_get_cart_url(); ?>"
                   class="flex items-center justify-center gap-2 w-full border border-gray-200 text-gray-700 text-sm font-semibold py-3 rounded-xl hover:border-primary hover:text-primary transition bg-white">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                    কার্ট দেখুন
                    <?php if ($drawer_cart_count > 0) : ?>
                        <span class="bg-primary text-white text-[10px] font-bold px-1.5 py-0.5 rounded-full">
                            <?php echo $drawer_cart_count; ?>
                        </span>
                    <?php endif; ?>
                </a>
            <?php endif; ?>
        </div>

    </div><!-- /.side-drawer -->

    <!-- ── DESKTOP SEARCH MODAL (outside nav!) ─── -->
    <div x-show="searchOpen"
         style="display:none;"
         class="fixed inset-0 z-[9999]"
         role="dialog" aria-modal="true">

        <!-- Backdrop -->
        <div x-show="searchOpen"
             x-transition:enter="ease-out duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="ease-in duration-150"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             @click="searchOpen = false"
             class="fixed inset-0 bg-gray-900/70 backdrop-blur-sm"></div>

        <!-- Panel -->
        <div class="relative flex items-start justify-center px-4 pt-24">
            <div x-show="searchOpen"
                 x-transition:enter="ease-out duration-200"
                 x-transition:enter-start="opacity-0 scale-95"
                 x-transition:enter-end="opacity-100 scale-100"
                 x-transition:leave="ease-in duration-150"
                 x-transition:leave-start="opacity-100 scale-100"
                 x-transition:leave-end="opacity-0 scale-95"
                 class="bg-white rounded-2xl shadow-2xl w-full max-w-xl overflow-hidden">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-bold text-gray-900">পণ্য খুঁজুন</h3>
                        <button @click="searchOpen = false" class="p-1.5 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                    <form role="search" method="get" class="relative" action="<?php echo home_url('/'); ?>">
                        <input type="search"
                               class="w-full pl-5 pr-14 py-4 border-2 border-gray-200 rounded-xl text-base focus:outline-none focus:border-primary transition"
                               placeholder="পণ্যের নাম লিখুন..."
                               value="<?php echo get_search_query(); ?>"
                               name="s"
                               x-ref="searchInput"
                               autofocus>
                        <button type="submit" class="absolute right-3 top-1/2 -translate-y-1/2 bg-primary text-white p-2.5 rounded-lg hover:bg-opacity-90 transition">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        </button>
                        <input type="hidden" name="post_type" value="product">
                    </form>
                </div>
            </div>
        </div>
    </div>

</div><!-- /x-data wrapper -->

<!-- ── STYLES ──────────────────────────────────── -->
<style>
/* Spacer sizing - Matches exact fixed header height across devices */
.fmb-nav-spacer { height: 56px; }
@media (min-width: 640px) { .fmb-nav-spacer { height: 64px; } }
@media (min-width: 768px) { .fmb-nav-spacer { height: <?php echo ($show_topbar && $topbar_text ? 36 : 0) + 80; ?>px; } }

/* Desktop menu */
.fmb-desktop-menu { list-style: none; margin: 0; padding: 0; }
.fmb-desktop-menu li a {
    text-decoration: none;
    color: #374151;
    font-weight: 500;
    font-size: 15px;
    transition: color 0.2s;
    position: relative;
    padding-bottom: 4px;
}
.fmb-desktop-menu li a::after {
    content: '';
    position: absolute;
    bottom: 0; left: 0;
    width: 0; height: 2px;
    background: var(--primary);
    transition: width 0.25s ease;
    border-radius: 2px;
}
.fmb-desktop-menu li a:hover { color: var(--primary); }
.fmb-desktop-menu li a:hover::after { width: 100%; }
.fmb-desktop-menu li.current-menu-item > a { color: var(--primary); font-weight: 600; }
.fmb-desktop-menu li.current-menu-item > a::after { width: 100%; }

/* Mobile drawer menu */
.fmb-mobile-drawer-menu { list-style: none; margin: 0; padding: 0; }
.fmb-mobile-drawer-menu li { margin: 2px 0; }
.fmb-mobile-drawer-menu li a {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 11px 14px;
    color: #374151;
    font-weight: 500;
    font-size: 15px;
    text-decoration: none;
    border-radius: 10px;
    transition: background 0.15s, color 0.15s;
}
.fmb-mobile-drawer-menu li a::before {
    content: '';
    width: 6px; height: 6px;
    border-radius: 50%;
    background: #d1d5db;
    flex-shrink: 0;
    transition: background 0.15s;
}
.fmb-mobile-drawer-menu li a:hover { background: #f0f9ff; color: var(--primary); }
.fmb-mobile-drawer-menu li a:hover::before { background: var(--primary); }
.fmb-mobile-drawer-menu li.current-menu-item > a { background: #f0f9ff; color: var(--primary); font-weight: 600; }
.fmb-mobile-drawer-menu li.current-menu-item > a::before { background: var(--primary); }
</style>

<!-- ── SCROLL HIDE / SHOW SCRIPT ────────────────── -->
<script>
(function () {
    var header    = document.getElementById('fmb-main-header');
    if (!header) return;

    var topbarH   = <?php echo (int) $topbar_h; ?>;
    var lastY     = 0;
    var ticking   = false;

    function update() {
        var y = window.scrollY;

        // On mobile devices, keep header always visible and add subtle shadow on scroll
        if (window.innerWidth < 768) {
            header.style.transform = 'translateY(0)';
            if (y > 10) {
                header.style.boxShadow = '0 4px 15px rgba(0,0,0,0.1)';
            } else {
                header.style.boxShadow = '';
            }
            lastY = y;
            ticking = false;
            return;
        }

        if (y <= topbarH + 5) {
            // Near top — restore full position
            header.style.transform = 'translateY(0)';
            header.style.boxShadow = '';
        } else if (y > lastY + 5) {
            // Scrolling DOWN — hide header
            header.style.transform = 'translateY(-110%)';
        } else if (y < lastY - 3) {
            // Scrolling UP — show navbar
            header.style.transform = 'translateY(0)';
            header.style.boxShadow = '0 4px 20px rgba(0,0,0,0.08)';
        }

        lastY   = y;
        ticking = false;
    }

    window.addEventListener('scroll', function () {
        if (!ticking) {
            requestAnimationFrame(update);
            ticking = true;
        }
    }, { passive: true });
})();
</script>

<!-- Real-Time Live Search AJAX Script -->
<script>
(function($) {
    $(document).ready(function() {
        var timer = null;
        $(document).on('input', '.fmb-live-search-input', function() {
            var $input = $(this);
            var term = $input.val().trim();
            var $wrapper = $input.closest('.fmb-search-wrapper');
            var $results = $wrapper.find('.fmb-live-search-results');

            if ($results.length === 0) {
                $results = $('<div class="fmb-live-search-results absolute left-0 right-0 top-full mt-2 bg-white rounded-xl shadow-2xl border border-gray-100 hidden z-50 max-h-96 overflow-y-auto"></div>').appendTo($wrapper);
            }

            clearTimeout(timer);
            if (term.length < 2) {
                $results.hide().empty();
                return;
            }

            timer = setTimeout(function() {
                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    data: { action: 'fmb_live_search', term: term },
                    dataType: 'json',
                    success: function(res) {
                        if (res.success && res.data.products && res.data.products.length > 0) {
                            var html = '<div class="p-2 space-y-1">';
                            res.data.products.forEach(function(item) {
                                html += '<a href="' + item.url + '" class="flex items-center gap-3 p-2 hover:bg-blue-50 rounded-lg transition group border-b border-gray-50 last:border-0">';
                                html += '<img src="' + item.thumbnail + '" class="w-11 h-11 object-cover rounded border shrink-0">';
                                html += '<div class="flex-grow min-w-0"><div class="text-sm font-bold text-gray-800 truncate group-hover:text-primary">' + item.title + '</div>';
                                html += '<div class="text-xs text-blue-600 font-bold mt-0.5">' + (item.price_html || item.price + '৳') + '</div></div>';
                                html += '</a>';
                            });
                            html += '</div>';
                            $results.html(html).show();
                        } else {
                            $results.html('<div class="p-4 text-center text-sm text-gray-500">কোনো প্রডাক্ট পাওয়া যায়নি</div>').show();
                        }
                    }
                });
            }, 300);
        });

        $(document).on('click', function(e) {
            if (!$(e.target).closest('.fmb-search-wrapper').length) {
                $('.fmb-live-search-results').hide();
            }
        });
    });
})(jQuery);
</script>