<script>
    // Base URLs for consumer tabs - append ?account_no= so same consumer sticks when switching tabs
    window.consumerTabBaseUrls = {
        consumer: "{{ route('consumer') }}",
        ledger: "{{ route('ledger') }}",
        lroLedger: "{{ route('lro-ledger') }}",
        service: "{{ route('service') }}",
        meter: "{{ route('meter') }}",
        location: "{{ route('location') }}",
        consumption: "{{ route('consumption') }}"
    };

    // Shared Consumer Header Update Script
    // This script loads the current consumer from sessionStorage and updates the header
    // Updated to work with consumer_zone table structure
    
    document.addEventListener('DOMContentLoaded', function() {
        loadConsumerHeader();
        try {
            const params = new URLSearchParams(window.location.search);
            const accountNo = params.get('account_no') || params.get('account_number') || params.get('account');
            if (accountNo && accountNo.trim()) {
                const stored = sessionStorage.getItem('currentConsumer');
                const storedObj = stored ? JSON.parse(stored) : null;
                const storedAccount = storedObj && (storedObj.account_no || storedObj.account_number);
                if (storedAccount !== accountNo.trim()) {
                    fetchConsumerByAccountNo(accountNo.trim(), function(consumer) {
                        if (consumer) {
                            sessionStorage.setItem('currentConsumer', JSON.stringify(consumer));
                            loadConsumerHeader();
                        }
                    });
                }
            }
        } catch (e) { console.error(e); }
        
        // Listen for storage changes (when consumer is searched on another page)
        window.addEventListener('storage', function(e) {
            if (e.key === 'currentConsumer') {
                loadConsumerHeader();
            }
        });
        
        // Also check periodically in case consumer was selected on same page
        setInterval(loadConsumerHeader, 1000);
        // Update tab links again after a short delay so Consumer Details, etc. stick even if URL fetch is still in progress
        setTimeout(loadConsumerHeader, 150);
    });

    function fetchConsumerByAccountNo(accountNo, callback) {
        const url = "{{ route('consumer.search') }}?search=" + encodeURIComponent(accountNo);
        fetch(url).then(function(r) { return r.json(); }).then(function(data) {
            if (data && data.success && data.consumer) {
                callback(data.consumer);
            } else {
                callback(null);
            }
        }).catch(function() { callback(null); });
    }

    function loadConsumerHeader() {
        const storedConsumer = sessionStorage.getItem('currentConsumer');
        
        if (storedConsumer) {
            try {
                const consumer = JSON.parse(storedConsumer);
                updateConsumerHeader(consumer);
                updateConsumerTabLinks(consumer);
                console.log('✅ Consumer header loaded from session:', consumer.account_no || consumer.account_number);
            } catch (e) {
                console.error('Error loading consumer from session:', e);
            }
        } else {
            updateConsumerTabLinks(null);
            // Reset to default if no consumer in session
            const headerName = document.getElementById('consumerHeaderName');
            const headerStatus = document.getElementById('consumerHeaderStatus');
            if (headerName && headerName.textContent !== 'No Consumer Selected') {
                headerName.textContent = 'No Consumer Selected';
            }
            if (headerStatus && !headerStatus.textContent.includes('Please search')) {
                headerStatus.textContent = 'Please search for a consumer';
                headerStatus.className = 'badge bg-secondary';
            }
        }
    }
    
    // Expose for ledger and other pages to sync header when they load consumer data
    window.loadConsumerHeader = loadConsumerHeader;

    function updateConsumerTabLinks(consumer) {
        if (!window.consumerTabBaseUrls) return;
        const accountNo = consumer && (consumer.account_no || consumer.account_number)
            ? (consumer.account_no || consumer.account_number)
            : null;
        const baseUrls = window.consumerTabBaseUrls;
        const tabs = document.querySelectorAll('.nav-tabs a[href]');
        function pathOnly(url) {
            if (!url) return '';
            try {
                return new URL(url, window.location.origin).pathname.replace(/\/$/, '');
            } catch (e) {
                return (url.split('?')[0] || '').replace(/\/$/, '');
            }
        }
        tabs.forEach(function(link) {
            const href = link.getAttribute('href') || '';
            const linkPath = pathOnly(href);
            let newHref = href;
            Object.keys(baseUrls).forEach(function(key) {
                const basePath = pathOnly(baseUrls[key]);
                if (linkPath === basePath) {
                    newHref = baseUrls[key] + (accountNo ? '?account_no=' + encodeURIComponent(accountNo) : '');
                }
            });
            if (newHref !== href) {
                link.setAttribute('href', newHref);
            }
        });
    }

    // function updateConsumerHeader(consumer) {
    //     // Use account_name from consumer_zone table (or fallback to old structure)
    //     const accountNo = consumer.account_no || consumer.account_number || '';
    //     const accountName = consumer.account_name || consumer.full_name || '';
        
    //     // If no account_name, try to construct from old structure
    //     let fullName = accountName;
    //     if (!fullName && (consumer.last_name || consumer.first_name)) {
    //         fullName = (consumer.last_name || '') + ', ' + (consumer.first_name || '');
    //         if (consumer.middle_name) {
    //             fullName += ' ' + consumer.middle_name.charAt(0) + '.';
    //         }
    //         if (consumer.extension) {
    //             fullName += ' ' + consumer.extension;
    //         }
    //     }

    //     // Update header name
    //     const headerName = document.getElementById('consumerHeaderName');
    //     if (headerName) {
    //         headerName.textContent = accountNo + ' ' + fullName;
    //     }

    //     // Update header status badge - use status_label or status_code from consumer_zone
    //     const headerStatus = document.getElementById('consumerHeaderStatus');
    //     if (headerStatus) {
    //         const statusText = consumer.status_label || consumer.status_code || consumer.status || 'N/A';
    //         headerStatus.textContent = statusText + ' Consumer';
            
    //         // Update badge color based on status
    //         headerStatus.className = 'badge';
    //         const statusUpper = (statusText || '').toUpperCase();
    //         if (statusUpper === 'ACTIVE' || statusUpper === 'A') {
    //             headerStatus.classList.add('bg-success');
    //         } else if (statusUpper === 'INACTIVE' || statusUpper === 'I') {
    //             headerStatus.classList.add('bg-warning');
    //         } else if (statusUpper === 'DISCONNECTED' || statusUpper === 'D') {
    //             headerStatus.classList.add('bg-danger');
    //         } else if (statusUpper === 'SUSPENDED' || statusUpper === 'S') {
    //             headerStatus.classList.add('bg-warning');
    //         } else {
    //             headerStatus.classList.add('bg-secondary');
    //         }
    //     }
    // }
     function updateConsumerHeader(consumer) {
        // Use account_name from consumer_zone table (or fallback to old structure)
        const accountNo = consumer.account_no || consumer.account_number || '';
        const accountName = consumer.account_name || consumer.full_name || '';
        
        // If no account_name, try to construct from old structure
        let fullName = accountName;
        if (!fullName && (consumer.last_name || consumer.first_name)) {
            fullName = (consumer.last_name || '') + ', ' + (consumer.first_name || '');
            if (consumer.middle_name) {
                fullName += ' ' + consumer.middle_name.charAt(0) + '.';
            }
            if (consumer.extension) {
                fullName += ' ' + consumer.extension;
            }
        }

        const statusCodeRaw = (consumer.status_code || '').toString().trim().toUpperCase();
        const statusLabelRaw = (consumer.status_label || consumer.status || '').toString().trim().toUpperCase();
        let statusCode = '';
        if (statusCodeRaw === 'A' || statusCodeRaw === 'ACTIVE') {
            statusCode = 'A';
        } else if (statusCodeRaw === 'P' || statusCodeRaw === 'PENDING') {
            statusCode = 'P';
        } else if (statusCodeRaw === 'X' || statusCodeRaw === 'D' || statusCodeRaw === 'DISCONNECTED') {
            statusCode = 'X';
        } else if (statusLabelRaw === 'ACTIVE') {
            statusCode = 'A';
        } else if (statusLabelRaw === 'PENDING') {
            statusCode = 'P';
        } else if (statusLabelRaw === 'DISCONNECTED' || statusLabelRaw === 'D') {
            statusCode = 'X';
        }
        const statusUpper = (statusCode || statusLabelRaw || 'N/A').toUpperCase();

        // Update header name (warning/danger text matches status, like badge)
        const headerName = document.getElementById('consumerHeaderName');
        if (headerName) {
            headerName.textContent = accountNo + ' ' + fullName;
            headerName.className = 'mb-1';
            if (statusUpper === 'PENDING' || statusUpper === 'P') {
                headerName.classList.add('text-warning', 'fw-semibold');
            } else if (statusUpper === 'DISCONNECTED' || statusUpper === 'X' || statusUpper === 'D') {
                headerName.classList.add('text-danger', 'fw-semibold');
            }
        }

        // Update header status badge - use status_label or status_code from consumer_zone
        const headerStatus = document.getElementById('consumerHeaderStatus');
        if (headerStatus) {
            headerStatus.textContent = (statusCode || 'N/A') + ' Consumer';
            
            // Badge colors: Active / Pending / Disconnected (matches ConsumerZone::status_label)
            headerStatus.className = 'badge';
            if (statusUpper === 'ACTIVE' || statusUpper === 'A') {
                headerStatus.classList.add('bg-success');
            } else if (statusUpper === 'PENDING' || statusUpper === 'P') {
                headerStatus.classList.add('bg-warning');
            } else if (statusUpper === 'DISCONNECTED' || statusUpper === 'X' || statusUpper === 'D') {
                headerStatus.classList.add('bg-danger');
            } else {
                headerStatus.classList.add('bg-secondary');
            }
        }
    }
    
    // Make function globally available for other scripts
    window.updateConsumerHeader = updateConsumerHeader;
    
    // Consumer Search Functionality with Autocomplete
    // This works across all consumer pages
    let searchTimeout;
    let suggestionsTimeout;
    let selectedIndex = -1;
    
    // Initialize search functionality when DOM is ready
    $(document).ready(function() {
        initializeConsumerSearch();
    });
    
    function initializeConsumerSearch() {
        const searchInput = $('#consumerSearch');
        if (!searchInput.length) {
            return; // Search input not found on this page
        }
        
        // Search input event
        searchInput.on('input', function(e) {
            const searchTerm = $(this).val().trim();
            
            // Clear previous timeout
            clearTimeout(suggestionsTimeout);
            
            // Show suggestions if search term is at least 2 characters
            if (searchTerm.length >= 2) {
                suggestionsTimeout = setTimeout(function() {
                    loadSuggestions(searchTerm);
                }, 300);
            } else {
                $('#consumerSuggestions').hide().empty();
            }
        });
        
        // Keyboard navigation
        searchInput.on('keydown', function(e) {
            const suggestions = $('#consumerSuggestions .list-group-item');
            
            if (e.keyCode === 38) { // Arrow up
                e.preventDefault();
                selectedIndex = Math.max(-1, selectedIndex - 1);
                updateSelectedSuggestion(suggestions);
            } else if (e.keyCode === 40) { // Arrow down
                e.preventDefault();
                selectedIndex = Math.min(suggestions.length - 1, selectedIndex + 1);
                updateSelectedSuggestion(suggestions);
            } else if (e.keyCode === 13) { // Enter
                e.preventDefault();
                if (selectedIndex >= 0 && suggestions.length > 0) {
                    const selected = $(suggestions[selectedIndex]);
                    selectConsumer(selected.data('account-no'), selected.data('account-name'));
                } else {
                    const searchTerm = $(this).val().trim();
                    if (searchTerm.length >= 2) {
                        searchConsumer(searchTerm);
                    }
                }
            } else if (e.keyCode === 27) { // Escape
                $('#consumerSuggestions').hide();
                selectedIndex = -1;
            }
        });
        
        // Hide suggestions when clicking outside
        $(document).on('click', function(e) {
            if (!$(e.target).closest('#consumerSearch, #consumerSuggestions').length) {
                $('#consumerSuggestions').hide();
                selectedIndex = -1;
            }
        });
    }
    
    function updateSelectedSuggestion(suggestions) {
        suggestions.removeClass('active');
        if (selectedIndex >= 0 && selectedIndex < suggestions.length) {
            $(suggestions[selectedIndex]).addClass('active');
            const parent = suggestions.parent();
            const selected = $(suggestions[selectedIndex]);
            if (parent.length && selected.length) {
                parent.scrollTop(selected.position().top + parent.scrollTop());
            }
        }
    }
    
    function loadSuggestions(query) {
        $.ajax({
            url: '{{ route("consumer.suggestions") }}',
            method: 'GET',
            data: { q: query },
            success: function(response) {
                if (response.success && response.data && response.data.length > 0) {
                    displaySuggestions(response.data);
                } else {
                    $('#consumerSuggestions').hide().empty();
                }
            },
            error: function(xhr) {
                console.error('Error loading suggestions:', xhr);
                $('#consumerSuggestions').hide().empty();
            }
        });
    }
    
    function displaySuggestions(suggestions) {
        const container = $('#consumerSuggestions');
        container.empty();
        
        suggestions.forEach(function(consumer, index) {
            const item = $('<a href="#" class="list-group-item list-group-item-action"></a>')
                .data('account-no', consumer.account_no)
                .data('account-name', consumer.account_name)
                .html('<div><strong>' + consumer.account_no + '</strong> - ' + (consumer.account_name || '') + '</div>' +
                      '<small class="text-muted">Meter: ' + (consumer.meter_number || 'N/A') + ' | Ctrl: ' + (consumer.cons_ctrl || 'N/A') + '</small>')
                .on('click', function(e) {
                    e.preventDefault();
                    selectConsumer(consumer.account_no, consumer.account_name);
                });
            
            container.append(item);
        });
        
        container.show();
        selectedIndex = -1;
    }
    
    function selectConsumer(accountNo, accountName) {
        $('#consumerSearch').val(accountNo);
        $('#consumerSuggestions').hide();
        selectedIndex = -1;
        searchConsumer(accountNo);
    }
    
    function searchConsumer(searchTerm) {
        if (!searchTerm || searchTerm.length < 2) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'warning',
                    title: 'Invalid Search',
                    text: 'Please enter at least 2 characters',
                    confirmButtonColor: '#ffc107'
                });
            }
            return;
        }

        // Hide suggestions when searching
        $('#consumerSuggestions').hide();
        selectedIndex = -1;

        // Store search term in sessionStorage for other pages
        sessionStorage.setItem('consumerSearchTerm', searchTerm);

        $.ajax({
            url: '{{ route("consumer.search") }}',
            method: 'GET',
            data: { search: searchTerm },
            success: function(response) {
                console.log('Search success:', response);
                if (response.success && response.consumer) {
                    // Store consumer in sessionStorage
                    sessionStorage.setItem('currentConsumer', JSON.stringify(response.consumer));
                    
                    // Update header
                    if (typeof updateConsumerHeader === 'function') {
                        updateConsumerHeader(response.consumer);
                    }
                    
                    // Show success message if Swal is available
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'success',
                            title: 'Consumer Found!',
                            text: 'Consumer information loaded successfully',
                            timer: 1500,
                            showConfirmButton: false
                        });
                    }
                    
                    // On non-main consumer pages (ledger, lro-ledger, service, meter, location, consumption), navigate
                    // to the same page with the new account_no so the URL and sessionStorage stay in sync.
                    const path = (window.location.pathname || '').replace(/\/$/, '');
                    const pathSegment = path.split('/').pop() || path;
                    const consumerDetailsSegments = ['consumer'];
                    const tabSegmentsThatNeedRedirect = ['ledger', 'lro-ledger', 'service', 'meter', 'location', 'consumption'];
                    const isConsumerDetailsPage = consumerDetailsSegments.some(function(s) { return pathSegment === s || path === s || path.endsWith('/' + s); });
                    const needsRedirectToNewConsumer = tabSegmentsThatNeedRedirect.some(function(s) { return pathSegment === s || path === s || path.endsWith('/' + s); });
                    if (needsRedirectToNewConsumer && !isConsumerDetailsPage) {
                        const accountNo = response.consumer.account_no || response.consumer.account_number;
                        if (accountNo) {
                            const url = new URL(window.location.href);
                            url.searchParams.set('account_no', accountNo);
                            console.log('Consumer loaded, navigating to:', url.toString());
                            setTimeout(function() { window.location.href = url.toString(); }, 100);
                        } else {
                            console.log('Consumer loaded, reloading page for new data');
                            setTimeout(function() { window.location.reload(); }, 100);
                        }
                    } else if (isConsumerDetailsPage) {
                        console.log('Consumer loaded, header updated (main consumer page)');
                        loadConsumerHeader();
                    } else {
                        // Fallback for any other consumer tab route
                        const accountNo = response.consumer.account_no || response.consumer.account_number;
                        if (accountNo) {
                            const url = new URL(window.location.href);
                            url.searchParams.set('account_no', accountNo);
                            setTimeout(function() { window.location.href = url.toString(); }, 100);
                        } else {
                            setTimeout(function() { window.location.reload(); }, 100);
                        }
                    }
                }
            },
            error: function(xhr) {
                console.log('Search error:', xhr);
                let message = 'No consumer found matching your search';
                
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    message = xhr.responseJSON.message;
                }
                
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'info',
                        title: 'Not Found',
                        text: message,
                        confirmButtonColor: '#17a2b8'
                    });
                }
            }
        });
    }
    
    // Make searchConsumer available globally
    window.searchConsumer = searchConsumer;

    // --- F1 Search Modal (same behavior as header search, works on ALL consumer tabs) ---
    let f1SuggestionsTimeout;
    let f1SelectedIndex = -1;

    // function isMainConsumerPage() {
    //     if (window.isMainConsumerPage === true) return true;
    //     const path = (window.location.pathname || '').replace(/\/$/, '');
    //     const pathSegment = path.split('/').pop() || path;
    //     return pathSegment === 'consumer' || pathSegment === 'main-consumer' || path === 'consumer' || path.endsWith('/consumer') || path.endsWith('/main-consumer');
    // }
     function isMainConsumerPage() {
        if (window.isMainConsumerPage === true) return true;
        const path = (window.location.pathname || '').replace(/\/$/, '');
        const pathSegment = path.split('/').pop() || path;
        return pathSegment === 'consumer' || pathSegment === 'main-consumer' || path === 'consumer' || path.endsWith('/consumer') || path.endsWith('/main-consumer');
    }

    $(document).on('keydown', function(e) {
        if ((e.key === 'S' || e.key === 's' || e.keyCode === 83)) {
            const isInputField = $(e.target).is('input, textarea, [contenteditable="true"], select, button');
            if (!isInputField) {
                e.preventDefault();
                $('#f1SearchModal').modal('show');
            }
        }
    });

    $('#f1SearchModal').on('shown.bs.modal', function() {
        $('#f1SearchInput').val('').focus();
        $('#f1SearchSuggestions').hide().empty();
        f1SelectedIndex = -1;
    });

    function loadF1Suggestions(query) {
        $.ajax({
            url: '{{ route("consumer.suggestions") }}',
            method: 'GET',
            data: { q: query },
            success: function(response) {
                if (response.success && response.data && response.data.length > 0) {
                    displayF1Suggestions(response.data);
                } else {
                    $('#f1SearchSuggestions').html('<div class="list-group-item text-center text-muted py-2">No consumer found</div>').show();
                    f1SelectedIndex = -1;
                }
            },
            error: function(xhr) {
                $('#f1SearchSuggestions').html('<div class="list-group-item text-center text-danger py-2">Error searching</div>').show();
                f1SelectedIndex = -1;
            }
        });
    }

    function displayF1Suggestions(suggestions) {
        const container = $('#f1SearchSuggestions');
        container.empty();
        suggestions.forEach(function(consumer) {
            const item = $('<a href="#" class="list-group-item list-group-item-action"></a>')
                .data('account-no', consumer.account_no)
                .data('account-name', consumer.account_name)
                .html('<div><strong>' + (consumer.account_no || '') + '</strong> - ' + (consumer.account_name || '') + '</div>' +
                      '<small class="text-muted">Meter: ' + (consumer.meter_number || 'N/A') + ' | Ctrl: ' + (consumer.cons_ctrl || 'N/A') + '</small>')
                .on('click', function(e) {
                    e.preventDefault();
                    selectConsumerFromF1Modal(consumer.account_no, consumer.account_name);
                });
            container.append(item);
        });
        container.show();
        f1SelectedIndex = -1;
    }

    function updateF1SelectedSuggestion() {
        const suggestions = $('#f1SearchSuggestions .list-group-item');
        suggestions.removeClass('active');
        if (f1SelectedIndex >= 0 && f1SelectedIndex < suggestions.length) {
            $(suggestions[f1SelectedIndex]).addClass('active');
            const parent = suggestions.parent();
            const selected = $(suggestions[f1SelectedIndex]);
            if (parent.length && selected.length) {
                parent.scrollTop(selected.position().top + parent.scrollTop());
            }
        }
    }

       function selectConsumerFromF1Modal(accountNo, accountName) {
        $('#f1SearchSuggestions').hide();
        f1SelectedIndex = -1;

        $.ajax({
            url: '{{ route("consumer.search") }}',
            method: 'GET',
            data: { search: accountNo },
            success: function(response) {
                if (response.success && response.consumer) {
                    const consumer = response.consumer;
                    sessionStorage.setItem('currentConsumer', JSON.stringify(consumer));

                    if (typeof updateConsumerHeader === 'function') {
                        updateConsumerHeader(consumer);
                    }
                    if (typeof updateConsumerTabLinks === 'function') {
                        updateConsumerTabLinks(consumer);
                    }

                    if (isMainConsumerPage() && typeof window.updateConsumerInfo === 'function') {
                        window.updateConsumerInfo(consumer);
                        if (typeof window.updateLatestBillCard === 'function') {
                            window.updateLatestBillCard(response.latest_bill || null);
                        }
                        if (typeof window.updateMeterReadingCard === 'function') {
                            window.updateMeterReadingCard(response.meter_reading || null);
                        }
                        if (consumer && (consumer.account_no || consumer.account_number)) {
                            var $meterAcc = $('#meterReadingAccountNo');
                            if ($meterAcc.length) $meterAcc.val(consumer.account_no || consumer.account_number);
                        }
                        $('#editConsumerBtn').prop('disabled', false);
                        $('#deleteConsumerBtn').prop('disabled', false);
                        $('#f1SearchModal').modal('hide');
                    } else {
                        // Ledger, LRO Ledger, Service, Meter, Location, Consumption: redirect to same tab with new account
                        const accNo = consumer.account_no || consumer.account_number;
                        if (accNo) {
                            const url = new URL(window.location.href);
                            url.searchParams.set('account_no', accNo);
                            $('#f1SearchModal').modal('hide');
                            window.location.href = url.toString();
                            return;
                        }
                        $('#f1SearchModal').modal('hide');
                    }

                    // Always update meter reading card when the page provides it (same as search bar behavior)
                    if (typeof window.updateMeterReadingCard === 'function') {
                        window.updateMeterReadingCard(response.meter_reading || null);
                    }
                    if (consumer && (consumer.account_no || consumer.account_number)) {
                        var $meterAccNo = $('#meterReadingAccountNo');
                        if ($meterAccNo.length) $meterAccNo.val(consumer.account_no || consumer.account_number);
                    }

                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'success',
                            title: 'Consumer Loaded!',
                            text: (consumer.account_no || '') + ' - ' + (consumer.account_name || ''),
                            timer: 1500,
                            showConfirmButton: false
                        });
                    }
                } else {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'info',
                            title: 'Not Found',
                            text: 'No consumer found',
                            confirmButtonColor: '#17a2b8'
                        });
                    }
                }
            },
            error: function(xhr) {
                const message = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'No consumer found matching your search';
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'info',
                        title: 'Not Found',
                        text: message,
                        confirmButtonColor: '#17a2b8'
                    });
                }
            }
        });
    }

    $('#f1SearchInput').on('input', function() {
        const searchTerm = $(this).val().trim();
        clearTimeout(f1SuggestionsTimeout);
        if (searchTerm.length >= 2) {
            $('#f1SearchSuggestions').html('<div class="list-group-item text-center py-2"><i class="fas fa-spinner fa-spin"></i> Searching...</div>').show();
            f1SuggestionsTimeout = setTimeout(function() {
                loadF1Suggestions(searchTerm);
            }, 300);
        } else {
            $('#f1SearchSuggestions').hide().empty();
            f1SelectedIndex = -1;
        }
    });

    $('#f1SearchInput').on('keydown', function(e) {
        const suggestions = $('#f1SearchSuggestions .list-group-item');
        if (e.keyCode === 38) {
            e.preventDefault();
            f1SelectedIndex = Math.max(-1, f1SelectedIndex - 1);
            updateF1SelectedSuggestion();
        } else if (e.keyCode === 40) {
            e.preventDefault();
            f1SelectedIndex = Math.min(suggestions.length - 1, f1SelectedIndex + 1);
            updateF1SelectedSuggestion();
        } else if (e.keyCode === 13) {
            e.preventDefault();
            if (f1SelectedIndex >= 0 && suggestions.length > 0) {
                const selected = $(suggestions[f1SelectedIndex]);
                const accountNo = selected.data('account-no');
                if (accountNo) {
                    selectConsumerFromF1Modal(accountNo, selected.data('account-name'));
                } else {
                    const searchTerm = $(this).val().trim();
                    if (searchTerm.length >= 2) selectConsumerFromF1Modal(searchTerm, '');
                }
            } else {
                const searchTerm = $(this).val().trim();
                if (searchTerm.length >= 2) selectConsumerFromF1Modal(searchTerm, '');
            }
        } else if (e.keyCode === 27) {
            $('#f1SearchSuggestions').hide();
            f1SelectedIndex = -1;
        }
    });

    $(document).on('click', function(e) {
        if (!$(e.target).closest('#f1SearchInput, #f1SearchSuggestions').length) {
            $('#f1SearchSuggestions').hide();
            f1SelectedIndex = -1;
        }
    });

    $('#f1SearchModal').on('hidden.bs.modal', function() {
        $('#f1SearchInput').val('');
        $('#f1SearchSuggestions').hide().empty();
        f1SelectedIndex = -1;
    });
</script>

<style>
    #consumerSuggestions {
        background: white;
        border: 1px solid #ddd;
    }
    #consumerSuggestions .list-group-item {
        cursor: pointer;
        border-left: none;
        border-right: none;
        padding: 10px 15px;
    }
    #consumerSuggestions .list-group-item:first-child {
        border-top: none;
    }
    #consumerSuggestions .list-group-item:last-child {
        border-bottom: none;
    }
    #consumerSuggestions .list-group-item:hover,
    #consumerSuggestions .list-group-item.active {
        background-color: #f8f9fa;
        color: #495057;
    }
    #consumerSuggestions .list-group-item small {
        display: block;
        margin-top: 4px;
    }
    #f1SearchSuggestions.list-group { background: white; border: 1px solid #ddd; }
    #f1SearchSuggestions .list-group-item { cursor: pointer; border-left: none; border-right: none; padding: 10px 15px; }
    #f1SearchSuggestions .list-group-item:first-child { border-top: none; }
    #f1SearchSuggestions .list-group-item:hover,
    #f1SearchSuggestions .list-group-item.active { background-color: #f8f9fa; color: #495057; }
    #f1SearchSuggestions .list-group-item small { display: block; margin-top: 4px; }
</style>

