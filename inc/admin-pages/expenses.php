<?php
/**
 * Expenses Management Admin Page
 * FMB E-Commerce Store
 */

if (!defined('ABSPATH')) {
    exit;
}

function fmb_admin_expenses_page() {
    if (!current_user_can('manage_woocommerce')) {
        wp_die('Unauthorized access');
    }

    global $wpdb;
    fmb_init_purchase_stock_tables();
    
    // Ensure select2 is available for searchable dropdowns
    if (function_exists('wp_enqueue_script')) {
        wp_enqueue_style('woocommerce_admin_styles');
        wp_enqueue_script('wc-enhanced-select');
    }

    $current_tab = sanitize_text_field($_GET['tab'] ?? 'expenses');
    $base_url    = admin_url('admin.php?page=fmb-expenses');
    $ajax_nonce  = wp_create_nonce('fmb_purchase_stock_nonce');

    // Fetch Expenses for Tabs
    $expenses_list = fmb_get_expenses();
    $expense_categories = fmb_get_expense_categories();

    // Date filtering for Expense Reports
    $report_start = sanitize_text_field($_GET['report_start'] ?? date('Y-m-d', strtotime('-30 days')));
    $report_end   = sanitize_text_field($_GET['report_end'] ?? date('Y-m-d'));

    ?>
    <style>
    .fmb-purchase-stock-wrap {
        max-width: 100% !important;
        width: 100% !important;
        margin: 15px 0 70px 0 !important;
        padding: 0 20px 0 0 !important;
        box-sizing: border-box !important;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        color: #0f172a;
    }
    .fmb-hub-topbar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 12px;
        margin-bottom: 16px;
    }
    .fmb-hub-main-title {
        font-size: 22px;
        font-weight: 800;
        margin: 0;
        color: #0f172a;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .fmb-underline-tabs-container {
        display: flex;
        gap: 25px;
        border-bottom: 1px solid #cbd5e1;
        margin-bottom: 20px;
    }
    .fmb-underline-tab {
        text-decoration: none;
        color: #64748b;
        font-weight: 600;
        font-size: 14px;
        padding: 10px 0;
        border-bottom: 3px solid transparent;
        transition: all 0.2s;
        display: flex;
        align-items: center;
        gap: 6px;
    }
    .fmb-underline-tab:hover {
        color: #0f172a;
    }
    .fmb-underline-tab.active {
        color: #4338ca;
        border-bottom: 3px solid #4338ca;
    }
    .fmb-underline-tab .tab-num {
        background: #f1f5f9;
        color: #64748b;
        padding: 2px 8px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 700;
        transition: all 0.15s;
    }
    .fmb-underline-tab.active .tab-num {
        background: #e0e7ff;
        color: #4338ca;
        font-weight: 800;
    }

    /* KPI Metric Cards */
    .fmb-kpi-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 14px;
        margin-bottom: 20px;
    }
    .fmb-kpi-card {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 16px;
        display: flex;
        align-items: center;
        gap: 12px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.03);
    }
    .fmb-kpi-card .kpi-icon-wrap {
        width: 44px;
        height: 44px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }
    .fmb-kpi-card .kpi-icon-wrap .dashicons { font-size: 22px; width: 22px; height: 22px; }
    .fmb-kpi-card.card-blue   .kpi-icon-wrap { background: #eff6ff; color: #2563eb; }
    .fmb-kpi-card.card-purple .kpi-icon-wrap { background: #f5f3ff; color: #7c3aed; }
    .fmb-kpi-card.card-green  .kpi-icon-wrap { background: #ecfdf5; color: #059669; }
    .fmb-kpi-card.card-amber  .kpi-icon-wrap { background: #fffbeb; color: #d97706; }
    .fmb-kpi-card.card-rose   .kpi-icon-wrap { background: #fef2f2; color: #dc2626; }
    .fmb-kpi-card .kpi-label { font-size: 11.5px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.4px; display: block; }
    .fmb-kpi-card .kpi-val { font-size: 19px; font-weight: 800; margin: 4px 0 0 0; color: #0f172a; line-height: 1.1; }
    .fmb-kpi-card .kpi-val small { font-size: 13px; font-weight: 600; color: #64748b; }

    .fmb-table-card {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        padding: 0;
        overflow: hidden;
        box-shadow: 0 1px 3px rgba(0,0,0,0.03);
    }
    .fmb-table-responsive {
        overflow-x: auto;
    }
    .fmb-order-hub-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 13.5px;
    }
    .fmb-order-hub-table th, .fmb-order-hub-table td {
        padding: 14px 16px;
        text-align: left;
        border-bottom: 1px solid #e2e8f0;
    }
    .fmb-order-hub-table th {
        background: #f8fafc;
        font-weight: 700;
        color: #475569;
        text-transform: uppercase;
        font-size: 11px;
        letter-spacing: 0.5px;
    }
    .fmb-order-hub-table td {
        color: #334155;
        vertical-align: middle;
    }
    .fmb-order-hub-table tbody tr:hover {
        background: #f8fafc;
    }
    .fmb-btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 14px;
        font-size: 13px;
        font-weight: 600;
        border-radius: 6px;
        cursor: pointer;
        text-decoration: none;
        border: 1px solid transparent;
        transition: all 0.2s;
        line-height: 1;
    }
    .fmb-btn-primary-purple { background: #4338ca; color: #fff; }
    .fmb-btn-primary-purple:hover { background: #3730a3; }
    .fmb-btn-outline-adjust { background: #fff; border-color: #cbd5e1; color: #475569; }
    .fmb-btn-outline-adjust:hover { border-color: #94a3b8; color: #0f172a; }

    .fmb-cat-tag {
        display: inline-block;
        background: #f1f5f9;
        color: #475569;
        padding: 4px 10px;
        border-radius: 4px;
        font-size: 12px;
        font-weight: 600;
    }

    /* Modal Styles */
    .fmb-modal-backdrop {
        position: fixed; top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(15,23,42,0.6); z-index: 999999;
        display: flex; align-items: center; justify-content: center;
        backdrop-filter: blur(2px);
    }
    .fmb-modal-box {
        background: #fff; width: 100%; max-width: 500px;
        border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        display: flex; flex-direction: column; max-height: 90vh;
    }
    .fmb-modal-head {
        padding: 16px 20px; border-bottom: 1px solid #e2e8f0;
        display: flex; justify-content: space-between; align-items: center;
        background: #f8fafc; border-radius: 12px 12px 0 0;
    }
    .fmb-modal-head h3 { margin: 0; font-size: 16px; font-weight: 700; color: #0f172a; display: flex; align-items: center; gap: 8px; }
    .fmb-modal-close-x {
        background: none; border: none; font-size: 24px; line-height: 1;
        cursor: pointer; color: #94a3b8; padding: 0;
    }
    .fmb-modal-close-x:hover { color: #ef4444; }
    .fmb-modal-content {
        padding: 20px; overflow-y: auto;
    }
    .fmb-form-group { margin-bottom: 12px; }
    .fmb-form-lbl { display: block; font-size: 12.5px; font-weight: 600; color: #475569; margin-bottom: 6px; }
    .fmb-input-control {
        width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1;
        border-radius: 6px; font-size: 13.5px; color: #0f172a;
        box-sizing: border-box; transition: border-color 0.2s;
    }
    .fmb-input-control:focus { border-color: #4338ca; outline: none; box-shadow: 0 0 0 3px rgba(67,56,202,0.1); }
    </style>

    <div class="fmb-purchase-stock-wrap">
        
        <div class="fmb-hub-topbar">
            <h1 class="fmb-hub-main-title">
                💸 Expense Management
            </h1>
        </div>

        <!-- TABS -->
        <div class="fmb-underline-tabs-container">
            <a href="<?php echo esc_url(add_query_arg('tab', 'expenses', $base_url)); ?>" class="fmb-underline-tab <?php echo $current_tab === 'expenses' ? 'active' : ''; ?>">
                💸 Expenses
                <span class="tab-num"><?php echo count($expenses_list); ?></span>
            </a>
            <a href="<?php echo esc_url(add_query_arg('tab', 'expense-reports', $base_url)); ?>" class="fmb-underline-tab <?php echo $current_tab === 'expense-reports' ? 'active' : ''; ?>">
                📈 Expense Reports
            </a>
        </div>

        <?php if ($current_tab === 'expenses') : ?>

            <div class="fmb-expenses-wrap">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                    <h2 style="margin:0; font-size:17px; font-weight:800; color:#0f172a;">Operational Expenses</h2>
                    <div>
                        <button type="button" class="fmb-btn fmb-btn-outline-adjust" id="btn-open-manage-categories" style="margin-right: 10px;">
                            <span class="dashicons dashicons-category"></span> Manage Categories
                        </button>
                        <button type="button" class="fmb-btn fmb-btn-primary-purple" id="btn-open-expense-modal">
                            <span class="dashicons dashicons-plus-alt2"></span> Add New Expense
                        </button>
                    </div>
                </div>

                <div class="fmb-table-card">
                    <table class="fmb-order-hub-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Category</th>
                                <th>Ref / Transaction ID</th>
                                <th>Notes</th>
                                <th style="text-align:right;">Amount (৳)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($expenses_list)): ?>
                                <tr><td colspan="5" style="text-align:center; padding:30px; color:#64748b;">
                                    No expenses recorded yet.
                                </td></tr>
                            <?php else: ?>
                                <?php foreach($expenses_list as $exp): ?>
                                <tr>
                                    <td style="font-size:12px; color:#475569;"><?php echo date('d M, Y', strtotime($exp->expense_date)); ?></td>
                                    <td>
                                        <span class="fmb-cat-tag">
                                            <?php 
                                                if (!empty($exp->cat_name)) {
                                                    echo esc_html($exp->cat_name);
                                                    if (!empty($exp->sub_name)) echo ' &rarr; ' . esc_html($exp->sub_name);
                                                } else {
                                                    echo esc_html($exp->category);
                                                }
                                            ?>
                                        </span>
                                    </td>
                                    <td style="font-size:12px;"><?php echo esc_html($exp->reference); ?></td>
                                    <td style="font-size:11px; color:#64748b;"><?php echo esc_html($exp->notes); ?></td>
                                    <td style="text-align:right; font-weight:bold; color:#dc2626;">৳ <?php echo number_format($exp->amount, 2); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        <?php elseif ($current_tab === 'expense-reports') : ?>

            <div class="fmb-expense-reports-wrap">
                
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                    <h2 style="margin:0; font-size:17px; font-weight:800; color:#0f172a;">Expense Reports</h2>
                    <form method="get" action="<?php echo esc_url($base_url); ?>" style="display:flex; gap:10px; align-items:center;">
                        <input type="hidden" name="page" value="fmb-expenses">
                        <input type="hidden" name="tab" value="expense-reports">
                        <input type="date" name="report_start" class="fmb-filter-search-input" value="<?php echo esc_attr($report_start); ?>">
                        <span style="color:#64748b; margin-top:8px;">to</span>
                        <input type="date" name="report_end" class="fmb-filter-search-input" value="<?php echo esc_attr($report_end); ?>">
                        <button type="submit" class="fmb-btn fmb-btn-primary-purple">Filter</button>
                    </form>
                </div>

                <?php 
                    // Calculate totals and advanced metrics
                    $total_exp = 0;
                    $total_transactions = 0;
                    $cat_totals = array();
                    $filtered_expenses = array();
                    
                    // Prepare dates for the trend chart
                    $dates_data = array();
                    try {
                        $start = new DateTime($report_start);
                        $end = new DateTime($report_end);
                        $end->modify('+1 day');
                        $period = new DatePeriod($start, DateInterval::createFromDateString('1 day'), $end);
                        foreach ($period as $dt) {
                            $dates_data[$dt->format("d M")] = 0;
                        }
                    } catch (Exception $e) {}

                    if (!empty($expenses_list)) {
                        foreach ($expenses_list as $exp) {
                            $edate = date('Y-m-d', strtotime($exp->expense_date));
                            if ($edate >= $report_start && $edate <= $report_end) {
                                $filtered_expenses[] = $exp;
                                $total_exp += (float)$exp->amount;
                                $total_transactions++;
                                
                                $cat_name = !empty($exp->cat_name) ? $exp->cat_name : $exp->category;
                                if (!isset($cat_totals[$cat_name])) $cat_totals[$cat_name] = 0;
                                $cat_totals[$cat_name] += (float)$exp->amount;
                                
                                $fdate = date('d M', strtotime($exp->expense_date));
                                if (isset($dates_data[$fdate])) {
                                    $dates_data[$fdate] += (float)$exp->amount;
                                }
                            }
                        }
                    }
                    
                    if(!empty($cat_totals)) arsort($cat_totals); // sort by highest amount
                    
                    // Top category
                    $top_cat_name = 'None';
                    $top_cat_amt = 0;
                    if (!empty($cat_totals)) {
                        reset($cat_totals);
                        $top_cat_name = key($cat_totals);
                        $top_cat_amt = current($cat_totals);
                    }
                    
                    // Daily average
                    $days = count($dates_data);
                    $daily_avg = $days > 0 ? $total_exp / $days : 0;
                    
                    // Data for charts
                    $chart_labels = array_keys($cat_totals);
                    $chart_values = array_values($cat_totals);
                    
                    $trend_labels = array_keys($dates_data);
                    $trend_values = array_values($dates_data);
                ?>

                <!-- KPI Cards -->
                <div class="fmb-kpi-grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); margin-bottom: 25px;">
                    <div class="fmb-kpi-card card-rose">
                        <div class="kpi-icon-wrap"><span class="dashicons dashicons-money-alt"></span></div>
                        <div class="kpi-content">
                            <span class="kpi-label">Total Expenses</span>
                            <h2 class="kpi-val">৳ <?php echo number_format($total_exp, 0); ?></h2>
                        </div>
                    </div>
                    
                    <div class="fmb-kpi-card card-amber">
                        <div class="kpi-icon-wrap"><span class="dashicons dashicons-calendar-alt"></span></div>
                        <div class="kpi-content">
                            <span class="kpi-label">Daily Average</span>
                            <h2 class="kpi-val">৳ <?php echo number_format($daily_avg, 0); ?></h2>
                        </div>
                    </div>
                    
                    <div class="fmb-kpi-card card-purple">
                        <div class="kpi-icon-wrap"><span class="dashicons dashicons-chart-pie"></span></div>
                        <div class="kpi-content">
                            <span class="kpi-label">Top Category</span>
                            <h2 class="kpi-val" style="font-size:16px; margin-top:8px;"><?php echo esc_html($top_cat_name); ?></h2>
                            <span style="font-size:12px; color:#64748b;">৳ <?php echo number_format($top_cat_amt, 0); ?></span>
                        </div>
                    </div>
                    
                    <div class="fmb-kpi-card card-blue">
                        <div class="kpi-icon-wrap"><span class="dashicons dashicons-list-view"></span></div>
                        <div class="kpi-content">
                            <span class="kpi-label">Transactions</span>
                            <h2 class="kpi-val"><?php echo $total_transactions; ?></h2>
                        </div>
                    </div>
                </div>

                <!-- Charts Section -->
                <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
                <div style="display:grid; grid-template-columns: 1fr 2fr; gap:25px; margin-bottom: 25px;">
                    
                    <!-- Breakdown Donut Chart -->
                    <div class="fmb-table-card" style="padding:20px; display:flex; flex-direction:column;">
                        <h3 style="margin:0 0 15px 0; font-size:15px; color:#0f172a;">Breakdown by Category</h3>
                        <div style="flex:1; position:relative; min-height: 250px; display:flex; align-items:center; justify-content:center;">
                            <?php if(empty($cat_totals)): ?>
                                <p style="color:#64748b;">No data available.</p>
                            <?php else: ?>
                                <canvas id="expenseCatChart"></canvas>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Trend Bar Chart -->
                    <div class="fmb-table-card" style="padding:20px; display:flex; flex-direction:column;">
                        <h3 style="margin:0 0 15px 0; font-size:15px; color:#0f172a;">Daily Expense Trend</h3>
                        <div style="flex:1; position:relative; min-height: 250px;">
                            <canvas id="expenseTrendChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Detailed List -->
                <div class="fmb-table-card">
                    <div style="padding:15px 20px; border-bottom:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center;">
                        <h3 style="margin:0; font-size:16px;">Detailed Expenses</h3>
                        <span style="font-size:12px; background:#f1f5f9; padding:4px 10px; border-radius:12px; color:#475569; font-weight:600;">
                            <?php echo date('d M', strtotime($report_start)); ?> - <?php echo date('d M, Y', strtotime($report_end)); ?>
                        </span>
                    </div>
                    <div style="max-height: 450px; overflow-y:auto;">
                        <table class="fmb-order-hub-table" style="margin:0;">
                            <thead>
                                <tr>
                                    <th style="padding-left:20px;">Date</th>
                                    <th>Category</th>
                                    <th>Payment Info</th>
                                    <th>Notes</th>
                                    <th style="text-align:right; padding-right:20px;">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(empty($filtered_expenses)): ?>
                                    <tr><td colspan="5" style="text-align:center; padding:30px; color:#64748b;">No expenses recorded in this period.</td></tr>
                                <?php else: ?>
                                    <?php foreach($filtered_expenses as $exp): ?>
                                    <tr style="transition:background 0.2s; cursor:default;" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='transparent'">
                                        <td style="padding-left:20px; font-weight:600; color:#334155;">
                                            <?php echo date('d M, Y', strtotime($exp->expense_date)); ?>
                                        </td>
                                        <td>
                                            <span style="display:inline-block; padding:3px 10px; background:#eff6ff; color:#2563eb; font-size:12px; font-weight:600; border-radius:4px; border:1px solid #bfdbfe;">
                                                <?php 
                                                    if (!empty($exp->cat_name)) {
                                                        echo esc_html($exp->cat_name);
                                                        if (!empty($exp->sub_name)) echo ' &rarr; ' . esc_html($exp->sub_name);
                                                    } else {
                                                        echo esc_html($exp->category);
                                                    }
                                                ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div style="font-size:12px; font-weight:600; text-transform:capitalize; color:#0f172a;">
                                                <?php echo esc_html($exp->method ?? 'Cash'); ?>
                                            </div>
                                            <?php if(!empty($exp->reference)): ?>
                                                <div style="font-size:11px; color:#64748b; margin-top:2px;">Ref: <?php echo esc_html($exp->reference); ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td style="font-size:12px; color:#64748b;">
                                            <?php echo esc_html(wp_trim_words($exp->notes, 10, '...')); ?>
                                        </td>
                                        <td style="text-align:right; padding-right:20px; font-size:15px; font-weight:800; color:#dc2626;">
                                            ৳ <?php echo number_format($exp->amount, 0); ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Initialize Charts -->
                <script>
                    document.addEventListener("DOMContentLoaded", function() {
                        var catLabels = <?php echo json_encode($chart_labels); ?>;
                        var catData = <?php echo json_encode($chart_values); ?>;
                        
                        if (catLabels.length > 0) {
                            var ctxCat = document.getElementById('expenseCatChart').getContext('2d');
                            new Chart(ctxCat, {
                                type: 'doughnut',
                                data: {
                                    labels: catLabels,
                                    datasets: [{
                                        data: catData,
                                        backgroundColor: ['#ef4444','#f97316','#f59e0b','#84cc16','#22c55e','#06b6d4','#3b82f6','#6366f1','#a855f7','#ec4899'],
                                        borderWidth: 2,
                                        borderColor: '#fff',
                                        hoverOffset: 4
                                    }]
                                },
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    plugins: {
                                        legend: { position: 'right', labels: { boxWidth:12, font:{size:11, family:'Inter'} } },
                                        tooltip: {
                                            callbacks: {
                                                label: function(context) {
                                                    return ' ৳ ' + context.parsed.toLocaleString();
                                                }
                                            }
                                        }
                                    },
                                    cutout: '70%'
                                }
                            });
                        }

                        var trendLabels = <?php echo json_encode($trend_labels); ?>;
                        var trendData = <?php echo json_encode($trend_values); ?>;
                        
                        if (trendLabels.length > 0) {
                            var ctxTrend = document.getElementById('expenseTrendChart').getContext('2d');
                            new Chart(ctxTrend, {
                                type: 'bar',
                                data: {
                                    labels: trendLabels,
                                    datasets: [{
                                        label: 'Daily Expense',
                                        data: trendData,
                                        backgroundColor: '#6366f1',
                                        borderRadius: 4,
                                        barPercentage: 0.6
                                    }]
                                },
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    plugins: {
                                        legend: { display: false },
                                        tooltip: {
                                            callbacks: {
                                                label: function(context) {
                                                    return ' ৳ ' + context.parsed.y.toLocaleString();
                                                }
                                            }
                                        }
                                    },
                                    scales: {
                                        y: { beginAtZero: true, grid: { color: '#f1f5f9' }, border: { display: false } },
                                        x: { grid: { display: false }, border: { display: false }, ticks: { font: { size: 10 } } }
                                    }
                                }
                            });
                        }
                    });
                </script>

            </div>

        <?php endif; ?>

    </div>

    <!-- MODAL: Manage Categories -->
    <div class="fmb-modal-backdrop" id="fmb-manage-categories-modal" style="display:none;">
        <div class="fmb-modal-box" style="max-width: 500px;">
            <div class="fmb-modal-head">
                <h3><span class="dashicons dashicons-category"></span> Manage Expense Categories</h3>
                <button type="button" class="fmb-modal-close-x" id="fmb-close-categories-modal">&times;</button>
            </div>
            <div class="fmb-modal-content">
                <div style="margin-bottom: 20px;">
                    <form id="fmb-add-cat-form" onsubmit="return false;" style="display:flex; gap:10px; align-items:flex-end;">
                        <div class="fmb-form-group" style="flex:1;">
                            <label class="fmb-form-lbl">New Category Name</label>
                            <input type="text" id="new-cat-name" class="fmb-input-control" required>
                        </div>
                        <div class="fmb-form-group" style="flex:1;">
                            <label class="fmb-form-lbl">Parent Category (Optional)</label>
                            <select id="new-cat-parent" class="fmb-input-control">
                                <option value="0">-- None (Main Category) --</option>
                                <?php if(!empty($expense_categories)): foreach ($expense_categories as $cat) : ?>
                                    <option value="<?php echo esc_attr($cat['id']); ?>"><?php echo esc_html($cat['name']); ?></option>
                                <?php endforeach; endif; ?>
                            </select>
                        </div>
                        <button type="button" class="fmb-btn fmb-btn-primary-purple" id="btn-save-category">Add</button>
                    </form>
                </div>

                <div style="max-height: 300px; overflow-y:auto; border: 1px solid #e2e8f0; border-radius: 6px;">
                    <ul style="list-style:none; margin:0; padding:0; background:#f8fafc;" id="fmb-cat-list-container">
                        <?php if (empty($expense_categories)) : ?>
                            <li style="padding:10px; text-align:center; color:#64748b;">No categories created yet.</li>
                        <?php else: ?>
                            <?php foreach ($expense_categories as $cat) : ?>
                                <li style="padding:10px; border-bottom:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center;">
                                    <strong><?php echo esc_html($cat['name']); ?></strong>
                                    <button class="btn-del-cat" data-id="<?php echo esc_attr($cat['id']); ?>" style="background:none; border:none; color:#ef4444; cursor:pointer;"><span class="dashicons dashicons-trash"></span></button>
                                </li>
                                <?php if (!empty($cat['sub'])) : ?>
                                    <?php foreach ($cat['sub'] as $sub) : ?>
                                        <li style="padding:8px 10px 8px 30px; border-bottom:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center; background:#fff;">
                                            <span>&#8627; <?php echo esc_html($sub['name']); ?></span>
                                            <button class="btn-del-cat" data-id="<?php echo esc_attr($sub['id']); ?>" style="background:none; border:none; color:#ef4444; cursor:pointer;"><span class="dashicons dashicons-trash"></span></button>
                                        </li>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL: Add General Expense -->
    <div class="fmb-modal-backdrop" id="fmb-add-expense-modal" style="display:none;">
        <div class="fmb-modal-box" style="max-width: 450px;">
            <div class="fmb-modal-head">
                <h3><span class="dashicons dashicons-plus-alt2"></span> Record General Expense</h3>
                <button type="button" class="fmb-modal-close-x" id="fmb-close-expense-modal">&times;</button>
            </div>
            <div class="fmb-modal-content">
                <form id="fmb-add-expense-form" onsubmit="return false;">
                    
                    <div class="fmb-form-group">
                        <label class="fmb-form-lbl">Expense Date *</label>
                        <input type="date" id="add-exp-date" class="fmb-input-control" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>

                    <div class="fmb-form-group" style="margin-top:12px;">
                        <label class="fmb-form-lbl">Category *</label>
                        <select id="add-exp-category-id" class="fmb-input-control" required>
                            <option value="">-- Select Category --</option>
                            <?php if(!empty($expense_categories)): foreach ($expense_categories as $cat) : ?>
                                <option value="<?php echo esc_attr($cat['id']); ?>"><?php echo esc_html($cat['name']); ?></option>
                            <?php endforeach; endif; ?>
                        </select>
                        <input type="hidden" id="add-exp-category" value="">
                    </div>
                    
                    <div class="fmb-form-group" id="add-exp-subcategory-wrap" style="margin-top:12px; display:none;">
                        <label class="fmb-form-lbl">Sub-category</label>
                        <select id="add-exp-subcategory-id" class="fmb-input-control">
                            <option value="0">-- None --</option>
                        </select>
                    </div>

                    <div class="fmb-form-group" style="margin-top:12px;">
                        <label class="fmb-form-lbl">Amount (৳) *</label>
                        <input type="number" id="add-exp-amount" class="fmb-input-control" value="0" min="1" step="any" required>
                    </div>

                    <div class="fmb-form-group" style="margin-top:12px;">
                        <label class="fmb-form-lbl">Payment Method *</label>
                        <select id="add-exp-method" class="fmb-input-control">
                            <option value="cash">Cash</option>
                            <option value="bank">Bank Transfer</option>
                            <option value="bkash">bKash</option>
                            <option value="nagad">Nagad</option>
                        </select>
                    </div>

                    <div class="fmb-form-group" style="margin-top:12px;">
                        <label class="fmb-form-lbl">Reference / Invoice</label>
                        <input type="text" id="add-exp-ref" class="fmb-input-control" placeholder="Optional">
                    </div>

                    <div class="fmb-form-group" style="margin-top:12px;">
                        <label class="fmb-form-lbl">Notes</label>
                        <textarea id="add-exp-notes" rows="2" class="fmb-input-control" placeholder="Optional details..."></textarea>
                    </div>

                    <div style="margin-top:18px; text-align:right;">
                        <button type="button" class="fmb-btn fmb-btn-primary-purple" id="btn-submit-general-expense">
                            <span class="dashicons dashicons-yes"></span> Save Expense
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    jQuery(document).ready(function($) {
        var nonce   = '<?php echo $ajax_nonce; ?>';
        var ajaxUrl = '<?php echo admin_url("admin-ajax.php"); ?>';
        var expenseCategoriesData = <?php echo json_encode($expense_categories); ?> || {};
        
        $('#btn-open-manage-categories').on('click', function(){
            $('#fmb-manage-categories-modal').fadeIn(150);
        });

        $('#fmb-close-categories-modal').on('click', function(){
            $('#fmb-manage-categories-modal').fadeOut(150);
        });

        // Dynamic Sub-category Population
        $('#add-exp-category-id').on('change', function(){
            var catId = parseInt($(this).val());
            var $subWrap = $('#add-exp-subcategory-wrap');
            var $subSelect = $('#add-exp-subcategory-id');
            var categoryText = $(this).find('option:selected').text();
            
            $('#add-exp-category').val(categoryText);
            
            $subSelect.html('<option value="0">-- None --</option>');
            
            if (catId > 0 && expenseCategoriesData[catId] && expenseCategoriesData[catId].sub && expenseCategoriesData[catId].sub.length > 0) {
                var subs = expenseCategoriesData[catId].sub;
                subs.forEach(function(s){
                    $subSelect.append('<option value="'+s.id+'">'+s.name+'</option>');
                });
                $subWrap.show();
            } else {
                $subWrap.hide();
            }
        });

        // Add New Category
        $('#btn-save-category').on('click', function(){
            var name = $('#new-cat-name').val().trim();
            var parent_id = $('#new-cat-parent').val();
            
            if (!name) {
                alert('Please enter a category name.');
                return;
            }

            var $btn = $(this);
            $btn.prop('disabled', true).text('Saving...');

            $.post(ajaxUrl, {
                action: 'fmb_ajax_save_expense_category',
                nonce: nonce,
                name: name,
                parent_id: parent_id
            }, function(res){
                if (res.success) {
                    window.location.reload();
                } else {
                    alert(res.data || 'Failed to save category.');
                    $btn.prop('disabled', false).text('Add');
                }
            });
        });

        // Delete Category
        $(document).on('click', '.btn-del-cat', function(){
            if (!confirm('Are you sure you want to delete this category? Sub-categories (if any) will also be deleted.')) return;
            var id = $(this).data('id');
            var $btn = $(this);
            $btn.prop('disabled', true);
            
            $.post(ajaxUrl, {
                action: 'fmb_ajax_delete_expense_category',
                nonce: nonce,
                id: id
            }, function(res){
                if (res.success) {
                    window.location.reload();
                } else {
                    alert(res.data || 'Failed to delete category.');
                    $btn.prop('disabled', false);
                }
            });
        });

        $('#btn-open-expense-modal').on('click', function(){
            $('#fmb-add-expense-modal').fadeIn(150);
        });

        $('#fmb-close-expense-modal').on('click', function(){
            $('#fmb-add-expense-modal').fadeOut(150);
        });

        $('#btn-submit-general-expense').on('click', function(){
            var date = $('#add-exp-date').val();
            var category_id = $('#add-exp-category-id').val();
            var sub_category_id = $('#add-exp-subcategory-id').val();
            var category = $('#add-exp-category').val() || 'Uncategorized';
            var amount = $('#add-exp-amount').val();
            var method = $('#add-exp-method').val();
            var ref = $('#add-exp-ref').val();
            var notes = $('#add-exp-notes').val();

            if (!category_id || !amount || amount <= 0) {
                alert('Please select a valid category and enter an amount.');
                return;
            }

            var $btn = $(this);
            $btn.prop('disabled', true).text('Saving...');

            $.post(ajaxUrl, {
                action: 'fmb_ajax_add_general_expense',
                nonce: nonce,
                date: date,
                category_id: category_id,
                sub_category_id: sub_category_id,
                category: category,
                amount: amount,
                method: method,
                reference: ref,
                notes: notes
            }, function(res){
                if (res.success) {
                    alert('Expense added successfully!');
                    window.location.reload();
                } else {
                    alert(res.data || 'Failed to add expense.');
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-yes"></span> Save Expense');
                }
            });
        });
    });
    </script>
    <?php
}
