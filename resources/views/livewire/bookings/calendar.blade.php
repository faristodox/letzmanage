<div>
    <div class="mb-4 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div class="w-full sm:w-72">
            <x-input-label for="space_id" :value="__('Office Space')" />
            <select wire:model.live="space_id" id="space_id" class="mt-1 block w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                @forelse ($spaces as $space)
                    <option value="{{ $space->id }}">{{ $space->name }}</option>
                @empty
                    <option value="">{{ __('No office spaces available') }}</option>
                @endforelse
            </select>
        </div>

        <div class="flex items-center gap-3">
            <x-secondary-button wire:click="previousMonth">&laquo; {{ __('Prev') }}</x-secondary-button>
            <span class="text-sm font-semibold text-slate-900">{{ $monthStart->format('F Y') }}</span>
            <x-secondary-button wire:click="nextMonth">{{ __('Next') }} &raquo;</x-secondary-button>
        </div>
    </div>

    @if (session('status'))
        <div class="mb-4 rounded-lg bg-emerald-50 p-3 text-sm text-emerald-700 ring-1 ring-inset ring-emerald-600/20">
            {{ session('status') }}
        </div>
    @endif

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="grid grid-cols-7 text-xs font-semibold uppercase tracking-wider text-slate-500">
            @foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $dayName)
                <div class="border-b border-slate-200 bg-slate-50 px-2 py-2 text-center">{{ __($dayName) }}</div>
            @endforeach
        </div>

        <div class="grid grid-cols-7">
            @foreach ($days as $day)
                @php
                    $key = $day->format('Y-m-d');
                    $isCurrentMonth = $day->month === $monthStart->month;
                    $isPast = $day->endOfDay()->isPast();
                    $dayBookings = $bookingsByDay->get($key, collect());
                @endphp
                <div class="min-h-[100px] border-b border-r border-slate-100 p-2 {{ $isCurrentMonth ? 'bg-white' : 'bg-slate-50' }}">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-medium {{ $isCurrentMonth ? 'text-slate-700' : 'text-slate-400' }}">{{ $day->format('j') }}</span>
                        @if ($isCurrentMonth && ! $isPast && $space_id)
                            <button wire:click="openCreate('{{ $key }}')" class="text-xs font-semibold text-indigo-500 hover:text-indigo-700" title="{{ __('New booking') }}">+</button>
                        @endif
                    </div>

                    <div class="mt-1 space-y-1">
                        @foreach ($dayBookings as $booking)
                            <div class="truncate rounded px-1.5 py-0.5 text-[11px] {{ $booking->status->value === 'approved' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}"
                                 title="{{ $booking->requesterName() }} ({{ ucfirst($booking->status->value) }}) {{ $booking->start_time->format('H:i') }}-{{ $booking->end_time->format('H:i') }}">
                                {{ $booking->start_time->format('H:i') }} {{ $booking->title ?: $booking->requesterName() }}
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="mt-4 flex gap-4 text-xs text-slate-500">
        <span class="inline-flex items-center gap-1.5"><span class="inline-block h-3 w-3 rounded bg-emerald-50 ring-1 ring-inset ring-emerald-600/20"></span> {{ __('Approved') }}</span>
        <span class="inline-flex items-center gap-1.5"><span class="inline-block h-3 w-3 rounded bg-amber-50 ring-1 ring-inset ring-amber-600/20"></span> {{ __('Pending') }}</span>
    </div>

    <!-- Create Booking Modal -->
    @if ($showModal)
        <div class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0">
            <div class="fixed inset-0 bg-slate-900/50" wire:click="closeModal"></div>

            <div class="relative mx-auto mb-6 transform overflow-hidden rounded-2xl bg-white shadow-xl transition-all sm:w-full sm:max-w-md">
                <form wire:submit="save" class="p-6 sm:p-8">
                    <h2 class="text-lg font-semibold text-slate-900">{{ __('New Booking') }} — {{ \Illuminate\Support\Carbon::parse($date)->format('D, j M Y') }}</h2>

                    @if ($errorMessage)
                        <div class="mt-4 rounded-lg bg-red-50 p-3 text-sm text-red-700 ring-1 ring-inset ring-red-600/10">
                            {{ $errorMessage }}
                        </div>
                    @endif

                    <div class="mt-4">
                        <x-input-label for="modal_title" :value="__('Title (optional)')" />
                        <x-text-input wire:model="title" id="modal_title" type="text" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('title')" class="mt-2" />
                    </div>

                    <div class="mt-4 grid grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="modal_start_time" :value="__('Start Time')" />
                            <x-text-input wire:model="start_time" id="modal_start_time" type="time" class="mt-1 block w-full" />
                            <x-input-error :messages="$errors->get('start_time')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="modal_end_time" :value="__('End Time')" />
                            <x-text-input wire:model="end_time" id="modal_end_time" type="time" class="mt-1 block w-full" />
                            <x-input-error :messages="$errors->get('end_time')" class="mt-2" />
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end gap-3">
                        <x-secondary-button type="button" wire:click="closeModal">{{ __('Cancel') }}</x-secondary-button>
                        <x-primary-button type="submit">{{ __('Submit') }}</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
