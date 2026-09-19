<?php

namespace Tests\Feature\Livewire;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Livewire\Roles\Index;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RolesIndexTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);

        return $admin;
    }

    public function test_the_committee_member_role_shows_its_own_label_not_staffs(): void
    {
        // Regression test: the role-header Blade previously had a bare
        // @else that labeled every non-Admin/Manager role "Staff", which
        // would have silently mislabeled Committee Member too.
        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->assertSee('Committee Member')
            ->assertSeeInOrder(['Manager', 'Staff']);
    }

    public function test_admin_can_toggle_a_permission_for_the_committee_member_role(): void
    {
        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->assertSet('matrix.committee_member.'.PermissionName::ManageArchive->value, false)
            ->call('toggle', RoleName::CommitteeMember->value, PermissionName::ManageArchive->value)
            ->call('save');

        $committeeMember = Role::findByName(RoleName::CommitteeMember->value);
        $this->assertTrue($committeeMember->hasPermissionTo(PermissionName::ManageArchive->value));
    }
}
