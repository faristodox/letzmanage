<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ __('Office Spaces') }}</h1>
            <p class="mt-1 text-sm text-slate-500">{{ __('Manage the bookable spaces available at your branches.') }}</p>
        </div>
    </x-slot>

    <livewire:office-spaces.index />
</x-app-layout>
