<?php

namespace App\Livewire\Users;

use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\Branch;
use App\Models\CommitteeMember;
use App\Models\Portfolio;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
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

    public ?int $portfolio_id = null;

    /**
     * When set, the User being saved gets linked to this existing
     * CommitteeMember roster entry instead of a new one being created for
     * them — avoids the same person being entered twice (once as a roster
     * attendee, once as a login account).
     */
    public ?int $linkCommitteeMemberId = null;

    /**
     * Only asked for when auto-creating a brand new roster entry (i.e. no
     * existing one was picked to link to) — an existing entry already has
     * its own position.
     */
    public string $committeePosition = '';

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
        $this->reset(['editingId', 'name', 'email', 'password', 'branch_id', 'portfolio_id', 'linkCommitteeMemberId', 'committeePosition']);
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
        $this->portfolio_id = $user->portfolio_id;
        $this->linkCommitteeMemberId = $user->committeeMember?->id;
        $this->committeePosition = $user->committeeMember?->position ?? '';
        $this->status = $user->status->value;
        $this->showModal = true;
    }

    protected function rules(): array
    {
        $isCommitteeMember = $this->role === RoleName::CommitteeMember->value;

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
            'portfolio_id' => [Rule::requiredIf($isCommitteeMember), 'nullable', 'integer', 'exists:portfolios,id'],
            'linkCommitteeMemberId' => ['nullable', 'integer', 'exists:committee_members,id'],
            'committeePosition' => [Rule::requiredIf($isCommitteeMember && ! $this->linkCommitteeMemberId), 'nullable', 'string', 'max:255'],
            'status' => ['required', 'string', 'in:'.implode(',', array_map(fn ($s) => $s->value, UserStatus::cases()))],
        ];
    }

    public function save(): void
    {
        $this->validate();

        $isCommitteeMember = $this->role === RoleName::CommitteeMember->value;

        $data = [
            'name' => $this->name,
            'email' => $this->email,
            'branch_id' => $this->branch_id,
            'portfolio_id' => $isCommitteeMember ? $this->portfolio_id : null,
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

        $this->syncCommitteeMemberLink($user, $isCommitteeMember);

        $this->showModal = false;
        $this->resetForm();
    }

    /**
     * Keeps the CommitteeMember roster in sync with this User's Committee
     * Member status — unlinking a previous roster entry if the role/link
     * changed, then either linking to the chosen existing entry or creating
     * a new one, so the same person is never represented twice.
     */
    private function syncCommitteeMemberLink(User $user, bool $isCommitteeMember): void
    {
        CommitteeMember::where('user_id', $user->id)->update(['user_id' => null]);

        if (! $isCommitteeMember) {
            return;
        }

        if ($this->linkCommitteeMemberId) {
            CommitteeMember::whereKey($this->linkCommitteeMemberId)->update([
                'user_id' => $user->id,
                'portfolio_id' => $this->portfolio_id,
            ]);

            return;
        }

        CommitteeMember::create([
            'name' => $this->name,
            'position' => $this->committeePosition,
            'portfolio_id' => $this->portfolio_id,
            'user_id' => $user->id,
        ]);
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
            ->with(['branch', 'portfolio', 'roles'])
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
            'portfolios' => Portfolio::orderBy('name')->get(),
            'availableCommitteeMembers' => $this->portfolio_id
                ? CommitteeMember::query()
                    ->where('portfolio_id', $this->portfolio_id)
                    ->where(function ($query) {
                        $query->whereNull('user_id');

                        if ($this->editingId) {
                            $query->orWhere('user_id', $this->editingId);
                        }
                    })
                    ->orderBy('name')
                    ->get()
                : collect(),
        ]);
    }
}
