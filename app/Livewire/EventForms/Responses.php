<?php

namespace App\Livewire\EventForms;

use App\Enums\EventCheckInMethod;
use App\Enums\EventFormFieldType;
use App\Models\EventCheckIn;
use App\Models\EventForm;
use App\Services\EventFormAnalyticsService;
use Illuminate\Database\QueryException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

class Responses extends Component
{
    use WithPagination;

    public EventForm $eventForm;

    public ?int $viewingId = null;

    public ?int $confirmingDeleteId = null;

    public string $search = '';

    public function mount(EventForm $eventForm): void
    {
        // viewCheckIn is a superset of viewResponses (it also admits door-duty
        // staff holding only "check in event participants") — the page itself
        // still hides the full response data from them, gated per-section below.
        $this->authorize('viewCheckIn', $eventForm);

        $this->eventForm = $eventForm;
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function checkIn(int $responseId): void
    {
        $this->authorize('manualCheckIn', $this->eventForm);

        $response = $this->eventForm->responses()->findOrFail($responseId);

        if ($response->checkIn) {
            return;
        }

        try {
            EventCheckIn::create([
                'event_form_id' => $this->eventForm->id,
                'event_form_response_id' => $response->id,
                'method' => EventCheckInMethod::Manual,
                'checked_in_by' => auth()->id(),
                'checked_in_at' => now(),
            ]);
        } catch (QueryException) {
            // Unique constraint on event_form_response_id: already checked in
            // (e.g. concurrently via the public check-in page).
        }
    }

    public function undoCheckIn(int $responseId): void
    {
        $this->authorize('undoCheckIn', $this->eventForm);

        $response = $this->eventForm->responses()->findOrFail($responseId);
        $response->checkIn?->delete();
    }

    public function view(int $id): void
    {
        $this->authorize('viewResponses', $this->eventForm);

        $this->viewingId = $id;
    }

    public function closeViewModal(): void
    {
        $this->viewingId = null;
    }

    public function confirmDelete(int $id): void
    {
        $this->authorize('manageResponses', $this->eventForm);

        $this->confirmingDeleteId = $id;
    }

    public function closeDeleteModal(): void
    {
        $this->confirmingDeleteId = null;
    }

    public function delete(): void
    {
        $this->authorize('manageResponses', $this->eventForm);

        $this->eventForm->responses()->findOrFail($this->confirmingDeleteId)->delete();
        $this->confirmingDeleteId = null;
    }

    /**
     * Wrap a value as an Excel text formula so Excel/Sheets keep it as text
     * (e.g. phone numbers keep their leading 0 instead of being read as numbers).
     */
    private function excelText(?string $value): string
    {
        return ($value === null || $value === '') ? '' : '="'.$value.'"';
    }

    public function export(): StreamedResponse
    {
        $this->authorize('viewResponses', $this->eventForm);

        $fields = $this->eventForm->fields()->get();
        $checkinEnabled = $this->eventForm->checkin_enabled;
        $responses = $this->eventForm->responses()->with('checkIn')->orderBy('created_at')->get();
        $filename = 'responses-'.$this->eventForm->event->slug.'-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($fields, $responses, $checkinEnabled) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel reads names correctly

            $put = fn (array $row) => fputcsv($out, $row, ',', '"', '');

            $headers = ['Submitted At', ...$fields->pluck('label')->all()];

            if ($checkinEnabled) {
                $headers = [...$headers, 'Check-in Status', 'Check-in Time', 'Check-in Method'];
            }

            $put($headers);

            foreach ($responses as $response) {
                $row = [$response->created_at->format('Y-m-d H:i')];

                foreach ($fields as $field) {
                    $answer = $response->answers[$field->id] ?? null;

                    if (is_array($answer)) {
                        $row[] = implode(', ', $answer);
                    } elseif ($field->type === EventFormFieldType::Phone) {
                        $row[] = $this->excelText($answer);
                    } else {
                        $row[] = (string) $answer;
                    }
                }

                if ($checkinEnabled) {
                    $row[] = $response->checkIn ? 'Checked In' : 'Not Checked In';
                    $row[] = $response->checkIn?->checked_in_at->format('Y-m-d H:i') ?? '';
                    $row[] = $response->checkIn ? ucfirst($response->checkIn->method->value) : '';
                }

                $put($row);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function matchesSearch(mixed $response): bool
    {
        $needle = Str::lower($this->search);

        if ($response->reference && Str::contains(Str::lower($response->reference), $needle)) {
            return true;
        }

        foreach ($response->answers as $value) {
            if (! is_array($value) && $value !== null && Str::contains(Str::lower((string) $value), $needle)) {
                return true;
            }
        }

        return false;
    }

    public function render(EventFormAnalyticsService $analytics)
    {
        $fields = $this->eventForm->fields()->get();
        $allResponses = $this->eventForm->responses()->with('checkIn')->orderByDesc('created_at')->get();

        $charts = $fields->filter(fn ($field) => $field->type->isChoice())
            ->map(fn ($field) => [
                'field' => $field,
                'chartType' => $field->type->isMultiple() ? 'bar' : 'pie',
                ...$analytics->optionCounts($field, $allResponses),
            ]);

        // Search/pagination run over the already-fetched collection (not a
        // second query) — response counts per event are modest, and this
        // sidesteps MySQL-vs-SQLite JSON-search syntax differences entirely.
        $filtered = $this->search === '' ? $allResponses : $allResponses->filter(fn ($r) => $this->matchesSearch($r))->values();
        $page = $this->getPage();
        $perPage = 15;
        $responses = new LengthAwarePaginator(
            $filtered->forPage($page, $perPage)->values(),
            $filtered->count(),
            $perPage,
            $page,
            ['path' => request()->url(), 'pageName' => 'page']
        );

        $checkedInCount = $allResponses->filter(fn ($r) => $r->checkIn)->count();

        return view('livewire.event-forms.responses', [
            'responses' => $responses,
            'fields' => $fields,
            'previewFields' => $fields->take(3),
            'charts' => $charts,
            'totalCount' => $allResponses->count(),
            'todayCount' => $allResponses->filter(fn ($r) => $r->created_at->isToday())->count(),
            'weekCount' => $allResponses->filter(fn ($r) => $r->created_at->isAfter(now()->subWeek()))->count(),
            'checkedInCount' => $checkedInCount,
            'notCheckedInCount' => $allResponses->count() - $checkedInCount,
            'onsiteCount' => $allResponses->filter(fn ($r) => $r->checkIn?->method === EventCheckInMethod::Onsite)->count(),
        ]);
    }
}
