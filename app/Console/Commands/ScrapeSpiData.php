<?php

namespace App\Console\Commands;

use App\Models\Organization;
use App\Models\SpiMember;
use App\Support\CurrentOrganization;
use Illuminate\Console\Command;
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

    protected array $levels = ['01', '02', '03', '04', '05'];

    protected bool $sslVerify = true;

    protected CookieJar $jar;

    public function handle(): int
    {
        $organization = $this->resolveOrganization();

        if (! $organization) {
            $this->error('No organization found to scrape into. Pass --organization=<id|slug>.');

            return 1;
        }

        // Scope every SpiMember read/write to this organization (tenant).
        app(CurrentOrganization::class)->set($organization);
        $this->info("Scraping into organization: {$organization->name} (#{$organization->id})");

        $username = config('services.spi.username');
        $password = config('services.spi.password');

        if (! $username || ! $password) {
            $this->error('SPI_USERNAME / SPI_PASSWORD not set in .env');

            return 1;
        }

        $this->jar       = new CookieJar();
        $this->sslVerify = (bool) config('services.spi.ssl_verify', true);

        if (! $this->login()) {
            return 1;
        }

        // Step 2: scrape member data per level
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
                'memberdistrict' => '36',
                'subGO'          => 'PAPAR',
            ]);

            $response = $this->get($url);

            if (! $response->successful()) {
                $this->error("Failed to fetch level {$level}: HTTP {$response->status()}");
                continue;
            }

            $this->scrapeMembers($response->body(), $level);
        }

        // Step 3: scrape naqib assignments per level
        $this->newLine();
        $this->line('══════════════════════════════════════');
        $this->info('Fetching naqib assignments…');

        foreach ($this->levels as $level) {
            $this->newLine();
            $this->info("Naqib — level {$level}…");
            $this->scrapeNaqibForLevel($level);
        }

        // Step 4: scrape each member's profile page (skipped when --skip-profiles)
        if (! $this->option('skip-profiles')) {
            $this->newLine();
            $this->line('══════════════════════════════════════');
            $this->info('Fetching member profiles…');
            $this->scrapeProfiles();
        }

        $this->newLine();
        $this->info('Done.');

        return 0;
    }

    /**
     * Resolve which organization to scrape into. Accepts --organization as an
     * ID or slug; otherwise defaults to the IKRAM SPI org, then the first org.
     */
    private function resolveOrganization(): ?Organization
    {
        $option = $this->option('organization');

        if ($option) {
            return is_numeric($option)
                ? Organization::find((int) $option)
                : Organization::where('slug', $option)->first();
        }

        // Called from within a request (e.g. the Sync button)? Use that org.
        if ($current = app(CurrentOrganization::class)->get()) {
            return $current;
        }

        return Organization::where('slug', 'ikram-setiawangsa')->first()
            ?? Organization::orderBy('id')->first();
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
}
