<!DOCTYPE html>
<html lang="en">
@include('partials.header')

<body id="page-top">
    <div id="wrapper">
        <!-- Sidebar -->
        @include('partials.sidebar')
        <!-- Sidebar -->
        <div id="content-wrapper" class="d-flex flex-column">
            <div id="content">
                <!-- Simple Header -->
                @include('consumer.header')

              @include('consumer.nav-tabs', ['activeTab' => 'location'])

                

                <!-- Main Content Area -->
                <div class="p-3 bg-light">
                    <div class="row g-3">
                        <!-- Location Details Card -->
                        <div class="col-lg-8">
                            <div class="card mb-3">
                                <div class="card-header bg-primary text-white">
                                    <h6 class="mb-0">Consumer Location Details</h6>
                                </div>
                                <div class="card-body" id="locationDetailsCard">
                                    <div class="text-center text-muted py-4">
                                        <i class="fas fa-search fa-2x mb-3 d-block"></i>
                                        <p>Please search for a consumer to view location details</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Map Container -->
                            <div class="card">
                                <div class="card-header bg-primary text-white">
                                    <h6 class="mb-0">Location Map</h6>
                                </div>
                                <div class="card-body p-0">
                                    <div id="map" style="height: 400px; width: 100%; min-height: 400px;"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Map Controls & Nearby Consumers -->
                        <div class="col-lg-4">
                            <!-- Map Controls Card -->
                            <div class="card mb-3">
                                <div class="card-header bg-primary text-white">
                                    <h6 class="mb-0">Map Controls</h6>
                                </div>
                                <div class="card-body">
                                    <div class="d-grid gap-2">
                                        <button class="btn btn-primary btn-sm" id="centerMapBtn">
                                            <i class="fas fa-crosshairs me-1"></i>Center on Location
                                        </button>
                                        <button class="btn btn-outline-secondary btn-sm" id="streetViewBtn">
                                            <i class="fas fa-street-view me-1"></i>Street View
                                        </button>
                                        <button class="btn btn-outline-secondary btn-sm" id="directionsBtn">
                                            <i class="fas fa-directions me-1"></i>Get Directions
                                        </button>
                                        <button class="btn btn-outline-secondary btn-sm" id="printMapBtn">
                                            <i class="fas fa-print me-1"></i>Print Map
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Nearby Consumers -->
                            <div class="card">
                                <div class="card-header bg-primary text-white">
                                    <h6 class="mb-0">Consumers in Same Zone</h6>
                                </div>
                                <div class="card-body p-0">
                                    <div class="table-responsive" style="max-height: 300px; overflow-y: auto;">
                                        <table class="table table-hover mb-0 table-sm">
                                            <thead class="table-light" style="position: sticky; top: 0; z-index: 10;">
                                                <tr>
                                                    <th>Account #</th>
                                                    <th>Name</th>
                                                    <th class="text-center">Status</th>
                                                </tr>
                                            </thead>
                                            <tbody id="nearbyConsumersBody">
                                                <tr>
                                                    <td colspan="3" class="text-center text-muted py-3">
                                                        <i class="fas fa-users"></i> Search for a consumer to see nearby consumers
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!---Container Fluid-->
    </div>
</div>

<!-- Scroll to top -->
<a class="scroll-to-top rounded" href="#page-top">
    <i class="fas fa-angle-up"></i>
</a>

@include('partials.footer')
<!-- Footer -->

@include('consumer.shared-header-script')

