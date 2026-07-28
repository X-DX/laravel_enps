<?php

namespace App\Support\Hashing;

use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Hashing\BcryptHasher;

/**
 * A password hasher that understands BOTH the legacy scheme and the modern one:
 *
 *  - Legacy production passwords are unsalted SHA-256 (64 hex chars).
 *  - New / changed passwords are bcrypt.
 *
 * It verifies whichever scheme a stored hash uses, always *creates* bcrypt, and
 * reports legacy hashes as needing a rehash. Combined with Laravel's built-in
 * "rehash on login", this transparently upgrades each legacy user to bcrypt the
 * first time they log in — no password resets required.
 */
class LegacyPasswordHasher implements Hasher
{
    public function __construct(
        private readonly BcryptHasher $bcrypt,
    ) {
    }

    public function info($hashedValue): array
    {
        return password_get_info($hashedValue);
    }

    /**
     * Always create modern bcrypt hashes.
     */
    public function make($value, array $options = []): string
    {
        return $this->bcrypt->make($value, $options);
    }

    /**
     * Verify a plaintext value against either a bcrypt or a legacy SHA-256 hash.
     */
    public function check($value, $hashedValue, array $options = []): bool
    {
        if (! is_string($hashedValue) || $hashedValue === '') {
            return false;
        }

        if ($this->isBcrypt($hashedValue)) {
            return $this->bcrypt->check($value, $hashedValue, $options);
        }

        if ($this->isLegacySha256($hashedValue)) {
            // Constant-time comparison of the unsalted legacy SHA-256 hash.
            return hash_equals(strtolower($hashedValue), hash('sha256', (string) $value));
        }

        return false;
    }

    /**
     * Bcrypt hashes only need a rehash if their work factor changed; any legacy
     * (or unrecognised) hash should be upgraded to bcrypt.
     */
    public function needsRehash($hashedValue, array $options = []): bool
    {
        if ($this->isBcrypt($hashedValue)) {
            return $this->bcrypt->needsRehash($hashedValue, $options);
        }

        return true;
    }

    private function isBcrypt(string $hash): bool
    {
        return (password_get_info($hash)['algoName'] ?? null) === 'bcrypt';
    }

    private function isLegacySha256(string $hash): bool
    {
        return strlen($hash) === 64 && ctype_xdigit($hash);
    }
}
