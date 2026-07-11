<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ __('Dashboard') }}</h1>
            <p class="mt-1 text-sm text-slate-500">{{ __('Welcome back, :name!', ['name' => auth()->user()->name]) }}</p>
        </div>
    </x-slot>

    <div class="space-y-6">
        @php $currentOrg = app(\App\Support\CurrentOrganization::class)->get(); @endphp
        @if ($currentOrg)
            @php $bookingUrl = route('booking.request', $currentOrg->slug); @endphp
            <div
                x-data="{
                    copied: false,
                    url: @js($bookingUrl),
                    async copy() {
                        try {
                            await navigator.clipboard.writeText(this.url);
                        } catch (e) {
                            this.$refs.urlInput.select();
                            document.execCommand('copy');
                        }
                        this.copied = true;
                        setTimeout(() => this.copied = false, 2000);
                    }
                }"
                class="rounded-xl border border-indigo-100 bg-gradient-to-br from-indigo-50 to-violet-50 p-5 shadow-sm"
            >
                <div class="flex items-start gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white text-indigo-600 shadow-sm ring-1 ring-indigo-100">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244" />
                        </svg>
                    </div>
                    <div class="min-w-0 flex-1">
                        <h2 class="text-sm font-semibold text-slate-900">{{ __('Your public booking link') }}</h2>
                        <p class="mt-0.5 text-xs text-slate-500">
                            {{ __('Share this link so anyone can request a booking at :name — no account needed.', ['name' => $currentOrg->name]) }}
                        </p>

                        <div class="mt-3 flex flex-col gap-2 sm:flex-row">
                            <input
                                type="text"
                                readonly
                                x-ref="urlInput"
                                value="{{ $bookingUrl }}"
                                @focus="$event.target.select()"
                                class="min-w-0 flex-1 rounded-lg border-slate-200 bg-white/80 font-mono text-sm text-slate-700 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            >
                            <div class="flex gap-2">
                                <button
                                    type="button"
                                    @click="copy()"
                                    class="inline-flex items-center justify-center gap-1.5 whitespace-nowrap rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700"
                                >
                                    <svg x-show="!copied" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-4 w-4">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0 0 13.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 0 1-.75.75H9a.75.75 0 0 1-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 0 1-2.25 2.25H6.75A2.25 2.25 0 0 1 4.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 0 1 1.927-.184" />
                                    </svg>
                                    <svg x-show="copied" x-cloak xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="h-4 w-4">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                    </svg>
                                    <span x-text="copied ? '{{ __('Copied!') }}' : '{{ __('Copy') }}'"></span>
                                </button>
                                <a
                                    href="{{ $bookingUrl }}"
                                    target="_blank"
                                    rel="noopener"
                                    class="inline-flex items-center justify-center gap-1.5 whitespace-nowrap rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50"
                                >
                                    {{ __('Open') }}
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-4 w-4">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                                    </svg>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif

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
