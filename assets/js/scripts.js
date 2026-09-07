jQuery(document).ready(function ($) {
    // =========================================
    // ১. পপ-আপ মাল্টি-প্রডাক্ট লজিক (Popup Cart Logic)
    // =========================================
    let popupCart = [];

    function renderPopupCart() {
        const list = $('#popup-order-items-list');
        list.empty();
        let subtotal = 0;

        popupCart.forEach((item, index) => {
            subtotal += item.price * item.qty;

            // Remove Button (Optional: Disable for single item if needed, but allow valid cart management)
            let removeBtn = `<span class="popup-remove-item text-red-500 cursor-pointer ml-2 text-xs" data-index="${index}">❌</span>`;

            list.append(`
                <div class="flex items-center gap-3 bg-white p-2 rounded border border-gray-100">
                    <img src="${item.img}" class="w-12 h-12 rounded object-cover border">
                    <div class="flex-grow">
                        <div class="flex justify-between">
                            <h5 class="text-sm font-bold text-gray-800 line-clamp-1">${item.name}</h5>
                            ${removeBtn}
                        </div>
                        <div class="flex justify-between items-center mt-1">
                            <span class="text-blue-600 font-bold text-sm">Tk ${item.price}</span>
                            <div class="flex items-center border rounded bg-gray-50">
                                <button type="button" class="px-2 text-gray-500 font-bold hover:bg-gray-200 popup-qty-dec" data-index="${index}">-</button>
                                <span class="text-xs px-2 font-bold">${item.qty}</span>
                                <button type="button" class="px-2 text-gray-500 font-bold hover:bg-gray-200 popup-qty-inc" data-index="${index}">+</button>
                            </div>
                        </div>
                        ${item.discount > 0 ? `<span class="text-[10px] text-green-600">Save Tk ${item.discount * item.qty}</span>` : ''}
                    </div>
                </div>
            `);
        });

        let hasFreeDelivery = popupCart.some(item => item.freeDelivery);
        let shipping = parseFloat($('#fmb-quick-order-form input[name="delivery_area"]:checked').val()) || 0;

        if (hasFreeDelivery) {
            shipping = 0;
            $('#fmb-modal-shipping').text('ফ্রি');
        } else {
            $('#fmb-modal-shipping').text('+' + shipping + '৳');
        }

        $('#fmb-modal-subtotal').text(subtotal + '৳');
        $('#fmb-modal-total').text((subtotal + shipping) + '৳');

        // হিডেন ইনপুট আপডেট (JSON)
        $('#popup_hidden_order_items').val(JSON.stringify(popupCart));
    }

    // পপ-আপ ওপেন ট্রিগার
    $(document).on('click', '.qs-order-popup-trigger', function (e) {
        e.preventDefault();

        var btn = $(this);
        var pid = btn.data('product-id');
        var price = parseFloat(btn.data('price')) || 0;
        var discount = parseFloat(btn.data('discount')) || 0;
        var customDelivery = parseFloat(btn.data('custom-delivery')); // Can be NaN if not set
        var freeDelivery = btn.data('free-delivery') == '1';

        // Toggle Delivery Options
        if (!isNaN(customDelivery) && customDelivery >= 0) {
            // Show Custom & Hide Default
            $('#fmb-delivery-options').addClass('hidden');
            $('#fmb-custom-delivery-option').removeClass('hidden');

            // Update Values
            $('#fmb-custom-delivery-option input').val(customDelivery).prop('checked', true).trigger('change');
            $('#fmb-custom-charge-amount').text(customDelivery + 'Tk');
        } else {
            // Show Default & Hide Custom
            $('#fmb-custom-delivery-option').addClass('hidden');
            $('#fmb-delivery-options').removeClass('hidden');

            // Reset Default Selection
            // We specifically want to check the first input within the delivery options container
            $('#fmb-delivery-options input[name="delivery_area"]').first().prop('checked', true).trigger('change');
        }

        // কার্ড থেকে টাইটেল ও ছবি নেওয়া
        var card = btn.closest('.fmb-card');
        var title = card.find('.fmb-product-title-source').text().trim();
        var imgSrc = card.find('.fmb-card-img img').attr('src');

        let topQtyInput = document.getElementById('top-qty-input');
        let selectedQty = topQtyInput ? (parseInt(topQtyInput.value) || 1) : 1;

        // পপ-আপ কার্ট রিসেট এবং নতুন প্রোডাক্ট এড
        popupCart = [{
            id: pid,
            name: title,
            price: price,
            img: imgSrc,
            qty: selectedQty,
            discount: discount,
            freeDelivery: freeDelivery
        }];

        renderPopupCart();

        // Pixel Tracking
        if (typeof fbq === 'function') {
            fbq('track', 'AddToCart', {
                content_ids: [String(pid)],
                content_type: 'product',
                value: price,
                currency: 'BDT',
                content_name: title,
                num_items: selectedQty
            });
            fbq('track', 'InitiateCheckout', {
                content_ids: [String(pid)],
                content_type: 'product',
                value: price,
                currency: 'BDT',
                content_name: title,
                num_items: 1
            });
        }


        // ৫. আপসেল (Upsell) - AJAX দিয়ে আনা
        $('#popup-upsell-wrapper').addClass('hidden');
        $('#popup-upsell-list').empty();

        $.post(fmb_vars.ajax_url, {
            action: 'fmb_get_popup_upsells',
            product_id: pid
        }, function (res) {
            if (res.success && res.data.length > 0) {
                let upsellHtml = '';
                res.data.forEach(u => {
                    upsellHtml += `
                    <div class="border rounded bg-white p-2 flex flex-col items-center text-center">
                        <img src="${u.img}" class="w-16 h-16 object-contain mb-1">
                        <h6 class="text-xs font-bold line-clamp-2 h-8 leading-tight mb-1">${u.name}</h6>
                        <span class="text-xs font-bold text-red-600 mb-2">${u.price}৳</span>
                        <button type="button" class="popup-add-upsell w-full bg-gray-800 text-white text-[10px] py-1 rounded hover:bg-black transition"
                            data-id="${u.id}" data-name="${u.name}" data-price="${u.price}" data-img="${u.img}" data-discount="${u.discount}" data-free-delivery="${u.freeDelivery ? '1' : '0'}">
                            + Add
                        </button>
                    </div>
                `;
                });
                $('#popup-upsell-list').html(upsellHtml);
                $('#popup-upsell-wrapper').removeClass('hidden');
            }
        });

        // পপ-আপ ওপেন অ্যানিমেশন
        $('#fmb-quick-order-modal').fadeIn().find('.fmb-modal-content').removeClass('animate-slideDown').addClass('animate-slideUp');
        $('body').addClass('overflow-hidden');
    });

    // Close Modal
    $(document).on('click', '.fmb-close-modal', function () {
        $('#fmb-quick-order-modal').fadeOut();
        $('body').removeClass('overflow-hidden');
    });

    $(document).on('click', '.fmb-modal-overlay', function (e) {
        if ($(e.target).hasClass('fmb-modal-overlay')) {
            $('#fmb-quick-order-modal').fadeOut();
            $('body').removeClass('overflow-hidden');
        }
    });

    // আপসেল এড করা
    $(document).on('click', '.popup-add-upsell', function () {
        let btn = $(this);
        let item = {
            id: btn.data('id'),
            name: btn.data('name'),
            price: parseFloat(btn.data('price')) || 0,
            img: btn.data('img'),
            qty: 1,
            discount: parseFloat(btn.data('discount')) || 0,
            freeDelivery: btn.data('free-delivery') == '1'
        };

        if (typeof fbq === 'function') {
            fbq('track', 'AddToCart', {
                content_ids: [String(item.id)],
                content_type: 'product',
                value: item.price,
                currency: 'BDT',
                content_name: item.name,
                num_items: 1
            });
        }

        // Check if already in cart
        let exists = popupCart.find(i => i.id == item.id);
        if (exists) {
            exists.qty++;
        } else {
            popupCart.push(item);
        }

        // Visual Feedback
        btn.text('Added!').addClass('bg-green-600').prop('disabled', true);
        setTimeout(() => {
            btn.text('+ Add').removeClass('bg-green-600').prop('disabled', false); // Optional: Keep disabled? Usually user might want to add 2. Let's reset.
        }, 1000);

        renderPopupCart();
    });

    // পপ-আপ কার্ট অ্যাকশনস
    $(document).on('click', '.popup-qty-inc', function () {
        let idx = $(this).data('index');
        popupCart[idx].qty++;
        renderPopupCart();
    });

    $(document).on('click', '.popup-qty-dec', function () {
        let idx = $(this).data('index');
        if (popupCart[idx].qty > 1) {
            popupCart[idx].qty--;
        } else {
            // Optional: Ask confirmation before removing last item? 
            // For now, allow remove or keep at 1? Usually remove if user clicks minus on 1.
            // Or maybe strictly remove button only. Let's allowing removing via minus for UX.
            if (confirm("Remove item?")) {
                popupCart.splice(idx, 1);
                if (popupCart.length === 0) {
                    // Close modal if empty? Or show empty state?
                    // Let's close modal for better UX
                    $('.fmb-close-modal').trigger('click');
                }
            }
        }
        renderPopupCart();
    });

    $(document).on('click', '.popup-remove-item', function () {
        let idx = $(this).data('index');
        popupCart.splice(idx, 1);
        if (popupCart.length === 0) $('.fmb-close-modal').trigger('click');
        renderPopupCart();
    });

    // শিপিং চার্জ পরিবর্তন (Popup)
    $('#fmb-quick-order-form input[name="delivery_area"]').on('change', renderPopupCart);


    // =========================================
    // ৪. AJAX অর্ডার সাবমিট (পপ-আপ ফর্ম - Multi Product Handler)
    // =========================================
    $('#fmb-quick-order-form').on('submit', function (e) {
        e.preventDefault();

        var form = $(this);
        var btn = form.find('.fmb-submit-btn');
        var originalHtml = btn.html();
        var msg = form.find('.fmb-loading-msg');

        if (typeof popupCart !== 'undefined' && popupCart.length === 0) {
            alert('Cart is empty!');
            return;
        }

        // তাৎক্ষণিক রেসপন্সিভ লোডিং স্টেট ও স্পিনার
        btn.prop('disabled', true).addClass('opacity-90 cursor-wait').html(`
            <span class="inline-flex items-center justify-center gap-2">
                <svg class="animate-spin h-5 w-5 text-white inline-block" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span>অর্ডার প্রসেস হচ্ছে...</span>
            </span>
        `);
        if (msg.length) msg.removeClass('hidden');

        $.ajax({
            url: (typeof fmb_vars !== 'undefined' ? fmb_vars.ajax_url : '/wp-admin/admin-ajax.php'),
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'fmb_multi_product_order',
                data: form.serialize()
            },
            timeout: 15000,
            success: function (res) {
                if (res.success && res.data && res.data.redirect_url) {
                    btn.removeClass('opacity-90 cursor-wait').addClass('bg-green-600').html(`
                        <span class="inline-flex items-center justify-center gap-2">
                            <svg class="h-5 w-5 text-white inline-block" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span>অর্ডার সফল! পেজ লোড হচ্ছে...</span>
                        </span>
                    `);
                    window.location.replace(res.data.redirect_url);
                } else {
                    alert((res.data && res.data.message) ? res.data.message : 'দুঃখিত, কোনো সমস্যা হয়েছে। আবার চেষ্টা করুন।');
                    btn.prop('disabled', false).removeClass('opacity-90 cursor-wait bg-green-600').html(originalHtml);
                    if (msg.length) msg.addClass('hidden');
                }
            },
            error: function () {
                alert('সার্ভারে সমস্যা হয়েছে। অনুগ্রহ করে পরে চেষ্টা করুন।');
                btn.prop('disabled', false).removeClass('opacity-90 cursor-wait bg-green-600').html(originalHtml);
                if (msg.length) msg.addClass('hidden');
            }
        });
    });

    // ১. কার্ট স্টেট (Cart State)
    let cart = [];

    // প্রথমে মেইন প্রডাক্ট কার্টে যোগ করা
    if (typeof mainProduct !== 'undefined') {
        cart.push(mainProduct);
    }

    // ২. রেন্ডার ফাংশন (UI Update)
    function renderCart() {
        const list = $('#order-items-list');
        list.empty();
        let subtotal = 0;
        let hasFreeDelivery = false; // logic evaluation will update this
        let logicDiscount = 0;

        cart.forEach((item, index) => {
            let itemSubtotal = item.price * item.qty;
            
            // Custom Logic Evaluation
            let dynamicLogicText = '';
            if (item.logicQty > 0 && item.logicType) {
                if (item.qty >= item.logicQty) {
                    // Reward applied!
                    if (item.logicType === 'free_delivery') {
                        hasFreeDelivery = true;
                        dynamicLogicText = `<div class="text-[11px] md:text-xs text-green-600 font-bold mt-0.5">আপনি ফ্রি ডেলিভারি পেয়েছেন!</div>`;
                    } else if (item.logicType === 'fixed') {
                        logicDiscount += item.logicValue;
                        dynamicLogicText = `<div class="text-[11px] md:text-xs text-green-600 font-bold mt-0.5">${item.logicValue}৳ ছাড় পেয়েছেন!</div>`;
                    } else if (item.logicType === 'percent') {
                        let discountAmt = (itemSubtotal * item.logicValue) / 100;
                        logicDiscount += discountAmt;
                        dynamicLogicText = `<div class="text-[11px] md:text-xs text-green-600 font-bold mt-0.5">${item.logicValue}% ছাড় পেয়েছেন!</div>`;
                    }
                } else {
                    // Prompt to add more
                    let remaining = item.logicQty - item.qty;
                    if (item.logicType === 'free_delivery') {
                        dynamicLogicText = `<div class="text-[11px] md:text-xs text-orange-600 font-semibold mt-0.5">আর ${remaining} টি অ্যাড করলে ফ্রি ডেলিভারি!</div>`;
                    } else if (item.logicType === 'fixed') {
                        dynamicLogicText = `<div class="text-[11px] md:text-xs text-orange-600 font-semibold mt-0.5">আর ${remaining} টি অ্যাড করলে ${item.logicValue}৳ ছাড়!</div>`;
                    } else if (item.logicType === 'percent') {
                        dynamicLogicText = `<div class="text-[11px] md:text-xs text-orange-600 font-semibold mt-0.5">আর ${remaining} টি অ্যাড করলে ${item.logicValue}% ছাড়!</div>`;
                    }
                }
            } else if (item.customLogic) {
                // Fallback for old custom logic text
                dynamicLogicText = `<div class="text-[11px] md:text-xs text-orange-600 font-semibold mt-0.5">${item.customLogic}</div>`;
            }

            subtotal += itemSubtotal;

            let removeBtn = index === 0 ? '' : `<span class="remove-item text-red-500 cursor-pointer ml-2 text-xs" data-index="${index}">❌</span>`;

            list.append(`
                <div class="flex items-center gap-3 bg-white p-2 rounded border border-gray-100 relative">
                    <img src="${item.img}" class="w-12 h-12 rounded object-cover border">
                    <div class="flex-grow">
                        <div class="flex justify-between">
                            <h5 class="text-sm font-bold text-gray-800 line-clamp-1">${item.name}</h5>
                            ${removeBtn}
                        </div>
                        ${dynamicLogicText}
                        <div class="flex justify-between items-center mt-1">
                            <span class="text-blue-600 font-bold text-sm">${item.qty > 1 ? `<span class="text-gray-500 font-normal text-xs mr-1">${item.price}৳ × ${item.qty} =</span>` : ''}${itemSubtotal}৳</span>
                            <div class="flex items-center border rounded bg-gray-50">
                                <button type="button" class="px-2 text-gray-500 font-bold hover:bg-gray-200 qty-dec" data-index="${index}">-</button>
                                <span class="text-xs px-2 font-bold">${item.qty}</span>
                                <button type="button" class="px-2 text-gray-500 font-bold hover:bg-gray-200 qty-inc" data-index="${index}">+</button>
                            </div>
                        </div>
                    </div>
                </div>
            `);
        });

        // টোটাল আপডেট - শুধুমাত্র এই ফর্মের ইনপুট চেক করবে
        if (!hasFreeDelivery) {
            hasFreeDelivery = cart.some(item => item.freeDelivery);
        }
        
        let shipping = parseFloat($('#multi-product-checkout-form input[name="delivery_area"]:checked').val()) || 0;
        
        if (hasFreeDelivery) {
            shipping = 0;
            $('#summary-shipping').text('ফ্রি');
            $('#multi-product-checkout-form input[name="delivery_area"]').each(function() {
                $(this).closest('label').find('.shipping-price-text').text('ফ্রি'); 
            });
        } else {
            $('#summary-shipping').text('+' + shipping + '৳');
            $('#multi-product-checkout-form input[name="delivery_area"]').each(function() {
                $(this).closest('label').find('.shipping-price-text').text($(this).val() + '৳'); 
            });
        }
        
        let total = subtotal + shipping - logicDiscount;
        if (total < 0) total = 0;
        
        let logicDiscountHTML = logicDiscount > 0 ? `<div class="flex justify-between text-sm text-green-600 font-bold mt-2"><span>ডিসকাউন্ট</span><span>-${logicDiscount}৳</span></div>` : '';
        
        $('#summary-subtotal').html(subtotal + '৳' + logicDiscountHTML);
        $('#final-total').text(total + '৳');
        $('#btn-final-total').text(total + '৳');

        // হিডেন ইনপুটে ডাটা সেট (JSON)
        $('#hidden_order_items').val(JSON.stringify(cart));
    }

    // ৩. আপসেল প্রডাক্ট এড করা
    $('.btn-add-upsell').on('click', function () {
        let btn = $(this);
        let newItem = {
            id: btn.data('id'),
            name: btn.data('name'),
            price: parseFloat(btn.data('price')),
            img: btn.data('img'),
            qty: 1,
            freeDelivery: btn.data('free-delivery') == '1',
            customLogic: btn.data('custom-logic') || '',
            logicQty: parseInt(btn.data('logic-qty')) || 0,
            logicType: btn.data('logic-type') || '',
            logicValue: parseFloat(btn.data('logic-value')) || 0
        };

        if (typeof fbq === 'function') {
            fbq('track', 'AddToCart', {
                content_ids: [String(newItem.id)],
                content_type: 'product',
                value: newItem.price,
                currency: 'BDT',
                content_name: newItem.name,
                num_items: 1
            });
        }

        // চেক করা অলরেডি আছে কিনা
        let exists = cart.find(i => i.id === newItem.id);
        if (exists) {
            exists.qty++;
            alert('পণ্যের পরিমাণ বাড়ানো হয়েছে!');
        } else {
            cart.push(newItem);
            btn.text('✔ যুক্ত হয়েছে').addClass('bg-green-600').prop('disabled', true);
        }
        renderCart();
        // স্ক্রল করে ফর্মের কাছে নেওয়া
        $('html, body').animate({ scrollTop: $("#checkout-area").offset().top - 100 }, 500);
    });

    // ৪. ইভেন্ট লিসেনারস (Qty +/- Remove)
    $(document).on('click', '.qty-inc', function () {
        let idx = $(this).data('index');
        cart[idx].qty++;
        renderCart();
    });

    $(document).on('click', '.qty-dec', function () {
        let idx = $(this).data('index');
        if (cart[idx].qty > 1) {
            cart[idx].qty--;
        } else if (idx !== 0) { // মেইন প্রডাক্ট রিমুভ হবে না 1 এর নিচে
            let removedId = cart[idx].id;
            let isBump = cart[idx].isOrderBump;
            cart.splice(idx, 1);

            // বাটন রিসেট
            if (isBump) {
                let cb = $(`.ob-checkbox`).filter(function() { return $(this).data('id') == removedId; });
                if (cb.length) {
                    cb.prop('checked', false);
                    cb.closest('.order-bump-item').find('.ob-qty-wrapper').addClass('hidden');
                }
            } else {
                $(`.btn-add-upsell[data-id="${removedId}"]`).text('+ এড করুন').removeClass('bg-green-600').prop('disabled', false);
            }
        }
        renderCart();
    });

    $(document).on('click', '.remove-item', function () {
        let idx = $(this).data('index');
        let removedId = cart[idx].id;
        let isBump = cart[idx].isOrderBump;

        cart.splice(idx, 1);

        // বাটন রিসেট
        if (isBump) {
            let cb = $(`.ob-checkbox`).filter(function() { return $(this).data('id') == removedId; });
            if (cb.length) {
                cb.prop('checked', false);
                cb.closest('.order-bump-item').find('.ob-qty-wrapper').addClass('hidden');
            }
        } else {
            $(`.btn-add-upsell[data-id="${removedId}"]`).text('+ এড করুন').removeClass('bg-green-600').prop('disabled', false);
        }

        renderCart();
    });

    $('#multi-product-checkout-form input[name="delivery_area"]').on('change', renderCart);

    // Order Bumps Logic
    $(document).on('change', '.ob-checkbox', function() {
        let cb = $(this);
        let id = cb.data('id');
        let wrapper = cb.closest('.order-bump-item');
        let qtyWrapper = wrapper.find('.ob-qty-wrapper');
        let qtyInput = qtyWrapper.find('.ob-qty-input');
        
        if (cb.is(':checked')) {
            qtyWrapper.removeClass('hidden');
            qtyInput.val(1);
            
            let newItem = {
                id: id,
                name: cb.data('name'),
                price: parseFloat(cb.data('price')),
                img: cb.data('img'),
                qty: 1,
                freeDelivery: cb.data('free-delivery') == '1', // Optional: if bumps have free delivery
                isOrderBump: true, // Flag to identify
                logicQty: parseInt(cb.data('logic-qty')) || 0,
                logicType: cb.data('logic-type') || '',
                logicValue: parseFloat(cb.data('logic-value')) || 0
            };
            
            if (typeof fbq === 'function') {
                fbq('track', 'AddToCart', {
                    content_ids: [String(newItem.id)],
                    content_type: 'product',
                    value: newItem.price,
                    currency: 'BDT',
                    content_name: newItem.name,
                    num_items: 1
                });
            }
            
            cart.push(newItem);
        } else {
            qtyWrapper.addClass('hidden');
            let idx = cart.findIndex(i => i.id == id && i.isOrderBump);
            if (idx !== -1) {
                cart.splice(idx, 1);
            }
        }
        renderCart();
    });

    $(document).on('click', '.ob-qty-inc, .ob-qty-plus', function() {
        let wrapper = $(this).closest('.order-bump-item');
        let input = wrapper.find('.ob-qty-input');
        let cb = wrapper.find('.ob-checkbox');
        let id = cb.data('id');
        
        let newQty = parseInt(input.val()) + 1;
        input.val(newQty);
        
        let cartItem = cart.find(i => i.id == id && i.isOrderBump);
        if (cartItem) {
            cartItem.qty = newQty;
            renderCart();
        }
    });

    $(document).on('click', '.ob-qty-dec, .ob-qty-minus', function() {
        let wrapper = $(this).closest('.order-bump-item');
        let input = wrapper.find('.ob-qty-input');
        let cb = wrapper.find('.ob-checkbox');
        let id = cb.data('id');
        
        let currentQty = parseInt(input.val());
        if (currentQty > 1) {
            let newQty = currentQty - 1;
            input.val(newQty);
            
            let cartItem = cart.find(i => i.id == id && i.isOrderBump);
            if (cartItem) {
                cartItem.qty = newQty;
                renderCart();
            }
        } else {
            // Uncheck and remove
            cb.prop('checked', false).trigger('change');
        }
    });

    // প্রথমবার রেন্ডার
    renderCart();

    // ৫. অর্ডার সাবমিট (AJAX Multi Product)
    $('#multi-product-checkout-form').on('submit', function (e) {
        e.preventDefault();
        let form = $(this);
        let btn = form.find('button[type="submit"]');
        let originalHtml = btn.html();

        if (typeof cart !== 'undefined' && cart.length === 0) {
            alert('দয়া করে অন্তত একটি প্রোডাক্ট সিলেক্ট করুন।');
            return;
        }

        // তাৎক্ষণিক রেসপন্সিভ লোডিং স্টেট ও স্পিনার
        btn.prop('disabled', true).addClass('opacity-90 cursor-wait').html(`
            <span class="inline-flex items-center justify-center gap-2">
                <svg class="animate-spin h-6 w-6 text-white inline-block" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span>অর্ডার প্রসেস হচ্ছে...</span>
            </span>
        `);

        $.ajax({
            url: (typeof fmb_vars !== 'undefined' ? fmb_vars.ajax_url : '/wp-admin/admin-ajax.php'),
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'fmb_multi_product_order',
                data: form.serialize()
            },
            timeout: 15000,
            success: function (res) {
                if (res.success && res.data && res.data.redirect_url) {
                    btn.removeClass('opacity-90 cursor-wait').addClass('bg-green-600').html(`
                        <span class="inline-flex items-center justify-center gap-2">
                            <svg class="h-6 w-6 text-white inline-block" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span>অর্ডার সফল! পেজ লোড হচ্ছে...</span>
                        </span>
                    `);
                    window.location.replace(res.data.redirect_url);
                } else {
                    alert((res.data && res.data.message) ? res.data.message : 'সমস্যা হয়েছে। আবার চেষ্টা করুন।');
                    btn.prop('disabled', false).removeClass('opacity-90 cursor-wait bg-green-600').html(originalHtml);
                }
            },
            error: function () {
                alert('সার্ভার থেকে রেসপন্স পেতে সমস্যা হয়েছে। অনুগ্রহ করে ইন্টারনেট সংযোগ চেক করে আবার চেষ্টা করুন।');
                btn.prop('disabled', false).removeClass('opacity-90 cursor-wait bg-green-600').html(originalHtml);
            }
        });
    });

    // =========================================
    // ৬. Quick View Logic
    // =========================================
    $(document).on('click', '.quick-view-trigger', function(e) {
        e.preventDefault();
        
        let productId = $(this).data('product-id');
        if (!productId) return;
        
        let modal = $('#fmb-quick-view-modal');
        let contentWrap = $('#quick-view-content');
        
        // Show loading state
        contentWrap.html(`
            <div class="p-12 flex justify-center items-center flex-col min-h-[300px]">
                <svg class="animate-spin h-10 w-10 text-primary mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <p class="text-gray-500 font-medium">প্রোডাক্টের বিস্তারিত লোড হচ্ছে...</p>
            </div>
        `);
        
        // Show Modal
        modal.fadeIn().find('.fmb-modal-content').removeClass('animate-slideDown').addClass('animate-slideUp');
        $('body').addClass('overflow-hidden');
        
        // Fetch Data via AJAX
        $.post(fmb_vars.ajax_url, {
            action: 'fmb_quick_view',
            product_id: productId
        }, function(res) {
            if (res.success && res.data.html) {
                contentWrap.html(res.data.html);
            } else {
                contentWrap.html('<div class="p-10 text-center text-red-500 font-bold">দুঃখিত, তথ্য লোড করা যায়নি!</div>');
            }
        }).fail(function() {
            contentWrap.html('<div class="p-10 text-center text-red-500 font-bold">দুঃখিত, সার্ভারে সমস্যা হয়েছে!</div>');
        });
    });

    // Close Quick View
    $('.fmb-close-qv, #fmb-quick-view-modal').on('click', function (e) {
        if (e.target !== this) return; // ignore bubbled events
        $('#fmb-quick-view-modal').find('.fmb-modal-content').removeClass('animate-slideUp').addClass('animate-slideDown');
        setTimeout(() => {
            $('#fmb-quick-view-modal').fadeOut();
            $('body').removeClass('overflow-hidden');
        }, 200);
    });

    // =========================================
    // ৭. Form Auto-fill & Live Lead Capture
    // =========================================
    function getCheckoutFields() {
        return {
            name: $('input[name="popup_billing_first_name"], input[name="landing_billing_first_name"]'),
            phone: $('input[name="popup_billing_phone"], input[name="landing_billing_phone"]'),
            address: $('textarea[name="popup_billing_address_1"], textarea[name="landing_billing_address_1"]')
        };
    }

    let fields = getCheckoutFields();
    if (localStorage.getItem('fmb_name')) fields.name.val(localStorage.getItem('fmb_name'));
    if (localStorage.getItem('fmb_phone')) fields.phone.val(localStorage.getItem('fmb_phone'));
    if (localStorage.getItem('fmb_address')) fields.address.val(localStorage.getItem('fmb_address'));

    fields.name.add(fields.phone).add(fields.address).on('blur', function() {
        let nameVal = fields.name.filter(function() { return $(this).val(); }).first().val() || localStorage.getItem('fmb_name') || '';
        let phoneVal = fields.phone.filter(function() { return $(this).val(); }).first().val() || localStorage.getItem('fmb_phone') || '';
        let addressVal = fields.address.filter(function() { return $(this).val(); }).first().val() || localStorage.getItem('fmb_address') || '';

        if (nameVal) localStorage.setItem('fmb_name', nameVal);
        if (phoneVal) localStorage.setItem('fmb_phone', phoneVal);
        if (addressVal) localStorage.setItem('fmb_address', addressVal);

        if (phoneVal.replace(/\D/g, '').length >= 11) {
            let leadCart = [];
            if (typeof popupCart !== 'undefined' && popupCart.length > 0) leadCart = popupCart;
            else if (typeof cart !== 'undefined' && cart.length > 0) leadCart = cart;
            else if (typeof mainProduct !== 'undefined') leadCart = [mainProduct];

            $.post(fmb_vars.ajax_url, {
                action: 'fmb_save_lead',
                name: nameVal,
                phone: phoneVal,
                address: addressVal,
                cart: JSON.stringify(leadCart),
                url: window.location.href
            });
        }
    });

});