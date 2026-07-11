<?php

use App\Enums\OrganizationStatus;
use App\Models\Organization;
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

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('branches', 'branches.index')->name('branches.index');
    Route::view('office-spaces', 'office-spaces.index')->name('office-spaces.index');
    Route::view('users', 'users.index')->name('users.index');
    Route::view('settings', 'settings.index')->name('settings.index');
    Route::view('roles', 'roles.index')->name('roles.index')->can('manage roles');
    Route::view('bookings', 'bookings.index')->name('bookings.index');
    Route::view('bookings/calendar', 'bookings.calendar')->name('bookings.calendar');
    Route::view('spi-members', 'spi-members.index')->name('spi-members.index')->middleware('spi-enabled')->can('view spi data');
});

// Platform (super-admin) area — manage all organizations across the SaaS.
Route::middleware(['auth', 'super-admin'])->prefix('admin')->group(function () {
    Route::view('organizations', 'admin.organizations.index')->name('admin.organizations.index');
});

Route::post('telegram/webhook', App\Http\Controllers\TelegramWebhookController::class);

require __DIR__.'/auth.php';
