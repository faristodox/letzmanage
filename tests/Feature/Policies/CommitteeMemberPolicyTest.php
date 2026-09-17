<?php

namespace Tests\Feature\Policies;

use App\Enums\RoleName;
use App\Models\CommitteeMember;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommitteeMemberPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_admin_can_manage_committee_members(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);

        $committeeMember = CommitteeMember::factory()->create();

        $this->assertTrue($admin->can('viewAny', CommitteeMember::class));
        $this->assertTrue($admin->can('create', CommitteeMember::class));
        $this->assertTrue($admin->can('update', $committeeMember));
        $this->assertTrue($admin->can('delete', $committeeMember));
    }

    public function test_manager_can_manage_committee_members(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole(RoleName::Manager->value);

        $committeeMember = CommitteeMember::factory()->create();

        $this->assertTrue($manager->can('viewAny', CommitteeMember::class));
        $this->assertTrue($manager->can('create', CommitteeMember::class));
        $this->assertTrue($manager->can('update', $committeeMember));
        $this->assertTrue($manager->can('delete', $committeeMember));
    }

    public function test_staff_cannot_manage_committee_members_by_default(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole(RoleName::Staff->value);

        $committeeMember = CommitteeMember::factory()->create();

        $this->assertFalse($staff->can('viewAny', CommitteeMember::class));
        $this->assertFalse($staff->can('create', CommitteeMember::class));
        $this->assertFalse($staff->can('update', $committeeMember));
        $this->assertFalse($staff->can('delete', $committeeMember));
    }
}
