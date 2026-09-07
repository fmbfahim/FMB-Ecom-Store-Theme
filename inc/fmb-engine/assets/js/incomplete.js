/**
 * Incomplete Checkouts Admin UI Script
 * Handles tab navigation, copy to clipboard, column toggling, and AJAX interactions.
 */

// --- Globally Defined Functions ---
window.showTabContent = function(contentIdToShow, clickedTab, subtitle) {
    const tabPanes = jQuery('.tab-pane');
    const tabButtons = jQuery('.tab-button');
    const subtitleElement = jQuery('#current-view-subtitle');

    tabPanes.each(function() {
        jQuery(this).hide().removeClass('active-pane');
    });

    tabButtons.each(function() {
        jQuery(this).removeClass('active').attr('aria-current', 'false');
    });

    const selectedPane = jQuery('#' + contentIdToShow);
    if (selectedPane.length) {
        selectedPane.show().addClass('active-pane');
    } else {
        console.warn('[showTabContent] Pane element not found for ID:', contentIdToShow);
    }

    if (clickedTab) {
        jQuery(clickedTab).addClass('active').attr('aria-current', 'page');
    }

    if (subtitleElement.length && subtitle) {
        subtitleElement.text(subtitle);
    }

    // If analytics tab is selected, fetch live data
    if (contentIdToShow === 'analytics-content' && typeof window.fetchLiveAnalyticsData === 'function') {
        window.fetchLiveAnalyticsData();
    }

    // If Incomplete Orders tab is shown, re-render column toggles if necessary
    if (contentIdToShow === 'incomplete-orders-content' && typeof window.renderICTableColumnCheckboxes === 'function') {
        window.renderICTableColumnCheckboxes();
        // Also attach pagination handlers when showing this tab
        setTimeout(function() {
            if (typeof window.attachPaginationHandlers === 'function') {
                window.attachPaginationHandlers();
            }
        }, 100);
    }

    if (contentIdToShow) {
        try {
            localStorage.setItem('icActiveAdminTab', contentIdToShow);
            localStorage.setItem('icActiveAdminTabButton', clickedTab ? clickedTab.id : '');
            localStorage.setItem('icActiveAdminSubtitle', subtitle || '');
        } catch (e) {
            console.warn('IC Dev: Could not write to localStorage for tab persistence.');
        }
    }
};

