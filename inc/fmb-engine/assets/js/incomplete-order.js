(function($) {
    var autosaveDelay = 1000;
    var autosaveTimeout;

    // Universal selectors for all themes (WoodMart, Astra), page builders (Elementor), CartFlows, and WC Blocks
    var fieldSelectors = {
        billing_first_name: ['input[name="billing_first_name"]', '#billing_first_name', 'input[name="shipping_first_name"]', '#shipping_first_name', 'input[autocomplete="given-name"]'],
        billing_last_name:  ['input[name="billing_last_name"]', '#billing_last_name', 'input[name="shipping_last_name"]', '#shipping_last_name', 'input[autocomplete="family-name"]'],
        billing_address_1:  ['input[name="billing_address_1"]', '#billing_address_1', 'input[name="shipping_address_1"]', '#shipping_address_1', 'input[autocomplete="address-line1"]'],
        billing_phone:      ['input[name="billing_phone"]', '#billing_phone', 'input[name="shipping_phone"]', '#shipping_phone', 'input[autocomplete="tel"]', '.woocommerce-checkout input[type="tel"]', 'input[type="tel"]'],
        billing_country:    ['select[name="billing_country"]', '#billing_country', 'input[name="billing_country"]', 'select[name="shipping_country"]', '#shipping_country', 'select[autocomplete="country"]'],
        billing_state:      ['select[name="billing_state"]', '#billing_state', 'input[name="billing_state"]', 'select[name="shipping_state"]', '#shipping_state', 'input[autocomplete="address-level1"]'],
        billing_city:       ['input[name="billing_city"]', '#billing_city', 'input[name="shipping_city"]', '#shipping_city', 'input[autocomplete="address-level2"]'],
        billing_email:      ['input[name="billing_email"]', '#billing_email', 'input[autocomplete="email"]', '.woocommerce-checkout input[type="email"]', 'input[type="email"]']
    };

    function getFieldValue(selectors) {
        var value = '';
        for (var i = 0; i < selectors.length; i++) {
            var elements = $(selectors[i]);
            elements.each(function() {
                var val = $(this).val();
                if (val && val.trim() !== '') {
                    value = val.trim();
                    return false; // Break jQuery each loop
                }
            });
            if (value) {
                break; // Break outer loop
            }
        }
        return value;
    }

    function autosave() {
        var formData = {
            action: 'autosave_order',
            _ajax_nonce: ads_ajax_data.nonce,
            billing_first_name: getFieldValue(fieldSelectors.billing_first_name),
            billing_last_name: getFieldValue(fieldSelectors.billing_last_name),
            billing_address_1: getFieldValue(fieldSelectors.billing_address_1),
            billing_phone: getFieldValue(fieldSelectors.billing_phone),
            billing_country: getFieldValue(fieldSelectors.billing_country),
            billing_state: getFieldValue(fieldSelectors.billing_state),
            billing_city: getFieldValue(fieldSelectors.billing_city),
            billing_email: getFieldValue(fieldSelectors.billing_email),
        };

        // Validate phone - minimum length for BD phone numbers
        if (!formData.billing_phone || formData.billing_phone.replace(/\D/g,'').length < 11) {
            return;
        }

        $.post(ads_ajax_data.ajax_url, formData)
            .done(function(response) {
                if (response.success) {
                    console.log('FMB Engine: ' + response.data.message + '. Order ID:', response.data.order_id);
                }
            })
            .fail(function(jqXHR, textStatus, errorThrown) {
                console.error('FMB Engine autosave failed:', textStatus, errorThrown);
            });
    }

    // Collect all selectors to bind events globally
    var allSelectorsArray = [];
    $.each(fieldSelectors, function(key, selectors) {
        allSelectorsArray = allSelectorsArray.concat(selectors);
    });
    // Remove duplicates
    allSelectorsArray = allSelectorsArray.filter(function(item, pos) {
        return allSelectorsArray.indexOf(item) == pos;
    });
    var allSelectors = allSelectorsArray.join(',');

    // Bind to document to catch dynamically loaded checkouts (CartFlows, Elementor, Blocks)
    $(document).on('input change blur', allSelectors, function() {
        clearTimeout(autosaveTimeout);
        autosaveTimeout = setTimeout(autosave, autosaveDelay);
    });

    // Classic WooCommerce events
    $(document.body).on(
        'added_to_cart removed_from_cart updated_cart_totals shipping_method_selected updated_shipping_method wc_fragments_refreshed',
        function() {
            setTimeout(autosave, autosaveDelay);
        }
    );

    // Initial check on load
    $(window).on('load', function() {
        setTimeout(autosave, autosaveDelay);
    });
})(jQuery);