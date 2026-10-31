(() => {
    const panel = document.querySelector('[data-customer-rider-location]');
    if (!panel) return;

    const status = panel.querySelector('[data-customer-location-status]');
    const endpoint = panel.dataset.locationUrl;
    const pollMs = 60000;
    let stopped = false;

    const refresh = async () => {
        if (stopped || document.hidden) return;
        try {
            const response = await fetch(endpoint, {
                method: 'GET',
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' },
                cache: 'no-store',
            });
            if (!response.ok) {
                status.textContent = 'No recent rider-shared location is available. Delivery progress remains available in the status history.';
                return;
            }
            const location = await response.json();
            if (!location.success || !Number.isFinite(Number(location.latitude)) || !Number.isFinite(Number(location.longitude))) {
                status.textContent = 'No recent rider-shared location is available. Delivery progress remains available in the status history.';
                return;
            }
            const receivedAt = new Date(location.received_at);
            const ageSeconds = Number(location.age_seconds);
            if (!Number.isFinite(ageSeconds) || ageSeconds < 0 || ageSeconds > 120) {
                status.textContent = 'The latest rider-shared location is stale. Check the delivery status history for updates.';
                return;
            }
            const latitude = Number(location.latitude).toFixed(5);
            const longitude = Number(location.longitude).toFixed(5);
            const link = document.createElement('a');
            link.href = `https://www.openstreetmap.org/?mlat=${encodeURIComponent(latitude)}&mlon=${encodeURIComponent(longitude)}#map=16/${encodeURIComponent(latitude)}/${encodeURIComponent(longitude)}`;
            link.target = '_blank';
            link.rel = 'noopener noreferrer';
            link.textContent = 'Open recent rider-shared location in OpenStreetMap';
            status.replaceChildren(link, document.createTextNode(` · Updated ${receivedAt.toLocaleTimeString()}${Number(location.accuracy_m) > 0 ? ` · accuracy ±${Math.round(Number(location.accuracy_m))} m` : ''}`));
        } catch (_) {
            status.textContent = 'Unable to load rider-shared location right now. Delivery progress remains available in the status history.';
        }
    };

    refresh();
    const timer = window.setInterval(refresh, pollMs);
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) return;
        refresh();
    });
    window.addEventListener('pagehide', () => {
        stopped = true;
        window.clearInterval(timer);
    }, { once: true });
})();
