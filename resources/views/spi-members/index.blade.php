<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ __('Data Ahli (SPI)') }}</h1>
            <p class="mt-1 text-sm text-slate-500">{{ __('Senarai ahli IKRAM Setiawangsa dari sistem SPI (Modul 03–05).') }}</p>
        </div>
    </x-slot>

    <livewire:spi-members.index />
</x-app-layout>
