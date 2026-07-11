<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Organization;
use App\Models\User;
use App\Support\CurrentOrganization;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardBookingLinkTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_org_user_sees_their_public_booking_link_on_the_dashboard(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme-co']);
        $branch = Branch::factory()->for($org)->create();
        $user = User::factory()->for($org)->create(['branch_id' => $branch->id]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Your public booking link')
            ->assertSee(route('booking.request', 'acme-co'));
    }

    public function test_super_admin_without_org_does_not_see_a_booking_link_card(): void
    {
        $superAdmin = User::factory()->create(['is_super_admin' => true, 'organization_id' => null]);

        app(CurrentOrganization::class)->clear();

        $this->actingAs($superAdmin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Your public booking link');
    }
}
