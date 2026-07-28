<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Hash Driver
    |--------------------------------------------------------------------------
    |
    | We default to our custom "legacy" driver, which understands both the
    | migrated unsalted SHA-256 passwords and modern bcrypt. This driver is
    | registered in App\Providers\AppServiceProvider via Hash::extend().
    |
    | Supported: "bcrypt", "argon", "argon2id", "legacy"
    |
    */

    'driver' => env('HASH_DRIVER', 'legacy'),

    /*
    |--------------------------------------------------------------------------
    | Bcrypt Options
    |--------------------------------------------------------------------------
    |
    | The work factor used whenever the legacy driver creates (or upgrades a
    | password to) bcrypt.
    |
    */

    'bcrypt' => [
        'rounds' => env('BCRYPT_ROUNDS', 12),
        'verify' => true,
        'limit' => null,
    ],

    'argon' => [
        'memory' => 65536,
        'threads' => 1,
        'time' => 4,
        'verify' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Rehash On Login
    |--------------------------------------------------------------------------
    |
    | When true, Laravel re-hashes a user's password on successful login if the
    | hasher reports it needs a rehash. This is what silently upgrades legacy
    | SHA-256 users to bcrypt.
    |
    */

    'rehash_on_login' => true,

];
