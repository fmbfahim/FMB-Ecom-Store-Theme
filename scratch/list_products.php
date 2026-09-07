<?php
define('WP_USE_THEMES', false);
require_once 'c:/xampp/htdocs/fmbecstore/wp-load.php';

$posts = get_posts(array('post_type' => 'product', 'posts_per_page' => -1));
foreach ($posts as $p) {
    $variant = get_post_meta($p->ID, '_fmb_single_template_variant', true);
    echo $p->ID . ' | ' . $p->post_title . ' | variant: ' . ($variant ?: 'default/classic') . "\n";
}
