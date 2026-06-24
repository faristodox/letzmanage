<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use GuzzleHttp\Cookie\CookieJar;
use Symfony\Component\DomCrawler\Crawler;

class ScrapeSpiData extends Command
{
    protected $signature = 'spi:scrape';
    protected $description = 'Log into ikram-spi.org and pull member data (levels 03–05, district 36)';

    protected string $baseUrl = 'https://www.ikram-spi.org/sys/';

    protected array $levels = ['03', '04', '05'];

    public function handle(): int
    {
        $username = config('services.spi.username');
        $password = config('services.spi.password');

        if (! $username || ! $password) {
            $this->error('SPI_USERNAME / SPI_PASSWORD not set in .env');

            return 1;
        }

        $jar = new CookieJar();

        // verify=false only needed on Windows dev (missing CA bundle); server uses true
        $sslVerify = (bool) config('services.spi.ssl_verify', true);

        // Step 1: log in
        $loginResponse = Http::withOptions(['cookies' => $jar, 'verify' => $sslVerify])
            ->asForm()
            ->post($this->baseUrl.'login.asp', [
                'u_userid'     => $username,
                'u_pass'       => $password,
                'Submitbutton' => 'Login',
            ]);

        $this->info('Login HTTP status: '.$loginResponse->status());

        if (str_contains($loginResponse->body(), 'Please login')) {
            $this->error('Login failed — still seeing the login form.');
            $this->warn('Compare the POST body to what the browser sends via DevTools > Network.');

            return 1;
        }

        $this->info('Login successful.');

        // Step 2: scrape each level
        foreach ($this->levels as $level) {
            $this->newLine();
            $this->line('──────────────────────────────────────');
            $this->info("Fetching level {$level}…");

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

            $response = Http::withOptions(['cookies' => $jar, 'verify' => $sslVerify])->get($url);

            if (! $response->successful()) {
                $this->error("Failed to fetch level {$level}: HTTP {$response->status()}");
                continue;
            }

            $this->scrapeTable($response->body(), $level);
        }

        $this->newLine();
        $this->info('Done.');

        return 0;
    }

    private function scrapeTable(string $html, string $level): void
    {
        $crawler   = new Crawler($html);
        $dataTable = $crawler->filter('table')->eq(10); // table[10] = clean member list

        $members = [];

        $dataTable->filter('tr')->each(function (Crawler $row) use (&$members, $level) {
            $cells = $row->filter('td, th')->each(
                fn (Crawler $cell) => trim($cell->text())
            );

            // Member rows have a numeric BIL in column 0 and at least 9 columns
            if (count($cells) < 9 || ! is_numeric($cells[0])) {
                return;
            }

            $members[] = [
                'nama'     => $cells[1],
                'no_ahli'  => trim($cells[2]),
                'no_kp'    => trim($cells[3]),
                'umur'     => (int) $cells[4],
                'jantina'  => $cells[5],  // Lelaki / Perempuan
                'kategori' => $cells[6],  // B / D / I / R
                'kawasan'  => $cells[7],
                'no_tel'   => trim($cells[8]),
                'level'    => $level,
            ];
        });

        $this->info("Level {$level}: ".count($members).' members parsed.');

        $now = now();

        foreach ($members as $member) {
            \App\Models\SpiMember::updateOrCreate(
                ['no_ahli' => $member['no_ahli']],
                array_merge($member, ['synced_at' => $now])
            );
        }

        $this->info("Level {$level}: ".count($members).' members saved.');
    }
}
