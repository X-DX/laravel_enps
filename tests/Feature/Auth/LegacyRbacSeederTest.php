<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Database\Seeders\LegacyRbacSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LegacyRbacSeederTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['user_permission', 'role_permission', 'permissions', 'roles', 'user_account', 'menu_items'] as $t) {
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
            $t->text('menu_ids')->nullable();
            $t->integer('user_status');
            $t->integer('first_login')->default(0);
            $t->date('last_pwd_change')->nullable();
        });
        Schema::create('menu_items', function (Blueprint $t) {
            $t->integer('menu_id')->primary();
            $t->string('menu_name')->nullable();
            $t->string('menu_label')->nullable();
            $t->string('menu')->nullable();
            $t->string('sub_menu')->nullable();
        });

        DB::table('menu_items')->insert([
            ['menu_id' => 1, 'menu_name' => '', 'menu_label' => 'District Master', 'menu' => 'adminsection', 'sub_menu' => 'masterentry'],
            ['menu_id' => 2, 'menu_name' => '', 'menu_label' => 'Bank Entry', 'menu' => 'adminsection', 'sub_menu' => 'masterentry'],
            ['menu_id' => 171, 'menu_name' => '', 'menu_label' => 'First Register', 'menu' => 'entrysection', 'sub_menu' => 'firstregister'],
        ]);

        DB::table('user_account')->insert([
            ['user_id' => 'admin', 'username' => 'ADMIN', 'password' => 'x', 'role_flag' => 'A', 'menu_ids' => null, 'user_status' => 1, 'first_login' => 1],
            ['user_id' => 'staff', 'username' => 'STAFF', 'password' => 'x', 'role_flag' => 'S', 'menu_ids' => '1,171,999', 'user_status' => 1, 'first_login' => 1],
        ]);
    }

    public function test_it_seeds_roles_from_role_flags(): void
    {
        $this->seed(LegacyRbacSeeder::class);

        $this->assertDatabaseHas('roles', ['code' => 'A', 'name' => 'Administrator']);
        $this->assertDatabaseHas('roles', ['code' => 'S', 'name' => 'Staff']);
    }

    public function test_it_seeds_one_permission_per_menu_item(): void
    {
        $this->seed(LegacyRbacSeeder::class);

        $this->assertSame(3, DB::table('permissions')->count());
        $this->assertDatabaseHas('permissions', [
            'legacy_menu_id' => 1,
            'group' => 'adminsection',
            'name' => 'District Master',
            'key' => 'adminsection.district_master',
        ]);
    }

    public function test_it_grants_users_only_their_valid_menu_ids(): void
    {
        $this->seed(LegacyRbacSeeder::class);

        // staff had 1,171,999 — 999 has no menu_item, so only 2 grants.
        $this->assertSame(2, DB::table('user_permission')->where('user_id', 'staff')->count());

        $staff = User::find('staff');
        $this->assertTrue($staff->hasPermissionTo('adminsection.district_master'));
        $this->assertTrue($staff->hasPermissionTo('entrysection.first_register'));
        $this->assertFalse($staff->hasPermissionTo('adminsection.bank_entry'));
    }

    public function test_it_is_idempotent(): void
    {
        $this->seed(LegacyRbacSeeder::class);
        $this->seed(LegacyRbacSeeder::class);

        $this->assertSame(3, DB::table('permissions')->count());
        $this->assertSame(2, DB::table('user_permission')->where('user_id', 'staff')->count());
    }
}
