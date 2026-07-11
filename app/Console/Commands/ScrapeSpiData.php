<?php

namespace App\Console\Commands;

use App\Models\Organization;
use App\Models\SpiMember;
use App\Models\SpiSantuniMember;
use App\Support\CurrentOrganization;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use GuzzleHttp\Cookie\CookieJar;
use Symfony\Component\DomCrawler\Crawler;

class ScrapeSpiData extends Command
{
    protected $signature = 'spi:scrape
        {--skip-profiles : Skip member profile scraping (faster, for browser sync)}
        {--organization= : Organization ID or slug to scrape into (defaults to the SPI organization)}';
    protected $description = 'Log into ikram-spi.org and pull member + naqib data (levels 03–05)';

    protected string $baseUrl = 'https://www.ikram-spi.org/sys/';

    protected array $levels = ['00', '01', '02', '03', '04', '05'];

    /** SPI kawasan (memberdistrict) code for the organization being scraped. */
    protected string $district = '';

    protected bool $sslVerify = true;

    protected CookieJar $jar;

    public function handle(): int
    {
        $username = config('services.spi.username');
        $password = config('services.spi.password');

        if (! $username || ! $password) {
            $this->error('SPI_USERNAME / SPI_PASSWORD not set in .env');

            return 1;
        }

        $organizations = $this->targetOrganizations();

        if ($organizations->isEmpty()) {
            $this->error('No SPI-enabled organization with a kawasan code found. Pass --organization=<id|slug> or enable SPI + set a kawasan on an organization.');

            return 1;
        }

        $this->jar       = new CookieJar();
        $this->sslVerify = (bool) config('services.spi.ssl_verify', true);

        if (! $this->login()) {
            return 1;
        }

        foreach ($organizations as $organization) {
            if (! $organization->spi_district_code) {
                $this->warn("Skipping {$organization->name}: no kawasan (memberdistrict) code set.");

                continue;
            }

            // Scope every SPI read/write to this organization (tenant).
            app(CurrentOrganization::class)->set($organization);
            $this->district = $organization->spi_district_code;

            $this->newLine();
            $this->line('══════════════════════════════════════');
            $this->info("Organization: {$organization->name} (#{$organization->id}) — kawasan {$this->district}");

            $this->scrapeOrganization();
        }

        app(CurrentOrganization::class)->clear();

        $this->newLine();
        $this->info('Done.');

        return 0;
    }

    /** Scrape all SPI data for the organization currently in context. */
    private function scrapeOrganization(): void
    {
        // Step 1: member data per level
        foreach ($this->levels as $level) {
            $this->newLine();
            $this->line('──────────────────────────────────────');
            $this->info("Fetching members — level {$level}…");

            $url = $this->baseUrl.'admin_member_bylevel.asp?'.http_build_query([
                'u_type'         => '',
                'u_membergroup'  => '',
                'u_level2'       => '',
                'agefrom'        => '',
                'ageto'          => '',
                'memberlevel_0'  => '',
                'memberlevel_1'  => '',
                'memberlevel_2'  => '',
                'memberlevel_3'  => '',
                'memberlevel_4'  => '',
                'memberlevel_5'  => '',
                'invcategory'    => '',
                'idprogram'      => '',
                'idajk'          => '',
                'idspecialist'   => '',
                'u_level'        => $level,
                'memberstate'    => '',
                'ln'             => '',
                'orderby'        => 'byname',
                'mshiptype'      => '',
                'mstatus'        => '',
                'sex'            => '',
                'memberdistrict' => $this->district,
                'subGO'          => 'PAPAR',
            ]);

            $response = $this->get($url);

            if (! $response->successful()) {
                $this->error("Failed to fetch level {$level}: HTTP {$response->status()}");
                continue;
            }

            $this->scrapeMembers($response->body(), $level);
        }

        // Step 2: "ahli baru untuk disantuni" (new members awaiting assignment)
        $this->newLine();
        $this->line('══════════════════════════════════════');
        $this->info('Fetching ahli baru untuk disantuni…');
        $this->scrapeSantuni();

        // Step 3: naqib assignments per level
        $this->newLine();
        $this->line('══════════════════════════════════════');
        $this->info('Fetching naqib assignments…');

        foreach ($this->levels as $level) {
            $this->newLine();
            $this->info("Naqib — level {$level}…");
            $this->scrapeNaqibForLevel($level);
        }

        // Step 4: member profile pages (skipped when --skip-profiles)
        if (! $this->option('skip-profiles')) {
            $this->newLine();
            $this->line('══════════════════════════════════════');
            $this->info('Fetching member profiles…');
            $this->scrapeProfiles();
        }
    }

