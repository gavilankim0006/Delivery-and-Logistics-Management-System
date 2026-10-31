class ServiceAreaEditor {
    constructor(config) {
        this.map = new google.maps.Map(document.getElementById(config.mapId), {
            center: { lat: 14.5547, lng: 121.0244 }, zoom: 11, mapTypeControl: false, streetViewControl: false
        });
        this.path = [];
        this.polyline = new google.maps.Polyline({ map: this.map, strokeColor: '#0f766e', strokeWeight: 3, strokeOpacity: .9, clickable: false });
        this.polygon = null;
        this.form = document.getElementById('service-area-form');
        this.form.dataset.vertices = '[]';
        this.map.addListener('click', (event) => {
            this.path.push({ lat: event.latLng.lat(), lng: event.latLng.lng() });
            this.render();
        });
        document.getElementById('zone-undo').addEventListener('click', () => { this.path.pop(); this.render(); });
        document.getElementById('zone-clear').addEventListener('click', () => { this.load([]); });
        const cancelEditBtn = document.getElementById('zone-cancel-edit');
        if (cancelEditBtn) {
            cancelEditBtn.addEventListener('click', () => {
                document.getElementById('area-id').value = '';
                document.getElementById('area-name').value = '';
                this.load([]);
                cancelEditBtn.classList.add('d-none');
                const title = document.getElementById('zone-form-title');
                if (title) title.textContent = 'Create or edit a zone';
                const countEl = document.getElementById('zone-point-count');
                if (countEl) {
                    countEl.classList.remove('text-danger');
                    countEl.classList.add('text-muted');
                    countEl.textContent = 'Add at least 3 points by clicking the map.';
                }
            });
        }
        document.querySelectorAll('.zone-edit').forEach((button) => button.addEventListener('click', () => this.edit(JSON.parse(button.dataset.zone))));
        this.form.addEventListener('submit', (event) => {
            if (this.path.length < 3) {
                event.preventDefault();
                const countEl = document.getElementById('zone-point-count');
                if (countEl) {
                    countEl.textContent = 'Add at least 3 map points before saving.';
                    countEl.classList.add('text-danger');
                    countEl.classList.remove('text-muted');
                }
                const btn = this.form.querySelector('button[type="submit"]');
                if (btn) {
                    btn.disabled = false;
                    btn.removeAttribute('aria-busy');
                    if (btn.dataset.originalLabel) {
                        btn.textContent = btn.dataset.originalLabel;
                    }
                }
                return;
            }
            document.getElementById('area-polygon').value = JSON.stringify(this.path);
        });
    }
    render() {
        this.polyline.setPath(this.path);
        if (this.polygon) this.polygon.setMap(null);
        this.polygon = this.path.length >= 3 ? new google.maps.Polygon({
            map: this.map,
            paths: this.path,
            fillColor: '#0f766e',
            fillOpacity: .18,
            strokeColor: '#0f766e',
            strokeWeight: 2,
            clickable: false
        }) : null;
        const countEl = document.getElementById('zone-point-count');
        if (countEl) {
            countEl.classList.remove('text-danger');
            countEl.classList.add('text-muted');
            countEl.textContent = `${this.path.length} point${this.path.length === 1 ? '' : 's'} selected.`;
        }
        document.getElementById('area-polygon').value = this.path.length >= 3 ? JSON.stringify(this.path) : '';
    }
    load(path) { this.path = path; this.render(); }
    edit(zone) {
        document.getElementById('area-id').value = zone.id;
        document.getElementById('area-name').value = zone.name;
        document.querySelector('[name="is_active"]').checked = Number(zone.is_active) === 1;
        const polygon = JSON.parse(zone.polygon_json);
        this.load(polygon);
        ['base_delivery_fee', 'free_distance_km', 'per_km_rate', 'free_weight_kg', 'per_kg_rate'].forEach((key) => { document.getElementById(key).value = zone[key]; });
        this.map.panTo(polygon[0]);
        const cancelEditBtn = document.getElementById('zone-cancel-edit');
        if (cancelEditBtn) cancelEditBtn.classList.remove('d-none');
        const title = document.getElementById('zone-form-title');
        if (title) title.textContent = `Edit zone #${zone.id}: ${zone.name}`;
        document.getElementById('area-name').focus();
    }
}
window.ServiceAreaEditor = ServiceAreaEditor;
window.dispatchEvent(new Event('service-area-editor-ready'));
