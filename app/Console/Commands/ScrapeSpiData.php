<?php

namespace App\Console\Commands;

use App\Models\Organization;
use App\Models\SpiMember;
use App\Models\SpiNaqibUsrah;
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
        // memberdistrict is a best-effort attempt at server-side filtering,
        // mirroring the param used on the member-list and santuni endpoints —
        // unverified for this particular page, so processUsrahDetail() below
        // still authoritatively filters by each usrah's own Kawasan field
        // regardless of whether this actually narrows the listing.
        $url = $this->baseUrl.'admin_usrahdetail.asp?'.http_build_query([
            'u_level' => $level,
            'memberdistrict' => $this->district,
        ]);

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

    /**
     * Pull the full usrah record from its detail page: the labeled form
     * fields at the top (Status, Nama Usrah, Negeri, Kawasan, Tahap, Jenis,
     * Kategori, Naqib, Nota, Tarikh Mula, Tarikh Bubar) plus the member
     * table below it — and save it as one SpiNaqibUsrah row, upserted by
     * SPI's own u_id so re-syncing updates the same record instead of
     * duplicating it.
     */
    private function processUsrahDetail(string $usrahId): void
    {
        $url      = $this->baseUrl.'admin_usrah_detail.asp?u_id='.$usrahId;
        $response = $this->get($url);

        if (! $response->successful()) {
            return;
        }

        $crawler = new Crawler($response->body());

        $status      = $this->labeledField($crawler, 'Status');
        $usrahName   = $this->labeledField($crawler, 'Nama Usrah');
        $negeri      = $this->labeledField($crawler, 'Negeri');
        $kawasan     = $this->labeledField($crawler, 'Kawasan');
        $tahap       = $this->labeledField($crawler, 'Tahap');
        $jenis       = $this->labeledField($crawler, 'Jenis');
        $kategori    = $this->labeledField($crawler, 'Kategori');
        $naqibName   = $this->labeledField($crawler, 'Naqib');
        $nota        = $this->labeledField($crawler, 'Nota');
        $tarikhMula  = $this->labeledField($crawler, 'Tarikh Mula');
        $tarikhBubar = $this->labeledField($crawler, 'Tarikh Bubar');

        ['members' => $members, 'naqibFromJawatan' => $naqibFromJawatan] = $this->usrahMemberTable($crawler);

        // Some levels (e.g. 05) don't carry a labeled "Naqib" field at all —
        // fall back to whichever member row is marked JAWATAN = "Naqib".
        $naqibName = $naqibName ?: $naqibFromJawatan;

        if (! $usrahName && ! $naqibName && empty($members)) {
            // Page didn't come back in a shape we recognize — nothing to save.
            return;
        }

        // Kept even when it doesn't match the organization's own kawasan —
        // groups marked "SEMUA" or belonging to a neighbouring kawasan are
        // still relevant to see here, just filterable in the UI by Kawasan.
        SpiNaqibUsrah::updateOrCreate(
            ['usrah_id' => (int) $usrahId],
            [
                'level' => $tahap ?: null,
                'usrah_name' => $usrahName,
                'status' => $status,
                'naqib_name' => $naqibName ?: null,
                'is_temporary_group' => ! $naqibName,
                'jenis' => $jenis,
                'kategori' => $kategori,
                'tarikh_mula' => $tarikhMula,
                'tarikh_bubar' => $tarikhBubar,
                'nota' => $nota,
                'negeri' => $negeri,
                'kawasan' => $kawasan,
                'members' => $members,
                'member_count' => count($members),
                'synced_at' => now(),
            ]
        );

        // Keep the existing per-member "naqib" / "usrah_label" annotation on
        // SpiMember in sync too (shown on the main member list/export).
        if (! $naqibName || empty($members)) {
            return;
        }

        $updated = 0;

        foreach ($members as $member) {
            if (empty($member['nama'])) {
                continue;
            }

            $updated += SpiMember::where('nama', $member['nama'])->update([
                'naqib' => $naqibName,
                'usrah_label' => $usrahName ?: "Usrah {$usrahId}",
            ]);
        }

        if ($updated > 0) {
            $this->line("    Usrah {$usrahId} (Naqib: {$naqibName}): {$updated} member(s) updated.");
        }
    }

    /**
     * Read a labeled field's value from the usrah detail page's form-style
     * layout: the row whose cell text exactly matches $label (tolerant of a
     * trailing colon, e.g. "Kawasan:"), then whichever kind of control holds
     * the value in the next cell — a <select>'s selected option, a
     * <textarea>, an <input>'s value attribute, or (for read-only fields)
     * the cell's own plain text.
     */
    private function labeledField(Crawler $crawler, string $label): ?string
    {
        $needle = strtolower(rtrim($label, ':'));
        $value  = null;

        $crawler->filter('tr')->each(function (Crawler $row) use ($needle, &$value) {
            if ($value !== null) {
                return;
            }

            $cellTexts = $this->cells($row);

            foreach ($cellTexts as $i => $text) {
                if (strtolower(rtrim($text, ':')) !== $needle) {
                    continue;
                }

                $valueCell = $row->filter('td, th')->eq($i + 1);

                if ($valueCell->count() === 0) {
                    $value = '';

                    return;
                }

                $select = $valueCell->filter('select');
                if ($select->count() > 0) {
                    $selected = $select->filter('option[selected]');
                    $option   = $selected->count() > 0 ? $selected : $select->filter('option');
                    $value    = $option->count() > 0 ? trim($option->first()->text()) : '';

                    return;
                }

                $textarea = $valueCell->filter('textarea');
                if ($textarea->count() > 0) {
                    $value = trim($textarea->first()->text());

                    return;
                }

                $input = $valueCell->filter('input[type="text"], input:not([type])');
                if ($input->count() > 0) {
                    $value = trim($input->first()->attr('value') ?? '');

                    return;
                }

                $value = trim($cellTexts[$i + 1] ?? '');

                return;
            }
        });

        return ($value === null || $value === '') ? null : $value;
    }

    /**
     * Parse the "Senarai Ahli-Ahli Usrah" member table on a usrah detail
     * page (BIL, NAMA, PERINGKAT, TAHAP, JAWATAN, TARIKH SERTAI, NO TEL,
     * EMAIL — not every column is guaranteed present). Also reports back
     * whichever member is marked JAWATAN = "Naqib", for levels whose detail
     * page has no separate labeled Naqib field.
     *
     * The header row is found by content (first row containing both NAMA and
     * JAWATAN), not by table/row position — the real page may have a title
     * row ("Senarai Ahli-Ahli Usrah - Aktif") above the actual column
     * headers within the same table.
     *
     * @return array{members: array<int, array<string, mixed>>, naqibFromJawatan: ?string}
     */
    private function usrahMemberTable(Crawler $crawler): array
    {
        $members          = [];
        $naqibFromJawatan = null;

        $crawler->filter('table')->each(function (Crawler $table) use (&$members, &$naqibFromJawatan) {
            if (! empty($members)) {
                return;
            }

            $idx = null;

            $table->filter('tr')->each(function (Crawler $row) use (&$idx, &$members, &$naqibFromJawatan) {
                $cells = $this->cells($row);

                if ($idx === null) {
                    $upper = array_map('strtoupper', $cells);

                    if (in_array('NAMA', $upper, true) && in_array('JAWATAN', $upper, true)) {
                        $idx = [
                            'nama' => array_search('NAMA', $upper, true),
                            'peringkat' => $this->findColIdx($upper, 'PERINGKAT'),
                            'tahap' => $this->findColIdx($upper, 'TAHAP'),
                            'jawatan' => array_search('JAWATAN', $upper, true),
                            'tarikh_sertai' => $this->findColIdx($upper, 'TARIKH SERTAI'),
                            'no_tel' => $this->findColIdx($upper, 'NO TEL') !== false
                                ? $this->findColIdx($upper, 'NO TEL')
                                : $this->findColIdx($upper, 'TEL'),
                            'email' => $this->findColIdx($upper, 'EMAIL'),
                        ];
                    }

                    return;
                }

                if (empty($cells[0] ?? null) || ! is_numeric($cells[0])) {
                    return;
                }

                $get = fn (string $key) => $idx[$key] !== false && isset($cells[$idx[$key]])
                    ? trim($cells[$idx[$key]])
                    : null;

                $nama    = $get('nama');
                $jawatan = $get('jawatan');

                if (empty($nama)) {
                    return;
                }

                if ($jawatan && strtolower($jawatan) === 'naqib') {
                    $naqibFromJawatan = $nama;
                }

                $members[] = array_filter([
                    'nama' => $nama,
                    'peringkat' => $get('peringkat'),
                    'tahap' => $get('tahap'),
                    'jawatan' => $jawatan,
                    'tarikh_sertai' => $get('tarikh_sertai'),
                    'no_tel' => $get('no_tel'),
                    'email' => $get('email'),
                ], fn ($v) => $v !== null && $v !== '');
            });
        });

        return ['members' => $members, 'naqibFromJawatan' => $naqibFromJawatan];
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
