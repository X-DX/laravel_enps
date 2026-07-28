<?php

namespace Tests\Feature\Auth;

use App\Models\Permission;
use App\Models\User;
use App\Support\Navigation\SidebarMenu;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SidebarMenuTest extends TestCase
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

        Permission::create(['key' => 'adminsection.district_master', 'name' => 'District Master', 'group' => 'adminsection', 'legacy_menu_id' => 1]);
        Permission::create(['key' => 'adminsection.bank_entry', 'name' => 'Bank Entry', 'group' => 'adminsection', 'legacy_menu_id' => 2]);
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
        ]);

        return User::find($userId);
    }

    private function totalItems($menu): int
    {
        return $menu->sum(fn ($section) => $section['subs']->sum(fn ($sub) => $sub['items']->count()));
    }

    public function test_admin_sees_the_full_three_level_tree(): void
    {
        $menu = app(SidebarMenu::class)->forUser($this->makeUser('admin', 'A'));

        // Two sections (Admin Section before Entry Section), 3 items total.
        $this->assertCount(2, $menu);
        $this->assertSame('Admin Section', $menu->first()['title']);
        $this->assertSame(3, $this->totalItems($menu));

        // First section → sub-section is the legacy name "Master Entry".
        $this->assertSame('Master Entry', $menu->first()['subs']->first()['title']);
    }

    public function test_a_staff_user_sees_only_permitted_items(): void
    {
        $staff = $this->makeUser('staff', 'S');
        $perm = Permission::where('key', 'adminsection.district_master')->first();
        $staff->directPermissions()->attach($perm->id);

        $menu = app(SidebarMenu::class)->forUser($staff);

        $this->assertCount(1, $menu);
        $section = $menu->first();
        $this->assertSame('Admin Section', $section['title']);
        $this->assertCount(1, $section['subs']);

        $sub = $section['subs']->first();
        $this->assertSame('Master Entry', $sub['title']);
        $this->assertCount(1, $sub['items']);
        $this->assertSame('District Master', $sub['items']->first()['label']);
    }

    public function test_a_user_with_no_grants_sees_no_sections(): void
    {
        $menu = app(SidebarMenu::class)->forUser($this->makeUser('nobody', 'S'));

        $this->assertTrue($menu->isEmpty());
    }
}
