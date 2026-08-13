# Notifications: live bell and browser push

Two layers sit on top of the existing `notifications` table:

* **Live bell** — the header bell polls `GET /notifications/feed` (30s while the
  tab is in front, 120s when it is in the background) and refreshes the badge
  and the dropdown list. Nothing to configure.
* **Browser push** — the same notification is delivered through the browser's
  push service, so it arrives with the tab closed. Each user switches it on per
  browser from the toggle at the bottom of the bell dropdown.

## Moving parts

| Piece | Where |
| --- | --- |
| Subscriptions table | `PUSH_SUBSCRIPTIONS` (migration `2026_01_01_001300`) |
| Sending | `App\Services\WebPushService`, queued via `App\Jobs\SendPushNotification` |
| Hook | `NotificationService::notify()` — every existing notification is pushed |
| Endpoints | `POST /push/subscribe`, `POST /push/unsubscribe`, `POST /push/test` |
| Service worker | `public/sw.js` (must stay at the site root for scope) |
| Front end | `resources/js/notifications.js` |

Push requires HTTPS (or `localhost`) — the browser refuses to register a
service worker otherwise.

## Keys

Generated once and stored in `.env`:

```bash
php artisan push:vapid
```

Copy the three lines it prints into `.env`, then `php artisan config:clear`.
Replacing the keys invalidates every existing subscription — users have to turn
the toggle on again.

Set `WEBPUSH_ENABLED=false` to switch push off; the bell keeps working.

## Windows / WAMP: OpenSSL configuration

PHP on Windows ships `openssl.cnf` but sets no `OPENSSL_CONF`, and without it
OpenSSL cannot generate the ephemeral EC key each push message is encrypted
with. `php artisan push:vapid` works around this for key generation
(`WEBPUSH_OPENSSL_CONF` in `.env`); everything else needs the file where each
OpenSSL build looks by default — and the CLI and Apache builds disagree:

| Build | `OPENSSLDIR` | File it needs |
| --- | --- | --- |
| PHP CLI | `C:\Program Files\Common Files\SSL` | `…\SSL\openssl.cnf` |
| Apache (`mod_php`) | `C:\Apache24\conf\` | `C:\Apache24\conf\openssl.cnf` |

Both were populated on this server (elevated, once):

```powershell
New-Item -ItemType Directory -Force "C:\Program Files\Common Files\SSL" | Out-Null; Copy-Item "C:\wamp64\bin\php\php8.2.29\extras\ssl\openssl.cnf" "C:\Program Files\Common Files\SSL\openssl.cnf"
New-Item -ItemType Directory -Force "C:\Apache24\conf" | Out-Null; Copy-Item "C:\wamp64\bin\apache\apache2.4.65\conf\openssl.cnf" "C:\Apache24\conf\openssl.cnf"
```

Check what a given SAPI expects with
`openssl_get_cert_locations()['default_default_cert_area']`. Without the file,
push delivery fails quietly (a warning lands in `storage/logs`) and only the
live bell works.

## Checking it

1. Sign in, open the bell, switch **Browser notifications** on and accept the
   browser prompt.
2. `POST /push/test` (or from the console:
   `fetch('/push/test', {method:'POST', headers:{'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content}})`)
   should raise a system notification.
3. `SELECT COUNT(*) FROM PUSH_SUBSCRIPTIONS` confirms the registration was stored.

## Queue

`QUEUE_CONNECTION=sync` is fine: the push job is dispatched *after* the response
is sent, so it never slows the request. On a busy installation switch the queue
to `database` and run a worker — the job then leaves the web process entirely.
