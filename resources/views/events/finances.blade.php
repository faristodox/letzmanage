<x-app-layout>
    <x-slot name="header">
        <div>
            <a href="{{ route('events.index') }}" wire:navigate class="text-sm font-medium text-indigo-600 hover:text-indigo-700">&larr; {{ __('Events') }}</a>
            <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-900">{{ $event->title }}</h1>
            <p class="mt-1 text-sm text-slate-500">{{ __('Finances') }}</p>
        </div>
    </x-slot>

    <livewire:events.finances :event="$event" />
</x-app-layout>
