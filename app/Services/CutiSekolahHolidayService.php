<?php

namespace App\Services;

use App\Enums\HolidaySource;
use App\Enums\HolidayType;
use App\Enums\MalaysianState;
use App\Models\Holiday;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Scrapes Malaysia public + school holidays from cutisekolah.com.my — an
 * alternative to the Google public-holiday calendar (see
 * GoogleHolidayCalendarService), picked because it also publishes
 * state-specific school holiday dates, which Google's calendar doesn't have.
 *
 * The site has no API; years are discovered by probing
 * kalendar-{year}/ sequentially from START_YEAR until a page 404s, so new
 * years the site publishes are picked up automatically without a code
 * change or a hard-coded end year.
 */
class CutiSekolahHolidayService
{
    private const BASE_URL = 'https://cutisekolah.com.my/';

    private const START_YEAR = 2026;

    private const MALAY_MONTHS = [
        'januari' => 1, 'jan' => 1,
        'februari' => 2, 'feb' => 2,
        'mac' => 3,
        'april' => 4, 'apr' => 4,
        'mei' => 5,
        'jun' => 6,
        'julai' => 7, 'jul' => 7,
        'ogos' => 8, 'ogs' => 8,
        'september' => 9, 'sept' => 9, 'sep' => 9,
        'oktober' => 10, 'okt' => 10,
        'november' => 11, 'nov' => 11,
        'disember' => 12, 'dis' => 12,
    ];

    /**
     * Fetches and stores every available year of public holidays, plus
     * school holidays for $stateSlug when given. Safe to re-run: each
     * (year, type, state) group is fully replaced from that year's page
     * rather than merged, since the site is the sole source of truth for
     * it — this also means a parsing-logic fix (e.g. a since-corrected
     * over-inclusive row) cleans up previously-stored rows on the very next
     * sync, rather than leaving stale ones behind forever.
     */
    public function sync(?string $stateSlug): void
    {
        $year = self::START_YEAR;

        while (($publicHtml = $this->fetchPage("kalendar-{$year}/")) !== null) {
            $this->replaceYear($year, HolidayType::Public, null, $this->parsePublicHolidays($publicHtml, $year));

            if ($stateSlug !== null) {
                $schoolHtml = $this->fetchPage("kalendar-akademik-{$year}/{$stateSlug}/");

                if ($schoolHtml !== null) {
                    $this->replaceYear($year, HolidayType::School, $stateSlug, $this->parseSchoolHolidays($schoolHtml, $stateSlug));
                }
            }

            $year++;
        }
    }

    private function fetchPage(string $path): ?string
    {
        $response = Http::timeout(20)->get(self::BASE_URL.$path);

        return $response->successful() ? $response->body() : null;
    }

    /**
     * @return array<int, array{date: string, title: string, description: string, applicable_states: ?array<int, string>}>
     */
    private function parsePublicHolidays(string $html, int $year): array
    {
        $holidays = [];

        $this->eachTableWithHeaders($html, ['Tarikh', 'Cuti'], function (Crawler $row) use (&$holidays, $year) {
            $cells = $row->filter('td');

            if ($cells->count() < 2) {
                return;
            }

            $date = $this->parseMalayDateWithinYear($cells->eq(0)->text(), $year);
            $title = $this->cleanTitle($cells->eq(1)->text());
            $states = $cells->count() > 2 ? trim($cells->eq(2)->text()) : '';

            if ($date === null || $title === '') {
                return;
            }

            $holidays[] = [
                'date' => $date,
                'title' => $title,
                'description' => $states,
                'applicable_states' => $this->parseApplicableStates($states),
            ];
        });

        return $holidays;
    }

