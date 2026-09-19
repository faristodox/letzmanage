<?php

namespace Tests\Feature\Policies;

use App\Enums\RoleName;
use App\Models\Event;
use App\Models\Portfolio;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_admin_can_manage_events(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);

        $event = Event::factory()->create();

        $this->assertTrue($admin->can('viewAny', Event::class));
        $this->assertTrue($admin->can('create', Event::class));
        $this->assertTrue($admin->can('update', $event));
        $this->assertTrue($admin->can('delete', $event));
        $this->assertTrue($admin->can('viewFinances', $event));
        $this->assertTrue($admin->can('manageFinances', $event));
    }

    public function test_staff_cannot_manage_events_by_default(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole(RoleName::Staff->value);

        $event = Event::factory()->create();

        $this->assertFalse($staff->can('viewAny', Event::class));
        $this->assertFalse($staff->can('create', Event::class));
        $this->assertFalse($staff->can('update', $event));
        $this->assertFalse($staff->can('viewFinances', $event));
        $this->assertFalse($staff->can('manageFinances', $event));
    }

    public function test_view_only_role_can_view_but_not_manage_finances(): void
    {
        $viewer = User::factory()->create();
        $viewer->givePermissionTo('view event responses');

        $event = Event::factory()->create();

        $this->assertTrue($viewer->can('viewFinances', $event));
        $this->assertFalse($viewer->can('manageFinances', $event));
    }

    public function test_committee_member_can_only_manage_their_own_portfolios_events(): void
    {
        $wanita = Portfolio::factory()->create();
        $belia = Portfolio::factory()->create();

        $wanitaMember = User::factory()->create(['portfolio_id' => $wanita->id]);
        $wanitaMember->assignRole(RoleName::CommitteeMember->value);

        $ownEvent = Event::factory()->create(['portfolio_id' => $wanita->id]);
        $otherPortfolioEvent = Event::factory()->create(['portfolio_id' => $belia->id]);
        $unassignedEvent = Event::factory()->create(['portfolio_id' => null]);

        $this->assertTrue($wanitaMember->can('viewAny', Event::class));
        $this->assertTrue($wanitaMember->can('create', Event::class));

        $this->assertTrue($wanitaMember->can('view', $ownEvent));
        $this->assertTrue($wanitaMember->can('update', $ownEvent));
        $this->assertTrue($wanitaMember->can('delete', $ownEvent));

        $this->assertFalse($wanitaMember->can('view', $otherPortfolioEvent));
        $this->assertFalse($wanitaMember->can('update', $otherPortfolioEvent));
        $this->assertFalse($wanitaMember->can('delete', $otherPortfolioEvent));

        $this->assertFalse($wanitaMember->can('view', $unassignedEvent));
        $this->assertFalse($wanitaMember->can('update', $unassignedEvent));
    }

    public function test_admin_and_manager_are_unrestricted_by_portfolio(): void
    {
        $wanita = Portfolio::factory()->create();
        $otherPortfolioEvent = Event::factory()->create(['portfolio_id' => $wanita->id]);

        $admin = User::factory()->create(['portfolio_id' => null]);
        $admin->assignRole(RoleName::Admin->value);

        $manager = User::factory()->create(['portfolio_id' => null]);
        $manager->assignRole(RoleName::Manager->value);

        foreach ([$admin, $manager] as $user) {
            $this->assertTrue($user->can('view', $otherPortfolioEvent));
        }

        // Admin has ManageEventForms via the full-permission grant; Manager
        // also has it explicitly in the seeder — both should be able to
        // update a portfolio's event despite not belonging to it.
        $this->assertTrue($admin->can('update', $otherPortfolioEvent));
        $this->assertTrue($manager->can('update', $otherPortfolioEvent));
    }
}