    /**
     * Which organizations to scrape:
     * - --organization=<id|slug> → just that one
     * - called from a request (Sync button) → the current org
     * - otherwise → every SPI-enabled org that has a kawasan code
     *
     * @return \Illuminate\Support\Collection<int, Organization>
     */
    private function targetOrganizations(): \Illuminate\Support\Collection
    {
        $option = $this->option('organization');

        if ($option) {
            $org = is_numeric($option)
                ? Organization::find((int) $option)
                : Organization::where('slug', $option)->first();

            return collect($org ? [$org] : []);
        }

        // Called from within a request (e.g. the Sync button)? Use that org.
        if ($current = app(CurrentOrganization::class)->get()) {
            return collect([$current]);
        }

        return Organization::query()
            ->where('spi_enabled', true)
            ->whereNotNull('spi_district_code')
            ->orderBy('id')
            ->get();
    }

    // ── Auth ─────────────────────────────────────────────────────────────────

    private function login(): bool
    {
        $r = Http::withOptions(['cookies' => $this->jar, 'verify' => $this->sslVerify])
            ->asForm()
            ->post($this->baseUrl.'login.asp', [
                'u_userid'     => config('services.spi.username'),
                'u_pass'       => config('services.spi.password'),
                'Submitbutton' => 'Login',
            ]);

        $this->info('Login HTTP status: '.$r->status());

        if (str_contains($r->body(), 'Please login')) {
            $this->error('Login failed — still seeing the login form.');

            return false;
        }

        $this->info('Login successful.');

        return true;
    }

    // ── Member scraping ──────────────────────────────────────────────────────

    private function scrapeMembers(string $html, string $level): void
    {
        $crawler   = new Crawler($html);
        $dataTable = $crawler->filter('table')->eq(10); // table[10] = clean member list

        $members = [];

        $dataTable->filter('tr')->each(function (Crawler $row) use (&$members, $level) {
            $cells = $this->cells($row);

            if (count($cells) < 9 || ! is_numeric($cells[0])) {
                return;
            }

            // Extract the real SPI internal u_id from the profile link in the PILIH column
            $spiUid = null;
            $row->filter('a')->each(function (Crawler $a) use (&$spiUid) {
                $href = $a->attr('href') ?? '';
                if (preg_match('/admin_member_detail(?:2)?\.asp\?u_id=(\d+)/i', $href, $m)) {
                    $spiUid = (int) $m[1];
                }
            });

            $members[] = [
                'nama'     => $cells[1],
                'no_ahli'  => preg_replace('/\s+/', '', $cells[2]),
                'spi_uid'  => $spiUid,
                'no_kp'    => preg_replace('/\s+/', '', $cells[3]),
                'umur'     => (int) $cells[4],
                'jantina'  => $cells[5],
                'kategori' => $cells[6],
                'kawasan'  => $cells[7],
                'no_tel'   => trim($cells[8]),
                'level'    => $level,
            ];
        });

        $now = now();

        foreach ($members as $member) {
            SpiMember::updateOrCreate(
                ['no_ahli' => $member['no_ahli']],
                array_merge($member, ['synced_at' => $now])
            );
        }

        $this->info("Level {$level}: ".count($members).' members saved.');
    }

    // ── Naqib scraping ───────────────────────────────────────────────────────

