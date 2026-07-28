<?php

namespace Tests\Feature\MasterData;

use App\Exports\DesignationsExport;
use App\Livewire\MasterData\Designations;
use App\Models\Designation;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class DesignationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['user_permission', 'role_permission', 'permissions', 'roles', 'user_account', 'designation_master'] as $t) {
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
        // bigIncrements → the database assigns designation_id (matches the Postgres sequence).
        Schema::create('designation_master', function (Blueprint $t) {
            $t->bigIncrements('designation_id');
            $t->string('designation', 180);
        });

        Permission::create(['key' => 'adminsection.designation_master', 'name' => 'Designation Master', 'group' => 'adminsection', 'legacy_menu_id' => 2]);
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
        $this->actingAs($this->makeUser('staff', 'S'))->get('/master/designations')->assertForbidden();
    }

    public function test_an_admin_can_open_the_screen(): void
    {
        $this->actingAs($this->makeUser('admin', 'A'))->get('/master/designations')->assertOk()->assertSee('Designation Master');
    }

    public function test_it_creates_a_designation_with_an_auto_generated_id(): void
    {
        Livewire::actingAs($this->makeUser('admin', 'A'))
            ->test(Designations::class)
            ->call('create')
            ->set('designation', 'Assistant Teacher')
            ->call('save')
            ->assertHasNoErrors();

        $row = Designation::where('designation', 'Assistant Teacher')->first();
        $this->assertNotNull($row);
        $this->assertGreaterThan(0, $row->designation_id);   // the DB assigned it
    }

    public function test_it_rejects_a_blank_or_duplicate_designation(): void
    {
        DB::table('designation_master')->insert(['designation' => 'Clerk']);
        $admin = $this->makeUser('admin', 'A');

        Livewire::actingAs($admin)->test(Designations::class)
            ->set('designation', '')->call('save')->assertHasErrors(['designation' => 'required']);

        Livewire::actingAs($admin)->test(Designations::class)
            ->set('designation', 'Clerk')->call('save')->assertHasErrors(['designation' => 'unique']);
    }

    public function test_it_updates_a_designation(): void
    {
        $id = DB::table('designation_master')->insertGetId(['designation' => 'Clerk'], 'designation_id');

        Livewire::actingAs($this->makeUser('admin', 'A'))
            ->test(Designations::class)
            ->call('edit', $id)
            ->assertSet('designation', 'Clerk')
            ->set('designation', 'Head Clerk')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('designation_master', ['designation_id' => $id, 'designation' => 'Head Clerk']);
    }

    public function test_it_deletes_a_designation(): void
    {
        $id = DB::table('designation_master')->insertGetId(['designation' => 'Clerk'], 'designation_id');

        Livewire::actingAs($this->makeUser('admin', 'A'))->test(Designations::class)->call('delete', $id);

        $this->assertDatabaseMissing('designation_master', ['designation_id' => $id]);
    }

    public function test_it_exports_the_filtered_designations_to_excel(): void
    {
        Excel::fake();

        DB::table('designation_master')->insert([
            ['designation' => 'Clerk'],
            ['designation' => 'Head Clerk'],
        ]);

        Livewire::actingAs($this->makeUser('admin', 'A'))
            ->test(Designations::class)
            ->set('search', 'head')
            ->call('export');

        Excel::assertDownloaded('designations-'.now()->format('Y-m-d').'.xlsx', function (DesignationsExport $export) {
            return $export->query()->count() === 1;
        });
    }
}
