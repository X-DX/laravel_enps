<?php

namespace Tests\Feature\FirstRegister;

use App\Livewire\FirstRegister\FirstEntries;
use App\Livewire\FirstRegister\FirstEntry;
use App\Models\FirstReceipt;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * First Register 6.1a: the entry form (save + duplicate guard + location→DDO cascade) and the
 * three list screens (View All / Pending / Finalized).
 */
class FirstRegisterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['user_permission', 'role_permission', 'permissions', 'roles', 'user_account', 'first_receipt', 'purpose_master_codes', 'bank_master', 'loc_master', 'treasury_master', 'ddo_master'] as $t) {
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
        Schema::create('purpose_master_codes', function (Blueprint $t) {
            $t->string('pid', 10)->primary();
            $t->string('purpose');
        });
        Schema::create('bank_master', function (Blueprint $t) {
            $t->bigIncrements('bank_code');
            $t->string('bank_name')->nullable();
            $t->string('branch_name')->nullable();
        });
        Schema::create('loc_master', function (Blueprint $t) {
            $t->bigIncrements('loc_code');
            $t->string('loc_name')->nullable();
            $t->bigInteger('dist_code')->nullable();
        });
        Schema::create('treasury_master', function (Blueprint $t) {
            $t->string('treasury_code', 10)->primary();
            $t->string('treasury_name', 150);
            $t->bigInteger('dist_code')->nullable();
        });
        Schema::create('ddo_master', function (Blueprint $t) {
            $t->bigIncrements('ddo_sl');
            $t->string('ddo_name', 150)->nullable();
            $t->bigInteger('loc_code')->nullable();
            $t->string('treasury_code', 10)->nullable();
            $t->string('ddo_code', 7)->nullable();
        });
        Schema::create('first_receipt', function (Blueprint $t) {
            $t->bigIncrements('sl_no');
            $t->string('draft_no')->nullable();
            $t->date('draft_date')->nullable();
            $t->string('order_no')->nullable();
            $t->date('order_date')->nullable();
            $t->decimal('amount', 15, 2)->nullable();
            $t->date('date_of_entry')->nullable();
            $t->string('flag', 3)->nullable();
            $t->bigInteger('ddocode')->nullable();
            $t->char('type', 1)->nullable();
            $t->bigInteger('draw_bank_code')->nullable();
            $t->string('purpose', 10)->nullable();
            $t->char('contribution_type', 2)->nullable();
            $t->char('pension_type', 1)->nullable();
            $t->string('user_id')->nullable();
            $t->date('finalize_date')->nullable();
        });

        Permission::create(['key' => 'entrysection.entry_first_register', 'name' => 'Entry First Register', 'group' => 'entrysection', 'legacy_menu_id' => 171]);
        Permission::create(['key' => 'entrysection.view_all_first_entries', 'name' => 'View All First Entries', 'group' => 'entrysection', 'legacy_menu_id' => 172]);
        Permission::create(['key' => 'entrysection.pending_first_entry', 'name' => 'Pending First Entry', 'group' => 'entrysection', 'legacy_menu_id' => 173]);
        Permission::create(['key' => 'entrysection.finalized_first_entry', 'name' => 'Finalized First Entry', 'group' => 'entrysection', 'legacy_menu_id' => 174]);

        DB::table('purpose_master_codes')->insert(['pid' => 'D01', 'purpose' => 'DEDUCTION FOR JAN']);
        DB::table('bank_master')->insert(['bank_code' => 10, 'bank_name' => 'SBI', 'branch_name' => 'Main']);
        DB::table('loc_master')->insert(['loc_code' => 1, 'loc_name' => 'Itanagar']);
        DB::table('treasury_master')->insert(['treasury_code' => '01', 'treasury_name' => 'Itanagar Treasury']);
        DB::table('ddo_master')->insert(['ddo_sl' => 2, 'ddo_name' => 'DDO Alpha', 'loc_code' => 1, 'treasury_code' => '01']);
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

