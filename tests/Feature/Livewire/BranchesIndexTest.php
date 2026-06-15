<?php

namespace Tests\Feature\Livewire;

use App\Enums\BranchStatus;
use App\Enums\RoleName;
use App\Livewire\Branches\Index;
use App\Models\Branch;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BranchesIndexTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_admin_can_create_edit_and_delete_a_branch(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);

        Livewire::actingAs($admin)
            ->test(Index::class)
            ->call('create')
            ->set('name', 'New Branch')
            ->set('location', 'Penang')
            ->set('status', BranchStatus::Active->value)
            ->call('save')
            ->assertHasNoErrors();

        $branch = Branch::where('name', 'New Branch')->first();
        $this->assertNotNull($branch);

        Livewire::actingAs($admin)
            ->test(Index::class)
            ->call('edit', $branch->id)
            ->set('name', 'Updated Branch')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('Updated Branch', $branch->refresh()->name);

        Livewire::actingAs($admin)
            ->test(Index::class)
            ->call('confirmDelete', $branch->id)
            ->call('delete');

        $this->assertNull(Branch::find($branch->id));
    }

    public function test_staff_cannot_access_branches_component(): void
    {
        $branch = Branch::factory()->create();

        $staff = User::factory()->create(['branch_id' => $branch->id]);
        $staff->assignRole(RoleName::Staff->value);

        Livewire::actingAs($staff)
            ->test(Index::class)
            ->assertForbidden();
    }
}
