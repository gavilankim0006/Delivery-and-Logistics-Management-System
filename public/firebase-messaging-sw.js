/**
 * Firebase / Web Push Service Worker for Dispatch
 * Handles background push notifications when user is not actively viewing the page.
 */

self.addEventListener('install', (event) => {
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(self.clients.claim());
});

self.addEventListener('push', (event) => {
    let payload = {
        title: 'Dispatch Delivery Update',
        body: 'You have a new delivery status update.',
        icon: '/SIAproject/public/favicon.ico',
        badge: '/SIAproject/public/favicon.ico',
        data: { url: '/SIAproject/orders' }
    };

    if (event.data) {
        try {
            const data = event.data.json();
            if (data.notification) {
                payload.title = data.notification.title || payload.title;
                payload.body = data.notification.body || payload.body;
            } else if (data.title || data.body) {
                payload.title = data.title || payload.title;
                payload.body = data.body || payload.body;
            }
            if (data.data) {
                payload.data = Object.assign(payload.data, data.data);
            }
        } catch (e) {
            payload.body = event.data.text();
        }
    }

    const options = {
        body: payload.body,
        icon: payload.icon,
        badge: payload.badge,
        tag: 'dispatch-delivery-status',
        renotify: true,
        data: payload.data
    };

    event.waitUntil(
        self.registration.showNotification(payload.title, options)
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const targetUrl = (event.notification.data && event.notification.data.url)
        ? event.notification.data.url
        : '/SIAproject/orders';

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
            for (let i = 0; i < clientList.length; i++) {
                const client = clientList[i];
                if (client.url.includes(targetUrl) && 'focus' in client) {
                    return client.focus();
                }
            }
            if (clients.openWindow) {
                return clients.openWindow(targetUrl);
            }
        })
    );
});
