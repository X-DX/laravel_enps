<?php

namespace Tests\Feature\Auth;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RolePermissionTest extends TestCase
{
    /**
     * Build the RBAC tables (+ a minimal user_account) in the sqlite test DB.
     */
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

    public function test_admin_bypasses_all_permission_checks(): void
    {
        $admin = $this->makeUser('admin', 'A');

        $this->assertTrue($admin->isAdmin());
        $this->assertTrue($admin->hasPermissionTo('anything.at.all'));
    }

    public function test_a_directly_granted_permission_is_allowed(): void
    {
        $perm = Permission::create(['key' => 'central_register.create', 'name' => 'Create CR']);
        $user = $this->makeUser('staff1', 'S');
        $user->directPermissions()->attach($perm->id);

        $this->assertFalse($user->isAdmin());
        $this->assertTrue($user->hasPermissionTo('central_register.create'));
        $this->assertFalse($user->hasPermissionTo('reports.view'));
    }

    public function test_a_permission_inherited_from_the_role_is_allowed(): void
    {
        $perm = Permission::create(['key' => 'reports.view', 'name' => 'View reports']);
        $role = Role::create(['code' => 'S', 'name' => 'Staff']);
        $role->permissions()->attach($perm->id);

        $user = $this->makeUser('staff2', 'S');

        $this->assertTrue($user->hasPermissionTo('reports.view'));
    }

    public function test_a_user_without_grants_is_denied(): void
    {
        Permission::create(['key' => 'x.y', 'name' => 'X']);
        $user = $this->makeUser('staff3', 'S');

        $this->assertFalse($user->hasPermissionTo('x.y'));
    }
}
