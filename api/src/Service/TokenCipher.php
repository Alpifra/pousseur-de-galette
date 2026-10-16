<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Symmetric encryption of the Spotify tokens at rest: a database dump must not
 * be enough to take control of a host's Spotify account.
 */
class TokenCipher
{
    private string $key;

    public function __construct(
        #[Autowire('%env(APP_CRYPTO_KEY)%')]
        string $key,
    ) {
        $binary = sodium_hex2bin($key);
        if (\SODIUM_CRYPTO_SECRETBOX_KEYBYTES !== \strlen($binary)) {
            throw new \InvalidArgumentException('APP_CRYPTO_KEY must be 64 hex characters, see api/.env.');
        }

        $this->key = $binary;
    }

    public function encrypt(string $plaintext): string
    {
        $nonce = random_bytes(\SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        return sodium_bin2base64($nonce.sodium_crypto_secretbox($plaintext, $nonce, $this->key), \SODIUM_BASE64_VARIANT_ORIGINAL);
    }

    public function decrypt(string $ciphertext): string
    {
        $raw = sodium_base642bin($ciphertext, \SODIUM_BASE64_VARIANT_ORIGINAL);
        $nonce = substr($raw, 0, \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $payload = substr($raw, \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        $plaintext = sodium_crypto_secretbox_open($payload, $nonce, $this->key);
        if (false === $plaintext) {
            throw new \RuntimeException('Unable to decrypt the stored token: APP_CRYPTO_KEY probably changed.');
        }

        return $plaintext;
    }
}
