<?php

namespace App\Services;

use App\Models\EventFormField;
use App\Models\EventFormResponse;
use Illuminate\Support\Collection;

/**
 * Aggregates response answers for choice-type fields (select/radio/checkbox)
 * into label/count pairs ready for a pie or bar chart.
 */
class EventFormAnalyticsService
{
    /**
     * @param  Collection<int, EventFormResponse>  $responses
     * @return array{labels: array<int, string>, data: array<int, int>}
     */
    public function optionCounts(EventFormField $field, Collection $responses): array
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
}
