<?php

namespace Database\Seeders;

use App\Models\CommitteeMember;
use App\Models\Organization;
use App\Models\Portfolio;
use Illuminate\Database\Seeder;

class CommitteeMemberSeeder extends Seeder
{
    /**
     * The full committee roster for IKRAM Setiawangsa — exco office-bearers
     * plus each portfolio's Ketua/Timbalan Ketua — per the
     * "CARTA ORGANISASI IKRAM KAWASAN SETIAWANGSA SESI 2026-2028" chart,
     * seeded so AI MoM can match them by name during attendee analysis.
     */
    private const MEMBERS = [
        ['name' => 'Mohd Nasri Bin Ishak', 'position' => 'Yang Dipertua'],
        ['name' => 'Abdullah Zubair Bin Mohd Razali', 'position' => 'Timbalan Yang Dipertua', 'portfolio' => 'Jawatankuasa Dakwah Akar Umbi'],
        ['name' => 'Muhammad Adam Bin Mohd Adnan', 'position' => 'Setiausaha (Perancangan Strategik)'],
        ['name' => 'Muhammad Faris Bin Sayed Mohamed', 'position' => 'Penolong Setiausaha (Pengurusan Data)'],
        ['name' => 'Saifurrahman Bin Alias', 'position' => 'Bendahari'],
        ['name' => 'Mardhiah Binti Mior Hajjar', 'position' => 'Ketua Wanita', 'portfolio' => 'Jawatankuasa Wanita'],
        ['name' => 'Muhammad Darwiis Bin Mohammad Aris', 'position' => 'Ketua Ikram Muda', 'portfolio' => 'Jawatankuasa Ikram Muda'],
        ['name' => 'Ahmad Sayyaf Bin Ahmad Faridz', 'position' => 'Ketua Jawatankuasa Tarbiah dan Modal Insan', 'portfolio' => 'Jawatankuasa Tarbiah dan Modal Insan'],
        ['name' => 'Ahmad Razin Bin Zainal Abidin', 'position' => 'Ketua Jawatankuasa Hal Ehwal Mahasiswa', 'portfolio' => 'Jawatankuasa Hal Ehwal Mahasiswa'],
        ['name' => 'Abdul Rashid Bin Mohamad Alias', 'position' => 'Ketua Jawatankuasa Pembangunan Institusi', 'portfolio' => 'Jawatankuasa Pembangunan Institusi'],
        ['name' => 'Sahri Bin Usman', 'position' => 'Ketua Jawatankuasa Pendidikan Masyarakat', 'portfolio' => 'Jawatankuasa Pendidikan Masyarakat'],
        ['name' => 'Mohamad Hafizi Bin Din', 'position' => 'Ketua Jawatankuasa Kebajikan dan Kesejahteraan', 'portfolio' => 'Jawatankuasa Kebajikan dan Kesejahteraan'],
        ['name' => 'Nur Afiqah Izzati Binti Abdullah Zawawi', 'position' => 'Timbalan Ketua Ikram Muda', 'portfolio' => 'Jawatankuasa Ikram Muda'],
        ['name' => 'Maimanah Binti Mohamad Lokman', 'position' => 'Timbalan Ketua Jawatankuasa Hal Ehwal Mahasiswa', 'portfolio' => 'Jawatankuasa Hal Ehwal Mahasiswa'],
        ['name' => 'Mohamad Solihin Bin Mohamad Nazri', 'position' => 'Timbalan Ketua Jawatankuasa Dakwah Akar Umbi', 'portfolio' => 'Jawatankuasa Dakwah Akar Umbi'],
        ['name' => 'Abdullah Nasih Bin Zainal Adnan', 'position' => 'Ketua Jawatankuasa Media dan Dakwah Digital', 'portfolio' => 'Jawatankuasa Media dan Dakwah Digital'],
    ];

    public function run(): void
    {
        $organization = Organization::where('slug', 'ikram-setiawangsa')->first();

        if (! $organization) {
            $this->command?->warn('IKRAM Setiawangsa organization not found — skipping committee member seeding.');

            return;
        }

        foreach (self::MEMBERS as $member) {
            $portfolioId = isset($member['portfolio'])
                ? Portfolio::where('organization_id', $organization->id)->where('name', $member['portfolio'])->value('id')
                : null;

            CommitteeMember::firstOrCreate(
                [
                    'organization_id' => $organization->id,
                    'name' => $member['name'],
                ],
                [
                    'position' => $member['position'],
                    'portfolio_id' => $portfolioId,
                ]
            );
        }
    }
}
