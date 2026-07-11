<?php

namespace Tests\Feature;

use App\Livewire\SpiMembers\Index;
use App\Livewire\SpiMembers\Santuni;
use App\Models\Organization;
use App\Models\SpiMember;
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
}
