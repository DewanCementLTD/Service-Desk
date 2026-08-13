/* Service Desk push worker.
 *
 * Served from the site root so its scope covers the whole application. It only
 * handles push delivery and notification clicks — nothing is cached, so a
 * deploy never serves stale pages. */

self.addEventListener('install', () => self.skipWaiting());

self.addEventListener('activate', (event) => event.waitUntil(self.clients.claim()));

self.addEventListener('push', (event) => {
    let payload = {};

    try {
        payload = event.data ? event.data.json() : {};
    } catch (e) {
        payload = { title: 'Service Desk', body: event.data ? event.data.text() : '' };
    }

    const title = payload.title || 'Service Desk';

    event.waitUntil(
        self.registration.showNotification(title, {
            body: payload.body || '',
            tag: payload.tag || 'service-desk',
            icon: '/icon-192.png',
            badge: '/icon-192.png',
            timestamp: Date.now(),
            requireInteraction: payload.type === 'URGENT',
            data: { url: payload.url || '/notifications' },
        })
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const target = new URL(event.notification.data?.url || '/notifications', self.location.origin).href;

    // Reuse an open tab when there is one, otherwise open a new window.
    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clients) => {
            for (const client of clients) {
                if (client.url === target && 'focus' in client) {
                    return client.focus();
                }
            }

            for (const client of clients) {
                if ('navigate' in client && 'focus' in client) {
                    return client.navigate(target).then((c) => c && c.focus());
                }
            }

            return self.clients.openWindow(target);
        })
    );
});
