/**
 * Google Maps & Route Preview Component
 * Implements Places Autocomplete (country: PH), Interactive Polyline Map,
 * Dynamic Fee Estimation, and Keyboard-Accessible Typed Address Fallback.
 */

class DeliveryMapManager {
    constructor(config = {}) {
        this.mapElementId = config.mapElementId || 'delivery-map';
        this.pickupInputId = config.pickupInputId || 'pickup_address';
        this.dropoffInputId = config.dropoffInputId || 'dropoff_address';
        this.weightInputId = config.weightInputId || 'parcel_weight';
        this.distanceDisplayId = config.distanceDisplayId || 'summary-distance';
        this.durationDisplayId = config.durationDisplayId || 'summary-duration';
        this.feeDisplayId = config.feeDisplayId || 'summary-fee';
        this.errorContainerId = config.errorContainerId || 'route-error-alert';
        this.submitButtonId = config.submitButtonId || 'btn-confirm-order';
        this.calculateEndpoint = config.calculateEndpoint || '/SIAproject/api/route/calculate';

        this.map = null;
        this.directionsRenderer = null;
        this.pickupMarker = null;
        this.dropoffMarker = null;
        this.polyline = null;

        this.pickupCoords = null;
        this.dropoffCoords = null;
        this.debounceTimer = null;

        this.init();
    }

    init() {
        this.initElements();
        this.initGoogleMaps();
        this.attachEventListeners();
    }

    initElements() {
        this.mapEl = document.getElementById(this.mapElementId);
        this.pickupInput = document.getElementById(this.pickupInputId);
        this.dropoffInput = document.getElementById(this.dropoffInputId);
        this.weightInput = document.getElementById(this.weightInputId);
        this.distanceDisplay = document.getElementById(this.distanceDisplayId);
        this.durationDisplay = document.getElementById(this.durationDisplayId);
        this.feeDisplay = document.getElementById(this.feeDisplayId);
        this.errorContainer = document.getElementById(this.errorContainerId);
        this.submitBtn = document.getElementById(this.submitButtonId);
    }

    initGoogleMaps() {
        if (!this.mapEl) return;

        // Default centered on Metro Manila
        const defaultCenter = { lat: 14.5547, lng: 121.0244 };

        if (window.google && window.google.maps) {
            this.map = new google.maps.Map(this.mapEl, {
                center: defaultCenter,
                zoom: 12,
                mapTypeControl: false,
                streetViewControl: false,
                fullscreenControl: true,
                zoomControl: true
            });

            this.directionsRenderer = new google.maps.DirectionsRenderer({
                map: this.map,
                suppressMarkers: false,
                polylineOptions: {
                    strokeColor: '#1F4FD8',
                    strokeWeight: 5,
                    strokeOpacity: 0.85
                }
            });

            this.initAutocomplete();
        } else {
            // Leaflet or fallback map rendering if Google Maps JS script not present
            this.renderMockMapPreview(defaultCenter);
        }
    }

    initAutocomplete() {
        if (!window.google || !google.maps.places) return;

        const options = {
            componentRestrictions: { country: 'ph' },
            fields: ['formatted_address', 'geometry', 'name']
        };

        if (this.pickupInput) {
            this.pickupAutocomplete = new google.maps.places.Autocomplete(this.pickupInput, options);
            this.pickupAutocomplete.addListener('place_changed', () => {
                const place = this.pickupAutocomplete.getPlace();
                if (place.geometry && place.geometry.location) {
                    this.pickupCoords = {
                        lat: place.geometry.location.lat(),
                        lng: place.geometry.location.lng()
                    };
                }
                this.triggerRouteCalculation();
            });
        }

        if (this.dropoffInput) {
            this.dropoffAutocomplete = new google.maps.places.Autocomplete(this.dropoffInput, options);
            this.dropoffAutocomplete.addListener('place_changed', () => {
                const place = this.dropoffAutocomplete.getPlace();
                if (place.geometry && place.geometry.location) {
                    this.dropoffCoords = {
                        lat: place.geometry.location.lat(),
                        lng: place.geometry.location.lng()
                    };
                }
                this.triggerRouteCalculation();
            });
        }
    }

    renderMockMapPreview(center) {
        if (!this.mapEl) return;
        this.mapEl.innerHTML = `
            <div class="d-flex flex-column align-items-center justify-content-center h-100 bg-light text-muted p-4 border rounded" style="min-height: 280px;">
                <i class="bi bi-geo-alt-fill text-primary fs-1 mb-2"></i>
                <div class="fw-semibold text-dark">Interactive Route Preview</div>
                <div class="small text-center mt-1" id="mock-map-status">Enter pickup and drop-off addresses to preview distance, travel time, and fee.</div>
                <div id="mock-route-summary" class="mt-3 d-none">
                    <span class="badge bg-primary px-3 py-2 fs-6" id="mock-route-tag">Route calculated</span>
                </div>
            </div>
        `;
    }

    attachEventListeners() {
        const debouncedCalc = () => {
            clearTimeout(this.debounceTimer);
            this.debounceTimer = setTimeout(() => this.triggerRouteCalculation(), 600);
        };

        if (this.pickupInput) {
            this.pickupInput.addEventListener('input', debouncedCalc);
            this.pickupInput.addEventListener('blur', () => this.triggerRouteCalculation());
        }
        if (this.dropoffInput) {
            this.dropoffInput.addEventListener('input', debouncedCalc);
            this.dropoffInput.addEventListener('blur', () => this.triggerRouteCalculation());
        }
        if (this.weightInput) {
            this.weightInput.addEventListener('change', () => this.triggerRouteCalculation());
            this.weightInput.addEventListener('input', debouncedCalc);
        }
    }

