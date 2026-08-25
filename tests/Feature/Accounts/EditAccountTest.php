<?php

namespace Tests\Feature\Accounts;

use App\Livewire\Accounts\IssueAccount;
use App\Models\Permission;
use App\Models\Subscriber;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Edit reuses the IssueAccount component in "edit mode" (mount is given a Subscriber).
 * The key rule: a FINALIZED account freezes department + pension type (they define the
 * account number); a pending draft can change everything.
 */
class EditAccountTest extends TestCase
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
            $t->bigInteger('closure_reason_id');
            $t->boolean('isactive')->default(true);
        });

        Permission::create(['key' => 'entrysection.issue_account', 'name' => 'Issue Account', 'group' => 'entrysection', 'legacy_menu_id' => 152]);
        Permission::create(['key' => 'entrysection.edit_issued_account', 'name' => 'Edit issued Account', 'group' => 'entrysection', 'legacy_menu_id' => 153]);

        DB::table('retirement_year')->insert(['year' => 60]);
        DB::table('designation_master')->insert([
            ['designation_id' => 1738, 'designation' => 'L.D.C'],
            ['designation_id' => 1739, 'designation' => 'U.D.C'],
        ]);
        DB::table('department')->insert([
            ['dept_code' => '15', 'dept_name' => 'AP/HEALTH'],
            ['dept_code' => '20', 'dept_name' => 'AP/EDUCATION'],
        ]);
        DB::table('treasury_master')->insert(['treasury_code' => '01', 'treasury_name' => 'Itanagar', 'dist_code' => 1]);
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

    private function seedSubscriber(array $overrides = []): Subscriber
    {
        return Subscriber::create(array_merge([
            'name' => 'Arup Chandra Roy',
            'father_name' => 'Ganesh Roy',
            'mother_name' => 'Sona Roy',
            'single_mother_flag' => 0,
            'appnt_ord_no' => 'NIC/2026/001',
            'doapptorder' => '2021-01-01',
            'dob' => '1996-07-20',
            'doj' => '2021-01-01',
            'dor' => '2056-07-31',
            'designation' => 1738,
            'nameofdept' => '15',
            'pension_type' => 'N',
            'pay' => 60000,
            'ddocode' => 2,
            'name_nominee' => 'Sam',
            'starting_month' => '01',
            'starting_fin_year' => 2026,
            'save_flag' => 'T',
            'account_no' => null,
            'entry_date' => now()->toDateString(),
            'user_id' => 'admin',
            'flag_pt' => 'N',
            'closure_reason_id' => 0,
            'isactive' => true,
        ], $overrides));
    }

    public function test_the_edit_route_is_forbidden_without_the_permission(): void
    {
        // Owned by the acting user so route-model binding resolves — this isolates the
        // permission gate (403), not the per-user ownership scope (which would 404).
        $staff = $this->makeUser('staff', 'S');
        $sub = $this->seedSubscriber(['user_id' => 'staff']);

        $this->actingAs($staff)->get("/accounts/{$sub->id}/edit")->assertForbidden();
    }

    public function test_it_prefills_the_form_from_the_subscriber(): void
    {
        $sub = $this->seedSubscriber();

        Livewire::actingAs($this->makeUser('admin', 'A'))
            ->test(IssueAccount::class, ['subscriber' => $sub])
            ->assertSet('editingId', $sub->id)
            ->assertSet('isFinalized', false)
            ->assertSet('name', 'Arup Chandra Roy')
            ->assertSet('dob', '1996-07-20')
            ->assertSet('nameofdept', '15')
            ->assertSet('pension_type', 'N')
            ->assertSet('ddocode', '2')
            ->assertSet('treasury_code', '01');   // derived from the DDO
    }

    public function test_editing_a_pending_draft_can_change_everything(): void
    {
        $sub = $this->seedSubscriber();   // pending draft

        Livewire::actingAs($this->makeUser('admin', 'A'))
            ->test(IssueAccount::class, ['subscriber' => $sub])
            ->set('name', 'Renamed Person')
            ->set('nameofdept', '20')          // department CAN change on a draft
            ->set('pension_type', 'U')          // pension CAN change on a draft
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('allotment_accnt_no', [
            'id' => $sub->id,
            'name' => 'Renamed Person',
            'nameofdept' => '20',
            'pension_type' => 'U',
        ]);
    }

    public function test_edit_shows_the_ddo_even_when_its_treasury_is_not_linked(): void
    {
        // The common case in the real data: a DDO with no treasury_code.
        DB::table('ddo_master')->insert(['ddo_sl' => 9, 'ddo_name' => 'UNLINKED DDO', 'treasury_code' => null]);
        $sub = $this->seedSubscriber(['ddocode' => 9]);

        Livewire::actingAs($this->makeUser('admin', 'A'))
            ->test(IssueAccount::class, ['subscriber' => $sub])
            ->assertSet('ddocode', '9')
            ->assertSet('treasury_code', '')      // nothing to derive
            ->assertSee('UNLINKED DDO')           // ...but the DDO still shows as an option
            ->set('name', 'Edited Name')
            ->call('save')                        // and saving works without a treasury
            ->assertHasNoErrors();

        $this->assertDatabaseHas('allotment_accnt_no', ['id' => $sub->id, 'name' => 'Edited Name', 'ddocode' => 9]);
    }

    public function test_editing_a_finalized_account_freezes_department_and_pension(): void
    {
        $sub = $this->seedSubscriber(['save_flag' => 'F', 'account_no' => 'AP/NPS/15/0001']);

        Livewire::actingAs($this->makeUser('admin', 'A'))
            ->test(IssueAccount::class, ['subscriber' => $sub])
            ->assertSet('isFinalized', true)
            ->set('name', 'Renamed Person')
            ->set('nameofdept', '20')          // attempt to change — must be ignored
            ->set('pension_type', 'U')          // attempt to change — must be ignored
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('allotment_accnt_no', [
            'id' => $sub->id,
            'name' => 'Renamed Person',   // this one DID change
            'nameofdept' => '15',         // frozen
            'pension_type' => 'N',        // frozen
            'account_no' => 'AP/NPS/15/0001',   // untouched
        ]);
    }
}
