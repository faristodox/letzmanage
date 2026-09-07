<?php

namespace Tests\Feature\Livewire;

use App\Enums\FormFieldType;
use App\Enums\RoleName;
use App\Livewire\Forms\Responses;
use App\Models\Form;
use App\Models\FormField;
use App\Models\FormResponse;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FormsResponsesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_admin_can_delete_a_response(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);

        $form = Form::factory()->create();
        $response = FormResponse::factory()->for($form, 'form')->create();

        Livewire::actingAs($admin)
            ->test(Responses::class, ['form' => $form])
            ->call('confirmDelete', $response->id)
            ->call('delete');

        $this->assertSame(0, $form->responses()->count());
    }

    public function test_analysis_counts_choice_field_answers(): void
    {
        $form = Form::factory()->create();
        $field = FormField::factory()->for($form, 'form')->create([
            'type' => FormFieldType::Select,
            'options' => ['Chicken', 'Beef'],
        ]);

        FormResponse::factory()->for($form, 'form')->create(['answers' => [$field->id => 'Chicken']]);
        FormResponse::factory()->for($form, 'form')->create(['answers' => [$field->id => 'Chicken']]);
        FormResponse::factory()->for($form, 'form')->create(['answers' => [$field->id => 'Beef']]);

        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);

        $charts = Livewire::actingAs($admin)
            ->test(Responses::class, ['form' => $form])
            ->viewData('charts');

        $chart = $charts->first();
        $counts = array_combine($chart['labels'], $chart['data']);

        $this->assertSame(2, $counts['Chicken']);
        $this->assertSame(1, $counts['Beef']);
    }

    public function test_analysis_sums_number_field_answers(): void
    {
        $form = Form::factory()->create();
        $amountField = FormField::factory()->for($form, 'form')->create([
            'label' => 'Amount (RM)',
            'type' => FormFieldType::Number,
        ]);

        FormResponse::factory()->for($form, 'form')->create(['answers' => [$amountField->id => 50]]);
        FormResponse::factory()->for($form, 'form')->create(['answers' => [$amountField->id => 25.5]]);
        FormResponse::factory()->for($form, 'form')->create(['answers' => [$amountField->id => null]]);

        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);

        $numberTotals = Livewire::actingAs($admin)
            ->test(Responses::class, ['form' => $form])
            ->viewData('numberTotals');

        $this->assertSame(75.5, $numberTotals->first()['total']);
    }

    public function test_manager_with_view_only_permission_cannot_delete_a_response(): void
    {
        $viewer = User::factory()->create();
        $viewer->givePermissionTo('view form responses');

        $form = Form::factory()->create();
        $response = FormResponse::factory()->for($form, 'form')->create();

        Livewire::actingAs($viewer)
            ->test(Responses::class, ['form' => $form])
            ->call('confirmDelete', $response->id)
            ->assertForbidden();
    }

    public function test_staff_cannot_view_responses(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole(RoleName::Staff->value);

        $form = Form::factory()->create();

        Livewire::actingAs($staff)
            ->test(Responses::class, ['form' => $form])
            ->assertForbidden();
    }
}
