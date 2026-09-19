<?php

namespace Tests\Feature\Livewire;

use App\Enums\EventType;
use App\Enums\RoleName;
use App\Livewire\CommitteeMembers\Index;
use App\Models\CommitteeMember;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Portfolio;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CommitteeMembersIndexTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function admin(Organization $organization): User
    {
        $admin = User::factory()->create(['organization_id' => $organization->id]);
        $admin->assignRole(RoleName::Admin->value);

        return $admin;
    }

    public function test_staff_without_permission_is_forbidden(): void
    {
        $organization = Organization::factory()->create();
        $staff = User::factory()->create(['organization_id' => $organization->id]);
        $staff->assignRole(RoleName::Staff->value);

        Livewire::actingAs($staff)
            ->test(Index::class)
            ->assertForbidden();
    }

    public function test_admin_can_add_a_committee_member(): void
    {
        $organization = Organization::factory()->create();

        Livewire::actingAs($this->admin($organization))
            ->test(Index::class)
            ->call('create')
            ->set('name', 'Ahmad Zaki')
            ->set('position', 'President')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('committee_members', [
            'organization_id' => $organization->id,
            'name' => 'Ahmad Zaki',
            'position' => 'President',
        ]);
    }

    public function test_admin_can_add_a_committee_member_to_a_portfolio(): void
    {
        $organization = Organization::factory()->create();
        $wanita = Portfolio::factory()->for($organization)->create();

        Livewire::actingAs($this->admin($organization))
            ->test(Index::class)
            ->call('create')
            ->set('name', 'Ahmad Zaki')
            ->set('position', 'President')
            ->set('portfolio_id', $wanita->id)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('committee_members', [
            'organization_id' => $organization->id,
            'name' => 'Ahmad Zaki',
            'portfolio_id' => $wanita->id,
        ]);
    }

    public function test_the_full_committee_members_page_renders_with_the_portfolio_and_linked_account_columns(): void
    {
        $organization = Organization::factory()->create();
        $wanita = Portfolio::factory()->for($organization)->create();
        $linkedUser = User::factory()->create(['organization_id' => $organization->id, 'portfolio_id' => $wanita->id]);
        CommitteeMember::factory()->create([
            'organization_id' => $organization->id,
            'portfolio_id' => $wanita->id,
            'user_id' => $linkedUser->id,
        ]);

        $this->actingAs($this->admin($organization))
            ->get(route('committee-members.index'))
            ->assertOk()
            ->assertSee('Portfolio')
            ->assertSee('Linked Account')
            ->assertSee($linkedUser->email);
    }

    public function test_validation_requires_name_and_position(): void
    {
        $organization = Organization::factory()->create();

        Livewire::actingAs($this->admin($organization))
            ->test(Index::class)
            ->call('create')
            ->set('name', '')
            ->set('position', '')
            ->call('save')
            ->assertHasErrors(['name', 'position']);
    }

    public function test_admin_can_edit_a_committee_member(): void
    {
        $organization = Organization::factory()->create();
        $committeeMember = CommitteeMember::factory()->for($organization)->create(['name' => 'Old Name', 'position' => 'Secretary']);

        Livewire::actingAs($this->admin($organization))
            ->test(Index::class)
            ->call('edit', $committeeMember)
            ->assertSet('name', 'Old Name')
            ->set('name', 'New Name')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('committee_members', [
            'id' => $committeeMember->id,
            'name' => 'New Name',
            'position' => 'Secretary',
        ]);
    }

    public function test_admin_can_delete_a_committee_member(): void
    {
        $organization = Organization::factory()->create();
        $committeeMember = CommitteeMember::factory()->for($organization)->create();

        Livewire::actingAs($this->admin($organization))
            ->test(Index::class)
            ->call('delete', $committeeMember);

        $this->assertDatabaseMissing('committee_members', ['id' => $committeeMember->id]);
    }

    public function test_admin_can_view_a_members_attendance_history(): void
    {
        $organization = Organization::factory()->create();
        $committeeMember = CommitteeMember::factory()->for($organization)->create();
        $event = Event::factory()->for($organization)->create(['type' => EventType::CommitteeMeeting, 'title' => 'Board Meeting Q1']);
        $event->attendees()->create(['committee_member_id' => $committeeMember->id, 'checked_in_at' => now()]);

        Livewire::actingAs($this->admin($organization))
            ->test(Index::class)
            ->call('viewAttendance', $committeeMember)
            ->assertSee('Board Meeting Q1');
    }
}
