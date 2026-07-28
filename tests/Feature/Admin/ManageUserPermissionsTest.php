<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\ManageUserPermissions;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

class ManageUserPermissionsTest extends TestCase
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

        Permission::create(['key' => 'adminsection.add_update_user', 'name' => 'Add Update user', 'group' => 'adminsection', 'legacy_menu_id' => 31]);
        Permission::create(['key' => 'entrysection.first_register', 'name' => 'First Register', 'group' => 'entrysection', 'legacy_menu_id' => 171]);
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
            'last_pwd_change' => now()->toDateString(),
        ]);

        return User::find($userId);
    }

    public function test_admin_can_open_the_screen(): void
    {
        $admin = $this->makeUser('admin', 'A');

        $this->actingAs($admin)->get('/admin/permissions')->assertOk();
    }

    public function test_a_non_admin_without_the_permission_is_forbidden(): void
    {
        $staff = $this->makeUser('staff', 'S');

        $this->actingAs($staff)->get('/admin/permissions')->assertForbidden();
    }

    public function test_admin_can_grant_and_revoke_a_users_permissions(): void
    {
        $admin = $this->makeUser('admin', 'A');
        $staff = $this->makeUser('staff', 'S');
        $perm = Permission::where('key', 'entrysection.first_register')->first();

        // Grant.
        Livewire::actingAs($admin)->test(ManageUserPermissions::class)
            ->set('userId', 'staff')
            ->set('selected', [(string) $perm->id])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('user_permission', ['user_id' => 'staff', 'permission_id' => $perm->id]);
        $this->assertTrue(User::find('staff')->hasPermissionTo('entrysection.first_register'));

        // Revoke.
        Livewire::actingAs($admin)->test(ManageUserPermissions::class)
            ->set('userId', 'staff')
            ->set('selected', [])
            ->call('save');

        $this->assertDatabaseMissing('user_permission', ['user_id' => 'staff', 'permission_id' => $perm->id]);
    }
}
