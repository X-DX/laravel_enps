<?php

use App\Http\Middleware\EnsurePasswordIsCurrent; // Custom middleware that checks whether the password is expired
use App\Livewire\Admin\ManageUserPermissions; // Admin: manage a user's permissions
use App\Livewire\Auth\ChangePassword; // Livewire change password page
use App\Livewire\Auth\Login; // Livewire login page
use App\Livewire\MasterData\Banks; // Master Data: Bank Master (3b)
use App\Livewire\MasterData\Ddos; // Master Data: DDO Master (3d)
use App\Livewire\MasterData\Designations; // Master Data: Designation Master (3b)
use App\Livewire\MasterData\Districts; // Master Data: District Master (3a)
use App\Livewire\MasterData\Locations; // Master Data: Location Master (3c)
use App\Livewire\MasterData\Treasuries; // Master Data: Treasury Master (3f)
use App\Livewire\Settings\ContributionShare; // Settings: Contribution Share (3e)
use App\Livewire\Settings\InterestRates; // Settings: Interest Rate (3e)
use App\Livewire\Settings\RetirementYear; // Settings: Retirement Year (3e)
use App\Support\Captcha\CaptchaService; // Generates CAPTCHA image
use Illuminate\Support\Facades\Auth; // Handles login/logout
use Illuminate\Support\Facades\Route; // Creates routes

use App\Livewire\Accounts\Subscribers; // Entry Section: View All Accounts (M4, 4a)


// Root URL
Route::view('/', 'landing')->name('home');

/* 
Guest Routes:
Everything inside this group can only be accessed by users who are NOT logged in.
*/
Route::middleware('guest')->group(function () {
    Route::get('/login', Login::class)->name('login'); // Login Route
    Route::get('/captcha', fn(CaptchaService $captcha) => $captcha->generateImage())->name('captcha'); // CAPTCHA Route
});

/* 
Auth Routes:
Everything inside this group can only be accessed by users who are logged in.
*/
Route::middleware('auth')->group(function () {
    // Reachable even while a password change is pending (avoids a redirect loop).
    Route::get('/password/change', ChangePassword::class)->name('password.change');

    Route::post('/logout', function () {
        Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()->route('login');
    })->name('logout');

    // Everything below requires a current (non-expired) password.
    Route::middleware(EnsurePasswordIsCurrent::class)->group(function () {
        Route::view('/dashboard', 'dashboard')->name('dashboard');

        // Master Data — District Master (M3, slice 3a).
        Route::get('/master/districts', Districts::class)
            ->middleware('can:adminsection.district_master')
            ->name('master.districts');

        // Master Data — Bank + Designation (M3, slice 3b).
        Route::get('/master/banks', Banks::class)
            ->middleware('can:adminsection.bank_entry')
            ->name('master.banks');

        Route::get('/master/designations', Designations::class)
            ->middleware('can:adminsection.designation_master')
            ->name('master.designations');

        // Master Data — Location Master (M3, slice 3c).
        Route::get('/master/locations', Locations::class)
            ->middleware('can:adminsection.location_master')
            ->name('master.locations');

        // Master Data — DDO Master (M3, slice 3d).
        Route::get('/master/ddos', Ddos::class)
            ->middleware('can:adminsection.ddo_entry')
            ->name('master.ddos');

        // Master Data — Treasury Master (M3, slice 3f — net-new, no legacy origin).
        Route::get('/master/treasuries', Treasuries::class)
            ->middleware('can:adminsection.treasury_master')
            ->name('master.treasuries');

        // Settings — the "Others" configuration screens (M3, slice 3e).
        Route::get('/settings/interest-rates', InterestRates::class)
            ->middleware('can:adminsection.change_interest_rate')
            ->name('settings.interest-rates');

        Route::get('/settings/retirement-year', RetirementYear::class)
            ->middleware('can:adminsection.change_retirement_year')
            ->name('settings.retirement-year');

        Route::get('/settings/contribution-share', ContributionShare::class)
            ->middleware('can:adminsection.change_share_rate')
            ->name('settings.contribution-share');

        // Admin: manage which permissions each user has.
        Route::get('/admin/permissions', ManageUserPermissions::class)
            ->middleware('can:adminsection.add_update_user')
            ->name('admin.permissions');

        // Entry Section — View All Accounts (M4, slice 4a).
        Route::get('/accounts', Subscribers::class)
            ->middleware('can:entrysection.view_all_accounts')
            ->name('accounts.index');
    });
});
