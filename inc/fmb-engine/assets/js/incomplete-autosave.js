(function($) {
    'use strict';
    var autosaveTimer;

    $(document).on('input change', 'form.checkout input, form.checkout textarea', function() {
        clearTimeout(autosaveTimer);
        autosaveTimer = setTimeout(function() {
            var phone = $('#billing_phone').val();
            var name = $('#billing_first_name').val();
            var address = $('#billing_address_1').val();
            var email = $('#billing_email').val();

            if (phone && phone.replace(/[^\d]/g, '').length >= 11) {
                $.post(fmbAutosaveParams.ajax_url, {
                    action: 'autosave_order',
                    billing_phone: phone,
                    billing_first_name: name,
                    billing_address_1: address,
                    billing_email: email
                });
            }
        }, 1200);
    });
})(jQuery);
