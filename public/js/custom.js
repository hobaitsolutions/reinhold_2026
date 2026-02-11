function toggleHistoricOrder(element){
    var textelement = jQuery(element).find('span');
    var text = textelement.text();
    var container = jQuery(element).closest('.order-wrapper').next().find('.collapse');

    if (text == 'Anzeigen')
    {
        textelement.text('Ausblenden');
        container.show(1000);
    }
    else{
        textelement.text('Anzeigen');
        container.hide(1000);
    }
}

jQuery(document).ready(function () {
    jQuery('.historic-orders').each(function (){
        // var $el = $(this);
        // jQuery.get('/pleasant/mysql/historicOrders.php', function(data){
        //     $el.html(data);
        // });
    });
    if (jQuery('.product-detail-description').length) {
        // alert();
        // var str = jQuery('.product-detail-description').html();
        // var beginWarning = str.indexOf("+++");
        // var endwarning = str.lastIndexOf("+++");
        // if (beginWarning != -1) {
        //     var warning = str.substring(
        //         beginWarning + 4,
        //         endwarning
        //     );
        //     jQuery('.product-detail-description').html(str.substring(0, beginWarning) +
        //         '<div class="custom-alert alert-danger">' + warning + '</div>'+str.substring(endwarning +3));
        // }
        let x = jQuery('.product-detail-description').html();
        jQuery('.product-detail-description').html( x.replace(/\+\+\+(.*?)\+\+\+/g, '<div class="custom-alert alert-danger">$1</div>'));
    }
    jQuery('.datasheet-btn').on('click touch', function (e){
        e.preventDefault();
        var $el = jQuery(this);
        jQuery.getJSON($el.attr('href'), function (data){
            if (data.success == true){
                window.location.href = data['url'];
            }
            else{
                alert('Für diese Bestellung existieren keine Datenblätter.');
                $el.remove();
            }
        })
    });
    jQuery('.download-order-datasheets').on('click touch', function(e){
        e.preventDefault();
        var $obj = jQuery(this);
        (jQuery.ajax('/customAPI/order-datasheets.php?orderId=' + $obj.attr('data-order-id')).done(function (data){
            var data = (JSON.parse(data));
            if (data.success){
                window.location.href = data.url;
            }
            else{
                $obj.attr('disabled', 'disabled').text('Keine Datenblätter für diese Bestellung verfügbar').css({'opacity': '.3', 'cursor': 'not-allowed'}).removeClass('download-order-datasheets');
            }
        }));
    });
    if (jQuery("#orderhistory").length) {
        var table = jQuery('#orderhistory').DataTable(
            {
                responsive: false,
                autoWidth: true,
                buttons: [
                    {
                        extend: 'copy',
                    },
                    {
                        extend: 'excel',
                        title: null
                    },
                    {
                        extend: 'csv',
                    },
                    {
                        text: 'JSON',
                        action: function (e, dt, button, config) {
                            var data = dt.buttons.exportData();

                            jQuery.fn.dataTable.fileSave(
                                new Blob([JSON.stringify(data)]),
                                'Export.json'
                            );
                        }
                    },
                    {
                        extend: 'pdfHtml5',
                        exportOptions: {
                            columns: ':visible'
                        },
                        orientation: 'landscape'
                    },
                    {
                        extend: 'print',
                        exportOptions: {
                            columns: ':visible'
                        },
                    },
                ],
                dom: 'lfrtipB',
                language: {
                    "url": "/datatables_german.json"
                },
                pageLength: 25,
                order: [[1, "desc"]],
                initComplete: function (settings, json) {
                    jQuery('.table-loading').remove();
                    jQuery('.table-wrap').show();
                }
            }
        );
        table.column(7).visible(false)
        table.column(9).visible(false)
        table.column(10).visible(false)
        table.column(11).visible(false)
        table.column(12).visible(false)
    }


    jQuery('.chose-columns input').on('click touch', function (e) {
        var column = table.column(jQuery(this).val());
        column.visible(!column.visible());
    });
    if (jQuery("#quickordertable").length) {
        var quickorder = jQuery('#quickordertable').DataTable(
            {
                responsive: true,
                autoWidth: true,
                //dom: 'lfrtipB',
                dom: 'pftripl',
                language: {
                    "url": "/datatables_german.json"
                },
                search: {
                    regex: true
                },
                // buttons: [],
                //  'columns': [
                //     {data: 'image'}, /* index - 0 */
                //     {data: 'productnumber'}, /* index - 1 */
                //     {data: 'text'}, /* index - 2 */
                //     {data: 'price'}, /* index - 3 */
                //     {data: 'buy'} /* index - 4 */
                // ],
                // 'columnDefs': [{
                //     'targets': [0, 4], /* column index */
                //     'orderable': false, /* true or false */
                // }],
                pageLength: 25,
                order: [[1, "asc"]],
                initComplete: function (settings, json) {
                    jQuery('.table-loading').remove();
                    jQuery('#quickordertable').show();
                }
            }
        );
        // quickorder.buttons.disable();
        // quickorder.responsive.recalc();
    }

    jQuery('.filterAdress').on('change', function () {
        quickorder.column(1).search(jQuery(this).val(), true, false).draw();
    });

    jQuery('.lightbox').topbox();

    const cardBodies = document.querySelectorAll('.card-body[data-url]');

    cardBodies.forEach(cardBody => {
        // Make the card body clickable
        cardBody.style.cursor = 'pointer';

        cardBody.addEventListener('click', function (event) {
            // Get the URL from the data-url attribute
            const url = this.getAttribute('data-url');

            // Check if the click was on or inside a form or a link (buy button or other links)
            // We don't want to navigate away if user is clicking on a form or link
            let targetElement = event.target;

            // Traverse up the DOM to check if the click was inside a form or a link
            while (targetElement !== this) {
                if (
                    targetElement.tagName === 'FORM' ||
                    targetElement.tagName === 'A' ||
                    targetElement.tagName === 'BUTTON' ||
                    targetElement.closest('.product-action') !== null
                ) {
                    // If clicked on a form, link, button or within product-action div, don't navigate
                    return;
                }
                targetElement = targetElement.parentElement;
            }

            // Navigate to the product detail page
            window.location.href = url;
        });
    });
});


