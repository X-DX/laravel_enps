<?php

namespace Tests\Feature\Accounts;

use App\Exports\SubscribersExport;
use App\Livewire\Accounts\Subscribers;
use App\Models\Permission;
use App\Models\Subscriber;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class SubscriberTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['user_permission', 'role_permission', 'permissions', 'roles', 'user_account', 'allotment_accnt_no', 'pran_no', 'department', 'ddo_master', 'designation_master'] as $t) {
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
        Schema::create('ddo_master', function (Blueprint $t) {
            $t->bigIncrements('ddo_sl');
            $t->string('ddo_name', 150)->nullable();
        });
        Schema::create('designation_master', function (Blueprint $t) {
            $t->bigIncrements('designation_id');
            $t->string('designation')->nullable();
        });
        Schema::create('department', function (Blueprint $t) {
            $t->string('dept_code', 10)->primary();
            $t->string('dept_name');
        });
        Schema::create('pran_no', function (Blueprint $t) {
            $t->string('account_no')->primary();
            $t->double('pran_no')->nullable();
        });
        Schema::create('allotment_accnt_no', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('name');
            $t->string('account_no')->nullable();
            $t->char('save_flag', 1)->nullable();
            $t->date('dob')->nullable();
            $t->string('nameofdept')->nullable();
            $t->bigInteger('ddocode')->nullable();
            $t->bigInteger('designation')->nullable();
            $t->string('user_id', 10)->nullable();
        });

        Permission::create(['key' => 'entrysection.view_all_accounts', 'name' => 'View All Accounts', 'group' => 'entrysection', 'legacy_menu_id' => 154]);

        DB::table('ddo_master')->insert(['ddo_sl' => 589, 'ddo_name' => 'DIR SMALL SAVINGS']);
        DB::table('designation_master')->insert(['designation_id' => 1, 'designation' => 'L.D.C']);
        DB::table('department')->insert([
            ['dept_code' => '01', 'dept_name' => 'AP/ACCTT'],
            ['dept_code' => '02', 'dept_name' => 'AP/AGRI'],
        ]);
        DB::table('pran_no')->insert(['account_no' => 'AP/NPS/01/0001', 'pran_no' => 110016825057]);
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

    private function seedSubscribers(): void
    {
        DB::table('allotment_accnt_no')->insert([
            // Finalized subscriber with an account number + a PRAN. nameofdept padded on purpose.
            ['id' => 1, 'name' => 'PONDITA MODI', 'account_no' => 'AP/NPS/01/0001', 'save_flag' => 'F', 'dob' => '1981-01-11', 'nameofdept' => '01 ', 'ddocode' => 589, 'designation' => 1],
            // Pending (draft) subscriber — no account number yet.
            ['id' => 2, 'name' => 'DRAFT PERSON', 'account_no' => null, 'save_flag' => 'T', 'dob' => null, 'nameofdept' => '02', 'ddocode' => 589, 'designation' => 1],
        ]);
    }

    public function test_the_route_is_forbidden_without_the_permission(): void
    {
        $this->actingAs($this->makeUser('staff', 'S'))->get('/accounts')->assertForbidden();
    }

    public function test_an_admin_can_open_the_screen(): void
    {
        $this->actingAs($this->makeUser('admin', 'A'))->get('/accounts')->assertOk()->assertSee('View All Accounts');
    }

    public function test_subscribers_are_scoped_to_their_owner_for_non_admins(): void
    {
        DB::table('allotment_accnt_no')->insert([
            ['id' => 10, 'name' => 'OP1 ACCOUNT', 'account_no' => null, 'save_flag' => 'T', 'nameofdept' => '01', 'ddocode' => 589, 'designation' => 1, 'user_id' => 'op1'],
            ['id' => 11, 'name' => 'OP2 ACCOUNT', 'account_no' => null, 'save_flag' => 'T', 'nameofdept' => '01', 'ddocode' => 589, 'designation' => 1, 'user_id' => 'op2'],
        ]);

        // A non-admin operator sees only their own account.
        $this->actingAs($this->makeUser('op1', 'S'));
        $rows = Subscriber::query()->get();
        $this->assertCount(1, $rows);
        $this->assertSame('OP1 ACCOUNT', $rows->first()->name);

        // An admin sees everyone's.
        $this->actingAs($this->makeUser('boss', 'A'));
        $this->assertSame(2, Subscriber::query()->count());
    }

    public function test_it_lists_a_subscriber_with_all_its_details(): void
    {
        $this->seedSubscribers();

        Livewire::actingAs($this->makeUser('admin', 'A'))
            ->test(Subscribers::class)
            ->assertSee('PONDITA MODI')          // name
            ->assertSee('AP/NPS/01/0001')        // account no
            ->assertSee('110016825057')          // PRAN (formatted, no scientific notation)
            ->assertSee('AP/ACCTT')              // department name (via trimmed dept code)
            ->assertSee('L.D.C')                 // designation
            ->assertSee('DIR SMALL SAVINGS')     // DDO
            ->assertSee('Finalized');            // status badge
    }

    public function test_a_pending_subscriber_shows_pending_not_an_account_number(): void
    {
        $this->seedSubscribers();

        Livewire::actingAs($this->makeUser('admin', 'A'))
            ->test(Subscribers::class)
            ->assertSee('DRAFT PERSON')
            ->assertSee('Pending');
    }

    public function test_search_filters_by_name(): void
    {
        $this->seedSubscribers();

        Livewire::actingAs($this->makeUser('admin', 'A'))
            ->test(Subscribers::class)
            ->set('search', 'pondita')
            ->assertSee('PONDITA MODI')
            ->assertDontSee('DRAFT PERSON');
    }

    public function test_search_filters_by_account_number(): void
    {
        $this->seedSubscribers();

        Livewire::actingAs($this->makeUser('admin', 'A'))
            ->test(Subscribers::class)
            ->set('search', '01/0001')
            ->assertSee('PONDITA MODI')
            ->assertDontSee('DRAFT PERSON');
    }

    public function test_status_filter_shows_only_finalized_or_only_pending(): void
    {
        $this->seedSubscribers();

        $admin = $this->makeUser('admin', 'A');

        Livewire::actingAs($admin)->test(Subscribers::class)
            ->set('status', 'F')
            ->assertSee('PONDITA MODI')
            ->assertDontSee('DRAFT PERSON');

        Livewire::actingAs($admin)->test(Subscribers::class)
            ->set('status', 'T')
            ->assertSee('DRAFT PERSON')
            ->assertDontSee('PONDITA MODI');
    }

    public function test_it_exports_the_filtered_subscribers_to_excel(): void
    {
        Excel::fake();
        $this->seedSubscribers();

        Livewire::actingAs($this->makeUser('admin', 'A'))
            ->test(Subscribers::class)
            ->set('status', 'F')
            ->call('export');

        // Only the finalized subscriber is exported.
        Excel::assertDownloaded('subscribers-all-'.now()->format('Y-m-d').'.xlsx', function (SubscribersExport $export) {
            return $export->query()->count() === 1;
        });
    }

    public function test_pending_mode_shows_only_pending_subscribers(): void
    {
        $this->seedSubscribers();

        Livewire::actingAs($this->makeUser('admin', 'A'))
            ->test(Subscribers::class, ['mode' => 'pending'])
            ->assertSee('DRAFT PERSON')
            ->assertDontSee('PONDITA MODI');
    }

    public function test_finalized_mode_shows_only_finalized_subscribers(): void
    {
        $this->seedSubscribers();

        Livewire::actingAs($this->makeUser('admin', 'A'))
            ->test(Subscribers::class, ['mode' => 'finalized'])
            ->assertSee('PONDITA MODI')
            ->assertDontSee('DRAFT PERSON');
    }

    public function test_it_downloads_the_list_as_a_pdf(): void
    {
        $this->seedSubscribers();

        Livewire::actingAs($this->makeUser('admin', 'A'))
            ->test(Subscribers::class)
            ->call('pdf')
            ->assertFileDownloaded('subscribers-all-'.now()->format('Y-m-d').'.pdf');
    }
}
