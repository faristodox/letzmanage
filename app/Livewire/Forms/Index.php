<?php

namespace App\Livewire\Forms;

use App\Enums\FormStatus;
use App\Models\Form;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public bool $showModal = false;

    #[Validate('required|string|max:255')]
    public string $title = '';

    public ?int $confirmingDeleteId = null;

    public function mount(): void
    {
        $this->authorize('viewAny', Form::class);
    }

    public function create(): void
    {
        $this->authorize('create', Form::class);

        $this->reset(['title']);
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->reset(['title']);
        $this->resetValidation();
    }

    public function save(): void
    {
        $this->authorize('create', Form::class);

        $data = $this->validate();

        $form = Form::create([
            'title' => $data['title'],
            'slug' => Form::uniqueSlug($data['title']),
            'status' => FormStatus::Draft,
            'created_by' => auth()->id(),
        ]);

        $this->redirect(route('forms.builder', $form), navigate: true);
    }

    public function confirmDelete(int $id): void
    {
        $form = Form::findOrFail($id);
        $this->authorize('delete', $form);

        $this->confirmingDeleteId = $id;
    }

    public function closeDeleteModal(): void
    {
        $this->confirmingDeleteId = null;
    }

    public function delete(): void
    {
        $form = Form::findOrFail($this->confirmingDeleteId);
        $this->authorize('delete', $form);

        $form->delete();
        $this->confirmingDeleteId = null;
    }

    public function render()
    {
        $forms = Form::query()
            ->withCount('responses')
            ->orderByDesc('created_at')
            ->paginate(10);

        return view('livewire.forms.index', [
            'forms' => $forms,
        ]);
    }
}
