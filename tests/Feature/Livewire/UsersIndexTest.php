<?php

namespace Tests\Feature\Livewire;

use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Livewire\Users\Index;
use App\Models\Branch;
use App\Models\CommitteeMember;
use App\Models\Portfolio;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UsersIndexTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_admin_can_create_and_edit_a_user_with_a_role(): void
    {
        $branch = Branch::factory()->create();

        $admin = User::factory()->create(['branch_id' => $branch->id]);
        $admin->assignRole(RoleName::Admin->value);

        Livewire::actingAs($admin)
            ->test(Index::class)
            ->call('create')
            ->set('name', 'New Manager')
            ->set('email', 'manager@example.com')
            ->set('password', 'password123')
            ->set('role', RoleName::Manager->value)
            ->set('branch_id', $branch->id)
            ->set('status', UserStatus::Active->value)
            ->call('save')
            ->assertHasNoErrors();

        $user = User::where('email', 'manager@example.com')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole(RoleName::Manager->value));
        $this->assertSame($branch->id, $user->branch_id);

        Livewire::actingAs($admin)
            ->test(Index::class)
            ->call('edit', $user->id)
            ->set('name', 'Updated Manager')
            ->set('role', RoleName::Staff->value)
            ->call('save')
            ->assertHasNoErrors();

        $user->refresh();
        $this->assertSame('Updated Manager', $user->name);
        $this->assertTrue($user->hasRole(RoleName::Staff->value));
        $this->assertFalse($user->hasRole(RoleName::Manager->value));
    }

    public function test_admin_cannot_delete_their_own_account(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);

        Livewire::actingAs($admin)
            ->test(Index::class)
            ->call('confirmDelete', $admin->id)
            ->assertForbidden();
    }

    public function test_creating_a_committee_member_without_linking_auto_creates_a_roster_entry(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);

        $wanita = Portfolio::factory()->create();

        Livewire::actingAs($admin)
            ->test(Index::class)
            ->call('create')
            ->set('name', 'Siti Aminah')
            ->set('email', 'siti@example.com')
            ->set('password', 'password123')
            ->set('role', RoleName::CommitteeMember->value)
            ->set('portfolio_id', $wanita->id)
            ->set('committeePosition', 'Setiausaha')
            ->call('save')
            ->assertHasNoErrors();

        $user = User::where('email', 'siti@example.com')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole(RoleName::CommitteeMember->value));
        $this->assertSame($wanita->id, $user->portfolio_id);

        $committeeMember = CommitteeMember::where('user_id', $user->id)->first();
        $this->assertNotNull($committeeMember);
        $this->assertSame('Siti Aminah', $committeeMember->name);
        $this->assertSame('Setiausaha', $committeeMember->position);
        $this->assertSame($wanita->id, $committeeMember->portfolio_id);
    }

    public function test_creating_a_committee_member_can_link_to_an_existing_roster_entry_instead_of_duplicating(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);

        $wanita = Portfolio::factory()->create();
        $existing = CommitteeMember::factory()->create([
            'portfolio_id' => $wanita->id,
            'name' => 'Siti Aminah',
            'position' => 'Setiausaha',
        ]);

        Livewire::actingAs($admin)
            ->test(Index::class)
            ->call('create')
            ->set('name', 'Siti Aminah')
            ->set('email', 'siti@example.com')
            ->set('password', 'password123')
            ->set('role', RoleName::CommitteeMember->value)
            ->set('portfolio_id', $wanita->id)
            ->set('linkCommitteeMemberId', $existing->id)
            ->call('save')
            ->assertHasNoErrors();

        $user = User::where('email', 'siti@example.com')->first();
        $this->assertSame(1, CommitteeMember::where('portfolio_id', $wanita->id)->count(), 'Linking to an existing entry must not create a duplicate.');
        $this->assertSame($user->id, $existing->fresh()->user_id);
    }

    public function test_switching_a_committee_members_role_away_unlinks_their_roster_entry_and_clears_their_portfolio(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);

        $wanita = Portfolio::factory()->create();
        $committeeMemberUser = User::factory()->create(['portfolio_id' => $wanita->id]);
        $committeeMemberUser->assignRole(RoleName::CommitteeMember->value);
        $roster = CommitteeMember::factory()->create(['portfolio_id' => $wanita->id, 'user_id' => $committeeMemberUser->id]);

        Livewire::actingAs($admin)
            ->test(Index::class)
            ->call('edit', $committeeMemberUser->id)
            ->set('role', RoleName::Staff->value)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertNull($committeeMemberUser->fresh()->portfolio_id);
        $this->assertNull($roster->fresh()->user_id, 'The roster entry itself should stay, just unlinked from the account.');
    }

    public function test_committee_member_role_requires_a_portfolio(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);

        Livewire::actingAs($admin)
            ->test(Index::class)
            ->call('create')
            ->set('name', 'Siti Aminah')
            ->set('email', 'siti@example.com')
            ->set('password', 'password123')
            ->set('role', RoleName::CommitteeMember->value)
            ->call('save')
            ->assertHasErrors(['portfolio_id']);
    }

    public function test_the_full_users_page_renders_with_the_portfolio_column_and_form_fields(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);

        $wanita = Portfolio::factory()->create();
        $committeeMemberUser = User::factory()->create(['portfolio_id' => $wanita->id]);
        $committeeMemberUser->assignRole(RoleName::CommitteeMember->value);

        $this->actingAs($admin)
            ->get(route('users.index'))
            ->assertOk()
            ->assertSee('Portfolio')
            ->assertSee($wanita->name);
    }

    public function test_manager_cannot_access_users_component(): void
    {
        $branch = Branch::factory()->create();

        $manager = User::factory()->create(['branch_id' => $branch->id]);
        $manager->assignRole(RoleName::Manager->value);

        Livewire::actingAs($manager)
            ->test(Index::class)
            ->assertForbidden();
    }
}
