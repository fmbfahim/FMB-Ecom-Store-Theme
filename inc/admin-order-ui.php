<?php
// Custom Mobile-Friendly UI for WooCommerce Order Edit Page

add_action('admin_enqueue_scripts', 'fmb_custom_admin_order_ui');
function fmb_custom_admin_order_ui($hook) {
    global $post, $current_screen;
    
    // Check if we are on the Order Edit page (Classic or HPOS)
    $is_order_page = false;
    
    // Classic WooCommerce Order Page
    if (in_array($hook, ['post.php', 'post-new.php'])) {
        $post_type = isset($_GET['post_type']) ? $_GET['post_type'] : '';
        if (empty($post_type) && isset($_GET['post'])) {
            $post_type = get_post_type($_GET['post']);
        }
        if ($post_type == 'shop_order') {
            $is_order_page = true;
        }
    }
    // WooCommerce HPOS Order Page
    if ($hook == 'woocommerce_page_wc-orders' && isset($_GET['action']) && $_GET['action'] == 'edit') {
        $is_order_page = true;
    }

    if ($is_order_page) {
        $css = "
        <style>
            /* Global Order Page Resets for Mobile */
            @media screen and (max-width: 782px) {
                #post-body-content, #postbox-container-1, #postbox-container-2 {
                    width: 100% !important;
                    float: none !important;
                }
                
                /* Make the main grid a stacked flexbox */
                #post-body.columns-2 {
                    display: flex;
                    flex-direction: column;
                }
                
                /* Re-order elements: Order details first, Actions next, Items below */
                #postbox-container-1 {
                    order: 2;
                }
                #postbox-container-2 {
                    order: 1;
                }
                
                /* Order Details Header Box */
                .woocommerce-order-data__heading {
                    font-size: 18px !important;
                    font-weight: bold !important;
                    padding-bottom: 10px;
                    border-bottom: 1px solid #eee;
                    margin-bottom: 15px;
                }
                
                /* Make the 3 columns in order data stacked */
                .woocommerce-order-data__meta.order_data_column_container {
                    display: flex;
                    flex-direction: column;
                    gap: 20px;
                }
                .order_data_column {
                    width: 100% !important;
                    float: none !important;
                    padding: 0 !important;
                    border: 1px solid #e5e7eb;
                    border-radius: 8px;
                    padding: 15px !important;
                    box-sizing: border-box;
                    background: #f9fafb;
                }
                
                /* Inputs and Selects Mobile Friendly */
                .order_data_column input, 
                .order_data_column select, 
                .order_data_column textarea {
                    width: 100% !important;
                    height: 45px !important;
                    font-size: 16px !important; /* Prevents iOS zoom */
                    border-radius: 6px;
                }
                
                /* Make buttons large and touch-friendly */
                .button, .save_order {
                    height: 50px !important;
                    line-height: 48px !important;
                    font-size: 16px !important;
                    border-radius: 8px !important;
                    padding: 0 20px !important;
                    display: block !important;
                    width: 100% !important;
                    text-align: center !important;
                    margin-bottom: 10px !important;
                }
                
                /* Update Button specifically */
                .save_order {
                    background: #2271b1 !important;
                    border-color: #2271b1 !important;
                    color: white !important;
                    font-weight: bold;
                }
                
                /* Order Items Table Mobile Scroll */
                .woocommerce_order_items_wrapper {
                    overflow-x: auto;
                    -webkit-overflow-scrolling: touch;
                    border: 1px solid #e5e7eb;
                    border-radius: 8px;
                }
                
                table.woocommerce_order_items th, 
                table.woocommerce_order_items td {
                    padding: 12px 10px !important;
                }
                
                /* Add some breathing room */
                .postbox {
                    border-radius: 10px !important;
                    box-shadow: 0 2px 5px rgba(0,0,0,0.05) !important;
                    margin-bottom: 20px !important;
                    border: 1px solid #e2e8f0;
                }
                
                .hndle {
                    font-size: 16px !important;
                    padding: 15px !important;
                    border-bottom: 1px solid #eee;
                }
                
                /* Hide unnecessary WooCommerce clutter */
                #woocommerce-order-downloads,
                #postcustom,
                #slugdiv,
                #trackbacksdiv,
                .order_download_permissions {
                    display: none !important;
                }
            }
            
            /* General Cleanups for all devices */
            .order_data_column h3 {
                color: #1e293b;
                margin-top: 0;
            }
            #woocommerce-order-data .inside {
                padding: 20px;
            }
            #order_line_items .button {
                background-color: #f1f5f9;
                border-color: #cbd5e1;
            }
        </style>
        ";
        echo $css;
    }
}
