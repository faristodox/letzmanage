<?php

namespace App\Livewire\Meetings;

use App\Models\Meeting;
use App\Services\MeetingMinutesPrintService;
use Livewire\Component;

class Show extends Component
{
    public Meeting $meeting;

    public string $language = 'en';

    public bool $editingMinutes = false;

    public string $editMinutesText = '';

    /** @var array<int, array{topic: string, subPoints: string, actionBy: string, notes: string}> */
    public array $editAgendaItems = [];

    public string $editPreparedByName = '';

    public string $editPreparedByPosition = '';

    public string $editConfirmedByName = '';

    public string $editConfirmedByPosition = '';

    public function mount(Meeting $meeting): void
    {
        $this->authorize('view', $meeting);

        $this->meeting = $meeting;
    }

    public function setLanguage(string $language): void
    {
        $this->language = $language === 'ms' ? 'ms' : 'en';
    }

    public function currentMinutes(): ?string
    {
        if ($this->language === 'ms' && $this->meeting->minutes_ms) {
            return $this->meeting->minutes_ms;
        }

        return $this->meeting->minutes;
    }

    /**
     * Loads the current (AI-generated or previously edited) content into the
     * edit form — the AI's output is treated as a first draft, not a final
     * answer, so everything here is just a starting point to correct.
     */
    public function startEditingMinutes(MeetingMinutesPrintService $printService): void
    {
        $this->authorize('update', $this->meeting);

        $this->editMinutesText = (string) $this->currentMinutes();

        $agendaData = ($this->language === 'ms' ? $this->meeting->agenda_items_ms : $this->meeting->agenda_items) ?? [];
        $this->editAgendaItems = collect($agendaData['agenda_items'] ?? [])
            ->map(fn (array $item) => [
                'topic' => $item['topic'] ?? '',
                'subPoints' => implode("\n", $item['sub_points'] ?? []),
                'actionBy' => $item['action_by'] ?? '',
                'notes' => $item['notes'] ?? '',
            ])
            ->all();

        $printData = $printService->build($this->meeting, $this->language);
        $this->editPreparedByName = (string) $printData['preparedBy']['name'];
        $this->editPreparedByPosition = (string) $printData['preparedBy']['position'];
        $this->editConfirmedByName = (string) $printData['confirmedBy']['name'];
        $this->editConfirmedByPosition = (string) $printData['confirmedBy']['position'];

        $this->editingMinutes = true;
    }

    public function addAgendaItem(): void
    {
        $this->editAgendaItems[] = ['topic' => '', 'subPoints' => '', 'actionBy' => '', 'notes' => ''];
    }

    public function removeAgendaItem(int $index): void
    {
        unset($this->editAgendaItems[$index]);
        $this->editAgendaItems = array_values($this->editAgendaItems);
    }

    public function cancelEditingMinutes(): void
    {
        $this->editingMinutes = false;
    }

    public function saveMinutesEdits(): void
    {
        $this->authorize('update', $this->meeting);

        $data = $this->validate([
            'editMinutesText' => ['required', 'string'],
            'editAgendaItems.*.topic' => ['nullable', 'string', 'max:255'],
            'editAgendaItems.*.subPoints' => ['nullable', 'string'],
            'editAgendaItems.*.actionBy' => ['nullable', 'string', 'max:255'],
            'editAgendaItems.*.notes' => ['nullable', 'string'],
            'editPreparedByName' => ['nullable', 'string', 'max:255'],
            'editPreparedByPosition' => ['nullable', 'string', 'max:255'],
            'editConfirmedByName' => ['nullable', 'string', 'max:255'],
            'editConfirmedByPosition' => ['nullable', 'string', 'max:255'],
        ]);

        $agendaItems = collect($this->editAgendaItems)
            ->filter(fn (array $item) => trim($item['topic']) !== '')
            ->map(fn (array $item) => [
                'topic' => trim($item['topic']),
                'sub_points' => collect(preg_split('/\r\n|\r|\n/', $item['subPoints']))
                    ->map(fn ($line) => trim($line))->filter()->values()->all(),
                'action_by' => trim($item['actionBy']),
                'notes' => trim($item['notes']),
            ])
            ->values()
            ->all();

        // The fallback attendee list (used on the print page only when there's
        // no confirmed check-in) isn't editable here — preserve whatever's
        // already there instead of wiping it out when saving agenda edits.
        $agendaField = $this->language === 'ms' ? 'agenda_items_ms' : 'agenda_items';
        $existingAttendees = ($this->meeting->{$agendaField} ?? [])['attendees'] ?? [];

        $minutesField = $this->language === 'ms' ? 'minutes_ms' : 'minutes';

        $this->meeting->update([
            $minutesField => $data['editMinutesText'],
            $agendaField => ['attendees' => $existingAttendees, 'agenda_items' => $agendaItems],
            'prepared_by_name' => $data['editPreparedByName'] ?: null,
            'prepared_by_position' => $data['editPreparedByPosition'] ?: null,
            'confirmed_by_name' => $data['editConfirmedByName'] ?: null,
            'confirmed_by_position' => $data['editConfirmedByPosition'] ?: null,
        ]);

        $this->editingMinutes = false;
    }

    public function delete()
    {
        $this->authorize('delete', $this->meeting);

        $this->meeting->delete();

        return $this->redirect(route('meetings.index'), navigate: true);
    }

    public function downloadTranscript()
    {
        $this->authorize('view', $this->meeting);

        return response()->streamDownload(
            fn () => print ((string) $this->meeting->transcript),
            "{$this->meeting->title}-transcript.txt",
        );
    }

    public function downloadMinutes()
    {
        $this->authorize('view', $this->meeting);

        $suffix = $this->language === 'ms' && $this->meeting->minutes_ms ? '-minutes-ms' : '-minutes';

        return response()->streamDownload(
            fn () => print ((string) $this->currentMinutes()),
            "{$this->meeting->title}{$suffix}.txt",
        );
    }

    public function render()
    {
        // Stop wire:poll once the pipeline reaches a terminal state — no
        // point polling a Meeting that's already Ready or Failed.
        $this->meeting->refresh();

        return view('livewire.meetings.show');
    }
}
