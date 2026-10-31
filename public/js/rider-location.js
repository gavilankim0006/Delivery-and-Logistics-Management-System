(() => {
    const panel = document.querySelector('[data-rider-location]');
    if (!panel) return;

    const button = panel.querySelector('[data-location-toggle]');
    const status = panel.querySelector('[data-location-status]');
    const endpoint = panel.dataset.locationUrl;
    const csrfToken = panel.dataset.csrfToken;
    const intervalMs = 30000;
    let watchId = null;
    let timer = null;
    let latest = null;
    let sending = false;
    let sharingRequested = false;

    const send = async (location) => {
        if (sending) return;
        sending = true;
        try {
            const response = await fetch(endpoint, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    latitude: location.coords.latitude,
                    longitude: location.coords.longitude,
                    accuracy_m: location.coords.accuracy,
                }),
            });
            if (!response.ok) throw new Error('Location update rejected');
            status.textContent = `Sharing on · last sent ${new Date().toLocaleTimeString()}`;
        } catch (_) {
            status.textContent = 'Location could not be shared. Check your connection and try again.';
        } finally {
            sending = false;
        }
    };

    const stop = async () => {
        sharingRequested = false;
        if (watchId !== null) navigator.geolocation.clearWatch(watchId);
        watchId = null;
        if (timer !== null) window.clearInterval(timer);
        timer = null;
        button.disabled = true;
        let confirmed = false;
        try {
            const response = await fetch(endpoint, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ action: 'stop' }),
            });
            confirmed = response.ok;
        } catch (_) {
            confirmed = false;
        }
        latest = null;
        button.disabled = false;
        button.textContent = 'Start sharing location';
        button.setAttribute('aria-pressed', 'false');
        status.textContent = confirmed
            ? 'Location sharing is off. Your location is not tracked in the background.'
            : 'Could not confirm the stop request. Close this page or try again; sharing ends automatically when the assignment ends.';
    };

    button.addEventListener('click', () => {
        if (watchId !== null) {
            stop();
            return;
        }
        if (!navigator.geolocation) {
            status.textContent = 'This browser does not support location sharing.';
            return;
        }
        sharingRequested = true;
        status.textContent = 'Requesting permission…';
        watchId = navigator.geolocation.watchPosition(
            (location) => {
                latest = location;
                if (!timer) send(latest);
            },
            () => {
                status.textContent = 'Location permission is unavailable. Allow it in your browser settings to share.';
                stop();
            },
            { enableHighAccuracy: true, maximumAge: 15000, timeout: 20000 },
        );
        timer = window.setInterval(() => {
            if (latest) send(latest);
        }, intervalMs);
        button.textContent = 'Stop sharing location';
        button.setAttribute('aria-pressed', 'true');
        status.textContent = 'Sharing periodically while this delivery page is open.';
    });

    document.addEventListener('visibilitychange', () => {
        if (document.hidden && sharingRequested) stop();
    });
    window.addEventListener('pagehide', () => {
        if (sharingRequested && navigator.sendBeacon) {
            const payload = new URLSearchParams({
                csrf_token: csrfToken,
                action: 'stop',
            });
            navigator.sendBeacon(endpoint, payload);
        }
    });
})();
