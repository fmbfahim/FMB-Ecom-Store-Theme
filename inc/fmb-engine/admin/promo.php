<?php
/**
 * FMB Engine Header Banner
 */
if (!defined('ABSPATH')) exit;

if (!function_exists('fmb_engine_render_header')) {
    function fmb_engine_render_header() {
        $current_page = isset($_GET['page']) ? $_GET['page'] : '';
        ?>
        <style>
            @keyframes gradientShift { 0% { background-position: 0% 50%; } 50% { background-position: 100% 50%; } 100% { background-position: 0% 50%; } }
            @keyframes fadeUp { from { opacity: 0; transform: translateY(15px); } to { opacity: 1; transform: translateY(0); } }
            
            .fmb-premium-header {
                display: flex; justify-content: space-between; align-items: center; margin: 24px 20px 30px 0; padding: 28px 36px; border-radius: 20px; color: white; 
                background: linear-gradient(135deg, #1e1b4b, #312e81, #1e40af); background-size: 200% 200%; animation: gradientShift 10s ease infinite, fadeUp 0.6s ease-out backwards; 
                box-shadow: 0 20px 40px -10px rgba(49, 46, 129, 0.3); position: relative; overflow: hidden; font-family: 'Inter', sans-serif;
            }
            .fmb-premium-header::after {
                content: ''; position: absolute; top: 0; left: 0; right: 0; bottom: 0; 
                background: url('data:image/svg+xml;utf8,<svg viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg"><filter id="noise"><feTurbulence type="fractalNoise" baseFrequency="0.65" numOctaves="3" stitchTiles="stitch"/></filter><rect width="100%" height="100%" filter="url(%23noise)" opacity="0.05"/></svg>'); 
                pointer-events: none; mix-blend-mode: overlay;
            }
            .fmb-premium-title {
                font-size: 26px; font-weight: 800; color: #ffffff; margin: 0; letter-spacing: -0.5px; display: flex; align-items: center; gap: 14px; position: relative; z-index: 2; text-shadow: 0 2px 10px rgba(0,0,0,0.2); line-height: 1.2;
            }
            .fmb-premium-badge {
                background: rgba(255, 255, 255, 0.15); backdrop-filter: blur(10px); color: #ffffff; padding: 8px 16px; border-radius: 30px; font-size: 13px; font-weight: 600; display: flex; align-items: center; gap: 8px; border: 1px solid rgba(255,255,255,0.2); position: relative; z-index: 2;
            }
        </style>
        <header class="fmb-premium-header">
            <h2 class="fmb-premium-title">
                <span style="font-size:30px; background: #ffffff; border-radius: 12px; padding: 6px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); display:flex; align-items:center; justify-content:center;">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 28px; height: 28px; color: #2563eb;">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456Z" />
                    </svg>
                </span>
                <?php
                switch ($current_page) {
                    case 'fmb-engine-incomplete-orders': echo 'Incomplete Orders Dashboard'; break;
                    case 'fmb-engine-fraud-customer-block': echo 'Fraud Customer Block'; break;
                    case 'fmb-engine-partial-payment': echo 'Partial Payment Engine'; break;
                    case 'fmb-engine-fb-settings': echo 'Pixel & Server CAPI Tracking'; break;
                    case 'fmb-engine-courier': echo 'BD Courier Fraud Checker'; break;
                    case 'fmb-engine-courier-setup': echo 'BD Courier Integration Setup'; break;
                    default: echo 'FMB Engine Dashboard & Order Optimizer';
                }
                ?>
            </h2>
            <div class="fmb-premium-badge">
                🚀 Powered by FMB Engine
            </div>
        </header>
        <?php
    }
}