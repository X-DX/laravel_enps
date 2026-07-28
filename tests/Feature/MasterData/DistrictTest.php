<?php

namespace Tests\Feature\MasterData;

use App\Exports\DistrictsExport;
use App\Livewire\MasterData\Districts;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class DistrictTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['user_permission', 'role_permission', 'permissions', 'roles', 'user_account', 'loc_master', 'dist_master', 'state_master'] as $t) {
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
        Schema::create('state_master', function (Blueprint $t) {
            $t->integer('state_code')->primary();
            $t->string('state_name', 100);
        });
        Schema::create('dist_master', function (Blueprint $t) {
            $t->bigInteger('dist_code')->primary();
            $t->integer('state_code')->nullable();   // added by the state feature; nullable
            $t->text('dist_name')->nullable();
        });
        Schema::create('loc_master', function (Blueprint $t) {
            $t->bigInteger('loc_code')->primary();
            $t->text('loc_name')->nullable();
            $t->bigInteger('dist_code')->nullable();
        });

        // Two states are enough to exercise the dropdown: the AP default + one other.
        DB::table('state_master')->insert([
            ['state_code' => 12, 'state_name' => 'Arunachal Pradesh'],
            ['state_code' => 14, 'state_name' => 'Manipur'],
        ]);

        Permission::create(['key' => 'adminsection.district_master', 'name' => 'District Master', 'group' => 'adminsection', 'legacy_menu_id' => 1]);
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
        $this->actingAs($this->makeUser('staff', 'S'))
            ->get('/master/districts')
            ->assertForbidden();
    }

    public function test_an_admin_can_open_the_screen(): void
    {
        $this->actingAs($this->makeUser('admin', 'A'))
            ->get('/master/districts')
            ->assertOk()
            ->assertSee('District Master');
    }

    public function test_it_creates_a_district_defaulting_to_arunachal_pradesh(): void
    {
        Livewire::actingAs($this->makeUser('admin', 'A'))
            ->test(Districts::class)
            ->call('create')
            ->assertSet('state_code', '12')   // the form pre-selects Arunachal Pradesh
            ->set('dist_code', '24')
            ->set('dist_name', 'Kolkata')
            ->call('save')
            ->assertHasNoErrors();

        // The default AP state is saved alongside the district.
        $this->assertDatabaseHas('dist_master', ['dist_code' => 24, 'state_code' => 12, 'dist_name' => 'Kolkata']);
    }

    public function test_state_is_required(): void
    {
        Livewire::actingAs($this->makeUser('admin', 'A'))
            ->test(Districts::class)
            ->call('create')
            ->set('state_code', '')          // admin clears the state
            ->set('dist_code', '24')
            ->set('dist_name', 'Kolkata')
            ->call('save')
            ->assertHasErrors(['state_code' => 'required']);

        $this->assertDatabaseMissing('dist_master', ['dist_code' => 24]);
    }

    public function test_it_rejects_a_blank_name_and_a_duplicate_code(): void
    {
        DB::table('dist_master')->insert(['dist_code' => 24, 'dist_name' => 'Kolkata']);
        $admin = $this->makeUser('admin', 'A');

        // Blank name.
        Livewire::actingAs($admin)->test(Districts::class)
            ->set('dist_code', '25')->set('dist_name', '')
            ->call('save')->assertHasErrors(['dist_name' => 'required']);

        // Duplicate code.
        Livewire::actingAs($admin)->test(Districts::class)
            ->set('dist_code', '24')->set('dist_name', 'Howrah')
            ->call('save')->assertHasErrors(['dist_code' => 'unique']);
    }

    public function test_editing_a_legacy_district_starts_with_no_state_then_saves_the_chosen_one(): void
    {
        // A legacy row: it exists with NO state (the progressive-backfill starting point).
        DB::table('dist_master')->insert(['dist_code' => 24, 'dist_name' => 'Kolkata']);

        Livewire::actingAs($this->makeUser('admin', 'A'))
            ->test(Districts::class)
            ->call('edit', 24)
            ->assertSet('dist_name', 'Kolkata')
            ->assertSet('state_code', '')      // no state yet → placeholder is shown
            ->set('state_code', '14')          // admin picks Manipur (the correct state for this row)
            ->set('dist_name', 'Kolkata Metro')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('dist_master', ['dist_code' => 24, 'state_code' => 14, 'dist_name' => 'Kolkata Metro']);
    }

    public function test_it_deletes_a_district(): void
    {
        DB::table('dist_master')->insert(['dist_code' => 24, 'dist_name' => 'Kolkata']);

        Livewire::actingAs($this->makeUser('admin', 'A'))
            ->test(Districts::class)
            ->call('delete', 24);

        $this->assertDatabaseMissing('dist_master', ['dist_code' => 24]);
    }

    public function test_it_refuses_to_delete_a_district_that_has_locations(): void
    {
        DB::table('dist_master')->insert(['dist_code' => 24, 'dist_name' => 'Kolkata']);
        DB::table('loc_master')->insert(['loc_code' => 1, 'loc_name' => 'Salt Lake', 'dist_code' => 24]);

        Livewire::actingAs($this->makeUser('admin', 'A'))
            ->test(Districts::class)
            ->call('delete', 24);

        $this->assertDatabaseHas('dist_master', ['dist_code' => 24]);
    }

    public function test_search_filters_the_list(): void
    {
        DB::table('dist_master')->insert([
            ['dist_code' => 24, 'dist_name' => 'Kolkata'],
            ['dist_code' => 25, 'dist_name' => 'Howrah'],
        ]);

        Livewire::actingAs($this->makeUser('admin', 'A'))
            ->test(Districts::class)
            ->set('search', 'howrah')
            ->assertSee('Howrah')
            ->assertDontSee('Kolkata');
    }

    public function test_per_page_controls_the_page_size(): void
    {
        for ($i = 1; $i <= 12; $i++) {
            DB::table('dist_master')->insert(['dist_code' => $i, 'dist_name' => 'District'.$i]);
        }

        $component = Livewire::actingAs($this->makeUser('admin', 'A'))->test(Districts::class);

        // Default 10 per page → District12 (code 12) is on page 2, not visible.
        $component->assertSee('District10')->assertDontSee('District12');

        // Bump to 25 → all 12 fit on one page.
        $component->set('perPage', 25)->assertSee('District12');
    }

    public function test_it_exports_the_filtered_districts_to_excel(): void
    {
        Excel::fake();

        DB::table('dist_master')->insert([
            ['dist_code' => 24, 'dist_name' => 'Kolkata'],
            ['dist_code' => 25, 'dist_name' => 'Howrah'],
        ]);

        Livewire::actingAs($this->makeUser('admin', 'A'))
            ->test(Districts::class)
            ->set('search', 'kolkata')
            ->call('export');

        // The export respects the search term → only Kolkata is included.
        Excel::assertDownloaded('districts-'.now()->format('Y-m-d').'.xlsx', function (DistrictsExport $export) {
            return $export->query()->count() === 1;
        });
    }
}
