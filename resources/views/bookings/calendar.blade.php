<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ __('Booking Calendar') }}</h1>
            <p class="mt-1 text-sm text-slate-500">{{ __('See space availability at a glance and create new bookings.') }}</p>
        </div>
    </x-slot>

    <livewire:bookings.calendar />
</x-app-layout>
