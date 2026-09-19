<?php

namespace Tests\Feature\Livewire;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Livewire\Roles\Index;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
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

    public function test_saving_does_not_wipe_a_permission_granted_after_the_page_was_loaded(): void
    {
        // Regression test: save() used to sync a role's ENTIRE permission
        // list from the component's $matrix snapshot, which is built from
        // PermissionName::cases() at mount() time. A permission added to
        // that enum (e.g. via a seeder re-run) after the page was already
        // mounted has no key in the snapshot at all, so saving from that
        // stale page silently dropped it — even though nothing about it was
        // ever toggled off.
        $manager = Role::findByName(RoleName::Manager->value);

        $component = Livewire::actingAs($this->admin())->test(Index::class);

        // Simulate a brand new permission being granted to Manager (e.g. a
        // fresh PermissionName case seeded) after this page was mounted —
        // the component's $matrix has no key for it at all.
        Permission::findOrCreate('a brand new permission');
        $manager->givePermissionTo('a brand new permission');

        $component->call('toggle', RoleName::Manager->value, PermissionName::ManageMeetings->value)
            ->call('toggle', RoleName::Manager->value, PermissionName::ManageMeetings->value)
            ->call('save');

        $this->assertTrue($manager->fresh()->hasPermissionTo('a brand new permission'));
        $this->assertTrue($manager->fresh()->hasPermissionTo(PermissionName::ManageMeetings->value));
    }
}
