<?php

namespace Tests\Feature\Accounts;

use App\Livewire\Accounts\IssueAccount;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

class IssueAccountTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['user_permission', 'role_permission', 'permissions', 'roles', 'user_account', 'allotment_accnt_no', 'ddo_master', 'treasury_master', 'department', 'designation_master', 'retirement_year'] as $t) {
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
        Schema::create('retirement_year', function (Blueprint $t) {
            $t->integer('year');
        });
        Schema::create('designation_master', function (Blueprint $t) {
            $t->bigIncrements('designation_id');
            $t->string('designation')->nullable();
        });
        Schema::create('department', function (Blueprint $t) {
            $t->string('dept_code', 10)->primary();
            $t->string('dept_name');
        });
        Schema::create('treasury_master', function (Blueprint $t) {
            $t->string('treasury_code', 10)->primary();
            $t->string('treasury_name', 150);
            $t->bigInteger('dist_code')->nullable();
        });
        Schema::create('ddo_master', function (Blueprint $t) {
            $t->bigIncrements('ddo_sl');
            $t->string('ddo_name', 150)->nullable();
            $t->string('ddo_code', 7)->nullable();
            $t->string('treasury_code', 10)->nullable();
        });
        Schema::create('allotment_accnt_no', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('name');
            $t->string('father_name')->nullable();
            $t->string('mother_name')->nullable();
            $t->bigInteger('single_mother_flag')->default(0);
            $t->string('appnt_ord_no')->nullable();
            $t->date('doapptorder')->nullable();
            $t->date('dob')->nullable();
            $t->date('doj')->nullable();
            $t->date('dor')->nullable();
            $t->bigInteger('designation')->nullable();
            $t->string('nameofdept')->nullable();
            $t->char('pension_type', 1)->nullable();
            $t->bigInteger('pay')->nullable();
            $t->bigInteger('ddocode')->nullable();
            $t->string('name_nominee')->nullable();
            $t->string('name_nominee2')->nullable();
            $t->string('name_nominee3')->nullable();
            $t->string('starting_month')->nullable();
            $t->bigInteger('starting_fin_year')->nullable();
            $t->string('account_no')->nullable();
            $t->char('save_flag', 1)->nullable();
            $t->date('entry_date')->nullable();
            $t->date('finalize_date')->nullable();
            $t->string('user_id')->nullable();
            $t->char('flag_pt', 1)->nullable();
            $t->bigInteger('closure_reason_id');   // NOT NULL — guards the fillable regression
            $t->boolean('isactive')->default(true);
        });

        Permission::create(['key' => 'entrysection.issue_account', 'name' => 'Issue Account', 'group' => 'entrysection', 'legacy_menu_id' => 152]);

        DB::table('retirement_year')->insert(['year' => 60]);
        DB::table('designation_master')->insert(['designation_id' => 1738, 'designation' => 'L.D.C']);
        DB::table('department')->insert(['dept_code' => '15', 'dept_name' => 'AP/HEALTH']);
        DB::table('treasury_master')->insert([
            ['treasury_code' => '01', 'treasury_name' => 'Itanagar', 'dist_code' => 1],
            ['treasury_code' => '02', 'treasury_name' => 'Naharlagun', 'dist_code' => 1],
        ]);
        DB::table('ddo_master')->insert([
            ['ddo_sl' => 2, 'ddo_name' => 'DDO Alpha', 'treasury_code' => '01'],
            ['ddo_sl' => 3, 'ddo_name' => 'DDO Beta', 'treasury_code' => '02'],
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

    /** Fill every required field with valid values (a helper for the happy-path tests). */
    private function validForm(\Livewire\Features\SupportTesting\Testable $c): \Livewire\Features\SupportTesting\Testable
    {
        return $c->set('name', 'Arup Chandra Roy')
            ->set('father_name', 'Ganesh Roy')
            ->set('mother_name', 'Sona Roy')
            ->set('appnt_ord_no', 'NIC/2026/001')
            ->set('doapptorder', '2021-01-01')
            ->set('dob', '1996-07-20')
            ->set('doj', '2021-01-01')
            ->set('dor', '2056-07-31')
            ->set('designation', '1738')
            ->set('nameofdept', '15')
            ->set('pension_type', 'N')
            ->set('pay', '60000')
            ->set('treasury_code', '01')
            ->set('ddocode', '2')
            ->set('name_nominee', 'Sam')
            ->set('starting_month', '01')
            ->set('starting_fin_year', '2026');
    }

    public function test_the_route_is_forbidden_without_the_permission(): void
    {
        $this->actingAs($this->makeUser('staff', 'S'))->get('/accounts/issue')->assertForbidden();
    }

    public function test_an_admin_can_open_the_form(): void
    {
        $this->actingAs($this->makeUser('admin', 'A'))->get('/accounts/issue')->assertOk()->assertSee('Issue Account');
    }

    public function test_dob_auto_fills_the_retirement_date(): void
    {
        Livewire::actingAs($this->makeUser('admin', 'A'))
            ->test(IssueAccount::class)
            ->set('dob', '1996-07-20')
            ->assertSet('dor', '2056-07-31');   // + 60 years, end of that month
    }

    public function test_single_mother_clears_the_father_name(): void
    {
        Livewire::actingAs($this->makeUser('admin', 'A'))
            ->test(IssueAccount::class)
            ->set('father_name', 'Ganesh Roy')
            ->set('single_mother_flag', true)
            ->assertSet('father_name', '');
    }

    public function test_choosing_a_treasury_shows_only_its_ddos_and_clears_the_old_ddo(): void
    {
        Livewire::actingAs($this->makeUser('admin', 'A'))
            ->test(IssueAccount::class)
            ->set('treasury_code', '01')
            ->assertSee('DDO Alpha')       // under treasury 01
            ->assertDontSee('DDO Beta')    // under treasury 02
            ->set('ddocode', '2')
            ->set('treasury_code', '02')   // switching treasury...
            ->assertSet('ddocode', '');    // ...clears the stale DDO
    }

    public function test_father_name_is_required_unless_single_mother(): void
    {
        $admin = $this->makeUser('admin', 'A');

        // Without single mother → father is required.
        $this->validForm(Livewire::actingAs($admin)->test(IssueAccount::class))
            ->set('father_name', '')
            ->call('save')
            ->assertHasErrors(['father_name' => 'required']);

        // With single mother → father may be blank.
        $this->validForm(Livewire::actingAs($admin)->test(IssueAccount::class))
            ->set('father_name', '')
            ->set('single_mother_flag', true)
            ->call('save')
            ->assertHasNoErrors();
    }

    public function test_it_saves_a_draft_with_the_system_fields(): void
    {
        $this->validForm(Livewire::actingAs($this->makeUser('admin', 'A'))->test(IssueAccount::class))
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('name', '');   // the form clears for the next entry

        $this->assertDatabaseHas('allotment_accnt_no', [
            'name' => 'Arup Chandra Roy',
            'designation' => 1738,
            'nameofdept' => '15',
            'ddocode' => 2,
            'pension_type' => 'N',
            'pay' => 60000,
            // system-set
            'save_flag' => 'T',       // a draft
            'account_no' => null,     // pending — no number until finalize
            'flag_pt' => 'N',
            'closure_reason_id' => 0,
            'user_id' => 'admin',
            'isactive' => true,
        ]);
    }
}
