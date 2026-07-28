<?php

namespace Tests\Unit;

use App\Support\Hashing\LegacyPasswordHasher;
use Illuminate\Hashing\BcryptHasher;
use PHPUnit\Framework\TestCase;

class LegacyPasswordHasherTest extends TestCase
{
    private LegacyPasswordHasher $hasher;

    protected function setUp(): void
    {
        parent::setUp();

        // Low cost keeps the test fast; the algorithm is what matters here.
        $this->hasher = new LegacyPasswordHasher(new BcryptHasher(['rounds' => 4]));
    }

    public function test_it_verifies_a_legacy_sha256_password(): void
    {
        $stored = hash('sha256', 'secret123'); // how a migrated password looks

        $this->assertTrue($this->hasher->check('secret123', $stored));
        $this->assertFalse($this->hasher->check('wrong-password', $stored));
    }

    public function test_legacy_hashes_are_flagged_for_rehash(): void
    {
        $stored = hash('sha256', 'secret123');

        $this->assertTrue($this->hasher->needsRehash($stored));
    }

    public function test_make_produces_a_bcrypt_hash(): void
    {
        $hash = $this->hasher->make('secret123');

        $this->assertSame('bcrypt', password_get_info($hash)['algoName']);
        $this->assertTrue($this->hasher->check('secret123', $hash));
    }

    public function test_bcrypt_hashes_do_not_need_rehash_at_the_same_cost(): void
    {
        $hash = $this->hasher->make('secret123');

        $this->assertFalse($this->hasher->needsRehash($hash));
    }

    public function test_empty_or_garbage_hashes_fail_safely(): void
    {
        $this->assertFalse($this->hasher->check('anything', ''));
        $this->assertFalse($this->hasher->check('anything', 'not-a-real-hash'));
    }
}
