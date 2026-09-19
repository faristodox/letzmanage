<?php

namespace App\Livewire\CommitteeMembers;

use App\Models\CommitteeMember;
use App\Models\Portfolio;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public bool $showModal = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $position = '';

    public string $icNumber = '';

    public ?int $portfolio_id = null;

    public ?int $viewingAttendanceForId = null;

    public function mount(): void
    {
        $this->authorize('viewAny', CommitteeMember::class);
    }

    public function create(): void
    {
        $this->authorize('create', CommitteeMember::class);

        $this->reset(['editingId', 'name', 'position', 'icNumber', 'portfolio_id']);
        $this->resetValidation();
        $this->showModal = true;
    }

    public function edit(CommitteeMember $committeeMember): void
    {
        $this->authorize('update', $committeeMember);

        $this->editingId = $committeeMember->id;
        $this->name = $committeeMember->name;
        $this->position = $committeeMember->position;
        $this->icNumber = (string) $committeeMember->ic_number;
        $this->portfolio_id = $committeeMember->portfolio_id;
        $this->resetValidation();
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->reset(['editingId', 'name', 'position', 'icNumber', 'portfolio_id']);
        $this->resetValidation();
    }

    public function save(): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'position' => ['required', 'string', 'max:255'],
            'icNumber' => ['nullable', 'string', 'max:32'],
            'portfolio_id' => ['nullable', 'integer', 'exists:portfolios,id'],
        ]);

        $data = [
            'name' => $data['name'],
            'position' => $data['position'],
            'ic_number' => $data['icNumber'] ?: null,
            'portfolio_id' => $data['portfolio_id'],
        ];

        if ($this->editingId) {
            $committeeMember = CommitteeMember::findOrFail($this->editingId);
            $this->authorize('update', $committeeMember);
            $committeeMember->update($data);
        } else {
            $this->authorize('create', CommitteeMember::class);
            auth()->user()->organization->committeeMembers()->create($data);
        }

        $this->closeModal();
    }

    public function delete(CommitteeMember $committeeMember): void
    {
        $this->authorize('delete', $committeeMember);

        $committeeMember->delete();
    }

    public function viewAttendance(CommitteeMember $committeeMember): void
    {
        $this->authorize('view', $committeeMember);

        $this->viewingAttendanceForId = $committeeMember->id;
    }

    public function closeAttendanceModal(): void
    {
        $this->viewingAttendanceForId = null;
    }

    public function render()
    {
        $committeeMembers = CommitteeMember::query()
            ->with(['portfolio', 'user'])
            ->orderBy('name')
            ->paginate(15);

        $viewingAttendance = $this->viewingAttendanceForId
            ? CommitteeMember::with(['attendedEvents' => fn ($query) => $query->orderByDesc('event_attendances.checked_in_at')])
                ->find($this->viewingAttendanceForId)
            : null;

        return view('livewire.committee-members.index', [
            'committeeMembers' => $committeeMembers,
            'viewingAttendance' => $viewingAttendance,
            'portfolios' => Portfolio::orderBy('name')->get(),
        ]);
    }
}
