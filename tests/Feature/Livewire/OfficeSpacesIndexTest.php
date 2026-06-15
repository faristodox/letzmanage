<?php

namespace Tests\Feature\Livewire;

use App\Enums\OfficeSpaceStatus;
use App\Enums\RoleName;
use App\Livewire\OfficeSpaces\Index;
use App\Models\Branch;
use App\Models\OfficeSpace;
use App\Models\OfficeSpaceType;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class OfficeSpacesIndexTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_manager_can_create_office_space_in_their_own_branch_only(): void
    {
        $branchA = Branch::factory()->create();
        $branchB = Branch::factory()->create();

        $manager = User::factory()->create(['branch_id' => $branchA->id]);
        $manager->assignRole(RoleName::Manager->value);

        Livewire::actingAs($manager)
            ->test(Index::class)
            ->call('create')
            ->set('name', 'Manager Room')
            ->set('type_id', OfficeSpaceType::where('name', 'Meeting Room')->value('id'))
            ->set('capacity', 4)
            ->set('facilities', 'projector, whiteboard')
            ->set('status', OfficeSpaceStatus::Active->value)
            ->call('save')
            ->assertHasNoErrors();

        $space = OfficeSpace::where('name', 'Manager Room')->first();
        $this->assertNotNull($space);
        $this->assertSame($branchA->id, $space->branch_id);
        $this->assertSame(['projector', 'whiteboard'], $space->facilities);

        // Manager cannot create or edit a space in another branch.
        $otherSpace = OfficeSpace::factory()->create(['branch_id' => $branchB->id]);

        Livewire::actingAs($manager)
            ->test(Index::class)
            ->call('edit', $otherSpace->id)
            ->assertForbidden();
    }

    public function test_admin_can_create_office_space_in_any_branch(): void
    {
        $branchA = Branch::factory()->create();
        $branchB = Branch::factory()->create();

        $admin = User::factory()->create(['branch_id' => $branchA->id]);
        $admin->assignRole(RoleName::Admin->value);

        Livewire::actingAs($admin)
            ->test(Index::class)
            ->call('create')
            ->set('branch_id', $branchB->id)
            ->set('name', 'Admin Room')
            ->set('type_id', OfficeSpaceType::where('name', 'Hot Desk')->value('id'))
            ->set('capacity', 1)
            ->set('status', OfficeSpaceStatus::Active->value)
            ->call('save')
            ->assertHasNoErrors();

        $space = OfficeSpace::where('name', 'Admin Room')->first();
        $this->assertSame($branchB->id, $space->branch_id);
    }

    public function test_admin_can_upload_an_image_for_an_office_space(): void
    {
        Storage::fake('public');

        $branch = Branch::factory()->create();

        $admin = User::factory()->create(['branch_id' => $branch->id]);
        $admin->assignRole(RoleName::Admin->value);

        Livewire::actingAs($admin)
            ->test(Index::class)
            ->call('create')
            ->set('branch_id', $branch->id)
            ->set('name', 'Photo Room')
            ->set('type_id', OfficeSpaceType::where('name', 'Meeting Room')->value('id'))
            ->set('capacity', 4)
            ->set('status', OfficeSpaceStatus::Active->value)
            ->set('image', UploadedFile::fake()->image('room.jpg'))
            ->call('save')
            ->assertHasNoErrors();

        $space = OfficeSpace::where('name', 'Photo Room')->first();
        $this->assertNotNull($space->image_path);
        Storage::disk('public')->assertExists($space->image_path);
    }

    public function test_staff_can_view_but_not_create_office_spaces(): void
    {
        $branch = Branch::factory()->create();
        OfficeSpace::factory()->create(['branch_id' => $branch->id]);

        $staff = User::factory()->create(['branch_id' => $branch->id]);
        $staff->assignRole(RoleName::Staff->value);

        Livewire::actingAs($staff)
            ->test(Index::class)
            ->assertOk()
            ->call('create')
            ->assertForbidden();
    }
}
