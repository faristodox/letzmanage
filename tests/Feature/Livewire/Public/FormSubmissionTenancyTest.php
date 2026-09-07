<?php

namespace Tests\Feature\Livewire\Public;

use App\Enums\FormStatus;
use App\Enums\OrganizationStatus;
use App\Enums\RoleName;
use App\Models\Form;
use App\Models\Organization;
use App\Models\User;
use App\Support\CurrentOrganization;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FormSubmissionTenancyTest extends TestCase
{
    use RefreshDatabase;

    public function test_matching_slugs_across_two_organizations_resolve_to_the_correct_forms(): void
    {
        $ctx = app(CurrentOrganization::class);

        $orgA = Organization::factory()->create(['slug' => 'org-a']);
        $orgB = Organization::factory()->create(['slug' => 'org-b']);

        // Both organizations independently pick the same slug for their form.
        $formA = $ctx->runFor($orgA, fn () => Form::factory()->published()->create(['title' => 'Org A Survey', 'slug' => 'survey']));
        $formB = $ctx->runFor($orgB, fn () => Form::factory()->published()->create(['title' => 'Org B Survey', 'slug' => 'survey']));

        $this->get(route('form-submission.show', ['organization' => $orgA, 'formSlug' => $formA->slug]))
            ->assertOk()
            ->assertSee('Org A Survey')
            ->assertDontSee('Org B Survey');

        $this->get(route('form-submission.show', ['organization' => $orgB, 'formSlug' => $formB->slug]))
            ->assertOk()
            ->assertSee('Org B Survey')
            ->assertDontSee('Org A Survey');
    }

    public function test_a_forms_slug_cannot_be_accessed_via_a_different_organizations_url(): void
    {
        $ctx = app(CurrentOrganization::class);

        $orgA = Organization::factory()->create(['slug' => 'org-a']);
        $orgB = Organization::factory()->create(['slug' => 'org-b']);

        $formA = $ctx->runFor($orgA, fn () => Form::factory()->published()->create(['slug' => 'only-in-a']));

        // org-b/only-in-a does not exist — orgB has no form with that slug.
        $this->get("/form/{$orgB->slug}/only-in-a")->assertNotFound();

        // Sanity check: the real URL for org A works.
        $this->get(route('form-submission.show', ['organization' => $orgA, 'formSlug' => $formA->slug]))->assertOk();
    }

    public function test_unpublished_form_returns_404(): void
    {
        $organization = Organization::factory()->create();
        $form = Form::factory()->create([
            'organization_id' => $organization->id,
            'status' => FormStatus::Draft,
        ]);

        $this->get(route('form-submission.show', ['organization' => $organization, 'formSlug' => $form->slug]))
            ->assertNotFound();
    }

    public function test_suspended_organization_returns_404(): void
    {
        $organization = Organization::factory()->create(['status' => OrganizationStatus::Suspended]);
        $form = Form::factory()->published()->create(['organization_id' => $organization->id]);

        $this->get(route('form-submission.show', ['organization' => $organization, 'formSlug' => $form->slug]))
            ->assertNotFound();
    }

    /**
     * The public "form/{organization:slug}/{formSlug}" route and the admin
     * "forms/{form}/builder" route are both 2-segment URIs — this guards
     * against the public route (registered first) swallowing admin requests
     * before Laravel ever reaches the routes below it.
     */
    public function test_admin_builder_route_is_not_swallowed_by_the_public_route(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);

        $form = Form::factory()->create();

        $this->actingAs($admin)
            ->get(route('forms.builder', $form))
            ->assertOk()
            ->assertSee($form->title);
    }
}
