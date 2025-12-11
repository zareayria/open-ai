// Debounce function to limit the rate at which a function gets called.
function debounce(func, wait) {
    let timeout;
    return function(...args) {
        const context = this;
        clearTimeout(timeout);
        timeout = setTimeout(() => func.apply(context, args), wait);
    };
}

jQuery(document).ready(function($) {
    let itemIndex = 0;

    // Function to add a new invoice item
    function addInvoiceItem(title = '', description = '') {
        const wrapper = $('#invoice-items-wrapper');
        const newItem = `
            <div class="invoice-item">
                <hr>
                <p>
                    <label>عنوان محصول</label>
                    <input type="text" name="invoice_items[${itemIndex}][title]" value="${title}">
                </p>
                <p>
                    <label>تعداد</label>
                    <input type="number" name="invoice_items[${itemIndex}][quantity]" min="1" value="1">
                </p>
                <p>
                    <label>توضیحات</label>
                    <textarea name="invoice_items[${itemIndex}][description]">${description}</textarea>
                </p>
                <button type="button" class="remove-invoice-item">حذف محصول</button>
            </div>
        `;
        wrapper.append(newItem);
        itemIndex++;
    }

    // Handle manual item addition
    $('#add-invoice-item').on('click', function() {
        addInvoiceItem();
    });

    // Handle item removal
    $('#invoice-items-wrapper').on('click', '.remove-invoice-item', function() {
        $(this).closest('.invoice-item').remove();
    });

    // Handle product search
    const searchInput = $('#product-search');
    const searchResults = $('#product-search-results');

    // Debounced search function
    const debouncedSearch = debounce(function() {
        const searchTerm = searchInput.val();

        if (searchTerm.length < 3) {
            searchResults.empty();
            return;
        }

        $.ajax({
            url: invoice_form_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'search_products',
                nonce: invoice_form_ajax.nonce,
                search_term: searchTerm,
            },
            success: function(response) {
                searchResults.empty();
                if (response.success && response.data.length > 0) {
                    const productList = $('<ul></ul>');
                    response.data.forEach(function(product) {
                        const listItem = $(`<li><a href="#" data-product-id="${product.id}">${product.title}</a></li>`);
                        productList.append(listItem);
                    });
                    searchResults.append(productList);
                } else {
                    searchResults.html('<p>محصولی یافت نشد.</p>');
                }
            }
        });
    }, 300); // 300ms delay

    searchInput.on('keyup', debouncedSearch);

    // Handle product selection from search results
    searchResults.on('click', 'a', function(e) {
        e.preventDefault();
        const productTitle = $(this).text();
        addInvoiceItem(productTitle);
        searchInput.val('');
        searchResults.empty();
    });
});
