<div>
    @if ($step === 'success')
        <div class="rounded-2xl border border-emerald-100 bg-emerald-50 p-8 text-center">
            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-emerald-100 text-emerald-600">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                </svg>
            </div>
            <h2 class="mt-4 text-xl font-bold text-slate-900">{{ __('Check-in Successful') }}</h2>
            <p class="mt-2 text-sm text-slate-600">
                {{ __('You have successfully checked in.') }}
                @if ($matchedResponse?->reference)
                    <br>{{ $matchedResponse->reference }}
                @endif
            </p>
        </div>

    @elseif ($step === 'already')
        <div class="rounded-2xl border border-amber-100 bg-amber-50 p-8 text-center">
            <h2 class="text-xl font-bold text-slate-900">{{ __('Already Checked In') }}</h2>
            <p class="mt-2 text-sm text-slate-600">
                @if ($matchedResponse?->checkIn)
                    {{ __('You already checked in at :time.', ['time' => $matchedResponse->checkIn->checked_in_at->format('g:i A')]) }}
                @else
                    {{ __('This registration has already been checked in.') }}
                @endif
            </p>
        </div>

    @elseif ($step === 'found')
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
            <h2 class="text-lg font-semibold text-slate-900">{{ __('Registration Found') }}</h2>

            <dl class="mt-4 divide-y divide-slate-100">
                @if ($matchedResponse?->reference)
                    <div class="py-2 flex justify-between text-sm">
                        <dt class="text-slate-500">{{ __('Registration ID') }}</dt>
                        <dd class="font-medium text-slate-900">{{ $matchedResponse->reference }}</dd>
                    </div>
                @endif
                @foreach ($checkinFields as $field)
                    <div class="py-2 flex justify-between gap-4 text-sm">
                        <dt class="text-slate-500">{{ $field->label }}</dt>
                        <dd class="font-medium text-slate-900 text-right">{{ $matchedResponse->answers[$field->id] ?? '—' }}</dd>
                    </div>
                @endforeach
            </dl>

            <button type="button" wire:click="confirmCheckIn" class="mt-6 w-full rounded-lg bg-gradient-to-r from-indigo-600 to-violet-600 px-6 py-3 text-sm font-semibold text-white shadow-lg shadow-indigo-200 transition hover:shadow-indigo-300">
                {{ __('Confirm Check-in') }}
            </button>
        </div>

    @elseif ($step === 'not_found')
        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-8 text-center">
            <h2 class="text-xl font-bold text-slate-900">{{ __('Registration Not Found') }}</h2>
            <p class="mt-2 text-sm text-slate-600">
                {{ __('We could not find a registration matching the information provided.') }}
            </p>

            @if ($eventForm->checkin_onsite_registration_enabled)
                <button type="button" wire:click="startOnsiteRegistration" class="mt-6 w-full rounded-lg bg-gradient-to-r from-indigo-600 to-violet-600 px-6 py-3 text-sm font-semibold text-white shadow-lg shadow-indigo-200 transition hover:shadow-indigo-300">
                    {{ __('Register Now') }}
                </button>
            @else
                <p class="mt-4 text-sm font-medium text-slate-700">
                    {{ __('Please contact the registration counter.') }}
                </p>
            @endif
        </div>

    @elseif ($step === 'register')
        <livewire:public.event-registration :event-form="$eventForm" />

    @elseif (! $eventForm->isCheckinOpen())
        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-8 text-center">
            <h2 class="text-xl font-bold text-slate-900">{{ __('Check-in Not Open') }}</h2>
            <p class="mt-2 text-sm text-slate-600">{{ __('Event check-in is not currently open.') }}</p>
        </div>

    @else
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
            <p class="text-sm text-slate-500">{{ __('Please enter your registered information.') }}</p>

            <form wire:submit="submitVerification" class="mt-4 space-y-5">
                @foreach ($checkinFields as $field)
                    <div>
                        <label class="block text-sm font-medium text-slate-700">{{ $field->label }}</label>
                        <div class="mt-1.5">
                            <x-event-forms.field-input :field="$field" :wire-model="'verify.'.$field->id" />
                        </div>
                        <x-input-error :messages="$errors->get('verify.'.$field->id)" class="mt-1.5" />
                    </div>
                @endforeach

                <button type="submit" class="w-full rounded-lg bg-gradient-to-r from-indigo-600 to-violet-600 px-6 py-3 text-sm font-semibold text-white shadow-lg shadow-indigo-200 transition hover:shadow-indigo-300">
                    {{ __('Check In') }}
                </button>
            </form>
        </div>
    @endif
</div>
