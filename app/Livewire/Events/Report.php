<?php

namespace App\Livewire\Events;

use App\Models\Event;
use App\Services\EventFormAnalyticsService;
use App\Services\EventReportService;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;
use Symfony\Component\HttpFoundation\StreamedResponse;

class Report extends Component
{
    use WithFileUploads;

    public Event $event;

    public string $eventDate = '';

    public string $eventTime = '';

    public string $theme = '';

    public string $venue = '';

    public string $objectives = '';

    public string $problems = '';

    public string $achievements = '';

    public string $directorsRemarks = '';

    /** @var array<int, array{time: string, activity: string}> */
    public array $itineraryRows = [];

    public string $preparedByName = '';

    public string $preparedByPosition = '';

    public string $preparedByDate = '';

    public $preparedBySignature = null;

    public ?string $existingPreparedBySignaturePath = null;

    public bool $removePreparedBySignature = false;

    public string $reviewedByName = '';

    public string $reviewedByPosition = '';

    public string $reviewedByDate = '';

    public $reviewedBySignature = null;

    public ?string $existingReviewedBySignaturePath = null;

    public bool $removeReviewedBySignature = false;

    public string $approvedByName = '';

    public string $approvedByPosition = '';

    public string $approvedByDate = '';

    public $approvedBySignature = null;

    public ?string $existingApprovedBySignaturePath = null;

    public bool $removeApprovedBySignature = false;

    public function mount(Event $event): void
    {
        $this->authorize('viewFinances', $event);

        $this->event = $event;
        $this->loadReportDetails();
    }

    private function loadReportDetails(): void
    {
        $detail = $this->event->reportDetail()->first();

        $this->eventDate = $detail?->event_date?->format('Y-m-d') ?? '';
        $this->eventTime = $detail?->event_time ?? '';
        $this->theme = $detail?->theme ?? '';
        $this->venue = $detail?->venue ?? '';
        $this->objectives = $detail?->objectives ?? '';
        $this->problems = $detail?->problems ?? '';
        $this->achievements = $detail?->achievements ?? '';
        $this->directorsRemarks = $detail?->directors_remarks ?? '';
        $this->preparedByName = $detail?->prepared_by_name ?? '';
        $this->preparedByPosition = $detail?->prepared_by_position ?? '';
        $this->preparedByDate = $detail?->prepared_by_date?->format('Y-m-d') ?? '';
        $this->reviewedByName = $detail?->reviewed_by_name ?? '';
        $this->reviewedByPosition = $detail?->reviewed_by_position ?? '';
        $this->reviewedByDate = $detail?->reviewed_by_date?->format('Y-m-d') ?? '';
        $this->approvedByName = $detail?->approved_by_name ?? '';
        $this->approvedByPosition = $detail?->approved_by_position ?? '';
        $this->approvedByDate = $detail?->approved_by_date?->format('Y-m-d') ?? '';

        $this->existingPreparedBySignaturePath = $detail?->prepared_by_signature_path;
        $this->existingReviewedBySignaturePath = $detail?->reviewed_by_signature_path;
        $this->existingApprovedBySignaturePath = $detail?->approved_by_signature_path;
        $this->preparedBySignature = null;
        $this->reviewedBySignature = null;
        $this->approvedBySignature = null;
        $this->removePreparedBySignature = false;
        $this->removeReviewedBySignature = false;
        $this->removeApprovedBySignature = false;

        $rows = $this->event->itineraryItems()->get()
            ->map(fn ($item) => ['time' => (string) $item->time, 'activity' => (string) $item->activity])
            ->all();

        $this->itineraryRows = $rows !== [] ? $rows : [['time' => '', 'activity' => '']];
    }

    public function addItineraryRow(): void
    {
        $this->authorize('update', $this->event);

        $this->itineraryRows[] = ['time' => '', 'activity' => ''];
    }

    public function removeItineraryRow(int $index): void
    {
        $this->authorize('update', $this->event);

        unset($this->itineraryRows[$index]);
        $this->itineraryRows = array_values($this->itineraryRows);

        if ($this->itineraryRows === []) {
            $this->itineraryRows = [['time' => '', 'activity' => '']];
        }
    }

