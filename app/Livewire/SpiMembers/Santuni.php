<?php

namespace App\Livewire\SpiMembers;

use App\Models\SpiSantuniMember;
use Illuminate\Support\Facades\Artisan;
use Livewire\Component;
use Livewire\WithPagination;

class Santuni extends Component
{
    use WithPagination;

    public string $search = '';

    public string $filterJantina = '';

    public bool $syncing = false;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilterJantina(): void
    {
        $this->resetPage();
    }

    public function sync(): void
    {
        $this->syncing = true;

        try {
            Artisan::call('spi:scrape', ['--skip-profiles' => true]);
        } catch (\Throwable $e) {
            $this->syncing = false;

            return;
        }

        $this->redirect(route('spi-members.index'), navigate: true);
    }

    private function filteredQuery()
    {
        return SpiSantuniMember::query()
            ->when($this->search, function ($q) {
                $q->where(function ($q) {
                    $q->where('nama', 'like', "%{$this->search}%")
                      ->orWhere('no_kp', 'like', "%{$this->search}%");
                });
            })
            ->when($this->filterJantina, fn ($q) => $q->where('jantina', 'like', "%{$this->filterJantina}%"))
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
        $filename = 'ahli-baru-disantuni-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($members) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel

            $put = fn (array $fields) => fputcsv($out, $fields, ',', '"', '');

            $put([
                'Bil', 'Nama', 'No. KP', 'Umur', 'Peringkat', 'Jantina',
                'Kategori', 'No. Tel', 'Tarikh Semak', 'Tarikh Lulus',
            ]);

            foreach ($members as $i => $m) {
                $put([
                    $i + 1,
                    $m->nama,
                    $m->maskedNoKp(),
                    $m->umur,
                    $m->peringkat,
                    $m->jantina,
                    $m->kategori,
                    $this->excelText($m->no_tel), // keep leading 0 in Excel
                    $m->tarikh_semak,
                    $m->tarikh_lulus,
                ]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function render()
    {
        $members = $this->filteredQuery()->paginate(20);

        $lastSync = SpiSantuniMember::max('synced_at');

        return view('livewire.spi-members.santuni', [
            'members'  => $members,
            'total'    => SpiSantuniMember::count(),
            'lastSync' => $lastSync ? \Carbon\Carbon::parse($lastSync) : null,
        ]);
    }
}
