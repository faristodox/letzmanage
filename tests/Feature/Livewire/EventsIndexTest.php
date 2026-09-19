<?php

namespace Tests\Feature\Livewire;

use App\Enums\EventFormStatus;
use App\Enums\EventFormType;
use App\Enums\EventType;
use App\Enums\RoleName;
use App\Livewire\Events\Index;
use App\Models\Event;
use App\Models\EventForm;
use App\Models\EventFormResponse;
use App\Models\Organization;
use App\Models\OrganizationCalendarSetting;
use App\Models\Portfolio;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class EventsIndexTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_admin_can_create_an_event_and_is_redirected_to_the_registration_form_builder(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);

        Livewire::actingAs($admin)
            ->test(Index::class)
            ->call('create')
            ->set('title', 'Annual Dinner 2026')
            ->call('save')
            ->assertHasNoErrors();

        $event = Event::where('title', 'Annual Dinner 2026')->first();
        $this->assertNotNull($event);
        $this->assertSame('annual-dinner-2026', $event->slug);
        $this->assertSame($admin->id, $event->created_by);

        $registrationForm = $event->registrationForm;
        $this->assertNotNull($registrationForm);
        $this->assertSame(EventFormStatus::Draft, $registrationForm->status);
        $this->assertSame($admin->id, $registrationForm->created_by);
        $this->assertSame(EventType::Event, $event->type);
    }

    public function test_admin_can_create_a_committee_meeting_event(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);

        Livewire::actingAs($admin)
            ->test(Index::class)
            ->call('create')
            ->set('type', 'committee_meeting')
            ->set('title', 'Mesyuarat JKK')
            ->call('save')
            ->assertHasNoErrors();

        $event = Event::where('title', 'Mesyuarat JKK')->first();
        $this->assertNotNull($event);
        $this->assertSame(EventType::CommitteeMeeting, $event->type);
    }

    public function test_committee_member_cannot_create_a_committee_meeting_event(): void
    {
        $portfolio = Portfolio::factory()->create();
        $committeeMember = User::factory()->create(['portfolio_id' => $portfolio->id]);
        $committeeMember->assignRole(RoleName::CommitteeMember->value);

        Livewire::actingAs($committeeMember)
            ->test(Index::class)
            ->call('create')
            ->set('type', 'committee_meeting')
            ->set('title', 'Mesyuarat Cuba Bypass')
            ->call('save')
            ->assertHasErrors(['type']);

        $this->assertDatabaseMissing('events', ['title' => 'Mesyuarat Cuba Bypass']);
    }

    public function test_committee_member_can_still_create_a_regular_event(): void
    {
        $portfolio = Portfolio::factory()->create();
        $committeeMember = User::factory()->create(['portfolio_id' => $portfolio->id]);
        $committeeMember->assignRole(RoleName::CommitteeMember->value);

        Livewire::actingAs($committeeMember)
            ->test(Index::class)
            ->call('create')
            ->set('title', 'Program Wanita')
            ->call('save')
            ->assertHasNoErrors();

        $event = Event::where('title', 'Program Wanita')->first();
        $this->assertNotNull($event);
        $this->assertSame(EventType::Event, $event->type);
        $this->assertSame($portfolio->id, $event->portfolio_id);
    }

    public function test_admin_can_set_the_schedule_and_location_when_creating_an_event(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);

        Livewire::actingAs($admin)
            ->test(Index::class)
            ->call('create')
            ->set('title', 'Annual Dinner 2026')
            ->set('startDate', '2026-11-20')
            ->set('startTime', '19:00')
            ->set('location', 'Dewan Serbaguna, Kuala Lumpur')
            ->call('save')
            ->assertHasNoErrors();

        $event = Event::where('title', 'Annual Dinner 2026')->first();
        $this->assertSame('2026-11-20', $event->start_date->format('Y-m-d'));
        $this->assertSame('19:00', $event->start_time);
        $this->assertNull($event->end_date);
        $this->assertSame('Dewan Serbaguna, Kuala Lumpur', $event->location);
    }

    public function test_creating_an_event_with_end_date_before_start_date_fails_validation(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);

        Livewire::actingAs($admin)
            ->test(Index::class)
            ->call('create')
            ->set('title', 'Annual Dinner 2026')
            ->set('startDate', '2026-11-20')
            ->set('endDate', '2026-11-18')
            ->call('save')
            ->assertHasErrors(['endDate']);
    }

    public function test_schedule_fields_are_optional_when_creating_an_event(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);

        Livewire::actingAs($admin)
            ->test(Index::class)
            ->call('create')
            ->set('title', 'Annual Dinner 2026')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertNotNull(Event::where('title', 'Annual Dinner 2026')->first());
    }

    public function test_committee_member_only_sees_their_own_portfolios_events_in_the_list(): void
    {
        $wanita = Portfolio::factory()->create();
        $belia = Portfolio::factory()->create();

        $wanitaMember = User::factory()->create(['portfolio_id' => $wanita->id]);
        $wanitaMember->assignRole(RoleName::CommitteeMember->value);

        Event::factory()->create(['portfolio_id' => $wanita->id, 'title' => 'WANITA Event']);
        Event::factory()->create(['portfolio_id' => $belia->id, 'title' => 'Belia Event']);
        Event::factory()->create(['portfolio_id' => null, 'title' => 'General Event']);

        Livewire::actingAs($wanitaMember)
            ->test(Index::class)
            ->assertSee('WANITA Event')
            ->assertDontSee('Belia Event')
            ->assertDontSee('General Event');
    }

    public function test_admin_sees_events_from_every_portfolio(): void
    {
        $wanita = Portfolio::factory()->create();
        $belia = Portfolio::factory()->create();

        $admin = User::factory()->create(['portfolio_id' => null]);
        $admin->assignRole(RoleName::Admin->value);

        Event::factory()->create(['portfolio_id' => $wanita->id, 'title' => 'WANITA Event']);
        Event::factory()->create(['portfolio_id' => $belia->id, 'title' => 'Belia Event']);

        Livewire::actingAs($admin)
            ->test(Index::class)
            ->assertSee('WANITA Event')
            ->assertSee('Belia Event');
    }

    public function test_a_committee_members_new_event_is_auto_tagged_to_their_own_portfolio(): void
    {
        $wanita = Portfolio::factory()->create();

        $wanitaMember = User::factory()->create(['portfolio_id' => $wanita->id]);
        $wanitaMember->assignRole(RoleName::CommitteeMember->value);

        Livewire::actingAs($wanitaMember)
            ->test(Index::class)
            ->call('create')
            ->set('title', 'WANITA Retreat')
            ->call('save')
            ->assertHasNoErrors();

        $event = Event::where('title', 'WANITA Retreat')->first();
        $this->assertNotNull($event);
        $this->assertSame($wanita->id, $event->portfolio_id);
    }

    public function test_staff_cannot_create_an_event(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole(RoleName::Staff->value);

        Livewire::actingAs($staff)
            ->test(Index::class)
            ->assertForbidden();
    }

    public function test_deleting_an_event_also_deletes_its_registration_form_and_responses(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);

        $event = Event::factory()->create();
        $registrationForm = EventForm::factory()->for($event)->create();
        EventFormResponse::factory()->for($registrationForm, 'eventForm')->create();

        Livewire::actingAs($admin)
            ->test(Index::class)
            ->call('confirmDelete', $event->id)
            ->call('delete');

        $this->assertNull(Event::find($event->id));
        $this->assertNull(EventForm::find($registrationForm->id));
        $this->assertSame(0, EventFormResponse::count());
    }

    public function test_deleting_a_synced_event_deletes_its_google_calendar_event_first(): void
    {
        Http::fake(['https://www.googleapis.com/calendar/v3/*' => Http::response([], 204)]);

        $organization = Organization::factory()->create();
        OrganizationCalendarSetting::factory()->for($organization)->sharedModeConnected()->create();

        $admin = User::factory()->create(['organization_id' => $organization->id]);
        $admin->assignRole(RoleName::Admin->value);

        $event = Event::factory()->for($organization)->create(['google_event_id' => 'gcal-event-to-delete']);

        Livewire::actingAs($admin)
            ->test(Index::class)
            ->call('confirmDelete', $event->id)
            ->call('delete');

        Http::assertSent(fn ($request) => $request->method() === 'DELETE' && str_contains($request->url(), 'gcal-event-to-delete'));
        $this->assertNull(Event::find($event->id));
    }

    public function test_deleting_an_unsynced_event_sends_nothing_to_google(): void
    {
        Http::fake();

        $organization = Organization::factory()->create();
        OrganizationCalendarSetting::factory()->for($organization)->sharedModeConnected()->create();

        $admin = User::factory()->create(['organization_id' => $organization->id]);
        $admin->assignRole(RoleName::Admin->value);

        $event = Event::factory()->for($organization)->create(['google_event_id' => null]);

        Livewire::actingAs($admin)
            ->test(Index::class)
            ->call('confirmDelete', $event->id)
            ->call('delete');

        Http::assertNothingSent();
    }

    public function test_deleting_an_event_also_deletes_its_banner_file(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('events/banner.jpg', 'fake-image-content');

        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);

        $event = Event::factory()->create(['banner_path' => 'events/banner.jpg']);
        EventForm::factory()->for($event)->create();

        Livewire::actingAs($admin)
            ->test(Index::class)
            ->call('confirmDelete', $event->id)
            ->call('delete');

        Storage::disk('public')->assertMissing('events/banner.jpg');
    }

    public function test_actions_column_links_to_registration_feedback_financial_and_report(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);

        $event = Event::factory()->create();
        $registrationForm = EventForm::factory()->for($event)->create();

        $response = $this->actingAs($admin)->get(route('events.index'));

        $response->assertOk();
        $response->assertSee(route('event-forms.builder', $registrationForm), false);
        $response->assertSee(route('event-forms.builder', $registrationForm).'#form-settings', false);
        $response->assertSee(route('events.finances', $event), false);
        $response->assertSee(route('events.report', $event), false);
    }

    public function test_feedback_action_links_directly_to_the_feedback_form_once_one_exists(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);

        $event = Event::factory()->create();
        EventForm::factory()->for($event)->create();
        $feedbackForm = EventForm::factory()->for($event)->feedback()->create();

        $this->actingAs($admin)
            ->get(route('events.index'))
            ->assertOk()
            ->assertSee(route('event-forms.builder', $feedbackForm), false);
    }

    public function test_feedback_action_creates_the_feedback_form_on_demand_when_none_exists(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);

        $event = Event::factory()->create();
        EventForm::factory()->for($event)->create();

        $this->assertNull($event->fresh()->feedbackForm);

        Livewire::actingAs($admin)
            ->test(Index::class)
            ->call('createFeedbackForm', $event->id)
            ->assertRedirect();

        $feedbackForm = $event->fresh()->feedbackForm;

        $this->assertNotNull($feedbackForm);
        $this->assertSame(EventFormType::Feedback, $feedbackForm->type);
    }

    public function test_feedback_action_reuses_the_existing_feedback_form_instead_of_duplicating_it(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);

        $event = Event::factory()->create();
        EventForm::factory()->for($event)->create();
        $feedbackForm = EventForm::factory()->for($event)->feedback()->create();

        Livewire::actingAs($admin)
            ->test(Index::class)
            ->call('createFeedbackForm', $event->id)
            ->assertRedirect(route('event-forms.builder', $feedbackForm));

        $this->assertSame(1, EventForm::where('event_id', $event->id)->where('type', EventFormType::Feedback)->count());
    }

    private function fakeGeminiResponse(array $data): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    ['content' => ['parts' => [['text' => json_encode($data)]]]],
                ],
            ]),
        ]);
    }

    public function test_committee_member_can_create_an_event_from_a_poster_message(): void
    {
        config(['services.gemini.api_key' => 'test-key']);

        $this->fakeGeminiResponse([
            'title' => 'Program Wanita',
            'start_date' => '2026-10-05',
            'start_time' => '09:00',
            'end_date' => null,
            'end_time' => null,
            'location' => 'Dewan Serbaguna',
            'description' => 'A community program.',
        ]);

        $portfolio = Portfolio::factory()->create();
        $committeeMember = User::factory()->create(['portfolio_id' => $portfolio->id]);
        $committeeMember->assignRole(RoleName::CommitteeMember->value);

        Livewire::actingAs($committeeMember)
            ->test(Index::class)
            ->call('createFromPoster')
            ->set('posterMessage', 'Program Wanita, 5 Oktober, 9 pagi, di Dewan Serbaguna')
            ->call('extractFromPoster')
            ->assertSet('posterStep', 'review')
            ->assertSet('title', 'Program Wanita')
            ->assertSet('location', 'Dewan Serbaguna')
            ->call('saveFromPoster')
            ->assertHasNoErrors()
            ->assertRedirect();

        $event = Event::where('title', 'Program Wanita')->first();
        $this->assertNotNull($event);
        $this->assertSame(EventType::Event, $event->type);
        $this->assertSame($portfolio->id, $event->portfolio_id);
        $this->assertSame('Dewan Serbaguna', $event->location);
        $this->assertSame('A community program.', $event->description);
        $this->assertNull($event->banner_path);
    }

    public function test_creating_from_poster_with_an_image_stores_it_as_the_events_banner(): void
    {
        Storage::fake('public');
        config(['services.gemini.api_key' => 'test-key']);

        $this->fakeGeminiResponse([
            'title' => 'Mesyuarat Agung',
            'start_date' => null,
            'start_time' => null,
            'end_date' => null,
            'end_time' => null,
            'location' => null,
            'description' => null,
        ]);

        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);

        Livewire::actingAs($admin)
            ->test(Index::class)
            ->call('createFromPoster')
            ->set('posterImage', UploadedFile::fake()->image('poster.jpg'))
            ->call('extractFromPoster')
            ->assertSet('posterStep', 'review')
            ->call('saveFromPoster')
            ->assertHasNoErrors();

        $event = Event::where('title', 'Mesyuarat Agung')->first();
        $this->assertNotNull($event);
        $this->assertNotNull($event->banner_path);
        Storage::disk('public')->assertExists($event->banner_path);
    }

    public function test_extracting_from_poster_requires_an_image_or_a_message(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);

        Livewire::actingAs($admin)
            ->test(Index::class)
            ->call('createFromPoster')
            ->call('extractFromPoster')
            ->assertHasErrors(['posterMessage'])
            ->assertSet('posterStep', 'upload');

        Http::assertNothingSent();
    }

    public function test_extraction_failure_shows_an_inline_error_and_creates_nothing(): void
    {
        config(['services.gemini.api_key' => 'test-key']);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response(['error' => 'boom'], 500),
        ]);

        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);

        Livewire::actingAs($admin)
            ->test(Index::class)
            ->call('createFromPoster')
            ->set('posterMessage', 'Some event message')
            ->call('extractFromPoster')
            ->assertSet('posterStep', 'upload')
            ->assertSet('extractionError', fn ($value) => ! empty($value));

        $this->assertSame(0, Event::count());
    }

}
