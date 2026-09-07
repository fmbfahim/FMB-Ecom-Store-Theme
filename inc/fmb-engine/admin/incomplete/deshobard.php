<?php
$from = isset($_GET['from']) ? sanitize_text_field($_GET['from']) : date('Y-m-d', strtotime('-30 days'));
$to = isset($_GET['to']) ? sanitize_text_field($_GET['to']) : date('Y-m-d');
?>
<div class=" ads-analytics-dashboard">
    <div class="ads-date-filter">

        <form method="get" action="">
            <input type="hidden" name="page" value="fmb-engine-incomplete-orders" />
            <label for="from"><?php esc_html_e('From:', 'ad-saver'); ?></label>
            <input type="date" name="from" id="from" value="<?php echo esc_attr($from); ?>" />

            <label for="to"><?php esc_html_e('To:', 'ad-saver'); ?></label>
            <input type="date" name="to" id="to" value="<?php echo esc_attr($to); ?>" />

            <button type="submit" class="btn-primary"><?php esc_html_e('Apply', 'ad-saver'); ?></button>
        </form>
    </div>

    <div class="ads-summary-cards">
        <div class="ads-card ads-card-incomplete">
            <h3><?php esc_html_e('Total Incomplete Orders', 'ad-saver'); ?></h3>
            <div class="ads-value" id="total-incomplete-orders">0</div>
        </div>

        <div class="ads-card ads-card-recovered">
            <h3><?php esc_html_e('Total Recovery Orders', 'ad-saver'); ?></h3>
            <div class="ads-value" id="total-recovery-orders">0</div>
        </div>

        <div class="ads-card ads-card-rate">
            <h3><?php esc_html_e('Recovery Rate', 'ad-saver'); ?></h3>
            <div class="ads-value" id="recovery-rate">0%</div>
        </div>
        <div class="ads-card ads-card-amount">
            <h3><?php esc_html_e('Recovery Amount', 'ad-saver'); ?></h3>
            <div class="ads-value" id="recovery-amount">0.00৳</div>
        </div>
    </div>

    <div class="ads-charts-container">
        <div class="ads-chart-box">
            <h2 style="display: flex; align-items: center; gap: 8px;">
                <span style="background: #eff6ff; color: #3b82f6; padding: 4px; border-radius: 6px; display: inline-flex;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline></svg>
                </span>
                <?php esc_html_e('Incomplete Orders by Day', 'ad-saver'); ?>
            </h2>
            <div class="ads-chart-wrapper">
                <canvas id="incomplete-orders-chart"></canvas>
            </div>
        </div>

        <div class="ads-chart-box">
            <h2><?php esc_html_e('Orders by Status', 'ad-saver'); ?></h2>
            <div class="ads-chart-wrapper">
                <canvas id="orders-by-status-chart"></canvas>
            </div>
        </div>
    </div>

    <div class="ads-table-container">
        <h2><?php esc_html_e('Top Recovery Days (Last 30 Days)', 'ad-saver'); ?></h2>
        <div class="overflow-x-auto" style="border-radius: 12px; overflow: hidden; border: 1px solid #f1f5f9;">
            <table class="checkout-table" style="width: 100%; border-collapse: separate; border-spacing: 0;">
                <thead>
                <tr>
                    <th style="padding: 18px; text-align: left; background: #f8fafc; color: #64748b; font-size: 12px; font-weight: 700; text-transform: uppercase; border-bottom: 2px solid #f1f5f9;"><?php esc_html_e('Date', 'ad-saver'); ?></th>
                    <th style="padding: 18px; text-align: left; background: #f8fafc; color: #64748b; font-size: 12px; font-weight: 700; text-transform: uppercase; border-bottom: 2px solid #f1f5f9;"><?php esc_html_e('Recovered Carts', 'ad-saver'); ?></th>
                    <th style="padding: 18px; text-align: left; background: #f8fafc; color: #64748b; font-size: 12px; font-weight: 700; text-transform: uppercase; border-bottom: 2px solid #f1f5f9;"><?php esc_html_e('Recovered Revenue', 'ad-saver'); ?></th>
                </tr>
                </thead>
                <tbody id="top-recovery-days-tbody" class="bg-white divide-y divide-gray-200">
                <tr>
                    <td colspan="3" class="px-6 py-4 text-center text-gray-500"><?php esc_html_e('No data available', 'ad-saver'); ?></td>
                </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php