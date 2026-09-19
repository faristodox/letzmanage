<?php

namespace Tests\Feature\Services;

use App\Models\Holiday;
use App\Services\CutiSekolahHolidayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CutiSekolahHolidayServiceTest extends TestCase
{
    use RefreshDatabase;

    private function publicHolidayPage(): string
    {
        return <<<'HTML'
        <html><body>
        <table class="has-fixed-layout">
            <thead><tr><th>Tarikh</th><th>Cuti</th><th>Negeri</th></tr></thead>
            <tbody>
                <tr><td>1 Januari (Khamis)</td><td><a href="#">Tahun Baru</a></td><td>Semua Negeri kecuali Johor</td></tr>
                <tr><td>25 Disember (Jumaat)</td><td><a href="#">Hari Krismas (Christmas Day)</a></td><td>Semua Negeri</td></tr>
                <tr><td>14 Januari (Rabu)</td><td><a href="#">Hari Keputeraan Sultan Kelantan</a></td><td>Kelantan</td></tr>
                <tr><td>1 Februari (Ahad)</td><td><a href="#">Hari Wilayah Persekutuan</a></td><td>W. P. Kuala Lumpur, Labuan & Putrajaya</td></tr>
            </tbody>
        </table>
        <table class="has-fixed-layout">
            <tbody>
                <tr><td><a href="#">Johor</a></td><td><a href="#">Kedah</a></td></tr>
            </tbody>
        </table>
        </body></html>
        HTML;
    }

    private function schoolHolidayPage(): string
    {
        return <<<'HTML'
        <html><body>
        <table>
            <thead><tr><th>Takwim Sekolah</th><th>Mula</th><th>Akhir</th><th>Jumlah Hari</th></tr></thead>
            <tbody>
                <tr><td>Tarikh Mula Persekolahan<br></td><td>12 Jan 2026<br>(Isnin)</td><td>&ndash;<br></td><td>&ndash;<br></td></tr>
                <tr><td>Cuti Penggal 1<br></td><td>21 Mac 2026<br>(Sabtu)</td><td>29 Mac 2026<br>(Ahad)</td><td>9<br></td></tr>
            </tbody>
        </table>
        <table>
            <thead><tr><th>Cuti Perayaan</th><th>Mula</th><th>Akhir</th><th>Negeri</th></tr></thead>
            <tbody>
                <tr><td>Deepavali<br></td><td>8 Nov 2026<br>(Ahad)</td><td>10 Nov 2026<br>(Selasa)</td><td>Semua Negeri Kumpulan B<br></td></tr>
            </tbody>
        </table>
        </body></html>
        HTML;
    }

    public function test_parses_public_holidays_and_cleans_bracketed_titles(): void
    {
        Http::fake([
            'https://cutisekolah.com.my/kalendar-2026/' => Http::response($this->publicHolidayPage()),
            'https://cutisekolah.com.my/*' => Http::response('', 404),
        ]);

        app(CutiSekolahHolidayService::class)->sync(null);

        $this->assertDatabaseCount('holidays', 4);

        $newYear = Holiday::query()->whereDate('date', '2026-01-01')->where('source', 'cutisekolah')->first();
        $this->assertNotNull($newYear);
        $this->assertSame('Tahun Baru', $newYear->title);
        $this->assertSame('Semua Negeri kecuali Johor', $newYear->description);

        $christmas = Holiday::query()->whereDate('date', '2026-12-25')->where('source', 'cutisekolah')->first();
        $this->assertNotNull($christmas);
        $this->assertSame('Hari Krismas', $christmas->title, 'Bracketed text must be stripped from the title.');
    }

    public function test_parses_the_negeri_column_into_applicable_states(): void
    {
        Http::fake([
            'https://cutisekolah.com.my/kalendar-2026/' => Http::response($this->publicHolidayPage()),
            'https://cutisekolah.com.my/*' => Http::response('', 404),
        ]);

        app(CutiSekolahHolidayService::class)->sync(null);

        $nationwide = Holiday::query()->where('title', 'Hari Krismas')->first();
        $this->assertNull($nationwide->applicable_states, 'Plain "Semua Negeri" means nationwide (null).');

        $nationwideMinusJohor = Holiday::query()->where('title', 'Tahun Baru')->first();
        $this->assertNotContains('johor', $nationwideMinusJohor->applicable_states);
        $this->assertContains('kuala-lumpur', $nationwideMinusJohor->applicable_states, '"kecuali" only excludes the named states — every other state still applies.');

        $kelantanOnly = Holiday::query()->where('title', 'Hari Keputeraan Sultan Kelantan')->first();
        $this->assertSame(['kelantan'], $kelantanOnly->applicable_states);

        $federalTerritories = Holiday::query()->where('title', 'Hari Wilayah Persekutuan')->first();
        $this->assertSame(['kuala-lumpur', 'labuan', 'putrajaya'], $federalTerritories->applicable_states, '"W. P." prefix must be stripped before matching the state.');
    }

    public function test_applies_to_state_respects_the_parsed_restriction(): void
    {
        $nationwide = Holiday::create(['date' => '2026-12-25', 'title' => 'Hari Krismas', 'source' => 'cutisekolah', 'type' => 'public']);
        $this->assertTrue($nationwide->appliesToState('kuala-lumpur'));
        $this->assertTrue($nationwide->appliesToState(null));

        $kelantanOnly = Holiday::create(['date' => '2026-01-14', 'title' => 'Hari Keputeraan Sultan Kelantan', 'source' => 'cutisekolah', 'type' => 'public', 'applicable_states' => ['kelantan']]);
        $this->assertTrue($kelantanOnly->appliesToState('kelantan'));
        $this->assertFalse($kelantanOnly->appliesToState('kuala-lumpur'));
        $this->assertFalse($kelantanOnly->appliesToState(null));
    }

    public function test_stops_probing_years_at_the_first_missing_page(): void
    {
        Http::fake([
            'https://cutisekolah.com.my/kalendar-2026/' => Http::response($this->publicHolidayPage()),
            'https://cutisekolah.com.my/kalendar-2027/' => Http::response($this->publicHolidayPage()),
            'https://cutisekolah.com.my/kalendar-2028/' => Http::response('', 404),
        ]);

        app(CutiSekolahHolidayService::class)->sync(null);

        Http::assertSent(fn ($request) => $request->url() === 'https://cutisekolah.com.my/kalendar-2026/');
        Http::assertSent(fn ($request) => $request->url() === 'https://cutisekolah.com.my/kalendar-2027/');
        Http::assertSent(fn ($request) => $request->url() === 'https://cutisekolah.com.my/kalendar-2028/');
        Http::assertNotSent(fn ($request) => $request->url() === 'https://cutisekolah.com.my/kalendar-2029/');
        // 2026 (200), 2027 (200), 2028 (404, loop stops there) — no 2029 request at all.
        Http::assertSentCount(3);
    }

    public function test_does_not_fetch_school_holidays_when_no_state_is_given(): void
    {
        Http::fake([
            'https://cutisekolah.com.my/kalendar-2026/' => Http::response($this->publicHolidayPage()),
            'https://cutisekolah.com.my/kalendar-2027/' => Http::response('', 404),
        ]);

        app(CutiSekolahHolidayService::class)->sync(null);

        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'kalendar-akademik'));
    }

    public function test_parses_school_holidays_as_date_ranges_and_skips_the_non_holiday_start_row(): void
    {
        Http::fake([
            'https://cutisekolah.com.my/kalendar-2026/' => Http::response($this->publicHolidayPage()),
            'https://cutisekolah.com.my/kalendar-akademik-2026/kuala-lumpur/' => Http::response($this->schoolHolidayPage()),
            'https://cutisekolah.com.my/kalendar-2027/' => Http::response('', 404),
        ]);

        app(CutiSekolahHolidayService::class)->sync('kuala-lumpur');

        $schoolHolidays = Holiday::query()->where('source', 'cutisekolah')->where('type', 'school')->get();
        $this->assertCount(2, $schoolHolidays);

        $this->assertFalse(
            $schoolHolidays->contains('title', 'Tarikh Mula Persekolahan'),
            'The school-start-date row (Akhir = dash) is not a holiday and must be skipped.'
        );

        $cutiPenggal = $schoolHolidays->firstWhere('title', 'Cuti Penggal 1');
        $this->assertNotNull($cutiPenggal);
        $this->assertSame('2026-03-21', $cutiPenggal->date->format('Y-m-d'));
        $this->assertSame('2026-03-29', $cutiPenggal->end_date->format('Y-m-d'));
        $this->assertSame('kuala-lumpur', $cutiPenggal->state);

        $deepavali = $schoolHolidays->firstWhere('title', 'Deepavali');
        $this->assertNotNull($deepavali);
        $this->assertSame('2026-11-08', $deepavali->date->format('Y-m-d'));
        $this->assertSame('2026-11-10', $deepavali->end_date->format('Y-m-d'));
    }

    /**
     * Reproduces the real cutisekolah.com.my quirk: a state's own academic
     * page can list the SAME festival twice — once as a "kecuali <state>"
     * exception and once as that excluded state's own row — because the
     * site shows the comparison verbatim rather than trimming it per state.
     */
    private function schoolHolidayPageWithStateException(): string
    {
        return <<<'HTML'
        <html><body>
        <table>
            <thead><tr><th>Cuti Perayaan</th><th>Mula</th><th>Akhir</th><th>Negeri</th></tr></thead>
            <tbody>
                <tr><td>Deepavali<br></td><td>8 Nov 2026<br>(Ahad)</td><td>10 Nov 2026<br>(Selasa)</td><td>Semua Negeri Kumpulan B<br>kecuali Sarawak<br></td></tr>
                <tr><td>Deepavali<br></td><td>9 Nov 2026<br>(Isnin)</td><td>9 Nov 2026<br>(Isnin)</td><td>Sarawak<br></td></tr>
            </tbody>
        </table>
        </body></html>
        HTML;
    }

    public function test_a_state_only_gets_the_school_holiday_row_that_actually_applies_to_it(): void
    {
        Http::fake([
            'https://cutisekolah.com.my/kalendar-2026/' => Http::response($this->publicHolidayPage()),
            'https://cutisekolah.com.my/kalendar-akademik-2026/kuala-lumpur/' => Http::response($this->schoolHolidayPageWithStateException()),
            'https://cutisekolah.com.my/kalendar-akademik-2026/sarawak/' => Http::response($this->schoolHolidayPageWithStateException()),
            'https://cutisekolah.com.my/kalendar-2027/' => Http::response('', 404),
        ]);

        $service = app(CutiSekolahHolidayService::class);
        $service->sync('kuala-lumpur');
        $service->sync('sarawak');

        $klDeepavali = Holiday::query()->where('type', 'school')->where('state', 'kuala-lumpur')->where('title', 'Deepavali')->get();
        $this->assertCount(1, $klDeepavali, 'Kuala Lumpur must get only the "kecuali Sarawak" row, not the Sarawak-only row.');
        $this->assertSame('2026-11-08', $klDeepavali->first()->date->format('Y-m-d'));
        $this->assertSame('2026-11-10', $klDeepavali->first()->end_date->format('Y-m-d'));

        $sarawakDeepavali = Holiday::query()->where('type', 'school')->where('state', 'sarawak')->where('title', 'Deepavali')->get();
        $this->assertCount(1, $sarawakDeepavali, 'Sarawak must get only its own row, not the "kecuali Sarawak" row.');
        $this->assertSame('2026-11-09', $sarawakDeepavali->first()->date->format('Y-m-d'));
        $this->assertSame('2026-11-09', $sarawakDeepavali->first()->end_date->format('Y-m-d'));
    }

    public function test_re_running_updates_rows_instead_of_duplicating(): void
    {
        Http::fake([
            'https://cutisekolah.com.my/kalendar-2026/' => Http::response($this->publicHolidayPage()),
            'https://cutisekolah.com.my/kalendar-akademik-2026/kuala-lumpur/' => Http::response($this->schoolHolidayPage()),
            'https://cutisekolah.com.my/kalendar-2027/' => Http::response('', 404),
        ]);

        $service = app(CutiSekolahHolidayService::class);
        $service->sync('kuala-lumpur');
        $countAfterFirstRun = Holiday::query()->where('source', 'cutisekolah')->count();

        $service->sync('kuala-lumpur');
        $countAfterSecondRun = Holiday::query()->where('source', 'cutisekolah')->count();

        $this->assertSame($countAfterFirstRun, $countAfterSecondRun);
    }

    public function test_switching_state_keeps_both_states_school_holidays_without_duplicating_public_ones(): void
    {
        Http::fake([
            'https://cutisekolah.com.my/kalendar-2026/' => Http::response($this->publicHolidayPage()),
            'https://cutisekolah.com.my/kalendar-akademik-2026/kuala-lumpur/' => Http::response($this->schoolHolidayPage()),
            'https://cutisekolah.com.my/kalendar-akademik-2026/selangor/' => Http::response($this->schoolHolidayPage()),
            'https://cutisekolah.com.my/kalendar-2027/' => Http::response('', 404),
        ]);

        $service = app(CutiSekolahHolidayService::class);
        $service->sync('kuala-lumpur');
        $service->sync('selangor');

        $this->assertSame(4, Holiday::query()->where('source', 'cutisekolah')->where('type', 'public')->count());
        $this->assertSame(2, Holiday::query()->where('source', 'cutisekolah')->where('type', 'school')->where('state', 'kuala-lumpur')->count());
        $this->assertSame(2, Holiday::query()->where('source', 'cutisekolah')->where('type', 'school')->where('state', 'selangor')->count());
    }
}