    private function validForm(\Livewire\Features\SupportTesting\Testable $c): \Livewire\Features\SupportTesting\Testable
    {
        return $c->set('treasuryCode', '01')
            ->set('ddocode', '2')
            ->set('orderNo', 'ORD/2024/001')
            ->set('orderDate', '2024-01-01')
            ->set('draftNo', '123456')
            ->set('draftDate', '2024-01-05')
            ->set('amount', '50000')
            ->set('contributionType', 'SC')
            ->set('pensionType', 'N')
            ->set('drawBankCode', '10')
            ->set('purpose', 'D01');
    }

    private function seedReceipt(string $flag = 'T', string $draftNo = '999', string $draftDate = '2024-02-01'): int
    {
        return DB::table('first_receipt')->insertGetId([
            'draft_no' => $draftNo,
            'draft_date' => $draftDate,
            'order_no' => 'ORD/X',
            'order_date' => '2024-01-01',
            'amount' => 1000,
            'date_of_entry' => '2024-01-01',
            'flag' => $flag,
            'ddocode' => 2,
            'type' => 'R',
            'draw_bank_code' => 10,
            'purpose' => 'D01',
            'contribution_type' => 'SC',
            'pension_type' => 'N',
            'user_id' => 'admin',
        ]);
    }

    /* ---- entry form ---- */

    public function test_the_entry_route_is_forbidden_without_permission(): void
    {
        $this->actingAs($this->makeUser('staff', 'S'))->get('/first-register/entry')->assertForbidden();
    }

    public function test_choosing_a_treasury_shows_only_its_ddos(): void
    {
        DB::table('ddo_master')->insert(['ddo_sl' => 3, 'ddo_name' => 'DDO Beta', 'treasury_code' => '02']);

        Livewire::actingAs($this->makeUser('admin', 'A'))
            ->test(FirstEntry::class)
            ->set('treasuryCode', '01')
            ->assertSee('DDO Alpha')     // under treasury 01
            ->assertDontSee('DDO Beta')  // under treasury 02
            ->set('ddocode', '2')
            ->set('treasuryCode', '02')  // switching treasury...
            ->assertSet('ddocode', '');  // ...clears the stale DDO
    }

    public function test_ticking_draft_auto_selects_double_contribution(): void
    {
        Livewire::actingAs($this->makeUser('admin', 'A'))
            ->test(FirstEntry::class)
            ->set('isDraft', true)
            ->assertSet('contributionType', 'DC')   // draft → double
            ->set('isDraft', false)
            ->assertSet('contributionType', '');    // receipt → cleared
    }

    public function test_it_saves_an_entry_as_pending_with_system_fields(): void
    {
        $this->validForm(Livewire::actingAs($this->makeUser('admin', 'A'))->test(FirstEntry::class))
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('draftNo', '');   // form clears

        $this->assertDatabaseHas('first_receipt', [
            'draft_no' => '123456',
            'order_no' => 'ORD/2024/001',
            'amount' => 50000,
            'ddocode' => 2,
            'type' => 'R',            // isDraft = false → Receipt
            'contribution_type' => 'SC',
            'pension_type' => 'N',
            'draw_bank_code' => 10,
            'purpose' => 'D01',
            'flag' => 'T',            // pending
            'user_id' => 'admin',
        ]);
    }

    public function test_a_duplicate_draft_needs_save_anyway(): void
    {
        $this->seedReceipt(flag: 'T', draftNo: '123456', draftDate: '2024-01-05');   // an existing one

        $component = $this->validForm(Livewire::actingAs($this->makeUser('admin', 'A'))->test(FirstEntry::class));

        $component->call('save')
            ->assertHasNoErrors()
            ->assertSet('showForceSave', true);
        $this->assertSame(1, DB::table('first_receipt')->count());   // NOT saved yet

        $component->call('forceSave');
        $this->assertSame(2, DB::table('first_receipt')->count());   // override worked
    }

    /* ---- lists ---- */

    public function test_the_list_route_is_forbidden_without_permission(): void
    {
        $this->actingAs($this->makeUser('staff', 'S'))->get('/first-register')->assertForbidden();
    }