    /**
     * Parses the "Negeri" column, e.g. "Semua Negeri" (nationwide, returns
     * null), "Semua Negeri kecuali Johor, Kedah & Melaka" (nationwide minus
     * an exclusion list), or an explicit list like "Kedah, Perlis &
     * Terengganu" — needed because some public holidays (a Sultan's
     * birthday, a state's own founding day) only apply to specific states,
     * and without this every organization saw every state's holidays.
     *
     * @return ?array<int, string> null means "applies everywhere"
     */
    private function parseApplicableStates(string $negeriText): ?array
    {
        $text = trim($negeriText);

        if ($text === '' || str_starts_with($text, 'Semua Negeri')) {
            if (str_contains($text, 'kecuali')) {
                $excluded = $this->extractStateSlugs(trim(Str::after($text, 'kecuali')));
                $all = array_map(fn (MalaysianState $state) => $state->value, MalaysianState::cases());

                return array_values(array_diff($all, $excluded));
            }

            return null;
        }

        return $this->extractStateSlugs($text);
    }

    /**
     * @return array<int, string>
     */
    private function extractStateSlugs(string $text): array
    {
        $slugs = [];

        foreach (preg_split('/,|&|\bdan\b/u', $text) as $part) {
            $slug = $this->stateSlugFromName($part);

            if ($slug !== null) {
                $slugs[] = $slug;
            }
        }

        return array_values(array_unique($slugs));
    }

    private function stateSlugFromName(string $name): ?string
    {
        // The site inconsistently prefixes the 3 federal territories with
        // "W.P." / "W. P." (sometimes not at all), which Str::slug() would
        // otherwise bake into the slug (e.g. "w-p-kuala-lumpur").
        $normalized = trim(preg_replace('/^W\.?\s*P\.?\s*/iu', '', trim($name)) ?? '');

        if ($normalized === '') {
            return null;
        }

        return MalaysianState::tryFrom(Str::slug($normalized))?->value;
    }

    /**
     * Covers both tables on a kalendar-akademik page — the school-term dates
     * (Cuti Penggal, Cuti Pertengahan Tahun, ...) and the festival-based
     * school holidays (Cuti Perayaan) — since both share the same
     * label/Mula/Akhir column shape. The very first row (school start date)
     * has a placeholder, non-date "Akhir" and is naturally skipped since it
     * fails to parse as a date.
     *
     * The festival table's 4th column ("Negeri") sometimes lists the SAME
     * title twice with different dates — e.g. Deepavali as both "Semua
     * Negeri Kumpulan B kecuali Sarawak" (8-10 Nov) and "Sarawak" (9 Nov) —
     * because a state's own page shows this comparison verbatim even though
     * only one row actually applies to it. schoolHolidayAppliesToState()
     * filters those out so a state only gets its own row(s).
     *
     * @return array<int, array{date: string, end_date: string, title: string}>
     */
    private function parseSchoolHolidays(string $html, string $stateSlug): array
    {
        $holidays = [];

        $this->eachTableWithHeaders($html, ['Mula', 'Akhir'], function (Crawler $row) use (&$holidays, $stateSlug) {
            $cells = $row->filter('td');

            if ($cells->count() < 3) {
                return;
            }

            $title = $this->cleanTitle($cells->eq(0)->text());
            $start = $this->parseMalayDateWithYear($cells->eq(1)->text());
            $end = $this->parseMalayDateWithYear($cells->eq(2)->text());
            $negeri = $cells->count() > 3 ? trim($cells->eq(3)->text()) : '';

            if ($title === '' || $start === null || $end === null) {
                return;
            }

            if (! $this->schoolHolidayAppliesToState($negeri, $stateSlug)) {
                return;
            }

            $holidays[] = ['date' => $start, 'end_date' => $end, 'title' => $title];
        });

        return $holidays;
    }

    /**
     * Deliberately does NOT try to resolve "Kumpulan A"/"Kumpulan B" labels
     * to a fixed list of states — that grouping turned out to be
     * inconsistent per-festival on the real site (Perlis is conventionally
     * "Kumpulan A" for school terms, but its own page labels its Chinese
     * New Year row "Kumpulan B"). Instead this only acts on LITERAL state
     * names, exactly like parseApplicableStates() for public holidays: a
     * "kecuali <states>" exclusion excludes only those states; an explicit
     * state list includes only those states; anything else (a bare
     * "Kumpulan A/B" label, or no Negeri at all) can't be tied to one state
     * over another, so it's included rather than guessed away.
     */
    private function schoolHolidayAppliesToState(string $negeriText, string $stateSlug): bool
    {
        $text = trim($negeriText);

        if ($text === '') {
            return true;
        }

        if (str_contains($text, 'kecuali')) {
            $excluded = $this->extractStateSlugs(trim(Str::after($text, 'kecuali')));

            return ! in_array($stateSlug, $excluded, true);
        }

        $explicitStates = $this->extractStateSlugs($text);

        if ($explicitStates === []) {
            return true;
        }

        return in_array($stateSlug, $explicitStates, true);
    }

