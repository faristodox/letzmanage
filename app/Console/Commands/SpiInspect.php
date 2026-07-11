<?php

namespace App\Console\Commands;

use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Debug helper: log into SPI and dump the structure of an arbitrary page —
 * its forms/filter fields and its tables — to understand how to scrape it.
 *
 * Usage: php artisan spi:inspect "admin_register_districtapproach.asp"
 */
class SpiInspect extends Command
{
    protected $signature = 'spi:inspect {path : Path relative to the SPI /sys/ base, incl. any query string} {--dump= : Print full rows of table index N}';

    protected $description = 'Log into SPI and dump a page\'s forms and tables (for building a scraper)';

    protected string $baseUrl = 'https://www.ikram-spi.org/sys/';

    public function handle(): int
    {
        $jar = new CookieJar();
        $verify = (bool) config('services.spi.ssl_verify', true);

        $login = Http::withOptions(['cookies' => $jar, 'verify' => $verify])
            ->asForm()
            ->post($this->baseUrl.'login.asp', [
                'u_userid' => config('services.spi.username'),
                'u_pass' => config('services.spi.password'),
                'Submitbutton' => 'Login',
            ]);

        if (str_contains($login->body(), 'Please login')) {
            $this->error('Login failed.');

            return self::FAILURE;
        }
        $this->info('Login OK.');

        $url = $this->baseUrl.$this->argument('path');
        $this->line("GET {$url}");
        $res = Http::withOptions(['cookies' => $jar, 'verify' => $verify])->get($url);
        $this->line('HTTP '.$res->status().' — '.strlen($res->body()).' bytes');
        $this->newLine();

        $crawler = new Crawler($res->body());

        // --- Forms / filter fields ---
        $this->line('════════ FORMS / FILTER FIELDS ════════');
        $crawler->filter('form')->each(function (Crawler $form, $i) {
            $action = $form->attr('action') ?? '(same page)';
            $method = strtoupper($form->attr('method') ?? 'GET');
            $this->info("Form #{$i}: {$method} {$action}");

            $form->filter('input, select, textarea')->each(function (Crawler $field) {
                $tag = $field->nodeName();
                $name = $field->attr('name');
                if (! $name) {
                    return;
                }
                if ($tag === 'select') {
                    $opts = $field->filter('option')->each(fn (Crawler $o) => trim($o->attr('value') ?? '').'='.trim($o->text()));
                    $opts = array_slice(array_filter($opts), 0, 12);
                    $this->line("  select {$name}: ".implode(' | ', $opts));
                } else {
                    $type = $field->attr('type') ?? $tag;
                    $value = $field->attr('value') ?? '';
                    $this->line("  {$tag}[{$type}] {$name} = {$value}");
                }
            });
        });

        // --- Tables ---
        $this->newLine();
        $this->line('════════ TABLES ('.$crawler->filter('table')->count().') ════════');
        $crawler->filter('table')->each(function (Crawler $table, $i) {
            $rows = $table->filter('tr');
            if ($rows->count() === 0) {
                return;
            }
            $firstCells = $rows->eq(0)->filter('td, th')->each(fn (Crawler $c) => trim(preg_replace('/\s+/', ' ', $c->text())));
            $preview = implode(' | ', array_slice(array_filter($firstCells), 0, 10));
            if ($preview === '') {
                return;
            }
            $this->line("Table[{$i}] rows={$rows->count()} cols=".count($firstCells)." : ".mb_substr($preview, 0, 160));
        });

        // --- Optionally dump one table's rows ---
        if ($this->option('dump') !== null) {
            $idx = (int) $this->option('dump');
            $this->newLine();
            $this->line("════════ TABLE[{$idx}] ROWS ════════");
            $crawler->filter('table')->eq($idx)->filter('tr')->each(function (Crawler $row, $r) {
                $cells = $row->filter('td, th')->each(fn (Crawler $c) => trim(preg_replace('/\s+/', ' ', $c->text())));
                if (array_filter($cells, fn ($c) => $c !== '')) {
                    $indexed = [];
                    foreach ($cells as $i => $c) {
                        $indexed[] = "[$i]".mb_substr($c, 0, 22);
                    }
                    $this->line($r.' ('.count($cells).'): '.implode(' ', $indexed));
                }
            });
        }

        return self::SUCCESS;
    }
}
