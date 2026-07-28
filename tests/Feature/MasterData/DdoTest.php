<?php

namespace Tests\Feature\MasterData;

use App\Exports\DdosExport;
use App\Livewire\MasterData\Ddos;
use App\Models\Ddo;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class DdoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['user_permission', 'role_permission', 'permissions', 'roles', 'user_account', 'ddo_master', 'treasury_master', 'loc_master', 'dist_master'] as $t) {
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
            $t->string('treasury_code', 10)->primary();
            $t->bigInteger('dist_code');
            $t->string('treasury_name', 150);
        });
        Schema::create('ddo_master', function (Blueprint $t) {
            $t->bigIncrements('ddo_code');                 // auto-generated (matches the Postgres sequence)
            $t->string('ddo_name', 150)->nullable();
            $t->string('loc_code', 10)->nullable();        // legacy column, retained
            $t->string('treasury_code', 10)->nullable();   // the current link
        });

        Permission::create(['key' => 'adminsection.ddo_entry', 'name' => 'DDO Entry', 'group' => 'adminsection', 'legacy_menu_id' => 5]);

        // Two districts, one treasury each.
        DB::table('dist_master')->insert([
            ['dist_code' => 24, 'dist_name' => 'Kolkata'],
            ['dist_code' => 25, 'dist_name' => 'Howrah'],
        ]);
        DB::table('treasury_master')->insert([
            ['treasury_code' => '01', 'treasury_name' => 'Kolkata Treasury', 'dist_code' => 24],
            ['treasury_code' => '02', 'treasury_name' => 'Howrah Treasury', 'dist_code' => 25],
        ]);
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

    private function seedDdos(): void
    {
        DB::table('ddo_master')->insert([
            ['ddo_code' => 100, 'ddo_name' => 'DDO Alpha', 'treasury_code' => '01'],   // Kolkata Treasury
            ['ddo_code' => 200, 'ddo_name' => 'DDO Beta', 'treasury_code' => '02'],    // Howrah Treasury
        ]);
    }

    public function test_the_route_is_forbidden_without_the_permission(): void
    {
        $this->actingAs($this->makeUser('staff', 'S'))->get('/master/ddos')->assertForbidden();
    }

    public function test_an_admin_can_open_the_screen(): void
    {
        $this->actingAs($this->makeUser('admin', 'A'))->get('/master/ddos')->assertOk()->assertSee('DDO Master');
    }

    public function test_it_creates_a_ddo_with_an_auto_generated_code(): void
    {
        Livewire::actingAs($this->makeUser('admin', 'A'))
            ->test(Ddos::class)
            ->call('create')
            ->set('form_district', '24')   // District → Treasury cascade in the form
            ->set('treasury_code', '01')
            ->set('ddo_name', 'DDO New')
            ->call('save')
            ->assertHasNoErrors();

        $row = Ddo::where('ddo_name', 'DDO New')->first();
        $this->assertNotNull($row);
        $this->assertGreaterThan(0, $row->ddo_code);        // the DB assigned it
        $this->assertSame('01', (string) $row->treasury_code);
    }

    public function test_it_requires_a_valid_treasury(): void
    {
        $admin = $this->makeUser('admin', 'A');

        Livewire::actingAs($admin)->test(Ddos::class)
            ->set('ddo_name', 'DDO New')->set('treasury_code', '')
            ->call('save')->assertHasErrors(['treasury_code' => 'required']);

        Livewire::actingAs($admin)->test(Ddos::class)
            ->set('ddo_name', 'DDO New')->set('treasury_code', '99')
            ->call('save')->assertHasErrors(['treasury_code' => 'exists']);
    }

    public function test_it_rejects_a_blank_name(): void
    {
        Livewire::actingAs($this->makeUser('admin', 'A'))->test(Ddos::class)
            ->set('ddo_name', '')->set('treasury_code', '01')
            ->call('save')->assertHasErrors(['ddo_name' => 'required']);
    }

    public function test_it_updates_a_ddo_and_preselects_its_district(): void
    {
        $this->seedDdos();

        Livewire::actingAs($this->makeUser('admin', 'A'))
            ->test(Ddos::class)
            ->call('edit', 100)
            ->assertSet('ddo_name', 'DDO Alpha')
            ->assertSet('treasury_code', '01')
            ->assertSet('form_district', '24')   // district pre-selected from the treasury
            ->set('ddo_name', 'DDO Alpha Renamed')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('ddo_master', ['ddo_code' => 100, 'ddo_name' => 'DDO Alpha Renamed', 'treasury_code' => '01']);
    }

    public function test_editing_a_legacy_ddo_starts_with_no_treasury_then_saves_the_chosen_one(): void
    {
        // A legacy DDO with a location but NO treasury (the progressive-backfill start point).
        DB::table('ddo_master')->insert(['ddo_code' => 300, 'ddo_name' => 'DDO Legacy', 'loc_code' => '1', 'treasury_code' => null]);

        Livewire::actingAs($this->makeUser('admin', 'A'))
            ->test(Ddos::class)
            ->call('edit', 300)
            ->assertSet('treasury_code', '')       // no treasury yet → placeholder
            ->assertSet('form_district', '')
            ->set('form_district', '24')
            ->set('treasury_code', '01')
            ->call('save')
            ->assertHasNoErrors();

        // loc_code is preserved; treasury_code is now set.
        $this->assertDatabaseHas('ddo_master', ['ddo_code' => 300, 'loc_code' => '1', 'treasury_code' => '01']);
    }

    public function test_changing_the_form_district_clears_the_treasury(): void
    {
        Livewire::actingAs($this->makeUser('admin', 'A'))
            ->test(Ddos::class)
            ->set('form_district', '24')
            ->set('treasury_code', '01')
            ->set('form_district', '25')
            ->assertSet('treasury_code', '');
    }

    public function test_it_deletes_a_ddo(): void
    {
        $this->seedDdos();

        Livewire::actingAs($this->makeUser('admin', 'A'))->test(Ddos::class)->call('delete', 100);

        $this->assertDatabaseMissing('ddo_master', ['ddo_code' => 100]);
    }

    public function test_it_filters_by_district(): void
    {
        $this->seedDdos();

        Livewire::actingAs($this->makeUser('admin', 'A'))
            ->test(Ddos::class)
            ->set('filterDistrict', '24')      // Kolkata → only its treasury's DDOs
            ->assertSee('DDO Alpha')
            ->assertDontSee('DDO Beta');
    }

    public function test_it_filters_by_treasury(): void
    {
        $this->seedDdos();

        Livewire::actingAs($this->makeUser('admin', 'A'))
            ->test(Ddos::class)
            ->set('filterTreasury', '02')      // Howrah Treasury → only its DDOs
            ->assertSee('DDO Beta')
            ->assertDontSee('DDO Alpha');
    }

    public function test_changing_district_resets_the_treasury_filter(): void
    {
        Livewire::actingAs($this->makeUser('admin', 'A'))
            ->test(Ddos::class)
            ->set('filterDistrict', '24')
            ->set('filterTreasury', '01')
            ->set('filterDistrict', '25')
            ->assertSet('filterTreasury', '');
    }

    public function test_it_exports_only_the_filtered_ddos(): void
    {
        Excel::fake();
        $this->seedDdos();

        Livewire::actingAs($this->makeUser('admin', 'A'))
            ->test(Ddos::class)
            ->set('filterTreasury', '01')
            ->call('export');

        Excel::assertDownloaded('ddos-'.now()->format('Y-m-d').'.xlsx', function (DdosExport $export) {
            return $export->query()->count() === 1;
        });
    }
}
