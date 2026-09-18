<?php

namespace App\Services;

use App\Enums\EventType;
use App\Models\CommitteeMember;
use App\Models\Meeting;
use Carbon\Carbon;

/**
 * Builds the data the official Minutes of Meeting print template needs —
 * kept separate from the Livewire Show component so the print route can
 * build the exact same data without going through Livewire (mirrors
 * EventReportService's role for the Event Report print page).
 */
class MeetingMinutesPrintService
{
    private const MALAY_DAYS = [
        'Sunday' => 'Ahad', 'Monday' => 'Isnin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu',
        'Thursday' => 'Khamis', 'Friday' => 'Jumaat', 'Saturday' => 'Sabtu',
    ];

    private const MALAY_MONTHS = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Mac', 4 => 'April', 5 => 'Mei', 6 => 'Jun',
        7 => 'Julai', 8 => 'Ogos', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Disember',
    ];

    /**
     * @return array<string, mixed>
     */
    public function build(Meeting $meeting, string $language): array
    {
        $agendaData = ($language === 'ms' ? $meeting->agenda_items_ms : $meeting->agenda_items)
            ?? $meeting->agenda_items
            ?? [];

        $confirmedAttendees = $this->confirmedAttendees($meeting);

        return [
            'attendees' => $confirmedAttendees ?? ($agendaData['attendees'] ?? []),
            'attendeesConfirmed' => $confirmedAttendees !== null,
            'agendaItems' => $agendaData['agenda_items'] ?? [],
            'preparedBy' => $meeting->prepared_by_name
                ? ['name' => $meeting->prepared_by_name, 'position' => $meeting->prepared_by_position]
                : $this->resolvePerson($meeting->creator?->name, $meeting->organization_id),
            'confirmedBy' => $meeting->confirmed_by_name
                ? ['name' => $meeting->confirmed_by_name, 'position' => $meeting->confirmed_by_position]
                : $this->resolveSecretary($meeting->organization_id),
            'schedule' => $this->schedule($meeting, $language),
        ];
    }

    /**
     * @return ?array<int, array{name: string, position: string}>
     */
    private function confirmedAttendees(Meeting $meeting): ?array
    {
        $event = $meeting->event;

        if (! $event || $event->type !== EventType::CommitteeMeeting) {
            return null;
        }

        $attendees = $event->attendees()->with('committeeMember')->get();

        if ($attendees->isEmpty()) {
            return null;
        }

        return $attendees
            ->map(fn ($attendee) => ['name' => $attendee->displayName(), 'position' => (string) $attendee->displayPosition()])
            ->all();
    }

    /**
     * @return array{name: ?string, position: ?string}
     */
    private function resolvePerson(?string $name, ?int $organizationId): array
    {
        if (! $name) {
            return ['name' => null, 'position' => null];
        }

        $match = $organizationId
            ? CommitteeMember::where('organization_id', $organizationId)->where('name', $name)->first()
            : null;

        return ['name' => $name, 'position' => $match?->position];
    }

    /**
     * @return array{name: ?string, position: ?string}
     */
    private function resolveSecretary(?int $organizationId): array
    {
        $secretary = $organizationId
            ? CommitteeMember::where('organization_id', $organizationId)->where('position', 'like', '%Setiausaha%')->first()
            : null;

        return ['name' => $secretary?->name, 'position' => $secretary?->position];
    }

    /**
     * @return array{date: ?string, day: ?string, time: ?string, location: ?string}
     */
    private function schedule(Meeting $meeting, string $language): array
    {
        $event = $meeting->event;

        if (! $event || ! $event->start_date) {
            return ['date' => null, 'day' => null, 'time' => null, 'location' => $event?->location];
        }

        $date = $event->start_date;
        $day = $language === 'ms' ? (self::MALAY_DAYS[$date->format('l')] ?? $date->format('l')) : $date->format('l');
        $dateLabel = $language === 'ms'
            ? "{$date->format('d')} ".self::MALAY_MONTHS[(int) $date->format('n')]." {$date->format('Y')}"
            : $date->format('d F Y');

        $time = null;

        if ($event->start_time) {
            $start = Carbon::createFromFormat('H:i', $event->start_time);
            $time = $language === 'ms' ? $this->malayTime($start) : $start->format('g:i A');

            if ($event->end_time) {
                $end = Carbon::createFromFormat('H:i', $event->end_time);
                $time .= ' - '.($language === 'ms' ? $this->malayTime($end) : $end->format('g:i A'));
            }
        }

        return ['date' => $dateLabel, 'day' => $day, 'time' => $time, 'location' => $event->location];
    }

    /**
     * Malay time-of-day words rather than AM/PM — a reasonable approximation
     * (exact boundaries vary by convention), fine for this cosmetic header.
     */
    private function malayTime(Carbon $time): string
    {
        $period = match (true) {
            $time->hour < 12 => 'Pagi',
            $time->hour < 14 => 'Tengah Hari',
            $time->hour < 19 => 'Petang',
            default => 'Malam',
        };

        return $time->format('g:i').' '.$period;
    }
}
