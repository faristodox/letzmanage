<?php

namespace App\Livewire\SpiMembers;

use App\Models\SpiMember;
use Illuminate\Support\Facades\Artisan;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public string $filterLevel = '';

    public string $filterJantina = '';

    public bool $syncing = false;

    public ?string $syncMessage = null;

    public bool $syncSuccess = false;


    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilterLevel(): void
    {
        $this->resetPage();
    }

    public function updatingFilterJantina(): void
    {
        $this->resetPage();
    }

    public function sync(): void
    {
        $this->syncing  = true;
        $this->detailId = null; // close modal before long operation

        try {
            Artisan::call('spi:scrape', ['--skip-profiles' => true]);
        } catch (\Throwable $e) {
            $this->syncMessage = 'Sync failed: '.$e->getMessage();
            $this->syncSuccess = false;
            $this->syncing     = false;

            return;
        }

        // Redirect for a clean re-render after the long sync
        $this->redirect(route('spi-members.index'), navigate: true);
    }

    private function filteredQuery()
    {
        return SpiMember::query()
            ->when($this->search, function ($q) {
                $q->where(function ($q) {
                    $q->where('nama', 'like', "%{$this->search}%")
                      ->orWhere('no_ahli', 'like', "%{$this->search}%")
                      ->orWhere('no_tel', 'like', "%{$this->search}%");
                });
            })
            ->when($this->filterLevel, fn ($q) => $q->where('level', $this->filterLevel))
            ->when($this->filterJantina, fn ($q) => $q->where('jantina', 'like', "%{$this->filterJantina}%"))
            ->orderBy('level')
            ->orderBy('nama');
    }

    /**
     * Wrap a value as an Excel text formula so Excel/Sheets keep it as text
     * (e.g. phone numbers keep their leading 0 instead of being read as numbers).
     */
    private function excelText(?string $value): string
    {
        return ($value === null || $value === '') ? '' : '="'.$value.'"';
    }

    public function export(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $members = $this->filteredQuery()->get();
        $filename = 'ahli-modul-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($members) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel reads Malay names correctly

            $put = fn (array $fields) => fputcsv($out, $fields, ',', '"', '');

            $put([
                'Bil', 'No. Ahli', 'Nama', 'No. KP', 'Umur', 'Jantina', 'Kategori',
                'Kawasan', 'No. Tel', 'Peringkat', 'Naqib',
                'Jawatankuasa Terkini', 'Usrah Dibawa Terkini', 'Penglibatan Amal',
            ]);

            foreach ($members as $i => $m) {
                $latestJk = $m->jawatankuasa ? last($m->jawatankuasa) : null;
                $latestUsrah = $m->usrah_dibawa ? last($m->usrah_dibawa) : null;

                $put([
                    $i + 1,
                    $m->no_ahli,
                    $m->nama,
                    $m->maskedNoKp(),
                    $m->umur,
                    $m->jantina,
                    $m->kategori,
                    $m->kawasan,
                    $this->excelText($m->no_tel), // keep leading 0 in Excel
                    SpiMember::levelLabel($m->level),
                    $m->naqib,
                    $latestJk['jawatan'] ?? '',
                    $latestUsrah['nama'] ?? '',
                    $m->penglibatan_amal ? implode(', ', $m->penglibatan_amal) : '',
                ]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function render()
    {
        $members = $this->filteredQuery()->paginate(20);

        $stats = SpiMember::query()
            ->selectRaw('level, count(*) as total')
            ->groupBy('level')
            ->orderBy('level')
            ->pluck('total', 'level');

        $lastSync = SpiMember::max('synced_at');

        return view('livewire.spi-members.index', [
            'members'  => $members,
            'stats'    => $stats,
            'lastSync' => $lastSync ? \Carbon\Carbon::parse($lastSync) : null,
        ]);
    }
}