    private function scrapeNaqibForLevel(string $level): void
    {
        $url = $this->baseUrl.'admin_usrahdetail.asp?u_level='.$level;

        $response = $this->get($url);

        if (! $response->successful()) {
            $this->warn("Could not fetch usrah listing for level {$level}.");

            return;
        }

        // Collect all unique usrah detail page IDs from links on this page
        $crawler  = new Crawler($response->body());
        $usrahIds = [];

        $crawler->filter('a')->each(function (Crawler $a) use (&$usrahIds) {
            $href = $a->attr('href') ?? '';
            if (preg_match('/admin_usrah_detail\.asp\?u_id=(\d+)/i', $href, $m)) {
                $usrahIds[$m[1]] = true;
            }
        });

        $usrahIds = array_keys($usrahIds);
        $this->line("  Found ".count($usrahIds)." usrah groups for level {$level}.");

        foreach ($usrahIds as $usrahId) {
            $this->processUsrahDetail((string) $usrahId);
        }
    }

    private function processUsrahDetail(string $usrahId): void
    {
        $url      = $this->baseUrl.'admin_usrah_detail.asp?u_id='.$usrahId;
        $response = $this->get($url);

        if (! $response->successful()) {
            return;
        }

        $crawler = new Crawler($response->body());

        $naqibName = null;
        $members   = [];

        // Strategy 1: find Naqib name from the header input field.
        // The detail page has a row like: | Naqib | <input value="FULL NAME"> |
        $crawler->filter('tr')->each(function (Crawler $row) use (&$naqibName) {
            $cellTexts = $row->filter('td')->each(fn (Crawler $c) => trim($c->text()));

            foreach ($cellTexts as $text) {
                if (strtolower(trim($text)) === 'naqib') {
                    // The adjacent cell contains an input with the naqib's name
                    $input = $row->filter('input[type="text"], input:not([type])');
                    if ($input->count() > 0) {
                        $val = trim($input->first()->attr('value') ?? '');
                        if (! empty($val)) {
                            $naqibName = $val;
                        }
                    }
                    break;
                }
            }
        });

        // Walk all tables to find the one with NAMA + JAWATAN header columns
        $crawler->filter('table')->each(function (Crawler $table) use (&$naqibName, &$members) {
            $headerCells = $this->cells($table->filter('tr')->first());
            $upper       = array_map('strtoupper', $headerCells);

            if (! in_array('NAMA', $upper) || ! in_array('JAWATAN', $upper)) {
                return;
            }

            $namaIdx    = array_search('NAMA', $upper);
            $jawatanIdx = array_search('JAWATAN', $upper);

            $table->filter('tr')->each(function (Crawler $row) use ($namaIdx, $jawatanIdx, &$naqibName, &$members) {
                $cells = $this->cells($row);

                if (count($cells) <= max($namaIdx, $jawatanIdx)) {
                    return;
                }

                if (strtoupper($cells[$namaIdx] ?? '') === 'NAMA') {
                    return;
                }

                $nama    = $cells[$namaIdx] ?? '';
                $jawatan = strtolower(trim($cells[$jawatanIdx] ?? ''));

                if (empty($nama)) {
                    return;
                }

                // Level 05 usrah groups mark the naqib with JAWATAN = "Naqib"
                if ($jawatan === 'naqib') {
                    $naqibName = $nama;
                }

                $members[] = $nama;
            });
        });

        if (! $naqibName || empty($members)) {
            return;
        }

        // Match members to our DB by exact name and update naqib + usrah_label
        $updated = 0;

        foreach ($members as $nama) {
            $count = SpiMember::where('nama', $nama)
                ->update([
                    'naqib'       => $naqibName,
                    'usrah_label' => "Usrah {$usrahId}",
                ]);

            $updated += $count;
        }

        if ($updated > 0) {
            $this->line("    Usrah {$usrahId} (Naqib: {$naqibName}): {$updated} member(s) updated.");
        }
    }

    // ── Profile scraping ─────────────────────────────────────────────────────

