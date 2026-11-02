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
        this.sourceNoteId = config.sourceNoteId || 'route-source-note';
        this.errorContainerId = config.errorContainerId || 'route-error-alert';
        this.submitButtonId = config.submitButtonId || 'btn-confirm-order';
        this.calculateEndpoint = config.calculateEndpoint || '/SIAproject/api/route/calculate';
        this.activeStop = 'pickup';
        this.requestId = 0;
        this.routeReady = false;

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
        this.restoreSavedLocations();
        const form = this.mapEl?.closest('form');
        if (form) {
            form.addEventListener('submit', (event) => {
                if (!this.routeReady) {
                    event.preventDefault();
                    this.showError('Select both locations and wait for a verified Google route before saving.');
                }
            }, true);
        }
    }

    initElements() {
        this.mapEl = document.getElementById(this.mapElementId);
        this.pickupInput = document.getElementById(this.pickupInputId);
        this.dropoffInput = document.getElementById(this.dropoffInputId);
        this.weightInput = document.getElementById(this.weightInputId);
        this.distanceDisplay = document.getElementById(this.distanceDisplayId);
        this.durationDisplay = document.getElementById(this.durationDisplayId);
        this.feeDisplay = document.getElementById(this.feeDisplayId);
        this.sourceNote = document.getElementById(this.sourceNoteId);
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
                suppressMarkers: true,
                polylineOptions: {
                    strokeColor: '#1F4FD8',
                    strokeWeight: 5,
                    strokeOpacity: 0.85
                }
            });

            this.initAutocomplete();
            this.initMapInteractions();
        } else {
            // Leaflet or fallback map rendering if Google Maps JS script not present
            this.renderMockMapPreview(defaultCenter);
        }
    }

    initMapInteractions() {
        if (!this.map || !window.google || !google.maps) return;

        this.map.addListener('click', (event) => {
            const coords = { lat: event.latLng.lat(), lng: event.latLng.lng() };
            const address = `${coords.lat.toFixed(6)}, ${coords.lng.toFixed(6)}`;
            this.setStopLocation(this.activeStop, coords, address);
        });

        document.querySelectorAll('[data-map-select]').forEach((button) => {
            button.addEventListener('click', () => {
                this.activeStop = button.dataset.mapSelect === 'dropoff' ? 'dropoff' : 'pickup';
                document.querySelectorAll('[data-map-select]').forEach((option) => {
                    const active = option === button;
                    option.classList.toggle('active', active);
                    option.setAttribute('aria-pressed', String(active));
                });
            });
        });
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
                    this.setStopLocation('pickup', {
                        lat: place.geometry.location.lat(),
                        lng: place.geometry.location.lng()
                    }, place.formatted_address || place.name || this.pickupInput.value);
                }
            });
        }

        if (this.dropoffInput) {
            this.dropoffAutocomplete = new google.maps.places.Autocomplete(this.dropoffInput, options);
            this.dropoffAutocomplete.addListener('place_changed', () => {
                const place = this.dropoffAutocomplete.getPlace();
                if (place.geometry && place.geometry.location) {
                    this.setStopLocation('dropoff', {
                        lat: place.geometry.location.lat(),
                        lng: place.geometry.location.lng()
                    }, place.formatted_address || place.name || this.dropoffInput.value);
                }
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
            this.pickupInput.addEventListener('input', () => {
                this.clearStopLocation('pickup');
                this.clearRouteEstimate();
                this.clearError();
                debouncedCalc();
            });
            this.pickupInput.addEventListener('blur', () => this.triggerRouteCalculation(true));
        }
        if (this.dropoffInput) {
            this.dropoffInput.addEventListener('input', () => {
                this.clearStopLocation('dropoff');
                this.clearRouteEstimate();
                this.clearError();
                debouncedCalc();
            });
            this.dropoffInput.addEventListener('blur', () => this.triggerRouteCalculation(true));
        }
        if (this.weightInput) {
            this.weightInput.addEventListener('change', () => this.triggerRouteCalculation());
            this.weightInput.addEventListener('input', debouncedCalc);
        }
    }

    async triggerRouteCalculation(showGeoErrors = false) {
        const pickup = this.pickupInput ? this.pickupInput.value.trim() : '';
        const dropoff = this.dropoffInput ? this.dropoffInput.value.trim() : '';
        const weight = this.weightInput ? parseFloat(this.weightInput.value) || 1.0 : 1.0;
        if (this.weightInput && (!Number.isFinite(parseFloat(this.weightInput.value)) || parseFloat(this.weightInput.value) <= 0)) return;

        if (!pickup || !dropoff) {
            return;
        }

        // Try client-side geocoding first for fast interactive feedback
        if (window.google && window.google.maps) {
            try {
                if (!this.pickupCoords && pickup.length >= 3) {
                    await this.geocodeStopIfNeeded('pickup');
                }
            } catch (err) {
                // If client geocoder fails, proceed to server-side calculation
            }
            try {
                if (!this.dropoffCoords && dropoff.length >= 3) {
                    await this.geocodeStopIfNeeded('dropoff');
                }
            } catch (err) {
                // If client geocoder fails, proceed to server-side calculation
            }
        }

        if (pickup.toLowerCase() === dropoff.toLowerCase()) {
            this.showError('Pickup and drop-off locations cannot be identical.');
            return;
        }

        const requestId = ++this.requestId;
        this.routeReady = false;
        const distanceField = document.getElementById('distance_km');
        if (distanceField) distanceField.value = '';
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
            if (requestId !== this.requestId) return;
            if (res.success && res.data) {
                this.routeReady = true;
                this.updateSummaryUI(res.data);
                this.drawRouteOnMap(res.data);
                this.routeReady = true;
            } else {
                throw new Error(res.error || 'Unable to compute route.');
            }
        })
        .catch((err) => {
            if (requestId === this.requestId) this.showError(err.message);
        })
        .finally(() => {
            if (requestId === this.requestId) this.setLoading(false);
        });
    }

    updateSummaryUI(data) {
        if (this.distanceDisplay) {
            this.distanceDisplay.textContent = `${data.distance_km} km`;
        }
        const distanceInput = document.getElementById('distance_km');
        if (distanceInput) {
            distanceInput.value = data.distance_km;
            distanceInput.dispatchEvent(new Event('input', { bubbles: true }));
        }
        if (this.durationDisplay) {
            this.durationDisplay.textContent = `~${data.duration_min} min`;
        }
        if (this.feeDisplay) {
            this.feeDisplay.textContent = `PHP ${parseFloat(data.fee).toFixed(2)}`;
        }
        if (this.sourceNote) {
            this.sourceNote.textContent = data.source === 'google_api'
                ? 'Route verified by Google Maps.'
                : 'Route estimate is approximate (offline fallback); verify the road distance before saving.';
        }

        // Store form fields (both standard and hidden)
        this.setHiddenField('distance_km', data.distance_km);
        this.setHiddenField('hidden_distance_km', data.distance_km);
        this.setHiddenField('hidden_estimated_duration_min', data.duration_min);
        this.setHiddenField('hidden_delivery_fee', data.fee);

        if (data.start_location) {
            this.setHiddenField('pickup_lat', data.start_location.lat);
            this.setHiddenField('pickup_lng', data.start_location.lng);
            this.setHiddenField('hidden_pickup_lat', data.start_location.lat);
            this.setHiddenField('hidden_pickup_lng', data.start_location.lng);
            if (!this.pickupCoords) {
                this.pickupCoords = data.start_location;
            }
            this.updateMarker('pickup', data.start_location);
        }
        if (data.end_location) {
            this.setHiddenField('dropoff_lat', data.end_location.lat);
            this.setHiddenField('dropoff_lng', data.end_location.lng);
            this.setHiddenField('hidden_dropoff_lat', data.end_location.lat);
            this.setHiddenField('hidden_dropoff_lng', data.end_location.lng);
            if (!this.dropoffCoords) {
                this.dropoffCoords = data.end_location;
            }
            this.updateMarker('dropoff', data.end_location);
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

    updateMarker(stop, coords) {
        if (!this.map || !window.google || !google.maps) return;
        const isPickup = stop === 'pickup';
        const markerKey = isPickup ? 'pickupMarker' : 'dropoffMarker';
        if (this[markerKey]) {
            this[markerKey].setPosition(coords);
            return;
        }
        this[markerKey] = new google.maps.Marker({
            map: this.map,
            position: coords,
            label: isPickup ? 'P' : 'D',
            title: isPickup ? 'Pickup location' : 'Drop-off location',
            draggable: true
        });
        this[markerKey].addListener('dragend', (event) => {
            const moved = { lat: event.latLng.lat(), lng: event.latLng.lng() };
            this.setStopLocation(stop, moved, `${moved.lat.toFixed(6)}, ${moved.lng.toFixed(6)}`);
        });
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
            const alert = document.createElement('div');
            alert.className = 'alert alert-danger d-flex align-items-center';
            alert.setAttribute('role', 'alert');
            const icon = document.createElement('i');
            icon.className = 'bi bi-exclamation-triangle-fill me-2 fs-5';
            icon.setAttribute('aria-hidden', 'true');
            const message = document.createElement('div');
            message.className = 'flex-grow-1';
            message.textContent = msg;
            alert.append(icon, message);
            this.errorContainer.replaceChildren(alert);
            this.errorContainer.classList.remove('d-none');
        } else {
            console.error(msg);
        }
        if (this.submitBtn) {
            this.submitBtn.disabled = true;
        }
        this.routeReady = false;
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

    setStopLocation(stop, coords, address) {
        const isPickup = stop === 'pickup';
        const input = isPickup ? this.pickupInput : this.dropoffInput;
        const latField = document.getElementById(`${stop}_lat`);
        const lngField = document.getElementById(`${stop}_lng`);

        if (isPickup) this.pickupCoords = coords;
        else this.dropoffCoords = coords;

        if (input && address) input.value = address;
        if (latField) latField.value = coords.lat;
        if (lngField) lngField.value = coords.lng;
        const distanceField = document.getElementById('distance_km');
        if (distanceField) distanceField.value = '';

        const markerKey = isPickup ? 'pickupMarker' : 'dropoffMarker';
        if (this[markerKey]) this[markerKey].setMap(null);
        if (this.map && window.google && google.maps) {
            this[markerKey] = new google.maps.Marker({
                map: this.map,
                position: coords,
                label: isPickup ? 'P' : 'D',
                title: isPickup ? 'Pickup location' : 'Drop-off location',
                draggable: true
            });
            this[markerKey].addListener('dragend', (event) => {
                const moved = { lat: event.latLng.lat(), lng: event.latLng.lng() };
                const movedAddress = `${moved.lat.toFixed(6)}, ${moved.lng.toFixed(6)}`;
                this.setStopLocation(stop, moved, movedAddress);
            });
        }

        this.clearRouteEstimate();
        this.clearError();
        this.triggerRouteCalculation();
    }

    clearStopLocation(stop) {
        const isPickup = stop === 'pickup';
        if (isPickup) this.pickupCoords = null;
        else this.dropoffCoords = null;
        this.setHiddenField(`${stop}_lat`, '');
        this.setHiddenField(`${stop}_lng`, '');
        const markerKey = isPickup ? 'pickupMarker' : 'dropoffMarker';
        if (this[markerKey]) {
            this[markerKey].setMap(null);
            this[markerKey] = null;
        }
    }

    restoreSavedLocations() {
        for (const stop of ['pickup', 'dropoff']) {
            const lat = parseFloat(document.getElementById(`${stop}_lat`)?.value);
            const lng = parseFloat(document.getElementById(`${stop}_lng`)?.value);
            const input = stop === 'pickup' ? this.pickupInput : this.dropoffInput;
            if (Number.isFinite(lat) && Number.isFinite(lng) && input?.value) {
                if (stop === 'pickup') this.pickupCoords = { lat, lng };
                else this.dropoffCoords = { lat, lng };
                if (this.map && window.google && google.maps) {
                    const markerKey = stop === 'pickup' ? 'pickupMarker' : 'dropoffMarker';
                    this[markerKey] = new google.maps.Marker({
                        map: this.map,
                        position: { lat, lng },
                        label: stop === 'pickup' ? 'P' : 'D',
                        title: stop === 'pickup' ? 'Pickup location' : 'Drop-off location',
                        draggable: true
                    });
                    this[markerKey].addListener('dragend', (event) => {
                        const moved = { lat: event.latLng.lat(), lng: event.latLng.lng() };
                        this.setStopLocation(stop, moved, `${moved.lat.toFixed(6)}, ${moved.lng.toFixed(6)}`);
                    });
                }
            }
        }
        if (this.pickupCoords && this.dropoffCoords) this.triggerRouteCalculation();
    }

    clearRouteEstimate() {
        this.routeReady = false;
        this.requestId++;
        this.setLoading(false);
        this.setHiddenField('distance_km', '');
        if (this.distanceDisplay) this.distanceDisplay.textContent = 'Not calculated';
        if (this.durationDisplay) this.durationDisplay.textContent = '—';
        if (this.feeDisplay) this.feeDisplay.textContent = '—';
        if (this.sourceNote) this.sourceNote.textContent = '';
        this.routeReady = false;
        if (this.submitBtn) this.submitBtn.disabled = false;
        if (this.directionsRenderer) this.directionsRenderer.set('directions', null);
        if (this.polyline) {
            this.polyline.setMap(null);
            this.polyline = null;
        }
    }

    /**
     * Geocode an address string to coordinates using Google Maps Geocoder.
     * Restricted to the Philippines (country: PH).
     * @param {string} address
     * @returns {Promise<{lat: number, lng: number, formatted: string}>}
     */
    geocodeAddress(address) {
        if (!window.google || !google.maps) {
            return Promise.reject(new Error('Google Maps not available'));
        }
        if (!this._geocoder) {
            this._geocoder = new google.maps.Geocoder();
        }
        return new Promise((resolve, reject) => {
            this._geocoder.geocode(
                { address, componentRestrictions: { country: 'PH' } },
                (results, status) => {
                    if (status === 'OK' && results && results.length > 0) {
                        const loc = results[0].geometry.location;
                        resolve({
                            lat: loc.lat(),
                            lng: loc.lng(),
                            formatted: results[0].formatted_address
                        });
                    } else {
                        reject(new Error(
                            status === 'ZERO_RESULTS'
                                ? 'No location found for this address. Try a more specific address or select from suggestions.'
                                : 'Could not look up this address. Please select from suggestions or place a pin on the map.'
                        ));
                    }
                }
            );
        });
    }

    /**
     * Geocode a stop's typed address into coordinates if not already resolved.
     * Sets coords, hidden lat/lng fields, and places a draggable marker on the map.
     * Does NOT call triggerRouteCalculation to avoid recursion.
     * @param {'pickup'|'dropoff'} stop
     */
    async geocodeStopIfNeeded(stop) {
        const isPickup = stop === 'pickup';
        const coords = isPickup ? this.pickupCoords : this.dropoffCoords;
        const input = isPickup ? this.pickupInput : this.dropoffInput;
        const address = input ? input.value.trim() : '';

        if (coords || address.length < 3) return;

        let result;
        try {
            result = await this.geocodeAddress(address);
        } catch (err) {
            const label = isPickup ? 'Pickup' : 'Drop-off';
            throw new Error(`${label}: ${err.message}`);
        }

        const newCoords = { lat: result.lat, lng: result.lng };

        if (isPickup) this.pickupCoords = newCoords;
        else this.dropoffCoords = newCoords;

        const latField = document.getElementById(`${stop}_lat`);
        const lngField = document.getElementById(`${stop}_lng`);
        if (latField) latField.value = newCoords.lat;
        if (lngField) lngField.value = newCoords.lng;

        // Place a draggable marker so the user can fine-tune the geocoded location
        const markerKey = isPickup ? 'pickupMarker' : 'dropoffMarker';
        if (this[markerKey]) this[markerKey].setMap(null);
        if (this.map && window.google && google.maps) {
            this[markerKey] = new google.maps.Marker({
                map: this.map,
                position: newCoords,
                label: isPickup ? 'P' : 'D',
                title: isPickup ? 'Pickup location' : 'Drop-off location',
                draggable: true
            });
            this[markerKey].addListener('dragend', (event) => {
                const moved = { lat: event.latLng.lat(), lng: event.latLng.lng() };
                this.setStopLocation(stop, moved, `${moved.lat.toFixed(6)}, ${moved.lng.toFixed(6)}`);
            });
        }
    }
}

// Global initialization helper
window.DeliveryMapManager = DeliveryMapManager;
