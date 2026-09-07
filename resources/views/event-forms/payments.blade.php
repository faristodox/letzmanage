<x-app-layout>
    <x-slot name="header">
        <div>
            <a href="{{ route('event-forms.builder', $eventForm) }}" wire:navigate class="text-sm font-medium text-indigo-600 hover:text-indigo-700">&larr; {{ __('Back to Form') }}</a>
            <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-900">{{ $eventForm->event->title }}</h1>
            <p class="mt-1 text-sm text-slate-500">{{ __('Payments') }}</p>
        </div>
    </x-slot>

    <livewire:event-forms.payments :event-form="$eventForm" />
</x-app-layout>