window.populateAnalyticsTab = function(data) {
    if (!data || typeof data !== 'object') {
        console.warn('IC Dev: Invalid or no analytics data received for populateAnalyticsTab.');
        const analyticsContainer = jQuery('#analytics-content');
        if (analyticsContainer.length) {
            analyticsContainer.find('.font-medium[id^="analytics-"], .font-medium[id^="acr-"], .font-medium[id^="rr-"], #db-stats-active, #db-stats-oldest, #db-stats-newest').text('Error');
            analyticsContainer.find('.progress-bar-fill').css('width', '0%');
            const topRecoveryTableBody = jQuery('#top-recovery-days-table tbody');
            if (topRecoveryTableBody.length) topRecoveryTableBody.html('<tr><td colspan="3" class="text-center py-4">Error loading analytics data.</td></tr>');
        }
        return;
    }

    const currencySymbol = data.currency_symbol || '$';
    const summaryPeriods = data.placeholders && data.placeholders.summary_periods ? data.placeholders.summary_periods : ['one_day', 'seven_day', 'thirty_day', 'all_time'];
    const idSuffixesArray = data.placeholders && data.placeholders.summary_ids ? data.placeholders.summary_ids : ['24h', '7d', '30d', 'all'];

    summaryPeriods.forEach((periodKey, index) => {
        const idSuffix = idSuffixesArray[index];
        const summary_total_key = `${periodKey}_total`;
        const summary_success_key = `${periodKey}_success`;
        const summaryTotal = data.summary && typeof data.summary[summary_total_key] !== 'undefined' ? parseInt(data.summary[summary_total_key]) : 0;
        const summarySuccess = data.summary && typeof data.summary[summary_success_key] !== 'undefined' ? parseInt(data.summary[summary_success_key]) : 0;
        const summaryPending = summaryTotal - summarySuccess;
        const summaryIdPrefix = `analytics-${idSuffix}-`;

        const totalEl = jQuery(`#${summaryIdPrefix}total`);
        const completedEl = jQuery(`#${summaryIdPrefix}completed`);
        const pendingEl = jQuery(`#${summaryIdPrefix}pending`);
        const conversionEl = jQuery(`#${summaryIdPrefix}conversion`);
        const progressEl = jQuery(`#${summaryIdPrefix}progress`);

        if(totalEl.length) totalEl.text(summaryTotal);
        if(completedEl.length) completedEl.text(summarySuccess);
        if(pendingEl.length) pendingEl.text(summaryPending);

        const conversionRate = (summaryTotal > 0) ? ((summarySuccess / summaryTotal) * 100).toFixed(1) : 0;
        if(conversionEl.length) conversionEl.text(`${conversionRate}%`);
        if(progressEl.length) progressEl.css('width', `${conversionRate}%`);

        // --- Abandoned Checkout Rate Cards Data ---
        const acrIdPrefix = `acr-${idSuffix}-`;
        const acrData = data.checkout_rate && data.checkout_rate[periodKey] ? data.checkout_rate[periodKey] : {};

        const acrCompletedEl = jQuery(`#${acrIdPrefix}completed`);
        const acrAbandonedEl = jQuery(`#${acrIdPrefix}abandoned`);
        const acrSessionsEl = jQuery(`#${acrIdPrefix}sessions`);
        const acrRateEl = jQuery(`#${acrIdPrefix}rate`);
        const acrProgressEl = jQuery(`#${acrIdPrefix}progress`);

        if(acrCompletedEl.length) acrCompletedEl.text(acrData.completed_orders || 0);
        if(acrAbandonedEl.length) acrAbandonedEl.text(acrData.abandoned_carts || 0);
        if(acrSessionsEl.length) acrSessionsEl.text(acrData.total_sessions || 0);
        if(acrRateEl.length) acrRateEl.text(`${parseFloat(acrData.abandon_rate || 0).toFixed(1)}%`);
        if(acrProgressEl.length) acrProgressEl.css('width', `${parseFloat(acrData.abandon_rate || 0).toFixed(1)}%`);

        // --- Revenue Recovery Cards Data ---
        const rrIdPrefix = `rr-${idSuffix}-`;
        const rrData = data.revenue_recovery && data.revenue_recovery[periodKey] ? data.revenue_recovery[periodKey] : {};

        const rrCountEl = jQuery(`#${rrIdPrefix}count`);
        const rrValueEl = jQuery(`#${rrIdPrefix}value`);
        if(rrCountEl.length) rrCountEl.text(rrData.count || 0);
        if(rrValueEl.length) rrValueEl.html(`${currencySymbol}${parseFloat(rrData.value || 0).toFixed(2)}`);
    });

    // --- DB Stats ---
    if (data.db_stats) {
        const activeEl = jQuery('#db-stats-active');
        const oldestEl = jQuery('#db-stats-oldest');
        const newestEl = jQuery('#db-stats-newest');
        if(activeEl.length) activeEl.text(data.db_stats.active || 0);
        if(oldestEl.length) oldestEl.text(data.db_stats.oldest || 'N/A');
        if(newestEl.length) newestEl.text(data.db_stats.newest || 'N/A');
    }

    // --- Top Recovery Days Table ---
    const topRecoveryTableBody = jQuery('#top-recovery-days-table tbody');
    if (data.top_recovery_days && topRecoveryTableBody.length) {
        topRecoveryTableBody.empty();
        if (data.top_recovery_days.length > 0) {
            data.top_recovery_days.forEach(day => {
                let formattedDate = day.date || 'N/A';
                if (day.date) {
                    try {
                        const dateParts = day.date.split('-');
                        if (dateParts.length === 3) {
                            const dateObj = new Date(parseInt(dateParts[0]), parseInt(dateParts[1]) - 1, parseInt(dateParts[2]));
                            if (!isNaN(dateObj.getTime())) {
                                formattedDate = dateObj.toLocaleDateString(undefined, { year: 'numeric', month: 'long', day: 'numeric' });
                            }
                        }
                    } catch (e) { console.warn("Error formatting date for top recovery:", day.date, e); }
                }
                const rowHtml = `
                    <tr>
                        <td>${formattedDate}</td>
                        <td>${day.count || 0}</td>
                        <td class="text-right">${currencySymbol}${parseFloat(day.value || 0).toFixed(2)}</td>
                    </tr>`;
                topRecoveryTableBody.append(rowHtml);
            });
        } else {
            topRecoveryTableBody.html('<tr><td colspan="3" class="text-center py-4">No recovery data available for the selected period.</td></tr>');
        }
    }
};

window.handleScheduleCleanupNow = function(event) {
    const button = jQuery(event.target);
    const statusDataForAlerts = (typeof ic_ajax_object !== 'undefined' && ic_ajax_object.labels) ? ic_ajax_object.labels : {};
    const originalText = button.text();
    button.prop('disabled', true).text(statusDataForAlerts.scheduling || 'Scheduling...');

    jQuery.ajax({
        url: (typeof ic_ajax_object !== 'undefined') ? ic_ajax_object.ajax_url : '',
        type: 'POST',
        data: {
            action: 'schedule_cleanup_now',
            nonce: (typeof ic_ajax_object !== 'undefined') ? ic_ajax_object.schedule_cleanup_nonce : ''
        },
        success: function(data) {
            alert(data.data.message || (data.success ? (statusDataForAlerts.schedule_success || 'Scheduled!') : (statusDataForAlerts.schedule_fail || 'Failed!')));
            if(data.success) location.reload();
        },
        error: function(err) {
            console.error("Schedule Cleanup Error:", err);
            alert(statusDataForAlerts.error_generic || 'Error.');
        },
        complete: function() {
            button.prop('disabled', false).text(originalText);
        }
    });
};

window.handleRunCleanupNow = function(event) {
    const button = jQuery(event.target);
    const statusDataForAlerts = (typeof ic_ajax_object !== 'undefined' && ic_ajax_object.labels) ? ic_ajax_object.labels : {};
    const confirmMsg = statusDataForAlerts.run_confirm_text || 'Run cleanup now?';
    if (!confirm(confirmMsg)) return;

    const originalText = button.text();
    button.prop('disabled', true).text(statusDataForAlerts.running || 'Running...');

    jQuery.ajax({
        url: (typeof ic_ajax_object !== 'undefined') ? ic_ajax_object.ajax_url : '',
        type: 'POST',
        data: {
            action: 'run_cleanup_now',
            nonce: (typeof ic_ajax_object !== 'undefined') ? ic_ajax_object.run_cleanup_nonce : ''
        },
        success: function(data) {
            const successMsg = (statusDataForAlerts.run_success || 'Done! %s items removed.').replace('%s', data.data.count || 0);
            alert(data.success ? successMsg : (data.data.message || statusDataForAlerts.run_fail || 'Failed!'));
            if(data.success) location.reload();
        },
        error: function(err) {
            console.error("Run Cleanup Error:", err);
            alert(statusDataForAlerts.error_generic || 'Error.');
        },
        complete: function() {
            button.prop('disabled', false).text(originalText);
        }
    });
};

