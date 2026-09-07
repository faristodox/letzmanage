<?php

namespace Tests\Feature\Livewire;

use App\Enums\RoleName;
use App\Livewire\Forms\Index;
use App\Models\Form;
use App\Models\FormResponse;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FormsIndexTest extends TestCase
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

    public function test_admin_can_create_a_form_and_is_redirected_to_the_builder(): void
    {
        $admin = $this->admin();

        Livewire::actingAs($admin)
            ->test(Index::class)
            ->call('create')
            ->set('title', 'Volunteer Sign-up')
            ->call('save')
            ->assertHasNoErrors();

        $form = Form::where('title', 'Volunteer Sign-up')->first();
        $this->assertNotNull($form);
        $this->assertSame('volunteer-sign-up', $form->slug);
        $this->assertSame($admin->id, $form->created_by);
    }

    public function test_staff_cannot_create_a_form(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole(RoleName::Staff->value);

        Livewire::actingAs($staff)
            ->test(Index::class)
            ->assertForbidden();
    }

    public function test_deleting_a_form_also_deletes_its_responses(): void
    {
        $admin = $this->admin();

        $form = Form::factory()->create();
        FormResponse::factory()->for($form, 'form')->create();

        Livewire::actingAs($admin)
            ->test(Index::class)
            ->call('confirmDelete', $form->id)
            ->call('delete');

        $this->assertNull(Form::find($form->id));
        $this->assertSame(0, FormResponse::count());
    }
}
