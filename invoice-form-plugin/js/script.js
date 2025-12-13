jQuery(document).ready(function($) {

    // Fetch seller info on change
    $('#seller-select').on('change', function() {
        var sellerId = $(this).val();
        var infoContainer = $('#seller-info-container');

        if (sellerId) {
            $.ajax({
                url: ifp_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'get_seller_info',
                    seller_id: sellerId
                },
                success: function(response) {
                    if (response.success) {
                        var data = response.data;
                        infoContainer.find('#seller-phone').text('تلفن: ' + data.phone);
                        if (data.photo_url) {
                            infoContainer.find('#seller-photo').attr('src', data.photo_url).show();
                        } else {
                            infoContainer.find('#seller-photo').hide();
                        }
                        infoContainer.slideDown();
                    } else {
                        infoContainer.slideUp();
                    }
                },
                error: function() {
                    infoContainer.slideUp();
                }
            });
        } else {
            infoContainer.slideUp();
        }
    });

    // Add new invoice item
    var itemIndex = 1;
    $('#add-item-btn').on('click', function() {
        var container = $('#invoice-items-container');
        var firstItem = container.find('.invoice-item:first');
        var newItem = firstItem.clone();

        newItem.find('input').val('');
        newItem.find('input').each(function() {
            var name = $(this).attr('name');
            if (name) {
                $(this).attr('name', name.replace(/\[\d+\]/, '[' + itemIndex + ']'));
            }
        });

        newItem.find('.remove-item-btn').show();
        container.append(newItem);
        itemIndex++;
    });

    // Remove invoice item
    $('#invoice-items-container').on('click', '.remove-item-btn', function() {
        if ($('#invoice-items-container .invoice-item').length > 1) {
            $(this).closest('.invoice-item').remove();
        }
    });

    // Basic form validation feedback
    $('#ifp-invoice-form').on('submit', function(e) {
        var isValid = true;
        var messages = $('#ifp-form-messages');
        messages.html('').hide();

        // Example: check if seller is selected
        if ($('#seller-select').val() === '') {
            isValid = false;
            messages.append('<p>لطفاً یک فروشنده را انتخاب کنید.</p>');
        }

        // Example: check if at least one item has a name
        var firstItemName = $('input[name="items[0][product_name]"]').val();
        if (firstItemName.trim() === '') {
             isValid = false;
             messages.append('<p>لطفاً حداقل یک محصول را با عنوان وارد کنید.</p>');
        }


        if (!isValid) {
            messages.show();
            e.preventDefault();
        }
    });
});
