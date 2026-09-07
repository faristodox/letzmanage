<?php

namespace Tests\Feature;

use App\Enums\EventFormStatus;
use App\Models\Event;
use App\Models\EventForm;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventFeedbackRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_published_feedback_survey_is_reachable(): void
    {
        $organization = Organization::factory()->create();
        $event = Event::factory()->create(['organization_id' => $organization->id, 'title' => 'Annual Dinner']);
        EventForm::factory()->for($event)->create(['organization_id' => $organization->id]); // registration form
        EventForm::factory()->for($event)->feedback()->published()->create(['organization_id' => $organization->id]);

        $this->get(route('event-feedback.show', ['organization' => $organization, 'eventSlug' => $event->slug]))
            ->assertOk()
            ->assertSee('Annual Dinner');
    }

    public function test_returns_404_when_no_feedback_survey_exists_yet(): void
    {
        $organization = Organization::factory()->create();
        $event = Event::factory()->create(['organization_id' => $organization->id]);
        EventForm::factory()->for($event)->published()->create(['organization_id' => $organization->id]);

        $this->get(route('event-feedback.show', ['organization' => $organization, 'eventSlug' => $event->slug]))
            ->assertNotFound();
    }

    public function test_returns_404_when_feedback_survey_is_still_draft(): void
    {
        $organization = Organization::factory()->create();
        $event = Event::factory()->create(['organization_id' => $organization->id]);
        EventForm::factory()->for($event)->feedback()->create([
            'organization_id' => $organization->id,
            'status' => EventFormStatus::Draft,
        ]);

        $this->get(route('event-feedback.show', ['organization' => $organization, 'eventSlug' => $event->slug]))
            ->assertNotFound();
    }
}
