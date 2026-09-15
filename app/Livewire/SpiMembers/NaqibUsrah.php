<?php

namespace App\Livewire\SpiMembers;

use App\Enums\SpiKawasan;
use App\Models\SpiMember;
use App\Models\SpiNaqibUsrah;
use Illuminate\Support\Facades\Artisan;
use Livewire\Component;
use Livewire\WithPagination;

class NaqibUsrah extends Component
{
    use WithPagination;

    /** Sentinel wire:model value for "no kawasan recorded" (a null column can't be a select value). */
    public const NO_KAWASAN = '__none__';

    public string $search = '';

    public string $filterLevel = '';

    public string $filterKawasan = '';

    public bool $syncing = false;

    /**
     * Defaults the Kawasan filter to this organization's own branch — the
     * account scraping this data can see usrah groups from other kawasan
     * too (kept, not discarded, since they're still relevant), so this just
     * starts the view scoped to "home turf" without hiding the rest.
     */
    public function mount(): void
    {
        $organization = auth()->user()?->organization;

        $this->filterKawasan = $organization
            ? (SpiKawasan::tryFrom($organization->spi_district_code ?? '')?->name ?? '')
            : '';
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilterLevel(): void
    {
        $this->resetPage();
    }

    public function updatingFilterKawasan(): void
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
        return SpiNaqibUsrah::query()
            ->when($this->search, function ($q) {
                $q->where(function ($q) {
                    $q->where('naqib_name', 'like', "%{$this->search}%")
                      ->orWhere('members', 'like', "%{$this->search}%");
                });
            })
            ->when($this->filterLevel, fn ($q) => $q->where('level', $this->filterLevel))
            ->when($this->filterKawasan !== '', function ($q) {
                $this->filterKawasan === self::NO_KAWASAN
                    ? $q->whereNull('kawasan')
                    : $q->where('kawasan', $this->filterKawasan);
            })
            ->orderBy('level')
            ->orderBy('is_temporary_group')
            ->orderBy('naqib_name');
    }

    public function export(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $groups = $this->filteredQuery()->get();
        $filename = 'naqib-usrah-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($groups) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel

            $put = fn (array $fields) => fputcsv($out, $fields, ',', '"', '');

            $put([
                'Bil', 'Tahap', 'Nama Usrah', 'Naqib', 'Status', 'Jenis', 'Kategori',
                'Tarikh Mula', 'Tarikh Bubar', 'Negeri', 'Kawasan', 'Bilangan Ahli', 'Ahli Usrah',
            ]);

            foreach ($groups as $i => $group) {
                $members = collect($group->members ?? [])
                    ->map(function ($m) {
                        $bits = array_filter([$m['jawatan'] ?? null, $m['no_tel'] ?? null]);

                        return trim(($m['nama'] ?? '').($bits ? ' ('.implode(', ', $bits).')' : ''));
                    })
                    ->implode('; ');

                $put([
                    $i + 1,
                    $group->level ? SpiMember::levelLabel($group->level) : '—',
                    $group->usrah_name,
                    $group->displayName(),
                    $group->status,
                    $group->jenis,
                    $group->kategori,
                    $group->tarikh_mula,
                    $group->tarikh_bubar,
                    $group->negeri,
                    $group->kawasan,
                    $group->member_count,
                    $members,
                ]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function render()
    {
        $groups = $this->filteredQuery()->paginate(20);

        $lastSync = SpiNaqibUsrah::max('synced_at');

        $kawasanOptions = SpiNaqibUsrah::query()
            ->whereNotNull('kawasan')
            ->distinct()
            ->orderBy('kawasan')
            ->pluck('kawasan');

        return view('livewire.spi-members.naqib-usrah', [
            'groups' => $groups,
            'total' => SpiNaqibUsrah::count(),
            'lastSync' => $lastSync ? \Carbon\Carbon::parse($lastSync) : null,
            'levels' => ['00', '01', '02', '03', '04', '05'],
            'kawasanOptions' => $kawasanOptions,
            'hasBlankKawasan' => SpiNaqibUsrah::whereNull('kawasan')->exists(),
        ]);
    }
}
