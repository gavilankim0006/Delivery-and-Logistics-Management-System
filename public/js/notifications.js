/**
 * Client-side Notification & Firebase Push Handler for Dispatch
 */
export default (function (window, document) {
    'use strict';

    function urlBase64ToUint8Array(base64String) {
        const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
        const base64 = (base64String + padding)
            .replace(/-/g, '+')
            .replace(/_/g, '/');
        const rawData = window.atob(base64);
        const outputArray = new Uint8Array(rawData.length);
        for (let i = 0; i < rawData.length; ++i) {
            outputArray[i] = rawData.charCodeAt(i);
        }
        return outputArray;
    }

    const NotificationManager = {
        vapidKey: window.DISPATCH_VAPID_KEY || 'BHrmPX2mONM_J_4rHvbd-uRR42bdopDxItXUg__g5i0nZBRkSHT8nUxp1WOe7fM57VpJ1wJZcVs-txXVDxgbG-I',
        saveTokenUrl: window.DISPATCH_SAVE_TOKEN_URL || '/SIAproject/auth/save_token',
        testPushUrl: window.DISPATCH_TEST_PUSH_URL || '/SIAproject/auth/test_push',
        csrfToken: window.DISPATCH_CSRF_TOKEN || '',

        isSupported: function () {
            return 'Notification' in window && 'serviceWorker' in navigator;
        },

        getPermissionState: function () {
            if (!this.isSupported()) return 'unsupported';
            return Notification.permission; // 'default', 'granted', 'denied'
        },

        registerServiceWorker: async function () {
            if (!('serviceWorker' in navigator)) return null;
            try {
                // Register service worker in public root
                const swPath = (window.DISPATCH_BASE_URL || '/SIAproject/').replace(/\/+$/, '') + '/firebase-messaging-sw.js';
                const registration = await navigator.serviceWorker.register(swPath, {
                    scope: (window.DISPATCH_BASE_URL || '/SIAproject/')
                });
                return registration;
            } catch (err) {
                console.warn('Service worker registration fallback:', err);
                return null;
            }
        },

        subscribePush: async function () {
            if (!this.isSupported()) {
                throw new Error('Push notifications are not supported in this browser.');
            }

            const permission = await Notification.requestPermission();
            if (permission !== 'granted') {
                throw new Error(permission === 'denied' ? 'Notification permission was denied.' : 'Notification permission was dismissed.');
            }

            const reg = await this.registerServiceWorker();
            if (!reg) {
                throw new Error('Could not register the push service worker. Check that the service worker URL and scope are available on this site.');
            }
            if (!reg.pushManager || !this.vapidKey) {
                throw new Error('Push notifications are not configured for this site.');
            }

            let sub;
            try {
                sub = await reg.pushManager.getSubscription();
                if (!sub) {
                    sub = await reg.pushManager.subscribe({
                        userVisibleOnly: true,
                        applicationServerKey: urlBase64ToUint8Array(this.vapidKey)
                    });
                }
            } catch (pushErr) {
                throw new Error(`Could not subscribe to push notifications: ${pushErr?.message || 'subscription failed'}`);
            }
            if (!sub?.endpoint) {
                throw new Error('The browser did not return a valid push subscription.');
            }

            // Persist the endpoint and encryption keys required by the Web Push protocol.
            const token = JSON.stringify(sub.toJSON());

            await this.saveToken(token);
            return token;
        },

        readJsonResponse: async function (response, action) {
            const text = await response.text();
            try {
                return JSON.parse(text);
            } catch {
                const detail = text.trim().startsWith('<')
                    ? 'The server returned an HTML page instead of JSON. Check the request URL and server logs.'
                    : 'The server returned an invalid response.';
                throw new Error(`Could not ${action}: ${detail} (HTTP ${response.status}).`);
            }
        },

        saveToken: async function (token) {
            const response = await fetch(this.saveTokenUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    subscription: JSON.parse(token),
                    csrf_token: this.csrfToken
                })
            });

            const result = await this.readJsonResponse(response, 'save notification token');
            if (!response.ok || !result.success) {
                throw new Error(result.error || 'Failed to save notification token.');
            }
            return result;
        },

        sendTestPush: async function () {
            const response = await fetch(this.testPushUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    csrf_token: this.csrfToken
                })
            });

            const result = await this.readJsonResponse(response, 'send test notification');
            if (!response.ok || !result.success) {
                throw new Error(result.error || result.message || 'Failed to trigger test push.');
            }

            // Also show immediate notification if permission is granted
            if (Notification.permission === 'granted') {
                try {
                    new Notification('Dispatch Notification Test', {
                        body: 'Push messaging is active and working properly!',
                        icon: '/SIAproject/public/favicon.ico'
                    });
                } catch (e) {
                    // Ignored if service worker display required
                }
            }

            return result;
        },

        initUI: function () {
            const pushCard = document.getElementById('push-notification-controls');
            if (!pushCard) return;

            const badge = document.getElementById('push-status-badge');
            const infoText = document.getElementById('push-status-info');
            const enableBtn = document.getElementById('enable-push-btn');
            const testBtn = document.getElementById('test-push-btn');
            const pushCheckbox = document.getElementById('push_opt_in');

            const updateDisplay = () => {
                const state = this.getPermissionState();
                if (state === 'unsupported') {
                    if (badge) {
                        badge.className = 'badge bg-secondary';
                        badge.textContent = 'Unsupported in this browser';
                    }
                    if (infoText) infoText.textContent = 'Web push notifications are not supported by this browser.';
                    if (enableBtn) enableBtn.disabled = true;
                    if (testBtn) testBtn.style.display = 'none';
                } else if (state === 'granted') {
                    if (badge) {
                        badge.className = 'badge bg-success';
                        badge.textContent = 'Push active on this device';
                    }
                    if (infoText) infoText.textContent = 'Browser notifications are enabled. You will receive live delivery status updates.';
                    if (enableBtn) enableBtn.style.display = 'none';
                    if (testBtn) testBtn.style.display = 'inline-block';
                    if (pushCheckbox && !pushCheckbox.checked) {
                        pushCheckbox.checked = true;
                    }
                } else if (state === 'denied') {
                    if (badge) {
                        badge.className = 'badge bg-danger';
                        badge.textContent = 'Notifications blocked';
                    }
                    if (infoText) infoText.textContent = 'Notifications are blocked in your browser settings. Please allow notifications in site permissions.';
                    if (enableBtn) {
                        enableBtn.disabled = true;
                        enableBtn.textContent = 'Blocked by browser';
                    }
                    if (testBtn) testBtn.style.display = 'none';
                } else {
                    // Default / Promptable
                    if (badge) {
                        badge.className = 'badge bg-warning text-dark';
                        badge.textContent = 'Permission needed';
                    }
                    if (infoText) infoText.textContent = 'Click Enable below to allow status alerts on this browser.';
                    if (enableBtn) {
                        enableBtn.style.display = 'inline-block';
                        enableBtn.disabled = false;
                        enableBtn.textContent = 'Enable browser notifications';
                    }
                    if (testBtn) testBtn.style.display = 'none';
                }
            };

            updateDisplay();

            if (enableBtn) {
                enableBtn.addEventListener('click', async (e) => {
                    e.preventDefault();
                    enableBtn.disabled = true;
                    enableBtn.textContent = 'Enabling...';
                    try {
                        await this.subscribePush();
                        updateDisplay();
                        alert('Browser push notifications have been enabled!');
                    } catch (err) {
                        alert(err.message || 'Could not enable push notifications.');
                        updateDisplay();
                    }
                });
            }

            if (testBtn) {
                testBtn.addEventListener('click', async (e) => {
                    e.preventDefault();
                    testBtn.disabled = true;
                    testBtn.textContent = 'Sending test...';
                    try {
                        const res = await this.sendTestPush();
                        alert(res.message || 'Test push notification sent successfully!');
                    } catch (err) {
                        alert(err.message || 'Failed to send test push.');
                    } finally {
                        testBtn.disabled = false;
                        testBtn.textContent = 'Send test notification';
                    }
                });
            }

            if (pushCheckbox) {
                pushCheckbox.addEventListener('change', async () => {
                    if (pushCheckbox.checked && Notification.permission !== 'granted') {
                        try {
                            await this.subscribePush();
                            updateDisplay();
                        } catch (err) {
                            console.warn('Opt-in push registration canceled:', err);
                        }
                    }
                });
            }
        }
    };

    window.DispatchNotifications = NotificationManager;
    return NotificationManager;
})(window, document);
