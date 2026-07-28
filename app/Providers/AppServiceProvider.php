<?php

namespace App\Providers;

use App\Support\Hashing\LegacyPasswordHasher;
use Illuminate\Hashing\BcryptHasher;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    // Whenever someone requests the legacy hash driver, create and use a LegacyPasswordHasher (which internally uses Laravel's BcryptHasher for bcrypt operations)
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Runs automatically when the Laravel application starts.
        // We use it to register our custom password hashing driver.

        // Register the custom "legacy" hash driver used by config/hashing.php.
        // It bridges the migrated SHA-256 passwords and modern bcrypt so the
        // existing user_account users can log in and are upgraded on the fly.
        Hash::extend('legacy', function ($app) {
            // Register a new hash driver named "legacy".
            // Later, when config/hashing.php contains:
            // 'driver' => 'legacy'
            // Laravel will execute this closure to create the hasher.
            //
            // $app is Laravel's Service Container, which provides access
            // to configuration, database, cache, and other services.

            return new LegacyPasswordHasher(
                // Create an instance of our custom hasher.

                new BcryptHasher([
                    // LegacyPasswordHasher delegates bcrypt operations
                    // (creating and verifying bcrypt hashes) to Laravel's
                    // built-in BcryptHasher.

                    'rounds' => $app['config']->get('hashing.bcrypt.rounds', 12),
                    // Read the bcrypt cost (work factor) from:
                    // config/hashing.php
                    //
                    // Example:
                    // 'rounds' => 12
                    //
                    // If no value exists, use 12 as the default.

                    'verify' => $app['config']->get('hashing.bcrypt.verify', true),
                    // Read the bcrypt verification setting from:
                    // config/hashing.php
                    //
                    // Example:
                    // 'verify' => true
                    //
                    // If missing, default to true.
                ]),
            );
        });
        // Return the fully configured LegacyPasswordHasher instance.
        // Laravel will use this whenever Hash::check(), Hash::make(),
        // Auth::attempt(), etc., require the "legacy" driver.
    }
}
