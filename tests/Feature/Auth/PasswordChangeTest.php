<?php

namespace Tests\Feature\Auth;

use App\Livewire\Auth\ChangePassword;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

class PasswordChangeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('user_account');
        Schema::create('user_account', function (Blueprint $table) {
            $table->string('user_id', 10)->primary();
            $table->string('username', 20);
            $table->string('password', 64);
            $table->char('role_flag', 1)->nullable();
            $table->text('sys_ip')->nullable();
            $table->integer('user_status');
            $table->integer('first_login')->default(0);
            $table->date('last_pwd_change')->nullable();
        });
    }

    private function makeUser(array $overrides = []): User
    {
        DB::table('user_account')->insert(array_merge([
            'user_id' => 'tester',
            'username' => 'TESTER',
            'password' => Hash::make('secret123'), // bcrypt
            'role_flag' => 'S',
            'user_status' => 1,
            'first_login' => 0,        // must change (first login)
            'last_pwd_change' => null,
        ], $overrides));

        return User::find('tester');
    }

    public function test_a_user_requiring_a_change_is_redirected_from_protected_pages(): void
    {
        $user = $this->makeUser(['first_login' => 0]);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertRedirect(route('password.change'));
    }

    public function test_a_user_with_a_current_password_can_reach_the_dashboard(): void
    {
        $user = $this->makeUser([
            'first_login' => 1,
            'last_pwd_change' => now()->toDateString(),
        ]);

        $this->actingAs($user)->get('/dashboard')->assertOk();
    }

    public function test_changing_the_password_updates_the_record_and_clears_the_requirement(): void
    {
        $user = $this->makeUser(['first_login' => 0]);

        Livewire::actingAs($user)->test(ChangePassword::class)
            ->set('current_password', 'secret123')
            ->set('password', 'new-secret-123')
            ->set('password_confirmation', 'new-secret-123')
            ->call('update')
            ->assertHasNoErrors()
            ->assertRedirect(route('dashboard'));

        $fresh = User::find('tester');
        $this->assertTrue(Hash::check('new-secret-123', $fresh->password));
        $this->assertFalse($fresh->mustChangePassword());
    }

    public function test_an_incorrect_current_password_is_rejected(): void
    {
        $user = $this->makeUser(['first_login' => 0]);

        Livewire::actingAs($user)->test(ChangePassword::class)
            ->set('current_password', 'WRONG')
            ->set('password', 'new-secret-123')
            ->set('password_confirmation', 'new-secret-123')
            ->call('update')
            ->assertHasErrors('current_password');
    }
}
