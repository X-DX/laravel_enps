<?php

namespace Tests\Feature\Accounts;

use App\Livewire\Accounts\Subscribers;
use App\Models\Permission;
use App\Models\User;
use App\Services\AccountFinalizer;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

class FinalizeAccountTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['user_permission', 'role_permission', 'permissions', 'roles', 'user_account', 'allotment_accnt_no', 'account_sequence', 'ddo_master', 'designation_master', 'department', 'pran_no'] as $t) {
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
        Schema::create('account_sequence', function (Blueprint $t) {
            $t->string('dept_code', 10)->primary();
            $t->bigInteger('account_seq_no');
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
            $t->date('dob')->nullable();
            $t->bigInteger('designation')->nullable();
            $t->string('nameofdept')->nullable();
            $t->bigInteger('ddocode')->nullable();
            $t->char('pension_type', 1)->nullable();
            $t->string('account_no')->nullable();
            $t->char('save_flag', 1)->nullable();
            $t->date('finalize_date')->nullable();
            $t->string('user_id')->nullable();
            $t->bigInteger('single_mother_flag')->default(0);
            $t->bigInteger('closure_reason_id');
            $t->boolean('isactive')->default(true);
        });

        Permission::create(['key' => 'entrysection.issue_account', 'name' => 'Issue Account', 'group' => 'entrysection', 'legacy_menu_id' => 152]);
        Permission::create(['key' => 'entrysection.view_all_accounts', 'name' => 'View All Accounts', 'group' => 'entrysection', 'legacy_menu_id' => 154]);
        Permission::create(['key' => 'entrysection.pending_issue_accounts', 'name' => 'Pending Issue Accounts', 'group' => 'entrysection', 'legacy_menu_id' => 155]);
        Permission::create(['key' => 'entrysection.finalized_issued_account', 'name' => 'Finalized Issued Account', 'group' => 'entrysection', 'legacy_menu_id' => 156]);

        DB::table('account_sequence')->insert(['dept_code' => '15', 'account_seq_no' => 41]);
        DB::table('department')->insert(['dept_code' => '15', 'dept_name' => 'AP/HEALTH']);
        DB::table('designation_master')->insert(['designation_id' => 1738, 'designation' => 'L.D.C']);
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

    private function seedDraft(string $name, string $dept = '15', string $pension = 'N'): int
    {
        return DB::table('allotment_accnt_no')->insertGetId([
            'name' => $name,
            'nameofdept' => $dept,
            'pension_type' => $pension,
            'designation' => 1738,
            'ddocode' => 2,
            'user_id' => 'admin',
            'single_mother_flag' => 0,
            'closure_reason_id' => 0,
            'isactive' => true,
            'save_flag' => 'T',
            'account_no' => null,
        ]);
    }

    /* ---- the service (the safe counter) ---- */

    public function test_finalize_allots_a_number_and_marks_finalized_and_advances_the_counter(): void
    {
        $id = $this->seedDraft('Arup Roy');

        $done = app(AccountFinalizer::class)->finalizeMany([$id]);

        $this->assertCount(1, $done);
        $this->assertSame('AP/NPS/15/0042', $done[0]['account_no']);   // counter was 41 → 42
        $this->assertDatabaseHas('allotment_accnt_no', [
            'id' => $id, 'account_no' => 'AP/NPS/15/0042', 'save_flag' => 'F',
        ]);
        $this->assertSame(42, (int) DB::table('account_sequence')->where('dept_code', '15')->value('account_seq_no'));
    }

    public function test_a_ups_subscriber_gets_the_ups_prefix(): void
    {
        $id = $this->seedDraft('Ups Person', '15', 'U');

        $done = app(AccountFinalizer::class)->finalizeMany([$id]);

        $this->assertSame('AP/UPS/15/0042', $done[0]['account_no']);
    }

    public function test_two_drafts_in_the_same_department_get_consecutive_numbers_no_collision(): void
    {
        $a = $this->seedDraft('Person A');
        $b = $this->seedDraft('Person B');

        $done = app(AccountFinalizer::class)->finalizeMany([$a, $b]);

        // The counter increments per finalize, so no two ever share a number.
        $this->assertSame('AP/NPS/15/0042', $done[0]['account_no']);
        $this->assertSame('AP/NPS/15/0043', $done[1]['account_no']);
    }