    /**
     * Resolves the path to persist for one signature: a fresh upload replaces
     * (and deletes) the current file, a removal request clears it, otherwise
     * the current path is kept as-is.
     */
    private function resolveSignaturePath($upload, bool $remove, ?string $currentPath): ?string
    {
        if ($upload) {
            if ($currentPath) {
                Storage::disk('public')->delete($currentPath);
            }

            return $upload->store('event-reports', 'public');
        }

        if ($remove && $currentPath) {
            Storage::disk('public')->delete($currentPath);

            return null;
        }

        return $currentPath;
    }

    public function saveReportDetails(): void
    {
        $this->authorize('update', $this->event);

        $data = $this->validate([
            'eventDate' => ['nullable', 'date'],
            'eventTime' => ['nullable', 'string', 'max:255'],
            'theme' => ['nullable', 'string', 'max:255'],
            'venue' => ['nullable', 'string', 'max:255'],
            'objectives' => ['nullable', 'string'],
            'problems' => ['nullable', 'string'],
            'achievements' => ['nullable', 'string'],
            'directorsRemarks' => ['nullable', 'string'],
            'preparedByName' => ['nullable', 'string', 'max:255'],
            'preparedByPosition' => ['nullable', 'string', 'max:255'],
            'preparedByDate' => ['nullable', 'date'],
            'preparedBySignature' => ['nullable', 'image', 'max:2048'],
            'reviewedByName' => ['nullable', 'string', 'max:255'],
            'reviewedByPosition' => ['nullable', 'string', 'max:255'],
            'reviewedByDate' => ['nullable', 'date'],
            'reviewedBySignature' => ['nullable', 'image', 'max:2048'],
            'approvedByName' => ['nullable', 'string', 'max:255'],
            'approvedByPosition' => ['nullable', 'string', 'max:255'],
            'approvedByDate' => ['nullable', 'date'],
            'approvedBySignature' => ['nullable', 'image', 'max:2048'],
            'itineraryRows.*.time' => ['nullable', 'string', 'max:255'],
            'itineraryRows.*.activity' => ['nullable', 'string', 'max:255'],
        ]);

        $detail = $this->event->reportDetail()->first();

        $preparedBySignaturePath = $this->resolveSignaturePath($this->preparedBySignature, $this->removePreparedBySignature, $detail?->prepared_by_signature_path);
        $reviewedBySignaturePath = $this->resolveSignaturePath($this->reviewedBySignature, $this->removeReviewedBySignature, $detail?->reviewed_by_signature_path);
        $approvedBySignaturePath = $this->resolveSignaturePath($this->approvedBySignature, $this->removeApprovedBySignature, $detail?->approved_by_signature_path);

        $this->event->reportDetail()->updateOrCreate([], [
            'event_date' => $data['eventDate'] ?: null,
            'event_time' => $data['eventTime'] ?: null,
            'theme' => $data['theme'] ?: null,
            'venue' => $data['venue'] ?: null,
            'objectives' => $data['objectives'] ?: null,
            'problems' => $data['problems'] ?: null,
            'achievements' => $data['achievements'] ?: null,
            'directors_remarks' => $data['directorsRemarks'] ?: null,
            'prepared_by_name' => $data['preparedByName'] ?: null,
            'prepared_by_position' => $data['preparedByPosition'] ?: null,
            'prepared_by_date' => $data['preparedByDate'] ?: null,
            'prepared_by_signature_path' => $preparedBySignaturePath,
            'reviewed_by_name' => $data['reviewedByName'] ?: null,
            'reviewed_by_position' => $data['reviewedByPosition'] ?: null,
            'reviewed_by_date' => $data['reviewedByDate'] ?: null,
            'reviewed_by_signature_path' => $reviewedBySignaturePath,
            'approved_by_name' => $data['approvedByName'] ?: null,
            'approved_by_position' => $data['approvedByPosition'] ?: null,
            'approved_by_date' => $data['approvedByDate'] ?: null,
            'approved_by_signature_path' => $approvedBySignaturePath,
        ]);

        $this->event->itineraryItems()->delete();

        $order = 0;

        foreach ($this->itineraryRows as $row) {
            $time = trim((string) ($row['time'] ?? ''));
            $activity = trim((string) ($row['activity'] ?? ''));

            if ($time === '' && $activity === '') {
                continue;
            }

            $this->event->itineraryItems()->create([
                'time' => $time ?: null,
                'activity' => $activity ?: null,
                'order' => $order++,
            ]);
        }

        $this->loadReportDetails();
    }

