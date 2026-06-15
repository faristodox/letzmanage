<?php

namespace Tests\Feature\Livewire;

use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Livewire\Users\Index;
use App\Models\Branch;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UsersIndexTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_admin_can_create_and_edit_a_user_with_a_role(): void
    {
        $branch = Branch::factory()->create();

        $admin = User::factory()->create(['branch_id' => $branch->id]);
        $admin->assignRole(RoleName::Admin->value);

        Livewire::actingAs($admin)
            ->test(Index::class)
            ->call('create')
            ->set('name', 'New Manager')
            ->set('email', 'manager@example.com')
            ->set('password', 'password123')
            ->set('role', RoleName::Manager->value)
            ->set('branch_id', $branch->id)
            ->set('status', UserStatus::Active->value)
            ->call('save')
            ->assertHasNoErrors();

        $user = User::where('email', 'manager@example.com')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole(RoleName::Manager->value));
        $this->assertSame($branch->id, $user->branch_id);

        Livewire::actingAs($admin)
            ->test(Index::class)
            ->call('edit', $user->id)
            ->set('name', 'Updated Manager')
            ->set('role', RoleName::Staff->value)
            ->call('save')
            ->assertHasNoErrors();

        $user->refresh();
        $this->assertSame('Updated Manager', $user->name);
        $this->assertTrue($user->hasRole(RoleName::Staff->value));
        $this->assertFalse($user->hasRole(RoleName::Manager->value));
    }

    public function test_admin_cannot_delete_their_own_account(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);

        Livewire::actingAs($admin)
            ->test(Index::class)
            ->call('confirmDelete', $admin->id)
            ->assertForbidden();
    }

    public function test_manager_cannot_access_users_component(): void
    {
        $branch = Branch::factory()->create();

        $manager = User::factory()->create(['branch_id' => $branch->id]);
        $manager->assignRole(RoleName::Manager->value);

        Livewire::actingAs($manager)
            ->test(Index::class)
            ->assertForbidden();
    }
}
