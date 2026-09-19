<?php

namespace App\Livewire\Portfolios;

use App\Models\Portfolio;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public bool $showModal = false;

    public ?int $editingId = null;

    #[Validate('required|string|max:255')]
    public string $name = '';

    public ?int $confirmingDeleteId = null;

    public function mount(): void
    {
        $this->authorize('viewAny', Portfolio::class);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        $this->authorize('create', Portfolio::class);

        $this->reset(['editingId', 'name']);
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $portfolio = Portfolio::findOrFail($id);
        $this->authorize('update', $portfolio);

        $this->editingId = $portfolio->id;
        $this->name = $portfolio->name;
        $this->showModal = true;
    }

    public function save(): void
    {
        $data = $this->validate();

        if ($this->editingId) {
            $portfolio = Portfolio::findOrFail($this->editingId);
            $this->authorize('update', $portfolio);
            $portfolio->update($data);
        } else {
            $this->authorize('create', Portfolio::class);
            Portfolio::create($data);
        }

        $this->showModal = false;
        $this->reset(['editingId', 'name']);
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->reset(['editingId', 'name']);
        $this->resetValidation();
    }

    public function confirmDelete(int $id): void
    {
        $portfolio = Portfolio::findOrFail($id);
        $this->authorize('delete', $portfolio);

        $this->confirmingDeleteId = $id;
    }

    public function closeDeleteModal(): void
    {
        $this->confirmingDeleteId = null;
    }

    public function delete(): void
    {
        $portfolio = Portfolio::findOrFail($this->confirmingDeleteId);
        $this->authorize('delete', $portfolio);

        $portfolio->delete();
        $this->confirmingDeleteId = null;
    }

    public function render()
    {
        $portfolios = Portfolio::query()
            ->when($this->search, fn ($query) => $query->where('name', 'like', "%{$this->search}%"))
            ->orderBy('name')
            ->paginate(10);

        return view('livewire.portfolios.index', [
            'portfolios' => $portfolios,
        ]);
    }
}
