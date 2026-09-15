<?php

namespace Tests\Feature;

use App\Livewire\SpiMembers\Index;
use App\Livewire\SpiMembers\NaqibUsrah;
use App\Livewire\SpiMembers\Santuni;
use App\Models\Organization;
use App\Models\SpiMember;
use App\Models\SpiNaqibUsrah;
use App\Models\SpiSantuniMember;
use App\Support\CurrentOrganization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpiExportTest extends TestCase
{
    use RefreshDatabase;

    private function csvFrom($response): string
    {
        ob_start();
        $response->sendContent();

        return ob_get_clean();
    }

    public function test_modul_export_streams_csv_of_members(): void
    {
        $org = Organization::factory()->create();
        app(CurrentOrganization::class)->set($org);

        SpiMember::create([
            'no_ahli' => 'IM9001', 'nama' => 'Ahmad Contoh', 'level' => '00',
            'jantina' => 'Lelaki', 'no_tel' => '0123456789', 'no_kp' => '900101015555',
        ]);

        $csv = $this->csvFrom((new Index)->export());

        $this->assertStringContainsString('Nama', $csv);         // header
        $this->assertStringContainsString('Ahmad Contoh', $csv); // row
        $this->assertStringContainsString('IM9001', $csv);
        $this->assertStringContainsString('900101–****', $csv);  // IC masked
        $this->assertStringNotContainsString('900101015555', $csv); // full IC not present
    }

    public function test_santuni_export_streams_csv(): void
    {
        $org = Organization::factory()->create();
        app(CurrentOrganization::class)->set($org);

        SpiSantuniMember::create([
            'nama' => 'Nurul Contoh', 'no_kp' => '880202025555', 'peringkat' => 'AB',
            'jantina' => 'Perempuan', 'no_tel' => '0198887777', 'tarikh_lulus' => '4-Jul-26',
        ]);

        $csv = $this->csvFrom((new Santuni)->export());

        $this->assertStringContainsString('Tarikh Lulus', $csv);
        $this->assertStringContainsString('Nurul Contoh', $csv);
        $this->assertStringContainsString('0198887777', $csv);
        $this->assertStringContainsString('880202–****', $csv);
    }

    public function test_naqib_usrah_export_streams_csv(): void
    {
        $org = Organization::factory()->create();
        app(CurrentOrganization::class)->set($org);

        SpiNaqibUsrah::create([
            'level' => '00',
            'naqib_name' => 'Ahmad Ashraf Bin Zafrullah',
            'jenis' => 'Lelaki',
            'kategori' => 'Belia',
            'tarikh_mula' => '31-Dec-2025',
            'negeri' => 'Kuala Lumpur',
            'kawasan' => 'Setiawangsa',
            'member_count' => 1,
            'members' => [['nama' => 'Zainal Fikri', 'jawatan' => 'Ahli', 'no_tel' => '0173427958']],
        ]);

        $csv = $this->csvFrom((new NaqibUsrah)->export());

        $this->assertStringContainsString('Ahli Usrah', $csv);       // header
        $this->assertStringContainsString('Ahmad Ashraf', $csv);
        $this->assertStringContainsString('Setiawangsa', $csv);
        $this->assertStringContainsString('Zainal Fikri (Ahli, 0173427958)', $csv);
    }
}
