// Generate or retrieve Session ID
function fmbGetSessionId() {
    let sid = localStorage.getItem('fmb_visitor_session_id');
    if (!sid) {
        sid = 'sess_' + Math.random().toString(36).substr(2, 9) + '_' + Date.now();
        localStorage.setItem('fmb_visitor_session_id', sid);
    }
    return sid;
}

// Parse User Agent
function fmbGetDevice() {
    if (/Mobi|Android/i.test(navigator.userAgent)) return 'Mobile';
    if (/Tablet|iPad/i.test(navigator.userAgent)) return 'Tablet';
    return 'Desktop';
}

function fmbGetOS() {
    var ua = navigator.userAgent;
    if (ua.indexOf("Win") != -1) return "Windows";
    if (ua.indexOf("Mac") != -1) return "MacOS";
    if (ua.indexOf("Linux") != -1) return "Linux";
    if (ua.indexOf("Android") != -1) return "Android";
    if (ua.indexOf("like Mac") != -1) return "iOS";
    return "Unknown OS";
}

function fmbGetBrowser() {
    var ua = navigator.userAgent;
    if (ua.indexOf("Firefox") > -1) return "Firefox";
    if (ua.indexOf("SamsungBrowser") > -1) return "Samsung Internet";
    if (ua.indexOf("Opera") > -1 || ua.indexOf("OPR") > -1) return "Opera";
    if (ua.indexOf("Trident") > -1) return "Internet Explorer";
    if (ua.indexOf("Edge") > -1 || ua.indexOf("Edg") > -1) return "Edge";
    if (ua.indexOf("Chrome") > -1) return "Chrome";
    if (ua.indexOf("Safari") > -1) return "Safari";
    return "Unknown Browser";
}

// Get UTM Campaign
function fmbGetCampaign() {
    let params = new URLSearchParams(window.location.search);
    let campaign = params.get('utm_campaign') || params.get('utm_source') || params.get('fbclid') ? 'Facebook Ads' : '';
    if (params.get('utm_campaign')) campaign = params.get('utm_campaign');
    return campaign;
}