    public function test_pending_mode_shows_only_pending_and_finalized_shows_fz_cr(): void
    {
        $this->seedReceipt(flag: 'T', draftNo: 'PEND1');
        $this->seedReceipt(flag: 'CR', draftNo: 'FINAL1');
        $this->seedReceipt(flag: 'FZ', draftNo: 'FINAL2');

        $admin = $this->makeUser('admin', 'A');

        Livewire::actingAs($admin)->test(FirstEntries::class, ['mode' => 'pending'])
            ->assertSee('PEND1')->assertDontSee('FINAL1')->assertDontSee('FINAL2');

        Livewire::actingAs($admin)->test(FirstEntries::class, ['mode' => 'finalized'])
            ->assertSee('FINAL1')->assertSee('FINAL2')->assertDontSee('PEND1');
    }

    public function test_view_all_status_filter_narrows_the_list(): void
    {
        $this->seedReceipt(flag: 'T', draftNo: 'PEND1');
        $this->seedReceipt(flag: 'CR', draftNo: 'FINAL1');

        Livewire::actingAs($this->makeUser('admin', 'A'))->test(FirstEntries::class)
            ->set('status', 'T')
            ->assertSee('PEND1')->assertDontSee('FINAL1');
    }

    /* ---- pending actions (6.1b) ---- */

    public function test_finalize_selected_flips_pending_to_cr(): void
    {
        $a = $this->seedReceipt(flag: 'T', draftNo: 'A1');
        $b = $this->seedReceipt(flag: 'T', draftNo: 'B1');

        Livewire::actingAs($this->makeUser('admin', 'A'))
            ->test(FirstEntries::class, ['mode' => 'pending'])
            ->set('selected', [(string) $a, (string) $b])
            ->call('finalizeSelected')
            ->assertSet('selected', []);

        $this->assertSame(2, DB::table('first_receipt')->where('flag', 'CR')->whereNotNull('finalize_date')->count());
    }

    public function test_delete_selected_removes_only_draft_entries(): void
    {
        $draft = $this->seedReceipt(flag: 'T', draftNo: 'D1');
        $final = $this->seedReceipt(flag: 'CR', draftNo: 'F1');

        Livewire::actingAs($this->makeUser('admin', 'A'))
            ->test(FirstEntries::class, ['mode' => 'pending'])
            ->set('selected', [(string) $draft, (string) $final])
            ->call('deleteSelected');

        $this->assertDatabaseMissing('first_receipt', ['sl_no' => $draft]);   // draft gone
        $this->assertDatabaseHas('first_receipt', ['sl_no' => $final]);        // finalized kept
    }

    public function test_toggle_select_all_selects_the_pages_pending_entries(): void
    {
        $a = $this->seedReceipt(flag: 'T', draftNo: 'A1');
        $b = $this->seedReceipt(flag: 'T', draftNo: 'B1');

        Livewire::actingAs($this->makeUser('admin', 'A'))
            ->test(FirstEntries::class, ['mode' => 'pending'])
            ->call('toggleSelectAll')
            ->assertSet('selected', [(string) $b, (string) $a]);   // newest sl_no first
    }

    public function test_the_edit_route_is_forbidden_without_permission(): void
    {
        $id = $this->seedReceipt();

        $this->actingAs($this->makeUser('staff', 'S'))->get("/first-register/{$id}/edit")->assertForbidden();
    }

    public function test_editing_a_pending_entry_updates_it(): void
    {
        $id = $this->seedReceipt(flag: 'T', draftNo: '111', draftDate: '2024-03-03');
        $receipt = FirstReceipt::find($id);

        Livewire::actingAs($this->makeUser('admin', 'A'))
            ->test(FirstEntry::class, ['firstReceipt' => $receipt])
            ->assertSet('editingId', $id)
            ->assertSet('draftNo', '111')
            ->set('amount', '77777')
            ->set('contributionType', 'DC')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('first_receipt', ['sl_no' => $id, 'amount' => 77777, 'contribution_type' => 'DC', 'flag' => 'T']);
    }
}
