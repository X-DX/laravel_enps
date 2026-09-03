<?php

namespace Tests\Feature\CentralRegister;

use App\Livewire\CentralRegister\EntryCr;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Entry CR (menu 201) — CR generation. Focuses on generate(): auto numbering, attach-to-existing,
 * the counter, and the first_receipt CR→FZ flag flip.
 */
class EntryCrTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['user_permission', 'role_permission', 'permissions', 'roles', 'user_account', 'first_receipt', 'purpose_master_codes', 'bank_master', 'loc_master', 'treasury_master', 'ddo_master', 'central_reg', 'central_reg_entry_date', 'counter_centralreg'] as $t) {
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
            $t->string('flag_p', 2)->nullable();
            $t->string('order_no')->nullable();
            $t->string('draft_no')->nullable();
            $t->double('amount')->nullable();
            $t->string('order_date')->nullable();
            $t->string('draft_date')->nullable();
            $t->string('bank_name')->nullable();
            $t->string('purpose')->nullable();
        });
        Schema::create('central_reg_entry_date', function (Blueprint $t) {
            $t->bigInteger('receipt_no');
            $t->string('draft_no')->nullable();
            $t->date('cen_entry_date')->nullable();
            $t->bigInteger('cr_sl_no')->nullable();
            $t->double('amount')->nullable();
        });
        Schema::create('counter_centralreg', function (Blueprint $t) {
            $t->bigInteger('cunterid')->primary();
            $t->bigInteger('centregno');
            $t->bigInteger('recept_no');
            $t->bigInteger('receipt_reg_no')->default(0);
        });

        Permission::create(['key' => 'entrysection.entry_cr', 'name' => 'Entry CR', 'group' => 'entrysection', 'legacy_menu_id' => 201]);

        DB::table('purpose_master_codes')->insert(['pid' => 'D01', 'purpose' => 'CONTRIBUTION FOR JAN']);
        DB::table('bank_master')->insert(['bank_code' => 10, 'bank_name' => 'SBI', 'branch_name' => 'Main']);
        DB::table('loc_master')->insert(['loc_code' => 1, 'loc_name' => 'Itanagar']);
        DB::table('treasury_master')->insert(['treasury_code' => '01', 'treasury_name' => 'Itanagar Treasury']);
        DB::table('ddo_master')->insert(['ddo_sl' => 2, 'ddo_name' => 'DDO Alpha', 'loc_code' => 1, 'treasury_code' => '01']);
        DB::table('counter_centralreg')->insert(['cunterid' => 1, 'centregno' => 100, 'recept_no' => 500, 'receipt_reg_no' => 0]);
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

    private function seedReceipt(string $flag = 'CR', string $userId = 'admin'): int
    {
        return DB::table('first_receipt')->insertGetId([
            'draft_no' => '123', 'draft_date' => '2024-01-05',
            'order_no' => 'ORD/1', 'order_date' => '2024-01-01',
            'amount' => 1000, 'date_of_entry' => '2024-01-01',
            'flag' => $flag, 'ddocode' => 2, 'type' => 'R',
            'draw_bank_code' => 10, 'purpose' => 'D01',
            'contribution_type' => 'SC', 'pension_type' => 'N',
            'user_id' => $userId,
        ]);
    }

    public function test_the_route_is_forbidden_without_the_permission(): void
    {
        $this->actingAs($this->makeUser('staff', 'S'))->get('/central-register/entry')->assertForbidden();
    }

    public function test_auto_generate_numbers_the_batch_and_flips_the_flag(): void
    {
        $a = $this->seedReceipt();
        $b = $this->seedReceipt();

        Livewire::actingAs($this->makeUser('admin', 'A'))
            ->test(EntryCr::class)
            ->set('selected', [(string) $a, (string) $b])
            ->call('generate')
            ->assertSet('selected', []);

        // Both receipts are now CR-generated.
        $this->assertSame('FZ', DB::table('first_receipt')->where('sl_no', $a)->value('flag'));
        $this->assertSame('FZ', DB::table('first_receipt')->where('sl_no', $b)->value('flag'));

        // Two central_reg rows share the auto receipt number 500, with serials 100 and 101.
        $this->assertSame(2, DB::table('central_reg')->where('receipt_no', 500)->count());
        $this->assertDatabaseHas('central_reg', ['sl_no' => 100, 'receipt_no' => 500, 'first_receipt_sl_no' => $a]);
        $this->assertDatabaseHas('central_reg', ['sl_no' => 101, 'receipt_no' => 500, 'first_receipt_sl_no' => $b]);
        $this->assertSame(2, DB::table('central_reg_entry_date')->where('receipt_no', 500)->count());

        // Counter: serial +2 (one per receipt), receipt number +1.
        $counter = DB::table('counter_centralreg')->where('cunterid', 1)->first();
        $this->assertSame(102, (int) $counter->centregno);
        $this->assertSame(501, (int) $counter->recept_no);
    }

    public function test_attach_reuses_an_existing_receipt_number_without_bumping_it(): void
    {
        // An existing receipt (777) that belongs to us.
        DB::table('central_reg')->insert(['sl_no' => 50, 'receipt_no' => 777, 'user_id' => 'admin', 'order_no' => 'X', 'draft_no' => 'Y', 'amount' => 1]);
        $c = $this->seedReceipt();

        Livewire::actingAs($this->makeUser('admin', 'A'))
            ->test(EntryCr::class)
            ->set('selected', [(string) $c])
            ->set('attachReceiptNo', '777')
            ->call('generate')
            ->assertSet('selected', []);

        // A new central_reg row uses receipt 777, with the next serial (100).
        $this->assertDatabaseHas('central_reg', ['sl_no' => 100, 'receipt_no' => 777, 'first_receipt_sl_no' => $c]);
        $this->assertSame('FZ', DB::table('first_receipt')->where('sl_no', $c)->value('flag'));

        // Counter: serial advanced by 1, but the receipt number was NOT consumed.
        $counter = DB::table('counter_centralreg')->where('cunterid', 1)->first();
        $this->assertSame(101, (int) $counter->centregno);
        $this->assertSame(500, (int) $counter->recept_no);
    }

    public function test_an_invalid_attach_number_is_refused_and_writes_nothing(): void
    {
        $c = $this->seedReceipt();

        Livewire::actingAs($this->makeUser('admin', 'A'))
            ->test(EntryCr::class)
            ->set('selected', [(string) $c])
            ->set('attachReceiptNo', '999999')   // no such receipt
            ->call('generate');

        // Nothing happened: still pending, no central_reg row, counter untouched.
        $this->assertSame('CR', DB::table('first_receipt')->where('sl_no', $c)->value('flag'));
        $this->assertSame(0, DB::table('central_reg')->where('first_receipt_sl_no', $c)->count());
        $this->assertSame(100, (int) DB::table('counter_centralreg')->where('cunterid', 1)->value('centregno'));
    }

    public function test_the_attach_field_validates_against_our_own_receipts(): void
    {
        DB::table('central_reg')->insert(['sl_no' => 50, 'receipt_no' => 777, 'user_id' => 'admin', 'order_no' => 'X', 'draft_no' => 'Y', 'amount' => 1]);

        Livewire::actingAs($this->makeUser('admin', 'A'))
            ->test(EntryCr::class)
            ->set('attachReceiptNo', '777')
            ->assertSet('attachValid', true)
            ->set('attachReceiptNo', '999999')
            ->assertSet('attachValid', false);
    }
}
