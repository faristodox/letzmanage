<?php

namespace Tests\Feature\Livewire;

use App\Enums\EventFormFieldType;
use App\Enums\EventFormStatus;
use App\Enums\RoleName;
use App\Livewire\EventForms\Builder;
use App\Models\EventForm;
use App\Models\EventFormField;
use App\Models\EventFormResponse;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class EventFormsBuilderTest extends TestCase
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
        $eventForm = EventForm::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(Builder::class, ['eventForm' => $eventForm])
            ->call('addField')
            ->set('fieldLabel', 'Full Name')
            ->set('fieldType', EventFormFieldType::Text->value)
            ->set('fieldRequired', true)
            ->call('saveField')
            ->assertHasNoErrors();

        $field = $eventForm->fields()->first();
        $this->assertSame('Full Name', $field->label);
        $this->assertTrue($field->required);
    }

    public function test_admin_can_add_a_select_field_with_options(): void
    {
        $eventForm = EventForm::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(Builder::class, ['eventForm' => $eventForm])
            ->call('addField')
            ->set('fieldLabel', 'T-Shirt Size')
            ->set('fieldType', EventFormFieldType::Select->value)
            ->set('fieldOptions', "Small\nMedium\nLarge")
            ->call('saveField')
            ->assertHasNoErrors();

        $field = $eventForm->fields()->first();
        $this->assertSame(['Small', 'Medium', 'Large'], $field->options);
    }

    public function test_field_order_can_be_moved_up_and_down(): void
    {
        $eventForm = EventForm::factory()->create();
        $first = EventFormField::factory()->for($eventForm, 'eventForm')->create(['order' => 1]);
        $second = EventFormField::factory()->for($eventForm, 'eventForm')->create(['order' => 2]);

        Livewire::actingAs($this->admin())
            ->test(Builder::class, ['eventForm' => $eventForm])
            ->call('moveFieldUp', $second->id);

        $this->assertSame(1, $second->refresh()->order);
        $this->assertSame(2, $first->refresh()->order);
    }

    public function test_field_cannot_be_deleted_once_the_form_has_responses(): void
    {
        $eventForm = EventForm::factory()->create();
        $field = EventFormField::factory()->for($eventForm, 'eventForm')->create();
        EventFormResponse::factory()->for($eventForm, 'eventForm')->create(['answers' => [$field->id => 'x']]);

        Livewire::actingAs($this->admin())
            ->test(Builder::class, ['eventForm' => $eventForm])
            ->call('confirmDeleteField', $field->id)
            ->call('deleteField');

        $this->assertNotNull($field->refresh());
    }

    public function test_field_type_cannot_change_once_the_form_has_responses_but_label_can(): void
    {
        $eventForm = EventForm::factory()->create();
        $field = EventFormField::factory()->for($eventForm, 'eventForm')->create([
            'label' => 'Old Label',
            'type' => EventFormFieldType::Text,
        ]);
        EventFormResponse::factory()->for($eventForm, 'eventForm')->create(['answers' => [$field->id => 'x']]);

        Livewire::actingAs($this->admin())
            ->test(Builder::class, ['eventForm' => $eventForm])
            ->call('editField', $field->id)
            ->set('fieldLabel', 'New Label')
            ->set('fieldType', EventFormFieldType::Number->value)
            ->call('saveField')
            ->assertHasNoErrors();

        $field->refresh();
        $this->assertSame('New Label', $field->label);
        $this->assertSame(EventFormFieldType::Text, $field->type);
    }

    public function test_admin_can_upload_a_banner_image(): void
    {
        Storage::fake('public');

        $eventForm = EventForm::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(Builder::class, ['eventForm' => $eventForm])
            ->set('banner', UploadedFile::fake()->image('banner.jpg'))
            ->call('saveEventSettings')
            ->assertHasNoErrors();

        $event = $eventForm->event->refresh();
        $this->assertNotNull($event->banner_path);
        Storage::disk('public')->assertExists($event->banner_path);
    }

    public function test_admin_can_remove_the_banner_image(): void
    {
        Storage::fake('public');

        $eventForm = EventForm::factory()->create();
        $eventForm->event->update(['banner_path' => 'events/old-banner.jpg']);
        Storage::disk('public')->put('events/old-banner.jpg', 'fake-image-content');

        Livewire::actingAs($this->admin())
            ->test(Builder::class, ['eventForm' => $eventForm])
            ->set('removeBanner', true)
            ->call('saveEventSettings')
            ->assertHasNoErrors();

        $this->assertNull($eventForm->event->refresh()->banner_path);
        Storage::disk('public')->assertMissing('events/old-banner.jpg');
    }

    public function test_staff_cannot_access_the_builder(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole(RoleName::Staff->value);

        $eventForm = EventForm::factory()->create();

        Livewire::actingAs($staff)
            ->test(Builder::class, ['eventForm' => $eventForm])
            ->assertForbidden();
    }

    public function test_admin_can_publish_and_close_the_form_via_form_settings(): void
    {
        $eventForm = EventForm::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(Builder::class, ['eventForm' => $eventForm])
            ->set('status', EventFormStatus::Published->value)
            ->call('saveFormSettings')
            ->assertHasNoErrors();

        $this->assertSame(EventFormStatus::Published, $eventForm->refresh()->status);

        Livewire::actingAs($this->admin())
            ->test(Builder::class, ['eventForm' => $eventForm])
            ->set('status', EventFormStatus::Closed->value)
            ->call('saveFormSettings')
            ->assertHasNoErrors();

        $this->assertSame(EventFormStatus::Closed, $eventForm->refresh()->status);
    }
}