    public function test_it_skips_rows_that_are_already_finalized(): void
    {
        $id = $this->seedDraft('Done Already');
        DB::table('allotment_accnt_no')->where('id', $id)->update(['save_flag' => 'F', 'account_no' => 'AP/NPS/15/0001']);

        $done = app(AccountFinalizer::class)->finalizeMany([$id]);

        $this->assertCount(0, $done);   // skipped — not a draft
        $this->assertSame(41, (int) DB::table('account_sequence')->where('dept_code', '15')->value('account_seq_no'));   // counter untouched
    }

    /* ---- the list action ---- */

    public function test_admin_can_finalize_selected_drafts_from_the_list(): void
    {
        $a = $this->seedDraft('Person A');
        $b = $this->seedDraft('Person B');

        Livewire::actingAs($this->makeUser('admin', 'A'))
            ->test(Subscribers::class)
            ->set('selected', [(string) $a, (string) $b])
            ->call('finalize')
            ->assertSet('selected', []);   // the selection clears after finalizing

        $this->assertDatabaseHas('allotment_accnt_no', ['id' => $a, 'save_flag' => 'F', 'account_no' => 'AP/NPS/15/0042']);
        $this->assertDatabaseHas('allotment_accnt_no', ['id' => $b, 'save_flag' => 'F', 'account_no' => 'AP/NPS/15/0043']);
    }

    public function test_finalize_with_nothing_selected_changes_nothing(): void
    {
        $id = $this->seedDraft('Still Draft');

        Livewire::actingAs($this->makeUser('admin', 'A'))
            ->test(Subscribers::class)
            ->call('finalize');

        $this->assertDatabaseHas('allotment_accnt_no', ['id' => $id, 'save_flag' => 'T', 'account_no' => null]);
    }

    public function test_toggle_select_all_selects_only_the_pending_rows(): void
    {
        $a = $this->seedDraft('Person A');
        $b = $this->seedDraft('Person B');
        $c = $this->seedDraft('Person C');
        DB::table('allotment_accnt_no')->where('id', $c)->update(['save_flag' => 'F', 'account_no' => 'AP/NPS/15/0001']);

        Livewire::actingAs($this->makeUser('admin', 'A'))
            ->test(Subscribers::class)
            ->call('toggleSelectAll')
            ->assertSet('selected', [(string) $a, (string) $b]);   // the finalized one (c) is excluded
    }

    /* ---- delete (pending drafts only) ---- */

    public function test_delete_selected_removes_pending_drafts(): void
    {
        $a = $this->seedDraft('Person A');
        $b = $this->seedDraft('Person B');

        Livewire::actingAs($this->makeUser('admin', 'A'))
            ->test(Subscribers::class, ['mode' => 'pending'])
            ->set('selected', [(string) $a, (string) $b])
            ->call('deleteSelected')
            ->assertSet('selected', []);

        $this->assertDatabaseMissing('allotment_accnt_no', ['id' => $a]);
        $this->assertDatabaseMissing('allotment_accnt_no', ['id' => $b]);
    }

    public function test_delete_never_removes_a_finalized_account(): void
    {
        $draft = $this->seedDraft('Still Draft');
        $final = $this->seedDraft('Finalized One');
        DB::table('allotment_accnt_no')->where('id', $final)->update(['save_flag' => 'F', 'account_no' => 'AP/NPS/15/0001']);

        Livewire::actingAs($this->makeUser('admin', 'A'))
            ->test(Subscribers::class, ['mode' => 'pending'])
            ->set('selected', [(string) $draft, (string) $final])   // both ticked
            ->call('deleteSelected');

        $this->assertDatabaseMissing('allotment_accnt_no', ['id' => $draft]);   // draft gone
        $this->assertDatabaseHas('allotment_accnt_no', ['id' => $final]);       // finalized untouched
    }
}