window.attachSettingsButtonListeners = function() {
    const scheduleBtn = jQuery('#schedule-cleanup-now-btn');
    const runCleanupBtn = jQuery('#run-cleanup-now-btn');
    const resetAnalyticsBtn = jQuery('#ic-reset-analytics-btn');

    if (scheduleBtn.length && typeof window.handleScheduleCleanupNow === 'function') {
        scheduleBtn.off('click').on('click', window.handleScheduleCleanupNow);
    }

    if (runCleanupBtn.length && typeof window.handleRunCleanupNow === 'function') {
        runCleanupBtn.off('click').on('click', window.handleRunCleanupNow);
    }

    if (resetAnalyticsBtn.length && typeof window.handleResetAnalytics === 'function') {
        resetAnalyticsBtn.off('click').on('click', window.handleResetAnalytics);
    }
};

window.populateCleanupStatus = function(statusData) {
    const nextRunEl = jQuery('#next-cleanup-time');
    const lastRunEl = jQuery('#last-cleanup-time');
    const scheduleContainer = jQuery('#schedule-cleanup-now-container');

    if(nextRunEl.length && statusData) nextRunEl.html(statusData.next_run_html || 'N/A');
    if(lastRunEl.length && statusData) lastRunEl.html(statusData.last_run_html || 'N/A');

    if (scheduleContainer.length) {
        scheduleContainer.empty();
        if (!statusData.is_scheduled) {
            const scheduleBtn = jQuery('<button>', {
                type: 'button',
                id: 'schedule-cleanup-now-btn',
                class: 'btn-secondary text-sm mt-2',
                text: statusData.schedule_now_text || 'Schedule Cleanup Now'
            });
            scheduleContainer.append(scheduleBtn);
        }
    }

    if (typeof window.attachSettingsButtonListeners === 'function') {
        window.attachSettingsButtonListeners();
    }
};

window.handleResetAnalytics = function(event) {
    const button = jQuery(event.target);
    if (!confirm('Are you sure you want to reset all stored analytics data? This will only affect aggregated totals and will not delete individual checkout records.')) {
        return;
    }

    const originalButtonText = button.text();
    button.prop('disabled', true).text('Resetting...');

    jQuery.ajax({
        url: (typeof ic_ajax_object !== 'undefined') ? ic_ajax_object.ajax_url : '',
        type: 'POST',
        data: {
            action: 'ic_reset_stored_analytics',
            nonce: (typeof ic_ajax_object !== 'undefined') ? ic_ajax_object.reset_analytics_nonce : ''
        },
        success: function(data) {
            if (data.success) {
                alert(data.data.message || 'Stored analytics data has been reset. Please refresh the Analytics tab to see updated figures.');
                if (typeof window.fetchLiveAnalyticsData === 'function') {
                    window.fetchLiveAnalyticsData();
                }
            } else {
                alert('Error resetting analytics: ' + (data.data?.message || 'Unknown error.'));
            }
        },
        error: function(err) {
            console.error("Reset Analytics Error:", err);
            alert('Failed to reset analytics. Please check console.');
        },
        complete: function() {
            button.prop('disabled', false).text(originalButtonText);
        }
    });
};

window.fetchLiveAnalyticsData = function() {
    const analyticsContent = jQuery('#analytics-content');
    if (analyticsContent.length) {
        const summaryCards = analyticsContent.find('.analytics-summary-card > div, .analytics-detail-card > div');
        summaryCards.each(function() {
            jQuery(this).find('span.font-medium, span.analytics-stat-value, span.text-green-600, span.text-red-600').text('...');
            jQuery(this).find('.progress-bar-fill').css('width', '0%');
        });

        const topRecoveryTableBody = jQuery('#top-recovery-days-table tbody');
        if(topRecoveryTableBody.length) topRecoveryTableBody.html('<tr><td colspan="3" class="text-center py-4">Loading...</td></tr>');
    }

    /*
    jQuery.ajax({
        url: (typeof ic_ajax_object !== 'undefined') ? ic_ajax_object.ajax_url : '',
        type: 'POST',
        data: {
            action: 'ic_fetch_live_analytics_data',
            nonce: (typeof ic_ajax_object !== 'undefined') ? ic_ajax_object.fetch_analytics_nonce : ''
        },
        success: function(data) {
            if (data.success && typeof window.populateAnalyticsTab === 'function') {
                window.populateAnalyticsTab(data.data);
            } else {
                console.error('Error fetching live analytics:', data.data?.message || 'Unknown error or data format issue.');
            }
        },
        error: function(error) {
            console.error('Fetch Live Analytics Error:', error);
        }
    });*/
};

