<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ __('Data Ahli (SPI)') }}</h1>
            <p class="mt-1 text-sm text-slate-500">{{ __('Senarai ahli IKRAM Setiawangsa dari sistem SPI.') }}</p>
        </div>
    </x-slot>

    <div x-data="{ tab: 'moduls' }">
        {{-- Tabs --}}
        <div class="mb-6 flex gap-1 border-b border-slate-200">
            <button
                @click="tab = 'moduls'"
                type="button"
                class="border-b-2 px-4 py-2.5 text-sm font-semibold transition-colors"
                :class="tab === 'moduls' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-slate-500 hover:text-slate-700'"
            >
                {{ __('Ahli Mengikut Modul') }}
            </button>
            <button
                @click="tab = 'santuni'"
                type="button"
                class="border-b-2 px-4 py-2.5 text-sm font-semibold transition-colors"
                :class="tab === 'santuni' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-slate-500 hover:text-slate-700'"
            >
                {{ __('Ahli Baru untuk Disantuni') }}
            </button>
        </div>

        <div x-show="tab === 'moduls'">
            <livewire:spi-members.index />
        </div>

        <div x-show="tab === 'santuni'" x-cloak>
            <livewire:spi-members.santuni />
        </div>
    </div>
</x-app-layout>
