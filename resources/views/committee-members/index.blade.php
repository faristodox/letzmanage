<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ __('Committee Members') }}</h1>
            <p class="mt-1 text-sm text-slate-500">{{ __('Manage the official list of committee/board members and their positions.') }}</p>
        </div>
    </x-slot>

    <livewire:committee-members.index />
</x-app-layout>
