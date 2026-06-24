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
        $this->syncing     = true;
        $this->syncMessage = null;

        try {
            Artisan::call('spi:scrape');
            $this->syncMessage  = 'Sync completed. Member data is now up to date.';
            $this->syncSuccess  = true;
        } catch (\Throwable $e) {
            $this->syncMessage = 'Sync failed: '.$e->getMessage();
            $this->syncSuccess = false;
        }

        $this->syncing = false;
        $this->resetPage();
    }

    public function render()
    {
        $members = SpiMember::query()
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
            ->orderBy('nama')
            ->paginate(20);

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
