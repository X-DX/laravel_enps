<?php

namespace Tests\Feature\Accounts;

use App\Livewire\Accounts\AssignPran;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Assign PRAN Against Accounts (M5, 5.1): search a finalized account, add/update its PRAN as a
 * draft (save_flag='T'), then finalize/delete the drafts. PRAN is 12 digits, first digit 1-9,
 * confirm-matched, and globally unique.
 */
class AssignPranTest extends TestCase
{
    private const ABILITY = 'entrysection.assign_pran_against_accounts';

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['user_permission', 'role_permission', 'permissions', 'roles', 'user_account', 'allotment_accnt_no', 'pran_no', 'ddo_master', 'designation_master', 'treasury_master', 'department'] as $t) {
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
        });
        Schema::create('ddo_master', function (Blueprint $t) {
            $t->bigIncrements('ddo_sl');
            $t->string('ddo_name', 150)->nullable();
            $t->string('treasury_code', 10)->nullable();
        });
        Schema::create('allotment_accnt_no', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('name');
            $t->string('account_no')->nullable();
            $t->char('save_flag', 1)->nullable();
            $t->boolean('isactive')->default(true);
            $t->date('dob')->nullable();
            $t->string('nameofdept')->nullable();
            $t->bigInteger('ddocode')->nullable();
            $t->bigInteger('designation')->nullable();
            $t->string('user_id', 10)->nullable();   // the ownership column — without it the scope can never fire
        });
        Schema::create('pran_no', function (Blueprint $t) {
            $t->string('account_no')->primary();
            $t->bigInteger('pran_no')->nullable();
            $t->integer('nira_account')->nullable();
            $t->date('pran_allotment_date')->nullable();
            $t->string('ddo_reg')->nullable();
            $t->char('save_flag', 1)->nullable();
            $t->date('entry_date')->nullable();
            $t->date('finalize_date')->nullable();
            $t->string('user_id')->nullable();
            $t->integer('is_active')->nullable();
        });

        Permission::create(['key' => self::ABILITY, 'name' => 'Assign Pran Against Accounts', 'group' => 'entrysection', 'legacy_menu_id' => 157]);

        DB::table('designation_master')->insert(['designation_id' => 1, 'designation' => 'L.D.C']);
        DB::table('department')->insert(['dept_code' => '15', 'dept_name' => 'AP/HEALTH']);
        DB::table('treasury_master')->insert(['treasury_code' => '01', 'treasury_name' => 'Itanagar']);
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

    /**
     * Seed an account. `userId` defaults to null on purpose: 17,790 migrated rows carry no
     * owner, so that is the realistic case a non-admin operator must still be able to work with.
     */
    private function seedAccount(?string $accountNo, string $name = 'PONDITA MODI', string $saveFlag = 'F', bool $isactive = true, ?string $userId = null): void
    {
        DB::table('allotment_accnt_no')->insert([
            'name' => $name,
            'account_no' => $accountNo,
            'save_flag' => $saveFlag,
            'isactive' => $isactive,
            'dob' => '1981-01-11',
            'nameofdept' => '15',
            'ddocode' => 2,
            'designation' => 1,
            'user_id' => $userId,
        ]);
    }

    /** A plain operator (role 'S') holding the screen's permission directly. */
    private function makeOperator(string $userId = 'operator'): User
    {
        $user = $this->makeUser($userId, 'S');

        DB::table('user_permission')->insert([
            'user_id' => $userId,
            'permission_id' => Permission::where('key', self::ABILITY)->value('id'),
        ]);

        return $user;
    }

    private function seedPran(string $accountNo, string $saveFlag = 'T', int $pran = 110016825057): void
    {
        DB::table('pran_no')->insert([
            'account_no' => $accountNo,
            'pran_no' => $pran,
            'nira_account' => 0,
            'pran_allotment_date' => '2024-01-01',
            'ddo_reg' => 'SGV183455B',
            'save_flag' => $saveFlag,
            'entry_date' => '2024-01-01',
            'user_id' => 'admin',
            'is_active' => 1,
        ]);
    }

    public function test_the_route_is_forbidden_without_the_permission(): void
    {
        $this->actingAs($this->makeUser('staff', 'S'))->get('/accounts/pran')->assertForbidden();
    }

    public function test_search_finds_finalized_accounts_by_number_or_name(): void
    {
        $this->seedAccount('AP/NPS/15/0001', 'PONDITA MODI');
        $this->seedAccount(null, 'DRAFT PERSON', saveFlag: 'T');   // a draft — must not appear

        $component = Livewire::actingAs($this->makeUser('admin', 'A'))->test(AssignPran::class);

        $component->set('search', 'pondita')->assertSee('AP/NPS/15/0001')->assertSee('PONDITA MODI');
        $component->set('search', 'AP/NPS/15')->assertSee('PONDITA MODI')->assertDontSee('DRAFT PERSON');
    }

    public function test_selecting_an_account_loads_it_in_add_mode(): void
    {
        $this->seedAccount('AP/NPS/15/0001');

        Livewire::actingAs($this->makeUser('admin', 'A'))
            ->test(AssignPran::class)
            ->call('selectAccount', 'AP/NPS/15/0001')
            ->assertSet('selectedAccountNo', 'AP/NPS/15/0001')
            ->assertSet('pranNo', '');   // no existing PRAN
    }

    public function test_selecting_an_account_prefills_an_existing_pran(): void
    {
        $this->seedAccount('AP/NPS/15/0001');
        $this->seedPran('AP/NPS/15/0001', saveFlag: 'T', pran: 110016825057);

        Livewire::actingAs($this->makeUser('admin', 'A'))
            ->test(AssignPran::class)
            ->call('selectAccount', 'AP/NPS/15/0001')
            ->assertSet('pranNo', '110016825057')
            ->assertSet('confirmPranNo', '110016825057');
    }

    public function test_a_closed_account_cannot_be_selected(): void
    {
        $this->seedAccount('AP/NPS/15/0009', isactive: false);   // finalized but closed

        Livewire::actingAs($this->makeUser('admin', 'A'))
            ->test(AssignPran::class)
            ->call('selectAccount', 'AP/NPS/15/0009')
            ->assertSet('selectedAccountNo', '');   // rejected
    }

    public function test_save_validates_the_pran(): void
    {
        $this->seedAccount('AP/NPS/15/0001');
        $admin = $this->makeUser('admin', 'A');

        // too short / starts with 0 → regex error
        Livewire::actingAs($admin)->test(AssignPran::class)
            ->call('selectAccount', 'AP/NPS/15/0001')
            ->set('pranNo', '012345678901')->set('confirmPranNo', '012345678901')->set('pranAllotmentDate', '2024-01-01')
            ->call('save')->assertHasErrors(['pranNo' => 'regex']);

        // confirm mismatch
        Livewire::actingAs($admin)->test(AssignPran::class)
            ->call('selectAccount', 'AP/NPS/15/0001')
            ->set('pranNo', '110016825057')->set('confirmPranNo', '999916825057')->set('pranAllotmentDate', '2024-01-01')
            ->call('save')->assertHasErrors(['confirmPranNo' => 'same']);

        // missing date
        Livewire::actingAs($admin)->test(AssignPran::class)
            ->call('selectAccount', 'AP/NPS/15/0001')
            ->set('pranNo', '110016825057')->set('confirmPranNo', '110016825057')->set('pranAllotmentDate', '')
            ->call('save')->assertHasErrors(['pranAllotmentDate' => 'required']);
    }

    public function test_save_rejects_a_pran_already_used_by_another_account(): void
    {
        $this->seedAccount('AP/NPS/15/0001');
        $this->seedAccount('AP/NPS/15/0002');
        $this->seedPran('AP/NPS/15/0001', saveFlag: 'F', pran: 110016825057);   // already used

        Livewire::actingAs($this->makeUser('admin', 'A'))
            ->test(AssignPran::class)
            ->call('selectAccount', 'AP/NPS/15/0002')
            ->set('pranNo', '110016825057')->set('confirmPranNo', '110016825057')->set('pranAllotmentDate', '2024-01-01')
            ->call('save')
            ->assertHasErrors(['pranNo' => 'unique']);
    }

    public function test_save_adds_a_new_draft_pran_with_the_system_fields(): void
    {
        $this->seedAccount('AP/NPS/15/0001');

        Livewire::actingAs($this->makeUser('admin', 'A'))
            ->test(AssignPran::class)
            ->call('selectAccount', 'AP/NPS/15/0001')
            ->set('pranNo', '110016825057')->set('confirmPranNo', '110016825057')
            ->set('pranAllotmentDate', '2024-01-01')->set('niraAccount', true)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('selectedAccountNo', '');   // form clears

        $this->assertDatabaseHas('pran_no', [
            'account_no' => 'AP/NPS/15/0001',
            'pran_no' => 110016825057,
            'nira_account' => 1,
            'save_flag' => 'T',
            'ddo_reg' => 'SGV183455B',
            'user_id' => 'admin',
            'is_active' => 1,
        ]);
    }

    public function test_save_updates_an_existing_pran(): void
    {
        $this->seedAccount('AP/NPS/15/0001');
        $this->seedPran('AP/NPS/15/0001', saveFlag: 'T', pran: 110016825057);

        Livewire::actingAs($this->makeUser('admin', 'A'))
            ->test(AssignPran::class)
            ->call('selectAccount', 'AP/NPS/15/0001')
            ->set('pranNo', '110034821858')->set('confirmPranNo', '110034821858')->set('pranAllotmentDate', '2024-02-02')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('pran_no', ['account_no' => 'AP/NPS/15/0001', 'pran_no' => 110034821858]);
        $this->assertSame(1, DB::table('pran_no')->count());   // still one row, not a duplicate
    }

    public function test_finalize_selected_flips_drafts_to_finalized(): void
    {
        $this->seedAccount('AP/NPS/15/0001');
        $this->seedAccount('AP/NPS/15/0002');
        $this->seedPran('AP/NPS/15/0001', saveFlag: 'T', pran: 110016825057);
        $this->seedPran('AP/NPS/15/0002', saveFlag: 'T', pran: 110034821858);

        Livewire::actingAs($this->makeUser('admin', 'A'))
            ->test(AssignPran::class)
            ->set('selected', ['AP/NPS/15/0001', 'AP/NPS/15/0002'])
            ->call('finalizeSelected')
            ->assertSet('selected', []);

        $this->assertSame(2, DB::table('pran_no')->where('save_flag', 'F')->whereNotNull('finalize_date')->count());
    }

    public function test_delete_selected_removes_only_draft_prans(): void
    {
        $this->seedAccount('AP/NPS/15/0001');
        $this->seedAccount('AP/NPS/15/0002');
        $this->seedPran('AP/NPS/15/0001', saveFlag: 'T', pran: 110016825057);   // draft
        $this->seedPran('AP/NPS/15/0002', saveFlag: 'F', pran: 110034821858);   // finalized

        Livewire::actingAs($this->makeUser('admin', 'A'))
            ->test(AssignPran::class)
            ->set('selected', ['AP/NPS/15/0001', 'AP/NPS/15/0002'])
            ->call('deleteSelected');

        $this->assertDatabaseMissing('pran_no', ['account_no' => 'AP/NPS/15/0001']);   // draft gone
        $this->assertDatabaseHas('pran_no', ['account_no' => 'AP/NPS/15/0002']);        // finalized kept
    }

    public function test_toggle_select_all_selects_the_pages_pending_prans(): void
    {
        $this->seedAccount('AP/NPS/15/0001');
        $this->seedAccount('AP/NPS/15/0002');
        $this->seedPran('AP/NPS/15/0001', saveFlag: 'T', pran: 110016825057);
        $this->seedPran('AP/NPS/15/0002', saveFlag: 'T', pran: 110034821858);

        Livewire::actingAs($this->makeUser('admin', 'A'))
            ->test(AssignPran::class)
            ->call('toggleSelectAll')
            ->assertSet('selected', ['AP/NPS/15/0001', 'AP/NPS/15/0002']);
    }

    /* ---- cross-operator visibility (regression: every test above acts as an ADMIN, who
       bypasses OwnedByUserScope — so none of them could ever catch the scope leaking into
       this screen and blanking the account columns) ---- */

    public function test_the_pending_list_shows_account_details_to_a_non_admin_operator(): void
    {
        $this->seedAccount('AP/NPS/15/0001', 'ODI TAMIN', userId: 'someoneelse');
        $this->seedAccount('AP/NPS/15/0002', 'LEMGE PANSA', userId: null);   // migrated, owner-less
        $this->seedPran('AP/NPS/15/0001', saveFlag: 'T', pran: 110016825057);
        $this->seedPran('AP/NPS/15/0002', saveFlag: 'T', pran: 110034821858);

        Livewire::actingAs($this->makeOperator())
            ->test(AssignPran::class)
            ->assertSee('ODI TAMIN')       // owned by another operator
            ->assertSee('LEMGE PANSA')     // owned by nobody
            ->assertSee('L.D.C')           // designation, via subscriber.designationMaster
            ->assertSee('DDO Alpha')       // ddo, via subscriber.ddo
            ->assertSee('AP/HEALTH');      // department, resolved from nameofdept
    }

    public function test_a_non_admin_operator_can_search_an_account_owned_by_someone_else(): void
    {
        $this->seedAccount('AP/NPS/15/0001', 'ODI TAMIN', userId: 'someoneelse');

        Livewire::actingAs($this->makeOperator())
            ->test(AssignPran::class)
            ->set('search', 'odi')
            ->assertSee('AP/NPS/15/0001')
            ->assertSee('ODI TAMIN');
    }

    public function test_a_non_admin_operator_can_assign_a_pran_to_another_operators_account(): void
    {
        $this->seedAccount('AP/NPS/15/0001', 'ODI TAMIN', userId: 'someoneelse');

        Livewire::actingAs($this->makeOperator())
            ->test(AssignPran::class)
            ->call('selectAccount', 'AP/NPS/15/0001')
            ->assertSet('selectedAccountNo', 'AP/NPS/15/0001')
            ->set('pranNo', '110016825057')->set('confirmPranNo', '110016825057')
            ->set('pranAllotmentDate', '2024-01-01')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('pran_no', [
            'account_no' => 'AP/NPS/15/0001',
            'pran_no' => 110016825057,
            'user_id' => 'operator',   // the PRAN records who assigned it, the account keeps its own owner
        ]);
    }

    public function test_a_closed_account_is_still_rejected_for_a_non_admin_operator(): void
    {
        $this->seedAccount('AP/NPS/15/0009', isactive: false, userId: 'someoneelse');

        Livewire::actingAs($this->makeOperator())
            ->test(AssignPran::class)
            ->call('selectAccount', 'AP/NPS/15/0009')
            ->assertSet('selectedAccountNo', '');   // lifting ownership must NOT lift the status rules
    }
}
