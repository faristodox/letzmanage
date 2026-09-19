<?php

namespace App\Livewire\Roles;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use Livewire\Component;
use Spatie\Permission\Models\Role;

class Index extends Component
{
    public array $matrix = [];

    public bool $saved = false;

    public function mount(): void
    {
        $this->authorize('manage roles');
        $this->loadMatrix();
    }

    private function loadMatrix(): void
    {
        $roles = Role::with('permissions')->get()->keyBy('name');

        foreach (RoleName::cases() as $role) {
            $roleModel = $roles->get($role->value);
            foreach (PermissionName::cases() as $permission) {
                $this->matrix[$role->value][$permission->value] = $roleModel
                    ? $roleModel->permissions->contains('name', $permission->value)
                    : false;
            }
        }
    }

    public function toggle(string $role, string $permission): void
    {
        if ($role === RoleName::Admin->value) {
            return; // Admin always has all permissions
        }

        $this->matrix[$role][$permission] = ! ($this->matrix[$role][$permission] ?? false);
        $this->saved = false;
    }

    /**
     * Only replaces the permissions this component's own $matrix snapshot
     * knows about — a stale snapshot (page left open across a deploy that
     * added a new PermissionName case) has no key for that permission at
     * all, so it's left exactly as it is in the database instead of being
     * silently dropped by a wholesale syncPermissions() replace.
     */
    public function save(): void
    {
        $this->authorize('manage roles');

        foreach (RoleName::cases() as $roleName) {
            if ($roleName === RoleName::Admin) {
                continue;
            }

            $role = Role::findByName($roleName->value);
            $known = array_keys($this->matrix[$roleName->value] ?? []);
            $toggledOn = collect($this->matrix[$roleName->value] ?? [])->filter()->keys()->all();
            $unknownButGranted = $role->permissions->pluck('name')->diff($known)->all();

            $role->syncPermissions(array_unique(array_merge($toggledOn, $unknownButGranted)));
        }

        $this->saved = true;
        $this->loadMatrix();
    }

    public function render()
    {
        $permissionGroups = collect(PermissionName::cases())
            ->groupBy(fn (PermissionName $p) => $p->group());

        return view('livewire.roles.index', [
            'roles' => RoleName::cases(),
            'permissionGroups' => $permissionGroups,
        ]);
    }
}
