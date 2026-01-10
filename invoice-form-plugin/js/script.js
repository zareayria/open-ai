jQuery(document).ready(function($) {

    // --- Tab Functionality ---
    $('.tabs .tab-link').on('click', function() {
        var tabId = $(this).data('tab');

        // Update active class for tabs
        $('.tabs .tab-link').removeClass('active');
        $(this).addClass('active');

        // Show/hide tab content
        $('.tab-content').removeClass('active');
        $('#' + tabId).addClass('active');

        // Enable/disable the relevant file input
        if (tabId === 'file-upload') {
            $('#invoice_file').prop('disabled', false);
            // Also disable manual entry fields so they are not submitted
            $('#manual-entry :input').prop('disabled', true);
        } else {
            $('#invoice_file').prop('disabled', true);
            $('#manual-entry :input').prop('disabled', false);
        }
    });
    // Trigger click on init to set initial disabled state
    $('.tabs .tab-link.active').trigger('click');


    // --- Seller Info Display ---
    $('#seller_id').on('change', function() {
        var sellerId = $(this).val();
        var infoBox = $('#seller-info-box');

        if (sellerId) {
            $.ajax({
                url: invoice_form_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'get_seller_info',
                    nonce: invoice_form_ajax.nonce,
                    seller_id: sellerId
                },
                dataType: 'json',
                beforeSend: function() {
                    infoBox.slideUp();
                },
                success: function(response) {
                    if (response.success) {
                        var seller = response.data;
                        $('#seller-image').attr('src', seller.image_url || '');
                        $('#seller-name-display').text(seller.name || '');
                        $('#seller-phone-display').text('موبایل: ' + (seller.mobile_number || ''));
                        infoBox.slideDown();
                    } else {
                        infoBox.slideUp();
                    }
                },
                error: function() {
                    infoBox.slideUp();
                }
            });
        } else {
            infoBox.slideUp();
        }
    });

    // --- Dynamic Invoice Item Rows ---
    var itemCounter = 1;

    function addInvoiceItem(title = '', quantity = 1, description = '') {
        var newItemRow = `
            <div class="invoice-item">
                <div class="item-col item-row-number">${itemCounter}</div>
                <div class="item-col">
                    <input type="text" name="invoice_items[${itemCounter-1}][title]" placeholder="نام محصول" value="${title}" required>
                </div>
                <div class="item-col">
                    <input type="number" name="invoice_items[${itemCounter-1}][quantity]" value="${quantity}" min="1" required>
                </div>
                <div class="item-col">
                    <input type="text" name="invoice_items[${itemCounter-1}][description]" value="${description}" placeholder="توضیحات (اختیاری)">
                </div>
                <div class="item-col">
                    <button type="button" class="remove-invoice-item">×</button>
                </div>
            </div>
        `;
        $('#invoice-items-wrapper').append(newItemRow);
        itemCounter++;
    }

    // Add first item on page load
    addInvoiceItem();

    $('#add-invoice-item').on('click', function() {
        addInvoiceItem();
    });

    // Remove item
    $('#invoice-items-wrapper').on('click', '.remove-invoice-item', function() {
        // Prevent removing the last item
        if ($('.invoice-item').length > 1) {
            $(this).closest('.invoice-item').remove();
            // Re-number the remaining items
            itemCounter = 1;
            $('.invoice-item').each(function() {
                $(this).find('.item-row-number').text(itemCounter);
                // Update array indices in name attributes
                $(this).find('input').each(function() {
                    var name = $(this).attr('name');
                    if (name) {
                        var newName = name.replace(/\[\d+\]/, '[' + (itemCounter - 1) + ']');
                        $(this).attr('name', newName);
                    }
                });
                itemCounter++;
            });
        }
    });

    // --- WooCommerce Product Search ---
    // Debounce function to limit AJAX calls
    var debounceTimeout;
    function debounce(func, delay) {
        return function() {
            var context = this;
            var args = arguments;
            clearTimeout(debounceTimeout);
            debounceTimeout = setTimeout(function() {
                func.apply(context, args);
            }, delay);
        };
    }

    var debouncedSearch = debounce(function() {
        var searchTerm = $('#product-search').val();
        var resultsContainer = $('#product-search-results');

        if (searchTerm.length < 3) {
            resultsContainer.hide();
            return;
        }

        $.ajax({
            url: invoice_form_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'search_products',
                nonce: invoice_form_ajax.nonce,
                search_term: searchTerm
            },
            dataType: 'json',
            success: function(response) {
                resultsContainer.html('').show();
                if (response.success && response.data.length > 0) {
                    $.each(response.data, function(index, product) {
                        resultsContainer.append('<div class="search-result-item" data-title="' + product.title + '">' + product.title + '</div>');
                    });
                } else {
                    resultsContainer.append('<div class="no-results">محصولی یافت نشد.</div>');
                }
            }
        });
    }, 500); // 500ms delay

    $('#product-search').on('keyup', debouncedSearch);

    // Handle click on a search result
    $(document).on('click', '.search-result-item', function() {
        var title = $(this).data('title');

        // Check if the last row is empty, if so, use it. Otherwise, add a new row.
        var lastItemTitleInput = $('.invoice-item:last').find('input[name$="[title]"]');
        if (lastItemTitleInput.val() === '') {
            lastItemTitleInput.val(title);
        } else {
             addInvoiceItem(title);
        }

        $('#product-search-results').hide();
        $('#product-search').val('');
    });

    // Hide search results if clicked outside
    $(document).on('click', function(e) {
        if (!$(e.target).closest('#product-search, #product-search-results').length) {
            $('#product-search-results').hide();
        }
    });

    // --- Form Validation ---
     $('#invoice-form').on('submit', function(e) {
        // Basic check: if manual entry is active, at least one item title must be filled.
        if ($('#manual-entry').is(':visible')) {
            var firstItemTitle = $('#invoice-items-wrapper').find('input[name="invoice_items[0][title]"]').val();
            if (!firstItemTitle || firstItemTitle.trim() === '') {
                alert('لطفاً حداقل نام یک محصول را در ردیف اول وارد کنید.');
                e.preventDefault(); // Stop form submission
            }
        }
    });

});
