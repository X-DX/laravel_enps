<?php

namespace Tests\Feature\CentralRegister;

use App\Livewire\CentralRegister\CrEntries;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Central Register lists (menu 202–204): View All / Pending / Finalized, mode-driven, showing
 * each finalized row's CR Receipt No from central_reg.
 */
class CrEntriesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['user_permission', 'role_permission', 'permissions', 'roles', 'user_account', 'first_receipt', 'purpose_master_codes', 'bank_master', 'loc_master', 'treasury_master', 'ddo_master', 'central_reg'] as $t) {
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
            $t->string('other_purpose', 150)->nullable();
            $t->char('contribution_type', 2)->nullable();
            $t->char('pension_type', 1)->nullable();
            $t->string('user_id')->nullable();
            $t->date('finalize_date')->nullable();
        });
        Schema::create('central_reg', function (Blueprint $t) {
            $t->bigInteger('sl_no')->primary();
            $t->bigInteger('receipt_no');
            $t->bigInteger('first_receipt_sl_no')->nullable();
            $t->string('user_id')->nullable();
        });

        foreach ([
            ['entrysection.view_all_cr_entries', 'View All CR Entries', 202],
            ['entrysection.pending_cr_entries', 'Pending CR Entries', 203],
            ['entrysection.finalized_cr_entries', 'Finalized CR Entries', 204],
            ['entrysection.entry_first_register', 'Entry First Register', 171],
        ] as [$key, $name, $menu]) {
            Permission::create(['key' => $key, 'name' => $name, 'group' => 'entrysection', 'legacy_menu_id' => $menu]);
        }

        DB::table('purpose_master_codes')->insert(['pid' => 'D01', 'purpose' => 'CONTRIBUTION FOR JAN']);
        DB::table('bank_master')->insert(['bank_code' => 10, 'bank_name' => 'SBI', 'branch_name' => 'Main']);
        DB::table('loc_master')->insert(['loc_code' => 1, 'loc_name' => 'Itanagar']);
        DB::table('treasury_master')->insert(['treasury_code' => '01', 'treasury_name' => 'Itanagar Treasury']);
        DB::table('ddo_master')->insert(['ddo_sl' => 2, 'ddo_name' => 'DDO Alpha', 'loc_code' => 1, 'treasury_code' => '01']);
    }

    private function makeUser(string $userId, string $roleFlag): User
    {
        DB::table('user_account')->insert([
            'user_id' => $userId, 'username' => strtoupper($userId), 'password' => 'x',
            'role_flag' => $roleFlag, 'user_status' => 1, 'first_login' => 1, 'last_pwd_change' => now()->toDateString(),
        ]);

        return User::find($userId);
    }

    private function grant(string $userId, string $key): void
    {
        $pid = DB::table('permissions')->where('key', $key)->value('id');
        DB::table('user_permission')->insert(['user_id' => $userId, 'permission_id' => $pid]);
    }

    private function seedReceipt(string $flag, string $draftNo, string $userId = 'admin'): int
    {
        return DB::table('first_receipt')->insertGetId([
            'draft_no' => $draftNo, 'draft_date' => '2024-01-05', 'order_no' => 'ORD/1', 'order_date' => '2024-01-01',
            'amount' => 1000, 'date_of_entry' => '2024-01-01', 'flag' => $flag, 'ddocode' => 2, 'type' => 'R',
            'draw_bank_code' => 10, 'purpose' => 'D01', 'contribution_type' => 'SC', 'pension_type' => 'N', 'user_id' => $userId,
        ]);
    }

    public function test_the_route_is_forbidden_without_the_permission(): void
    {
        $this->actingAs($this->makeUser('staff', 'S'))->get('/central-register')->assertForbidden();
    }

    public function test_view_all_shows_both_cr_and_fz_stages(): void
    {
        $this->seedReceipt('CR', 'PENDING1');
        $fz = $this->seedReceipt('FZ', 'DONE1');
        DB::table('central_reg')->insert(['sl_no' => 100, 'receipt_no' => 39028, 'first_receipt_sl_no' => $fz, 'user_id' => 'admin']);

        Livewire::actingAs($this->makeUser('admin', 'A'))
            ->test(CrEntries::class, ['mode' => 'all'])
            ->assertSee('PENDING1')
            ->assertSee('DONE1')
            ->assertSee('39028')                       // CR receipt no on the finalized row
            ->assertSee('Pending at CR Generation')
            ->assertSee('Finalized (CR Generated)');
    }

    public function test_pending_mode_shows_only_cr(): void
    {
        $this->seedReceipt('CR', 'PENDING1');
        $this->seedReceipt('FZ', 'DONE1');

        Livewire::actingAs($this->makeUser('admin', 'A'))
            ->test(CrEntries::class, ['mode' => 'pending'])
            ->assertSee('PENDING1')
            ->assertDontSee('DONE1');
    }

    public function test_finalized_mode_shows_only_fz_with_its_cr_number(): void
    {
        $this->seedReceipt('CR', 'PENDING1');
        $fz = $this->seedReceipt('FZ', 'DONE1');
        DB::table('central_reg')->insert(['sl_no' => 100, 'receipt_no' => 39028, 'first_receipt_sl_no' => $fz, 'user_id' => 'admin']);

        Livewire::actingAs($this->makeUser('admin', 'A'))
            ->test(CrEntries::class, ['mode' => 'finalized'])
            ->assertSee('DONE1')
            ->assertSee('39028')
            ->assertDontSee('PENDING1');
    }

    public function test_view_all_status_filter_narrows_to_finalized(): void
    {
        $this->seedReceipt('CR', 'PENDING1');
        $this->seedReceipt('FZ', 'DONE1');

        Livewire::actingAs($this->makeUser('admin', 'A'))
            ->test(CrEntries::class, ['mode' => 'all'])
            ->set('status', 'FZ')
            ->assertSee('DONE1')
            ->assertDontSee('PENDING1');
    }

    public function test_entries_are_scoped_to_their_owner_for_non_admins(): void
    {
        $this->seedReceipt('CR', 'MINE', 'op1');
        $this->seedReceipt('CR', 'THEIRS', 'op2');

        $op1 = $this->makeUser('op1', 'S');
        $this->grant('op1', 'entrysection.view_all_cr_entries');   // a permitted non-admin operator

        Livewire::actingAs($op1)
            ->test(CrEntries::class, ['mode' => 'all'])
            ->assertSee('MINE')
            ->assertDontSee('THEIRS');
    }
}
