<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ __('Forms') }}</h1>
            <p class="mt-1 text-sm text-slate-500">{{ __('Build custom forms and collect public responses.') }}</p>
        </div>
    </x-slot>

    <livewire:forms.index />
</x-app-layout>
