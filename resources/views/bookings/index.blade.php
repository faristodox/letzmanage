<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ __('Bookings') }}</h1>
            <p class="mt-1 text-sm text-slate-500">{{ __('View, request, and manage office space bookings.') }}</p>
        </div>
    </x-slot>

    <livewire:bookings.index />
</x-app-layout>
