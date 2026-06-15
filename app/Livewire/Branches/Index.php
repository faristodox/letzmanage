<?php

namespace App\Livewire\Branches;

use App\Enums\BranchStatus;
use App\Models\Branch;
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

    #[Validate('nullable|string|max:255')]
    public string $location = '';

    #[Validate('required|string')]
    public string $status = '';

    public ?int $confirmingDeleteId = null;

    public function mount(): void
    {
        $this->authorize('viewAny', Branch::class);
        $this->status = BranchStatus::Active->value;
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        $this->authorize('create', Branch::class);

        $this->reset(['editingId', 'name', 'location']);
        $this->status = BranchStatus::Active->value;
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $branch = Branch::findOrFail($id);
        $this->authorize('update', $branch);

        $this->editingId = $branch->id;
        $this->name = $branch->name;
        $this->location = (string) $branch->location;
        $this->status = $branch->status->value;
        $this->showModal = true;
    }

    public function save(): void
    {
        $data = $this->validate();

        if ($this->editingId) {
            $branch = Branch::findOrFail($this->editingId);
            $this->authorize('update', $branch);
            $branch->update($data);
        } else {
            $this->authorize('create', Branch::class);
            Branch::create($data);
        }

        $this->showModal = false;
        $this->reset(['editingId', 'name', 'location']);
        $this->status = BranchStatus::Active->value;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->reset(['editingId', 'name', 'location']);
        $this->status = BranchStatus::Active->value;
        $this->resetValidation();
    }

    public function confirmDelete(int $id): void
    {
        $branch = Branch::findOrFail($id);
        $this->authorize('delete', $branch);

        $this->confirmingDeleteId = $id;
    }

    public function closeDeleteModal(): void
    {
        $this->confirmingDeleteId = null;
    }

    public function delete(): void
    {
        $branch = Branch::findOrFail($this->confirmingDeleteId);
        $this->authorize('delete', $branch);

        $branch->delete();
        $this->confirmingDeleteId = null;
    }

    public function render()
    {
        $branches = Branch::query()
            ->when($this->search, fn ($query) => $query->where('name', 'like', "%{$this->search}%"))
            ->orderBy('name')
            ->paginate(10);

        return view('livewire.branches.index', [
            'branches' => $branches,
            'statuses' => BranchStatus::cases(),
        ]);
    }
}
