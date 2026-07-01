<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ __('Roles & Permissions') }}</h1>
            <p class="mt-1 text-sm text-slate-500">{{ __('Control which features each role can access.') }}</p>
        </div>
    </x-slot>

    <livewire:roles.index />
</x-app-layout>
