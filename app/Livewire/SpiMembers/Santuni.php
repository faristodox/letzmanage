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

    public function render()
    {
        $members = SpiSantuniMember::query()
            ->when($this->search, function ($q) {
                $q->where(function ($q) {
                    $q->where('nama', 'like', "%{$this->search}%")
                      ->orWhere('no_kp', 'like', "%{$this->search}%");
                });
            })
            ->when($this->filterJantina, fn ($q) => $q->where('jantina', 'like', "%{$this->filterJantina}%"))
            ->orderBy('nama')
            ->paginate(20);

        $lastSync = SpiSantuniMember::max('synced_at');

        return view('livewire.spi-members.santuni', [
            'members'  => $members,
            'total'    => SpiSantuniMember::count(),
            'lastSync' => $lastSync ? \Carbon\Carbon::parse($lastSync) : null,
        ]);
    }
}
