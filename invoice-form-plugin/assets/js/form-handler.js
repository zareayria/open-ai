jQuery(document).ready(function($) {

    // Tab functionality
    $('.tabs .tab-link').on('click', function() {
        var tab_id = $(this).data('tab');

        $('.tabs .tab-link').removeClass('active');
        $('.tab-content').removeClass('active');

        $(this).addClass('active');
        $("#" + tab_id).addClass('active');
    });

    // Add new invoice item row
    $('#add-invoice-item').on('click', function() {
        var itemIndex = $('#invoice-items-wrapper .invoice-item').length;
        var newItemRow = `
            <div class="invoice-item">
                <input type="text" name="invoice_items[${itemIndex}][title]" placeholder="عنوان محصول" required>
                <input type="number" name="invoice_items[${itemIndex}][quantity]" placeholder="تعداد" min="1" value="1" required>
                <textarea name="invoice_items[${itemIndex}][description]" placeholder="توضیحات (اختیاری)"></textarea>
                <button type="button" class="remove-invoice-item">حذف</button>
            </div>`;
        $('#invoice-items-wrapper').append(newItemRow);
    });

    // Remove invoice item row
    $('#invoice-items-wrapper').on('click', '.remove-invoice-item', function() {
        $(this).closest('.invoice-item').remove();
    });

    // WooCommerce product search
    if (typeof invoice_form_ajax !== 'undefined') {
        var searchTimeout;
        $('#product-search').on('keyup', function() {
            var searchTerm = $(this).val();
            var resultsContainer = $('#product-search-results');

            clearTimeout(searchTimeout);

            if (searchTerm.length < 3) {
                resultsContainer.hide();
                return;
            }

            searchTimeout = setTimeout(function() {
                $.ajax({
                    url: invoice_form_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'search_products',
                        nonce: invoice_form_ajax.nonce,
                        search_term: searchTerm
                    },
                    success: function(response) {
                        resultsContainer.empty();
                        if (response.success && response.data.length > 0) {
                            $.each(response.data, function(index, product) {
                                resultsContainer.append('<div class="product-result" data-title="' + product.title + '">' + product.title + '</div>');
                            });
                            resultsContainer.show();
                        } else {
                            resultsContainer.hide();
                        }
                    }
                });
            }, 500);
        });

        // Handle click on a search result
        $('#product-search-results').on('click', '.product-result', function() {
            var productTitle = $(this).data('title');
            var itemIndex = $('#invoice-items-wrapper .invoice-item').length;
             var newItemRow = `
                <div class="invoice-item">
                    <input type="text" name="invoice_items[${itemIndex}][title]" value="${productTitle}" required>
                    <input type="number" name="invoice_items[${itemIndex}][quantity]" placeholder="تعداد" min="1" value="1" required>
                    <textarea name="invoice_items[${itemIndex}][description]" placeholder="توضیحات (اختیاری)"></textarea>
                    <button type="button" class="remove-invoice-item">حذف</button>
                </div>`;
            $('#invoice-items-wrapper').append(newItemRow);
            $('#product-search').val('');
            $('#product-search-results').hide();
        });

        // Hide search results when clicking outside
        $(document).on('click', function(e) {
            if (!$(e.target).closest('#product-search, #product-search-results').length) {
                $('#product-search-results').hide();
            }
        });
    }

    // Display seller details on selection
    $('#seller_id').on('change', function() {
        var selectedId = $(this).val();
        var detailsContainer = $('#seller-details-container');

        if (selectedId && typeof invoice_form_ajax.sellers !== 'undefined') {
            var selectedSeller = invoice_form_ajax.sellers.find(function(seller) {
                return seller.id == selectedId;
            });

            if (selectedSeller) {
                var sellerHtml = `
                    <div class="seller-info">
                        ${selectedSeller.image_url ? `<img src="${selectedSeller.image_url}" alt="${selectedSeller.name}" class="seller-image">` : ''}
                        <div class="seller-contact">
                            ${selectedSeller.mobile_number ? `<p class="seller-phone"><strong>شماره تماس:</strong> ${selectedSeller.mobile_number}</p>` : ''}
                        </div>
                    </div>
                `;
                detailsContainer.html(sellerHtml).slideDown();
            } else {
                detailsContainer.slideUp().empty();
            }
        } else {
            detailsContainer.slideUp().empty();
        }
    });

});