    private function scrapeProfiles(): void
    {
        // Re-login to ensure fresh session before making 83+ profile requests
        $this->login();

        $members = SpiMember::all();
        $total   = $members->count();

        foreach ($members as $i => $member) {
            // Use the real SPI internal u_id (captured from member list link)
            $uid = $member->spi_uid
                ?? ltrim(preg_replace('/^IM/i', '', $member->no_ahli), '0'); // fallback

            if (empty($uid)) {
                continue;
            }

            $url      = $this->baseUrl."admin_member_detail2.asp?u_id={$uid}&msg=&showinfo=T";
            $response = $this->get($url);

            // Retry without showinfo=T if 500
            if ($response->status() === 500) {
                $response = $this->get($this->baseUrl."admin_member_detail2.asp?u_id={$uid}");
            }

            if (! $response->successful()) {
                $this->warn("  [{$i}/{$total}] HTTP {$response->status()} — {$member->nama} (u_id={$uid})");
                continue;
            }

            // Redirect to login means session expired
            if (str_contains($response->body(), 'Please login') || str_contains($response->body(), 'login.asp')) {
                $this->error('Session expired. Re-logging in…');
                // Re-login
                $username = config('services.spi.username');
                $password = config('services.spi.password');
                Http::withOptions(['cookies' => $this->jar, 'verify' => $this->sslVerify])
                    ->asForm()
                    ->post($this->baseUrl.'login.asp', [
                        'u_userid'     => $username,
                        'u_pass'       => $password,
                        'Submitbutton' => 'Login',
                    ]);
                $response = $this->get($url);
            }

            $this->parseProfile($member, $response->body());
            $this->line("  [{$i}/{$total}] {$member->nama}");

            usleep(200_000); // 200ms between requests
        }
    }

