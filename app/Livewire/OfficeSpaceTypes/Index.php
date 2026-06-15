<?php

namespace App\Livewire\OfficeSpaceTypes;

use App\Models\OfficeSpaceType;
use Illuminate\Validation\Rule;
use Livewire\Component;

class Index extends Component
{
    public bool $showModal = false;

    public ?int $editingId = null;

    public string $name = '';

    public ?int $confirmingDeleteId = null;

    public ?string $deleteError = null;

    public function mount(): void
    {
        $this->authorize('viewAny', OfficeSpaceType::class);
    }

    public function create(): void
    {
        $this->authorize('create', OfficeSpaceType::class);

        $this->reset(['editingId', 'name']);
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $type = OfficeSpaceType::findOrFail($id);
        $this->authorize('update', $type);

        $this->editingId = $type->id;
        $this->name = $type->name;
        $this->showModal = true;
    }

    public function save(): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('office_space_types', 'name')->ignore($this->editingId)],
        ]);

        if ($this->editingId) {
            $type = OfficeSpaceType::findOrFail($this->editingId);
            $this->authorize('update', $type);
            $type->update($data);
        } else {
            $this->authorize('create', OfficeSpaceType::class);
            OfficeSpaceType::create($data);
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
        $type = OfficeSpaceType::findOrFail($id);
        $this->authorize('delete', $type);

        $this->confirmingDeleteId = $id;
        $this->deleteError = null;
    }

    public function delete(): void
    {
        $type = OfficeSpaceType::findOrFail($this->confirmingDeleteId);
        $this->authorize('delete', $type);

        if ($type->officeSpaces()->exists()) {
            $this->deleteError = __('This type is assigned to one or more office spaces and cannot be deleted.');

            return;
        }

        $type->delete();
        $this->confirmingDeleteId = null;
    }

    public function cancelDelete(): void
    {
        $this->confirmingDeleteId = null;
        $this->deleteError = null;
    }

    public function render()
    {
        return view('livewire.office-space-types.index', [
            'types' => OfficeSpaceType::withCount('officeSpaces')->orderBy('name')->get(),
        ]);
    }
}
