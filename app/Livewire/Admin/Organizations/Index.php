<?php

namespace App\Livewire\Admin\Organizations;

use App\Enums\OrganizationStatus;
use App\Models\Organization;
use App\Models\User;
use App\Services\OrganizationProvisioner;
use App\Services\PlatformSettingService;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public bool $publicSignupEnabled = true;

    public bool $showModal = false;

    #[Validate('required|string|max:255')]
    public string $organization_name = '';

    #[Validate('required|string|max:255')]
    public string $admin_name = '';

    #[Validate('required|email|max:255')]
    public string $admin_email = '';

    #[Validate('required|string|min:8')]
    public string $admin_password = '';

    public ?int $confirmingStatusId = null;

    public function mount(PlatformSettingService $platformSettings): void
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);

        $this->publicSignupEnabled = $platformSettings->isPublicSignupEnabled();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function togglePublicSignup(PlatformSettingService $platformSettings): void
    {
        $this->publicSignupEnabled = ! $this->publicSignupEnabled;
        $platformSettings->setPublicSignupEnabled($this->publicSignupEnabled);
    }

    public function create(): void
    {
        $this->reset(['organization_name', 'admin_name', 'admin_email', 'admin_password']);
        $this->resetValidation();
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetValidation();
    }

    public function save(OrganizationProvisioner $provisioner): void
    {
        $this->validate([
            'organization_name' => ['required', 'string', 'max:255'],
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'admin_password' => ['required', 'string', 'min:8'],
        ]);

        $provisioner->provision($this->organization_name, [
            'name' => $this->admin_name,
            'email' => $this->admin_email,
            'password' => $this->admin_password,
        ]);

        $this->showModal = false;
        $this->reset(['organization_name', 'admin_name', 'admin_email', 'admin_password']);
    }

    public function toggleStatus(int $id): void
    {
        $organization = Organization::findOrFail($id);

        $organization->status = $organization->status === OrganizationStatus::Active
            ? OrganizationStatus::Suspended
            : OrganizationStatus::Active;

        $organization->save();
    }

    public function toggleSpi(int $id): void
    {
        $organization = Organization::findOrFail($id);
        $organization->spi_enabled = ! $organization->spi_enabled;
        $organization->save();
    }

    public function render()
    {
        $organizations = Organization::query()
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%")
                ->orWhere('slug', 'like', "%{$this->search}%"))
            ->withCount(['users', 'branches', 'bookings'])
            ->orderBy('name')
            ->paginate(15);

        return view('livewire.admin.organizations.index', [
            'organizations' => $organizations,
            'totalOrganizations' => Organization::count(),
            'totalUsers' => User::query()->withoutGlobalScopes()->count(),
        ]);
    }
}
