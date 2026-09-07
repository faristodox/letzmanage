<?php

namespace App\Livewire\Forms;

use App\Enums\FormFieldType;
use App\Models\Form;
use App\Services\FormAnalyticsService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

class Responses extends Component
{
    use WithPagination;

    public Form $form;

    public ?int $viewingId = null;

    public ?int $confirmingDeleteId = null;

    public string $search = '';

    public function mount(Form $form): void
    {
        $this->authorize('viewResponses', $form);

        $this->form = $form;
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function view(int $id): void
    {
        $this->authorize('viewResponses', $this->form);

        $this->viewingId = $id;
    }

    public function closeViewModal(): void
    {
        $this->viewingId = null;
    }

    public function confirmDelete(int $id): void
    {
        $this->authorize('manageResponses', $this->form);

        $this->confirmingDeleteId = $id;
    }

    public function closeDeleteModal(): void
    {
        $this->confirmingDeleteId = null;
    }

    public function delete(): void
    {
        $this->authorize('manageResponses', $this->form);

        $this->form->responses()->findOrFail($this->confirmingDeleteId)->delete();
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
        $this->authorize('viewResponses', $this->form);

        $fields = $this->form->fields()->get();
        $responses = $this->form->responses()->orderBy('created_at')->get();
        $filename = 'responses-'.$this->form->slug.'-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($fields, $responses) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel reads names correctly

            $put = fn (array $row) => fputcsv($out, $row, ',', '"', '');

            $put(['Submitted At', ...$fields->pluck('label')->all()]);

            foreach ($responses as $response) {
                $row = [$response->created_at->format('Y-m-d H:i')];

                foreach ($fields as $field) {
                    $answer = $response->answers[$field->id] ?? null;

                    if (is_array($answer)) {
                        $row[] = implode(', ', $answer);
                    } elseif ($field->type === FormFieldType::Phone) {
                        $row[] = $this->excelText($answer);
                    } elseif ($field->type === FormFieldType::File) {
                        $row[] = $answer ? Storage::url($answer) : '';
                    } else {
                        $row[] = (string) $answer;
                    }
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

    public function render(FormAnalyticsService $analytics)
    {
        $fields = $this->form->fields()->get();
        $allResponses = $this->form->responses()->orderByDesc('created_at')->get();

        $charts = $fields->filter(fn ($field) => $field->type->isChoice())
            ->map(fn ($field) => [
                'field' => $field,
                'chartType' => $field->type->isMultiple() ? 'bar' : 'pie',
                ...$analytics->optionCounts($field, $allResponses),
            ]);

        $numberTotals = $fields->filter(fn ($field) => $field->type === FormFieldType::Number)
            ->map(fn ($field) => [
                'field' => $field,
                'total' => $analytics->sumFor($field, $allResponses),
            ]);

        // Search/pagination run over the already-fetched collection (not a
        // second query) — response counts per form are modest, and this
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

        return view('livewire.forms.responses', [
            'responses' => $responses,
            'fields' => $fields,
            'previewFields' => $fields->take(3),
            'charts' => $charts,
            'numberTotals' => $numberTotals,
            'totalCount' => $allResponses->count(),
            'todayCount' => $allResponses->filter(fn ($r) => $r->created_at->isToday())->count(),
            'weekCount' => $allResponses->filter(fn ($r) => $r->created_at->isAfter(now()->subWeek()))->count(),
        ]);
    }
}
