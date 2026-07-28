<?php

namespace Tests\Feature\Auth;

use App\Livewire\Auth\Login;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

class LoginTest extends TestCase
{
    /**
     * Build minimal `user_account` + `login_log` tables in the (sqlite) test
     * database so we can exercise the real login flow without production data.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('user_account');
        Schema::create('user_account', function (Blueprint $table) {
            $table->string('user_id', 10)->primary();
            $table->string('username', 20);
            $table->string('password', 64);
            $table->char('role_flag', 1)->nullable();
            $table->text('menu_ids')->nullable();
            $table->text('sys_ip')->nullable();
            $table->integer('user_status');
            $table->integer('first_login')->default(0);
            $table->date('last_pwd_change')->nullable();
        });

        Schema::dropIfExists('login_log');
        Schema::create('login_log', function (Blueprint $table) {
            $table->bigIncrements('auto_id');
            $table->string('user_id', 15)->nullable();
            $table->dateTime('login_datetime')->nullable();
            $table->text('sys_ip')->nullable();
            $table->text('sys_os')->nullable();
        });
    }

    private function seedUser(array $overrides = []): void
    {
        DB::table('user_account')->insert(array_merge([
            'user_id' => 'tester',
            'username' => 'TESTER',
            'password' => hash('sha256', 'secret123'), // a legacy-style hash
            'role_flag' => 'S',
            'user_status' => 1,
            'first_login' => 1,
        ], $overrides));
    }

    /** Seed a known CAPTCHA code into the session and return it. */
    private function seedCaptcha(string $code = 'AB234'): string
    {
        session()->put('captcha', [
            'code' => $code,
            'expires_at' => now()->addMinutes(5)->timestamp,
        ]);

        return $code;
    }

    public function test_a_legacy_user_logs_in_and_is_upgraded_to_bcrypt(): void
    {
        $this->seedUser();
        $code = $this->seedCaptcha();

        Livewire::test(Login::class)
            ->set('userId', 'tester')
            ->set('password', 'secret123')
            ->set('captcha', $code)
            ->call('login')
            ->assertHasNoErrors()
            ->assertRedirect(route('dashboard'));

        $this->assertTrue(Auth::check());
        $this->assertSame('tester', Auth::id());

        // rehash-on-login: the stored SHA-256 hash is now bcrypt.
        $stored = DB::table('user_account')->where('user_id', 'tester')->value('password');
        $this->assertSame('bcrypt', password_get_info($stored)['algoName']);

        // login history recorded (event → listener).
        $this->assertDatabaseHas('login_log', ['user_id' => 'tester']);
    }

    public function test_wrong_password_is_rejected(): void
    {
        $this->seedUser();
        $code = $this->seedCaptcha();

        Livewire::test(Login::class)
            ->set('userId', 'tester')
            ->set('password', 'wrong-password')
            ->set('captcha', $code)
            ->call('login')
            ->assertHasErrors('userId');

        $this->assertFalse(Auth::check());
    }

    public function test_an_invalid_captcha_is_rejected(): void
    {
        $this->seedUser();
        $this->seedCaptcha('AB234');

        Livewire::test(Login::class)
            ->set('userId', 'tester')
            ->set('password', 'secret123')
            ->set('captcha', 'WRONG')
            ->call('login')
            ->assertHasErrors('captcha');

        $this->assertFalse(Auth::check());
    }

    public function test_disabled_account_cannot_log_in(): void
    {
        $this->seedUser(['user_status' => 0]);
        $code = $this->seedCaptcha();

        Livewire::test(Login::class)
            ->set('userId', 'tester')
            ->set('password', 'secret123')
            ->set('captcha', $code)
            ->call('login')
            ->assertHasErrors('userId');

        $this->assertFalse(Auth::check());
    }

    public function test_login_is_blocked_from_an_unauthorised_ip(): void
    {
        // sys_ip is set to an address different from the test client (127.0.0.1).
        $this->seedUser(['sys_ip' => '10.0.0.99']);
        $code = $this->seedCaptcha();

        Livewire::test(Login::class)
            ->set('userId', 'tester')
            ->set('password', 'secret123')
            ->set('captcha', $code)
            ->call('login')
            ->assertHasErrors('userId');

        $this->assertFalse(Auth::check());
    }

    public function test_too_many_attempts_are_rate_limited(): void
    {
        $this->seedUser();

        for ($i = 0; $i < 5; $i++) {
            $code = $this->seedCaptcha();
            Livewire::test(Login::class)
                ->set('userId', 'tester')
                ->set('password', 'wrong-password')
                ->set('captcha', $code)
                ->call('login')
                ->assertHasErrors('userId');
        }

        // The next attempt is blocked before credentials are even checked —
        // even though the password (and captcha) are now correct.
        $code = $this->seedCaptcha();
        Livewire::test(Login::class)
            ->set('userId', 'tester')
            ->set('password', 'secret123')
            ->set('captcha', $code)
            ->call('login')
            ->assertHasErrors('userId');

        $this->assertFalse(Auth::check());
    }
}
