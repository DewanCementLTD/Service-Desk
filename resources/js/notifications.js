/**
 * Header notification bell.
 *
 * Two halves of the same feature: the bell polls a small JSON feed so the badge
 * and the dropdown stay current while a tab is open, and — where the browser
 * supports it — a service worker subscription carries the same notifications
 * through when the tab is closed.
 */

const root = document.getElementById('notification-bell');
const configEl = document.getElementById('sd-notification-config');

if (root && configEl) {
    boot(JSON.parse(configEl.textContent));
}

function boot(config) {
    const button = document.getElementById('notification-button');
    const panel = document.getElementById('notification-panel');
    const badge = document.getElementById('notification-badge');
    const list = document.getElementById('notification-list');
    const pushRow = document.getElementById('notification-push');
    const pushToggle = document.getElementById('notification-push-toggle');
    const pushHint = document.getElementById('notification-push-hint');

    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    let lastSeenId = Number(sessionStorage.getItem('sd-last-notification') || 0);
    let timer = null;

    // ---------------------------------------------------------------- dropdown

    button.addEventListener('click', (event) => {
        event.stopPropagation();
        const open = panel.classList.toggle('hidden');
        button.setAttribute('aria-expanded', open ? 'false' : 'true');
        if (! open) {
            refresh();
        }
    });

    document.addEventListener('click', (event) => {
        if (! panel.classList.contains('hidden') && ! root.contains(event.target)) {
            close();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            close();
        }
    });

    function close() {
        panel.classList.add('hidden');
        button.setAttribute('aria-expanded', 'false');
    }

    // ------------------------------------------------------------------- feed

    function render(data) {
        const count = data.unread || 0;

        badge.textContent = count > 99 ? '99+' : count;
        badge.classList.toggle('hidden', count === 0);

        if (! data.notifications.length) {
            list.innerHTML = '<li class="px-3 py-6 text-center text-xs text-slate-400">Nothing yet.</li>';

            return;
        }

        list.innerHTML = data.notifications.map((item) => `
            <li>
                <a href="${item.url}" class="flex gap-2.5 px-3 py-2.5 transition hover:bg-slate-50 dark:hover:bg-slate-800">
                    <span class="mt-1.5 h-1.5 w-1.5 flex-none rounded-full ${item.unread ? 'bg-brand-600' : 'bg-transparent'}"></span>
                    <span class="min-w-0">
                        <span class="block truncate text-xs font-semibold ${item.unread ? 'text-slate-900 dark:text-white' : 'text-slate-600 dark:text-slate-400'}">${escapeHtml(item.title)}</span>
                        ${item.message ? `<span class="mt-0.5 block line-clamp-2 text-[11px] text-slate-500 dark:text-slate-400">${escapeHtml(item.message)}</span>` : ''}
                        <span class="mt-0.5 block text-[10px] uppercase tracking-wide text-slate-400">${escapeHtml(item.ago || '')}</span>
                    </span>
                </a>
            </li>
        `).join('');

        // A brand-new notification while the tab sits in the background is worth
        // a system notification even when push is not switched on.
        const newest = data.notifications[0];
        if (newest && newest.unread && newest.id > lastSeenId) {
            if (lastSeenId && document.hidden && window.Notification?.permission === 'granted') {
                new window.Notification(newest.title, { body: newest.message || '', tag: `sd-${newest.id}` });
            }
            lastSeenId = newest.id;
            sessionStorage.setItem('sd-last-notification', String(lastSeenId));
        }
    }

    async function refresh() {
        try {
            const response = await fetch(config.feedUrl, {
                headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
                credentials: 'same-origin',
            });

            if (! response.ok) {
                return;
            }

            render(await response.json());
        } catch (e) {
            // Offline or a dropped connection: the next tick tries again.
        }
    }

    function schedule() {
        clearInterval(timer);
        // Idle background tabs poll far less often than the one in front.
        timer = setInterval(refresh, document.hidden ? config.pollIdle : config.pollActive);
    }

    document.addEventListener('visibilitychange', () => {
        schedule();
        if (! document.hidden) {
            refresh();
        }
    });

    refresh();
    schedule();

    // ------------------------------------------------------------------- push

    const pushSupported = 'serviceWorker' in navigator && 'PushManager' in window && window.isSecureContext;

    if (! config.vapidKey || ! pushSupported) {
        pushRow?.classList.add('hidden');

        return;
    }

    let registration = null;

    navigator.serviceWorker.register(config.swUrl).then(async (reg) => {
        registration = reg;
        const subscription = await reg.pushManager.getSubscription();
        setPushState(!! subscription && window.Notification.permission === 'granted');

        // Keep the server copy fresh — a browser can rotate an endpoint.
        if (subscription && window.Notification.permission === 'granted') {
            post(config.subscribeUrl, subscription.toJSON());
        }
    }).catch(() => pushRow.classList.add('hidden'));

    pushToggle.addEventListener('change', async () => {
        pushToggle.disabled = true;

        try {
            if (pushToggle.checked) {
                await enablePush();
            } else {
                await disablePush();
            }
        } finally {
            pushToggle.disabled = false;
        }
    });

    async function enablePush() {
        const permission = await window.Notification.requestPermission();

        if (permission !== 'granted') {
            setPushState(false, permission === 'denied'
                ? 'Blocked in browser settings.'
                : 'Permission was not granted.');

            return;
        }

        const subscription = await registration.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: urlBase64ToUint8Array(config.vapidKey),
        });

        const ok = await post(config.subscribeUrl, subscription.toJSON());
        setPushState(ok, ok ? 'This browser will receive notifications.' : 'Could not register with the server.');
    }

    async function disablePush() {
        const subscription = await registration.pushManager.getSubscription();

        if (subscription) {
            await post(config.unsubscribeUrl, { endpoint: subscription.endpoint });
            await subscription.unsubscribe();
        }

        setPushState(false, 'Turned off on this browser.');
    }

    function setPushState(on, hint) {
        pushToggle.checked = on;
        if (hint) {
            pushHint.textContent = hint;
        }
    }

    async function post(url, payload) {
        try {
            const response = await fetch(url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'application/json',
                },
                body: JSON.stringify(payload),
            });

            return response.ok;
        } catch (e) {
            return false;
        }
    }
}

/** VAPID keys travel as base64url and the Push API wants raw bytes. */
function urlBase64ToUint8Array(base64) {
    const padded = (base64 + '='.repeat((4 - (base64.length % 4)) % 4))
        .replace(/-/g, '+')
        .replace(/_/g, '/');
    const raw = window.atob(padded);

    return Uint8Array.from(raw, (char) => char.charCodeAt(0));
}

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, (char) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    })[char]);
}
