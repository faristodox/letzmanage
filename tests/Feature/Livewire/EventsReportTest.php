<?php

namespace Tests\Feature\Livewire;

use App\Enums\EventCheckInMethod;
use App\Enums\RoleName;
use App\Livewire\Events\Report;
use App\Models\Event;
use App\Models\EventCheckIn;
use App\Models\EventForm;
use App\Models\EventFormField;
use App\Models\EventFormResponse;
use App\Models\EventReportDetail;
use App\Models\EventReportItineraryItem;
use App\Models\EventTransaction;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class EventsReportTest extends TestCase
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

    private function buildScenario(): Event
    {
        $event = Event::factory()->create();

        $registrationForm = EventForm::factory()->for($event)->checkinEnabled()->create();
        $responses = EventFormResponse::factory()->for($registrationForm, 'eventForm')->count(3)->create();

        EventCheckIn::factory()->for($registrationForm, 'eventForm')->create([
            'event_form_response_id' => $responses[0]->id,
            'method' => EventCheckInMethod::Manual,
        ]);
        EventCheckIn::factory()->for($registrationForm, 'eventForm')->create([
            'event_form_response_id' => $responses[1]->id,
            'method' => EventCheckInMethod::Onsite,
        ]);

        EventTransaction::factory()->for($event)->create(['category' => 'Registration Fee', 'amount' => 100]);
        EventTransaction::factory()->for($event)->create(['category' => 'Sponsorship', 'amount' => 50]);
        EventTransaction::factory()->for($event)->expense()->create(['category' => 'Venue', 'amount' => 30]);

        $feedbackForm = EventForm::factory()->for($event)->feedback()->create();
        $mealField = EventFormField::factory()->for($feedbackForm, 'eventForm')->choice(['Good', 'Average', 'Poor'])->create(['label' => 'Rating']);
        $commentField = EventFormField::factory()->for($feedbackForm, 'eventForm')->create(['label' => 'Comments']);

        EventFormResponse::factory()->for($feedbackForm, 'eventForm')->create([
            'answers' => [$mealField->id => 'Good', $commentField->id => 'Great event!'],
        ]);
        EventFormResponse::factory()->for($feedbackForm, 'eventForm')->create([
            'answers' => [$mealField->id => 'Average', $commentField->id => 'Could be better.'],
        ]);

        return $event;
    }

    public function test_registration_and_checkin_stats_are_correct(): void
    {
        $event = $this->buildScenario();

        $component = Livewire::actingAs($this->admin())->test(Report::class, ['event' => $event]);

        $this->assertSame(3, $component->viewData('totalRegistered'));
        $this->assertSame(2, $component->viewData('checkedInCount'));
        $this->assertSame(1, $component->viewData('notCheckedInCount'));
        $this->assertSame(1, $component->viewData('onsiteCount'));
    }

    public function test_financial_summary_and_category_breakdown_are_correct(): void
    {
        $event = $this->buildScenario();

        $component = Livewire::actingAs($this->admin())->test(Report::class, ['event' => $event]);

        $this->assertSame(150.0, $component->viewData('totalIncome'));
        $this->assertSame(30.0, $component->viewData('totalExpenses'));
        $this->assertSame(120.0, $component->viewData('netBalance'));

        $incomeByCategory = $component->viewData('incomeByCategory');
        $this->assertSame(100.0, $incomeByCategory['Registration Fee']);
        $this->assertSame(50.0, $incomeByCategory['Sponsorship']);

        $expensesByCategory = $component->viewData('expensesByCategory');
        $this->assertSame(30.0, $expensesByCategory['Venue']);
    }

    public function test_feedback_summary_includes_chart_data_and_comments(): void
    {
        $event = $this->buildScenario();

        $component = Livewire::actingAs($this->admin())->test(Report::class, ['event' => $event]);

        $this->assertSame(2, $component->viewData('feedbackResponseCount'));

        $charts = $component->viewData('charts');
        $this->assertCount(1, $charts);
        $this->assertSame('Rating', $charts->first()['field']->label);

        $comments = $component->viewData('comments');
        $this->assertCount(1, $comments);
        $this->assertSame('Comments', $comments->first()['field']->label);
        $this->assertTrue($comments->first()['comments']->contains('Great event!'));
    }

    public function test_report_renders_gracefully_with_no_feedback_survey(): void
    {
        $event = Event::factory()->create();
        EventForm::factory()->for($event)->create();

        $component = Livewire::actingAs($this->admin())->test(Report::class, ['event' => $event]);

        $this->assertNull($component->viewData('feedbackForm'));
        $this->assertSame(0, $component->viewData('feedbackResponseCount'));
        $component->assertSee(__('No feedback survey created yet.'));
    }

    public function test_report_renders_gracefully_with_no_registration_form(): void
    {
        $event = Event::factory()->create();

        $component = Livewire::actingAs($this->admin())->test(Report::class, ['event' => $event]);

        $this->assertNull($component->viewData('registrationForm'));
        $this->assertSame(0, $component->viewData('totalRegistered'));
        $component->assertSee(__('No registration form created yet.'));
    }

    public function test_staff_cannot_view_the_report(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole(RoleName::Staff->value);

        $event = Event::factory()->create();

        Livewire::actingAs($staff)
            ->test(Report::class, ['event' => $event])
            ->assertForbidden();
    }

    public function test_view_only_role_can_view_the_report(): void
    {
        $viewer = User::factory()->create();
        $viewer->givePermissionTo('view event responses');

        $event = $this->buildScenario();

        Livewire::actingAs($viewer)
            ->test(Report::class, ['event' => $event])
            ->assertOk();
    }

    public function test_csv_export_contains_expected_sections(): void
    {
        $this->actingAs($this->admin());

        $event = $this->buildScenario();

        $component = new Report;
        $component->event = $event;

        ob_start();
        app()->call([$component, 'export'])->sendContent();
        $content = ob_get_clean();

        $this->assertStringContainsString('Registration & Check-in', $content);
        $this->assertStringContainsString('Financial Summary', $content);
        $this->assertStringContainsString('Income by Category', $content);
        $this->assertStringContainsString('Expenses by Category', $content);
        $this->assertStringContainsString('Feedback', $content);
        $this->assertStringContainsString('120.00', $content);
        $this->assertStringNotContainsString('Program Details', $content);
        $this->assertStringNotContainsString('Sign-off', $content);
    }

    public function test_admin_can_save_program_details_and_itinerary(): void
    {
        $event = Event::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(Report::class, ['event' => $event])
            ->set('eventDate', '2026-09-01')
            ->set('eventTime', '9:00 AM - 1:00 PM')
            ->set('theme', 'Unity in Diversity')
            ->set('venue', 'Community Hall')
            ->set('objectives', 'Foster community bonding.')
            ->set('problems', 'Minor delay at registration.')
            ->set('achievements', 'Over 100 attendees.')
            ->set('directorsRemarks', 'Well organized event.')
            ->set('itineraryRows', [
                ['time' => '9:00 AM', 'activity' => 'Registration'],
                ['time' => '', 'activity' => ''],
                ['time' => '10:00 AM', 'activity' => 'Opening Ceremony'],
            ])
            ->set('preparedByName', 'Ahmad')
            ->set('preparedByPosition', 'Secretary')
            ->set('preparedByDate', '2026-09-02')
            ->call('saveReportDetails')
            ->assertHasNoErrors();

        $detail = EventReportDetail::where('event_id', $event->id)->first();
        $this->assertNotNull($detail);
        $this->assertSame('2026-09-01', $detail->event_date->format('Y-m-d'));
        $this->assertSame('9:00 AM - 1:00 PM', $detail->event_time);
        $this->assertSame('Unity in Diversity', $detail->theme);
        $this->assertSame('Community Hall', $detail->venue);
        $this->assertSame('Ahmad', $detail->prepared_by_name);
        $this->assertSame('Secretary', $detail->prepared_by_position);
        $this->assertSame('2026-09-02', $detail->prepared_by_date->format('Y-m-d'));

        // Blank row dropped, order preserved.
        $items = EventReportItineraryItem::where('event_id', $event->id)->orderBy('order')->get();
        $this->assertCount(2, $items);
        $this->assertSame('9:00 AM', $items[0]->time);
        $this->assertSame('Registration', $items[0]->activity);
        $this->assertSame('10:00 AM', $items[1]->time);
        $this->assertSame('Opening Ceremony', $items[1]->activity);

        // Round-trips on reload.
        Livewire::actingAs($this->admin())
            ->test(Report::class, ['event' => $event])
            ->assertSet('theme', 'Unity in Diversity')
            ->assertSet('itineraryRows', [
                ['time' => '9:00 AM', 'activity' => 'Registration'],
                ['time' => '10:00 AM', 'activity' => 'Opening Ceremony'],
            ]);
    }

    public function test_admin_can_upload_signatures_for_the_sign_off_block(): void
    {
        Storage::fake('public');

        $event = Event::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(Report::class, ['event' => $event])
            ->set('preparedByName', 'Ahmad')
            ->set('preparedBySignature', UploadedFile::fake()->image('signature.png'))
            ->set('reviewedByName', 'Siti')
            ->set('reviewedBySignature', UploadedFile::fake()->image('signature2.png'))
            ->call('saveReportDetails')
            ->assertHasNoErrors();

        $detail = EventReportDetail::where('event_id', $event->id)->first();
        $this->assertNotNull($detail->prepared_by_signature_path);
        $this->assertNotNull($detail->reviewed_by_signature_path);
        $this->assertNull($detail->approved_by_signature_path);
        Storage::disk('public')->assertExists($detail->prepared_by_signature_path);
        Storage::disk('public')->assertExists($detail->reviewed_by_signature_path);
    }

    public function test_uploading_a_new_signature_replaces_and_deletes_the_old_file(): void
    {
        Storage::fake('public');

        $event = Event::factory()->create();
        $detail = EventReportDetail::factory()->for($event)->create();
        Storage::disk('public')->put('event-reports/old-signature.png', 'fake-image-content');
        $detail->update(['prepared_by_signature_path' => 'event-reports/old-signature.png']);

        Livewire::actingAs($this->admin())
            ->test(Report::class, ['event' => $event])
            ->set('preparedBySignature', UploadedFile::fake()->image('new-signature.png'))
            ->call('saveReportDetails')
            ->assertHasNoErrors();

        $detail->refresh();
        $this->assertNotSame('event-reports/old-signature.png', $detail->prepared_by_signature_path);
        Storage::disk('public')->assertMissing('event-reports/old-signature.png');
        Storage::disk('public')->assertExists($detail->prepared_by_signature_path);
    }

    public function test_admin_can_remove_a_signature(): void
    {
        Storage::fake('public');

        $event = Event::factory()->create();
        $detail = EventReportDetail::factory()->for($event)->create();
        Storage::disk('public')->put('event-reports/signature.png', 'fake-image-content');
        $detail->update(['approved_by_signature_path' => 'event-reports/signature.png']);

        Livewire::actingAs($this->admin())
            ->test(Report::class, ['event' => $event])
            ->set('removeApprovedBySignature', true)
            ->call('saveReportDetails')
            ->assertHasNoErrors();

        $this->assertNull($detail->refresh()->approved_by_signature_path);
        Storage::disk('public')->assertMissing('event-reports/signature.png');
    }

    public function test_print_route_renders_the_signature_image_when_present(): void
    {
        Storage::fake('public');

        $event = Event::factory()->create();
        EventReportDetail::factory()->for($event)->create([
            'prepared_by_name' => 'Ahmad bin Ismail',
            'prepared_by_signature_path' => 'event-reports/signature.png',
        ]);
        Storage::disk('public')->put('event-reports/signature.png', 'fake-image-content');

        $response = $this->actingAs($this->admin())->get(route('events.report.print', $event));

        $response->assertOk();
        $response->assertSee(Storage::url('event-reports/signature.png'), false);
    }

    public function test_view_only_role_cannot_save_program_details_but_sees_them_read_only(): void
    {
        $event = Event::factory()->create();
        EventReportDetail::factory()->for($event)->create(['theme' => 'Existing Theme']);

        $viewer = User::factory()->create();
        $viewer->givePermissionTo('view event responses');

        Livewire::actingAs($viewer)
            ->test(Report::class, ['event' => $event])
            ->assertSee('Existing Theme')
            ->call('saveReportDetails')
            ->assertForbidden();

        Livewire::actingAs($viewer)
            ->test(Report::class, ['event' => $event])
            ->call('addItineraryRow')
            ->assertForbidden();
    }

    public function test_deleting_an_event_deletes_its_report_detail_and_itinerary(): void
    {
        $event = Event::factory()->create();
        EventReportDetail::factory()->for($event)->create();
        EventReportItineraryItem::factory()->for($event)->create();

        $event->delete();

        $this->assertSame(0, EventReportDetail::count());
        $this->assertSame(0, EventReportItineraryItem::count());
    }

    public function test_print_route_is_forbidden_for_staff(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole(RoleName::Staff->value);

        $event = Event::factory()->create();

        $this->actingAs($staff)->get(route('events.report.print', $event))->assertForbidden();
    }

    public function test_print_route_renders_program_details_itinerary_and_financials(): void
    {
        $event = $this->buildScenario();
        EventReportDetail::factory()->for($event)->create([
            'theme' => 'Unity in Diversity',
            'objectives' => 'Foster community bonding.',
        ]);
        EventReportItineraryItem::factory()->for($event)->create(['time' => '9:00 AM', 'activity' => 'Registration', 'order' => 0]);

        $response = $this->actingAs($this->admin())->get(route('events.report.print', $event));

        $response->assertOk();
        $response->assertSee('Unity in Diversity');
        $response->assertSee('Foster community bonding.');
        $response->assertSee('Registration');
        $response->assertSee('120.00');
    }
}
