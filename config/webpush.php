<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Browser push (Web Push / VAPID)
    |--------------------------------------------------------------------------
    |
    | Generate a key pair once with `php artisan push:vapid` and copy the two
    | values into .env. Push stays switched off until both are present, so the
    | application runs unchanged on installations that do not want it.
    |
    */

    'enabled' => env('WEBPUSH_ENABLED', true),

    'vapid' => [
        // Identifies the sender to the push service — a mailto: or https: URL.
        'subject'     => env('VAPID_SUBJECT', env('APP_URL', 'http://localhost')),
        'public_key'  => env('VAPID_PUBLIC_KEY'),
        'private_key' => env('VAPID_PRIVATE_KEY'),
    ],

    // Time to live handed to the push service, in seconds.
    'ttl' => (int) env('WEBPUSH_TTL', 86400),

    // A subscription is deleted after this many consecutive delivery failures.
    'max_failures' => (int) env('WEBPUSH_MAX_FAILURES', 3),

    /*
    | Windows PHP builds ship openssl.cnf under the PHP directory but set no
    | OPENSSL_CONF, which makes EC key generation fail. `push:vapid` reads this
    | path; the web server needs OPENSSL_CONF itself (see docs/push-setup.md).
    */
    'openssl_conf' => env('WEBPUSH_OPENSSL_CONF', dirname(PHP_BINARY).DIRECTORY_SEPARATOR
        .'extras'.DIRECTORY_SEPARATOR.'ssl'.DIRECTORY_SEPARATOR.'openssl.cnf'),

];