<!-- Leaflet Map Script - Load after jQuery -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
    let map;
    let consumerMarker;
    
    // Initialize the map - default center on Guihing, Hagonoy, Davao del Sur
    function initializeMap() {
        console.log('Initializing map...');
        try {
            if (!map) {
                if (!document.getElementById('map')) {
                    console.error('Map container not found!');
                    return;
                }
                
                map = L.map('map').setView([6.684939171591083, 125.34902224603036], 15); // Guihing, Hagonoy exact coordinates

    // Add OpenStreetMap tiles
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '© OpenStreetMap contributors'
    }).addTo(map);
                
                console.log('Map initialized successfully');
            }
        } catch (error) {
            console.error('Error initializing map:', error);
        }
    }

    // Load consumer location
    function loadConsumerLocation() {
        console.log('Loading consumer location...');
        const storedConsumer = sessionStorage.getItem('currentConsumer');
        console.log('Stored consumer:', storedConsumer);
        
        if (!storedConsumer) {
            console.log('No consumer in sessionStorage');
            $('#locationDetailsCard').html(`
                <div class="text-center text-muted py-4">
                    <i class="fas fa-search fa-2x mb-3 d-block"></i>
                    <p>Please search for a consumer to view location details</p>
                    <small class="text-muted">Search for a consumer on the Consumer Details page first</small>
                </div>
            `);
            return;
        }

        try {
            const consumer = JSON.parse(storedConsumer);
            console.log('Parsed consumer data:', consumer);
            
            // Display consumer details
            const address = consumer.address1 || consumer.address || 'Address not available';
            const accountNo = consumer.account_no || consumer.account_number || '';
            const accountName = consumer.account_name || consumer.full_name || '';
            const zoneCode = consumer.zone_code || consumer.zone || '';
            const latitude = consumer.latitude;
            const longitude = consumer.longitude;
            
            console.log('Consumer has stored GPS:', latitude, longitude);
            
            $('#locationDetailsCard').html(`
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-2">
                            <label class="fw-semibold">Account Number:</label>
                            <div>${accountNo}</div>
                        </div>
                        <div class="mb-2">
                            <label class="fw-semibold">Account Name:</label>
                            <div>${accountName}</div>
                        </div>
                        <div class="mb-2">
                            <label class="fw-semibold">Address:</label>
                            <div id="displayAddress">${address}</div>
                        </div>
                        <div class="mb-2">
                            <label class="fw-semibold">Zone:</label>
                            <div>${zoneCode}</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-2">
                            <label class="fw-semibold">GPS Coordinates:</label>
                            <div id="gpsCoordinates">
                                <span class="text-muted">
                                    <i class="fas fa-spinner fa-spin"></i> ${latitude && longitude ? 'Loading map...' : 'Geocoding address...'}
                                </span>
                            </div>
                        </div>
                        <div class="mb-2">
                            <label class="fw-semibold">Meter Number:</label>
                            <div>${consumer.meter_number || 'N/A'}</div>
                        </div>
                        <div class="mb-2">
                            <label class="fw-semibold">Status:</label>
                            <div>${consumer.status_label || consumer.status_code || consumer.status || 'N/A'}</div>
                        </div>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-12">
                        <div class="alert alert-info alert-dismissible fade show" role="alert">
                            <i class="fas fa-info-circle"></i>
                            <strong>Note:</strong> ${latitude && longitude ? 
                                'Using stored GPS coordinates. If location is incorrect, you can update it by clicking on the map.' : 
                                'GPS coordinates not stored. Attempting to geocode address. For exact location, you can click on the map to set coordinates.'}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    </div>
                </div>
            `);
            
            // Check if consumer has stored GPS coordinates
            if (latitude && longitude) {
                console.log('Using stored GPS coordinates:', latitude, longitude);
                showLocationOnMap(parseFloat(latitude), parseFloat(longitude), accountNo, accountName, address, zoneCode);
                $('#gpsCoordinates').html(`${parseFloat(latitude).toFixed(6)}° N, ${parseFloat(longitude).toFixed(6)}° E 
                    <span class="badge bg-success ms-2"><i class="fas fa-check"></i> Stored</span>`);
            } else {
                console.log('No stored GPS, geocoding address...');
                // Geocode the address
                geocodeAddress(address, accountNo, accountName, zoneCode);
            }
            
            // Load nearby consumers in the same zone
            loadNearbyConsumers(zoneCode, accountNo);
            
        } catch (e) {
            console.error('Error parsing consumer data:', e);
            $('#locationDetailsCard').html(`
                <div class="text-center text-danger py-4">
                    <i class="fas fa-exclamation-triangle fa-2x mb-3 d-block"></i>
                    <p>Error loading consumer location</p>
                </div>
            `);
        }
    }

    // Show location on map
    function showLocationOnMap(lat, lon, accountNo, accountName, address, zoneCode) {
        console.log('Showing location on map:', lat, lon);
        
        // Store coordinates globally
        currentLat = lat;
        currentLon = lon;
        
        // Update map
        map.setView([lat, lon], 17); // Zoom in closer for stored coordinates
        
        // Remove previous marker if exists
        if (consumerMarker) {
            map.removeLayer(consumerMarker);
        }

    // Add marker for consumer location
        consumerMarker = L.marker([lat, lon], {
            draggable: true // Make marker draggable so user can adjust position
        }).addTo(map);
        
        consumerMarker.bindPopup(`
            <b>${accountNo} ${accountName}</b><br>
            ${address}<br>
            Zone ${zoneCode}<br>
            <small class="text-muted"><i class="fas fa-hand-pointer"></i> Drag marker to adjust location</small>
        `).openPopup();
        
        // Handle marker drag to update coordinates
        consumerMarker.on('dragend', function(e) {
            const newLat = e.target.getLatLng().lat;
            const newLon = e.target.getLatLng().lng;
            currentLat = newLat;
            currentLon = newLon;
            
            $('#gpsCoordinates').html(`
                ${newLat.toFixed(6)}° N, ${newLon.toFixed(6)}° E 
                <span class="badge bg-warning ms-2"><i class="fas fa-exclamation-triangle"></i> Modified (not saved)</span>
                <button class="btn btn-sm btn-success ms-2" onclick="saveGPSCoordinates(${newLat}, ${newLon})">
                    <i class="fas fa-save"></i> Save
                </button>
            `);
            
            Swal.fire({
                icon: 'info',
                title: 'Coordinates Updated',
                text: `New location: ${newLat.toFixed(6)}° N, ${newLon.toFixed(6)}° E`,
                showCancelButton: true,
                confirmButtonText: 'Save Coordinates',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    saveGPSCoordinates(newLat, newLon);
                }
            });
        });
    }

    // Geocode address using Nominatim (OpenStreetMap)
    function geocodeAddress(address, accountNo, accountName, zoneCode) {
        console.log('Geocoding address:', address);
        
        // Add ", Guihing, Hagonoy, Davao del Sur, Philippines" to improve geocoding accuracy
        const fullAddress = address + ', Guihing, Hagonoy, Davao del Sur, Philippines';
        console.log('Full address for geocoding:', fullAddress);
        
        // Use Nominatim API for geocoding
        $.ajax({
            url: 'https://nominatim.openstreetmap.org/search',
            data: {
                q: fullAddress,
                format: 'json',
                limit: 1,
                countrycodes: 'ph' // Limit to Philippines
            },
            success: function(data) {
                console.log('Geocoding response:', data);
                if (data && data.length > 0) {
                    const lat = parseFloat(data[0].lat);
                    const lon = parseFloat(data[0].lon);
                    
                    console.log('Coordinates found:', lat, lon);
                    
                    $('#gpsCoordinates').html(`
                        ${lat.toFixed(6)}° N, ${lon.toFixed(6)}° E 
                        <span class="badge bg-warning ms-2"><i class="fas fa-exclamation-triangle"></i> Geocoded (not saved)</span>
                        <button class="btn btn-sm btn-success ms-2" onclick="saveGPSCoordinates(${lat}, ${lon})">
                            <i class="fas fa-save"></i> Save
                        </button>
                    `);
                    
                    showLocationOnMap(lat, lon, accountNo, accountName, address, zoneCode);
                    
                } else {
                    // If geocoding fails, show default Guihing location
                    const defaultLat = 6.684939171591083;
                    const defaultLon = 125.34902224603036;
                    
                    $('#gpsCoordinates').html(`
                        <span class="text-warning">
                            <i class="fas fa-map-marker-alt"></i> Could not geocode address. 
                            Showing Guihing, Hagonoy center. Click on map to set exact location.
                        </span>
                        <button class="btn btn-sm btn-primary ms-2 mt-1" onclick="saveGPSCoordinates(${defaultLat}, ${defaultLon})">
                            <i class="fas fa-save"></i> Save Default Location
                        </button>
                    `);
                    
                    showLocationOnMap(defaultLat, defaultLon, accountNo, accountName, address, zoneCode);
                }
            },
            error: function(xhr, status, error) {
                console.error('Geocoding error:', error);
                
                // Use default Guihing center as fallback
                const defaultLat = 6.684939171591083;
                const defaultLon = 125.34902224603036;
                
                $('#gpsCoordinates').html(`
                    <span class="text-danger">
                        <i class="fas fa-exclamation-circle"></i> Geocoding failed. 
                        Click on map to set exact location.
                    </span>
                    <button class="btn btn-sm btn-primary ms-2 mt-1" onclick="saveGPSCoordinates(${defaultLat}, ${defaultLon})">
                        <i class="fas fa-save"></i> Save Default Location
                    </button>
                `);
                
                // Show default location - Guihing, Hagonoy center
                map.setView([defaultLat, defaultLon], 15);
            }
        });
    }

    // Save GPS coordinates to database
    function saveGPSCoordinates(lat, lon) {
        const storedConsumer = sessionStorage.getItem('currentConsumer');
        if (!storedConsumer) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'No consumer selected'
            });
            return;
        }

        const consumer = JSON.parse(storedConsumer);
        const consumerId = consumer.id;

        Swal.fire({
            title: 'Saving GPS Coordinates...',
            text: 'Please wait',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        $.ajax({
            url: '/api/consumer/save-gps',
            method: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                consumer_id: consumerId,
                latitude: lat,
                longitude: lon
            },
            success: function(response) {
                console.log('GPS save response:', response);
                if (response.success) {
                    // Update consumer in sessionStorage
                    consumer.latitude = lat;
                    consumer.longitude = lon;
                    sessionStorage.setItem('currentConsumer', JSON.stringify(consumer));
                    
                    $('#gpsCoordinates').html(`
                        ${lat.toFixed(6)}° N, ${lon.toFixed(6)}° E 
                        <span class="badge bg-success ms-2"><i class="fas fa-check"></i> Saved</span>
                    `);
                    
                    Swal.fire({
                        icon: 'success',
                        title: 'Saved!',
                        text: 'GPS coordinates saved successfully',
                        timer: 2000,
                        showConfirmButton: false
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: response.message || 'Failed to save coordinates'
                    });
                }
            },
            error: function(xhr) {
                console.error('GPS save error:', xhr);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Failed to save GPS coordinates. ' + (xhr.responseJSON?.message || '')
                });
            }
        });
    }
    
    // Make function globally available
    window.saveGPSCoordinates = saveGPSCoordinates;

    // Load nearby consumers (same zone)
    function loadNearbyConsumers(zoneCode, currentAccountNo) {
        if (!zoneCode) {
            $('#nearbyConsumersBody').html(`
                <tr>
                    <td colspan="3" class="text-center text-muted py-3">
                        <i class="fas fa-info-circle"></i> Zone information not available
                    </td>
                </tr>
            `);
            return;
        }

        $('#nearbyConsumersBody').html(`
            <tr>
                <td colspan="3" class="text-center py-3">
                    <i class="fas fa-spinner fa-spin"></i> Loading nearby consumers...
                </td>
            </tr>
        `);

        // Fetch consumers in the same zone
        $.ajax({
            url: '/api/consumers-by-zone',
            method: 'GET',
            data: {
                zone_code: zoneCode,
                limit: 10
            },
            success: function(response) {
                if (response.success && response.consumers && response.consumers.length > 0) {
                    let html = '';
                    response.consumers.forEach(function(consumer) {
                        // Skip the current consumer
                        if (consumer.account_no === currentAccountNo) {
                            return;
                        }
                        
                        // const statusClass = consumer.status_label === 'Active' || consumer.status_code === 'A' ? 'bg-success' : 
                        //                   (consumer.status_label === 'Inactive' || consumer.status_code === 'I' ? 'bg-warning' : 'bg-danger');
                        
                             const statusClass = consumer.status_label === 'Active' || consumer.status_code === 'A' ? 'bg-success' :
                            (consumer.status_label === 'Pending' || consumer.status_code === 'P' ? 'bg-warning' :
                            (consumer.status_label === 'Disconnected' || consumer.status_code === 'X' || consumer.status_code === 'D' ? 'bg-danger' : 'bg-secondary'));
                     
                        
                        const statusText = consumer.status_label || consumer.status_code || 'N/A';
                        
                        html += `
                            <tr>
                                <td>${consumer.account_no || ''}</td>
                                <td style="font-size: 11px;">${consumer.account_name || ''}</td>
                                <td class="text-center"><span class="badge ${statusClass}">${statusText}</span></td>
                            </tr>
                        `;
                    });
                    
                    if (html === '') {
                        html = `
                            <tr>
                                <td colspan="3" class="text-center text-muted py-3">
                                    <i class="fas fa-info-circle"></i> No other consumers in this zone
                                </td>
                            </tr>
                        `;
                    }
                    
                    $('#nearbyConsumersBody').html(html);
                } else {
                    $('#nearbyConsumersBody').html(`
                        <tr>
                            <td colspan="3" class="text-center text-muted py-3">
                                <i class="fas fa-inbox"></i> No consumers found in this zone
                            </td>
                        </tr>
                    `);
                }
            },
            error: function(xhr, status, error) {
                console.error('Error loading nearby consumers:', error);
                $('#nearbyConsumersBody').html(`
                    <tr>
                        <td colspan="3" class="text-center text-danger py-3">
                            <i class="fas fa-exclamation-triangle"></i> Error loading nearby consumers
                        </td>
                    </tr>
                `);
            }
        });
    }

    // Map control buttons
    let currentLat = null;
    let currentLon = null;

    $('#centerMapBtn').on('click', function() {
        if (consumerMarker) {
            map.setView(consumerMarker.getLatLng(), 16);
            consumerMarker.openPopup();
        } else {
            alert('Please search for a consumer first');
        }
    });

    $('#streetViewBtn').on('click', function() {
        if (currentLat && currentLon) {
            // Open Google Street View
            window.open(`https://www.google.com/maps/@?api=1&map_action=pano&viewpoint=${currentLat},${currentLon}`, '_blank');
        } else {
            alert('Location coordinates not available');
        }
    });

    $('#directionsBtn').on('click', function() {
        if (currentLat && currentLon) {
            // Open Google Maps directions
            window.open(`https://www.google.com/maps/dir/?api=1&destination=${currentLat},${currentLon}`, '_blank');
        } else {
            alert('Location coordinates not available');
        }
    });

    $('#printMapBtn').on('click', function() {
        window.print();
    });

    // Initialize map on page load
    $(document).ready(function() {
        console.log('Location page ready');
        console.log('jQuery loaded:', typeof $ !== 'undefined');
        console.log('Leaflet loaded:', typeof L !== 'undefined');
        
        initializeMap();
        
        // Allow clicking on map to set location
        if (map) {
            map.on('click', function(e) {
                const lat = e.latlng.lat;
                const lon = e.latlng.lng;
                
                Swal.fire({
                    title: 'Set Consumer Location?',
                    html: `Set consumer location to:<br><strong>${lat.toFixed(6)}° N, ${lon.toFixed(6)}° E</strong>`,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, Set Location',
                    cancelButtonText: 'Cancel'
                }).then((result) => {
                    if (result.isConfirmed) {
                        const storedConsumer = sessionStorage.getItem('currentConsumer');
                        if (storedConsumer) {
                            const consumer = JSON.parse(storedConsumer);
                            const accountNo = consumer.account_no || consumer.account_number || '';
                            const accountName = consumer.account_name || consumer.full_name || '';
                            const address = consumer.address1 || consumer.address || '';
                            const zoneCode = consumer.zone_code || consumer.zone || '';
                            
                            // Update marker position
                            if (consumerMarker) {
                                consumerMarker.setLatLng([lat, lon]);
                            } else {
                                consumerMarker = L.marker([lat, lon], { draggable: true }).addTo(map);
                            }
                            
                            currentLat = lat;
                            currentLon = lon;
                            
                            consumerMarker.bindPopup(`
                                <b>${accountNo} ${accountName}</b><br>
                                ${address}<br>
                                Zone ${zoneCode}<br>
                                <small class="text-muted"><i class="fas fa-hand-pointer"></i> Drag to adjust</small>
                            `).openPopup();
                            
                            $('#gpsCoordinates').html(`
                                ${lat.toFixed(6)}° N, ${lon.toFixed(6)}° E 
                                <span class="badge bg-warning ms-2"><i class="fas fa-exclamation-triangle"></i> Not saved</span>
                                <button class="btn btn-sm btn-success ms-2" onclick="saveGPSCoordinates(${lat}, ${lon})">
                                    <i class="fas fa-save"></i> Save
                                </button>
                            `);
                        }
                    }
                });
            });
        }
        
        // Small delay to ensure sessionStorage is populated
        setTimeout(function() {
            loadConsumerLocation();
        }, 500);
        
        // Listen for consumer changes
        window.addEventListener('storage', function(e) {
            console.log('Storage event:', e.key);
            if (e.key === 'currentConsumer') {
                loadConsumerLocation();
            }
        });
        
        // Check periodically for consumer updates
        let checkCount = 0;
        let lastConsumer = null;
        const checkInterval = setInterval(function() {
            const stored = sessionStorage.getItem('currentConsumer');
            if (stored && stored !== lastConsumer) {
                console.log('Consumer changed, reloading location');
                lastConsumer = stored;
                loadConsumerLocation();
            }
            checkCount++;
            // Stop checking after 20 times (1 minute)
            if (checkCount > 20) {
                console.log('Stopped checking for consumer updates');
                clearInterval(checkInterval);
            }
        }, 2000); // Check every 2 seconds
    });
</script>

</body>
</html>

