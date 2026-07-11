<?php

namespace App\Services;

use App\Enums\ApprovalMode;
use App\Enums\BranchStatus;
use App\Enums\OrganizationStatus;
use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\Branch;
use App\Models\OfficeSpaceType;
use App\Models\Organization;
use App\Models\User;
use App\Support\CurrentOrganization;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Creates a new organization (tenant) with ready-to-use defaults and its first
 * admin user. Shared by public self-serve signup and super-admin org creation.
 */
class OrganizationProvisioner
{
    /** Starter office space types every new organization begins with. */
    private const DEFAULT_SPACE_TYPES = ['Meeting Room', 'Hot Desk', 'Event Space'];

    public function __construct(
        private CurrentOrganization $current,
        private SystemSettingService $settings,
    ) {}

    /**
     * @param  array{name: string, email: string, password: string}  $admin
     */
    public function provision(string $organizationName, array $admin): User
    {
        return DB::transaction(function () use ($organizationName, $admin) {
            $organization = Organization::create([
                'name' => $organizationName,
                'slug' => $this->uniqueSlug($organizationName),
                'status' => OrganizationStatus::Active,
            ]);

            // Seed everything below inside the new organization's tenant context
            // so each record is stamped with its organization_id automatically.
            return $this->current->runFor($organization, function () use ($admin) {
                $branch = Branch::create([
                    'name' => 'Head Office',
                    'status' => BranchStatus::Active,
                ]);

                foreach (self::DEFAULT_SPACE_TYPES as $typeName) {
                    OfficeSpaceType::create(['name' => $typeName]);
                }

                $this->settings->setApprovalMode(ApprovalMode::Manual);

                $user = User::create([
                    'name' => $admin['name'],
                    'email' => $admin['email'],
                    'password' => Hash::make($admin['password']),
                    'branch_id' => $branch->id,
                    'status' => UserStatus::Active,
                    'is_super_admin' => false,
                ]);

                $user->assignRole(RoleName::Admin->value);

                return $user;
            });
        });
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'org';
        $slug = $base;
        $suffix = 1;

        while (Organization::where('slug', $slug)->exists()) {
            $slug = $base.'-'.(++$suffix);
        }

        return $slug;
    }
}
