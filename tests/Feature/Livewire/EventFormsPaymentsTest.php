<?php

namespace Tests\Feature\Livewire;

use App\Enums\EventPaymentMethod;
use App\Enums\EventPaymentStatus;
use App\Enums\EventTransactionSource;
use App\Enums\RoleName;
use App\Livewire\EventForms\Payments;
use App\Models\EventForm;
use App\Models\EventFormResponse;
use App\Models\EventPayment;
use App\Models\EventTransaction;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EventFormsPaymentsTest extends TestCase
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

    private function pendingPayment(EventForm $eventForm, array $overrides = []): EventPayment
    {
        $response = EventFormResponse::factory()->for($eventForm, 'eventForm')->create();

        return EventPayment::factory()->for($response, 'response')->create($overrides);
    }

    public function test_admin_can_approve_a_pending_bank_transfer_payment(): void
    {
        $eventForm = EventForm::factory()->create();
        $payment = $this->pendingPayment($eventForm, ['method' => EventPaymentMethod::BankTransfer, 'amount' => 25]);

        Livewire::actingAs($this->admin())
            ->test(Payments::class, ['eventForm' => $eventForm])
            ->call('approve', $payment->id);

        $payment->refresh();
        $this->assertSame(EventPaymentStatus::Paid, $payment->status);
        $this->assertNotNull($payment->event_transaction_id);

        $transaction = EventTransaction::find($payment->event_transaction_id);
        $this->assertSame(EventTransactionSource::BankTransfer, $transaction->source);
        $this->assertSame('25.00', (string) $transaction->amount);
    }

    public function test_admin_can_manually_approve_a_stuck_chip_payment(): void
    {
        $eventForm = EventForm::factory()->create();
        $payment = $this->pendingPayment($eventForm, ['method' => EventPaymentMethod::Chip, 'amount' => 30]);

        Livewire::actingAs($this->admin())
            ->test(Payments::class, ['eventForm' => $eventForm])
            ->call('approve', $payment->id);

        $payment->refresh();
        $this->assertSame(EventPaymentStatus::Paid, $payment->status);

        $transaction = EventTransaction::find($payment->event_transaction_id);
        $this->assertSame(EventTransactionSource::Gateway, $transaction->source);
    }

    public function test_admin_can_reject_a_bank_transfer_payment(): void
    {
        $eventForm = EventForm::factory()->create();
        $payment = $this->pendingPayment($eventForm, ['method' => EventPaymentMethod::BankTransfer]);
        $admin = $this->admin();

        Livewire::actingAs($admin)
            ->test(Payments::class, ['eventForm' => $eventForm])
            ->call('confirmReject', $payment->id)
            ->call('reject');

        $payment->refresh();
        $this->assertSame(EventPaymentStatus::Rejected, $payment->status);
        $this->assertSame($admin->id, $payment->reviewed_by);
        $this->assertNotNull($payment->reviewed_at);
        $this->assertSame(0, EventTransaction::count());
    }

    public function test_a_payment_from_a_different_form_cannot_be_approved_here(): void
    {
        $eventForm = EventForm::factory()->create();
        $otherForm = EventForm::factory()->create();
        $foreignPayment = $this->pendingPayment($otherForm);

        $this->expectException(ModelNotFoundException::class);

        Livewire::actingAs($this->admin())
            ->test(Payments::class, ['eventForm' => $eventForm])
            ->call('approve', $foreignPayment->id);
    }

    public function test_staff_cannot_access_payments(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole(RoleName::Staff->value);

        $eventForm = EventForm::factory()->create();

        Livewire::actingAs($staff)
            ->test(Payments::class, ['eventForm' => $eventForm])
            ->assertForbidden();
    }

    public function test_view_only_role_cannot_access_payments(): void
    {
        $viewer = User::factory()->create();
        $viewer->givePermissionTo('view event responses');

        $eventForm = EventForm::factory()->create();

        Livewire::actingAs($viewer)
            ->test(Payments::class, ['eventForm' => $eventForm])
            ->assertForbidden();
    }
}
