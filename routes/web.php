<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');

Route::view('book', 'public.booking')->name('booking.request');

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
    Route::view('bookings', 'bookings.index')->name('bookings.index');
    Route::view('bookings/calendar', 'bookings.calendar')->name('bookings.calendar');
    Route::view('spi-members', 'spi-members.index')->name('spi-members.index');
});

Route::post('telegram/webhook', App\Http\Controllers\TelegramWebhookController::class);

require __DIR__.'/auth.php';
