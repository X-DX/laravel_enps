<?php

namespace Tests\Feature\Accounts;

use App\Models\Permission;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ShowSubscriberTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['user_permission', 'role_permission', 'permissions', 'roles', 'user_account', 'allotment_accnt_no', 'pran_no', 'department', 'ddo_master', 'designation_master', 'treasury_master'] as $t) {
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
        Schema::create('treasury_master', function (Blueprint $t) {
            $t->string('treasury_code', 10)->primary();
            $t->string('treasury_name', 150);
            $t->bigInteger('dist_code')->nullable();
        });
        Schema::create('ddo_master', function (Blueprint $t) {
            $t->bigIncrements('ddo_sl');
            $t->string('ddo_name', 150)->nullable();
            $t->string('treasury_code', 10)->nullable();
        });
        Schema::create('designation_master', function (Blueprint $t) {
            $t->bigIncrements('designation_id');
            $t->string('designation')->nullable();
        });
        Schema::create('department', function (Blueprint $t) {
            $t->string('dept_code', 10)->primary();
            $t->string('dept_name');
        });
        Schema::create('pran_no', function (Blueprint $t) {
            $t->string('account_no')->primary();
            $t->double('pran_no')->nullable();
            $t->string('ppan_no')->nullable();
        });
        Schema::create('allotment_accnt_no', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('name');
            $t->string('account_no')->nullable();
            $t->char('save_flag', 1)->nullable();
            $t->date('dob')->nullable();
            $t->date('doj')->nullable();
            $t->date('dor')->nullable();
            $t->date('doapptorder')->nullable();
            $t->date('entry_date')->nullable();
            $t->date('finalize_date')->nullable();
            $t->string('nameofdept')->nullable();
            $t->bigInteger('ddocode')->nullable();
            $t->bigInteger('designation')->nullable();
            $t->string('appnt_ord_no')->nullable();
            $t->string('father_name')->nullable();
            $t->string('mother_name')->nullable();
            $t->bigInteger('single_mother_flag')->default(0);
            $t->char('pension_type', 1)->nullable();
            $t->bigInteger('pay')->nullable();
            $t->string('starting_month')->nullable();
            $t->bigInteger('starting_fin_year')->nullable();
            $t->string('name_nominee')->nullable();
            $t->string('name_nominee2')->nullable();
            $t->string('name_nominee3')->nullable();
            $t->string('user_id')->nullable();
            $t->boolean('isactive')->default(true);
        });

        Permission::create(['key' => 'entrysection.view_all_accounts', 'name' => 'View All Accounts', 'group' => 'entrysection', 'legacy_menu_id' => 154]);

        DB::table('treasury_master')->insert(['treasury_code' => '01', 'treasury_name' => 'ITANAGAR TREASURY', 'dist_code' => 1]);
        DB::table('ddo_master')->insert(['ddo_sl' => 589, 'ddo_name' => 'DIR SMALL SAVINGS', 'treasury_code' => '01']);
        DB::table('designation_master')->insert(['designation_id' => 1, 'designation' => 'L.D.C']);
        DB::table('department')->insert(['dept_code' => '01', 'dept_name' => 'AP/ACCTT']);
        DB::table('pran_no')->insert(['account_no' => 'AP/NPS/01/0001', 'pran_no' => 110016825057, 'ppan_no' => 'APNPS010001']);

        // A finalized NPS subscriber, fully filled in.
        DB::table('allotment_accnt_no')->insert([
            'id' => 1, 'name' => 'PONDITA MODI', 'account_no' => 'AP/NPS/01/0001', 'save_flag' => 'F',
            'dob' => '1981-01-11', 'doj' => '2010-05-01', 'dor' => '2041-01-31', 'doapptorder' => '2013-06-11',
            'entry_date' => '2013-06-11', 'finalize_date' => '2013-07-01',
            'nameofdept' => '01', 'ddocode' => 589, 'designation' => 1, 'appnt_ord_no' => 'NM',
            'father_name' => 'FATHER X', 'mother_name' => 'MOTHER Y', 'single_mother_flag' => 0,
            'pension_type' => 'N', 'pay' => 71732, 'starting_month' => '05', 'starting_fin_year' => 2010,
            'name_nominee' => 'NOMINEE ONE', 'name_nominee2' => 'NOMINEE TWO', 'name_nominee3' => null,
            'user_id' => 'admin', 'isactive' => true,
        ]);

        // A UPS subscriber — used to check the pay label flips to "Basic Pay".
        DB::table('allotment_accnt_no')->insert([
            'id' => 2, 'name' => 'UPS PERSON', 'account_no' => 'AP/NPS/01/0002', 'save_flag' => 'F',
            'nameofdept' => '01', 'ddocode' => 589, 'designation' => 1,
            'pension_type' => 'U', 'pay' => 50000, 'starting_month' => '06', 'starting_fin_year' => 2011,
            'name_nominee' => 'NOM', 'single_mother_flag' => 0, 'user_id' => 'admin', 'isactive' => true,
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

    public function test_the_route_is_forbidden_without_the_permission(): void
    {
        $this->actingAs($this->makeUser('staff', 'S'))->get('/accounts/1')->assertForbidden();
    }

    public function test_an_admin_can_open_a_subscriber(): void
    {
        $this->actingAs($this->makeUser('admin', 'A'))->get('/accounts/1')->assertOk()->assertSee('PONDITA MODI');
    }

    public function test_it_shows_all_the_key_details_of_an_nps_subscriber(): void
    {
        $this->actingAs($this->makeUser('admin', 'A'))
            ->get('/accounts/1')
            ->assertOk()
            ->assertSee('FATHER X')            // father
            ->assertSee('MOTHER Y')            // mother
            ->assertSee('L.D.C')               // designation
            ->assertSee('AP/ACCTT')            // department (via trimmed dept code)
            ->assertSee('ITANAGAR TREASURY')   // treasury location (via the DDO's treasury)
            ->assertSee('DIR SMALL SAVINGS')   // DDO
            ->assertSee('Basic + DA')          // NPS pay label
            ->assertSee('71,732')              // pay, formatted
            ->assertSee('May')                 // deduction start month name
            ->assertSee('2010')                // deduction start year
            ->assertSee('NOMINEE ONE')         // 1st nominee
            ->assertSee('NOMINEE TWO')         // 2nd nominee
            ->assertSee('110016825057')        // PRAN
            ->assertSee('APNPS010001')         // PPAN
            ->assertSee('Finalized');          // status
    }

    public function test_a_ups_subscriber_shows_basic_pay_not_basic_plus_da(): void
    {
        $this->actingAs($this->makeUser('admin', 'A'))
            ->get('/accounts/2')
            ->assertOk()
            ->assertSee('UPS')
            ->assertSee('Basic Pay')
            ->assertDontSee('Basic + DA');
    }

    public function test_a_missing_subscriber_returns_404(): void
    {
        // Route-model binding: no subscriber with this id → automatic 404.
        $this->actingAs($this->makeUser('admin', 'A'))->get('/accounts/999999')->assertNotFound();
    }
}
