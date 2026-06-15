<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ __('Branches') }}</h1>
            <p class="mt-1 text-sm text-slate-500">{{ __('Manage your organization\'s branch locations.') }}</p>
        </div>
    </x-slot>

    <livewire:branches.index />
</x-app-layout>
