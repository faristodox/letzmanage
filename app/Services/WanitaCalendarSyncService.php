<?php

namespace App\Services;

use App\Models\OrganizationCalendarSetting;
use App\Models\WanitaCalendarEvent;
use App\Services\Concerns\AuthenticatesGoogleRequests;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Scrapes the Jawatankuasa WANITA committee's own Google Sheet calendar —
 * a yearly grid the committee edits directly, sharing the org's existing
 * Google connection (Settings > Calendar) rather than a separate OAuth
 * flow, since faris.mohamed@ikram.org.my already has Editor access to it.
 *
 * Sheet layout (confirmed against the real sheet, not assumed):
 * - Row 2 (0-indexed row 1), column A: the base year, e.g. "2026".
 * - Row 4 (index 3): month name headers in Malay, one per "event" column
 *   (a header at column N pairs with the day-number column N-1 immediately
 *   to its left) — e.g. "MEI" at column D pairs with day-numbers in
 *   column C. Months run left to right in calendar order starting from
 *   April of the base year; the year rolls over whenever a month number is
 *   smaller than the previous one (e.g. after DISEMBER comes JANUARI) —
 *   this is more reliable than the sheet's own inline "27" year suffixes,
 *   which are inconsistently applied (e.g. present on "JANUARI 27" but
 *   missing on "MAC" right after it).
 * - Row 5 onward (index 4+): one row per day-of-month. A day-number cell
 *   with no matching event cell text means no event that day.
 *
 * Only events whose cell background is exactly the sheet's own "TARBIAH
 * AKHAWAT" green or "JKW" turquoise are synced — every other color
 * (school holidays, national holidays, etc., already covered by the
 * Holiday feature) is deliberately ignored.
 */
class WanitaCalendarSyncService
{
    use AuthenticatesGoogleRequests;

    private const BASE_URL = 'https://sheets.googleapis.com/v4/';

    // Covers the header rows plus up to 31 days across all 12 month-pairs
    // (April through March) laid out across columns A-Y.
    private const RANGE = 'A1:Y40';

    private const MALAY_MONTHS = [
        'januari' => 1, 'februari' => 2, 'mac' => 3, 'april' => 4,
        'mei' => 5, 'jun' => 6, 'julai' => 7, 'ogos' => 8,
        'september' => 9, 'oktober' => 10, 'november' => 11, 'disember' => 12,
    ];

    public function __construct(private readonly GoogleOAuthService $oauth) {}

    public function sync(OrganizationCalendarSetting $setting): void
    {
        $sheetId = self::extractSheetId($setting->wanita_sheet_url);

        if ($sheetId === null) {
            throw new RuntimeException('No WANITA sheet URL is configured.');
        }

        $result = $this->authenticatedClient($setting, $this->oauth, self::BASE_URL)
            ->get("spreadsheets/{$sheetId}", [
                'ranges' => self::RANGE,
                'includeGridData' => 'true',
                'fields' => 'sheets(data(rowData(values(formattedValue,userEnteredFormat.backgroundColor))))',
            ]);

        if ($result->failed()) {
            throw new RuntimeException('WANITA calendar sync failed: '.$result->body());
        }

        $rows = $result->json('sheets.0.data.0.rowData') ?? [];
        $events = $this->parseEvents($rows);

        DB::transaction(function () use ($setting, $events) {
            WanitaCalendarEvent::where('organization_id', $setting->organization_id)->delete();

            foreach ($events as $event) {
                WanitaCalendarEvent::create([
                    'organization_id' => $setting->organization_id,
                    'date' => $event['date'],
                    'title' => $event['title'],
                ]);
            }
        });
    }

    /**
     * Accepts either a full Google Sheets URL or a bare sheet id.
     */
    public static function extractSheetId(?string $url): ?string
    {
        if ($url === null || trim($url) === '') {
            return null;
        }

        if (preg_match('#/spreadsheets/d/([a-zA-Z0-9_-]+)#', $url, $matches)) {
            return $matches[1];
        }

        if (preg_match('/^[a-zA-Z0-9_-]{20,}$/', trim($url))) {
            return trim($url);
        }

        return null;
    }

