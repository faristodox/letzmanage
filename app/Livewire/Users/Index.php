<?php

namespace App\Livewire\Users;

use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public bool $showModal = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $role = '';

    public ?int $branch_id = null;

    public string $status = '';

    public ?int $confirmingDeleteId = null;

    public function mount(): void
    {
        $this->authorize('viewAny', User::class);
        $this->resetForm();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    private function resetForm(): void
    {
        $this->reset(['editingId', 'name', 'email', 'password', 'branch_id']);
        $this->role = RoleName::Staff->value;
        $this->status = UserStatus::Active->value;
    }

    public function create(): void
    {
        $this->authorize('create', User::class);

        $this->resetForm();
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $user = User::findOrFail($id);
        $this->authorize('update', $user);

        $this->editingId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->password = '';
        $this->role = $user->getRoleNames()->first() ?? RoleName::Staff->value;
        $this->branch_id = $user->branch_id;
        $this->status = $user->status->value;
        $this->showModal = true;
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => $this->editingId
                ? ['required', 'email', 'max:255', 'unique:users,email,'.$this->editingId]
                : ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => $this->editingId
                ? ['nullable', 'string', 'min:8']
                : ['required', 'string', 'min:8'],
            'role' => ['required', 'string', 'in:'.implode(',', array_map(fn ($r) => $r->value, RoleName::cases()))],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'status' => ['required', 'string', 'in:'.implode(',', array_map(fn ($s) => $s->value, UserStatus::cases()))],
        ];
    }

    public function save(): void
    {
        $this->validate();

        $data = [
            'name' => $this->name,
            'email' => $this->email,
            'branch_id' => $this->branch_id,
            'status' => $this->status,
        ];

        if ($this->editingId) {
            $user = User::findOrFail($this->editingId);
            $this->authorize('update', $user);

            if ($this->password !== '') {
                $data['password'] = Hash::make($this->password);
            }

            $user->update($data);
            $user->syncRoles([$this->role]);
        } else {
            $this->authorize('create', User::class);

            $data['password'] = Hash::make($this->password);
            $user = User::create($data);
            $user->assignRole($this->role);
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
        $user = User::findOrFail($id);
        $this->authorize('delete', $user);

        $this->confirmingDeleteId = $id;
    }

    public function closeDeleteModal(): void
    {
        $this->confirmingDeleteId = null;
    }

    public function delete(): void
    {
        $user = User::findOrFail($this->confirmingDeleteId);
        $this->authorize('delete', $user);

        $user->delete();
        $this->confirmingDeleteId = null;
    }

    public function render()
    {
        $users = User::query()
            ->with(['branch', 'roles'])
            ->when($this->search, fn ($query) => $query->where(function ($q) {
                $q->where('name', 'like', "%{$this->search}%")
                    ->orWhere('email', 'like', "%{$this->search}%");
            }))
            ->orderBy('name')
            ->paginate(10);

        return view('livewire.users.index', [
            'users' => $users,
            'roles' => RoleName::cases(),
            'statuses' => UserStatus::cases(),
            'branches' => Branch::orderBy('name')->get(),
        ]);
    }
}