    triggerRouteCalculation() {
        const pickup = this.pickupInput ? this.pickupInput.value.trim() : '';
        const dropoff = this.dropoffInput ? this.dropoffInput.value.trim() : '';
        const weight = this.weightInput ? parseFloat(this.weightInput.value) || 1.0 : 1.0;

        if (!pickup || !dropoff) {
            return;
        }

        if (pickup.toLowerCase() === dropoff.toLowerCase()) {
            this.showError('Pickup and drop-off locations cannot be identical.');
            return;
        }

        this.setLoading(true);
        this.clearError();

        const payload = {
            origin: this.pickupCoords || pickup,
            destination: this.dropoffCoords || dropoff,
            weight_kg: weight
        };

        fetch(this.calculateEndpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(payload)
        })
        .then(async (response) => {
            const data = await response.json();
            if (!response.ok) {
                throw new Error(data.error || 'Failed to calculate delivery route.');
            }
            return data;
        })
        .then((res) => {
            if (res.success && res.data) {
                this.updateSummaryUI(res.data);
                this.drawRouteOnMap(res.data);
            } else {
                throw new Error(res.error || 'Unable to compute route.');
            }
        })
        .catch((err) => {
            this.showError(err.message);
        })
        .finally(() => {
            this.setLoading(false);
        });
    }

    updateSummaryUI(data) {
        if (this.distanceDisplay) {
            this.distanceDisplay.textContent = `${data.distance_km} km`;
        }
        if (this.durationDisplay) {
            this.durationDisplay.textContent = `~${data.duration_min} min`;
        }
        if (this.feeDisplay) {
            this.feeDisplay.textContent = `PHP ${parseFloat(data.fee).toFixed(2)}`;
        }

        // Store hidden fields in order form if present
        this.setHiddenField('hidden_distance_km', data.distance_km);
        this.setHiddenField('hidden_estimated_duration_min', data.duration_min);
        this.setHiddenField('hidden_delivery_fee', data.fee);
        if (data.start_location) {
            this.setHiddenField('hidden_pickup_lat', data.start_location.lat);
            this.setHiddenField('hidden_pickup_lng', data.start_location.lng);
        }
        if (data.end_location) {
            this.setHiddenField('hidden_dropoff_lat', data.end_location.lat);
            this.setHiddenField('hidden_dropoff_lng', data.end_location.lng);
        }

        const mockStatus = document.getElementById('mock-map-status');
        const mockSummary = document.getElementById('mock-route-summary');
        const mockTag = document.getElementById('mock-route-tag');
        if (mockStatus && mockSummary && mockTag) {
            mockStatus.textContent = `Pickup: ${data.start_address} → Drop-off: ${data.end_address}`;
            mockTag.textContent = `${data.distance_km} km • ~${data.duration_min} min • PHP ${data.fee}`;
            mockSummary.classList.remove('d-none');
        }
    }

    drawRouteOnMap(data) {
        if (!this.map || !window.google) return;

        if (data.start_location && data.end_location) {
            const bounds = new google.maps.LatLngBounds();
            const p1 = new google.maps.LatLng(data.start_location.lat, data.start_location.lng);
            const p2 = new google.maps.LatLng(data.end_location.lat, data.end_location.lng);

            bounds.extend(p1);
            bounds.extend(p2);
            this.map.fitBounds(bounds, { top: 40, right: 40, bottom: 40, left: 40 });

            // If overview_polyline is present and geometry library exists
            if (data.overview_polyline && google.maps.geometry && google.maps.geometry.encoding) {
                if (this.polyline) {
                    this.polyline.setMap(null);
                }
                const path = google.maps.geometry.encoding.decodePath(data.overview_polyline);
                this.polyline = new google.maps.Polyline({
                    path: path,
                    strokeColor: '#1F4FD8',
                    strokeOpacity: 0.85,
                    strokeWeight: 5,
                    map: this.map
                });
            }
        }
    }

    setLoading(loading) {
        if (this.submitBtn) {
            this.submitBtn.disabled = loading;
            if (loading) {
                this.submitBtn.dataset.origText = this.submitBtn.innerHTML;
                this.submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Calculating route...';
            } else if (this.submitBtn.dataset.origText) {
                this.submitBtn.innerHTML = this.submitBtn.dataset.origText;
            }
        }
    }

    showError(msg) {
        if (this.errorContainer) {
            this.errorContainer.innerHTML = `
                <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
                    <div class="flex-grow-1">${msg}</div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            `;
            this.errorContainer.classList.remove('d-none');
        } else {
            console.error(msg);
        }
        if (this.submitBtn) {
            this.submitBtn.disabled = true;
        }
    }

    clearError() {
        if (this.errorContainer) {
            this.errorContainer.innerHTML = '';
            this.errorContainer.classList.add('d-none');
        }
    }

    setHiddenField(id, val) {
        const el = document.getElementById(id);
        if (el) el.value = val;
    }
}

// Global initialization helper
window.DeliveryMapManager = DeliveryMapManager;
