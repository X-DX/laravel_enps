<?php

namespace Tests\Feature\MasterData;

use App\Exports\TreasuriesExport;
use App\Livewire\MasterData\Treasuries;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class TreasuryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['user_permission', 'role_permission', 'permissions', 'roles', 'user_account', 'treasury_master', 'dist_master'] as $t) {
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
        Schema::create('dist_master', function (Blueprint $t) {
            $t->bigInteger('dist_code')->primary();
            $t->text('dist_name')->nullable();
        });
        Schema::create('treasury_master', function (Blueprint $t) {
            $t->string('treasury_code', 10)->primary();   // digit-string like "01"
            $t->bigInteger('dist_code');
            $t->string('treasury_name', 150);
        });

        Permission::create(['key' => 'adminsection.treasury_master', 'name' => 'Treasury Master', 'group' => 'adminsection', 'legacy_menu_id' => 237]);

        // A district for the dropdown / relationship tests.
        DB::table('dist_master')->insert(['dist_code' => 24, 'dist_name' => 'Kolkata']);
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
        $this->actingAs($this->makeUser('staff', 'S'))->get('/master/treasuries')->assertForbidden();
    }

    public function test_an_admin_can_open_the_screen(): void
    {
        $this->actingAs($this->makeUser('admin', 'A'))->get('/master/treasuries')->assertOk()->assertSee('Treasury Master');
    }

    public function test_it_creates_a_treasury_and_preserves_a_leading_zero(): void
    {
        Livewire::actingAs($this->makeUser('admin', 'A'))
            ->test(Treasuries::class)
            ->call('create')
            ->set('dist_code', '24')
            ->set('treasury_code', '01')            // leading zero must survive
            ->set('treasury_name', 'Kolkata Treasury')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('treasury_master', ['treasury_code' => '01', 'treasury_name' => 'Kolkata Treasury', 'dist_code' => 24]);
        // Prove it did NOT collapse to the integer 1.
        $this->assertDatabaseMissing('treasury_master', ['treasury_code' => '1']);
    }

    public function test_it_rejects_a_code_with_letters(): void
    {
        Livewire::actingAs($this->makeUser('admin', 'A'))
            ->test(Treasuries::class)
            ->set('treasury_code', 'AB1')->set('treasury_name', 'Nope')->set('dist_code', '24')
            ->call('save')->assertHasErrors(['treasury_code' => 'regex']);
    }

    public function test_it_requires_a_district_and_rejects_a_nonexistent_one(): void
    {
        $admin = $this->makeUser('admin', 'A');

        // Missing district.
        Livewire::actingAs($admin)->test(Treasuries::class)
            ->set('treasury_code', '01')->set('treasury_name', 'Kolkata Treasury')->set('dist_code', '')
            ->call('save')->assertHasErrors(['dist_code' => 'required']);

        // District that doesn't exist (99 is not in dist_master).
        Livewire::actingAs($admin)->test(Treasuries::class)
            ->set('treasury_code', '01')->set('treasury_name', 'Kolkata Treasury')->set('dist_code', '99')
            ->call('save')->assertHasErrors(['dist_code' => 'exists']);
    }

    public function test_it_rejects_a_blank_name_and_a_duplicate_code(): void
    {
        DB::table('treasury_master')->insert(['treasury_code' => '01', 'treasury_name' => 'Kolkata Treasury', 'dist_code' => 24]);
        $admin = $this->makeUser('admin', 'A');

        Livewire::actingAs($admin)->test(Treasuries::class)
            ->set('treasury_code', '02')->set('treasury_name', '')->set('dist_code', '24')
            ->call('save')->assertHasErrors(['treasury_name' => 'required']);

        Livewire::actingAs($admin)->test(Treasuries::class)
            ->set('treasury_code', '01')->set('treasury_name', 'Another Treasury')->set('dist_code', '24')
            ->call('save')->assertHasErrors(['treasury_code' => 'unique']);
    }

    public function test_it_updates_a_treasury(): void
    {
        DB::table('treasury_master')->insert(['treasury_code' => '01', 'treasury_name' => 'Kolkata Treasury', 'dist_code' => 24]);

        Livewire::actingAs($this->makeUser('admin', 'A'))
            ->test(Treasuries::class)
            ->call('edit', '01')
            ->assertSet('treasury_code', '01')
            ->assertSet('treasury_name', 'Kolkata Treasury')
            ->assertSet('dist_code', '24')
            ->set('treasury_name', 'Kolkata Main Treasury')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('treasury_master', ['treasury_code' => '01', 'treasury_name' => 'Kolkata Main Treasury']);
    }

    public function test_it_deletes_a_treasury(): void
    {
        DB::table('treasury_master')->insert(['treasury_code' => '01', 'treasury_name' => 'Kolkata Treasury', 'dist_code' => 24]);

        Livewire::actingAs($this->makeUser('admin', 'A'))->test(Treasuries::class)->call('delete', '01');

        $this->assertDatabaseMissing('treasury_master', ['treasury_code' => '01']);
    }

    public function test_search_filters_the_list(): void
    {
        DB::table('treasury_master')->insert([
            ['treasury_code' => '01', 'treasury_name' => 'Kolkata Treasury', 'dist_code' => 24],
            ['treasury_code' => '02', 'treasury_name' => 'Howrah Treasury', 'dist_code' => 24],
        ]);

        Livewire::actingAs($this->makeUser('admin', 'A'))
            ->test(Treasuries::class)
            ->set('search', 'howrah')
            ->assertSee('Howrah Treasury')
            ->assertDontSee('Kolkata Treasury');
    }

    public function test_it_exports_the_searched_treasuries_to_excel(): void
    {
        Excel::fake();

        DB::table('treasury_master')->insert([
            ['treasury_code' => '01', 'treasury_name' => 'Kolkata Treasury', 'dist_code' => 24],
            ['treasury_code' => '02', 'treasury_name' => 'Howrah Treasury', 'dist_code' => 24],
        ]);

        Livewire::actingAs($this->makeUser('admin', 'A'))
            ->test(Treasuries::class)
            ->set('search', 'kolkata')
            ->call('export');

        // The export respects the search term → only the Kolkata treasury is included.
        Excel::assertDownloaded('treasuries-'.now()->format('Y-m-d').'.xlsx', function (TreasuriesExport $export) {
            return $export->query()->count() === 1;
        });
    }
}
