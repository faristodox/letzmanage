<?php

namespace Tests\Feature\Services;

use App\Models\Organization;
use App\Models\OrganizationCalendarSetting;
use App\Models\WanitaCalendarEvent;
use App\Services\WanitaCalendarSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class WanitaCalendarSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Builds a minimal fixture mirroring the real Google Sheets API shape:
     * row 0 title, row 1 base year, row 2 legend, row 3 month headers
     * (a header at column N pairs with the day-number column N-1), row 4+
     * daily data. Covers a normal pair (April/Mei) and a year-rollover
     * pair (Disember/Januari) in one small grid.
     */
    private function fakeSheetResponse(): array
    {
        $cell = fn (?string $value = null, ?array $bg = null) => array_filter([
            'formattedValue' => $value,
            'userEnteredFormat' => $bg ? ['backgroundColor' => $bg] : null,
        ]);

        $green = ['red' => 0, 'green' => 1, 'blue' => 0];
        $turquoise = ['red' => 0, 'green' => 1, 'blue' => 1];
        $yellow = ['red' => 1, 'green' => 1, 'blue' => 0];

        return [
            'sheets' => [[
                'data' => [[
                    'rowData' => [
                        ['values' => [$cell('TAKWIM JK WANITA')]],
                        ['values' => [$cell('2026')]],
                        ['values' => [$cell(), $cell('TARBIAH AKHAWAT'), $cell(), $cell('JKW')]],
                        ['values' => [
                            $cell(), $cell('APRIL'),
                            $cell(), $cell('MEI'),
                            $cell(), $cell('DISEMBER'),
                            $cell(), $cell('JANUARI 27'),
                        ]],
                        ['values' => [
                            $cell('1'), $cell(),
                            $cell('1'), $cell('Green Event', $green),
                            $cell('1'), $cell('Yellow Holiday', $yellow),
                            $cell('1'), $cell('Turquoise Event', $turquoise),
                        ]],
                        ['values' => [
                            $cell('2'), $cell(),
                            $cell('2'), $cell('No Color Event'),
                            $cell('2'), $cell(),
                            $cell('2'), $cell("Multi\nLine Title", $turquoise),
                        ]],
                    ],
                ]],
            ]],
        ];
    }

    private function connectedSetting(): OrganizationCalendarSetting
    {
        $organization = Organization::factory()->create();

        return OrganizationCalendarSetting::factory()->for($organization)->sharedModeConnected()->create([
            'wanita_sheet_url' => 'https://docs.google.com/spreadsheets/d/abc123XYZ/edit?gid=0#gid=0',
        ]);
    }

    public function test_only_green_and_turquoise_events_are_synced(): void
    {
        Http::fake(['https://sheets.googleapis.com/v4/spreadsheets/*' => Http::response($this->fakeSheetResponse())]);

        $setting = $this->connectedSetting();
        app(WanitaCalendarSyncService::class)->sync($setting);

        $events = WanitaCalendarEvent::where('organization_id', $setting->organization_id)->orderBy('date')->get();

        $this->assertCount(3, $events, 'Only the green/turquoise events should be kept — the yellow and colorless ones must be excluded.');
        $this->assertFalse($events->contains('title', 'like', '%Yellow Holiday%'));
        $this->assertFalse($events->contains('title', 'like', '%No Color Event%'));
    }

    public function test_every_synced_title_gets_the_wanita_suffix(): void
    {
        Http::fake(['https://sheets.googleapis.com/v4/spreadsheets/*' => Http::response($this->fakeSheetResponse())]);

        $setting = $this->connectedSetting();
        app(WanitaCalendarSyncService::class)->sync($setting);

        $greenEvent = WanitaCalendarEvent::where('title', 'like', 'Green Event%')->first();
        $this->assertSame('Green Event (WANITA)', $greenEvent->title);
    }

    public function test_multi_line_cell_titles_are_collapsed_to_one_line(): void
    {
        Http::fake(['https://sheets.googleapis.com/v4/spreadsheets/*' => Http::response($this->fakeSheetResponse())]);

        $setting = $this->connectedSetting();
        app(WanitaCalendarSyncService::class)->sync($setting);

        $this->assertDatabaseHas('wanita_calendar_events', ['title' => 'Multi Line Title (WANITA)']);
    }

    public function test_the_year_rolls_over_when_a_month_number_goes_backwards(): void
    {
        Http::fake(['https://sheets.googleapis.com/v4/spreadsheets/*' => Http::response($this->fakeSheetResponse())]);

        $setting = $this->connectedSetting();
        app(WanitaCalendarSyncService::class)->sync($setting);

        $greenEvent = WanitaCalendarEvent::where('title', 'like', 'Green Event%')->first();
        $this->assertSame('2026-05-01', $greenEvent->date->format('Y-m-d'), 'Mei (May) follows April in the same base year (2026).');

        $turquoiseEvent = WanitaCalendarEvent::where('title', 'like', 'Turquoise Event%')->first();
        $this->assertSame('2027-01-01', $turquoiseEvent->date->format('Y-m-d'), 'Januari comes right after Disember, so it must roll over to the next year.');
    }

    public function test_re_syncing_replaces_rather_than_duplicates(): void
    {
        Http::fake(['https://sheets.googleapis.com/v4/spreadsheets/*' => Http::response($this->fakeSheetResponse())]);

        $setting = $this->connectedSetting();
        $service = app(WanitaCalendarSyncService::class);

        $service->sync($setting);
        $countAfterFirstRun = WanitaCalendarEvent::where('organization_id', $setting->organization_id)->count();

        $service->sync($setting);
        $countAfterSecondRun = WanitaCalendarEvent::where('organization_id', $setting->organization_id)->count();

        $this->assertSame($countAfterFirstRun, $countAfterSecondRun);
    }

    public function test_a_removed_event_disappears_after_the_next_sync(): void
    {
        // A plain reference-captured closure, not two Http::fake() calls for
        // the same URL pattern — Laravel keeps the FIRST registered stub for
        // a repeated pattern rather than the latest one, so re-calling
        // Http::fake() mid-test would silently keep serving the original
        // fixture instead of the "edited" one below.
        $fixture = $this->fakeSheetResponse();
        Http::fake(function () use (&$fixture) {
            return Http::response($fixture);
        });

        $setting = $this->connectedSetting();
        $service = app(WanitaCalendarSyncService::class);
        $service->sync($setting);

        $this->assertDatabaseHas('wanita_calendar_events', ['title' => 'Green Event (WANITA)']);

        // Committee edited the sheet — that event is gone now.
        $fixture['sheets'][0]['data'][0]['rowData'][4]['values'][3] = [];

        $service->sync($setting);

        $this->assertDatabaseMissing('wanita_calendar_events', ['title' => 'Green Event (WANITA)']);
    }

    public function test_throws_when_no_sheet_url_is_configured(): void
    {
        $organization = Organization::factory()->create();
        $setting = OrganizationCalendarSetting::factory()->for($organization)->sharedModeConnected()->create([
            'wanita_sheet_url' => null,
        ]);

        $this->expectException(RuntimeException::class);

        app(WanitaCalendarSyncService::class)->sync($setting);
    }

    public function test_extract_sheet_id_handles_a_full_url(): void
    {
        $id = WanitaCalendarSyncService::extractSheetId('https://docs.google.com/spreadsheets/d/1X78kZgvksmEhxWI8MrLMD1DlS4qWzF5C0gPvCXonsz0/edit?gid=0#gid=0');

        $this->assertSame('1X78kZgvksmEhxWI8MrLMD1DlS4qWzF5C0gPvCXonsz0', $id);
    }

    public function test_extract_sheet_id_handles_a_bare_id(): void
    {
        $id = WanitaCalendarSyncService::extractSheetId('1X78kZgvksmEhxWI8MrLMD1DlS4qWzF5C0gPvCXonsz0');

        $this->assertSame('1X78kZgvksmEhxWI8MrLMD1DlS4qWzF5C0gPvCXonsz0', $id);
    }

    public function test_extract_sheet_id_returns_null_for_garbage(): void
    {
        $this->assertNull(WanitaCalendarSyncService::extractSheetId('not a url at all'));
        $this->assertNull(WanitaCalendarSyncService::extractSheetId(null));
        $this->assertNull(WanitaCalendarSyncService::extractSheetId(''));
    }
}
