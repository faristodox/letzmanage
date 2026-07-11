<?php

namespace Tests\Feature\Tenancy;

use App\Models\Branch;
use App\Models\Organization;
use App\Support\CurrentOrganization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationScopingTest extends TestCase
{
    use RefreshDatabase;

    private function context(): CurrentOrganization
    {
        return app(CurrentOrganization::class);
    }

    public function test_queries_are_scoped_to_the_current_organization(): void
    {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();

        Branch::factory()->for($orgA)->count(2)->create();
        Branch::factory()->for($orgB)->count(3)->create();

        $this->context()->set($orgA);
        $this->assertSame(2, Branch::count());

        $this->context()->set($orgB);
        $this->assertSame(3, Branch::count());
    }

    public function test_new_records_are_auto_stamped_with_the_current_organization(): void
    {
        $org = Organization::factory()->create();

        $this->context()->set($org);
        $branch = Branch::create(['name' => 'HQ', 'status' => 'active']);

        $this->assertSame($org->id, $branch->organization_id);
    }

    public function test_no_context_means_no_filtering(): void
    {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();

        Branch::factory()->for($orgA)->count(2)->create();
        Branch::factory()->for($orgB)->count(3)->create();

        $this->context()->clear();

        $this->assertSame(5, Branch::count());
    }

    public function test_across_organizations_scope_bypasses_the_filter(): void
    {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();

        Branch::factory()->for($orgA)->count(2)->create();
        Branch::factory()->for($orgB)->count(3)->create();

        $this->context()->set($orgA);

        $this->assertSame(2, Branch::count());
        $this->assertSame(5, Branch::query()->acrossOrganizations()->count());
    }
}
