<div>
    <div class="mb-4 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div class="flex w-full flex-col gap-4 sm:w-auto sm:flex-row sm:items-end">
            <div class="w-full sm:w-32">
                <x-input-label for="type" :value="__('Show')" />
                <select wire:model.live="type" id="type" class="mt-1 block w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="all">{{ __('All') }}</option>
                    <option value="booking">{{ __('Bookings') }}</option>
                    @if ($canViewEvents)
                        <option value="event">{{ __('Events') }}</option>
                    @endif
                </select>
            </div>

            @if ($type !== 'event')
                <div class="w-full sm:w-64">
                    <x-input-label for="space_id" :value="__('Office Space')" />
                    <select wire:model.live="space_id" id="space_id" class="mt-1 block w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @forelse ($spaces as $space)
                            <option value="{{ $space->id }}">{{ $space->name }}</option>
                        @empty
                            <option value="">{{ __('No office spaces available') }}</option>
                        @endforelse
                    </select>
                </div>
            @endif
        </div>

        <div class="flex shrink-0 items-center gap-3 whitespace-nowrap">
            <x-secondary-button wire:click="previousMonth" class="!whitespace-nowrap">&laquo; {{ __('Prev') }}</x-secondary-button>
            <span class="whitespace-nowrap text-sm font-semibold text-slate-900">{{ $monthStart->format('F Y') }}</span>
            <x-secondary-button wire:click="nextMonth" class="!whitespace-nowrap">{{ __('Next') }} &raquo;</x-secondary-button>
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
                    $dayEvents = $eventsByDay->get($key, collect());
                @endphp
                <div class="min-h-[100px] border-b border-r border-slate-100 p-2 {{ $isCurrentMonth ? 'bg-white' : 'bg-slate-50' }}">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-medium {{ $isCurrentMonth ? 'text-slate-700' : 'text-slate-400' }}">{{ $day->format('j') }}</span>
                        @if ($isCurrentMonth && ! $isPast && ($space_id || $canViewEvents))
                            <button wire:click="openCreate('{{ $key }}')" class="text-xs font-semibold text-indigo-500 hover:text-indigo-700" title="{{ __('New booking or event') }}">+</button>
                        @endif
                    </div>

                    <div class="mt-1 space-y-1">
                        @foreach ($dayBookings as $booking)
                            <div wire:click="viewBooking({{ $booking->id }})"
                                 class="cursor-pointer truncate rounded px-1.5 py-0.5 text-[11px] hover:ring-1 hover:ring-inset {{ $booking->status->value === 'approved' ? 'bg-emerald-50 text-emerald-700 hover:ring-emerald-600/30' : 'bg-amber-50 text-amber-700 hover:ring-amber-600/30' }}"
                                 title="{{ $booking->requesterName() }} ({{ ucfirst($booking->status->value) }}) {{ $booking->start_time->format('H:i') }}-{{ $booking->end_time->format('H:i') }}">
                                {{ $booking->start_time->format('H:i') }} {{ $booking->title ?: $booking->requesterName() }}
                            </div>
                        @endforeach

                        @foreach ($dayEvents as $event)
                            <div wire:click="viewEvent({{ $event->id }})"
                                 class="cursor-pointer truncate rounded bg-violet-50 px-1.5 py-0.5 text-[11px] text-violet-700 hover:ring-1 hover:ring-inset hover:ring-violet-600/30"
                                 title="{{ $event->title }} ({{ $event->registrationForm ? ucfirst($event->registrationForm->status->value) : __('No registration form') }}){{ $event->location ? ' — '.$event->location : '' }}">
                                {{ $event->start_time ? \Illuminate\Support\Carbon::createFromFormat('H:i', $event->start_time)->format('H:i').' ' : '' }}{{ $event->title }}
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="mt-4 flex gap-4 text-xs text-slate-500">
        <span class="inline-flex items-center gap-1.5"><span class="inline-block h-3 w-3 rounded bg-emerald-50 ring-1 ring-inset ring-emerald-600/20"></span> {{ __('Approved Booking') }}</span>
        <span class="inline-flex items-center gap-1.5"><span class="inline-block h-3 w-3 rounded bg-amber-50 ring-1 ring-inset ring-amber-600/20"></span> {{ __('Pending Booking') }}</span>
        @if ($canViewEvents)
            <span class="inline-flex items-center gap-1.5"><span class="inline-block h-3 w-3 rounded bg-violet-50 ring-1 ring-inset ring-violet-600/20"></span> {{ __('Event') }}</span>
        @endif
    </div>

    <!-- Create Booking / Event Modal -->
    @if ($showModal)
        <div class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0">
            <div class="fixed inset-0 bg-slate-900/50" wire:click="closeModal"></div>

            <div class="relative mx-auto mb-6 transform overflow-hidden rounded-2xl bg-white shadow-xl transition-all sm:w-full sm:max-w-lg">
                <div class="p-6 sm:p-8">
                    <h2 class="text-lg font-semibold text-slate-900">
                        {{ $modalTab === 'event' ? __('New Event') : __('New Booking') }} — {{ \Illuminate\Support\Carbon::parse($date)->format('D, j M Y') }}
                    </h2>

                    @if ($canViewEvents && $space_id)
                        <div class="mt-4 flex gap-1 rounded-lg bg-slate-100 p-1">
                            <button type="button" wire:click="$set('modalTab', 'event')"
                                class="flex-1 rounded-md px-3 py-1.5 text-sm font-medium transition {{ $modalTab === 'event' ? 'bg-white text-indigo-700 shadow-sm' : 'text-slate-500 hover:text-slate-700' }}">
                                {{ __('Event') }}
                            </button>
                            <button type="button" wire:click="$set('modalTab', 'booking')"
                                class="flex-1 rounded-md px-3 py-1.5 text-sm font-medium transition {{ $modalTab === 'booking' ? 'bg-white text-indigo-700 shadow-sm' : 'text-slate-500 hover:text-slate-700' }}">
                                {{ __('Booking') }}
                            </button>
                        </div>
                    @endif

                    @if ($modalTab === 'event' && $canViewEvents)
                        <form wire:submit="saveEvent" class="mt-4 space-y-4">
                            <div>
                                <x-input-label for="modal_event_title" :value="__('Title')" />
                                <x-text-input wire:model="eventTitle" id="modal_event_title" type="text" class="mt-1 block w-full" autofocus />
                                <x-input-error :messages="$errors->get('eventTitle')" class="mt-2" />
                            </div>

                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <x-input-label for="modal_event_start_date" :value="__('Event Date')" />
                                    <x-text-input wire:model="eventStartDate" id="modal_event_start_date" type="date" class="mt-1 block w-full" />
                                    <x-input-error :messages="$errors->get('eventStartDate')" class="mt-2" />
                                </div>
                                <div>
                                    <x-input-label for="modal_event_start_time" :value="__('Start Time (optional)')" />
                                    <x-text-input wire:model="eventStartTime" id="modal_event_start_time" type="time" class="mt-1 block w-full" />
                                    <x-input-error :messages="$errors->get('eventStartTime')" class="mt-2" />
                                </div>
                            </div>

                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <x-input-label for="modal_event_end_date" :value="__('End Date (optional)')" />
                                    <x-text-input wire:model="eventEndDate" id="modal_event_end_date" type="date" class="mt-1 block w-full" />
                                    <p class="mt-1 text-xs text-slate-400">{{ __('Leave blank for a one-day event.') }}</p>
                                    <x-input-error :messages="$errors->get('eventEndDate')" class="mt-2" />
                                </div>
                                <div>
                                    <x-input-label for="modal_event_end_time" :value="__('End Time (optional)')" />
                                    <x-text-input wire:model="eventEndTime" id="modal_event_end_time" type="time" class="mt-1 block w-full" />
                                    <x-input-error :messages="$errors->get('eventEndTime')" class="mt-2" />
                                </div>
                            </div>

                            <div>
                                <x-input-label for="modal_event_location" :value="__('Location (optional)')" />
                                <x-text-input wire:model="eventLocation" id="modal_event_location" type="text" class="mt-1 block w-full" />
                                <x-input-error :messages="$errors->get('eventLocation')" class="mt-2" />
                            </div>

                            <p class="text-xs text-slate-400">{{ __("You'll add registration fields on the next screen.") }}</p>

                            <div class="mt-6 flex justify-end gap-3">
                                <x-secondary-button type="button" wire:click="closeModal">{{ __('Cancel') }}</x-secondary-button>
                                <x-primary-button type="submit">{{ __('Create & Continue') }}</x-primary-button>
                            </div>
                        </form>
                    @else
                        <form wire:submit="save" class="mt-4 space-y-4">
                            @if ($errorMessage)
                                <div class="rounded-lg bg-red-50 p-3 text-sm text-red-700 ring-1 ring-inset ring-red-600/10">
                                    {{ $errorMessage }}
                                </div>
                            @endif

                            <div>
                                <x-input-label for="modal_title" :value="__('Title (optional)')" />
                                <x-text-input wire:model="title" id="modal_title" type="text" class="mt-1 block w-full" />
                                <x-input-error :messages="$errors->get('title')" class="mt-2" />
                            </div>

                            <div class="grid grid-cols-2 gap-4">
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
                    @endif
                </div>
            </div>
        </div>
    @endif

    <!-- Booking / Event Details Modal -->
    @if ($viewingBooking || $viewingEvent)
        <div class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0">
            <div class="fixed inset-0 bg-slate-900/50" wire:click="closeView"></div>

            <div class="relative mx-auto mb-6 transform overflow-hidden rounded-2xl bg-white shadow-xl transition-all sm:w-full sm:max-w-md">
                <div class="p-6 sm:p-8">
                    @if ($viewingBooking)
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex items-center gap-2.5">
                                <span class="inline-block h-3 w-3 shrink-0 rounded-full {{ $viewingBooking->status->value === 'approved' ? 'bg-emerald-500' : 'bg-amber-500' }}"></span>
                                <h2 class="text-lg font-semibold text-slate-900">{{ $viewingBooking->title ?: $viewingBooking->space->name }}</h2>
                            </div>
                            <button wire:click="closeView" class="shrink-0 text-slate-400 hover:text-slate-600">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        <div class="mt-4 space-y-3 text-sm text-slate-600">
                            <div class="flex items-center gap-2.5">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5 shrink-0 text-slate-400">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                                </svg>
                                {{ $viewingBooking->start_time->format('D, j M Y') }}, {{ $viewingBooking->start_time->format('H:i') }}–{{ $viewingBooking->end_time->format('H:i') }}
                            </div>
                            <div class="flex items-center gap-2.5">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5 shrink-0 text-slate-400">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" />
                                </svg>
                                {{ $viewingBooking->space->name }}
                            </div>
                            <div class="flex items-center gap-2.5">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5 shrink-0 text-slate-400">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                                </svg>
                                {{ $viewingBooking->requesterName() }}
                            </div>
                            @if ($viewingBooking->notes)
                                <div class="flex items-start gap-2.5">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5 shrink-0 text-slate-400">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0ZM3.75 12h.007v.008H3.75V12Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm-.375 5.25h.007v.008H3.75v-.008Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                                    </svg>
                                    <span>{{ $viewingBooking->notes }}</span>
                                </div>
                            @endif
                            <div>
                                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset {{ $viewingBooking->status->value === 'approved' ? 'bg-emerald-50 text-emerald-700 ring-emerald-600/20' : 'bg-amber-50 text-amber-700 ring-amber-600/20' }}">
                                    {{ ucfirst($viewingBooking->status->value) }}
                                </span>
                            </div>
                        </div>

                        @if ($viewingBooking->status === App\Enums\BookingStatus::Pending && (auth()->user()->can('approve', $viewingBooking) || auth()->user()->can('reject', $viewingBooking)))
                            @if ($viewErrorMessage)
                                <div class="mt-4 rounded-lg bg-red-50 p-3 text-sm text-red-700 ring-1 ring-inset ring-red-600/10">
                                    {{ $viewErrorMessage }}
                                </div>
                            @endif

                            @if ($confirmingApprove)
                                <div class="mt-4 space-y-3 border-t border-slate-100 pt-4">
                                    <div>
                                        <x-input-label for="approveNote" :value="__('Note for requester (optional)')" />
                                        <textarea wire:model="approveNote" id="approveNote" rows="2" class="mt-1 block w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                                    </div>
                                    <div class="flex justify-end gap-2">
                                        <x-secondary-button type="button" wire:click="cancelConfirm">{{ __('Back') }}</x-secondary-button>
                                        <x-primary-button type="button" wire:click="approveBooking">{{ __('Confirm Approve') }}</x-primary-button>
                                    </div>
                                </div>
                            @elseif ($confirmingReject)
                                <div class="mt-4 space-y-3 border-t border-slate-100 pt-4">
                                    <div>
                                        <x-input-label for="rejectReason" :value="__('Reason (optional)')" />
                                        <textarea wire:model="rejectReason" id="rejectReason" rows="2" class="mt-1 block w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                                    </div>
                                    <div class="flex justify-end gap-2">
                                        <x-secondary-button type="button" wire:click="cancelConfirm">{{ __('Back') }}</x-secondary-button>
                                        <x-danger-button type="button" wire:click="rejectBooking">{{ __('Confirm Reject') }}</x-danger-button>
                                    </div>
                                </div>
                            @else
                                <div class="mt-4 flex justify-end gap-2 border-t border-slate-100 pt-4">
                                    @can('reject', $viewingBooking)
                                        <x-secondary-button type="button" wire:click="confirmReject">{{ __('Reject') }}</x-secondary-button>
                                    @endcan
                                    @can('approve', $viewingBooking)
                                        <x-primary-button type="button" wire:click="confirmApprove">{{ __('Approve') }}</x-primary-button>
                                    @endcan
                                </div>
                            @endif
                        @endif
                    @elseif ($viewingEvent)
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex items-center gap-2.5">
                                <span class="inline-block h-3 w-3 shrink-0 rounded-full bg-violet-500"></span>
                                <h2 class="text-lg font-semibold text-slate-900">{{ $viewingEvent->title }}</h2>
                            </div>
                            <button wire:click="closeView" class="shrink-0 text-slate-400 hover:text-slate-600">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        <div class="mt-4 space-y-3 text-sm text-slate-600">
                            @if ($viewingEvent->scheduleLabel())
                                <div class="flex items-center gap-2.5">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5 shrink-0 text-slate-400">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                                    </svg>
                                    {{ $viewingEvent->scheduleLabel() }}
                                </div>
                            @endif
                            @if ($viewingEvent->location)
                                <div class="flex items-center gap-2.5">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5 shrink-0 text-slate-400">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" />
                                    </svg>
                                    {{ $viewingEvent->location }}
                                </div>
                            @endif
                            @if ($viewingEvent->description)
                                <div class="flex items-start gap-2.5">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5 shrink-0 text-slate-400">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0ZM3.75 12h.007v.008H3.75V12Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm-.375 5.25h.007v.008H3.75v-.008Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                                    </svg>
                                    <span>{{ $viewingEvent->description }}</span>
                                </div>
                            @endif
                            @if ($viewingEvent->registrationForm)
                                <div>
                                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset {{ $viewingEvent->registrationForm->status->value === 'published' ? 'bg-emerald-50 text-emerald-700 ring-emerald-600/20' : ($viewingEvent->registrationForm->status->value === 'closed' ? 'bg-amber-50 text-amber-700 ring-amber-600/20' : 'bg-slate-100 text-slate-600 ring-slate-500/10') }}">
                                        {{ ucfirst($viewingEvent->registrationForm->status->value) }}
                                    </span>
                                </div>
                            @endif
                        </div>

                        @can('update', $viewingEvent)
                            @if ($viewingEvent->registrationForm)
                                <div class="mt-6 flex justify-end">
                                    <a href="{{ route('event-forms.builder', $viewingEvent->registrationForm) }}" wire:navigate class="text-sm font-medium text-indigo-600 hover:text-indigo-700">
                                        {{ __('Manage Event →') }}
                                    </a>
                                </div>
                            @endif
                        @endcan
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