    public function export(EventFormAnalyticsService $analytics, EventReportService $reportService): StreamedResponse
    {
        $this->authorize('viewFinances', $this->event);

        $data = $reportService->build($this->event, $analytics);
        $filename = 'report-'.$this->event->slug.'-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($data) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel reads names correctly

            $put = fn (array $row = []) => fputcsv($out, $row, ',', '"', '');

            if ($data['registrationForm']) {
                $put(['Registration & Check-in']);
                $put(['Total Registered', $data['totalRegistered']]);

                if ($data['registrationForm']->checkin_enabled) {
                    $put(['Checked In', $data['checkedInCount']]);
                    $put(['Not Checked In', $data['notCheckedInCount']]);
                    $put(['On-site Registrations', $data['onsiteCount']]);
                }

                $put();
            }

            $put(['Financial Summary']);
            $put(['Total Income', number_format($data['totalIncome'], 2)]);
            $put(['Total Expenses', number_format($data['totalExpenses'], 2)]);
            $put(['Net Balance', number_format($data['netBalance'], 2)]);
            $put();

            if ($data['incomeByCategory']->isNotEmpty()) {
                $put(['Income by Category']);
                foreach ($data['incomeByCategory'] as $category => $amount) {
                    $put([$category, number_format($amount, 2)]);
                }
                $put();
            }

            if ($data['expensesByCategory']->isNotEmpty()) {
                $put(['Expenses by Category']);
                foreach ($data['expensesByCategory'] as $category => $amount) {
                    $put([$category, number_format($amount, 2)]);
                }
                $put();
            }

            if ($data['feedbackForm']) {
                $put(['Feedback']);
                $put(['Total Responses', $data['feedbackResponseCount']]);

                foreach ($data['charts'] as $chart) {
                    $put([$chart['field']->label]);
                    foreach ($chart['labels'] as $i => $label) {
                        $put([$label, $chart['data'][$i] ?? 0]);
                    }
                }
                $put();
            }

            $detail = $data['reportDetail'];

            if ($detail) {
                $put(['Program Details']);
                if ($detail->event_date) {
                    $put(['Event Date', $detail->event_date->format('Y-m-d')]);
                }
                if ($detail->event_time) {
                    $put(['Event Time', $detail->event_time]);
                }
                if ($detail->theme) {
                    $put(['Theme', $detail->theme]);
                }
                if ($detail->venue) {
                    $put(['Venue', $detail->venue]);
                }
                if ($detail->objectives) {
                    $put(['Objectives', $detail->objectives]);
                }
                if ($detail->problems) {
                    $put(['Problems', $detail->problems]);
                }
                if ($detail->achievements) {
                    $put(['Achievements', $detail->achievements]);
                }
                if ($detail->directors_remarks) {
                    $put(["Director's Remarks", $detail->directors_remarks]);
                }
                $put();
            }

            if ($data['itineraryItems']->isNotEmpty()) {
                $put(['Itinerary']);
                foreach ($data['itineraryItems'] as $item) {
                    $put([$item->time, $item->activity]);
                }
                $put();
            }

            if ($detail && ($detail->prepared_by_name || $detail->reviewed_by_name || $detail->approved_by_name)) {
                $put(['Sign-off']);
                if ($detail->prepared_by_name) {
                    $put(['Prepared By', $detail->prepared_by_name, $detail->prepared_by_position, $detail->prepared_by_date?->format('Y-m-d')]);
                }
                if ($detail->reviewed_by_name) {
                    $put(['Reviewed By', $detail->reviewed_by_name, $detail->reviewed_by_position, $detail->reviewed_by_date?->format('Y-m-d')]);
                }
                if ($detail->approved_by_name) {
                    $put(['Approved By', $detail->approved_by_name, $detail->approved_by_position, $detail->approved_by_date?->format('Y-m-d')]);
                }
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function render(EventFormAnalyticsService $analytics, EventReportService $reportService)
    {
        return view('livewire.events.report', [
            'event' => $this->event,
            ...$reportService->build($this->event, $analytics),
        ]);
    }
}
