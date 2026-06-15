<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ __('Settings') }}</h1>
            <p class="mt-1 text-sm text-slate-500">{{ __('Configure booking approvals and office space types for your organization.') }}</p>
        </div>
    </x-slot>

    <div class="max-w-3xl">
        <livewire:settings.index />
    </div>
</x-app-layout>