// --- DOMContentLoaded dependent initializations ---
jQuery(document).ready(function($) {
    const ajaxUrl = (typeof ic_ajax_object !== 'undefined') ? ic_ajax_object.ajax_url : (window.ajaxurl || '/wp-admin/admin-ajax.php');

    try {
        const activeTabId = localStorage.getItem('icActiveAdminTab');
        const activeTabButtonId = localStorage.getItem('icActiveAdminTabButton');
        const activeSubtitle = localStorage.getItem('icActiveAdminSubtitle');
        const firstTabButtonDefault = $('#tab-incomplete-orders');

        if (activeTabId && $('#' + activeTabId).length) {
            const tabButtonToClick = activeTabButtonId ? $('#' + activeTabButtonId) : $(`.tab-button[onclick*="${activeTabId}"]`);
            if (tabButtonToClick.length && typeof window.showTabContent === 'function') {
                window.showTabContent(activeTabId, tabButtonToClick[0], activeSubtitle || 'Incomplete Orders List');
            } else if (firstTabButtonDefault.length && typeof window.showTabContent === 'function') {
                window.showTabContent('incomplete-orders-content', firstTabButtonDefault[0], 'Incomplete Orders List');
            }
        } else if (firstTabButtonDefault.length && typeof window.showTabContent === 'function') {
            window.showTabContent('incomplete-orders-content', firstTabButtonDefault[0], 'Incomplete Orders List');
        }
    } catch (e) {
        console.warn('IC Dev: Error restoring tab state from localStorage.', e);
        const firstTabButtonDefault = $('#tab-incomplete-orders');
        if (firstTabButtonDefault.length && typeof window.showTabContent === 'function') {
            window.showTabContent('incomplete-orders-content', firstTabButtonDefault[0], 'Incomplete Orders List');
        }
    }

    $('#currentYear').text(new Date().getFullYear());

    const copyMessageEl = $('#copyMessage');
    function showCopyMessage() {
        // Create a completely independent toast message as fallback
        function createIndependentToast() {
            // Remove any existing independent toast
            $('#ic-independent-copy-toast').remove();

            const toast = $('<div>', {
                id: 'ic-independent-copy-toast',
                text: 'Copied to clipboard!',
                css: {
                    position: 'fixed',
                    bottom: '30px',
                    left: '50%',
                    transform: 'translateX(-50%)',
                    backgroundColor: '#22c55e',
                    color: 'white',
                    padding: '12px 24px',
                    borderRadius: '8px',
                    boxShadow: '0 4px 12px rgba(0,0,0,0.2)',
                    zIndex: '999999',
                    fontFamily: '-apple-system, BlinkMacSystemFont, Segoe UI, Roboto, sans-serif',
                    fontSize: '14px',
                    fontWeight: '500',
                    pointerEvents: 'none',
                    opacity: '0',
                    transition: 'opacity 0.3s ease-in-out',
                    whiteSpace: 'nowrap'
                }
            });

            $('body').append(toast);
            // Trigger animation
            requestAnimationFrame(() => {
                toast.css('opacity', '1');
            });

            // Remove after delay
            setTimeout(() => {
                toast.css('opacity', '0');
                setTimeout(() => {
                    toast.remove();
                }, 300);
            }, 1500);

            return true;
        }

        // Try to use the existing copy message element first
        if (!copyMessageEl.length) {
            console.warn('IC Dev: Copy message element not found in DOM, creating independent toast');
            return createIndependentToast();
        }

        // Force inline styles as a fallback in case CSS doesn't work
        copyMessageEl.css({
            position: 'fixed',
            bottom: '20px',
            left: '50%',
            transform: 'translateX(-50%)',
            backgroundColor: '#22c55e',
            color: 'white',
            padding: '12px 20px',
            borderRadius: '6px',
            boxShadow: '0 4px 12px rgba(0,0,0,0.15)',
            zIndex: '999999',
            opacity: '1',
            visibility: 'visible',
            fontFamily: 'Inter, sans-serif',
            fontSize: '14px',
            fontWeight: '500',
            pointerEvents: 'none',
            display: 'block',
            width: 'auto',
            height: 'auto',
            margin: '0',
            border: 'none',
            outline: 'none',
            transition: 'opacity 0.3s ease-in-out'
        });

        copyMessageEl.addClass('show');

        setTimeout(() => {
            // Fade out with transition
            copyMessageEl.css('opacity', '0');
            setTimeout(() => {
                copyMessageEl.css('visibility', 'hidden');
                copyMessageEl.removeClass('show');
            }, 300);
        }, 1500);
    }

    $('body').on('click', '.copy-button', function(event) {
        event.stopPropagation(); // Prevent bubbling to document level
        const target = $(this);
        const textToCopy = target.data('copytext');

        if (textToCopy) {
            const textArea = $('<textarea>').val(textToCopy).css({
                position: 'fixed',
                left: '-9999px'
            }).appendTo('body');

            textArea[0].select();

            try {
                const success = document.execCommand('copy');
                if (success) {
                    showCopyMessage();
                } else {
                    throw new Error('Copy command failed');
                }
            } catch (err) {
                console.error('Copy failed:', err);
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(textToCopy).then(() => {
                        showCopyMessage();
                    }, (clipErr) => {
                        console.error('Clipboard API copy failed:', clipErr);
                        alert('Failed to copy text.');
                    });
                } else {
                    alert('Failed to copy text.');
                }
            }

            textArea.remove();
        } else {
            console.warn('IC Dev: No text to copy found in button dataset');
        }
    });

    const toggleColumnsBtn = $('#toggleColumnsBtn');
    const columnsDropdownEl = $('#columnsDropdown');
    const incompleteOrdersTableEl = $('#incompleteOrdersTable');

    if (toggleColumnsBtn.length && columnsDropdownEl.length && incompleteOrdersTableEl.length) {
        const columnSelectAllCheckbox = columnsDropdownEl.find('.column-select-all');
        const tableColumns = [
            { key: 'checkbox', name: 'Select', defaultVisible: true, alwaysVisible: true },
            { key: 'date', name: 'Date', defaultVisible: true },
            { key: 'name', name: 'Name', defaultVisible: true },
            { key: 'phone', name: 'Phone', defaultVisible: true },
            { key: 'courier', name: 'Fraud Checker', defaultVisible: true },
            { key: 'products', name: 'Products', defaultVisible: true },
            { key: 'address', name: 'Address', defaultVisible: true },
            { key: 'state', name: 'State', defaultVisible: true },
            { key: 'subtotal', name: 'Subtotal', defaultVisible: true },
            { key: 'status', name: 'Status', defaultVisible: true },
            { key: 'note', name: 'Note', defaultVisible: true },
            { key: 'actions', name: 'Actions', defaultVisible: true, alwaysVisible: true }
        ];

        window.renderICTableColumnCheckboxes = function() {
            if (!columnSelectAllCheckbox.length) return;

            // Remove existing labels except the first one
            columnsDropdownEl.find('label:not(:first-of-type)').remove();

            // Load saved column preferences from localStorage with site-specific key
            const siteSpecificKey = 'icTableColumnPreferences_' + (window.location.hostname || 'local');
            let savedColumns = null;
            let columnPreferences = {};

            try {
                savedColumns = localStorage.getItem(siteSpecificKey);
                columnPreferences = savedColumns ? JSON.parse(savedColumns) : {};
            } catch (e) {
                console.warn('IC Dev: Failed to load column preferences from localStorage:', e);
                // Fallback to empty preferences
                columnPreferences = {};
            }

            tableColumns.forEach(col => {
                if (col.alwaysVisible && col.key === 'checkbox') {
                    toggleColumnVisibility(col.key, true);
                    return;
                }

                if (col.alwaysVisible && col.key === 'actions') {
                    toggleColumnVisibility(col.key, true);
                    return;
                }

                const label = $('<label>');
                const checkbox = $('<input>', {
                    type: 'checkbox',
                    'data-column-key': col.key
                });

                // Use saved preference or default
                const isVisible = columnPreferences.hasOwnProperty(col.key) ? columnPreferences[col.key] : col.defaultVisible;
                checkbox.prop('checked', isVisible);
                checkbox.prop('disabled', col.alwaysVisible || false);
                checkbox.addClass('text-[#1a7278] focus:ring-[#1a7278] border-gray-300 rounded');

                toggleColumnVisibility(col.key, isVisible);

                checkbox.on('change', function(event) {
                    toggleColumnVisibility(col.key, $(this).prop('checked'));
                    updateSelectAllColumnToggleCheckboxState();
                    // Save column preferences to localStorage
                    saveColumnPreferences();
                });

                label.append(checkbox).append(` ${col.name}`);
                columnsDropdownEl.append(label);
            });

            updateSelectAllColumnToggleCheckboxState();
        };

        function saveColumnPreferences() {
            const preferences = {};

            tableColumns.forEach(col => {
                if (!col.alwaysVisible) {
                    const checkbox = columnsDropdownEl.find(`input[data-column-key="${col.key}"]`);
                    if (checkbox.length) {
                        preferences[col.key] = checkbox.prop('checked');
                    }
                }
            });

            // Use site-specific key for localStorage
            const siteSpecificKey = 'icTableColumnPreferences_' + (window.location.hostname || 'local');

            try {
                localStorage.setItem(siteSpecificKey, JSON.stringify(preferences));
            } catch (e) {
                console.warn('IC Dev: Failed to save column preferences to localStorage:', e);
            }
        }

        function toggleColumnVisibility(columnKey, isVisible) {
            const th = incompleteOrdersTableEl.find(`th[data-column-key="${columnKey}"]`);
            const tds = incompleteOrdersTableEl.find(`td[data-column-key="${columnKey}"]`);

            if (th.length) {
                if (isVisible) {
                    th.removeClass('hidden');
                } else {
                    th.addClass('hidden');
                }
            }

            tds.each(function() {
                if (isVisible) {
                    $(this).removeClass('hidden');
                } else {
                    $(this).addClass('hidden');
                }
            });
        }

        function updateSelectAllColumnToggleCheckboxState() {
            if (!columnSelectAllCheckbox.length) return;

            const allCheckboxes = columnsDropdownEl.find('input[type="checkbox"]:not(.column-select-all):not(:disabled)');
            const allChecked = allCheckboxes.length > 0 && allCheckboxes.toArray().every(cb => cb.checked);
            const someChecked = allCheckboxes.toArray().some(cb => cb.checked);

            if (allChecked) {
                columnSelectAllCheckbox.prop('checked', true).prop('indeterminate', false);
            } else if (someChecked) {
                columnSelectAllCheckbox.prop('checked', false).prop('indeterminate', true);
            } else {
                columnSelectAllCheckbox.prop('checked', false).prop('indeterminate', false);
            }
        }

        toggleColumnsBtn.on('click', function(event) {
            event.stopPropagation();
            columnsDropdownEl.toggleClass('hidden');
        });

        $(document).on('click', function(event) {
            if (columnsDropdownEl && !columnsDropdownEl.hasClass('hidden') && !columnsDropdownEl.is(event.target) && !toggleColumnsBtn.is(event.target)) {
                columnsDropdownEl.addClass('hidden');
            }
        });

        if (columnSelectAllCheckbox.length) {
            columnSelectAllCheckbox.on('change', function(event) {
                const isChecked = $(this).prop('checked');

                columnsDropdownEl.find('input[type="checkbox"]:not(.column-select-all):not(:disabled)').each(function() {
                    if ($(this).prop('checked') !== isChecked) {
                        $(this).prop('checked', isChecked);
                        toggleColumnVisibility($(this).data('column-key'), isChecked);
                    }
                });

                // Save preferences after select all change
                saveColumnPreferences();
            });
        }

        if ($('#incomplete-orders-content').hasClass('active-pane')) {
            window.renderICTableColumnCheckboxes();
        }
    }

    const tableBody = incompleteOrdersTableEl ? incompleteOrdersTableEl.find('tbody') : null;

    if (tableBody && tableBody.length) {
        tableBody.off('click', 'button').on('click', 'button', function(event) {
            event.preventDefault();
            const targetButton = $(this);
            if (targetButton.prop('disabled')) return;

            if (targetButton.hasClass('ic-toggle-status')) {
                handleToggleStatus(targetButton);
            } else if (targetButton.hasClass('ic-save-note')) {
                handleSaveNote(targetButton);
            } else if (targetButton.hasClass('ic-delete-lead')) {
                handleDeleteLead(targetButton);
            }
        });
    }

    function handleToggleStatus(button) {
        const leadId = button.data('id');
        const originalButtonText = button.text();

        button.prop('disabled', true).text('Updating...');

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'update_incomplete_status',
                lead_id: leadId,
                nonce: ic_ajax_object.status_nonce
            },
            success: function(data) {
                if (data.success) {
                    const statusCell = $(`#status-cell-${leadId}`);
                    if (statusCell.length) {
                        statusCell.html('<span class="status-badge status-recovered">Recovered</span>');
                    }
                } else {
                    alert('Error: ' + (data.data?.reason || 'Unknown'));
                }
            },
            error: function(err) {
                console.error(err);
                alert('Request failed.');
            },
            complete: function() {
                button.prop('disabled', false).text(originalButtonText);
            }
        });
    }

    function handleSaveNote(button) {
        const leadId = button.data('id');
        const noteTextarea = $(`#note-${leadId}`);

        if (!noteTextarea.length) return;

        const note = noteTextarea.val();
        const originalButtonText = button.text();

        button.prop('disabled', true).text('Saving...');

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'update_abandoned_note',
                lead_id: leadId,
                note: note,
                nonce: ic_ajax_object.note_nonce
            },
            success: function(data) {
                if (data.success) {
                    const msg = $('<span>', {
                        text: ' Saved!',
                        css: {
                            color: 'green',
                            fontSize: '0.75rem'
                        }
                    });

                    button.after(msg);
                    setTimeout(function() {
                        msg.remove();
                    }, 2000);
                } else {
                    alert('Error: ' + (data.data?.reason || 'Unknown'));
                }
            },
            error: function(err) {
                console.error(err);
                alert('Request failed.');
            },
            complete: function() {
                button.prop('disabled', false).text(originalButtonText);
            }
        });
    }

    function handleDeleteLead(button) {
        const leadId = button.data('id');

        if (!confirm(`Delete ID ${leadId}?`)) return;

        const originalButtonText = button.text();
        button.prop('disabled', true).text('Deleting...');

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'delete_incomplete_orders',
                lead_id: leadId,
                nonce: ic_ajax_object.delete_nonce
            },
            success: function(data) {
                if (data.success) {
                    const row = $(`#checkout-row-${leadId}`);
                    if (row.length) row.remove();
                } else {
                    alert('Error: ' + (data.data?.reason || 'Unknown'));
                    button.prop('disabled', false).text(originalButtonText);
                }
            },
            error: function(err) {
                console.error(err);
                alert('Request failed.');
                button.prop('disabled', false).text(originalButtonText);
            }
        });
    }

    const selectAllRowsCheckbox = incompleteOrdersTableEl ? incompleteOrdersTableEl.find('.table-select-all-rows') : null;

    if (selectAllRowsCheckbox && selectAllRowsCheckbox.length && tableBody && tableBody.length) {
        selectAllRowsCheckbox.on('change', function() {
            tableBody.find('.row-checkbox').prop('checked', $(this).prop('checked'));
        });

        tableBody.on('change', '.row-checkbox', function() {
            selectAllRowsCheckbox.prop('checked', tableBody.find('.row-checkbox').toArray().every(cb => cb.checked));
        });
    }

    const bulkActionApplyBtn = $('#doaction-top');
    const bulkActionSelect = $('#bulk-actions-top');

    if (bulkActionApplyBtn.length && bulkActionSelect.length && tableBody && tableBody.length) {
        bulkActionApplyBtn.on('click', function() {
            const action = bulkActionSelect.val();
            if (action === '-1') {
                alert('Select action.');
                return;
            }

            const selectedLeadIds = tableBody.find('.row-checkbox:checked').map(function() {
                return $(this).val();
            }).get();

            if (selectedLeadIds.length === 0) {
                alert('Select orders.');
                return;
            }

            if (action === 'delete' && confirm(`Delete ${selectedLeadIds.length} item(s)?`)) {
                handleBulkDelete(selectedLeadIds);
            } else if (action === 'mark-recovered' && confirm(`Mark ${selectedLeadIds.length} item(s) as recovered?`)) {
                handleBulkMarkRecovered(selectedLeadIds);
            } else if (action === 'cancel' && confirm(`Cancel ${selectedLeadIds.length} item(s)?`)) {
                handleBulkCancel(selectedLeadIds);
            }
        });
    }

    function handleBulkCancel(leadIds) {
        if (!bulkActionApplyBtn.length) return;

        const btnTxt = bulkActionApplyBtn.text();
        bulkActionApplyBtn.prop('disabled', true).text('Cancelling...');

        const formData = new FormData();
        formData.append('action', 'bulk_cancel_incomplete_orders');
        formData.append('nonce', ic_ajax_object.bulk_action_nonce);

        leadIds.forEach(id => formData.append('lead_ids[]', id));

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(data) {
                if (data.success) {
                    alert(`Cancelled ${data.data.updated_count || 0} item(s).`);
                    window.location.reload();
                } else {
                    alert('Error: ' + (data.data?.message || 'Unknown error.'));
                }
            },
            error: function(err) {
                console.error(err);
                alert('Request failed.');
            },
            complete: function() {
                bulkActionApplyBtn.prop('disabled', false).text(btnTxt);
                if (bulkActionSelect.length) bulkActionSelect.val("-1");
            }
        });
    }

    function handleBulkDelete(leadIds) {
        if (!bulkActionApplyBtn.length) return;

        const btnTxt = bulkActionApplyBtn.text();
        bulkActionApplyBtn.prop('disabled', true).text('Deleting...');

        const formData = new FormData();
        formData.append('action', 'bulk_delete_incomplete_orders');
        formData.append('nonce', ic_ajax_object.bulk_action_nonce);

        leadIds.forEach(id => formData.append('lead_ids[]', id));

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(data) {
                if (data.success) {
                    alert(`Deleted ${data.data.deleted || 0} item(s).`);
                    leadIds.forEach(id => {
                        const row = $(`#checkout-row-${id}`);
                        if (row.length) row.remove();
                    });

                    if (selectAllRowsCheckbox && selectAllRowsCheckbox.length) {
                        selectAllRowsCheckbox.prop('checked', false);
                    }
                } else {
                    alert('Error: ' + (data.data?.reason || 'Unknown'));
                }
            },
            error: function(err) {
                console.error(err);
                alert('Request failed.');
            },
            complete: function() {
                bulkActionApplyBtn.prop('disabled', false).text(btnTxt);
                if(bulkActionSelect.length) bulkActionSelect.val("-1");
            }
        });
    }

    function handleBulkMarkRecovered(leadIds) {
        if (!bulkActionApplyBtn.length) return;

        const btnTxt = bulkActionApplyBtn.text();
        bulkActionApplyBtn.prop('disabled', true).text('Updating...');

        const formData = new FormData();
        formData.append('action', 'bulk_mark_incomplete_orders');
        formData.append('nonce', ic_ajax_object.bulk_action_nonce);

        leadIds.forEach(id => formData.append('lead_ids[]', id));

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(data) {
                if (data.success) {
                    alert(`Marked ${data.data.updated_count || 0} item(s) as recovered.`);
                    leadIds.forEach(id => {
                        const cell = $(`#status-cell-${id}`);
                        if (cell.length) {
                            cell.html('<span class="status-badge status-recovered">Recovered</span>');
                        }

                        const btn = $(`.ic-toggle-status[data-id="${id}"]`);
                        if (btn.length) {
                            btn.data('currentStatus', '✅');
                        }
                    });

                    if (selectAllRowsCheckbox && selectAllRowsCheckbox.length) {
                        selectAllRowsCheckbox.prop('checked', false);
                    }
                } else {
                    alert('Error: ' + (data.data?.message || 'Unknown'));
                }
            },
            error: function(err) {
                console.error(err);
                alert('Request failed.');
            },
            complete: function() {
                bulkActionApplyBtn.prop('disabled', false).text(btnTxt);
                if(bulkActionSelect.length) bulkActionSelect.val("-1");
            }
        });
    }

    // AJAX-based pagination handler - no page reloads
    window.attachPaginationHandlers = function() {
        const paginationContainer = $('#ic-pagination-links');

        if (paginationContainer.length) {
            const paginationLinks = paginationContainer.find('a');

            paginationLinks.each(function() {
                const link = $(this);

                if (!link.attr('data-handler-attached')) {
                    link.attr('data-handler-attached', 'true');

                    link.on('click', function(e) {
                        e.preventDefault(); // Always prevent default for AJAX

                        let pageNum = 1;
                        const currentUrl = new URL(window.location);
                        const currentPage = parseInt(currentUrl.searchParams.get('paged')) || 1;

                        // Extract page number from link text
                        if (link.text().includes('Next') || link.text().includes('»')) {
                            pageNum = currentPage + 1;
                        } else if (link.text().includes('Previous') || link.text().includes('«')) {
                            pageNum = Math.max(1, currentPage - 1);
                        } else {
                            // Extract number from link text for numbered pages
                            const matches = link.text().match(/\d+/);
                            if (matches) {
                                pageNum = parseInt(matches[0]);
                            }
                        }

                        // Load new page content via AJAX
                        window.loadPageContent(pageNum);
                    });
                }
            });
        }
    };

    // AJAX function to load page content without reload using existing endpoint
    window.loadPageContent = function(pageNum) {
        // Show loading state
        const tableBody = $('#incompleteOrdersTable tbody');
        const paginationContainer = $('#ic-pagination-links');

        if (tableBody.length) {
            // Store original content in case of error
            window.originalTableContent = tableBody.html();
            tableBody.html('<tr><td colspan="12" class="text-center py-8"><div class="inline-flex items-center"><svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-gray-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>Loading page ' + pageNum + '...</div></td></tr>');
        }

        // Update URL immediately for better UX
        const newUrl = new URL(window.location);
        if (pageNum > 1) {
            newUrl.searchParams.set('paged', pageNum);
        } else {
            newUrl.searchParams.delete('paged');
        }
        window.history.pushState({page: pageNum}, '', newUrl.toString());

        // Use fetch to get the new page content
        $.ajax({
            url: newUrl.toString(),
            type: 'GET',
            dataType: 'html',
            headers: {
                'X-Requested-With': 'XMLHttpRequest' // Indicate this is an AJAX request
            },
            success: function(html) {
                // Parse the response HTML
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');

                // Extract table body content
                const newTableBody = $(doc).find('#incompleteOrdersTable tbody');
                if (newTableBody.length && tableBody.length) {
                    tableBody.html(newTableBody.html());
                }

                // Extract pagination content
                const newPagination = $(doc).find('#ic-pagination-links');
                if (newPagination.length && paginationContainer.length) {
                    paginationContainer.html(newPagination.html());

                    // Re-attach handlers to new pagination links
                    setTimeout(function() {
                        window.attachPaginationHandlers();
                    }, 100);
                }

                // Restore column visibility settings after AJAX load
                if (typeof window.renderICTableColumnCheckboxes === 'function') {
                    setTimeout(function() {
                        window.renderICTableColumnCheckboxes();
                    }, 150);
                }

                // Scroll to top of table for better UX
                const tableContainer = $('#incomplete-orders-content');
                if (tableContainer.length) {
                    tableContainer[0].scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            },
            error: function(err) {
                console.error('IC Dev: AJAX pagination error:', err);
                if (tableBody.length && window.originalTableContent) {
                    tableBody.html(window.originalTableContent);
                }
                alert('Failed to load page content. The page will now reload.');
                window.location.href = newUrl.toString();
            }
        });
    };

    // Call initially
    window.attachPaginationHandlers();

    // Also attach on a delay for live servers (single delay only)
    setTimeout(function() {
        window.attachPaginationHandlers();
    }, 500);

    if (typeof window.attachSettingsButtonListeners === 'function') {
        window.attachSettingsButtonListeners();
    }

    // Products Modal Handler
    function initProductsModal() {
        const modal = $('#products-modal');
        const modalClose = $('.products-modal-close');
        const modalOverlay = $('.products-modal-overlay');
        const modalList = $('#products-modal-list');

        // Open modal when "View All Products" button is clicked
        $(document).on('click', '.view-all-products-btn', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const button = $(this);
            let productsData = null;
            
            // Try to get data from data attribute
            try {
                const productsJson = button.attr('data-products');
                if (productsJson) {
                    productsData = JSON.parse(productsJson);
                } else {
                    // Fallback to jQuery data method
                    productsData = button.data('products');
                }
            } catch (err) {
                console.error('Error parsing products data:', err);
                productsData = null;
            }
            
            if (productsData && Array.isArray(productsData) && productsData.length > 0) {
                // Clear previous content
                modalList.empty();
                
                // Build products list HTML
                let productsHtml = '<div class="products-list-container">';
                productsData.forEach(function(product, index) {
                    productsHtml += `
                        <div class="product-modal-item">
                            <div class="product-modal-info">
                                <span class="product-modal-number">${index + 1}.</span>
                                <span class="product-modal-name">${$('<div>').text(product.name).html()}</span>
                            </div>
                            <div class="product-modal-details">
                                <span class="product-modal-qty">Qty: ${product.quantity}</span>
                                <span class="product-modal-price">${product.formatted_total || product.total}</span>
                            </div>
                        </div>
                    `;
                });
                productsHtml += '</div>';
                
                modalList.html(productsHtml);
                modal.fadeIn(200);
                $('body').css('overflow', 'hidden');
                // Scroll to top of modal content
                modal.find('.products-modal-body').scrollTop(0);
            }
        });

        // Close modal when close button is clicked
        modalClose.on('click', function() {
            closeProductsModal();
        });

        // Close modal when clicking outside the modal content
        modalOverlay.on('click', function(e) {
            if ($(e.target).hasClass('products-modal-overlay')) {
                closeProductsModal();
            }
        });

        // Close modal on ESC key
        $(document).on('keydown', function(e) {
            if (e.key === 'Escape' && modal.is(':visible')) {
                closeProductsModal();
            }
        });

        function closeProductsModal() {
            modal.fadeOut(200);
            $('body').css('overflow', '');
        }
    }

    // Initialize products modal
    initProductsModal();
});

// Additional window load handler for live servers
jQuery(window).on('load', function() {
    setTimeout(function() {
        if (typeof window.attachPaginationHandlers === 'function') {
            window.attachPaginationHandlers();
        }
    }, 500);
});

// Re-attach handlers when window regains focus (for cache busting)
jQuery(window).on('focus', function() {
    setTimeout(function() {
        if (typeof window.attachPaginationHandlers === 'function') {
            window.attachPaginationHandlers();
        }
    }, 100);
}); 