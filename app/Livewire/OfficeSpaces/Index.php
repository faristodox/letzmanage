<?php

namespace App\Livewire\OfficeSpaces;

use App\Enums\OfficeSpaceStatus;
use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\OfficeSpace;
use App\Models\OfficeSpaceType;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class Index extends Component
{
    use WithFileUploads;
    use WithPagination;

    public string $search = '';

    public bool $showModal = false;

    public ?int $editingId = null;

    #[Validate('required|string|max:255')]
    public string $name = '';

    #[Validate('required|integer|exists:office_space_types,id')]
    public ?int $type_id = null;

    #[Validate('required|integer|min:1')]
    public int $capacity = 1;

    #[Validate('nullable|string')]
    public string $facilities = '';

    #[Validate('required|string')]
    public string $status = '';

    #[Validate('required|integer|exists:branches,id')]
    public ?int $branch_id = null;

    #[Validate('nullable|integer|exists:office_spaces,id')]
    public ?int $parent_id = null;

    #[Validate('nullable|image|max:2048')]
    public $image = null;

    public ?string $existingImagePath = null;

    public bool $removeImage = false;

    public ?int $confirmingDeleteId = null;

    public function mount(): void
    {
        $this->authorize('viewAny', OfficeSpace::class);
        $this->resetForm();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    private function resetForm(): void
    {
        $this->reset(['editingId', 'name', 'facilities', 'image', 'existingImagePath', 'removeImage', 'parent_id']);
        $this->type_id = OfficeSpaceType::orderBy('name')->value('id');
        $this->status = OfficeSpaceStatus::Active->value;
        $this->capacity = 1;
        $this->branch_id = auth()->user()->hasRole(RoleName::Admin->value)
            ? null
            : auth()->user()->branch_id;
    }

    public function create(): void
    {
        $this->resetForm();
        $this->authorize('create', [OfficeSpace::class, $this->branch_id]);
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $space = OfficeSpace::findOrFail($id);
        $this->authorize('update', $space);

        $this->editingId = $space->id;
        $this->name = $space->name;
        $this->type_id = $space->type_id;
        $this->capacity = $space->capacity;
        $this->facilities = implode(', ', $space->facilities ?? []);
        $this->status = $space->status->value;
        $this->branch_id = $space->branch_id;
        $this->existingImagePath = $space->image_path;
        $this->parent_id = $space->parent_id;
        $this->image = null;
        $this->removeImage = false;
        $this->showModal = true;
    }

    public function save(): void
    {
        $data = $this->validate();

        $data['facilities'] = array_values(array_filter(array_map('trim', explode(',', $this->facilities))));

        $space = $this->editingId ? OfficeSpace::findOrFail($this->editingId) : null;

        $data['parent_id'] = $this->parent_id ?: null;
        unset($data['image'], $data['removeImage']);

        if ($this->image) {
            if ($space?->image_path) {
                Storage::disk('public')->delete($space->image_path);
            }
            $data['image_path'] = $this->image->store('office-spaces', 'public');
        } elseif ($this->removeImage && $space?->image_path) {
            Storage::disk('public')->delete($space->image_path);
            $data['image_path'] = null;
        }

        if ($space) {
            $this->authorize('update', $space);
            $space->update($data);
        } else {
            $this->authorize('create', [OfficeSpace::class, $data['branch_id']]);
            OfficeSpace::create($data);
        }

        $this->showModal = false;
        $this->resetForm();
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
        $this->resetValidation();
    }

    public function confirmDelete(int $id): void
    {
        $space = OfficeSpace::findOrFail($id);
        $this->authorize('delete', $space);

        $this->confirmingDeleteId = $id;
    }

    public function closeDeleteModal(): void
    {
        $this->confirmingDeleteId = null;
    }

    public function delete(): void
    {
        $space = OfficeSpace::findOrFail($this->confirmingDeleteId);
        $this->authorize('delete', $space);

        if ($space->image_path) {
            Storage::disk('public')->delete($space->image_path);
        }

        $space->delete();
        $this->confirmingDeleteId = null;
    }

    public function render()
    {
        $spaces = OfficeSpace::query()
            ->visibleTo(auth()->user())
            ->with(['branch', 'type', 'parent'])
            ->when($this->search, fn ($query) => $query->where('name', 'like', "%{$this->search}%"))
            ->orderBy('name')
            ->paginate(10);

        $parentOptions = OfficeSpace::query()
            ->visibleTo(auth()->user())
            ->whereNull('parent_id')
            ->when($this->editingId, fn ($q) => $q->where('id', '!=', $this->editingId))
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('livewire.office-spaces.index', [
            'spaces' => $spaces,
            'types' => OfficeSpaceType::orderBy('name')->get(),
            'statuses' => OfficeSpaceStatus::cases(),
            'branches' => auth()->user()->hasRole(RoleName::Admin->value) ? Branch::orderBy('name')->get() : collect(),
            'parentOptions' => $parentOptions,
        ]);
    }
}
