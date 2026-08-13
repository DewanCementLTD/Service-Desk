<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use RuntimeException;

/**
 * Generates the VAPID key pair browser push is signed with. Run once per
 * installation; changing the keys unsubscribes every browser.
 *
 * The pair is built straight from OpenSSL rather than through the web-push
 * library so an explicit openssl.cnf can be passed — Windows/WAMP builds
 * usually have no OPENSSL_CONF set and key generation fails without it.
 */
class GenerateVapidKeys extends Command
{
    protected $signature = 'push:vapid';

    protected $description = 'Generate a VAPID key pair for browser push notifications';

    public function handle(): int
    {
        if (filled(config('webpush.vapid.public_key')) && ! $this->confirm(
            'VAPID keys are already configured. Generating new ones will unsubscribe every browser. Continue?'
        )) {
            return self::SUCCESS;
        }

        try {
            [$public, $private] = $this->createKeyPair();
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Add these to your .env file:');
        $this->newLine();
        $this->line('VAPID_SUBJECT="'.config('app.url').'"');
        $this->line('VAPID_PUBLIC_KEY="'.$public.'"');
        $this->line('VAPID_PRIVATE_KEY="'.$private.'"');
        $this->newLine();
        $this->comment('Then run: php artisan config:clear');

        return self::SUCCESS;
    }

    /** @return array{0: string, 1: string} base64url public point and private scalar */
    private function createKeyPair(): array
    {
        $args = ['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC];
        $conf = config('webpush.openssl_conf');

        if (filled($conf) && is_file($conf)) {
            $args['config'] = $conf;
        }

        $key = openssl_pkey_new($args);

        if (! $key) {
            throw new RuntimeException(
                'OpenSSL could not create an EC key. Point WEBPUSH_OPENSSL_CONF at a valid openssl.cnf.'
            );
        }

        $details = openssl_pkey_get_details($key);

        if (! $details || ! isset($details['ec'])) {
            throw new RuntimeException('OpenSSL returned no EC key details.');
        }

        $pad = fn (string $value) => str_pad($value, 32, "\0", STR_PAD_LEFT);

        return [
            $this->base64url("\x04".$pad($details['ec']['x']).$pad($details['ec']['y'])),
            $this->base64url($pad($details['ec']['d'])),
        ];
    }

    private function base64url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
