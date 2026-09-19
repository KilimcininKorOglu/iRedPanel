<?php

declare(strict_types=1);

namespace App\Utils;

use App\Exceptions\SecretBoxException;
use App\Models\Settings;

/**
 * Encrypts a secret that the panel must read back, such as the bind password
 * of an account resource. The key is derived from IREDPANEL_SECRET_KEY, so a
 * changed secret key makes the stored values unreadable.
 */
final class SecretBox
{
    private const PREFIX = 'v1:';
    private const CONTEXT = 'iredpanel-secretbox-v1:';

    private function __construct(private readonly string $key)
    {
    }

    public static function fromSettings(): self
    {
        return self::fromSecret(Settings::getInstance()->secretKey);
    }

    public static function fromSecret(string $secret): self
    {
        if (!extension_loaded('sodium')) {
            throw new \RuntimeException('ext-sodium is required to store encrypted secrets');
        }
        if ($secret === '') {
            throw new \InvalidArgumentException('The secret key must not be empty');
        }

        return new self(sodium_crypto_generichash(self::CONTEXT . $secret, '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES));
    }

    public function encrypt(string $plaintext): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        return self::PREFIX . base64_encode($nonce . sodium_crypto_secretbox($plaintext, $nonce, $this->key));
    }

    /**
     * @throws SecretBoxException when the value is malformed or was encrypted with another key
     */
    public function decrypt(string $stored): string
    {
        if (!str_starts_with($stored, self::PREFIX)) {
            throw new SecretBoxException('Unknown secret format');
        }
        $raw = base64_decode(substr($stored, strlen(self::PREFIX)), true);
        $minLength = SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + SODIUM_CRYPTO_SECRETBOX_MACBYTES;
        if ($raw === false || strlen($raw) < $minLength) {
            throw new SecretBoxException('Malformed secret');
        }
        $plaintext = sodium_crypto_secretbox_open(
            substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
            substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
            $this->key,
        );
        if ($plaintext === false) {
            throw new SecretBoxException('The secret cannot be decrypted with the current IREDPANEL_SECRET_KEY');
        }

        return $plaintext;
    }
}
