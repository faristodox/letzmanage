<?php

namespace Tests\Feature\Livewire;

use App\Enums\FormFieldType;
use App\Enums\FormStatus;
use App\Enums\RoleName;
use App\Livewire\Forms\Builder;
use App\Models\Form;
use App\Models\FormField;
use App\Models\FormResponse;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FormsBuilderTest extends TestCase
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

    public function test_admin_can_add_a_text_field(): void
    {
        $form = Form::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(Builder::class, ['form' => $form])
            ->call('addField')
            ->set('fieldLabel', 'Full Name')
            ->set('fieldType', FormFieldType::Text->value)
            ->set('fieldRequired', true)
            ->call('saveField')
            ->assertHasNoErrors();

        $field = $form->fields()->first();
        $this->assertSame('Full Name', $field->label);
        $this->assertTrue($field->required);
    }

    public function test_admin_can_add_a_select_field_with_options(): void
    {
        $form = Form::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(Builder::class, ['form' => $form])
            ->call('addField')
            ->set('fieldLabel', 'Meal Choice')
            ->set('fieldType', FormFieldType::Select->value)
            ->set('fieldOptions', "Chicken\nBeef")
            ->call('saveField')
            ->assertHasNoErrors();

        $field = $form->fields()->first();
        $this->assertSame(['Chicken', 'Beef'], $field->options);
    }

    public function test_field_order_can_be_moved_up_and_down(): void
    {
        $form = Form::factory()->create();
        $first = FormField::factory()->for($form, 'form')->create(['order' => 1]);
        $second = FormField::factory()->for($form, 'form')->create(['order' => 2]);

        Livewire::actingAs($this->admin())
            ->test(Builder::class, ['form' => $form])
            ->call('moveFieldUp', $second->id);

        $this->assertSame(1, $second->refresh()->order);
        $this->assertSame(2, $first->refresh()->order);
    }

    public function test_field_cannot_be_deleted_once_the_form_has_responses(): void
    {
        $form = Form::factory()->create();
        $field = FormField::factory()->for($form, 'form')->create();
        FormResponse::factory()->for($form, 'form')->create(['answers' => [$field->id => 'x']]);

        Livewire::actingAs($this->admin())
            ->test(Builder::class, ['form' => $form])
            ->call('confirmDeleteField', $field->id)
            ->call('deleteField');

        $this->assertNotNull($field->refresh());
    }

    public function test_field_type_cannot_change_once_the_form_has_responses_but_label_can(): void
    {
        $form = Form::factory()->create();
        $field = FormField::factory()->for($form, 'form')->create([
            'label' => 'Old Label',
            'type' => FormFieldType::Text,
        ]);
        FormResponse::factory()->for($form, 'form')->create(['answers' => [$field->id => 'x']]);

        Livewire::actingAs($this->admin())
            ->test(Builder::class, ['form' => $form])
            ->call('editField', $field->id)
            ->set('fieldLabel', 'New Label')
            ->set('fieldType', FormFieldType::Number->value)
            ->call('saveField')
            ->assertHasNoErrors();

        $field->refresh();
        $this->assertSame('New Label', $field->label);
        $this->assertSame(FormFieldType::Text, $field->type);
    }

    public function test_staff_cannot_access_the_builder(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole(RoleName::Staff->value);

        $form = Form::factory()->create();

        Livewire::actingAs($staff)
            ->test(Builder::class, ['form' => $form])
            ->assertForbidden();
    }

    public function test_admin_can_publish_and_close_the_form_via_form_settings(): void
    {
        $form = Form::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(Builder::class, ['form' => $form])
            ->set('status', FormStatus::Published->value)
            ->call('saveFormSettings')
            ->assertHasNoErrors();

        $this->assertSame(FormStatus::Published, $form->refresh()->status);

        Livewire::actingAs($this->admin())
            ->test(Builder::class, ['form' => $form])
            ->set('status', FormStatus::Closed->value)
            ->call('saveFormSettings')
            ->assertHasNoErrors();

        $this->assertSame(FormStatus::Closed, $form->refresh()->status);
    }
}
