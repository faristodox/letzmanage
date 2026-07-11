<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ __('Organizations') }}</h1>
            <p class="mt-1 text-sm text-slate-500">{{ __('Manage every organization (tenant) on the platform.') }}</p>
        </div>
    </x-slot>

    <livewire:admin.organizations.index />
</x-app-layout>
