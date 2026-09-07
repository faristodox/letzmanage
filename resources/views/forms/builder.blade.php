<x-app-layout>
    <x-slot name="header">
        <div>
            <a href="{{ route('forms.index') }}" wire:navigate class="text-sm font-medium text-indigo-600 hover:text-indigo-700">&larr; {{ __('Forms') }}</a>
            <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-900">{{ $form->title }}</h1>
            <p class="text-sm text-slate-500">{{ __('Form Builder') }}</p>
        </div>
    </x-slot>

    <livewire:forms.builder :form="$form" />
</x-app-layout>
