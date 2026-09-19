<?php

use App\Enums\EventFormStatus;
use App\Enums\EventFormType;
use App\Enums\EventType;
use App\Enums\FormStatus;
use App\Enums\OrganizationStatus;
use App\Http\Controllers\ChipWebhookController;
use App\Http\Controllers\GoogleCalendarConnectionController;
use App\Http\Controllers\TelegramWebhookController;
use App\Models\Event;
use App\Models\EventForm;
use App\Models\Form;
use App\Models\Meeting;
use App\Models\Organization;
use App\Services\EventFormAnalyticsService;
use App\Services\EventReportService;
use App\Services\MeetingMinutesPrintService;
use App\Support\CurrentOrganization;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');

// Public guest booking page, scoped to one organization by its slug.
Route::get('book/{organization:slug}', function (Organization $organization) {
    abort_if($organization->status === OrganizationStatus::Suspended, 404);

    app(CurrentOrganization::class)->set($organization);

    return view('public.booking', ['organization' => $organization]);
})->name('booking.request');

// Backward-compatible shortcut: /book redirects to the first organization's page
// so existing links/QR codes keep working.
Route::get('book', function () {
    $organization = Organization::orderBy('id')->first();

    abort_unless($organization, 404);

    return redirect()->route('booking.request', $organization->slug);
});

// Public event pages, scoped to one organization + one of its events by slug.
// Both Event and EventForm are resolved explicitly with acrossOrganizations()
// (not scopeBindings() or the $event->registrationForm relation) — each model
// carries its own OrganizationScope, so a stale CurrentOrganization left over
// from a previous request (it's only set further down, once we know which org
// this request is actually for) could otherwise poison either lookup.
Route::get('events/{organization:slug}/{eventSlug}/register', function (Organization $organization, string $eventSlug) {
    abort_if($organization->status === OrganizationStatus::Suspended, 404);

    $event = Event::query()->acrossOrganizations()
        ->where('organization_id', $organization->id)
        ->where('slug', $eventSlug)
        ->firstOrFail();

    $eventForm = EventForm::query()->acrossOrganizations()
        ->where('event_id', $event->id)
        ->where('type', EventFormType::Registration)
        ->first();

    abort_unless($eventForm && $eventForm->status === EventFormStatus::Published, 404);

    app(CurrentOrganization::class)->set($organization);

    return view('public.event-registration', ['organization' => $organization, 'event' => $event, 'eventForm' => $eventForm]);
})->name('event-registration.show');

// CHIP's success/failure/cancel redirect target — always re-verifies the
// actual payment status server-side (via GET /purchases/{id}/) rather than
// trusting which redirect fired or any query string value.
Route::get('events/{organization:slug}/{eventSlug}/register/payment-return', function (Organization $organization, string $eventSlug) {
    abort_if($organization->status === OrganizationStatus::Suspended, 404);

    $event = Event::query()->acrossOrganizations()
        ->where('organization_id', $organization->id)
        ->where('slug', $eventSlug)
        ->firstOrFail();

    app(CurrentOrganization::class)->set($organization);

    return view('public.event-payment-return', ['organization' => $organization, 'event' => $event]);
})->name('event-registration.payment-return');

Route::get('events/{organization:slug}/{eventSlug}/feedback', function (Organization $organization, string $eventSlug) {
    abort_if($organization->status === OrganizationStatus::Suspended, 404);

    $event = Event::query()->acrossOrganizations()
        ->where('organization_id', $organization->id)
        ->where('slug', $eventSlug)
        ->firstOrFail();

    $eventForm = EventForm::query()->acrossOrganizations()
        ->where('event_id', $event->id)
        ->where('type', EventFormType::Feedback)
        ->first();

    abort_unless($eventForm && $eventForm->status === EventFormStatus::Published, 404);

    app(CurrentOrganization::class)->set($organization);

    return view('public.event-registration', ['organization' => $organization, 'event' => $event, 'eventForm' => $eventForm]);
})->name('event-feedback.show');

