<x-app-layout>
    <x-slot name="header">
        <div>
            <a href="{{ route('settings.index') }}" wire:navigate class="text-sm font-medium text-indigo-600 hover:text-indigo-700">&larr; {{ __('Settings') }}</a>
            <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-900">{{ __('Google Integrations') }}</h1>
            <p class="mt-1 text-sm text-slate-500">{{ __('Connect a Google account to sync bookings/events to Calendar and store uploads in File Archive.') }}</p>
        </div>
    </x-slot>

    <div class="max-w-3xl">
        <livewire:settings.calendar />
    </div>
</x-app-layout>