    /**
     * @param  array<int, array{values?: array<int, array{formattedValue?: string, userEnteredFormat?: array}>}>  $rows
     * @return array<int, array{date: string, title: string}>
     */
    private function parseEvents(array $rows): array
    {
        $baseYear = (int) ($rows[1]['values'][0]['formattedValue'] ?? now()->year);
        $months = $this->parseMonthColumns($rows[3]['values'] ?? [], $baseYear);

        $events = [];

        foreach ($rows as $rowIndex => $row) {
            if ($rowIndex < 4) {
                continue;
            }

            $cells = $row['values'] ?? [];

            foreach ($months as $eventColumn => $month) {
                $dateColumn = $eventColumn - 1;
                $day = trim($cells[$dateColumn]['formattedValue'] ?? '');

                if ($day === '' || ! ctype_digit($day)) {
                    continue;
                }

                $eventCell = $cells[$eventColumn] ?? [];
                $title = $this->cleanTitle($eventCell['formattedValue'] ?? '');

                if ($title === '') {
                    continue;
                }

                $backgroundColor = $eventCell['userEnteredFormat']['backgroundColor'] ?? [];

                if (! $this->isGreen($backgroundColor) && ! $this->isTurquoise($backgroundColor)) {
                    continue;
                }

                if (! checkdate($month['month'], (int) $day, $month['year'])) {
                    continue;
                }

                $date = sprintf('%04d-%02d-%02d', $month['year'], $month['month'], (int) $day);
                $key = $date.'|'.$title;
                $events[$key] = ['date' => $date, 'title' => "{$title} (WANITA)"];
            }
        }

        return array_values($events);
    }

    /**
     * @param  array<int, array{formattedValue?: string}>  $headerCells
     * @return array<int, array{month: int, year: int}> keyed by column index
     */
    private function parseMonthColumns(array $headerCells, int $baseYear): array
    {
        $months = [];
        $year = $baseYear;
        $previousMonthNumber = null;

        foreach ($headerCells as $column => $cell) {
            $text = trim($cell['formattedValue'] ?? '');

            if ($text === '') {
                continue;
            }

            $name = trim(preg_replace('/\s*\d{2}$/', '', $text) ?? '');
            $monthNumber = self::MALAY_MONTHS[mb_strtolower($name)] ?? null;

            if ($monthNumber === null) {
                continue;
            }

            if ($previousMonthNumber !== null && $monthNumber < $previousMonthNumber) {
                $year++;
            }

            $previousMonthNumber = $monthNumber;
            $months[$column] = ['month' => $monthNumber, 'year' => $year];
        }

        return $months;
    }

    /**
     * A cell can contain a manual line break (Alt+Enter in Sheets), e.g.
     * "DAURAH UMUM\nMAULIDUR RASUL" — collapsed to one line so it renders
     * sensibly as a single calendar event title.
     */
    private function cleanTitle(string $raw): string
    {
        return trim(preg_replace('/\s+/', ' ', $raw) ?? '');
    }

    private function isGreen(array $backgroundColor): bool
    {
        return $this->channelsMatch($backgroundColor, red: 0, green: 1, blue: 0);
    }

    private function isTurquoise(array $backgroundColor): bool
    {
        return $this->channelsMatch($backgroundColor, red: 0, green: 1, blue: 1);
    }

    private function channelsMatch(array $backgroundColor, float $red, float $green, float $blue): bool
    {
        $epsilon = 0.01;

        return abs(($backgroundColor['red'] ?? 0.0) - $red) < $epsilon
            && abs(($backgroundColor['green'] ?? 0.0) - $green) < $epsilon
            && abs(($backgroundColor['blue'] ?? 0.0) - $blue) < $epsilon;
    }
}
