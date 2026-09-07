<?php
/**
 * FMB Employee Management & Payroll System — Admin Dashboard & UI
 */

if (!defined('ABSPATH')) {
    exit;
}

class FMB_Employee_Admin {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_menu', array($this, 'register_admin_menu'));
        add_action('wp_ajax_fmb_save_employee', array($this, 'ajax_save_employee'));
        add_action('wp_ajax_fmb_get_employee', array($this, 'ajax_get_employee'));
        add_action('wp_ajax_fmb_delete_employee', array($this, 'ajax_delete_employee'));
        add_action('wp_ajax_fmb_toggle_employee_status', array($this, 'ajax_toggle_employee_status'));
        add_action('wp_ajax_fmb_filter_performance', array($this, 'ajax_filter_performance'));
        add_action('wp_ajax_fmb_generate_payroll', array($this, 'ajax_generate_payroll'));
        add_action('wp_ajax_fmb_mark_payroll_paid', array($this, 'ajax_mark_payroll_paid'));
        add_action('wp_ajax_fmb_save_payroll_adjustment', array($this, 'ajax_save_payroll_adjustment'));
        add_action('wp_ajax_fmb_get_salary_slip', array($this, 'ajax_get_salary_slip'));
    }

    /**
     * Register Submenu in FMB Store
     */
    public function register_admin_menu() {
        add_submenu_page(
            'fmb-store',
            'Employees & Payroll',
            'Employees',
            'manage_woocommerce',
            'fmb-employees',
            array($this, 'render_admin_page')
        );
    }

    /**
     * Main Dashboard Page Renderer
     */
    public function render_admin_page() {
        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'employees';
        $employees = FMB_Employee_Manager::get_employees();
        $total_emp = count($employees);
        $active_emp = count(array_filter($employees, function($e) { return $e->status === 'active'; }));

        $current_month = current_time('Y-m');
        FMB_Employee_Manager::generate_monthly_payroll($current_month);
        ?>
        <div class="wrap fmb-employee-wrap" style="max-width: 1280px; margin: 20px auto 40px;">
            <!-- Header Banner -->
            <div style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); color: #fff; padding: 24px 30px; border-radius: 12px; margin-bottom: 24px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
                <div>
                    <h1 style="color: #fff; font-size: 24px; font-weight: 700; margin: 0 0 6px 0; display: flex; align-items: center; gap: 10px;">
                        👥 Employee Management & Payroll System
                    </h1>
                    <p style="color: #94a3b8; margin: 0; font-size: 14px;">
                        কর্মচারী প্রোফাইল, কাজের পারফরম্যান্স রিপোর্ট, কমিশন ট্র্যাকিং ও মাসিক বেতন পে-রোল ম্যানেজমেন্ট।
                    </p>
                </div>
                <div style="display: flex; gap: 10px;">
                    <button type="button" id="btn-open-add-employee" class="button button-primary" style="background: #2563eb; border-color: #2563eb; padding: 6px 16px; font-size: 14px; font-weight: 600; border-radius: 6px; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 2px 4px rgba(37,99,235,0.3);">
                        <span class="dashicons dashicons-plus-alt2" style="font-size: 16px; width: 16px; height: 16px;"></span> নতুন কর্মচারী যোগ করুন
                    </button>
                </div>
            </div>

            <!-- Stats Bar -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 16px; margin-bottom: 24px;">
                <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 18px 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); border-left: 4px solid #2563eb;">
                    <div style="font-size: 13px; font-weight: 600; color: #64748b; margin-bottom: 4px;">মোট কর্মচারী (Total Staff)</div>
                    <div style="font-size: 26px; font-weight: 800; color: #1e293b;"><?= $total_emp ?> জন</div>
                    <div style="font-size: 12px; color: #16a34a; margin-top: 4px; font-weight: 500;">সক্রিয়: <?= $active_emp ?> জন</div>
                </div>
                <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 18px 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); border-left: 4px solid #16a34a;">
                    <div style="font-size: 13px; font-weight: 600; color: #64748b; margin-bottom: 4px;">চলতি মাসের কনফার্মড অর্ডার</div>
                    <?php 
                        $total_confirmed_month = 0;
                        $total_sales_month = 0;
                        $payroll_records = FMB_Employee_Manager::get_payroll_records($current_month);
                        foreach ($payroll_records as $pr) {
                            $total_confirmed_month += $pr->total_confirmed_orders;
                            $total_sales_month += $pr->total_sales_amount;
                        }
                    ?>
                    <div style="font-size: 26px; font-weight: 800; color: #16a34a;"><?= number_format($total_confirmed_month) ?> টি</div>
                    <div style="font-size: 12px; color: #64748b; margin-top: 4px;">মোট সেলস: <?= wc_price($total_sales_month) ?></div>
                </div>
                <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 18px 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); border-left: 4px solid #ea580c;">
                    <div style="font-size: 13px; font-weight: 600; color: #64748b; margin-bottom: 4px;">চলতি মাসের মোট কমিশন</div>
                    <?php 
                        $total_comm_month = 0;
                        foreach ($payroll_records as $pr) {
                            $total_comm_month += $pr->earned_commission;
                        }
                    ?>
                    <div style="font-size: 26px; font-weight: 800; color: #ea580c;"><?= wc_price($total_comm_month) ?></div>
                    <div style="font-size: 12px; color: #64748b; margin-top: 4px;">অর্ডারভিত্তিক অর্জিত কমিশন</div>
                </div>
                <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 18px 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); border-left: 4px solid #8b5cf6;">
                    <div style="font-size: 13px; font-weight: 600; color: #64748b; margin-bottom: 4px;">মাসিক মোট প্রদেয় বেতন (Payroll)</div>
                    <?php 
                        $total_net_payroll = 0;
                        foreach ($payroll_records as $pr) {
                            $total_net_payroll += $pr->net_payable;
                        }
                    ?>
                    <div style="font-size: 26px; font-weight: 800; color: #8b5cf6;"><?= wc_price($total_net_payroll) ?></div>
                    <div style="font-size: 12px; color: #64748b; margin-top: 4px;">বেসিক + কমিশন + বোনাস</div>
                </div>
            </div>

            <!-- Navigation Tabs -->
            <div style="background: #fff; border-bottom: 1px solid #e2e8f0; padding: 0 10px; border-radius: 10px 10px 0 0; display: flex; gap: 5px;">
                <a href="?page=fmb-employees&tab=employees" class="nav-tab <?= $active_tab === 'employees' ? 'nav-tab-active' : '' ?>" style="font-size: 14px; font-weight: 600; padding: 12px 18px; border-radius: 6px 6px 0 0; margin-bottom: -1px;">
                    👥 Employees (কর্মচারী তালিকা)
                </a>
                <a href="?page=fmb-employees&tab=performance" class="nav-tab <?= $active_tab === 'performance' ? 'nav-tab-active' : '' ?>" style="font-size: 14px; font-weight: 600; padding: 12px 18px; border-radius: 6px 6px 0 0; margin-bottom: -1px;">
                    📊 Performance & Reports (পারফরম্যান্স রিপোর্ট)
                </a>
                <a href="?page=fmb-employees&tab=payroll" class="nav-tab <?= $active_tab === 'payroll' ? 'nav-tab-active' : '' ?>" style="font-size: 14px; font-weight: 600; padding: 12px 18px; border-radius: 6px 6px 0 0; margin-bottom: -1px;">
                    💳 Salary & Payroll Sheet (বেতন ও পে-রোল)
                </a>
            </div>

            <!-- Tab Content Container -->
            <div style="background: #fff; border: 1px solid #e2e8f0; border-top: none; border-radius: 0 0 10px 10px; padding: 24px; box-shadow: 0 2px 4px rgba(0,0,0,0.02);">
                <?php
                if ($active_tab === 'employees') {
                    $this->render_employees_tab($employees);
                } elseif ($active_tab === 'performance') {
                    $this->render_performance_tab($employees);
                } elseif ($active_tab === 'payroll') {
                    $this->render_payroll_tab($current_month);
                }
                ?>
            </div>
        </div>

        <?php
        $this->render_modals();
        $this->render_scripts();
    }

    /**
     * Tab 1: Employees List
     */
    private function render_employees_tab($employees) {
        ?>
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px;">
            <h2 style="margin: 0; font-size: 18px; font-weight: 700; color: #1e293b;">কর্মচারী ও অর্ডার প্রসেসিং কর্মকর্তা তালিকা</h2>
            <div style="font-size: 13px; color: #64748b;">মোট: <?= count($employees) ?> জন</div>
        </div>

        <table class="wp-list-table widefat fixed striped table-view-list" style="border: 1px solid #e2e8f0; border-radius: 6px; overflow: hidden;">
            <thead>
                <tr style="background: #f8fafc;">
                    <th style="font-weight: 700; color: #475569; padding: 12px 14px;">নাম ও ইউজার</th>
                    <th style="font-weight: 700; color: #475569; padding: 12px 14px;">পদবী (Designation)</th>
                    <th style="font-weight: 700; color: #475569; padding: 12px 14px;">যোগাযোগ (Phone/Email)</th>
                    <th style="font-weight: 700; color: #475569; padding: 12px 14px;">মূল বেতন (Base Salary)</th>
                    <th style="font-weight: 700; color: #475569; padding: 12px 14px;">কমিশন রেট</th>
                    <th style="font-weight: 700; color: #475569; padding: 12px 14px;">যোগদানের তারিখ</th>
                    <th style="font-weight: 700; color: #475569; padding: 12px 14px; text-align: center;">স্ট্যাটাস</th>
                    <th style="font-weight: 700; color: #475569; padding: 12px 14px; text-align: right;">অ্যাকশন</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($employees)): ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 40px; color: #94a3b8; font-size: 14px;">
                            কোনো কর্মচারী পাওয়া যায়নি। উপরে <strong>"নতুন কর্মচারী যোগ করুন"</strong> বাটনে ক্লিক করে যোগ করুন।
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($employees as $emp): 
                        $wp_user = $emp->user_id > 0 ? get_userdata($emp->user_id) : null;
                        $comm_text = $emp->commission_type === 'percent_of_sales' 
                            ? $emp->commission_rate . '% (বিক্রির ওপর)' 
                            : '৳ ' . number_format($emp->commission_rate, 2) . ' / অর্ডার';
                    ?>
                        <tr>
                            <td style="padding: 12px 14px;">
                                <strong style="font-size: 14px; color: #1e293b;"><?= esc_html($emp->name) ?></strong>
                                <?php if ($wp_user): ?>
                                    <div style="font-size: 11px; color: #64748b;">WP Login: <code><?= esc_html($wp_user->user_login) ?></code></div>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 12px 14px;">
                                <span style="background: #f1f5f9; color: #334155; font-size: 12px; font-weight: 600; padding: 3px 8px; border-radius: 4px; border: 1px solid #e2e8f0;">
                                    <?= esc_html($emp->designation) ?>
                                </span>
                            </td>
                            <td style="padding: 12px 14px; font-size: 13px;">
                                <?php if ($emp->phone): ?>
                                    <div>📞 <a href="tel:<?= esc_attr($emp->phone) ?>"><?= esc_html($emp->phone) ?></a></div>
                                <?php endif; ?>
                                <?php if ($emp->email): ?>
                                    <div style="color: #64748b; font-size: 12px;">✉️ <?= esc_html($emp->email) ?></div>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 12px 14px; font-weight: 700; color: #0f172a; font-size: 14px;">
                                <?= wc_price($emp->base_salary) ?>
                            </td>
                            <td style="padding: 12px 14px; font-weight: 600; color: #ea580c; font-size: 13px;">
                                <?= esc_html($comm_text) ?>
                            </td>
                            <td style="padding: 12px 14px; font-size: 13px; color: #475569;">
                                <?= $emp->joining_date ? date('M d, Y', strtotime($emp->joining_date)) : '-' ?>
                            </td>
                            <td style="padding: 12px 14px; text-align: center;">
                                <span class="emp-status-badge <?= $emp->status === 'active' ? 'active' : 'inactive' ?>" data-id="<?= $emp->id ?>" style="cursor: pointer; display: inline-block; font-size: 11px; font-weight: 700; padding: 3px 8px; border-radius: 12px; <?= $emp->status === 'active' ? 'background: #dcfce7; color: #15803d;' : 'background: #fee2e2; color: #b91c1c;' ?>">
                                    <?= $emp->status === 'active' ? '● Active' : '○ Inactive' ?>
                                </span>
                            </td>
                            <td style="padding: 12px 14px; text-align: right;">
                                <button type="button" class="button button-small btn-edit-employee" data-id="<?= $emp->id ?>" style="font-size: 12px; margin-right: 4px;">
                                    ✏️ Edit
                                </button>
                                <button type="button" class="button button-small button-link-delete btn-delete-employee" data-id="<?= $emp->id ?>" data-name="<?= esc_attr($emp->name) ?>" style="font-size: 12px;">
                                    🗑️ Delete
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        <?php
    }

    /**
     * Tab 2: Performance Reports
     */
    private function render_performance_tab($employees) {
        $range = isset($_GET['range']) ? sanitize_text_field($_GET['range']) : 'this_month';
        ?>
        <!-- Performance Filter Bar -->
        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px 18px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
            <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                <label style="font-weight: 700; color: #334155; font-size: 14px;">📅 সময়কাল ফিল্টার:</label>
                <select id="perf-date-range" style="border: 1px solid #cbd5e1; border-radius: 6px; padding: 6px 10px; font-size: 13px; background: #fff;">
                    <option value="today" <?= selected($range, 'today') ?>>আজকের রিপোর্ট (Today)</option>
                    <option value="yesterday" <?= selected($range, 'yesterday') ?>>গতকালের রিপোর্ট (Yesterday)</option>
                    <option value="last_7_days" <?= selected($range, 'last_7_days') ?>>বিগত ৭ দিন (Last 7 Days)</option>
                    <option value="this_month" <?= selected($range, 'this_month') ?>>চলতি মাস (This Month)</option>
                    <option value="last_month" <?= selected($range, 'last_month') ?>>বিগত মাস (Last Month)</option>
                </select>
                <button type="button" id="btn-refresh-perf" class="button" style="padding: 4px 12px; font-size: 13px; font-weight: 600;">
                    🔄 Refresh Data
                </button>
            </div>
            <div style="font-size: 12px; color: #64748b;">
                * অর্ডার অ্যাকশন ও কনফার্মেশনের ওপর ভিত্তি করে স্বয়ংক্রিয়ভাবে হিসাবকৃত।
            </div>
        </div>

        <div id="perf-table-container">
            <table class="wp-list-table widefat fixed striped" style="border: 1px solid #e2e8f0; border-radius: 6px; overflow: hidden;">
                <thead>
                    <tr style="background: #f8fafc;">
                        <th style="font-weight: 700; color: #475569; padding: 12px 14px;">কর্মচারীর নাম</th>
                        <th style="font-weight: 700; color: #475569; padding: 12px 14px; text-align: center;">কনফার্মড অর্ডার</th>
                        <th style="font-weight: 700; color: #475569; padding: 12px 14px; text-align: center;">ডেলিভারি সম্পন্ন</th>
                        <th style="font-weight: 700; color: #475569; padding: 12px 14px; text-align: center;">ক্যানসেল / রিটার্ন</th>
                        <th style="font-weight: 700; color: #475569; padding: 12px 14px;">কনফার্মেশন রেট (%)</th>
                        <th style="font-weight: 700; color: #475569; padding: 12px 14px;">মোট বিক্রয়মূল্য (Sales)</th>
                        <th style="font-weight: 700; color: #475569; padding: 12px 14px;">অর্জিত কমিশন</th>
                        <th style="font-weight: 700; color: #475569; padding: 12px 14px; text-align: right;">সম্ভাব্য মোট আয়</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($employees)): ?>
                        <tr><td colspan="8" style="text-align: center; padding: 30px; color: #94a3b8;">কোনো ডাটা নেই।</td></tr>
                    <?php else: ?>
                        <?php foreach ($employees as $emp): 
                            $perf = FMB_Employee_Manager::get_employee_performance($emp->id);
                        ?>
                            <tr>
                                <td style="padding: 12px 14px;">
                                    <strong style="font-size: 14px; color: #1e293b;"><?= esc_html($emp->name) ?></strong>
                                    <div style="font-size: 11px; color: #64748b;"><?= esc_html($emp->designation) ?></div>
                                </td>
                                <td style="padding: 12px 14px; text-align: center; font-weight: 700; color: #16a34a; font-size: 15px;">
                                    <?= number_format($perf['confirmed_orders']) ?>
                                </td>
                                <td style="padding: 12px 14px; text-align: center; font-weight: 600; color: #0284c7; font-size: 14px;">
                                    <?= number_format($perf['delivered_orders']) ?>
                                </td>
                                <td style="padding: 12px 14px; text-align: center; font-weight: 600; color: #dc2626; font-size: 14px;">
                                    <?= number_format($perf['cancelled_orders']) ?>
                                </td>
                                <td style="padding: 12px 14px;">
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <div style="flex: 1; background: #e2e8f0; height: 8px; border-radius: 4px; overflow: hidden;">
                                            <div style="background: <?= $perf['confirmation_rate'] >= 70 ? '#16a34a' : ($perf['confirmation_rate'] >= 50 ? '#eab308' : '#dc2626') ?>; width: <?= min(100, $perf['confirmation_rate']) ?>%; height: 100%;"></div>
                                        </div>
                                        <span style="font-size: 12px; font-weight: 700; color: #334155; width: 45px; text-align: right;"><?= $perf['confirmation_rate'] ?>%</span>
                                    </div>
                                </td>
                                <td style="padding: 12px 14px; font-weight: 700; color: #0f172a;">
                                    <?= wc_price($perf['total_sales']) ?>
                                </td>
                                <td style="padding: 12px 14px; font-weight: 700; color: #ea580c; font-size: 14px;">
                                    <?= wc_price($perf['earned_commission']) ?>
                                </td>
                                <td style="padding: 12px 14px; text-align: right; font-weight: 800; color: #15803d; font-size: 15px;">
                                    <?= wc_price($perf['estimated_total']) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * Tab 3: Salary & Payroll Sheet
     */
    private function render_payroll_tab($current_month) {
        $selected_month = isset($_GET['month']) ? sanitize_text_field($_GET['month']) : $current_month;
        FMB_Employee_Manager::generate_monthly_payroll($selected_month);
        $payroll_records = FMB_Employee_Manager::get_payroll_records($selected_month);
        ?>
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 12px;">
            <div style="display: flex; align-items: center; gap: 10px;">
                <label style="font-weight: 700; color: #334155; font-size: 14px;">📆 মাসের পে-রোল শীট:</label>
                <input type="month" id="payroll-month-select" value="<?= esc_attr($selected_month) ?>" style="border: 1px solid #cbd5e1; border-radius: 6px; padding: 6px 12px; font-size: 14px; background: #fff;">
                <button type="button" id="btn-load-payroll" class="button" style="font-weight: 600;">লোড করুন</button>
            </div>
            <div>
                <button type="button" id="btn-recalculate-payroll" class="button button-primary" style="background: #0f766e; border-color: #0f766e; font-weight: 600;">
                    🔄 রিক্যালকুলেট ও রিফ্রেশ (Sync Payroll)
                </button>
            </div>
        </div>

        <table class="wp-list-table widefat fixed striped" style="border: 1px solid #e2e8f0; border-radius: 6px; overflow: hidden;">
            <thead>
                <tr style="background: #f8fafc;">
                    <th style="font-weight: 700; color: #475569; padding: 12px 14px;">কর্মচারী</th>
                    <th style="font-weight: 700; color: #475569; padding: 12px 14px;">মূল বেতন (Base)</th>
                    <th style="font-weight: 700; color: #475569; padding: 12px 14px; text-align: center;">অর্ডার সংখ্যা</th>
                    <th style="font-weight: 700; color: #475569; padding: 12px 14px;">অর্জিত কমিশন</th>
                    <th style="font-weight: 700; color: #475569; padding: 12px 14px;">বোনাস (+)</th>
                    <th style="font-weight: 700; color: #475569; padding: 12px 14px;">কর্তন (-)</th>
                    <th style="font-weight: 700; color: #475569; padding: 12px 14px;">সর্বমোট প্রদেয় (Net)</th>
                    <th style="font-weight: 700; color: #475569; padding: 12px 14px; text-align: center;">পেমেন্ট স্ট্যাটাস</th>
                    <th style="font-weight: 700; color: #475569; padding: 12px 14px; text-align: right;">অ্যাকশন</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($payroll_records)): ?>
                    <tr><td colspan="9" style="text-align: center; padding: 30px; color: #94a3b8;">কোনো পে-রোল রেকর্ড পাওয়া যায়নি।</td></tr>
                <?php else: ?>
                    <?php foreach ($payroll_records as $pr): 
                        $is_paid = ($pr->payment_status === 'paid');
                    ?>
                        <tr>
                            <td style="padding: 12px 14px;">
                                <strong style="font-size: 14px; color: #1e293b;"><?= esc_html($pr->emp_name) ?></strong>
                                <div style="font-size: 11px; color: #64748b;"><?= esc_html($pr->emp_designation) ?></div>
                            </td>
                            <td style="padding: 12px 14px; font-weight: 600; color: #334155;">
                                <?= wc_price($pr->base_salary) ?>
                            </td>
                            <td style="padding: 12px 14px; text-align: center; font-size: 13px;">
                                <span style="color: #16a34a; font-weight: 700;"><?= $pr->total_confirmed_orders ?> কনফার্ম</span>
                                <?php if ($pr->total_cancelled_orders > 0): ?>
                                    <span style="color: #dc2626; font-size: 11px;">(<?= $pr->total_cancelled_orders ?> বাতিল)</span>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 12px 14px; font-weight: 700; color: #ea580c;">
                                <?= wc_price($pr->earned_commission) ?>
                            </td>
                            <td style="padding: 12px 14px; color: #16a34a; font-weight: 600;">
                                + <?= wc_price($pr->bonus) ?>
                            </td>
                            <td style="padding: 12px 14px; color: #dc2626; font-weight: 600;">
                                - <?= wc_price($pr->deductions) ?>
                            </td>
                            <td style="padding: 12px 14px; font-weight: 800; color: #15803d; font-size: 15px;">
                                <?= wc_price($pr->net_payable) ?>
                            </td>
                            <td style="padding: 12px 14px; text-align: center;">
                                <span style="display: inline-block; font-size: 12px; font-weight: 700; padding: 3px 10px; border-radius: 12px; <?= $is_paid ? 'background: #dcfce7; color: #15803d; border: 1px solid #86efac;' : 'background: #fef3c7; color: #b45309; border: 1px solid #fde047;' ?>">
                                    <?= $is_paid ? '✅ Paid (পরিশোধিত)' : '⏳ Unpaid (বকেয়া)' ?>
                                </span>
                                <?php if ($is_paid && $pr->paid_at): ?>
                                    <div style="font-size: 10px; color: #64748b; margin-top: 2px;"><?= date('d M Y', strtotime($pr->paid_at)) ?> (<?= esc_html($pr->payment_method ?: 'Cash') ?>)</div>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 12px 14px; text-align: right;">
                                <button type="button" class="button button-small btn-view-slip" data-id="<?= $pr->id ?>" style="margin-right: 4px; font-size: 12px;">
                                    📄 পে-স্লিপ
                                </button>
                                <button type="button" class="button button-small btn-adjust-payroll" data-id="<?= $pr->id ?>" data-bonus="<?= $pr->bonus ?>" data-deductions="<?= $pr->deductions ?>" data-notes="<?= esc_attr($pr->notes) ?>" style="margin-right: 4px; font-size: 12px;">
                                    ⚙️ বোনাস/কর্তন
                                </button>
                                <?php if (!$is_paid): ?>
                                    <button type="button" class="button button-small button-primary btn-mark-paid" data-id="<?= $pr->id ?>" data-amount="<?= $pr->net_payable ?>" data-name="<?= esc_attr($pr->emp_name) ?>" style="background: #16a34a; border-color: #16a34a; font-size: 12px;">
                                        💵 বেতন প্রদান
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        <?php
    }

    /**
     * Render Modals for Add/Edit Employee, Adjust Payroll, Pay, Salary Slip
     */
    private function render_modals() {
        $users = get_users(array('fields' => array('ID', 'display_name', 'user_email', 'user_login')));
        ?>
        <!-- Modal: Add / Edit Employee -->
        <div id="modal-employee-form" style="display: none; position: fixed; z-index: 999999; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); backdrop-filter: blur(2px); align-items: center; justify-content: center;">
            <div style="background: #fff; width: 560px; max-width: 92%; border-radius: 12px; padding: 24px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.2); position: relative; max-height: 90vh; overflow-y: auto;">
                <span class="btn-close-modal" style="position: absolute; top: 14px; right: 18px; font-size: 22px; cursor: pointer; color: #94a3b8; font-weight: bold;">&times;</span>
                <h3 id="emp-modal-title" style="margin: 0 0 18px 0; font-size: 18px; font-weight: 700; color: #1e293b;">নতুন কর্মচারী যোগ করুন</h3>
                <form id="form-employee">
                    <input type="hidden" name="id" id="emp_id" value="0">
                    <div style="margin-bottom: 14px;">
                        <label style="display: block; font-weight: 600; font-size: 13px; color: #334155; margin-bottom: 4px;">ওয়ার্ডপ্রেস ইউজার একাউন্ট (ঐচ্ছিক):</label>
                        <select name="user_id" id="emp_user_id" style="width: 100%;">
                            <option value="0">-- কোনো ইউজার লিঙ্ক করবেন না --</option>
                            <?php foreach ($users as $u): ?>
                                <option value="<?= $u->ID ?>"><?= esc_html($u->display_name) ?> (<?= esc_html($u->user_login) ?> - <?= esc_html($u->user_email) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                        <small style="color: #64748b; font-size: 11px;">ইউজার লিঙ্ক করলে ওই কর্মচারীর করা অর্ডার কনফার্মেশন স্বয়ংক্রিয়ভাবে ট্র্যাক হবে।</small>
                    </div>
                    <div style="margin-bottom: 14px;">
                        <label style="display: block; font-weight: 600; font-size: 13px; color: #334155; margin-bottom: 4px;">কর্মচারীর পূর্ণ নাম <span style="color:#ef4444;">*</span>:</label>
                        <input type="text" name="name" id="emp_name" required style="width: 100%;" placeholder="e.g. মোঃ রহিম আহমেদ">
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 14px;">
                        <div>
                            <label style="display: block; font-weight: 600; font-size: 13px; color: #334155; margin-bottom: 4px;">মোবাইল নম্বর:</label>
                            <input type="text" name="phone" id="emp_phone" style="width: 100%;" placeholder="017XXXXXXXX">
                        </div>
                        <div>
                            <label style="display: block; font-weight: 600; font-size: 13px; color: #334155; margin-bottom: 4px;">ইমেইল ঠিকানা:</label>
                            <input type="email" name="email" id="emp_email" style="width: 100%;" placeholder="name@domain.com">
                        </div>
                    </div>
                    <div style="margin-bottom: 14px;">
                        <label style="display: block; font-weight: 600; font-size: 13px; color: #334155; margin-bottom: 4px;">পদবী (Designation):</label>
                        <input type="text" name="designation" id="emp_designation" style="width: 100%;" value="Order Confirmation Officer" placeholder="e.g. Order Confirmation Officer">
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 14px;">
                        <div>
                            <label style="display: block; font-weight: 600; font-size: 13px; color: #334155; margin-bottom: 4px;">মূল মাসিক বেতন (Base Salary):</label>
                            <input type="number" step="0.01" name="base_salary" id="emp_base_salary" style="width: 100%;" value="0.00" placeholder="10000">
                        </div>
                        <div>
                            <label style="display: block; font-weight: 600; font-size: 13px; color: #334155; margin-bottom: 4px;">কমিশন মডেল:</label>
                            <select name="commission_type" id="emp_commission_type" style="width: 100%;">
                                <option value="fixed_per_order">নির্দিষ্ট টাকা প্রতি অর্ডারে (Fixed ৳)</option>
                                <option value="percent_of_sales">বিক্রির শতকরা হার (% of Sales)</option>
                            </select>
                        </div>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 14px;">
                        <div>
                            <label style="display: block; font-weight: 600; font-size: 13px; color: #334155; margin-bottom: 4px;">কমিশন রেট (Rate):</label>
                            <input type="number" step="0.01" name="commission_rate" id="emp_commission_rate" style="width: 100%;" value="0.00" placeholder="e.g. 20 (৳) or 2 (%)">
                        </div>
                        <div>
                            <label style="display: block; font-weight: 600; font-size: 13px; color: #334155; margin-bottom: 4px;">যোগদানের তারিখ:</label>
                            <input type="date" name="joining_date" id="emp_joining_date" style="width: 100%;" value="<?= current_time('Y-m-d') ?>">
                        </div>
                    </div>
                    <div style="margin-bottom: 18px;">
                        <label style="display: block; font-weight: 600; font-size: 13px; color: #334155; margin-bottom: 4px;">নোট (Optional):</label>
                        <textarea name="notes" id="emp_notes" rows="2" style="width: 100%;" placeholder="অতিরিক্ত কোনো তথ্য..."></textarea>
                    </div>
                    <div style="display: flex; justify-content: flex-end; gap: 10px; border-top: 1px solid #e2e8f0; padding-top: 14px;">
                        <button type="button" class="button btn-close-modal">বাতিল</button>
                        <button type="submit" class="button button-primary" style="background: #2563eb; border-color: #2563eb; font-weight: 600;">সংরক্ষণ করুন (Save Employee)</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Modal: Mark Payroll Paid -->
        <div id="modal-pay-payroll" style="display: none; position: fixed; z-index: 999999; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); backdrop-filter: blur(2px); align-items: center; justify-content: center;">
            <div style="background: #fff; width: 440px; max-width: 92%; border-radius: 12px; padding: 24px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.2); position: relative;">
                <span class="btn-close-modal" style="position: absolute; top: 14px; right: 18px; font-size: 22px; cursor: pointer; color: #94a3b8; font-weight: bold;">&times;</span>
                <h3 style="margin: 0 0 14px 0; font-size: 18px; font-weight: 700; color: #1e293b;">💵 বেতন পরিশোধ নিশ্চিতকরণ</h3>
                <form id="form-pay-payroll">
                    <input type="hidden" name="payroll_id" id="pay_payroll_id">
                    <div style="background: #f0fdf4; border: 1px solid #86efac; border-radius: 8px; padding: 12px; margin-bottom: 16px;">
                        <div style="font-size: 13px; color: #166534;">কর্মচারী: <strong id="pay_emp_name"></strong></div>
                        <div style="font-size: 16px; color: #15803d; font-weight: 800; margin-top: 4px;">মোট প্রদেয় বেতন: <span id="pay_amount"></span></div>
                    </div>
                    <div style="margin-bottom: 14px;">
                        <label style="display: block; font-weight: 600; font-size: 13px; color: #334155; margin-bottom: 4px;">পেমেন্ট মেথড:</label>
                        <select name="payment_method" id="pay_method" style="width: 100%;">
                            <option value="bKash">bKash (বিকাশ)</option>
                            <option value="Nagad">Nagad (নগদ)</option>
                            <option value="Bank Transfer">Bank Transfer (ব্যাংক)</option>
                            <option value="Cash">Cash (নগদ টাকা)</option>
                        </select>
                    </div>
                    <div style="margin-bottom: 14px;">
                        <label style="display: block; font-weight: 600; font-size: 13px; color: #334155; margin-bottom: 4px;">ট্রানজেকশন আইডি / রেফারেন্স (ঐচ্ছিক):</label>
                        <input type="text" name="payment_trx_id" id="pay_trx_id" style="width: 100%;" placeholder="e.g. TrxID987654">
                    </div>
                    <div style="display: flex; justify-content: flex-end; gap: 10px; border-top: 1px solid #e2e8f0; padding-top: 14px;">
                        <button type="button" class="button btn-close-modal">বাতিল</button>
                        <button type="submit" class="button button-primary" style="background: #16a34a; border-color: #16a34a; font-weight: 600;">পেইড হিসেবে চিহ্নিত করুন</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Modal: Payroll Bonus / Deduction Adjustments -->
        <div id="modal-adjust-payroll" style="display: none; position: fixed; z-index: 999999; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); backdrop-filter: blur(2px); align-items: center; justify-content: center;">
            <div style="background: #fff; width: 440px; max-width: 92%; border-radius: 12px; padding: 24px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.2); position: relative;">
                <span class="btn-close-modal" style="position: absolute; top: 14px; right: 18px; font-size: 22px; cursor: pointer; color: #94a3b8; font-weight: bold;">&times;</span>
                <h3 style="margin: 0 0 14px 0; font-size: 18px; font-weight: 700; color: #1e293b;">⚙️ বোনাস ও কর্তন সমন্বয় (Adjustments)</h3>
                <form id="form-adjust-payroll">
                    <input type="hidden" name="payroll_id" id="adj_payroll_id">
                    <div style="margin-bottom: 14px;">
                        <label style="display: block; font-weight: 600; font-size: 13px; color: #334155; margin-bottom: 4px;">বোনাস (অতিরিক্ত যোগ ৳):</label>
                        <input type="number" step="0.01" name="bonus" id="adj_bonus" style="width: 100%;" value="0.00">
                    </div>
                    <div style="margin-bottom: 14px;">
                        <label style="display: block; font-weight: 600; font-size: 13px; color: #334155; margin-bottom: 4px;">কর্তন (জরিমানা/অ্যাডভান্স কর্তন ৳):</label>
                        <input type="number" step="0.01" name="deductions" id="adj_deductions" style="width: 100%;" value="0.00">
                    </div>
                    <div style="margin-bottom: 14px;">
                        <label style="display: block; font-weight: 600; font-size: 13px; color: #334155; margin-bottom: 4px;">নোট/কারণ:</label>
                        <textarea name="notes" id="adj_notes" rows="2" style="width: 100%;" placeholder="e.g. ঈদুল ফিতর বোনাস বা অতিরিক্ত ছুটি কর্তন"></textarea>
                    </div>
                    <div style="display: flex; justify-content: flex-end; gap: 10px; border-top: 1px solid #e2e8f0; padding-top: 14px;">
                        <button type="button" class="button btn-close-modal">বাতিল</button>
                        <button type="submit" class="button button-primary" style="font-weight: 600;">সমন্বয় সেভ করুন</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Modal: Printable Salary Slip -->
        <div id="modal-salary-slip" style="display: none; position: fixed; z-index: 999999; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.65); backdrop-filter: blur(2px); align-items: center; justify-content: center;">
            <div style="background: #fff; width: 620px; max-width: 92%; border-radius: 12px; padding: 28px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.2); position: relative; max-height: 90vh; overflow-y: auto;">
                <span class="btn-close-modal" style="position: absolute; top: 14px; right: 18px; font-size: 22px; cursor: pointer; color: #94a3b8; font-weight: bold;">&times;</span>
                <div id="salary-slip-content">
                    <!-- Loaded via AJAX -->
                </div>
                <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px; border-top: 1px solid #e2e8f0; padding-top: 14px;" class="no-print">
                    <button type="button" class="button btn-close-modal">বন্ধ করুন</button>
                    <button type="button" onclick="window.print();" class="button button-primary" style="background: #0f172a; border-color: #0f172a; font-weight: 600;">🖨️ প্রিন্ট করুন (Print Slip)</button>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Inline Scripts & AJAX Handlers
     */
    private function render_scripts() {
        ?>
        <script>
        jQuery(document).ready(function($) {
            // Open Add Modal
            $('#btn-open-add-employee').on('click', function() {
                $('#form-employee')[0].reset();
                $('#emp_id').val(0);
                $('#emp-modal-title').text('নতুন কর্মচারী যোগ করুন');
                $('#modal-employee-form').fadeIn(200).css('display', 'flex');
            });

            // Close Modals
            $('.btn-close-modal, #modal-employee-form, #modal-pay-payroll, #modal-adjust-payroll, #modal-salary-slip').on('click', function(e) {
                if (e.target === this || $(e.target).hasClass('btn-close-modal')) {
                    $('#modal-employee-form, #modal-pay-payroll, #modal-adjust-payroll, #modal-salary-slip').fadeOut(150);
                }
            });

            // Save Employee Form AJAX
            $('#form-employee').on('submit', function(e) {
                e.preventDefault();
                var formData = $(this).serialize() + '&action=fmb_save_employee&_nonce=<?= wp_create_nonce("fmb_emp_nonce") ?>';
                
                $.post(ajaxurl, formData, function(res) {
                    if (res.success) {
                        alert('কর্মচারীর তথ্য সফলভাবে সংরক্ষণ করা হয়েছে!');
                        window.location.reload();
                    } else {
                        alert(res.data.message || 'Error saving employee');
                    }
                });
            });

            // Edit Employee Button
            $('.btn-edit-employee').on('click', function() {
                var id = $(this).data('id');
                $.post(ajaxurl, { action: 'fmb_get_employee', id: id, _nonce: '<?= wp_create_nonce("fmb_emp_nonce") ?>' }, function(res) {
                    if (res.success && res.data) {
                        var d = res.data;
                        $('#emp_id').val(d.id);
                        $('#emp_user_id').val(d.user_id);
                        $('#emp_name').val(d.name);
                        $('#emp_phone').val(d.phone);
                        $('#emp_email').val(d.email);
                        $('#emp_designation').val(d.designation);
                        $('#emp_base_salary').val(d.base_salary);
                        $('#emp_commission_type').val(d.commission_type);
                        $('#emp_commission_rate').val(d.commission_rate);
                        $('#emp_joining_date').val(d.joining_date);
                        $('#emp_notes').val(d.notes);

                        $('#emp-modal-title').text('কর্মচারীর তথ্য পরিবর্তন করুন (Edit Employee)');
                        $('#modal-employee-form').fadeIn(200).css('display', 'flex');
                    }
                });
            });

            // Delete Employee
            $('.btn-delete-employee').on('click', function() {
                var id = $(this).data('id');
                var name = $(this).data('name');
                if (confirm('আপনি কি নিশ্চিত যে "' + name + '" কে মুছে ফেলতে চান?')) {
                    $.post(ajaxurl, { action: 'fmb_delete_employee', id: id, _nonce: '<?= wp_create_nonce("fmb_emp_nonce") ?>' }, function(res) {
                        if (res.success) {
                            window.location.reload();
                        } else {
                            alert('মুছে ফেলা সম্ভব হয়নি।');
                        }
                    });
                }
            });

            // Status Toggle
            $('.emp-status-badge').on('click', function() {
                var $badge = $(this);
                var id = $badge.data('id');
                $.post(ajaxurl, { action: 'fmb_toggle_employee_status', id: id, _nonce: '<?= wp_create_nonce("fmb_emp_nonce") ?>' }, function(res) {
                    if (res.success) {
                        window.location.reload();
                    }
                });
            });

            // Filter Performance
            $('#perf-date-range, #btn-refresh-perf').on('change click', function() {
                var range = $('#perf-date-range').val();
                window.location.href = '?page=fmb-employees&tab=performance&range=' + range;
            });

            // Payroll Month Change
            $('#btn-load-payroll').on('click', function() {
                var month = $('#payroll-month-select').val();
                window.location.href = '?page=fmb-employees&tab=payroll&month=' + month;
            });

            // Recalculate Payroll
            $('#btn-recalculate-payroll').on('click', function() {
                var month = $('#payroll-month-select').val();
                $.post(ajaxurl, { action: 'fmb_generate_payroll', month: month, _nonce: '<?= wp_create_nonce("fmb_emp_nonce") ?>' }, function(res) {
                    if (res.success) {
                        alert('পে-রোল শীট সফলভাবে রিক্যালকুলেট ও রিফ্রেশ করা হয়েছে!');
                        window.location.reload();
                    }
                });
            });

            // Mark Paid Modal
            $('.btn-mark-paid').on('click', function() {
                var id = $(this).data('id');
                var name = $(this).data('name');
                var amount = $(this).data('amount');
                $('#pay_payroll_id').val(id);
                $('#pay_emp_name').text(name);
                $('#pay_amount').text('৳ ' + amount);
                $('#modal-pay-payroll').fadeIn(200).css('display', 'flex');
            });

            // Submit Pay Payroll
            $('#form-pay-payroll').on('submit', function(e) {
                e.preventDefault();
                var data = $(this).serialize() + '&action=fmb_mark_payroll_paid&_nonce=<?= wp_create_nonce("fmb_emp_nonce") ?>';
                $.post(ajaxurl, data, function(res) {
                    if (res.success) {
                        alert('বেতন সফলভাবে পরিশোধ হিসেবে চিহ্নিত করা হয়েছে!');
                        window.location.reload();
                    }
                });
            });

            // Open Adjust Modal
            $('.btn-adjust-payroll').on('click', function() {
                var id = $(this).data('id');
                var bonus = $(this).data('bonus');
                var deductions = $(this).data('deductions');
                var notes = $(this).data('notes');

                $('#adj_payroll_id').val(id);
                $('#adj_bonus').val(bonus);
                $('#adj_deductions').val(deductions);
                $('#adj_notes').val(notes);
                $('#modal-adjust-payroll').fadeIn(200).css('display', 'flex');
            });

            // Submit Adjust Payroll
            $('#form-adjust-payroll').on('submit', function(e) {
                e.preventDefault();
                var data = $(this).serialize() + '&action=fmb_save_payroll_adjustment&_nonce=<?= wp_create_nonce("fmb_emp_nonce") ?>';
                $.post(ajaxurl, data, function(res) {
                    if (res.success) {
                        window.location.reload();
                    }
                });
            });

            // View Salary Slip Modal
            $('.btn-view-slip').on('click', function() {
                var id = $(this).data('id');
                $('#salary-slip-content').html('<div style="text-align:center; padding:30px;">লোড হচ্ছে...</div>');
                $('#modal-salary-slip').fadeIn(200).css('display', 'flex');

                $.post(ajaxurl, { action: 'fmb_get_salary_slip', id: id, _nonce: '<?= wp_create_nonce("fmb_emp_nonce") ?>' }, function(res) {
                    if (res.success) {
                        $('#salary-slip-content').html(res.data.html);
                    } else {
                        $('#salary-slip-content').html('<div style="color:red;">Error loading slip.</div>');
                    }
                });
            });
        });
        </script>
        <?php
    }

    // =========================================================
    // AJAX Handlers
    // =========================================================

    public function ajax_save_employee() {
        check_ajax_referer('fmb_emp_nonce', '_nonce');
        $res = FMB_Employee_Manager::save_employee($_POST);
        if (is_wp_error($res)) {
            wp_send_json_error(['message' => $res->get_error_message()]);
        }
        wp_send_json_success(['id' => $res]);
    }

    public function ajax_get_employee() {
        check_ajax_referer('fmb_emp_nonce', '_nonce');
        $id = intval($_POST['id'] ?? 0);
        $emp = FMB_Employee_Manager::get_employee($id);
        if ($emp) {
            wp_send_json_success($emp);
        }
        wp_send_json_error();
    }

    public function ajax_delete_employee() {
        check_ajax_referer('fmb_emp_nonce', '_nonce');
        $id = intval($_POST['id'] ?? 0);
        FMB_Employee_Manager::delete_employee($id);
        wp_send_json_success();
    }

    public function ajax_toggle_employee_status() {
        check_ajax_referer('fmb_emp_nonce', '_nonce');
        $id = intval($_POST['id'] ?? 0);
        $emp = FMB_Employee_Manager::get_employee($id);
        if ($emp) {
            global $wpdb;
            $new_status = ($emp->status === 'active') ? 'inactive' : 'active';
            $wpdb->update($wpdb->prefix . 'fmb_employees', ['status' => $new_status], ['id' => $id]);
            wp_send_json_success();
        }
        wp_send_json_error();
    }

    public function ajax_generate_payroll() {
        check_ajax_referer('fmb_emp_nonce', '_nonce');
        $month = sanitize_text_field($_POST['month'] ?? current_time('Y-m'));
        FMB_Employee_Manager::generate_monthly_payroll($month);
        wp_send_json_success();
    }

    public function ajax_mark_payroll_paid() {
        check_ajax_referer('fmb_emp_nonce', '_nonce');
        global $wpdb;
        $id = intval($_POST['payroll_id'] ?? 0);
        $method = sanitize_text_field($_POST['payment_method'] ?? 'Cash');
        $trx_id = sanitize_text_field($_POST['payment_trx_id'] ?? '');

        $wpdb->update(
            $wpdb->prefix . 'fmb_employee_payroll',
            [
                'payment_status' => 'paid',
                'paid_at'        => current_time('mysql'),
                'payment_method' => $method,
                'payment_trx_id' => $trx_id
            ],
            ['id' => $id]
        );
        wp_send_json_success();
    }

    public function ajax_save_payroll_adjustment() {
        check_ajax_referer('fmb_emp_nonce', '_nonce');
        global $wpdb;
        $id = intval($_POST['payroll_id'] ?? 0);
        $bonus = max(0, floatval($_POST['bonus'] ?? 0));
        $deductions = max(0, floatval($_POST['deductions'] ?? 0));
        $notes = sanitize_textarea_field($_POST['notes'] ?? '');

        $table = $wpdb->prefix . 'fmb_employee_payroll';
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id));
        if ($row) {
            $net = (floatval($row->base_salary) + floatval($row->earned_commission) + $bonus) - $deductions;
            $wpdb->update(
                $table,
                [
                    'bonus'       => $bonus,
                    'deductions'  => $deductions,
                    'net_payable' => max(0, $net),
                    'notes'       => $notes
                ],
                ['id' => $id]
            );
            wp_send_json_success();
        }
        wp_send_json_error();
    }

    public function ajax_get_salary_slip() {
        check_ajax_referer('fmb_emp_nonce', '_nonce');
        global $wpdb;
        $id = intval($_POST['id'] ?? 0);
        $payroll_table = $wpdb->prefix . 'fmb_employee_payroll';
        $emp_table     = $wpdb->prefix . 'fmb_employees';

        $pr = $wpdb->get_row($wpdb->prepare("
            SELECT p.*, e.name as emp_name, e.phone as emp_phone, e.email as emp_email, e.designation as emp_designation, e.commission_type, e.commission_rate, e.joining_date
            FROM {$payroll_table} p
            JOIN {$emp_table} e ON p.employee_id = e.id
            WHERE p.id = %d
        ", $id));

        if (!$pr) {
            wp_send_json_error();
        }

        $month_formatted = date('F Y', strtotime($pr->month_year . '-01'));

        ob_start();
        ?>
        <div style="font-family: Arial, sans-serif; color: #1e293b;">
            <!-- Header -->
            <div style="text-align: center; border-bottom: 2px solid #0f172a; padding-bottom: 12px; margin-bottom: 18px;">
                <h2 style="margin: 0; font-size: 20px; font-weight: 800; color: #0f172a; text-transform: uppercase;"><?= get_bloginfo('name') ?></h2>
                <div style="font-size: 13px; font-weight: 600; color: #475569; margin-top: 4px;">বেতন বিবরণী / SALARY PAYSLIP — <?= esc_html($month_formatted) ?></div>
            </div>

            <!-- Employee Info Grid -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 18px; font-size: 13px; background: #f8fafc; padding: 12px; border-radius: 6px;">
                <div>
                    <div><strong>কর্মচারীর নাম:</strong> <?= esc_html($pr->emp_name) ?></div>
                    <div><strong>পদবী:</strong> <?= esc_html($pr->emp_designation) ?></div>
                    <div><strong>মোবাইল:</strong> <?= esc_html($pr->emp_phone ?: '-') ?></div>
                </div>
                <div style="text-align: right;">
                    <div><strong>মাসের সময়কাল:</strong> <?= esc_html($month_formatted) ?></div>
                    <div><strong>স্ট্যাটাস:</strong> <span style="font-weight: 700; color: <?= $pr->payment_status === 'paid' ? '#16a34a' : '#ea580c' ?>;"><?= strtoupper($pr->payment_status) ?></span></div>
                    <?php if ($pr->paid_at): ?>
                        <div><strong>পরিশোধের তারিখ:</strong> <?= date('d M Y', strtotime($pr->paid_at)) ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Earnings vs Deductions Table -->
            <table style="width: 100%; border-collapse: collapse; margin-bottom: 18px; font-size: 13px;">
                <thead>
                    <tr style="background: #0f172a; color: #fff;">
                        <th style="padding: 8px 12px; text-align: left;">আয়ের বিবরণ (Earnings)</th>
                        <th style="padding: 8px 12px; text-align: right;">টাকার পরিমাণ</th>
                        <th style="padding: 8px 12px; text-align: left; border-left: 1px solid #475569;">কর্তনের বিবরণ (Deductions)</th>
                        <th style="padding: 8px 12px; text-align: right;">টাকার পরিমাণ</th>
                    </tr>
                </thead>
                <tbody>
                    <tr style="border-bottom: 1px solid #e2e8f0;">
                        <td style="padding: 8px 12px;">মূল বেতন (Basic Salary)</td>
                        <td style="padding: 8px 12px; text-align: right; font-weight: 600;"><?= wc_price($pr->base_salary) ?></td>
                        <td style="padding: 8px 12px; border-left: 1px solid #e2e8f0;">জরিমানা / অ্যাডভান্স কর্তন</td>
                        <td style="padding: 8px 12px; text-align: right; color: #dc2626; font-weight: 600;"><?= wc_price($pr->deductions) ?></td>
                    </tr>
                    <tr style="border-bottom: 1px solid #e2e8f0;">
                        <td style="padding: 8px 12px;">
                            অর্জিত কমিশন 
                            <small style="display:block; color:#64748b; font-size:11px;">(<?= $pr->total_confirmed_orders ?> টি কনফার্মড অর্ডার)</small>
                        </td>
                        <td style="padding: 8px 12px; text-align: right; color: #16a34a; font-weight: 600;"><?= wc_price($pr->earned_commission) ?></td>
                        <td style="padding: 8px 12px; border-left: 1px solid #e2e8f0;">-</td>
                        <td style="padding: 8px 12px; text-align: right;">-</td>
                    </tr>
                    <tr style="border-bottom: 1px solid #e2e8f0;">
                        <td style="padding: 8px 12px;">বোনাস / ইনসেন্টিভ (+)</td>
                        <td style="padding: 8px 12px; text-align: right; color: #16a34a; font-weight: 600;"><?= wc_price($pr->bonus) ?></td>
                        <td style="padding: 8px 12px; border-left: 1px solid #e2e8f0;">-</td>
                        <td style="padding: 8px 12px; text-align: right;">-</td>
                    </tr>
                    <tr style="background: #f8fafc; font-weight: bold; border-top: 2px solid #cbd5e1;">
                        <td style="padding: 10px 12px;">মোট আয় (Total Earnings)</td>
                        <td style="padding: 10px 12px; text-align: right; color: #16a34a;"><?= wc_price($pr->base_salary + $pr->earned_commission + $pr->bonus) ?></td>
                        <td style="padding: 10px 12px; border-left: 1px solid #e2e8f0;">মোট কর্তন (Total Deductions)</td>
                        <td style="padding: 10px 12px; text-align: right; color: #dc2626;"><?= wc_price($pr->deductions) ?></td>
                    </tr>
                </tbody>
            </table>

            <!-- Net Payable Box -->
            <div style="background: #f0fdf4; border: 2px solid #86efac; border-radius: 8px; padding: 14px 20px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
                <div style="font-size: 15px; font-weight: 700; color: #166534;">সর্বমোট প্রদেয় বেতন (Net Salary Payable):</div>
                <div style="font-size: 22px; font-weight: 800; color: #15803d;"><?= wc_price($pr->net_payable) ?></div>
            </div>

            <?php if ($pr->notes): ?>
                <div style="font-size: 12px; color: #64748b; margin-bottom: 20px;">
                    <strong>নোট:</strong> <?= esc_html($pr->notes) ?>
                </div>
            <?php endif; ?>

            <!-- Signatures -->
            <div style="display: flex; justify-content: space-between; margin-top: 45px; padding-top: 10px; font-size: 12px;">
                <div style="border-top: 1px dashed #94a3b8; width: 160px; text-align: center;">কর্মচারীর স্বাক্ষর</div>
                <div style="border-top: 1px dashed #94a3b8; width: 160px; text-align: center;">অনুমোদনকারী স্বাক্ষর</div>
            </div>
        </div>
        <?php
        $html = ob_get_clean();
        wp_send_json_success(['html' => $html]);
    }
}

// Instantiate admin manager
FMB_Employee_Admin::get_instance();
