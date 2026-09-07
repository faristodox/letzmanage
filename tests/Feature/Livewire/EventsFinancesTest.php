<?php

namespace Tests\Feature\Livewire;

use App\Enums\EventTransactionType;
use App\Enums\RoleName;
use App\Livewire\Events\Finances;
use App\Models\Event;
use App\Models\EventForm;
use App\Models\EventFormResponse;
use App\Models\EventTransaction;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EventsFinancesTest extends TestCase
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

    public function test_admin_can_record_income(): void
    {
        $event = Event::factory()->create();
        $admin = $this->admin();

        Livewire::actingAs($admin)
            ->test(Finances::class, ['event' => $event])
            ->call('recordIncome')
            ->set('category', 'Registration Fee')
            ->set('amount', '50.00')
            ->set('transactionDate', now()->format('Y-m-d'))
            ->call('save')
            ->assertHasNoErrors();

        $transaction = EventTransaction::first();
        $this->assertNotNull($transaction);
        $this->assertSame(EventTransactionType::Income, $transaction->type);
        $this->assertSame('Registration Fee', $transaction->category);
        $this->assertSame('50.00', (string) $transaction->amount);
        $this->assertSame($admin->id, $transaction->recorded_by);
    }

    public function test_admin_can_record_expense(): void
    {
        $event = Event::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(Finances::class, ['event' => $event])
            ->call('recordExpense')
            ->set('category', 'Venue')
            ->set('amount', '200.00')
            ->set('transactionDate', now()->format('Y-m-d'))
            ->call('save')
            ->assertHasNoErrors();

        $transaction = EventTransaction::first();
        $this->assertSame(EventTransactionType::Expense, $transaction->type);
    }

    public function test_totals_and_net_balance_are_calculated_correctly(): void
    {
        $event = Event::factory()->create();
        EventTransaction::factory()->for($event)->create(['amount' => 100]);
        EventTransaction::factory()->for($event)->create(['amount' => 50]);
        EventTransaction::factory()->for($event)->expense()->create(['amount' => 30]);

        $component = Livewire::actingAs($this->admin())->test(Finances::class, ['event' => $event]);

        $this->assertSame(150.0, $component->viewData('totalIncome'));
        $this->assertSame(30.0, $component->viewData('totalExpenses'));
        $this->assertSame(120.0, $component->viewData('netBalance'));
    }

    public function test_net_balance_subtracts_gateway_fees(): void
    {
        $event = Event::factory()->create();
        EventTransaction::factory()->for($event)->create(['amount' => 100, 'gateway_fee_amount' => 3.5]);
        EventTransaction::factory()->for($event)->create(['amount' => 50]);
        EventTransaction::factory()->for($event)->expense()->create(['amount' => 30]);

        $component = Livewire::actingAs($this->admin())->test(Finances::class, ['event' => $event]);

        // Total Income stays the gross amount registrants actually paid.
        $this->assertSame(150.0, $component->viewData('totalIncome'));
        $this->assertSame(30.0, $component->viewData('totalExpenses'));
        // Net Balance reflects real cash-in-hand: 150 - 30 - 3.50 fee.
        $this->assertSame(116.5, $component->viewData('netBalance'));
        $this->assertSame(3.5, $event->totalGatewayFees());
    }

    public function test_finances_table_shows_the_fee_and_net_amount_for_a_gateway_transaction(): void
    {
        $event = Event::factory()->create();
        EventTransaction::factory()->for($event)->create(['amount' => 50, 'gateway_fee_amount' => 1.5]);

        Livewire::actingAs($this->admin())
            ->test(Finances::class, ['event' => $event])
            ->assertSee('Fee: 1.50')
            ->assertSee('Net: 48.50');
    }

    public function test_income_can_be_linked_to_a_registrant(): void
    {
        $event = Event::factory()->create();
        $registrationForm = EventForm::factory()->for($event)->create();
        $response = EventFormResponse::factory()->for($registrationForm, 'eventForm')->create();

        Livewire::actingAs($this->admin())
            ->test(Finances::class, ['event' => $event])
            ->call('recordIncome')
            ->set('category', 'Registration Fee')
            ->set('amount', '50.00')
            ->set('transactionDate', now()->format('Y-m-d'))
            ->set('responseId', $response->id)
            ->call('save')
            ->assertHasNoErrors();

        $transaction = EventTransaction::first();
        $this->assertSame($response->id, $transaction->event_form_response_id);
    }

    public function test_a_response_from_another_event_cannot_be_linked(): void
    {
        $event = Event::factory()->create();
        EventForm::factory()->for($event)->create();

        $otherEvent = Event::factory()->create();
        $otherForm = EventForm::factory()->for($otherEvent)->create();
        $foreignResponse = EventFormResponse::factory()->for($otherForm, 'eventForm')->create();

        Livewire::actingAs($this->admin())
            ->test(Finances::class, ['event' => $event])
            ->call('recordIncome')
            ->set('category', 'Registration Fee')
            ->set('amount', '50.00')
            ->set('transactionDate', now()->format('Y-m-d'))
            ->set('responseId', $foreignResponse->id)
            ->call('save')
            ->assertHasErrors(['responseId']);
    }

    public function test_amount_must_be_positive(): void
    {
        $event = Event::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(Finances::class, ['event' => $event])
            ->call('recordExpense')
            ->set('category', 'Venue')
            ->set('amount', '0')
            ->set('transactionDate', now()->format('Y-m-d'))
            ->call('save')
            ->assertHasErrors(['amount']);
    }

    public function test_admin_can_edit_a_transaction(): void
    {
        $event = Event::factory()->create();
        $transaction = EventTransaction::factory()->for($event)->create(['category' => 'Old', 'amount' => 10]);

        Livewire::actingAs($this->admin())
            ->test(Finances::class, ['event' => $event])
            ->call('edit', $transaction->id)
            ->set('category', 'New')
            ->set('amount', '25.00')
            ->call('save')
            ->assertHasNoErrors();

        $transaction->refresh();
        $this->assertSame('New', $transaction->category);
        $this->assertSame('25.00', (string) $transaction->amount);
    }

    public function test_admin_can_delete_a_transaction(): void
    {
        $event = Event::factory()->create();
        $transaction = EventTransaction::factory()->for($event)->create();

        Livewire::actingAs($this->admin())
            ->test(Finances::class, ['event' => $event])
            ->call('confirmDelete', $transaction->id)
            ->call('delete');

        $this->assertSame(0, EventTransaction::count());
    }

    public function test_staff_cannot_view_finances(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole(RoleName::Staff->value);

        $event = Event::factory()->create();

        Livewire::actingAs($staff)
            ->test(Finances::class, ['event' => $event])
            ->assertForbidden();
    }

    public function test_view_only_role_cannot_record_transactions(): void
    {
        $viewer = User::factory()->create();
        $viewer->givePermissionTo('view event responses');

        $event = Event::factory()->create();

        Livewire::actingAs($viewer)
            ->test(Finances::class, ['event' => $event])
            ->call('recordIncome')
            ->assertForbidden();
    }

    public function test_deleting_an_event_deletes_its_transactions(): void
    {
        $event = Event::factory()->create();
        EventTransaction::factory()->for($event)->create();

        $event->delete();

        $this->assertSame(0, EventTransaction::count());
    }

    public function test_deleting_a_linked_response_unlinks_rather_than_deletes_the_transaction(): void
    {
        $event = Event::factory()->create();
        $registrationForm = EventForm::factory()->for($event)->create();
        $response = EventFormResponse::factory()->for($registrationForm, 'eventForm')->create();
        $transaction = EventTransaction::factory()->for($event)->create(['event_form_response_id' => $response->id]);

        $response->delete();

        $this->assertSame(1, EventTransaction::count());
        $this->assertNull($transaction->refresh()->event_form_response_id);
    }
}