document.addEventListener('DOMContentLoaded', function () {
    const searchForm = document.getElementById('search-form');
    const searchInput = document.getElementById('search-input');
    const liveSearchResults = document.getElementById('live-search-results');
    const liveSearchResultsContainer = document.querySelector('.live-search-results-container');
    const searchSpinner = document.getElementById('search-spinner');
    const viewAllResults = document.getElementById('view-all-results');
    const viewAllResultsLink = document.getElementById('view-all-results-link');
    const viewAllContainer = document.querySelector('.live-search-view-all');
    const toggleAvailableCheckbox = document.getElementById('toggle-available');
    const toggleAvailableLabel = document.getElementById('toggle-available-label');
    const resultsCount = document.querySelector('.results-count');
    const searchResultsCount = document.querySelector('.serachresults');

    let debounceTimer;


    // Prefill search input with query parameter if it exists
    const urlParams = new URLSearchParams(window.location.search);
    const queryParam = urlParams.get('query');
    if (queryParam) {
        searchInput.value = queryParam;
    }

    // Add CSS styles for live search results
    const style = document.createElement('style');
    style.textContent = `
                .search-container {
                    position: relative;
                }
                .live-search-results {
                    position: absolute;
                    top: 100%;
                    left: 0;
                    right: 0;
                    background: white;
                    border: 1px solid #ddd;
                    border-radius: 0 0 4px 4px;
                    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
                    z-index: 1000;
                    max-height: 500px;
                    overflow-y: auto;
                    min-width: 320px;
                    @media screen and (min-width: 769px){
                        min-width: 500px;
                    }
                }
                .live-search-results-container {
                    padding: 10px;
                }
                .live-search-product {
                    display: flex;
                    margin-bottom: 10px;
                    padding-bottom: 10px;
                    border-bottom: 1px solid #eee;
                }
                .live-search-product:last-child {
                    margin-bottom: 0;
                    padding-bottom: 0;
                    border-bottom: none;
                }
                .live-search-product-image {
                    width: 60px;
                    height: 60px;
                    margin-right: 10px;
                    background-size: contain;
                    background-repeat: no-repeat;
                    background-position: center;
                }
                .live-search-product-info {
                    flex: 1;
                }
                .live-search-product-name {
                    font-weight: bold;
                    margin-bottom: 5px;
                }
                .live-search-product-price {
                    font-weight: bold;
                    color: #333;
                }
                .live-search-view-all {
                    padding: 10px;
                    text-align: center;
                    border-top: 1px solid #eee;
                }
                .search-spinner {
                    display: flex;
                    justify-content: center;
                    padding: 20px;
                }
                .search-container .d-none{
                display: none !important;
                }
                .results-count {
                    font-weight: bold;
                }
            `;
    document.head.appendChild(style);

    // Function to handle search input changes
    function handleSearchInput() {
        const query = searchInput.value.trim();

        // Clear previous results
        liveSearchResultsContainer.innerHTML = '';

        // Hide spinner initially
        searchSpinner.classList.add('d-none');

        // Hide results if query is less than 4 characters
        if (query.length < 4) {
            liveSearchResults.classList.add('d-none');
            searchSpinner.classList.add('d-none');
            return;
        }

        // Show only the spinner initially
        liveSearchResults.classList.remove('d-none');
        liveSearchResultsContainer.classList.add('d-none');
        viewAllContainer.classList.add('d-none');
        searchSpinner.classList.remove('d-none');

        // Make AJAX request
        fetch(`/customsearch/?query=${encodeURIComponent(query)}&json=true`)
            .then(response => response.json())
            .then(data => {
                // Hide spinner
                searchSpinner.classList.add('d-none');

                if (data.success && data.data && data.data.length > 0) {
                    liveSearchResultsContainer.innerHTML = '';

                    // Show results container and its content
                    liveSearchResults.classList.remove('d-none');
                    liveSearchResultsContainer.classList.remove('d-none');
                    searchSpinner.classList.add('d-none');

                    // Display up to 5 products
                    const products = data.data.slice(0, 5);

                    products.forEach(product => {
                        const productElement = document.createElement('div');
                        productElement.className = 'live-search-product';

                        let imageUrl = '';
                        if (product.cover && product.cover.media.path) {
                            imageUrl = '/' + product.cover.media.path;
                        }

                        let price = '';
                        if (product.price && product.price.gross) {
                            price = new Intl.NumberFormat('de-DE', {
                                style: 'currency',
                                currency: 'EUR'
                            }).format(product.price.gross);
                        }

                        productElement.innerHTML = `
                                    <a href="/detail/${product.id}" class="live-search-product-link" style="display: flex">
                                        <div class="live-search-product-image" style="background-image: url('${imageUrl}')"></div>
                                        <div class="live-search-product-info">
                                            <div class="live-search-product-name">${product.name}</div>
                                            <div class="live-search-product-number">${product.productNumber}</div>
                                            <div class="live-search-product-price">${price}</div>
                                        </div>
                                    </a>
                                `;

                        liveSearchResultsContainer.appendChild(productElement);
                    });

                    // Show view all checkbox and result count if there are more than 5 products
                    if (data.data.length > 5) {
                        searchSpinner.classList.add('d-none');
                        viewAllContainer.classList.remove('d-none');
                        resultsCount.textContent = `${data.data.length} Ergebnisse gefunden`;
                    } else {
                        viewAllContainer.classList.add('d-none');
                    }
                } else {
                    // No results found
                    liveSearchResults.classList.add('d-none');
                }
            })
            .catch(error => {
                console.error('Error fetching search results:', error);
                searchSpinner.classList.add('d-none');
                liveSearchResults.classList.add('d-none');
            });
    }

    // Add event listener with debounce
    searchInput.addEventListener('input', function () {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(handleSearchInput, 300);
    });

    // Close results when clicking outside
    document.addEventListener('click', function (event) {
        if (!searchInput.contains(event.target) && !liveSearchResults.contains(event.target)) {
            liveSearchResults.classList.add('d-none');
        }
    });

    // Prevent closing results when clicking inside
    liveSearchResults.addEventListener('click', function (event) {
        event.stopPropagation();
    });

    // Add event listener for view all checkbox if it exists
    if (viewAllResults) {
        viewAllResults.addEventListener('change', function () {
            if (this.checked) {
                const query = searchInput.value.trim();
                window.location.href = `/customsearch/?query=${encodeURIComponent(query)}`;
            }
        });
    }

    // Add event listener for "Alle Resultate anzeigen" link
    if (viewAllResultsLink) {
        viewAllResultsLink.addEventListener('click', function (event) {
            event.preventDefault();
            const query = searchInput.value.trim();
            window.location.href = `/customsearch/?query=${encodeURIComponent(query)}`;
        });
    }
});