jQuery(document).ready(function($) {
    if (typeof fmb_tracker_vars === 'undefined') return;

    let logId = null;
    let trackEvents = [];

    // Scroll Tracking
    let scrollDepths = { 25: false, 50: false, 75: false, 100: false };
    $(window).on('scroll', function() {
        let scrollTop = $(window).scrollTop();
        let docHeight = $(document).height();
        let winHeight = $(window).height();
        
        if (docHeight > winHeight) {
            let scrollPercent = (scrollTop / (docHeight - winHeight)) * 100;
            [25, 50, 75, 100].forEach(function(depth) {
                if (scrollPercent >= depth && !scrollDepths[depth]) {
                    scrollDepths[depth] = true;
                    trackEvents.push({ type: 'scroll', value: 'Scrolled ' + depth + '%' });
                }
            });
        }
    });

    // Button Click & Interaction Tracking (Crystal-Clear Identification)
    $(document).on('click', 'button, .btn, .button, a[href^="#"], a[href^="tel:"], a[href*="wa.me"], input[type="submit"], input[type="radio"][name="delivery_area"], .qty-btn, .color-option, .size-option, .swatch-anchor', function() {
        let $el = $(this);
        let tag = $el.prop('tagName').toLowerCase();
        let rawText = $el.text().replace(/\s+/g, ' ').trim();
        if (!rawText && $el.val()) rawText = $el.val().trim();
        
        let label = '';
        let category = '';

        // 1. Quantity buttons
        if ($el.hasClass('qty-plus') || $el.hasClass('plus') || $el.data('action') === 'increase' || rawText === '+') {
            category = 'পরিমাণ বৃদ্ধি (+)';
            let currentQty = $('#quantity, input.qty, input[name="quantity"]').val() || '';
            label = currentQty ? 'Quantity -> ' + currentQty : '+1';
        } else if ($el.hasClass('qty-minus') || $el.hasClass('minus') || $el.data('action') === 'decrease' || rawText === '-') {
            category = 'পরিমাণ হ্রাস (-)';
            let currentQty = $('#quantity, input.qty, input[name="quantity"]').val() || '';
            label = currentQty ? 'Quantity -> ' + currentQty : '-1';
        } 
        // 2. Sticky Order Button
        else if ($el.closest('.fmb-sticky-order-btn, #fmb-sticky-buy, .sticky-bottom-bar, .sticky-btn').length || $el.hasClass('fmb-sticky-order-btn')) {
            category = 'নিচের স্টিকি অর্ডার বাটন';
            label = rawText ? rawText.substring(0, 40) : 'এখনই অর্ডার করুন';
        }
        // 3. Form Order Confirm Submit Button
        else if ($el.attr('id') === 'landing_confirm_order' || $el.attr('id') === 'fmb_confirm_order' || $el.attr('id') === 'fmb_popup_order_submit' || $el.attr('name') === 'woocommerce_checkout_place_order' || $el.attr('id') === 'place_order' || $el.closest('#fmb-quick-order-form, #fmb-landing-order-form, form.checkout').length && $el.is('button[type="submit"], input[type="submit"]')) {
            category = 'অর্ডার ফর্ম সাবমিট বাটন';
            let totalText = $('#btn-final-total, .order-total .amount, #fmb_total_price').text().trim();
            label = (rawText ? rawText.substring(0, 30) : 'অর্ডার কনফার্ম') + (totalText ? ' (' + totalText + ')' : '');
        }
        // 4. Modal / Popup Order Buttons
        else if ($el.attr('id') === 'open-order-modal' || $el.attr('id') === 'open-order-modal-2' || $el.hasClass('fmb-order-popup-btn') || $el.hasClass('open-popup-btn')) {
            category = 'পপআপ অর্ডার ওপেন বাটন';
            label = rawText ? rawText.substring(0, 35) : 'অর্ডার ফরম পপআপ';
        }
        // 5. Call & WhatsApp
        else if ($el.is('a[href^="tel:"]') || $el.closest('a[href^="tel:"]').length) {
            category = 'কল বাটন';
            label = 'সরাসরি ফোন কল';
        } else if ($el.is('a[href*="wa.me"]') || $el.closest('a[href*="wa.me"]').length) {
            category = 'হোয়াটসঅ্যাপ বাটন';
            label = 'হোয়াটসঅ্যাপে চ্যাট';
        }
        // 6. Delivery Area Selection
        else if ($el.is('input[name="delivery_area"]') || $el.attr('name') === 'delivery_area') {
            category = 'ডেলিভারি এরিয়া নির্বাচন';
            label = $el.val() == '60' ? 'ঢাকার ভিতরে (৳৬০)' : ($el.val() == '120' ? 'ঢাকার বাহিরে (৳১২০)' : 'ডেলিভারি চার্জ ' + $el.val() + '৳');
        }
        // 7. Variations & Swatches
        else if ($el.hasClass('color-option') || $el.hasClass('size-option') || $el.hasClass('swatch-anchor')) {
            category = 'ভ্যারিয়েন্ট নির্বাচন';
            label = rawText || $el.attr('title') || $el.data('value') || 'Variant Selected';
        }
        // 8. Add to cart / Checkout
        else if ($el.hasClass('single_add_to_cart_button') || $el.hasClass('add_to_cart_button')) {
            category = 'কার্টে যোগ বাটন';
            label = rawText ? rawText.substring(0, 35) : 'Add to Cart';
        } else if ($el.hasClass('checkout-button')) {
            category = 'চেকআউট বাটন';
            label = rawText ? rawText.substring(0, 35) : 'Proceed to Checkout';
        }
        // Default button click
        else if (rawText) {
            category = 'বাটন ক্লিক';
            label = rawText.substring(0, 40);
        }

        if (category && label) {
            trackEvents.push({ 
                type: 'click', 
                value: '[' + category + '] ' + label 
            });
        }
    });

    // Form input focus tracking (Customer started typing)
    let typedFields = {};
    $(document).on('focus', 'input[name*="phone"], input[name*="name"], input[name*="address"]', function() {
        let fieldName = $(this).attr('name');
        if (!typedFields[fieldName]) {
            typedFields[fieldName] = true;
            let fieldLabel = fieldName.indexOf('phone') !== -1 ? 'মোবাইল নম্বর' : (fieldName.indexOf('name') !== -1 ? 'নাম' : 'ঠিকানা');
            trackEvents.push({ 
                type: 'input', 
                value: '[ফর্ম ফিল্ড ইনপুট] ' + fieldLabel + ' টাইপ শুরু' 
            });
        }
    });

    // 1. Initial Page Load Track
    $.post(fmb_tracker_vars.ajax_url, {
        action: 'fmb_track_visit',
        session_id: fmbGetSessionId(),
        url: window.location.href,
        title: document.title,
        device: fmbGetDevice(),
        browser: fmbGetBrowser(),
        os: fmbGetOS(),
        referrer: document.referrer,
        campaign: fmbGetCampaign()
    }, function(res) {
        if (res.success && res.data.log_id) {
            logId = res.data.log_id;
            
            // 2. Start Heartbeat (every 20 seconds) to calculate Time Spent
            setInterval(function() {
                if (logId) {
                    let currentEvents = trackEvents;
                    trackEvents = [];
                    
                    let postData = {
                        action: 'fmb_track_heartbeat',
                        log_id: logId
                    };
                    
                    if (currentEvents.length > 0) {
                        postData.events = JSON.stringify(currentEvents);
                    }
                    
                    $.post(fmb_tracker_vars.ajax_url, postData);
                }
            }, 20000);
        }
    });
});