    private function parseProfile(SpiMember $member, string $html): void
    {
        $crawler          = new Crawler($html);
        $jawatankuasa     = [];
        $usrahDibawa      = [];
        $penglibatanAmal  = [];

        $crawler->filter('table')->each(function (Crawler $table) use (&$jawatankuasa, &$usrahDibawa) {
            $headerCells = $this->cells($table->filter('tr')->first());
            $upper       = array_map('strtoupper', $headerCells);

            // ── Jawatankuasa table ──────────────────────────────────────────
            // Use str_contains to tolerate extra spaces or non-breaking spaces in headers
            $namaJkIdx = $this->findColIdx($upper, 'NAMA JAWATANKUASA');

            if ($namaJkIdx !== false) {
                $namaIdx    = $namaJkIdx;
                $jawatanIdx = $this->findColIdx($upper, 'JAWATAN');
                $tarikhIdx  = $this->findColIdx($upper, 'TARIKH BENTUK JK') ?? $this->findColIdx($upper, 'TARIKH BENTUK');
                $bubarIdx   = $this->findColIdx($upper, 'TARIKH BUBAR');
                $lokaliIdx  = $this->findColIdx($upper, 'LOKAL') ?? $this->findColIdx($upper, 'LOKASI');

                $table->filter('tr')->each(function (Crawler $row) use (
                    $namaIdx, $jawatanIdx, $tarikhIdx, $bubarIdx, $lokaliIdx, &$jawatankuasa
                ) {
                    $cells = $this->cells($row);

                    if (empty($cells[0]) || ! is_numeric($cells[0])) {
                        return;
                    }

                    $nama = $cells[$namaIdx] ?? '';

                    $jawatankuasa[] = array_filter([
                        'nama'          => $nama,
                        'jawatan'       => $jawatanIdx !== false ? ($cells[$jawatanIdx] ?? '') : '',
                        'tarikh_bentuk' => $tarikhIdx !== false ? ($cells[$tarikhIdx] ?? '') : '',
                        'tarikh_bubar'  => $bubarIdx !== false ? ($cells[$bubarIdx] ?? '') : '',
                        'lokaliti'      => $lokaliIdx !== false ? ($cells[$lokaliIdx] ?? '') : '',
                    ]);
                });
            }

            // ── Usrah dibawa table ──────────────────────────────────────────
            $namaUsrahIdx = $this->findColIdx($upper, 'NAMA USRAH');

            if ($namaUsrahIdx !== false) {
                $namaIdx     = $namaUsrahIdx;
                $tahapIdx    = $this->findColIdx($upper, 'TAHAP USRAH') ?? $this->findColIdx($upper, 'TAHAP');
                $kategoriIdx = $this->findColIdx($upper, 'KATEGORI');
                $tarikhIdx   = $this->findColIdx($upper, 'TARIKH BENTUK USRAH') ?? $this->findColIdx($upper, 'TARIKH BENTUK');
                $bubarIdx    = $this->findColIdx($upper, 'TARIKH BUBAR');

                // "JAWATAN NAQIB" column
                $jawatanIdx = false;
                foreach ($upper as $idx => $h) {
                    if (str_contains($h, 'JAWATAN')) {
                        $jawatanIdx = $idx;
                        break;
                    }
                }

                $table->filter('tr')->each(function (Crawler $row) use (
                    $namaIdx, $tahapIdx, $kategoriIdx, $tarikhIdx, $bubarIdx, $jawatanIdx, &$usrahDibawa
                ) {
                    $cells = $this->cells($row);

                    if (empty($cells[0]) || ! is_numeric($cells[0])) {
                        return;
                    }

                    $nama = $cells[$namaIdx] ?? '';

                    $usrahDibawa[] = array_filter([
                        'nama'          => $nama,
                        'jawatan'       => $jawatanIdx !== false ? ($cells[$jawatanIdx] ?? '') : '',
                        'tahap'         => $tahapIdx !== false ? ($cells[$tahapIdx] ?? '') : '',
                        'kategori'      => $kategoriIdx !== false ? ($cells[$kategoriIdx] ?? '') : '',
                        'tarikh_bentuk' => $tarikhIdx !== false ? ($cells[$tarikhIdx] ?? '') : '',
                        'tarikh_bubar'  => $bubarIdx !== false ? ($cells[$bubarIdx] ?? '') : '',
                    ]);
                });
            }
        });

        // ── Penglibatan amal: only currently checked checkboxes ─────────────
        $crawler->filter('input[type="checkbox"]')->each(function (Crawler $cb) use (&$penglibatanAmal) {
            // DomCrawler represents "checked" as the attribute being present
            if ($cb->attr('checked') === null) {
                return;
            }

            // The label text is in the same <td> as the checkbox
            $td = null;

            try {
                $td = $cb->closest('td');
            } catch (\Exception) {
                return;
            }

            if (! $td || $td->count() === 0) {
                return;
            }

            $text = trim(preg_replace('/[\s\xc2\xa0]+/', ' ', $td->text()));

            if (! empty($text)) {
                $penglibatanAmal[] = $text;
            }
        });

        $member->update([
            'jawatankuasa'     => ! empty($jawatankuasa) ? $jawatankuasa : null,
            'usrah_dibawa'     => ! empty($usrahDibawa) ? $usrahDibawa : null,
            'penglibatan_amal' => ! empty($penglibatanAmal) ? $penglibatanAmal : null,
        ]);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Find the first column index whose uppercased value contains $needle.
     * Returns false if not found.
     */
    private function findColIdx(array $upper, string $needle): int|false
    {
        foreach ($upper as $idx => $h) {
            if (str_contains($h, strtoupper($needle))) {
                return $idx;
            }
        }

        return false;
    }

    private function get(string $url)
    {
        return Http::withOptions(['cookies' => $this->jar, 'verify' => $this->sslVerify])->get($url);
    }

    private function cells(Crawler $row): array
    {
        return $row->filter('td, th')->each(
            fn (Crawler $cell) => trim(preg_replace('/[\s\xc2\xa0]+/', ' ', $cell->text()))
        );
    }

    // ── Ahli baru untuk disantuni ────────────────────────────────────────────

    /**
     * Scrape the "senarai permohonan untuk kawasan santuni" list — newly
     * approved members awaiting assignment. This is a live queue in SPI (members
     * drop off once processed), so we REPLACE the org's list to mirror it exactly.
     */
    private function scrapeSantuni(): void
    {
        $url = $this->baseUrl.'admin_register_districtapproach.asp?'.http_build_query([
            'memberdistrict' => $this->district,
            'subGO'          => 'PAPAR',
        ]);

        $response = $this->get($url);

        if (! $response->successful()) {
            $this->warn('Could not fetch santuni listing: HTTP '.$response->status());

            return;
        }

        $crawler = new Crawler($response->body());
        $rows = [];
        $idx = null;

        // Walk every row in the document; lock onto the header row (contains
        // NAMA + KP + PRKT), then parse the data rows that follow it. Row-based
        // (not table-based) to survive SPI's deeply nested table layout.
        $crawler->filter('tr')->each(function (Crawler $row) use (&$rows, &$idx) {
            $cells = $this->cells($row);
            if (empty($cells)) {
                return;
            }

            $upper = array_map('strtoupper', $cells);

            // Header row: a compact row whose cells exactly name the santuni
            // columns. Exact matches + a cell-count cap avoid locking onto the
            // giant navigation/menu row (where "KP" matches "Laporan KPI" etc.).
            if ($idx === null) {
                if (count($cells) <= 20
                    && in_array('BIL', $upper, true)
                    && in_array('NAMA', $upper, true)
                    && in_array('NO. KP', $upper, true)) {
                    $idx = [
                        'nama'         => $this->findColIdx($upper, 'NAMA'),
                        'no_kp'        => $this->findColIdx($upper, 'NO. KP'),
                        'keahlian'     => $this->findColIdx($upper, 'KEAHLIAN'),
                        'umur'         => $this->findColIdx($upper, 'UMUR'),
                        'peringkat'    => $this->findColIdx($upper, 'PRKT'),
                        'jantina'      => $this->findColIdx($upper, 'JANTINA'),
                        'kategori'     => $this->findColIdx($upper, 'KTGR'),
                        'negeri'       => $this->findColIdx($upper, 'NEGERI'),
                        'kawasan'      => $this->findColIdx($upper, 'KAWASAN'),
                        'tarikh_semak' => $this->findColIdx($upper, 'TKHSEMAK'),
                        'tarikh_lulus' => $this->findColIdx($upper, 'TKHLULUS'),
                    ];
                }

                return;
            }

            // Data row: numeric BIL in the first cell.
            if (! is_numeric($cells[0])) {
                return;
            }

            $get = fn (int|false $i) => ($i !== false && isset($cells[$i])) ? trim($cells[$i]) : null;

            $nama = $get($idx['nama']);
            $noKp = preg_replace('/\D+/', '', (string) $get($idx['no_kp']));

            // Skip anything that isn't a real member row (footer/summary/etc.).
            if (! $nama || strlen($noKp) < 6) {
                return;
            }

            $rows[$noKp] = [
                'nama'         => $nama,
                'no_kp'        => $noKp,
                'keahlian'     => $get($idx['keahlian']),
                'umur'         => (int) $get($idx['umur']),
                'peringkat'    => $get($idx['peringkat']),
                'jantina'      => $get($idx['jantina']),
                'kategori'     => $get($idx['kategori']),
                'negeri'       => $get($idx['negeri']),
                'kawasan'      => $get($idx['kawasan']),
                'tarikh_semak' => $get($idx['tarikh_semak']),
                'tarikh_lulus' => $get($idx['tarikh_lulus']),
            ];
        });

        // The santuni report has no phone column — cross-reference the member
        // list (same organization) by IC to fill it in.
        $phoneByKp = SpiMember::query()
            ->whereNotNull('no_kp')
            ->get(['no_kp', 'no_tel'])
            ->mapWithKeys(fn ($m) => [preg_replace('/\D+/', '', (string) $m->no_kp) => $m->no_tel])
            ->all();

        // Replace the org's santuni list so it mirrors SPI exactly.
        DB::transaction(function () use ($rows, $phoneByKp) {
            SpiSantuniMember::query()->delete(); // scoped to current org

            foreach ($rows as $row) {
                $row['no_tel'] = $phoneByKp[$row['no_kp']] ?? null;
                $row['synced_at'] = now();
                SpiSantuniMember::create($row);
            }
        });

        $this->line('  Saved '.count($rows).' ahli baru untuk disantuni.');
    }
}
