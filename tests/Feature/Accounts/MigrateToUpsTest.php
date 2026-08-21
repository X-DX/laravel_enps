<?php

namespace Tests\Feature\Accounts;

use App\Livewire\Accounts\MigrateToUps;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Migration to UPS (menu 161): search a finalized NPS account and migrate it to UPS — set
 * pension_type='U' AND log the migration into ups_migration, in one transaction. Only an NPS
 * account can migrate.
 */
class MigrateToUpsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['user_permission', 'role_permission', 'permissions', 'roles', 'user_account', 'allotment_accnt_no', 'ups_migration'] as $t) {
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
        Schema::create('allotment_accnt_no', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('name');
            $t->string('account_no')->nullable();
            $t->char('save_flag', 1)->nullable();
            $t->boolean('isactive')->default(true);
            $t->char('pension_type', 1)->nullable();
        });
        Schema::create('ups_migration', function (Blueprint $t) {
            $t->string('user_id', 10)->nullable();
            $t->string('account_no')->nullable();
            $t->integer('migration_year')->nullable();
            $t->integer('migration_month')->nullable();
            $t->timestamp('entry_date')->nullable();
        });

        Permission::create(['key' => 'entrysection.migration_to_ups', 'name' => 'Migration to UPS', 'group' => 'entrysection', 'legacy_menu_id' => 161]);
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

    private function seedAccount(string $accountNo, string $name = 'PONDITA MODI', string $pension = 'N', string $saveFlag = 'F', bool $isactive = true): int
    {
        return DB::table('allotment_accnt_no')->insertGetId([
            'name' => $name,
            'account_no' => $accountNo,
            'save_flag' => $saveFlag,
            'isactive' => $isactive,
            'pension_type' => $pension,
        ]);
    }

    public function test_the_route_is_forbidden_without_the_permission(): void
    {
        $this->actingAs($this->makeUser('staff', 'S'))->get('/accounts/ups-migration')->assertForbidden();
    }

    public function test_search_finds_finalized_accounts_by_number_or_name(): void
    {
        $this->seedAccount('AP/NPS/15/0001', 'PONDITA MODI');

        Livewire::actingAs($this->makeUser('admin', 'A'))
            ->test(MigrateToUps::class)
            ->set('search', 'pondita')
            ->assertSee('AP/NPS/15/0001')
            ->assertSee('PONDITA MODI');
    }

    public function test_migrate_requires_year_and_month(): void
    {
        $this->seedAccount('AP/NPS/15/0001');

        Livewire::actingAs($this->makeUser('admin', 'A'))
            ->test(MigrateToUps::class)
            ->call('selectAccount', 'AP/NPS/15/0001')
            ->call('migrate')
            ->assertHasErrors(['migrationYear', 'migrationMonth']);
    }

    public function test_migrating_an_nps_account_sets_ups_and_logs_it(): void
    {
        $id = $this->seedAccount('AP/NPS/15/0001', pension: 'N');

        Livewire::actingAs($this->makeUser('admin', 'A'))
            ->test(MigrateToUps::class)
            ->call('selectAccount', 'AP/NPS/15/0001')
            ->set('migrationYear', '2024')
            ->set('migrationMonth', '6')
            ->call('migrate')
            ->assertHasNoErrors()
            ->assertSet('selectedAccountNo', '');   // form clears

        $this->assertDatabaseHas('allotment_accnt_no', ['id' => $id, 'pension_type' => 'U']);
        $this->assertDatabaseHas('ups_migration', [
            'account_no' => 'AP/NPS/15/0001',
            'migration_year' => 2024,
            'migration_month' => 6,
            'user_id' => 'admin',
        ]);
    }

    public function test_it_will_not_migrate_an_account_already_on_ups(): void
    {
        $id = $this->seedAccount('AP/UPS/15/0001', pension: 'U');

        Livewire::actingAs($this->makeUser('admin', 'A'))
            ->test(MigrateToUps::class)
            ->call('selectAccount', 'AP/UPS/15/0001')
            ->set('migrationYear', '2024')
            ->set('migrationMonth', '6')
            ->call('migrate');

        $this->assertSame(0, DB::table('ups_migration')->count());   // nothing logged
        $this->assertDatabaseHas('allotment_accnt_no', ['id' => $id, 'pension_type' => 'U']);
    }
}
