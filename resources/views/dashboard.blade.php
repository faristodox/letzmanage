<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ __('Dashboard') }}</h1>
            <p class="mt-1 text-sm text-slate-500">{{ __('Welcome back, :name!', ['name' => auth()->user()->name]) }}</p>
        </div>
    </x-slot>

    <div class="space-y-6">
        @if (auth()->user()->branch)
            <div class="rounded-xl border border-slate-200 bg-white px-5 py-4 text-sm text-slate-600 shadow-sm">
                {{ __('Branch') }}: <span class="font-semibold text-slate-900">{{ auth()->user()->branch->name }}</span>
            </div>
        @endif

        <livewire:dashboard-stats />

        @can('create', App\Models\Booking::class)
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-slate-900">{{ __('Booking Calendar') }}</h2>
                    <a href="{{ route('bookings.calendar') }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-700">
                        {{ __('Open full calendar') }} &rarr;
                    </a>
                </div>

                <livewire:bookings.calendar />
            </div>
        @endcan
    </div>
</x-app-layout>
