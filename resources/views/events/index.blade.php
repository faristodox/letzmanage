<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ __('Events') }}</h1>
            <p class="mt-1 text-sm text-slate-500">{{ __('Create events, manage registration, check-in, and responses.') }}</p>
        </div>
    </x-slot>

    <livewire:events.index />
</x-app-layout>
