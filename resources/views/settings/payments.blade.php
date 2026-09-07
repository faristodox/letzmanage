<x-app-layout>
    <x-slot name="header">
        <div>
            <a href="{{ route('settings.index') }}" wire:navigate class="text-sm font-medium text-indigo-600 hover:text-indigo-700">&larr; {{ __('Settings') }}</a>
            <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-900">{{ __('Payment Settings') }}</h1>
            <p class="mt-1 text-sm text-slate-500">{{ __('Connect CHIP and/or bank transfer so event registration forms can charge a fee.') }}</p>
        </div>
    </x-slot>

    <div class="max-w-3xl">
        <livewire:settings.payments />
    </div>
</x-app-layout>
