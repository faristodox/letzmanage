<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ __('Users') }}</h1>
            <p class="mt-1 text-sm text-slate-500">{{ __('Manage team members, roles, and branch assignments.') }}</p>
        </div>
    </x-slot>

    <livewire:users.index />
</x-app-layout>
