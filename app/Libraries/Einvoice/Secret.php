<?php

namespace App\Libraries\Einvoice;

/**
 * Reversible encryption for the one secret this app needs the raw value of
 * later (the MyInvois client_secret) - everything else in the app (API
 * tokens, passwords) is a one-way hash. Wraps CI4's encrypter (keyed by
 * `encryption.key` in .env) so callers never touch it directly.
 */
class Secret
{
    public static function encrypt(string $plain): string
    {
        return base64_encode(service('encrypter')->encrypt($plain));
    }

    public static function decrypt(string $encoded): string
    {
        return service('encrypter')->decrypt(base64_decode($encoded));
    }
}
