/**
 * Google Maps & Route Preview Component
 * Implements Places Autocomplete (country: PH), Interactive Polyline Map,
 * Dynamic Fee Estimation, Separate Coordinate Address Input via Pinpoints,
 * Priority Coordinate Rendering, and Keyboard-Accessible Fallbacks.
 */

class DeliveryMapManager {
    constructor(config = {}) {
        this.mapElementId = config.mapElementId || 'delivery-map';
        this.pickupInputId = config.pickupInputId || 'pickup_address';
        this.dropoffInputId = config.dropoffInputId || 'dropoff_address';
        this.pickupCoordsId = config.pickupCoordsId || 'pickup_coords';
        this.dropoffCoordsId = config.dropoffCoordsId || 'dropoff_coords';
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

        // Pinpoint coordinates have strict priority over typed address text
        this.pickupPinCoords = null;
        this.dropoffPinCoords = null;
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
        this.pickupCoordsInput = document.getElementById(this.pickupCoordsId);
        this.dropoffCoordsInput = document.getElementById(this.dropoffCoordsId);
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
            // Mock map rendering if Google Maps JS script not present
            this.renderMockMapPreview(defaultCenter);
        }
    }

    initMapInteractions() {
        if (!this.map || !window.google || !google.maps) return;

        // Clicking on the map places a pinpoint for the active stop
        this.map.addListener('click', (event) => {
            const coords = { lat: event.latLng.lat(), lng: event.latLng.lng() };
            this.setStopPinpoint(this.activeStop, coords);
        });

        // Pin mode buttons: "Place pickup pin" vs "Place drop-off pin"
        document.querySelectorAll('[data-map-select]').forEach((button) => {
            button.addEventListener('click', () => {
                this.activeStop = button.dataset.mapSelect === 'dropoff' ? 'dropoff' : 'pickup';
                const activeStopHint = document.getElementById('active-stop-hint');
                if (activeStopHint) {
                    activeStopHint.textContent = `Map clicks set the ${this.activeStop === 'pickup' ? 'pickup' : 'drop-off'} pin.`;
                }
                document.querySelectorAll('[data-map-select]').forEach((option) => {
                    const active = option === button;
                    option.classList.toggle('active', active);
                    option.setAttribute('aria-pressed', String(active));
                });
            });
        });

        // "Clear pin" buttons in coordinate address input groups
        document.querySelectorAll('[data-clear-stop]').forEach((button) => {
            button.addEventListener('click', () => {
                const stop = button.dataset.clearStop === 'dropoff' ? 'dropoff' : 'pickup';
                this.clearStopPinpoint(stop);
            });
        });
    }

    initAutocomplete() {
        if (!window.google || !google.maps.places) return;

        const options = {
            componentRestrictions: { country: 'ph' },
            fields: ['formatted_address', 'geometry', 'name']
        };

        if (this.pickupInput && this.pickupInput.type !== 'hidden') {
            this.pickupAutocomplete = new google.maps.places.Autocomplete(this.pickupInput, options);
            this.pickupAutocomplete.addListener('place_changed', () => {
                const place = this.pickupAutocomplete.getPlace();
                if (place.geometry && place.geometry.location) {
                    const loc = {
                        lat: place.geometry.location.lat(),
                        lng: place.geometry.location.lng()
                    };
                    // Populate Address text box with selected place
                    this.pickupInput.value = place.formatted_address || place.name || this.pickupInput.value;

                    // Selecting an address suggestion supersedes any older pin.
                    if (this.pickupPinCoords) this.clearStopPinpoint('pickup');
                    // Pan map to the selected result to help the user confirm its location.
                    if (this.map) {
                        this.map.panTo(loc);
                        this.map.setZoom(15);
                    }
                    this.triggerRouteCalculation();
                }
            });
        }

        if (this.dropoffInput && this.dropoffInput.type !== 'hidden') {
            this.dropoffAutocomplete = new google.maps.places.Autocomplete(this.dropoffInput, options);
            this.dropoffAutocomplete.addListener('place_changed', () => {
                const place = this.dropoffAutocomplete.getPlace();
                if (place.geometry && place.geometry.location) {
                    const loc = {
                        lat: place.geometry.location.lat(),
                        lng: place.geometry.location.lng()
                    };
                    this.dropoffInput.value = place.formatted_address || place.name || this.dropoffInput.value;

                    // Selecting an address suggestion supersedes any older pin.
                    if (this.dropoffPinCoords) this.clearStopPinpoint('dropoff');
                    if (this.map) {
                        this.map.panTo(loc);
                        this.map.setZoom(15);
                    }
                    this.triggerRouteCalculation();
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

        if (this.pickupInput && this.pickupInput.type !== 'hidden') {
            this.pickupInput.addEventListener('input', () => {
                // Editing the address means any previous pin may no longer match it.
                if (this.pickupPinCoords) this.clearStopPinpoint('pickup');
                this.clearRouteEstimate();
                this.clearError();
                debouncedCalc();
            });
            this.pickupInput.addEventListener('blur', () => {
                if (!this.pickupPinCoords) {
                    this.triggerRouteCalculation(true);
                }
            });
        }
        if (this.dropoffInput && this.dropoffInput.type !== 'hidden') {
            this.dropoffInput.addEventListener('input', () => {
                // Editing the address means any previous pin may no longer match it.
                if (this.dropoffPinCoords) this.clearStopPinpoint('dropoff');
                this.clearRouteEstimate();
                this.clearError();
                debouncedCalc();
            });
            this.dropoffInput.addEventListener('blur', () => {
                if (!this.dropoffPinCoords) {
                    this.triggerRouteCalculation(true);
                }
            });
        }
        if (this.weightInput) {
            this.weightInput.addEventListener('change', () => this.triggerRouteCalculation());
            this.weightInput.addEventListener('input', debouncedCalc);
        }
    }

    async triggerRouteCalculation(showGeoErrors = false) {
        const pickupText = this.pickupInput ? this.pickupInput.value.trim() : '';
        const dropoffText = this.dropoffInput ? this.dropoffInput.value.trim() : '';
        const weight = this.weightInput ? parseFloat(this.weightInput.value) || 1.0 : 1.0;
        if (this.weightInput && (!Number.isFinite(parseFloat(this.weightInput.value)) || parseFloat(this.weightInput.value) <= 0)) return;

        // Pinpoint coordinates take priority over typed address text
        const pickupOrigin = this.pickupPinCoords || pickupText;
        const dropoffDestination = this.dropoffPinCoords || dropoffText;

        if (!pickupOrigin || !dropoffDestination) {
            return;
        }

        // Try client-side geocoding only for stops that do NOT have pinpoint coordinates
        if (window.google && window.google.maps) {
            if (!this.pickupPinCoords && pickupText.length >= 3) {
                try {
                    await this.geocodeStopIfNeeded('pickup');
                } catch (err) {
                    // Fall back to server-side calculation
                }
            }
            if (!this.dropoffPinCoords && dropoffText.length >= 3) {
                try {
                    await this.geocodeStopIfNeeded('dropoff');
                } catch (err) {
                    // Fall back to server-side calculation
                }
            }
        }

        if (this.pickupPinCoords && this.dropoffPinCoords) {
            if (Math.abs(this.pickupPinCoords.lat - this.dropoffPinCoords.lat) < 0.000001 &&
                Math.abs(this.pickupPinCoords.lng - this.dropoffPinCoords.lng) < 0.000001) {
                this.showError('Pickup and drop-off pinpoint locations cannot be identical.');
                return;
            }
        } else if (pickupText && dropoffText && pickupText.toLowerCase() === dropoffText.toLowerCase()) {
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
            origin: this.pickupPinCoords || this.pickupCoords || pickupText,
            destination: this.dropoffPinCoords || this.dropoffCoords || dropoffText,
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
            // Pinpoint coordinates have priority! Only overwrite hidden fields if not pinpointed
            if (!this.pickupPinCoords) {
                this.setHiddenField('pickup_lat', data.start_location.lat);
                this.setHiddenField('pickup_lng', data.start_location.lng);
                this.setHiddenField('hidden_pickup_lat', data.start_location.lat);
                this.setHiddenField('hidden_pickup_lng', data.start_location.lng);
                this.updateMarker('pickup', data.start_location);
            } else {
                this.updateMarker('pickup', this.pickupPinCoords);
            }
        }
        if (data.end_location) {
            if (!this.dropoffPinCoords) {
                this.setHiddenField('dropoff_lat', data.end_location.lat);
                this.setHiddenField('dropoff_lng', data.end_location.lng);
                this.setHiddenField('hidden_dropoff_lat', data.end_location.lat);
                this.setHiddenField('hidden_dropoff_lng', data.end_location.lng);
                this.updateMarker('dropoff', data.end_location);
            } else {
                this.updateMarker('dropoff', this.dropoffPinCoords);
            }
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

    /**
     * Updates or creates a marker on the map.
     * Priority rule: If a stop has pinpoint coordinates, the marker MUST display at the pinpoint coordinates.
     * @param {'pickup'|'dropoff'} stop
     * @param {{lat: number, lng: number}} coords
     */
    updateMarker(stop, coords) {
        if (!this.map || !window.google || !google.maps) return;
        const isPickup = stop === 'pickup';
        const markerKey = isPickup ? 'pickupMarker' : 'dropoffMarker';

        // Pinpoint coordinates have strict priority in displaying the icons on the map
        const pinCoords = isPickup ? this.pickupPinCoords : this.dropoffPinCoords;
        const targetCoords = pinCoords || coords;
        if (!targetCoords) return;

        if (this[markerKey]) {
            this[markerKey].setPosition(targetCoords);
            return;
        }

        this[markerKey] = new google.maps.Marker({
            map: this.map,
            position: targetCoords,
            label: isPickup ? 'P' : 'D',
            title: isPickup ? 'Pickup location' : 'Drop-off location',
            draggable: true
        });

        this[markerKey].addListener('dragend', (event) => {
            const moved = { lat: event.latLng.lat(), lng: event.latLng.lng() };
            // Dragging marker is a pinpoint action: updates coordinate address, NOT typed address
            this.setStopPinpoint(stop, moved);
        });
    }

    drawRouteOnMap(data) {
        if (!this.map || !window.google) return;

        // Ensure markers are displayed at priority pinpoint coordinates if set
        const pickupLoc = this.pickupPinCoords || data.start_location;
        const dropoffLoc = this.dropoffPinCoords || data.end_location;

        if (pickupLoc) {
            this.updateMarker('pickup', pickupLoc);
        }
        if (dropoffLoc) {
            this.updateMarker('dropoff', dropoffLoc);
        }

        if (pickupLoc && dropoffLoc) {
            const bounds = new google.maps.LatLngBounds();
            const p1 = new google.maps.LatLng(pickupLoc.lat, pickupLoc.lng);
            const p2 = new google.maps.LatLng(dropoffLoc.lat, dropoffLoc.lng);

            bounds.extend(p1);
            bounds.extend(p2);

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
                path.forEach((pt) => bounds.extend(pt));
            }

            this.map.fitBounds(bounds, { top: 40, right: 40, bottom: 40, left: 40 });
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

    /**
     * Sets a stop's location from a pinpoint action (click on map or drag marker).
     * Populates the readonly coordinate address textbox and hidden fields.
     * Does NOT overwrite the separate typed Address textbox.
     * Pinpoint coordinates have PRIORITY in displaying icons on the map.
     * @param {'pickup'|'dropoff'} stop
     * @param {{lat: number, lng: number}} coords
     * @param {boolean} recalculate
     */
    setStopPinpoint(stop, coords, recalculate = true) {
        const isPickup = stop === 'pickup';
        if (isPickup) {
            this.pickupPinCoords = coords;
            this.pickupCoords = coords;
        } else {
            this.dropoffPinCoords = coords;
            this.dropoffCoords = coords;
        }

        const coordStr = `${coords.lat.toFixed(6)}, ${coords.lng.toFixed(6)}`;
        const coordsInput = isPickup ? this.pickupCoordsInput : this.dropoffCoordsInput;
        const coordAddressState = isPickup ? 'pickup' : 'dropoff';
        // Keep Vue's model in sync when a pin is set by this non-Vue map manager.
        if (window.orderFormState?.coordinateAddresses) {
            window.orderFormState.coordinateAddresses[coordAddressState] = coordStr;
        }
        if (coordsInput) {
            coordsInput.value = coordStr;
            coordsInput.dispatchEvent(new Event('input', { bubbles: true }));
            coordsInput.dispatchEvent(new Event('change', { bubbles: true }));
        }

        this.setHiddenField(`${stop}_lat`, coords.lat);
        this.setHiddenField(`${stop}_lng`, coords.lng);
        this.setHiddenField(`hidden_${stop}_lat`, coords.lat);
        this.setHiddenField(`hidden_${stop}_lng`, coords.lng);

        const distanceField = document.getElementById('distance_km');
        if (distanceField) distanceField.value = '';

        // PRIORITY: Display the icon on the map at the pinpoint coordinates
        this.updateMarker(stop, coords);

        // Resolve the pin to address components (street/barangay/city/province).
        // Keep unit/house number untouched because reverse geocoding cannot infer it reliably.
        this.reverseGeocodePin(stop, coords);

        this.clearRouteEstimate();
        this.clearError();
        if (recalculate) {
            this.triggerRouteCalculation();
        }
    }

    /**
     * Clears a stop's pinpoint coordinates.
     * @param {'pickup'|'dropoff'} stop
     */
    clearStopPinpoint(stop) {
        const isPickup = stop === 'pickup';
        if (isPickup) {
            this.pickupPinCoords = null;
            this.pickupCoords = null;
        } else {
            this.dropoffPinCoords = null;
            this.dropoffCoords = null;
        }

        const coordsInput = isPickup ? this.pickupCoordsInput : this.dropoffCoordsInput;
        const coordAddressState = isPickup ? 'pickup' : 'dropoff';
        if (window.orderFormState?.coordinateAddresses) {
            window.orderFormState.coordinateAddresses[coordAddressState] = '';
        }
        if (coordsInput) {
            coordsInput.value = '';
            coordsInput.dispatchEvent(new Event('input', { bubbles: true }));
            coordsInput.dispatchEvent(new Event('change', { bubbles: true }));
        }

        this.setHiddenField(`${stop}_lat`, '');
        this.setHiddenField(`${stop}_lng`, '');
        this.setHiddenField(`hidden_${stop}_lat`, '');
        this.setHiddenField(`hidden_${stop}_lng`, '');

        const markerKey = isPickup ? 'pickupMarker' : 'dropoffMarker';
        if (this[markerKey]) {
            this[markerKey].setMap(null);
            this[markerKey] = null;
        }

        this.clearRouteEstimate();
        this.clearError();
        this.triggerRouteCalculation();
    }

    /**
     * Backward-compatible alias for setStopPinpoint.
     * Updates pinpoint coordinates and only sets address if address input is currently empty.
     */
    setStopLocation(stop, coords, address) {
        this.setStopPinpoint(stop, coords, true);
        const input = stop === 'pickup' ? this.pickupInput : this.dropoffInput;
        if (input && address && !input.value.trim()) {
            input.value = address;
        }
    }

    clearStopLocation(stop) {
        this.clearStopPinpoint(stop);
    }

    restoreSavedLocations() {
        let hasPickup = false;
        let hasDropoff = false;

        for (const stop of ['pickup', 'dropoff']) {
            const latVal = document.getElementById(`${stop}_lat`)?.value;
            const lngVal = document.getElementById(`${stop}_lng`)?.value;
            const lat = parseFloat(latVal);
            const lng = parseFloat(lngVal);

            if (Number.isFinite(lat) && Number.isFinite(lng) && latVal !== '' && lngVal !== '') {
                const coords = { lat, lng };
                this.setStopPinpoint(stop, coords, false);
                if (stop === 'pickup') hasPickup = true;
                if (stop === 'dropoff') hasDropoff = true;
            }
        }

        if (hasPickup || hasDropoff) {
            if (hasPickup && hasDropoff) {
                const bounds = new google.maps.LatLngBounds();
                if (this.pickupPinCoords) bounds.extend(this.pickupPinCoords);
                if (this.dropoffPinCoords) bounds.extend(this.dropoffPinCoords);
                this.map?.fitBounds(bounds, { top: 40, right: 40, bottom: 40, left: 40 });
                this.triggerRouteCalculation();
            } else if (hasPickup && this.pickupPinCoords) {
                this.map?.setCenter(this.pickupPinCoords);
                this.map?.setZoom(15);
            } else if (hasDropoff && this.dropoffPinCoords) {
                this.map?.setCenter(this.dropoffPinCoords);
                this.map?.setZoom(15);
            }
        } else {
            const pickup = this.pickupInput ? this.pickupInput.value.trim() : '';
            const dropoff = this.dropoffInput ? this.dropoffInput.value.trim() : '';
            if (pickup && dropoff) {
                this.triggerRouteCalculation();
            }
        }
    }

    clearRouteEstimate() {
        const distanceField = document.getElementById('distance_km');
        if (distanceField) {
            distanceField.value = '';
            distanceField.dispatchEvent(new Event('input', { bubbles: true }));
        }
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
     * Reverse-geocode a map pin and populate the stop's structured address fields.
     * Unit/house number is intentionally not changed.
     * @param {'pickup'|'dropoff'} stop
     * @param {{lat: number, lng: number}} coords
     */
    reverseGeocodePin(stop, coords) {
        const requestId = (this._reverseGeocodeRequestIds ||= { pickup: 0, dropoff: 0 });
        const currentRequest = ++requestId[stop];
        const applyValues = (values) => {
            if (requestId[stop] !== currentRequest) return;
            Object.entries(values).forEach(([name, value]) => {
                const field = document.querySelector(`[name="${name}"]`);
                if (!field) return;
                // Pinning refreshes resolved address components; manual entries
                // are preserved if the lookup has no value for that component.
                if (!value && field.value.trim()) return;
                field.value = value || '';
                field.dispatchEvent(new Event('input', { bubbles: true }));
                field.dispatchEvent(new Event('change', { bubbles: true }));
            });
        };

        const useNominatim = async () => {
            const url = new URL('https://nominatim.openstreetmap.org/reverse');
            url.search = new URLSearchParams({
                format: 'jsonv2', lat: String(coords.lat), lon: String(coords.lng), addressdetails: '1', zoom: '18'
            });
            const response = await fetch(url, { headers: { Accept: 'application/json' } });
            if (!response.ok) throw new Error(`OpenStreetMap lookup failed (${response.status})`);
            const address = (await response.json()).address || {};
            const city = address.city || address.town || address.municipality || address.city_district || '';
            const values = {
                [`${stop}_street`]: [address.house_number, address.road || address.pedestrian || address.residential].filter(Boolean).join(' '),
                [`${stop}_barangay`]: address.suburb || address.quarter || address.neighbourhood || address.village || '',
                [`${stop}_city`]: city,
                [`${stop}_province`]: address.state || address.province || address.region || ''
            };
            if (!Object.values(values).some(Boolean)) throw new Error('No address details found for this pin');
            applyValues(values);
        };

        if (!window.google?.maps?.Geocoder) {
            useNominatim().catch((error) => console.warn('Reverse geocoding unavailable:', error));
            return;
        }
        if (!this._geocoder) this._geocoder = new google.maps.Geocoder();

        this._geocoder.geocode({ location: coords }, (results, status) => {
            if (requestId[stop] !== currentRequest) return;
            if (status !== 'OK' || !results?.length) {
                // Use OpenStreetMap when Google Geocoding is disabled or unavailable.
                useNominatim().catch((error) => console.warn('Reverse geocoding unavailable:', error));
                return;
            }
            const components = results[0].address_components || [];
            const get = (...types) => {
                const component = components.find((item) => types.some((type) => item.types.includes(type)));
                return component?.long_name || '';
            };
            applyValues({
                [`${stop}_street`]: [get('street_number'), get('route')].filter(Boolean).join(' '),
                [`${stop}_barangay`]: get('sublocality_level_1', 'sublocality', 'neighborhood'),
                [`${stop}_city`]: get('locality', 'administrative_area_level_2'),
                [`${stop}_province`]: get('administrative_area_level_1')
            });
        });
    }

    /**
     * Geocode a stop's typed address into coordinates if not already resolved by a pinpoint.
     * Sets coords, hidden lat/lng fields, and places a draggable marker on the map.
     * Pinpoint coordinates have PRIORITY: if pinpoint coordinates already exist, this does nothing.
     * @param {'pickup'|'dropoff'} stop
     */
    async geocodeStopIfNeeded(stop) {
        const isPickup = stop === 'pickup';
        const pinCoords = isPickup ? this.pickupPinCoords : this.dropoffPinCoords;
        if (pinCoords) return; // Priority: Pinpoint coordinates already exist

        const input = isPickup ? this.pickupInput : this.dropoffInput;
        const address = input ? input.value.trim() : '';

        if (address.length < 3) return;

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

        // Place marker for geocoded address. User dragging it converts it to an exact pinpoint.
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
                this.setStopPinpoint(stop, moved);
            });
        }
    }
}

// Global initialization helper
window.DeliveryMapManager = DeliveryMapManager;