// Branches by the event's own type: a plain Event's check-in verifies
// against registration responses (existing EventForm-based flow); a
// Committee Meeting's check-in verifies against the Committee Members
// roster by IC number instead (no registration form involved at all).
Route::get('events/{organization:slug}/{eventSlug}/check-in', function (Organization $organization, string $eventSlug) {
    abort_if($organization->status === OrganizationStatus::Suspended, 404);

    $event = Event::query()->acrossOrganizations()
        ->where('organization_id', $organization->id)
        ->where('slug', $eventSlug)
        ->firstOrFail();

    if ($event->type === EventType::CommitteeMeeting) {
        abort_unless($event->checkin_enabled, 404);

        app(CurrentOrganization::class)->set($organization);

        return view('public.committee-meeting-checkin', ['organization' => $organization, 'event' => $event]);
    }

    $eventForm = EventForm::query()->acrossOrganizations()
        ->where('event_id', $event->id)
        ->where('type', EventFormType::Registration)
        ->first();

    abort_unless($eventForm && $eventForm->checkin_enabled, 404);

    app(CurrentOrganization::class)->set($organization);

    return view('public.event-checkin', ['organization' => $organization, 'event' => $event, 'eventForm' => $eventForm]);
})->name('event-checkin.show');

// Public form submission page, scoped to one organization + one of its forms
// by slug. Uses the "form" (singular) prefix — distinct from the admin
// "forms/{form}/..." routes below — so the two don't collide: both are
// 2-segment patterns under the same word, and this one (registered first)
// would otherwise swallow every admin forms.* request before it's ever
// reached. Same acrossOrganizations()-then-deferred-CurrentOrganization::set()
// pattern as the event registration route above, for the same reason.
Route::get('form/{organization:slug}/{formSlug}', function (Organization $organization, string $formSlug) {
    abort_if($organization->status === OrganizationStatus::Suspended, 404);

    $form = Form::query()->acrossOrganizations()
        ->where('organization_id', $organization->id)
        ->where('slug', $formSlug)
        ->firstOrFail();

    abort_unless($form->status === FormStatus::Published, 404);

    app(CurrentOrganization::class)->set($organization);

    return view('public.form-submission', ['organization' => $organization, 'form' => $form]);
})->name('form-submission.show');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

Route::get('profile/google-calendar/connect', [GoogleCalendarConnectionController::class, 'redirectForIndividual'])
    ->middleware(['auth'])
    ->name('profile.google-calendar.connect');
