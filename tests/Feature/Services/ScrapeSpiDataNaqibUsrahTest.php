<?php

namespace Tests\Feature\Services;

use App\Enums\SpiKawasan;
use App\Models\Organization;
use App\Models\SpiMember;
use App\Models\SpiNaqibUsrah;
use App\Support\CurrentOrganization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ScrapeSpiDataNaqibUsrahTest extends TestCase
{
    use RefreshDatabase;

    /** Links the level-00 listing page to one usrah detail page. */
    private function usrahListingHtml(): string
    {
        return '<html><body><a href="admin_usrah_detail.asp?u_id=14940">Usrah 14940</a></body></html>';
    }

    /**
     * Mirrors the "Maklumat Usrah" detail page: labeled fields at the top
     * (a mix of <select>, <input>, <textarea>, and plain read-only text)
     * followed by the "Senarai Ahli-Ahli Usrah" member table, with a title
     * row above the real column headers. $kawasan lets tests exercise the
     * per-organization kawasan filter.
     */
    private function usrahDetailHtml(string $kawasan = 'Setiawangsa'): string
    {
        return <<<HTML
        <html><body>
        <table>
            <tr><td>STATUS</td><td><select><option value="I">Inactive</option><option value="A" selected>Active</option></select></td></tr>
            <tr><td>Nama Usrah</td><td><input type="text" value="AHMAD SAYYAF / ABDULLAH ZUBAIR BIN MOHD RAZALI"></td></tr>
            <tr><td>Negeri:</td><td>KUALA LUMPUR</td></tr>
            <tr><td>Kawasan:</td><td>{$kawasan}</td></tr>
            <tr><td>Tahap</td><td><select><option value="02" selected>02</option><option value="03">03</option></select></td></tr>
            <tr><td>Jenis</td><td><select><option value="L" selected>Lelaki</option><option value="P">Perempuan</option></select></td></tr>
            <tr><td>Kategori:</td><td><select><option value="B" selected>Belia</option></select></td></tr>
            <tr><td>Naqib</td><td><input type="text" value="AHMAD SAYYAF BIN AHMAD FARIDZ"> <a href="#">Sila Klik Untuk Melihat Maklumat Lengkap Naqib</a></td></tr>
            <tr><td>Nota</td><td><textarea>dan ABDULLAH ZUBAIR BIN MOHD RAZALI</textarea></td></tr>
            <tr><td>Tarikh Mula</td><td>15-Feb-2026</td></tr>
            <tr><td>Tarikh Bubar</td><td></td></tr>
        </table>
        <table>
            <tr><th colspan="8">SENARAI AHLI-AHLI USRAH - AKTIF</th></tr>
            <tr><th>BIL</th><th>NAMA</th><th>PERINGKAT</th><th>TAHAP</th><th>JAWATAN</th><th>TARIKH SERTAI</th><th>NO TEL</th><th>EMAIL</th></tr>
            <tr><td>1</td><td>AHMAD SAYYAF BIN AHMAD FARIDZ</td><td>AT</td><td>02</td><td>Naqib</td><td>1-Jan-2026</td><td>0123456789</td><td>sayyaf@example.com</td></tr>
            <tr><td>2</td><td>ALI BIN ABU</td><td>AB</td><td>02</td><td>Ahli</td><td>15-Feb-2026</td><td>0198765432</td><td></td></tr>
        </table>
        </body></html>
        HTML;
    }

    private function fakeSpiEndpoints(string $kawasan = 'Setiawangsa'): void
    {
        Http::fake([
            'https://www.ikram-spi.org/sys/login.asp' => Http::response(''),
            'https://www.ikram-spi.org/sys/admin_usrahdetail.asp*' => Http::response($this->usrahListingHtml()),
            'https://www.ikram-spi.org/sys/admin_usrah_detail.asp?u_id=14940' => Http::response($this->usrahDetailHtml($kawasan)),
            'https://www.ikram-spi.org/sys/*' => Http::response(''),
        ]);
    }

    private function createOrganization(array $attributes = []): Organization
    {
        return Organization::factory()->create(array_merge([
            'spi_enabled' => true,
            'spi_district_code' => SpiKawasan::Setiawangsa->value,
        ], $attributes));
    }

    public function test_usrah_group_is_parsed_from_the_detail_pages_labeled_fields(): void
    {
        $organization = $this->createOrganization();

        $this->fakeSpiEndpoints();

        Artisan::call('spi:scrape', ['--organization' => $organization->id, '--skip-profiles' => true]);

        $group = SpiNaqibUsrah::where('usrah_id', 14940)->first();

        $this->assertNotNull($group);
        $this->assertSame('02', $group->level);
        $this->assertSame('AHMAD SAYYAF / ABDULLAH ZUBAIR BIN MOHD RAZALI', $group->usrah_name);
        $this->assertSame('Active', $group->status);
        $this->assertSame('AHMAD SAYYAF BIN AHMAD FARIDZ', $group->naqib_name);
        $this->assertFalse($group->is_temporary_group);
        $this->assertSame('Lelaki', $group->jenis);
        $this->assertSame('Belia', $group->kategori);
        $this->assertSame('15-Feb-2026', $group->tarikh_mula);
        $this->assertNull($group->tarikh_bubar);
        $this->assertSame('dan ABDULLAH ZUBAIR BIN MOHD RAZALI', $group->nota);
        $this->assertSame('KUALA LUMPUR', $group->negeri);
        $this->assertSame('Setiawangsa', $group->kawasan);
        $this->assertSame(2, $group->member_count);

        $this->assertSame('AHMAD SAYYAF BIN AHMAD FARIDZ', $group->members[0]['nama']);
        $this->assertSame('Naqib', $group->members[0]['jawatan']);
        $this->assertSame('0123456789', $group->members[0]['no_tel']);
        $this->assertSame('sayyaf@example.com', $group->members[0]['email']);

        $this->assertSame('ALI BIN ABU', $group->members[1]['nama']);
        $this->assertSame('Ahli', $group->members[1]['jawatan']);
        $this->assertArrayNotHasKey('email', $group->members[1]);
    }

    public function test_a_usrah_belonging_to_a_different_or_shared_kawasan_is_still_saved(): void
    {
        // The account scraping this org's data can see usrah groups beyond
        // its own kawasan (e.g. "SEMUA", or a neighbouring branch) — those
        // are kept, not discarded, so they stay visible and filterable.
        $organization = $this->createOrganization(['spi_district_code' => SpiKawasan::Setiawangsa->value]);

        $this->fakeSpiEndpoints(kawasan: 'Wangsa Maju');

        Artisan::call('spi:scrape', ['--organization' => $organization->id, '--skip-profiles' => true]);

        app(CurrentOrganization::class)->set($organization);
        $group = SpiNaqibUsrah::first();

        $this->assertNotNull($group);
        $this->assertSame('Wangsa Maju', $group->kawasan);
    }

    public function test_the_existing_member_naqib_annotation_is_kept_in_sync(): void
    {
        $organization = $this->createOrganization();

        app(CurrentOrganization::class)->set($organization);
        SpiMember::create(['no_ahli' => 'IM001', 'nama' => 'ALI BIN ABU', 'level' => '02']);

        $this->fakeSpiEndpoints();

        Artisan::call('spi:scrape', ['--organization' => $organization->id, '--skip-profiles' => true]);

        app(CurrentOrganization::class)->set($organization);
        $member = SpiMember::where('no_ahli', 'IM001')->first();

        $this->assertSame('AHMAD SAYYAF BIN AHMAD FARIDZ', $member->naqib);
        $this->assertSame('AHMAD SAYYAF / ABDULLAH ZUBAIR BIN MOHD RAZALI', $member->usrah_label);
    }

    public function test_re_syncing_updates_the_same_record_instead_of_duplicating_it(): void
    {
        $organization = $this->createOrganization();

        $this->fakeSpiEndpoints();

        Artisan::call('spi:scrape', ['--organization' => $organization->id, '--skip-profiles' => true]);
        Artisan::call('spi:scrape', ['--organization' => $organization->id, '--skip-profiles' => true]);

        app(CurrentOrganization::class)->set($organization);
        $this->assertSame(1, SpiNaqibUsrah::where('usrah_id', 14940)->count());
    }

    public function test_naqib_usrah_groups_are_scoped_to_the_organization_being_scraped(): void
    {
        $organization = $this->createOrganization();
        $otherOrganization = Organization::factory()->create();

        $this->fakeSpiEndpoints();

        Artisan::call('spi:scrape', ['--organization' => $organization->id, '--skip-profiles' => true]);

        app(CurrentOrganization::class)->set($organization);
        $this->assertSame(1, SpiNaqibUsrah::count());

        app(CurrentOrganization::class)->set($otherOrganization);
        $this->assertSame(0, SpiNaqibUsrah::count());
    }
}
