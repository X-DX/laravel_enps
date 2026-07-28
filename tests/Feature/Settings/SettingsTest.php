<?php

namespace Tests\Feature\Settings;

use App\Livewire\Settings\ContributionShare;
use App\Livewire\Settings\InterestRates;
use App\Livewire\Settings\RetirementYear;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['user_permission', 'role_permission', 'permissions', 'roles', 'user_account', 'rate', 'retirement_year', 'mst_contribution_share'] as $t) {
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
        Schema::create('rate', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('fin_year', 10)->nullable();
            $t->double('rate');
        });
        Schema::create('retirement_year', function (Blueprint $t) {
            $t->bigInteger('year')->nullable();
        });
        Schema::create('mst_contribution_share', function (Blueprint $t) {
            $t->bigInteger('sl');
            $t->bigInteger('emp_share');
            $t->bigInteger('govt_share')->nullable();
        });

        Permission::create(['key' => 'adminsection.change_interest_rate', 'name' => 'Change Interest Rate', 'group' => 'adminsection', 'legacy_menu_id' => 41]);
        Permission::create(['key' => 'adminsection.change_retirement_year', 'name' => 'Change Retirement Year', 'group' => 'adminsection', 'legacy_menu_id' => 42]);
        Permission::create(['key' => 'adminsection.change_share_rate', 'name' => 'Change Share Rate', 'group' => 'adminsection', 'legacy_menu_id' => 43]);

        DB::table('retirement_year')->insert(['year' => 60]);
        DB::table('mst_contribution_share')->insert(['sl' => 1, 'emp_share' => 10, 'govt_share' => 14]);
        DB::table('rate')->insert([
            ['fin_year' => '2013-2014', 'rate' => 8.8],
            ['fin_year' => '2014-2015', 'rate' => 8.8],
        ]);
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

    /* ---------------- Interest rate ---------------- */

    public function test_interest_rate_is_forbidden_without_the_permission(): void
    {
        $this->actingAs($this->makeUser('staff', 'S'))->get('/settings/interest-rates')->assertForbidden();
    }

    public function test_admin_can_add_an_interest_rate(): void
    {
        Livewire::actingAs($this->makeUser('admin', 'A'))
            ->test(InterestRates::class)
            ->call('create')
            ->set('fin_year', '2015-2016')
            ->set('rate', '8.5')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('rate', ['fin_year' => '2015-2016']);
    }

    public function test_interest_rate_rejects_a_duplicate_year_and_blank_rate(): void
    {
        $admin = $this->makeUser('admin', 'A');

        Livewire::actingAs($admin)->test(InterestRates::class)
            ->set('fin_year', '2013-2014')->set('rate', '9')
            ->call('save')->assertHasErrors(['fin_year' => 'unique']);

        Livewire::actingAs($admin)->test(InterestRates::class)
            ->set('fin_year', '2016-2017')->set('rate', '')
            ->call('save')->assertHasErrors(['rate' => 'required']);
    }

    /* ---------------- Retirement year ---------------- */

    public function test_retirement_year_loads_and_updates(): void
    {
        Livewire::actingAs($this->makeUser('admin', 'A'))
            ->test(RetirementYear::class)
            ->assertSet('year', '60')          // preloaded current value
            ->set('year', '62')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(62, (int) DB::table('retirement_year')->value('year'));
    }

    public function test_retirement_year_rejects_an_out_of_range_value(): void
    {
        Livewire::actingAs($this->makeUser('admin', 'A'))
            ->test(RetirementYear::class)
            ->set('year', '30')
            ->call('save')
            ->assertHasErrors(['year' => 'between']);
    }

    /* ---------------- Contribution share ---------------- */

    public function test_contribution_share_loads_and_updates(): void
    {
        Livewire::actingAs($this->makeUser('admin', 'A'))
            ->test(ContributionShare::class)
            ->assertSet('emp_share', '10')
            ->assertSet('govt_share', '14')
            ->set('emp_share', '12')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('mst_contribution_share', ['sl' => 1, 'emp_share' => 12, 'govt_share' => 14]);
    }

    public function test_contribution_share_rejects_an_invalid_value(): void
    {
        Livewire::actingAs($this->makeUser('admin', 'A'))
            ->test(ContributionShare::class)
            ->set('emp_share', '150')
            ->call('save')
            ->assertHasErrors(['emp_share' => 'between']);
    }
}
