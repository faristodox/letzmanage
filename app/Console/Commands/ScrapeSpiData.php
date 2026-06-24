<?php

namespace App\Console\Commands;

use App\Models\SpiMember;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use GuzzleHttp\Cookie\CookieJar;
use Symfony\Component\DomCrawler\Crawler;

class ScrapeSpiData extends Command
{
    protected $signature = 'spi:scrape';
    protected $description = 'Log into ikram-spi.org and pull member + naqib data (levels 03–05)';

    protected string $baseUrl = 'https://www.ikram-spi.org/sys/';

    protected array $levels = ['03', '04', '05'];

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

        $this->jar       = new CookieJar();
        $this->sslVerify = (bool) config('services.spi.ssl_verify', true);

        // Step 1: log in
        $loginResponse = Http::withOptions(['cookies' => $this->jar, 'verify' => $this->sslVerify])
            ->asForm()
            ->post($this->baseUrl.'login.asp', [
                'u_userid'     => $username,
                'u_pass'       => $password,
                'Submitbutton' => 'Login',
            ]);

        $this->info('Login HTTP status: '.$loginResponse->status());

        if (str_contains($loginResponse->body(), 'Please login')) {
            $this->error('Login failed — still seeing the login form.');

            return 1;
        }

        $this->info('Login successful.');

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

        $this->newLine();
        $this->info('Done.');

        return 0;
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

            $members[] = [
                'nama'     => $cells[1],
                'no_ahli'  => trim($cells[2]),
                'no_kp'    => trim($cells[3]),
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
        $url = $this->baseUrl.'admin_usrahdetail.asp?'.http_build_query([
            'u_level'        => $level,
            'utype'          => '',
            'memberdistrict' => '0',
            'subGO'          => 'PAPAR',
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

    private function processUsrahDetail(string $usrahId): void
    {
        $url      = $this->baseUrl.'admin_usrah_detail.asp?u_id='.$usrahId;
        $response = $this->get($url);

        if (! $response->successful()) {
            return;
        }

        $crawler = new Crawler($response->body());

        $naqibName  = null;
        $usrahLabel = null;
        $members    = []; // ['nama' => string, 'jawatan' => string]

        // The usrah label is typically in an input or text near the top —
        // we derive it from the Naqib row later.

        // Walk all tables to find the one with NAMA + JAWATAN header columns
        $crawler->filter('table')->each(function (Crawler $table) use (&$naqibName, &$members) {
            $headerCells = $this->cells($table->filter('tr')->first());
            $upper       = array_map('strtoupper', $headerCells);

            // Must have NAMA and JAWATAN columns
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

                // Skip header row
                if (strtoupper($cells[$namaIdx] ?? '') === 'NAMA') {
                    return;
                }

                $nama    = $cells[$namaIdx] ?? '';
                $jawatan = strtolower(trim($cells[$jawatanIdx] ?? ''));

                if (empty($nama)) {
                    return;
                }

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

    // ── Helpers ──────────────────────────────────────────────────────────────

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
