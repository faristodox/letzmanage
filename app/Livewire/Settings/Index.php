<?php

namespace App\Livewire\Settings;

use App\Enums\ApprovalMode;
use App\Enums\SettingKey;
use App\Models\Branch;
use App\Models\SystemSetting;
use App\Services\SystemSettingService;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;

class Index extends Component
{
    use WithFileUploads;

    public string $globalMode = '';

    /** @var array<int, string> */
    public array $branchModes = [];

    public string $globalApprovalNote = '';

    /** @var array<int, string> */
    public array $branchApprovalNotes = [];

    public string $organizationName = '';

    public $organizationLogo = null;

    public ?string $existingLogoPath = null;

    public bool $removeLogo = false;

    public function mount(SystemSettingService $settings): void
    {
        $this->authorize('viewAny', SystemSetting::class);

        $this->globalMode = $settings->getApprovalMode()->value;
        $this->globalApprovalNote = $settings->getApprovalEmailNote() ?? '';
        $this->organizationName = $settings->getOrganizationName() ?? '';
        $this->existingLogoPath = $settings->getOrganizationLogoPath();

        foreach (Branch::orderBy('name')->get() as $branch) {
            $override = SystemSetting::where('branch_id', $branch->id)
                ->where('key', SettingKey::BookingApprovalMode->value)
                ->value('value');

            $this->branchModes[$branch->id] = $override ?? 'default';

            $this->branchApprovalNotes[$branch->id] = SystemSetting::where('branch_id', $branch->id)
                ->where('key', SettingKey::ApprovalEmailNote->value)
                ->value('value') ?? '';
        }
    }

    public function save(SystemSettingService $settings): void
    {
        $this->authorize('update', new SystemSetting);

        $this->validate([
            'globalMode' => ['required', 'string', 'in:'.implode(',', array_map(fn ($m) => $m->value, ApprovalMode::cases()))],
            'branchModes.*' => ['required', 'string', 'in:default,'.implode(',', array_map(fn ($m) => $m->value, ApprovalMode::cases()))],
            'globalApprovalNote' => ['nullable', 'string', 'max:1000'],
            'branchApprovalNotes.*' => ['nullable', 'string', 'max:1000'],
            'organizationName' => ['nullable', 'string', 'max:255'],
            'organizationLogo' => ['nullable', 'image', 'max:2048'],
        ]);

        $settings->setApprovalMode(ApprovalMode::from($this->globalMode));
        $settings->setApprovalEmailNote($this->globalApprovalNote ?: null);
        $settings->setOrganizationName($this->organizationName ?: null);

        if ($this->organizationLogo) {
            if ($this->existingLogoPath) {
                Storage::disk('public')->delete($this->existingLogoPath);
            }

            $path = $this->organizationLogo->store('branding', 'public');
            $settings->setOrganizationLogoPath($path);
            $this->existingLogoPath = $path;
            $this->organizationLogo = null;
        } elseif ($this->removeLogo && $this->existingLogoPath) {
            Storage::disk('public')->delete($this->existingLogoPath);
            $settings->setOrganizationLogoPath(null);
            $this->existingLogoPath = null;
            $this->removeLogo = false;
        }

        foreach ($this->branchModes as $branchId => $mode) {
            if ($mode === 'default') {
                SystemSetting::where('branch_id', $branchId)
                    ->where('key', SettingKey::BookingApprovalMode->value)
                    ->delete();
            } else {
                $settings->setApprovalMode(ApprovalMode::from($mode), (int) $branchId);
            }
        }

        foreach ($this->branchApprovalNotes as $branchId => $note) {
            $settings->setApprovalEmailNote($note ?: null, (int) $branchId);
        }

        $this->dispatch('settings-saved');
    }

    public function render()
    {
        return view('livewire.settings.index', [
            'modes' => ApprovalMode::cases(),
            'branches' => Branch::orderBy('name')->get(),
        ]);
    }
}