Route::get('profile/google-calendar/callback', [GoogleCalendarConnectionController::class, 'callbackForIndividual'])
    ->middleware(['auth'])
    ->name('profile.google-calendar.callback');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('branches', 'branches.index')->name('branches.index');
    Route::view('portfolios', 'portfolios.index')->name('portfolios.index');
    Route::view('office-spaces', 'office-spaces.index')->name('office-spaces.index');
    Route::view('users', 'users.index')->name('users.index');
    Route::view('settings', 'settings.index')->name('settings.index');
    Route::view('settings/payments', 'settings.payments')->name('settings.payments')->can('manage settings');
    Route::view('settings/calendar', 'settings.calendar')->name('settings.calendar')->can('manage settings');
    Route::get('settings/calendar/google/connect', [GoogleCalendarConnectionController::class, 'redirectForShared'])
        ->name('settings.calendar.google.connect')->can('manage settings');
    Route::get('settings/calendar/google/callback', [GoogleCalendarConnectionController::class, 'callbackForShared'])
        ->name('settings.calendar.google.callback')->can('manage settings');
    Route::view('roles', 'roles.index')->name('roles.index')->can('manage roles');
    Route::view('bookings', 'bookings.index')->name('bookings.index');
    Route::view('bookings/calendar', 'bookings.calendar')->name('bookings.calendar');
    Route::view('spi-members', 'spi-members.index')->name('spi-members.index')->middleware('spi-enabled')->can('view spi data');

    Route::view('events', 'events.index')->name('events.index');

    Route::get('events/{event}/finances', fn (Event $event) => view('events.finances', ['event' => $event]))
        ->name('events.finances')->can('viewFinances', 'event');

    Route::get('events/{event}/report', fn (Event $event) => view('events.report', ['event' => $event]))
        ->name('events.report')->can('viewFinances', 'event');

    Route::get('events/{event}/report/print', function (Event $event, EventReportService $reportService, EventFormAnalyticsService $analytics) {
        return view('events.report-print', [
            'event' => $event,
            ...$reportService->build($event, $analytics),
        ]);
    })->name('events.report.print')->can('viewFinances', 'event');

    Route::get('event-forms/{eventForm}/builder', fn (EventForm $eventForm) => view('event-forms.builder', ['eventForm' => $eventForm]))
        ->name('event-forms.builder')->can('update', 'eventForm');

    Route::get('event-forms/{eventForm}/responses', fn (EventForm $eventForm) => view('event-forms.responses', ['eventForm' => $eventForm]))
        ->name('event-forms.responses')->can('viewResponses', 'eventForm');

    Route::get('event-forms/{eventForm}/payments', fn (EventForm $eventForm) => view('event-forms.payments', ['eventForm' => $eventForm]))
        ->name('event-forms.payments')->can('reviewPayments', 'eventForm');

    // Lets the form owner see the exact public page — including Draft/Closed
    // forms, which the real public routes above 404 on.
    Route::get('event-forms/{eventForm}/preview', fn (EventForm $eventForm) => view('public.event-registration', [
        'organization' => $eventForm->event->organization,
        'event' => $eventForm->event,
        'eventForm' => $eventForm,
        'preview' => true,
    ]))->name('event-forms.preview')->can('view', 'eventForm');

    Route::view('forms', 'forms.index')->name('forms.index');

    Route::view('archive', 'archive.index')->name('archive.index');

    Route::view('meetings', 'meetings.index')->name('meetings.index');

    Route::get('meetings/{meeting}', fn (Meeting $meeting) => view('meetings.show', ['meeting' => $meeting]))
        ->name('meetings.show')->can('view', 'meeting');

    Route::get('meetings/{meeting}/print', function (Meeting $meeting, MeetingMinutesPrintService $printService) {
        $language = request()->query('lang') === 'ms' ? 'ms' : 'en';

        return view('meetings.minutes-print', [
            'meeting' => $meeting,
            'language' => $language,
            ...$printService->build($meeting, $language),
        ]);
    })->name('meetings.print')->can('view', 'meeting');

    Route::view('committee-members', 'committee-members.index')->name('committee-members.index');

    Route::get('forms/{form}/builder', fn (Form $form) => view('forms.builder', ['form' => $form]))
        ->name('forms.builder')->can('update', 'form');

    Route::get('forms/{form}/responses', fn (Form $form) => view('forms.responses', ['form' => $form]))
        ->name('forms.responses')->can('viewResponses', 'form');

    // Lets the form owner see the exact public page — including Draft/Closed
    // forms, which the real public route above 404s on.
    Route::get('forms/{form}/preview', fn (Form $form) => view('public.form-submission', [
        'organization' => $form->organization,
        'form' => $form,
        'preview' => true,
    ]))->name('forms.preview')->can('view', 'form');
});

// Platform (super-admin) area — manage all organizations across the SaaS.
Route::middleware(['auth', 'super-admin'])->prefix('admin')->group(function () {
    Route::view('organizations', 'admin.organizations.index')->name('admin.organizations.index');
});

Route::post('telegram/webhook', TelegramWebhookController::class);
Route::post('chip/webhook', ChipWebhookController::class)->name('chip.webhook');

require __DIR__.'/auth.php';
