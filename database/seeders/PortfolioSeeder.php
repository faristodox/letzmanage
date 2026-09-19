<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\Portfolio;
use Illuminate\Database\Seeder;

class PortfolioSeeder extends Seeder
{
    /**
     * The real Jawatankuasa (committee) structure for IKRAM Setiawangsa,
     * per the "CARTA ORGANISASI IKRAM KAWASAN SETIAWANGSA SESI 2026-2028" chart.
     */
    private const NAMES = [
        'Jawatankuasa Wanita',
        'Jawatankuasa Ikram Muda',
        'Jawatankuasa Dakwah Akar Umbi',
        'Jawatankuasa Tarbiah dan Modal Insan',
        'Jawatankuasa Hal Ehwal Mahasiswa',
        'Jawatankuasa Pembangunan Institusi',
        'Jawatankuasa Pendidikan Masyarakat',
        'Jawatankuasa Kebajikan dan Kesejahteraan',
        'Jawatankuasa Media dan Dakwah Digital',
    ];

    public function run(): void
    {
        $organization = Organization::where('slug', 'ikram-setiawangsa')->first();

        if (! $organization) {
            $this->command?->warn('IKRAM Setiawangsa organization not found — skipping portfolio seeding.');

            return;
        }

        foreach (self::NAMES as $name) {
            Portfolio::firstOrCreate([
                'organization_id' => $organization->id,
                'name' => $name,
            ]);
        }
    }
}
