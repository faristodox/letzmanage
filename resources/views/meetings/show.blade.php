<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ __('AI MoM') }}</h1>
        </div>
    </x-slot>

    <livewire:meetings.show :meeting="$meeting" />
</x-app-layout>
