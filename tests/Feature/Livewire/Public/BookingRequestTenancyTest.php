<?php

namespace Tests\Feature\Livewire\Public;

use App\Livewire\Public\BookingRequest;
use App\Models\Branch;
use App\Models\OfficeSpace;
use App\Models\Organization;
use App\Support\CurrentOrganization;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BookingRequestTenancyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_public_booking_page_only_shows_its_own_organizations_spaces(): void
    {
        $ctx = app(CurrentOrganization::class);

        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();

        // Build each org's data inside its own tenant context so related records
        // (branch, office space type) are stamped to the same org.
        $spaceA = $ctx->runFor($orgA, function () {
            $branch = Branch::factory()->create();

            return OfficeSpace::factory()->create(['branch_id' => $branch->id, 'name' => 'Alpha Room']);
        });

        $spaceB = $ctx->runFor($orgB, function () {
            $branch = Branch::factory()->create();

            return OfficeSpace::factory()->create(['branch_id' => $branch->id, 'name' => 'Bravo Room']);
        });

        // Simulate the /book/{org-slug} route setting the tenant context.
        $ctx->set($orgA);

        $component = Livewire::test(BookingRequest::class)
            ->assertSet('organizationId', $orgA->id);

        $spaces = $component->viewData('spaces');

        $this->assertTrue($spaces->contains('id', $spaceA->id), 'Should see own org space');
        $this->assertFalse($spaces->contains('id', $spaceB->id), 'Must NOT see other org space');
    }

    public function test_guest_cannot_select_a_space_from_another_organization(): void
    {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();

        $branchB = Branch::factory()->for($orgB)->create();
        $spaceB = OfficeSpace::factory()->for($orgB)->create(['branch_id' => $branchB->id]);

        app(CurrentOrganization::class)->set($orgA);

        // Attempt to select org B's space while on org A's booking page.
        Livewire::test(BookingRequest::class)
            ->call('selectSpace', $spaceB->id)
            ->assertSet('space_id', null)
            ->assertSet('step', 1);
    }
}
