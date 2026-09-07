<?php

namespace Tests\Feature\Livewire\Public;

use App\Enums\EventFormStatus;
use App\Enums\OrganizationStatus;
use App\Models\Event;
use App\Models\EventForm;
use App\Models\Organization;
use App\Support\CurrentOrganization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventRegistrationTenancyTest extends TestCase
{
    use RefreshDatabase;

    public function test_matching_slugs_across_two_organizations_resolve_to_the_correct_events(): void
    {
        $ctx = app(CurrentOrganization::class);

        $orgA = Organization::factory()->create(['slug' => 'org-a']);
        $orgB = Organization::factory()->create(['slug' => 'org-b']);

        // Both organizations independently pick the same slug for their event.
        $eventA = $ctx->runFor($orgA, function () {
            $event = Event::factory()->create(['title' => 'Org A Signup', 'slug' => 'signup']);
            EventForm::factory()->for($event)->published()->create();

            return $event;
        });

        $eventB = $ctx->runFor($orgB, function () {
            $event = Event::factory()->create(['title' => 'Org B Signup', 'slug' => 'signup']);
            EventForm::factory()->for($event)->published()->create();

            return $event;
        });

        $this->get(route('event-registration.show', ['organization' => $orgA, 'eventSlug' => $eventA->slug]))
            ->assertOk()
            ->assertSee('Org A Signup')
            ->assertDontSee('Org B Signup');

        $this->get(route('event-registration.show', ['organization' => $orgB, 'eventSlug' => $eventB->slug]))
            ->assertOk()
            ->assertSee('Org B Signup')
            ->assertDontSee('Org A Signup');
    }

    public function test_an_events_slug_cannot_be_accessed_via_a_different_organizations_url(): void
    {
        $ctx = app(CurrentOrganization::class);

        $orgA = Organization::factory()->create(['slug' => 'org-a']);
        $orgB = Organization::factory()->create(['slug' => 'org-b']);

        $eventA = $ctx->runFor($orgA, function () {
            $event = Event::factory()->create(['slug' => 'only-in-a']);
            EventForm::factory()->for($event)->published()->create();

            return $event;
        });

        // org-b/only-in-a does not exist — orgB has no event with that slug.
        $this->get("/events/{$orgB->slug}/only-in-a/register")->assertNotFound();

        // Sanity check: the real URL for org A works.
        $this->get(route('event-registration.show', ['organization' => $orgA, 'eventSlug' => $eventA->slug]))->assertOk();
    }

    public function test_unpublished_form_returns_404(): void
    {
        $organization = Organization::factory()->create();
        $event = Event::factory()->create(['organization_id' => $organization->id]);
        EventForm::factory()->for($event)->create([
            'organization_id' => $organization->id,
            'status' => EventFormStatus::Draft,
        ]);

        $this->get(route('event-registration.show', ['organization' => $organization, 'eventSlug' => $event->slug]))
            ->assertNotFound();
    }

    public function test_suspended_organization_returns_404(): void
    {
        $organization = Organization::factory()->create(['status' => OrganizationStatus::Suspended]);
        $event = Event::factory()->create(['organization_id' => $organization->id]);
        EventForm::factory()->for($event)->published()->create(['organization_id' => $organization->id]);

        $this->get(route('event-registration.show', ['organization' => $organization, 'eventSlug' => $event->slug]))
            ->assertNotFound();
    }
}
