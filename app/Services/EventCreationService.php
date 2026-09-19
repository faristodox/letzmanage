<?php

namespace App\Services;

use App\Enums\EventFormStatus;
use App\Enums\EventFormType;
use App\Enums\EventStatus;
use App\Enums\EventType;
use App\Models\Event;
use App\Models\EventForm;

/**
 * Creates a new Event plus its registration EventForm together — the one
 * canonical event-creation sequence in this app, shared by the Events
 * list's "New Event" modal and the booking Calendar's "+" quick-create
 * modal, so both stay wired to the exact same event/registration-form shape.
 */
class EventCreationService
{
    /**
     * @param  array{title: string, type?: EventType, start_date?: ?string, start_time?: ?string, end_date?: ?string, end_time?: ?string, location?: ?string}  $attributes
     */
    public function createWithRegistrationForm(array $attributes): EventForm
    {
        $event = Event::create([
            ...$attributes,
            'slug' => Event::uniqueSlug($attributes['title']),
            'status' => EventStatus::Draft,
            'created_by' => auth()->id(),
            'portfolio_id' => auth()->user()->portfolio_id,
        ]);

        return EventForm::create([
            'event_id' => $event->id,
            'type' => EventFormType::Registration,
            'status' => EventFormStatus::Draft,
            'created_by' => auth()->id(),
        ]);
    }
}
