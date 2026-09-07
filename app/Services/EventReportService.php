<?php

namespace App\Services;

use App\Enums\EventCheckInMethod;
use App\Enums\EventFormFieldType;
use App\Enums\EventTransactionType;
use App\Models\Event;

/**
 * Aggregates everything the Event Report page (and its printable/CSV
 * outputs) needs: registration/check-in stats, financial totals, feedback
 * analysis, and the narrative program-report content (details + itinerary).
 * Kept separate from the Livewire component so the print route can build the
 * exact same data without going through Livewire.
 */
class EventReportService
{
    /**
     * @return array<string, mixed>
     */
    public function build(Event $event, EventFormAnalyticsService $analytics): array
    {
        $registrationForm = $event->registrationForm;
        $feedbackForm = $event->feedbackForm;

        $totalRegistered = 0;
        $checkedInCount = 0;
        $notCheckedInCount = 0;
        $onsiteCount = 0;

        if ($registrationForm) {
            $allResponses = $registrationForm->responses()->with('checkIn')->get();
            $checkedInCount = $allResponses->filter(fn ($r) => $r->checkIn)->count();
            $totalRegistered = $allResponses->count();
            $notCheckedInCount = $totalRegistered - $checkedInCount;
            $onsiteCount = $allResponses->filter(fn ($r) => $r->checkIn?->method === EventCheckInMethod::Onsite)->count();
        }

        $transactions = $event->transactions;
        $incomeByCategory = $transactions->where('type', EventTransactionType::Income)
            ->groupBy('category')
            ->map(fn ($rows) => (float) $rows->sum('amount'));
        $expensesByCategory = $transactions->where('type', EventTransactionType::Expense)
            ->groupBy('category')
            ->map(fn ($rows) => (float) $rows->sum('amount'));

        $charts = collect();
        $comments = collect();
        $feedbackResponseCount = 0;

        if ($feedbackForm) {
            $fields = $feedbackForm->fields()->get();
            $feedbackResponses = $feedbackForm->responses()->orderByDesc('created_at')->get();
            $feedbackResponseCount = $feedbackResponses->count();

            $charts = $fields->filter(fn ($field) => $field->type->isChoice())
                ->map(fn ($field) => [
                    'field' => $field,
                    'chartType' => $field->type->isMultiple() ? 'bar' : 'pie',
                    ...$analytics->optionCounts($field, $feedbackResponses),
                ]);

            $comments = $fields->filter(fn ($field) => in_array($field->type, [EventFormFieldType::Text, EventFormFieldType::Textarea], true))
                ->map(fn ($field) => [
                    'field' => $field,
                    'comments' => $feedbackResponses
                        ->map(fn ($r) => $r->answers[$field->id] ?? null)
                        ->filter(fn ($v) => filled($v))
                        ->take(10)
                        ->values(),
                ])
                ->filter(fn ($entry) => $entry['comments']->isNotEmpty())
                ->values();
        }

        return [
            'registrationForm' => $registrationForm,
            'feedbackForm' => $feedbackForm,
            'totalRegistered' => $totalRegistered,
            'checkedInCount' => $checkedInCount,
            'notCheckedInCount' => $notCheckedInCount,
            'onsiteCount' => $onsiteCount,
            'totalIncome' => $event->totalIncome(),
            'totalExpenses' => $event->totalExpenses(),
            'netBalance' => $event->netBalance(),
            'incomeByCategory' => $incomeByCategory,
            'expensesByCategory' => $expensesByCategory,
            'feedbackResponseCount' => $feedbackResponseCount,
            'charts' => $charts,
            'comments' => $comments,
            'reportDetail' => $event->reportDetail,
            'itineraryItems' => $event->itineraryItems,
        ];
    }
}
