<?php

namespace Tests\Feature\MasterData;

use App\Exports\LocationsExport;
use App\Livewire\MasterData\Locations;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class LocationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['user_permission', 'role_permission', 'permissions', 'roles', 'user_account', 'ddo_master', 'loc_master', 'dist_master'] as $t) {
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
        Schema::create('loc_master', function (Blueprint $t) {
            $t->bigInteger('loc_code')->primary();
            $t->text('loc_name')->nullable();
            $t->bigInteger('dist_code')->nullable();
        });
        Schema::create('ddo_master', function (Blueprint $t) {
            $t->bigInteger('ddo_code')->primary();
            $t->string('ddo_name', 150)->nullable();
            $t->string('loc_code', 10)->nullable();   // varchar in the legacy schema
        });

        Permission::create(['key' => 'adminsection.location_master', 'name' => 'Location Master', 'group' => 'adminsection', 'legacy_menu_id' => 3]);

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
        $this->actingAs($this->makeUser('staff', 'S'))->get('/master/locations')->assertForbidden();
    }

    public function test_an_admin_can_open_the_screen(): void
    {
        $this->actingAs($this->makeUser('admin', 'A'))->get('/master/locations')->assertOk()->assertSee('Location Master');
    }

    public function test_it_creates_a_location_under_a_district(): void
    {
        Livewire::actingAs($this->makeUser('admin', 'A'))
            ->test(Locations::class)
            ->call('create')
            ->set('loc_code', '1')
            ->set('loc_name', 'Salt Lake')
            ->set('dist_code', '24')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('loc_master', ['loc_code' => 1, 'loc_name' => 'Salt Lake', 'dist_code' => 24]);
    }

    public function test_it_requires_a_district_and_rejects_a_nonexistent_one(): void
    {
        $admin = $this->makeUser('admin', 'A');

        // Missing district.
        Livewire::actingAs($admin)->test(Locations::class)
            ->set('loc_code', '1')->set('loc_name', 'Salt Lake')->set('dist_code', '')
            ->call('save')->assertHasErrors(['dist_code' => 'required']);

        // District that doesn't exist (99 is not in dist_master).
        Livewire::actingAs($admin)->test(Locations::class)
            ->set('loc_code', '1')->set('loc_name', 'Salt Lake')->set('dist_code', '99')
            ->call('save')->assertHasErrors(['dist_code' => 'exists']);
    }

    public function test_it_rejects_a_blank_name_and_a_duplicate_code(): void
    {
        DB::table('loc_master')->insert(['loc_code' => 1, 'loc_name' => 'Salt Lake', 'dist_code' => 24]);
        $admin = $this->makeUser('admin', 'A');

        Livewire::actingAs($admin)->test(Locations::class)
            ->set('loc_code', '2')->set('loc_name', '')->set('dist_code', '24')
            ->call('save')->assertHasErrors(['loc_name' => 'required']);

        Livewire::actingAs($admin)->test(Locations::class)
            ->set('loc_code', '1')->set('loc_name', 'New Town')->set('dist_code', '24')
            ->call('save')->assertHasErrors(['loc_code' => 'unique']);
    }

    public function test_it_updates_a_location(): void
    {
        DB::table('loc_master')->insert(['loc_code' => 1, 'loc_name' => 'Salt Lake', 'dist_code' => 24]);

        Livewire::actingAs($this->makeUser('admin', 'A'))
            ->test(Locations::class)
            ->call('edit', 1)
            ->assertSet('loc_name', 'Salt Lake')
            ->assertSet('dist_code', '24')
            ->set('loc_name', 'Salt Lake Sector V')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('loc_master', ['loc_code' => 1, 'loc_name' => 'Salt Lake Sector V']);
    }

    public function test_it_deletes_a_location(): void
    {
        DB::table('loc_master')->insert(['loc_code' => 1, 'loc_name' => 'Salt Lake', 'dist_code' => 24]);

        Livewire::actingAs($this->makeUser('admin', 'A'))->test(Locations::class)->call('delete', 1);

        $this->assertDatabaseMissing('loc_master', ['loc_code' => 1]);
    }

    public function test_it_refuses_to_delete_a_location_that_has_ddos(): void
    {
        DB::table('loc_master')->insert(['loc_code' => 1, 'loc_name' => 'Salt Lake', 'dist_code' => 24]);
        DB::table('ddo_master')->insert(['ddo_code' => 100, 'ddo_name' => 'DDO A', 'loc_code' => '1']);

        Livewire::actingAs($this->makeUser('admin', 'A'))->test(Locations::class)->call('delete', 1);

        $this->assertDatabaseHas('loc_master', ['loc_code' => 1]);
    }

    public function test_it_filters_the_list_by_district(): void
    {
        DB::table('dist_master')->insert(['dist_code' => 25, 'dist_name' => 'Howrah']);
        DB::table('loc_master')->insert([
            ['loc_code' => 1, 'loc_name' => 'Salt Lake', 'dist_code' => 24],
            ['loc_code' => 2, 'loc_name' => 'Shibpur', 'dist_code' => 25],
        ]);

        Livewire::actingAs($this->makeUser('admin', 'A'))
            ->test(Locations::class)
            ->set('filterDistrict', '24')
            ->assertSee('Salt Lake')
            ->assertDontSee('Shibpur');
    }

    public function test_it_exports_only_the_selected_districts_locations(): void
    {
        Excel::fake();

        DB::table('dist_master')->insert(['dist_code' => 25, 'dist_name' => 'Howrah']);
        DB::table('loc_master')->insert([
            ['loc_code' => 1, 'loc_name' => 'Salt Lake', 'dist_code' => 24],
            ['loc_code' => 2, 'loc_name' => 'Shibpur', 'dist_code' => 25],
        ]);

        Livewire::actingAs($this->makeUser('admin', 'A'))
            ->test(Locations::class)
            ->set('filterDistrict', '24')
            ->call('export');

        // Only district 24 has one location.
        Excel::assertDownloaded('locations-'.now()->format('Y-m-d').'.xlsx', function (LocationsExport $export) {
            return $export->query()->count() === 1;
        });
    }
}
