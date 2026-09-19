<?php

namespace Tests\Feature\Policies;

use App\Enums\RoleName;
use App\Models\Event;
use App\Models\Meeting;
use App\Models\Portfolio;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MeetingPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_admin_can_manage_meetings(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);

        $meeting = Meeting::factory()->create();

        $this->assertTrue($admin->can('viewAny', Meeting::class));
        $this->assertTrue($admin->can('create', Meeting::class));
        $this->assertTrue($admin->can('view', $meeting));
        $this->assertTrue($admin->can('delete', $meeting));
    }

    public function test_manager_can_manage_meetings(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole(RoleName::Manager->value);

        $meeting = Meeting::factory()->create();

        $this->assertTrue($manager->can('viewAny', Meeting::class));
        $this->assertTrue($manager->can('create', Meeting::class));
        $this->assertTrue($manager->can('view', $meeting));
        $this->assertTrue($manager->can('delete', $meeting));
    }

    public function test_staff_cannot_manage_meetings_by_default(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole(RoleName::Staff->value);

        $meeting = Meeting::factory()->create();

        $this->assertFalse($staff->can('viewAny', Meeting::class));
        $this->assertFalse($staff->can('create', Meeting::class));
        $this->assertFalse($staff->can('view', $meeting));
        $this->assertFalse($staff->can('delete', $meeting));
    }

    public function test_committee_member_can_only_manage_meetings_linked_to_their_own_portfolios_events(): void
    {
        $wanita = Portfolio::factory()->create();
        $belia = Portfolio::factory()->create();

        $wanitaMember = User::factory()->create(['portfolio_id' => $wanita->id]);
        $wanitaMember->assignRole(RoleName::CommitteeMember->value);

        $ownEvent = Event::factory()->create(['portfolio_id' => $wanita->id]);
        $otherPortfolioEvent = Event::factory()->create(['portfolio_id' => $belia->id]);

        $ownMeeting = Meeting::factory()->create(['event_id' => $ownEvent->id]);
        $otherPortfolioMeeting = Meeting::factory()->create(['event_id' => $otherPortfolioEvent->id]);
        $standaloneMeeting = Meeting::factory()->create(['event_id' => null]);

        $this->assertTrue($wanitaMember->can('viewAny', Meeting::class));
        $this->assertTrue($wanitaMember->can('create', Meeting::class));

        $this->assertTrue($wanitaMember->can('view', $ownMeeting));
        $this->assertTrue($wanitaMember->can('delete', $ownMeeting));

        $this->assertFalse($wanitaMember->can('view', $otherPortfolioMeeting));
        $this->assertFalse($wanitaMember->can('delete', $otherPortfolioMeeting));

        $this->assertFalse($wanitaMember->can('view', $standaloneMeeting));
        $this->assertFalse($wanitaMember->can('delete', $standaloneMeeting));
    }

    public function test_admin_and_manager_are_unrestricted_by_portfolio_for_meetings(): void
    {
        $wanita = Portfolio::factory()->create();
        $event = Event::factory()->create(['portfolio_id' => $wanita->id]);
        $meeting = Meeting::factory()->create(['event_id' => $event->id]);

        $admin = User::factory()->create(['portfolio_id' => null]);
        $admin->assignRole(RoleName::Admin->value);

        $manager = User::factory()->create(['portfolio_id' => null]);
        $manager->assignRole(RoleName::Manager->value);

        foreach ([$admin, $manager] as $user) {
            $this->assertTrue($user->can('view', $meeting));
            $this->assertTrue($user->can('delete', $meeting));
        }
    }
}
