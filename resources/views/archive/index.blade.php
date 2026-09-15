<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ __('Archive') }}</h1>
            <p class="mt-1 text-sm text-slate-500">{{ __('Upload files here — they\'re stored in your organization\'s connected Google Drive.') }}</p>
        </div>
    </x-slot>

    <livewire:archive.index />
</x-app-layout>
