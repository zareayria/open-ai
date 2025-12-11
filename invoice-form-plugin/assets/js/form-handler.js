jQuery(document).ready(function($) {
    let itemIndex = 0;

    // --- Tab Switching Logic ---
    $('.tabs .tab-link').on('click', function(e) {
        e.preventDefault();

        // Remove active class from all tabs and content
        $('.tabs .tab-link').removeClass('active');
        $('.tab-content').removeClass('active');

        // Add active class to the clicked tab and its content
        $(this).addClass('active');
        const tabId = $(this).data('tab');
        $('#' + tabId).addClass('active');
    });

    // --- Manual Item Entry Logic ---

    // Function to add a new invoice item row
    function addInvoiceItem(title = '', description = '') {
        const wrapper = $('#invoice-items-wrapper');
        const newItemHTML = `
            <div class="invoice-item">
                <input type="text" name="invoice_items[${itemIndex}][title]" placeholder="عنوان محصول" value="${title}" required>
                <input type="number" name="invoice_items[${itemIndex}][quantity]" min="1" value="1" placeholder="تعداد" required>
                <input type="text" name="invoice_items[${itemIndex}][description]" placeholder="توضیحات (اختیاری)" value="${description}">
                <button type="button" class="remove-item-btn" title="حذف محصول">&times;</button>
            </div>
        `;
        wrapper.append(newItemHTML);
        itemIndex++;
    }

    // Add a new item when the button is clicked
    $('#add-invoice-item').on('click', function() {
        addInvoiceItem();
    });

    // Remove an item row when the remove button is clicked
    $('#invoice-items-wrapper').on('click', '.remove-item-btn', function() {
        $(this).closest('.invoice-item').remove();
    });

    // Add the first item row initially
    addInvoiceItem();


    // --- WooCommerce Product Search Logic ---

    const searchInput = $('#product-search');
    const searchResults = $('#product-search-results');
    let searchTimeout;

    searchInput.on('keyup', function() {
        const searchTerm = $(this).val();

        clearTimeout(searchTimeout);

        if (searchTerm.length < 3) {
            searchResults.empty().hide();
            return;
        }

        searchTimeout = setTimeout(function() {
            $.ajax({
                url: invoice_form_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'search_products',
                    nonce: invoice_form_ajax.nonce,
                    search_term: searchTerm,
                },
                beforeSend: function() {
                    searchResults.html('<div class="search-loading">در حال جستجو...</div>').show();
                },
                success: function(response) {
                    searchResults.empty();
                    if (response.success && response.data.length > 0) {
                        response.data.forEach(function(product) {
                            const resultItem = $(`<div class="search-result-item" data-title="${product.title}">${product.title}</div>`);
                            searchResults.append(resultItem);
                        });
                    } else {
                        searchResults.html('<div class="search-no-results">محصولی یافت نشد.</div>');
                    }
                },
                error: function() {
                     searchResults.html('<div class="search-error">خطا در جستجو.</div>');
                }
            });
        }, 500); // Debounce for 500ms
    });

    // Handle product selection from search results
    searchResults.on('click', '.search-result-item', function() {
        const productTitle = $(this).data('title');

        // Check if the last item row is empty, if so, use it. Otherwise, add a new one.
        const lastItemTitleInput = $('#invoice-items-wrapper .invoice-item:last-child input[type="text"]').first();
        if (lastItemTitleInput.val() === '') {
            lastItemTitleInput.val(productTitle);
        } else {
            addInvoiceItem(productTitle);
        }

        searchInput.val('');
        searchResults.empty().hide();
    });

    // Hide search results when clicking outside
    $(document).on('click', function(e) {
        if (!$(e.target).closest('#product-search, #product-search-results').length) {
            searchResults.empty().hide();
        }
    });

});
