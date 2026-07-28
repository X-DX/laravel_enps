<?php

namespace Tests\Feature\Auth;

use App\Models\Permission;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AuthorizationEnforcementTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['user_permission', 'role_permission', 'permissions', 'roles', 'user_account'] as $t) {
            Schema::dropIfExists($t);
        }

        Schema::create('roles', function (Blueprint $t) {
            $t->id();
            $t->string('code', 10)->unique();
            $t->string('name', 100);
            $t->string('description')->nullable();
            $t->timestamps();
        });
        Schema::create('permissions', function (Blueprint $t) {
            $t->id();
            $t->string('key')->unique();
            $t->string('name');
            $t->string('group')->nullable();
            $t->unsignedBigInteger('legacy_menu_id')->nullable();
            $t->timestamps();
        });
        Schema::create('role_permission', function (Blueprint $t) {
            $t->unsignedBigInteger('role_id');
            $t->unsignedBigInteger('permission_id');
            $t->primary(['role_id', 'permission_id']);
        });
        Schema::create('user_permission', function (Blueprint $t) {
            $t->string('user_id', 10);
            $t->unsignedBigInteger('permission_id');
            $t->primary(['user_id', 'permission_id']);
        });
        Schema::create('user_account', function (Blueprint $t) {
            $t->string('user_id', 10)->primary();
            $t->string('username', 20);
            $t->string('password', 64);
            $t->char('role_flag', 1)->nullable();
            $t->integer('user_status');
            $t->integer('first_login')->default(0);
            $t->date('last_pwd_change')->nullable();
        });
    }

    private function makeUser(string $userId, string $roleFlag): User
    {
        DB::table('user_account')->insert([
            'user_id' => $userId,
            'username' => strtoupper($userId),
            'password' => 'x',
            'role_flag' => $roleFlag,
            'user_status' => 1,
            'first_login' => 1,
        ]);

        return User::find($userId);
    }

    public function test_the_gate_allows_a_user_holding_the_permission(): void
    {
        $perm = Permission::create(['key' => 'reports.view', 'name' => 'View reports']);
        $user = $this->makeUser('staff', 'S');
        $user->directPermissions()->attach($perm->id);

        $this->assertTrue(Gate::forUser($user)->allows('reports.view'));
        $this->assertFalse(Gate::forUser($user)->allows('reports.export'));
    }

    public function test_admin_bypasses_the_gate_entirely(): void
    {
        $admin = $this->makeUser('admin', 'A');

        $this->assertTrue(Gate::forUser($admin)->allows('anything.at.all'));
    }

    public function test_can_middleware_allows_and_forbids(): void
    {
        Route::get('/_test/reports', fn () => 'ok')->middleware('can:reports.view');

        $perm = Permission::create(['key' => 'reports.view', 'name' => 'View reports']);
        $granted = $this->makeUser('staff', 'S');
        $granted->directPermissions()->attach($perm->id);
        $denied = $this->makeUser('other', 'S');

        $this->actingAs($granted)->get('/_test/reports')->assertOk();
        $this->actingAs($denied)->get('/_test/reports')->assertForbidden();
    }
}
