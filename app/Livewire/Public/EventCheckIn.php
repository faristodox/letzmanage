<?php

namespace App\Livewire\Public;

use App\Enums\CheckInVerificationMode;
use App\Enums\EventCheckInMethod;
use App\Models\EventCheckIn as EventCheckInModel;
use App\Models\EventForm;
use App\Models\EventFormResponse;
use App\Models\Organization;
use App\Support\CurrentOrganization;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Livewire\Component;

class EventCheckIn extends Component
{
    public ?int $organizationId = null;

    public ?int $eventFormId = null;

    public string $step = 'verify';

    public array $verify = [];

    public ?int $matchedResponseId = null;

    public string $method = 'link';

    /**
     * Runs on every Livewire request (initial + updates). Re-establishes the
     * organization (tenant) context so all queries stay scoped to the org whose
     * check-in page this is — the slug isn't present on update requests.
     */
    public function boot(): void
    {
        if ($this->organizationId) {
            app(CurrentOrganization::class)->set(Organization::find($this->organizationId));
        }
    }

    public function mount(EventForm $eventForm): void
    {
        $this->organizationId = app(CurrentOrganization::class)->id();
        $this->eventFormId = $eventForm->id;
        $this->method = request()->query('src') === 'qr' ? EventCheckInMethod::Qr->value : EventCheckInMethod::Link->value;
    }

    private function eventForm(): EventForm
    {
        return EventForm::with(['fields', 'event'])->findOrFail($this->eventFormId);
    }

    private function rulesFor(EventForm $eventForm): array
    {
        return $eventForm->checkinFields()
            ->mapWithKeys(fn ($field) => ["verify.{$field->id}" => ['required', 'string', 'max:255']])
            ->all();
    }

    private function valuesMatch(mixed $stored, mixed $submitted): bool
    {
        if ($stored === null || $submitted === null || $submitted === '' || is_array($stored)) {
            return false;
        }

        return Str::lower(trim((string) $stored)) === Str::lower(trim((string) $submitted));
    }

    private function findMatch(EventForm $eventForm): ?EventFormResponse
    {
        $fieldIds = $eventForm->checkin_verification_field_ids ?? [];

        return $eventForm->responses()->get()->first(function (EventFormResponse $response) use ($fieldIds, $eventForm) {
            $results = collect($fieldIds)->map(
                fn ($id) => $this->valuesMatch($response->answers[$id] ?? null, $this->verify[$id] ?? null)
            );

            return $eventForm->checkin_verification_mode === CheckInVerificationMode::All
                ? $results->isNotEmpty() && $results->every(fn ($m) => $m)
                : $results->contains(true);
        });
    }

    public function submitVerification(): void
    {
        $eventForm = $this->eventForm();

        if (! $eventForm->isCheckinOpen()) {
            return;
        }

        $this->validate($this->rulesFor($eventForm));

        $match = $this->findMatch($eventForm);

        if (! $match) {
            $this->step = 'not_found';

            return;
        }

        $this->matchedResponseId = $match->id;
        $this->step = $match->checkIn ? 'already' : 'found';
    }

    public function confirmCheckIn(): void
    {
        $eventForm = $this->eventForm();

        if (! $eventForm->isCheckinOpen() || ! $this->matchedResponseId) {
            return;
        }

        $response = EventFormResponse::findOrFail($this->matchedResponseId);

        if ($response->checkIn) {
            $this->step = 'already';

            return;
        }

        try {
            EventCheckInModel::create([
                'event_form_id' => $eventForm->id,
                'event_form_response_id' => $response->id,
                'method' => $this->method,
                'checked_in_at' => now(),
            ]);
        } catch (QueryException) {
            // Unique constraint on event_form_response_id: someone else checked
            // this response in between the find and the create.
        }

        $this->step = 'success';
    }

    public function startOnsiteRegistration(): void
    {
        $eventForm = $this->eventForm();

        if (! $eventForm->checkin_onsite_registration_enabled) {
            return;
        }

        $this->step = 'register';
    }

    #[On('event-form-submitted')]
    public function onRegistered(int $responseId): void
    {
        $eventForm = $this->eventForm();
        $response = EventFormResponse::findOrFail($responseId);

        try {
            EventCheckInModel::create([
                'event_form_id' => $eventForm->id,
                'event_form_response_id' => $response->id,
                'method' => EventCheckInMethod::Onsite->value,
                'checked_in_at' => now(),
            ]);
        } catch (QueryException) {
            // Already checked in somehow; fall through to the success screen anyway.
        }

        $this->matchedResponseId = $response->id;
        $this->step = 'success';
    }

    public function render()
    {
        $eventForm = $this->eventForm();

        return view('livewire.public.event-checkin', [
            'eventForm' => $eventForm,
            'event' => $eventForm->event,
            'checkinFields' => $eventForm->checkinFields(),
            'matchedResponse' => $this->matchedResponseId ? EventFormResponse::find($this->matchedResponseId) : null,
        ]);
    }
}