    /**
     * @param  array<int, string>  $requiredHeaderWords
     */
    private function eachTableWithHeaders(string $html, array $requiredHeaderWords, callable $rowCallback): void
    {
        $crawler = new Crawler($html);

        $crawler->filter('table')->each(function (Crawler $table) use ($requiredHeaderWords, $rowCallback) {
            $thead = $table->filter('thead');

            if ($thead->count() === 0) {
                return;
            }

            $headerText = $thead->text();

            foreach ($requiredHeaderWords as $word) {
                if (! str_contains($headerText, $word)) {
                    return;
                }
            }

            $table->filter('tbody tr')->each($rowCallback);
        });
    }

    /**
     * @param  array<int, array{date: string, end_date?: ?string, title: string, description?: ?string, applicable_states?: ?array<int, string>}>  $holidays
     */
    private function replaceYear(int $year, HolidayType $type, ?string $state, array $holidays): void
    {
        DB::transaction(function () use ($year, $type, $state, $holidays) {
            Holiday::query()
                ->where('source', HolidaySource::CutiSekolah->value)
                ->where('type', $type->value)
                ->when($state === null, fn ($query) => $query->whereNull('state'), fn ($query) => $query->where('state', $state))
                ->whereYear('date', $year)
                ->delete();

            foreach ($holidays as $holiday) {
                Holiday::create([
                    'date' => $holiday['date'],
                    'end_date' => $holiday['end_date'] ?? null,
                    'title' => $holiday['title'],
                    'description' => $holiday['description'] ?? null,
                    'source' => HolidaySource::CutiSekolah->value,
                    'type' => $type->value,
                    'state' => $state,
                    'applicable_states' => $holiday['applicable_states'] ?? null,
                ]);
            }
        });
    }

    /**
     * Removes bracketed content from a scraped title, e.g. an English
     * translation appended in parentheses — the app only wants the Malay
     * holiday name as the calendar event title.
     */
    private function cleanTitle(string $raw): string
    {
        $title = preg_replace('/\s*\([^)]*\)/u', '', trim($raw)) ?? '';

        return trim(preg_replace('/\s+/', ' ', $title) ?? '');
    }

    /**
     * Parses "1 Januari (Khamis)" style text (public-holiday page — no year
     * in the cell, so the year the page is for must be supplied separately).
     */
    private function parseMalayDateWithinYear(string $text, int $year): ?string
    {
        $clean = trim(Str::before($text, '('));

        if (! preg_match('/^(\d{1,2})\s+([A-Za-z]+)$/u', $clean, $matches)) {
            return null;
        }

        return $this->buildDate($matches[1], $matches[2], $year);
    }

    /**
     * Parses "21 Mac 2026 (Sabtu)" style text (school-holiday page — the
     * year is included in the cell itself).
     */
    private function parseMalayDateWithYear(string $text): ?string
    {
        $clean = trim(Str::before($text, '('));

        if (! preg_match('/^(\d{1,2})\s+([A-Za-z]+)\s+(\d{4})$/u', $clean, $matches)) {
            return null;
        }

        return $this->buildDate($matches[1], $matches[2], $matches[3]);
    }

    private function buildDate(string $day, string $monthName, int|string $year): ?string
    {
        $month = self::MALAY_MONTHS[mb_strtolower($monthName)] ?? null;

        if ($month === null) {
            return null;
        }

        return sprintf('%04d-%02d-%02d', (int) $year, $month, (int) $day);
    }
}
