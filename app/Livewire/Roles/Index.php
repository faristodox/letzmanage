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

    public function save(): void
    {
        $this->authorize('manage roles');

        foreach (RoleName::cases() as $roleName) {
            if ($roleName === RoleName::Admin) {
                continue;
            }

            $role = Role::findByName($roleName->value);
            $permissions = collect($this->matrix[$roleName->value] ?? [])
                ->filter()
                ->keys()
                ->toArray();

            $role->syncPermissions($permissions);
        }

        $this->saved = true;
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
