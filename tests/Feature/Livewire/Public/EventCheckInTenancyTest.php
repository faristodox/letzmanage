<?php

namespace Tests\Feature\Livewire\Public;

use App\Enums\OrganizationStatus;
use App\Models\Event;
use App\Models\EventForm;
use App\Models\Organization;
use App\Support\CurrentOrganization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventCheckInTenancyTest extends TestCase
{
    use RefreshDatabase;

    public function test_matching_slugs_across_two_organizations_resolve_to_the_correct_events_checkin_page(): void
    {
        $ctx = app(CurrentOrganization::class);

        $orgA = Organization::factory()->create(['slug' => 'org-a']);
        $orgB = Organization::factory()->create(['slug' => 'org-b']);

        $eventA = $ctx->runFor($orgA, function () {
            $event = Event::factory()->create(['title' => 'Org A Event', 'slug' => 'signup']);
            EventForm::factory()->for($event)->checkinEnabled()->create();

            return $event;
        });

        $eventB = $ctx->runFor($orgB, function () {
            $event = Event::factory()->create(['title' => 'Org B Event', 'slug' => 'signup']);
            EventForm::factory()->for($event)->checkinEnabled()->create();

            return $event;
        });

        $this->get(route('event-checkin.show', ['organization' => $orgA, 'eventSlug' => $eventA->slug]))
            ->assertOk()
            ->assertSee('Org A Event')
            ->assertDontSee('Org B Event');

        $this->get(route('event-checkin.show', ['organization' => $orgB, 'eventSlug' => $eventB->slug]))
            ->assertOk()
            ->assertSee('Org B Event')
            ->assertDontSee('Org A Event');
    }

    public function test_checkin_disabled_returns_404(): void
    {
        $organization = Organization::factory()->create();
        $event = Event::factory()->create(['organization_id' => $organization->id]);
        EventForm::factory()->for($event)->create(['organization_id' => $organization->id, 'checkin_enabled' => false]);

        $this->get(route('event-checkin.show', ['organization' => $organization, 'eventSlug' => $event->slug]))
            ->assertNotFound();
    }

    public function test_suspended_organization_returns_404(): void
    {
        $organization = Organization::factory()->create(['status' => OrganizationStatus::Suspended]);
        $event = Event::factory()->create(['organization_id' => $organization->id]);
        EventForm::factory()->for($event)->checkinEnabled()->create(['organization_id' => $organization->id]);

        $this->get(route('event-checkin.show', ['organization' => $organization, 'eventSlug' => $event->slug]))
            ->assertNotFound();
    }
}
