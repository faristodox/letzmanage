<?php

namespace Tests\Feature\Services;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\Organization;
use App\Models\OrganizationCalendarSetting;
use App\Models\Portfolio;
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

    public function test_no_events_are_promoted_when_no_portfolio_is_configured(): void
    {
        Http::fake(['https://sheets.googleapis.com/v4/spreadsheets/*' => Http::response($this->fakeSheetResponse())]);

        $setting = $this->connectedSetting();
        app(WanitaCalendarSyncService::class)->sync($setting);

        $this->assertSame(3, WanitaCalendarEvent::where('organization_id', $setting->organization_id)->count());
        $this->assertSame(0, Event::count());
    }

    public function test_future_entries_are_promoted_to_draft_events_but_lapsed_ones_are_not(): void
    {
        Http::fake(['https://sheets.googleapis.com/v4/spreadsheets/*' => Http::response($this->fakeSheetResponse())]);

        $portfolio = Portfolio::factory()->create();
        $setting = $this->connectedSetting();
        $setting->update(['wanita_portfolio_id' => $portfolio->id]);

        app(WanitaCalendarSyncService::class)->sync($setting);

        // Green Event (2026-05-01) has already lapsed relative to today —
        // no Event should be created for it, only the calendar-display row.
        $this->assertDatabaseMissing('events', ['title' => 'Green Event (WANITA)']);
        $greenCalendarEvent = WanitaCalendarEvent::where('title', 'Green Event (WANITA)')->first();
        $this->assertNull($greenCalendarEvent->event_id);

        // Turquoise Event and Multi Line Title (2027) are still upcoming.
        foreach (['Turquoise Event (WANITA)', 'Multi Line Title (WANITA)'] as $title) {
            $event = Event::where('title', $title)->first();
            $this->assertNotNull($event, "Expected a Draft Event for {$title}.");
            $this->assertSame(EventStatus::Draft, $event->status);
            $this->assertSame($portfolio->id, $event->portfolio_id);
            $this->assertNotNull($event->registrationForm);

            $calendarEvent = WanitaCalendarEvent::where('title', $title)->first();
            $this->assertSame($event->id, $calendarEvent->event_id);
        }
    }

    public function test_resyncing_does_not_duplicate_an_already_promoted_event(): void
    {
        Http::fake(['https://sheets.googleapis.com/v4/spreadsheets/*' => Http::response($this->fakeSheetResponse())]);

        $portfolio = Portfolio::factory()->create();
        $setting = $this->connectedSetting();
        $setting->update(['wanita_portfolio_id' => $portfolio->id]);

        $service = app(WanitaCalendarSyncService::class);
        $service->sync($setting);
        $eventIdAfterFirstSync = WanitaCalendarEvent::where('title', 'Turquoise Event (WANITA)')->first()->event_id;

        $service->sync($setting);

        $this->assertSame(1, Event::where('title', 'Turquoise Event (WANITA)')->count());
        $this->assertSame($eventIdAfterFirstSync, WanitaCalendarEvent::where('title', 'Turquoise Event (WANITA)')->first()->event_id);
    }

    public function test_a_promoted_entry_survives_even_if_removed_from_the_sheet(): void
    {
        $fixture = $this->fakeSheetResponse();
        Http::fake(function () use (&$fixture) {
            return Http::response($fixture);
        });

        $portfolio = Portfolio::factory()->create();
        $setting = $this->connectedSetting();
        $setting->update(['wanita_portfolio_id' => $portfolio->id]);

        $service = app(WanitaCalendarSyncService::class);
        $service->sync($setting);

        $calendarEvent = WanitaCalendarEvent::where('title', 'Turquoise Event (WANITA)')->first();
        $linkedEventId = $calendarEvent->event_id;
        $this->assertNotNull($linkedEventId);

        // The committee removed this entry from the sheet entirely.
        $fixture['sheets'][0]['data'][0]['rowData'][4]['values'][7] = [];

        $service->sync($setting);

        $this->assertDatabaseHas('wanita_calendar_events', ['id' => $calendarEvent->id, 'event_id' => $linkedEventId]);
        $this->assertDatabaseHas('events', ['id' => $linkedEventId]);
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
