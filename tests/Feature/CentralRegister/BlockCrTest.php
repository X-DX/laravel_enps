<?php

namespace Tests\Feature\CentralRegister;

use App\Livewire\CentralRegister\BlockCr;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Block / Blocked CR (menu 205–206): freezing a Central Register entry by its CR No, with a reason,
 * and unblocking / editing the reason. Guarded + owner-scoped; never deletes.
 */
class BlockCrTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['user_permission', 'role_permission', 'permissions', 'roles', 'user_account', 'first_receipt', 'ddo_master', 'treasury_master', 'loc_master', 'central_reg'] as $t) {
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
        Schema::create('loc_master', function (Blueprint $t) {
            $t->bigIncrements('loc_code');
            $t->string('loc_name')->nullable();
        });
        Schema::create('treasury_master', function (Blueprint $t) {
            $t->string('treasury_code', 10)->primary();
            $t->string('treasury_name', 150);
        });
        Schema::create('ddo_master', function (Blueprint $t) {
            $t->bigIncrements('ddo_sl');
            $t->string('ddo_name', 150)->nullable();
            $t->bigInteger('loc_code')->nullable();
            $t->string('treasury_code', 10)->nullable();
        });
        Schema::create('first_receipt', function (Blueprint $t) {
            $t->bigIncrements('sl_no');
            $t->string('draft_no')->nullable();
            $t->string('order_no')->nullable();
            $t->decimal('amount', 15, 2)->nullable();
            $t->string('flag', 3)->nullable();
            $t->bigInteger('ddocode')->nullable();
            $t->string('user_id')->nullable();
        });
        Schema::create('central_reg', function (Blueprint $t) {
            $t->bigInteger('sl_no')->primary();
            $t->bigInteger('receipt_no')->nullable();
            $t->bigInteger('first_receipt_sl_no')->nullable();
            $t->string('user_id')->nullable();
            $t->boolean('blocked_cr')->default(false);
            $t->text('blocked_reason')->nullable();
            $t->date('blocked_date')->nullable();
            $t->string('blocked_by_user')->nullable();
            $t->string('order_no')->nullable();
            $t->string('draft_no')->nullable();
            $t->double('amount')->nullable();
        });

        foreach ([
            ['entrysection.block_cr_nos', 'Block CR Nos', 205],
            ['entrysection.blocked_cr_lists', 'Blocked CR Lists', 206],
        ] as [$key, $name, $menu]) {
            Permission::create(['key' => $key, 'name' => $name, 'group' => 'entrysection', 'legacy_menu_id' => $menu]);
        }

        DB::table('treasury_master')->insert(['treasury_code' => '01', 'treasury_name' => 'Itanagar Treasury']);
        DB::table('loc_master')->insert(['loc_code' => 1, 'loc_name' => 'Itanagar']);
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

    private function seedCr(int $slNo, string $userId, bool $blocked, ?string $reason = null): void
    {
        $fr = DB::table('first_receipt')->insertGetId([
            'draft_no' => 'D' . $slNo, 'order_no' => 'ORD/' . $slNo, 'amount' => 1000, 'flag' => 'FZ', 'ddocode' => 2, 'user_id' => $userId,
        ]);

        DB::table('central_reg')->insert([
            'sl_no' => $slNo, 'receipt_no' => 39000 + $slNo, 'first_receipt_sl_no' => $fr, 'user_id' => $userId,
            'blocked_cr' => $blocked, 'blocked_reason' => $reason, 'blocked_date' => $blocked ? '2026-01-01' : null,
            'amount' => 1000, 'draft_no' => 'D' . $slNo, 'order_no' => 'ORD/' . $slNo,
        ]);
    }

    public function test_the_block_route_is_forbidden_without_permission(): void
    {
        $this->actingAs($this->makeUser('staff', 'S'))->get('/central-register/block')->assertForbidden();
        $this->actingAs($this->makeUser('staff2', 'S'))->get('/central-register/blocked')->assertForbidden();
    }

    public function test_block_mode_lists_unblocked_and_blocked_mode_lists_blocked(): void
    {
        $this->seedCr(100, 'admin', blocked: false);
        $this->seedCr(200, 'admin', blocked: true, reason: 'Under objection');

        Livewire::actingAs($this->makeUser('admin', 'A'))
            ->test(BlockCr::class, ['mode' => 'block'])
            ->assertSee('100')
            ->assertDontSee('Under objection');   // blocked one not on the block screen

        Livewire::actingAs(User::find('admin'))
            ->test(BlockCr::class, ['mode' => 'blocked'])
            ->assertSee('200')
            ->assertSee('Under objection');
    }

    public function test_blocking_freezes_the_entry_with_a_reason(): void
    {
        $this->seedCr(100, 'admin', blocked: false);

        Livewire::actingAs($this->makeUser('admin', 'A'))
            ->test(BlockCr::class, ['mode' => 'block'])
            ->call('openBlock', 100)
            ->assertSet('showModal', true)
            ->set('reason', 'Amount disputed')
            ->call('save')
            ->assertSet('showModal', false);

        $this->assertDatabaseHas('central_reg', ['sl_no' => 100, 'blocked_cr' => true, 'blocked_reason' => 'Amount disputed', 'blocked_by_user' => 'admin']);
    }

    public function test_blocking_requires_a_reason(): void
    {
        $this->seedCr(100, 'admin', blocked: false);

        Livewire::actingAs($this->makeUser('admin', 'A'))
            ->test(BlockCr::class, ['mode' => 'block'])
            ->call('openBlock', 100)
            ->set('reason', '')
            ->call('save')
            ->assertHasErrors(['reason']);

        $this->assertDatabaseHas('central_reg', ['sl_no' => 100, 'blocked_cr' => false]);
    }

    public function test_unblock_clears_the_flags(): void
    {
        $this->seedCr(200, 'admin', blocked: true, reason: 'Old reason');

        Livewire::actingAs($this->makeUser('admin', 'A'))
            ->test(BlockCr::class, ['mode' => 'blocked'])
            ->call('unblock', 200);

        $this->assertDatabaseHas('central_reg', ['sl_no' => 200, 'blocked_cr' => false, 'blocked_reason' => null, 'blocked_by_user' => null]);
    }

    public function test_edit_reason_updates_without_unblocking(): void
    {
        $this->seedCr(200, 'admin', blocked: true, reason: 'Old reason');

        Livewire::actingAs($this->makeUser('admin', 'A'))
            ->test(BlockCr::class, ['mode' => 'blocked'])
            ->call('openEdit', 200)
            ->assertSet('reason', 'Old reason')
            ->set('reason', 'New reason')
            ->call('save');

        $this->assertDatabaseHas('central_reg', ['sl_no' => 200, 'blocked_cr' => true, 'blocked_reason' => 'New reason']);
    }

    public function test_a_non_admin_cannot_block_another_operators_entry(): void
    {
        $this->seedCr(300, 'op2', blocked: false);   // owned by someone else

        $op1 = $this->makeUser('op1', 'S');
        $this->grant('op1', 'entrysection.block_cr_nos');

        Livewire::actingAs($op1)
            ->test(BlockCr::class, ['mode' => 'block'])
            ->call('openBlock', 300)
            ->set('reason', 'Trying to block')
            ->call('save');

        // Untouched — the guarded update is owner-scoped.
        $this->assertDatabaseHas('central_reg', ['sl_no' => 300, 'blocked_cr' => false]);
    }
}
