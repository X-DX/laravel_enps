<?php

namespace Tests\Feature\MasterData;

use App\Exports\BanksExport;
use App\Livewire\MasterData\Banks;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class BankTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['user_permission', 'role_permission', 'permissions', 'roles', 'user_account', 'bank_master'] as $t) {
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
        Schema::create('bank_master', function (Blueprint $t) {
            $t->bigInteger('bank_code')->primary();
            $t->string('bank_name', 30)->nullable();
            $t->string('branch_name', 30)->nullable();
        });

        Permission::create(['key' => 'adminsection.bank_entry', 'name' => 'Bank Entry', 'group' => 'adminsection', 'legacy_menu_id' => 4]);
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
        $this->actingAs($this->makeUser('staff', 'S'))->get('/master/banks')->assertForbidden();
    }

    public function test_an_admin_can_open_the_screen(): void
    {
        $this->actingAs($this->makeUser('admin', 'A'))->get('/master/banks')->assertOk()->assertSee('Bank Master');
    }

    public function test_it_creates_a_bank(): void
    {
        Livewire::actingAs($this->makeUser('admin', 'A'))
            ->test(Banks::class)
            ->call('create')
            ->set('bank_code', '5')
            ->set('bank_name', 'SBI')
            ->set('branch_name', 'Park Street')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('bank_master', ['bank_code' => 5, 'bank_name' => 'SBI', 'branch_name' => 'Park Street']);
    }

    public function test_it_rejects_a_blank_name_and_a_duplicate_code(): void
    {
        DB::table('bank_master')->insert(['bank_code' => 5, 'bank_name' => 'SBI', 'branch_name' => 'Park Street']);
        $admin = $this->makeUser('admin', 'A');

        Livewire::actingAs($admin)->test(Banks::class)
            ->set('bank_code', '6')->set('bank_name', '')->set('branch_name', 'Main')
            ->call('save')->assertHasErrors(['bank_name' => 'required']);

        Livewire::actingAs($admin)->test(Banks::class)
            ->set('bank_code', '5')->set('bank_name', 'HDFC')->set('branch_name', 'Salt Lake')
            ->call('save')->assertHasErrors(['bank_code' => 'unique']);
    }

    public function test_it_updates_a_bank(): void
    {
        DB::table('bank_master')->insert(['bank_code' => 5, 'bank_name' => 'SBI', 'branch_name' => 'Park Street']);

        Livewire::actingAs($this->makeUser('admin', 'A'))
            ->test(Banks::class)
            ->call('edit', 5)
            ->assertSet('bank_name', 'SBI')
            ->set('branch_name', 'Esplanade')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('bank_master', ['bank_code' => 5, 'branch_name' => 'Esplanade']);
    }

    public function test_it_deletes_a_bank(): void
    {
        DB::table('bank_master')->insert(['bank_code' => 5, 'bank_name' => 'SBI', 'branch_name' => 'Park Street']);

        Livewire::actingAs($this->makeUser('admin', 'A'))->test(Banks::class)->call('delete', 5);

        $this->assertDatabaseMissing('bank_master', ['bank_code' => 5]);
    }

    public function test_it_exports_the_filtered_banks_to_excel(): void
    {
        Excel::fake();

        DB::table('bank_master')->insert([
            ['bank_code' => 5, 'bank_name' => 'SBI', 'branch_name' => 'Park Street'],
            ['bank_code' => 6, 'bank_name' => 'HDFC', 'branch_name' => 'Salt Lake'],
        ]);

        Livewire::actingAs($this->makeUser('admin', 'A'))
            ->test(Banks::class)
            ->set('search', 'hdfc')
            ->call('export');

        Excel::assertDownloaded('banks-'.now()->format('Y-m-d').'.xlsx', function (BanksExport $export) {
            return $export->query()->count() === 1;
        });
    }
}
