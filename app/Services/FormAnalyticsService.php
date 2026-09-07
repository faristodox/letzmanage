<?php

namespace App\Services;

use App\Models\FormField;
use App\Models\FormResponse;
use Illuminate\Support\Collection;

/**
 * Aggregates response answers for choice-type fields (select/radio/checkbox)
 * into label/count pairs ready for a pie or bar chart. Independent twin of
 * EventFormAnalyticsService, kept decoupled from the Event module.
 */
class FormAnalyticsService
{
    /**
     * @param  Collection<int, FormResponse>  $responses
     * @return array{labels: array<int, string>, data: array<int, int>}
     */
    public function optionCounts(FormField $field, Collection $responses): array
    {
        $counts = [];

        foreach ($field->options ?? [] as $option) {
            $counts[$option] = 0;
        }

        foreach ($responses as $response) {
            $answer = $response->answers[$field->id] ?? null;

            if ($answer === null || $answer === '') {
                continue;
            }

            foreach (is_array($answer) ? $answer : [$answer] as $value) {
                $counts[$value] = ($counts[$value] ?? 0) + 1;
            }
        }

        return [
            'labels' => array_map(strval(...), array_keys($counts)),
            'data' => array_values($counts),
        ];
    }

    /**
     * Sum of numeric answers for a Number-type field across the given responses.
     *
     * @param  Collection<int, FormResponse>  $responses
     */
    public function sumFor(FormField $field, Collection $responses): float
    {
        return $responses
            ->map(fn ($response) => $response->answers[$field->id] ?? null)
            ->filter(fn ($value) => is_numeric($value))
            ->sum(fn ($value) => (float) $value);
    }
}
