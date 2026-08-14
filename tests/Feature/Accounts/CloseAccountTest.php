<?php

namespace Tests\Feature\Accounts;

use App\Livewire\Accounts\CloseAccount;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Close Account: a cascade form (department → account → name/PRAN → reason + closing details)
 * that writes an account_closure row and flips allotment_accnt_no.isactive to false, in one
 * transaction. An account can be closed only once (the WHERE isactive=true guard).
 */
class CloseAccountTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['user_permission', 'role_permission', 'permissions', 'roles', 'user_account', 'allotment_accnt_no', 'account_closure', 'm_closure_reason', 'department', 'ddo_master', 'pran_no'] as $t) {
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
        Schema::create('m_closure_reason', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('reason');
        });
        Schema::create('department', function (Blueprint $t) {
            $t->string('dept_code', 10)->primary();
            $t->string('dept_name');
        });
        Schema::create('ddo_master', function (Blueprint $t) {
            $t->bigIncrements('ddo_sl');
            $t->string('ddo_name', 150)->nullable();
            $t->string('treasury_code', 10)->nullable();
        });
        Schema::create('pran_no', function (Blueprint $t) {
            $t->string('account_no')->primary();
            $t->double('pran_no')->nullable();
        });
        Schema::create('allotment_accnt_no', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('name');
            $t->string('account_no')->nullable();
            $t->char('save_flag', 1)->nullable();
            $t->string('nameofdept')->nullable();
            $t->bigInteger('ddocode')->nullable();
            $t->char('pension_type', 1)->nullable();
            $t->bigInteger('closure_reason_id')->default(0);
            $t->boolean('isactive')->default(true);
        });
        Schema::create('account_closure', function (Blueprint $t) {
            $t->string('account_no', 30)->primary();
            $t->bigInteger('closure_reason_id');
            $t->date('closing_date')->nullable();
            $t->smallInteger('deduction_month')->nullable();
            $t->smallInteger('deduction_year')->nullable();
            $t->string('closed_by', 10)->nullable();
            $t->timestamp('created_at')->nullable();
        });

        Permission::create(['key' => 'entrysection.close_account', 'name' => 'Close Account', 'group' => 'entrysection', 'legacy_menu_id' => 234]);

        DB::table('m_closure_reason')->insert([
            ['id' => 1, 'reason' => 'Death Case'],
            ['id' => 2, 'reason' => 'VRS'],
        ]);
        DB::table('department')->insert(['dept_code' => '15', 'dept_name' => 'AP/HEALTH']);
        DB::table('ddo_master')->insert(['ddo_sl' => 2, 'ddo_name' => 'DDO Alpha', 'treasury_code' => '01']);
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

    private function seedFinalized(string $accountNo, string $name = 'PONDITA MODI', bool $isactive = true): int
    {
        return DB::table('allotment_accnt_no')->insertGetId([
            'name' => $name,
            'account_no' => $accountNo,
            'save_flag' => 'F',
            'nameofdept' => '15',
            'ddocode' => 2,
            'pension_type' => 'N',
            'closure_reason_id' => 0,
            'isactive' => $isactive,
        ]);
    }

    public function test_the_route_is_forbidden_without_the_permission(): void
    {
        $this->actingAs($this->makeUser('staff', 'S'))->get('/accounts/close')->assertForbidden();
    }

    public function test_selecting_an_account_fills_name_and_pran(): void
    {
        $this->seedFinalized('AP/NPS/15/0001', 'PONDITA MODI');
        DB::table('pran_no')->insert(['account_no' => 'AP/NPS/15/0001', 'pran_no' => 110016825057]);

        Livewire::actingAs($this->makeUser('admin', 'A'))
            ->test(CloseAccount::class)
            ->set('departmentCode', '15')
            ->set('accountNo', 'AP/NPS/15/0001')
            ->assertSet('name', 'PONDITA MODI')
            ->assertSet('pranNo', '110016825057');
    }

    public function test_closing_requires_reason_date_month_and_year(): void
    {
        $this->seedFinalized('AP/NPS/15/0001');

        Livewire::actingAs($this->makeUser('admin', 'A'))
            ->test(CloseAccount::class)
            ->set('departmentCode', '15')
            ->set('accountNo', 'AP/NPS/15/0001')
            ->call('close')
            ->assertHasErrors(['closeReason', 'closingDate', 'deductionMonth', 'deductionYear']);
    }

    public function test_it_closes_an_active_finalized_account(): void
    {
        $id = $this->seedFinalized('AP/NPS/15/0001');

        Livewire::actingAs($this->makeUser('admin', 'A'))
            ->test(CloseAccount::class)
            ->set('departmentCode', '15')
            ->set('accountNo', 'AP/NPS/15/0001')
            ->set('closeReason', '1')
            ->set('closingDate', '2026-08-14')
            ->set('deductionMonth', '8')
            ->set('deductionYear', '2026')
            ->call('close')
            ->assertHasNoErrors()
            ->assertSet('accountNo', '');   // the form clears after closing

        $this->assertDatabaseHas('account_closure', [
            'account_no' => 'AP/NPS/15/0001',
            'closure_reason_id' => 1,
            'deduction_month' => 8,
            'deduction_year' => 2026,
            'closed_by' => 'admin',
        ]);
        $this->assertDatabaseHas('allotment_accnt_no', ['id' => $id, 'isactive' => false]);
    }

    public function test_it_will_not_close_an_account_that_is_already_closed(): void
    {
        $id = $this->seedFinalized('AP/NPS/15/0001');

        $component = Livewire::actingAs($this->makeUser('admin', 'A'))
            ->test(CloseAccount::class)
            ->set('departmentCode', '15')
            ->set('accountNo', 'AP/NPS/15/0001')
            ->set('closeReason', '1')
            ->set('closingDate', '2026-08-14')
            ->set('deductionMonth', '8')
            ->set('deductionYear', '2026');

        // Someone else closes it between selection and submit.
        DB::table('allotment_accnt_no')->where('id', $id)->update(['isactive' => false]);

        $component->call('close');

        // The guard stopped a second closure — no row written.
        $this->assertSame(0, DB::table('account_closure')->count());
    }

    public function test_the_closed_register_lists_closures(): void
    {
        $this->seedFinalized('AP/NPS/15/0009', 'CLOSED PERSON', isactive: false);
        DB::table('account_closure')->insert([
            'account_no' => 'AP/NPS/15/0009',
            'closure_reason_id' => 1,
            'closing_date' => '2026-06-24',
            'deduction_month' => 6,
            'deduction_year' => 2026,
            'closed_by' => 'admin',
            'created_at' => now(),
        ]);

        Livewire::actingAs($this->makeUser('admin', 'A'))
            ->test(CloseAccount::class)
            ->assertSee('AP/NPS/15/0009')
            ->assertSee('CLOSED PERSON')
            ->assertSee('Death Case');
    }
}
