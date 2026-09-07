<div class="space-y-6">
    @php
        $isFeedbackForm = $eventForm->type->value === 'feedback';
    @endphp

    <div class="flex flex-wrap justify-end gap-2">
        <a href="{{ route('event-forms.responses', $eventForm) }}" wire:navigate
            class="inline-flex shrink-0 items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-4 w-4">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0ZM3.75 12h.007v.008H3.75V12Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm-.375 5.25h.007v.008H3.75v-.008Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
            </svg>
            {{ __('Responses') }}
        </a>
        @if ($eventForm->payment_enabled)
            @can('reviewPayments', $eventForm)
                <a href="{{ route('event-forms.payments', $eventForm) }}" wire:navigate
                    class="inline-flex shrink-0 items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-4 w-4">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z" />
                    </svg>
                    {{ __('Payments') }}
                </a>
            @endcan
        @endif
        <a href="{{ route('event-forms.preview', $eventForm) }}" target="_blank" rel="noopener"
            class="inline-flex shrink-0 items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-4 w-4">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
            </svg>
            {{ __('Preview') }}
        </a>
    </div>

    @if (session('status'))
        <div class="rounded-lg bg-emerald-50 p-3 text-sm text-emerald-700 ring-1 ring-inset ring-emerald-600/20">
            {{ session('status') }}
        </div>
    @endif

    @if ($eventForm->status->value === 'published' && $event->organization)
        @php $publicFormUrl = $isFeedbackForm ? $event->feedbackUrl() : $event->publicUrl(); @endphp
        <div
            x-data="{
                copied: false,
                url: @js($publicFormUrl),
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
            <h2 class="text-sm font-semibold text-slate-900">
                {{ $isFeedbackForm ? __('Your public feedback link') : __('Your public registration link') }}
            </h2>
            <p class="mt-0.5 text-xs text-slate-500">
                {{ $isFeedbackForm ? __('Share this link so attendees can leave feedback — no account needed.') : __('Share this link so anyone can register — no account needed.') }}
            </p>

            <div class="mt-3 flex flex-col gap-2 sm:flex-row">
                <input type="text" readonly x-ref="urlInput" value="{{ $publicFormUrl }}" @focus="$event.target.select()"
                    class="min-w-0 flex-1 rounded-lg border-slate-200 bg-white/80 font-mono text-sm text-slate-700 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <div class="flex gap-2">
                    <button type="button" @click="copy()" class="inline-flex items-center justify-center gap-1.5 whitespace-nowrap rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700">
                        <span x-text="copied ? '{{ __('Copied!') }}' : '{{ __('Copy') }}'"></span>
                    </button>
                    <a href="{{ $publicFormUrl }}" target="_blank" rel="noopener" class="inline-flex items-center justify-center gap-1.5 whitespace-nowrap rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50">
                        {{ __('Open') }}
                    </a>
                </div>
            </div>
        </div>
    @endif

    <!-- Event Settings -->
    <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="text-sm font-semibold text-slate-900">{{ __('Event Settings') }}</h2>
        <p class="mt-0.5 text-xs text-slate-500">{{ __('Shared across the registration form, check-in, and feedback survey for this event.') }}</p>

        <form wire:submit="saveEventSettings" class="mt-4 space-y-4">
            <div>
                <x-input-label for="eventTitle" :value="__('Title')" />
                <x-text-input wire:model="eventTitle" id="eventTitle" type="text" class="mt-1 block w-full" />
                <x-input-error :messages="$errors->get('eventTitle')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="eventDescription" :value="__('Description')" />
                <textarea wire:model="eventDescription" id="eventDescription" rows="3" class="mt-1 block w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                <x-input-error :messages="$errors->get('eventDescription')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="banner" :value="__('Banner Image (optional)')" />
                <p class="mt-0.5 text-xs text-slate-400">
                    {{ __('Recommended size: 1200×400px (3:1 ratio), JPG or PNG, max 2MB. It appears above the title on the public pages.') }}
                </p>

                @if ($banner)
                    <img src="{{ $banner->temporaryUrl() }}" alt="{{ __('Preview') }}" class="mt-2 aspect-[3/1] w-full max-w-xs rounded-lg object-cover ring-1 ring-slate-200">
                @elseif ($existingBannerPath && ! $removeBanner)
                    <img src="{{ \Illuminate\Support\Facades\Storage::url($existingBannerPath) }}" alt="{{ __('Current banner') }}" class="mt-2 aspect-[3/1] w-full max-w-xs rounded-lg object-cover ring-1 ring-slate-200">
                @endif

                <input wire:model="banner" id="banner" type="file" accept="image/*" class="mt-2 block w-full text-sm text-slate-500 file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-indigo-700 hover:file:bg-indigo-100">
                <div wire:loading wire:target="banner" class="mt-1 text-xs text-slate-500">{{ __('Uploading...') }}</div>
                <x-input-error :messages="$errors->get('banner')" class="mt-2" />

                @if ($existingBannerPath && ! $banner)
                    <label class="mt-2 flex items-center gap-2 text-sm text-slate-600">
                        <input type="checkbox" wire:model="removeBanner" class="rounded border-slate-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                        {{ __('Remove current banner') }}
                    </label>
                @endif
            </div>

            <div class="flex justify-end">
                <x-primary-button type="submit">{{ __('Save Event Settings') }}</x-primary-button>
            </div>
        </form>
    </div>

    <!-- Form Settings -->
    <div id="form-settings" class="scroll-mt-6 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="text-sm font-semibold text-slate-900">
            {{ $isFeedbackForm ? __('Feedback Survey Settings') : __('Registration Form Settings') }}
        </h2>

        <form wire:submit="saveFormSettings" class="mt-4 space-y-4">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <x-input-label for="status" :value="__('Status')" />
                    <select wire:model="status" id="status" class="mt-1 block w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @foreach ($statuses as $statusOption)
                            <option value="{{ $statusOption->value }}">{{ ucfirst($statusOption->value) }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('status')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="closes_at" :value="__('Closes At (optional)')" />
                    <input wire:model="closes_at" id="closes_at" type="datetime-local" class="mt-1 block w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <x-input-error :messages="$errors->get('closes_at')" class="mt-2" />
                </div>
            </div>

            <div class="flex justify-end">
                <x-primary-button type="submit">{{ __('Save Form Settings') }}</x-primary-button>
            </div>
        </form>
    </div>

    <!-- Fields -->
    <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex items-center justify-between">
            <h2 class="text-sm font-semibold text-slate-900">{{ __('Fields') }}</h2>
            <x-secondary-button wire:click="addField">{{ __('Add Field') }}</x-secondary-button>
        </div>

        @if ($this->hasResponses())
            <p class="mt-3 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-700 ring-1 ring-inset ring-amber-600/20">
                {{ __('This form already has responses, so existing fields can no longer be deleted or have their type/options changed — only new fields may be added.') }}
            </p>
        @endif

        <div class="mt-4 divide-y divide-slate-100">
            @forelse ($fields as $index => $field)
                <div wire:key="field-{{ $field->id }}" class="flex items-center justify-between gap-4 py-3">
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-slate-900">
                            {{ $field->label }}
                            @if ($field->required)
                                <span class="text-red-500">*</span>
                            @endif
                        </p>
                        <p class="text-xs text-slate-500">
                            {{ ucfirst($field->type->value) }}
                            @if ($field->type->isChoice())
                                &middot; {{ implode(', ', $field->options ?? []) }}
                            @endif
                        </p>
                    </div>
                    <div class="flex shrink-0 items-center gap-3 text-sm font-medium">
                        <button type="button" wire:click="moveFieldUp({{ $field->id }})" @if ($index === 0) disabled @endif class="text-slate-400 hover:text-slate-600 disabled:opacity-30">&uarr;</button>
                        <button type="button" wire:click="moveFieldDown({{ $field->id }})" @if ($index === $fields->count() - 1) disabled @endif class="text-slate-400 hover:text-slate-600 disabled:opacity-30">&darr;</button>
                        <button type="button" wire:click="editField({{ $field->id }})" class="text-indigo-600 hover:text-indigo-700">{{ __('Edit') }}</button>
                        <button type="button" wire:click="confirmDeleteField({{ $field->id }})" @if ($this->hasResponses()) disabled @endif class="text-red-600 hover:text-red-700 disabled:opacity-30">{{ __('Delete') }}</button>
                    </div>
                </div>
            @empty
                <p class="py-6 text-center text-sm text-slate-500">{{ __('No fields yet — add your first one above.') }}</p>
            @endforelse
        </div>
    </div>

    @if (! $isFeedbackForm)
        <!-- Payment -->
        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-sm font-semibold text-slate-900">{{ __('Payment') }}</h2>
            <p class="mt-0.5 text-xs text-slate-500">{{ __('Charge a registration fee via CHIP and/or bank transfer.') }}</p>

            @if (empty($availablePaymentMethods))
                <p class="mt-3 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-700 ring-1 ring-inset ring-amber-600/20">
                    {{ __('No payment method connected yet.') }}
                    <a href="{{ route('settings.payments') }}" wire:navigate class="font-semibold underline">{{ __('Connect CHIP or bank transfer in Payment Settings') }}</a>.
                </p>
            @else
                <form wire:submit="savePaymentSettings" class="mt-4 space-y-4">
                    <label class="flex items-center gap-2">
                        <input type="checkbox" wire:model.live="paymentEnabled" class="rounded border-slate-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                        <span class="text-sm font-medium text-slate-700">{{ __('Require payment to register') }}</span>
                    </label>

                    @if ($paymentEnabled)
                        <div>
                            <x-input-label :value="__('Payment Methods')" />
                            <div class="mt-1.5 space-y-1.5">
                                @foreach ($availablePaymentMethods as $method)
                                    <label class="flex items-center gap-2">
                                        <input type="checkbox" wire:model="paymentMethods" value="{{ $method }}" class="rounded border-slate-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                        <span class="text-sm text-slate-700">{{ $method === 'chip' ? __('CHIP') : __('Bank Transfer') }}</span>
                                    </label>
                                @endforeach
                            </div>
                            <x-input-error :messages="$errors->get('paymentMethods')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label :value="__('Pricing')" />
                            <div class="mt-1.5 space-y-1.5">
                                <label class="flex items-center gap-2">
                                    <input type="radio" wire:model.live="paymentPricingModel" value="flat" class="border-slate-300 text-indigo-600 focus:ring-indigo-500">
                                    <span class="text-sm text-slate-700">{{ __('Flat fee') }}</span>
                                </label>
                                <label class="flex items-center gap-2">
                                    <input type="radio" wire:model.live="paymentPricingModel" value="tiered" class="border-slate-300 text-indigo-600 focus:ring-indigo-500">
                                    <span class="text-sm text-slate-700">{{ __('Priced by a field (e.g. Ticket Type)') }}</span>
                                </label>
                            </div>
                        </div>

                        @if ($paymentPricingModel === 'flat')
                            <div class="max-w-xs">
                                <x-input-label for="paymentAmount" :value="__('Amount')" />
                                <x-text-input wire:model="paymentAmount" id="paymentAmount" type="number" step="0.01" min="0.01" class="mt-1 block w-full" />
                                <x-input-error :messages="$errors->get('paymentAmount')" class="mt-2" />
                            </div>
                        @else
                            <div>
                                <x-input-label for="paymentPricingFieldId" :value="__('Priced By')" />
                                @if ($pricingEligibleFields->isEmpty())
                                    <p class="mt-2 text-xs text-amber-600">{{ __('Add a Select or Radio field above with the price tiers as its options (e.g. VIP, Regular) before enabling tiered pricing.') }}</p>
                                @else
                                    <select wire:model.live="paymentPricingFieldId" id="paymentPricingFieldId" class="mt-1 block w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        <option value="">{{ __('— Select a field —') }}</option>
                                        @foreach ($pricingEligibleFields as $field)
                                            <option value="{{ $field->id }}">{{ $field->label }}</option>
                                        @endforeach
                                    </select>
                                @endif
                                <x-input-error :messages="$errors->get('paymentPricingFieldId')" class="mt-2" />

                                @if ($paymentPricingFieldId && ! empty($paymentOptionPrices))
                                    <div class="mt-3 space-y-2">
                                        @foreach ($paymentOptionPrices as $option => $price)
                                            <div class="flex items-center gap-2">
                                                <span class="w-40 shrink-0 truncate text-sm text-slate-700">{{ $option }}</span>
                                                <x-text-input wire:model="paymentOptionPrices.{{ $option }}" type="number" step="0.01" min="0" class="block w-32" placeholder="0.00" />
                                            </div>
                                            <x-input-error :messages="$errors->get('paymentOptionPrices.'.$option)" class="ml-40 -mt-1" />
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        @endif
                    @endif

                    <div class="flex justify-end">
                        <x-primary-button type="submit">{{ __('Save Payment Settings') }}</x-primary-button>
                    </div>
                </form>
            @endif
        </div>

        {{--
        Hidden per request (not deleted — the Feedback Survey/Finances/Report
        features themselves are untouched; they're just reached from the
        Events index row actions now instead of duplicating cards here).

        <!-- Feedback Survey -->
        <div id="feedback-survey" class="scroll-mt-6 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-sm font-semibold text-slate-900">{{ __('Feedback Survey') }}</h2>
            <p class="mt-0.5 text-xs text-slate-500">{{ __('A separate form attendees can fill in after the event — same field builder, its own responses and analysis.') }}</p>

            @if ($feedbackForm)
                <div class="mt-4 flex items-center justify-between gap-4 rounded-lg bg-slate-50 px-4 py-3">
                    <div>
                        @php
                            $feedbackStatusColors = [
                                'draft' => 'bg-slate-100 text-slate-600 ring-slate-500/10',
                                'published' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
                                'closed' => 'bg-amber-50 text-amber-700 ring-amber-600/20',
                            ];
                        @endphp
                        <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset {{ $feedbackStatusColors[$feedbackForm->status->value] }}">
                            {{ ucfirst($feedbackForm->status->value) }}
                        </span>
                    </div>
                    <div class="flex shrink-0 items-center gap-3 text-sm font-medium">
                        <a href="{{ route('event-forms.builder', $feedbackForm) }}" wire:navigate class="text-indigo-600 hover:text-indigo-700">{{ __('Edit') }}</a>
                        <a href="{{ route('event-forms.responses', $feedbackForm) }}" wire:navigate class="text-indigo-600 hover:text-indigo-700">{{ __('Responses') }}</a>
                        <button type="button" wire:click="confirmDeleteFeedbackForm" class="text-red-600 hover:text-red-700">{{ __('Delete') }}</button>
                    </div>
                </div>
            @else
                <div class="mt-4">
                    <x-secondary-button wire:click="addFeedbackForm">{{ __('+ Add Feedback Survey') }}</x-secondary-button>
                </div>
            @endif
        </div>

        @can('viewFinances', $event)
            <!-- Financial Ledger -->
            <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <h2 class="text-sm font-semibold text-slate-900">{{ __('Finances') }}</h2>
                        <p class="mt-0.5 text-xs text-slate-500">{{ __('Track income and expenses for this event.') }}</p>
                    </div>
                    <a href="{{ route('events.finances', $event) }}" wire:navigate class="shrink-0 text-sm font-medium text-indigo-600 hover:text-indigo-700">
                        {{ __('Open Ledger →') }}
                    </a>
                </div>
            </div>

            <!-- Event Report -->
            <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <h2 class="text-sm font-semibold text-slate-900">{{ __('Event Report') }}</h2>
                        <p class="mt-0.5 text-xs text-slate-500">{{ __('Registration, check-in, financial, and feedback numbers in one overview.') }}</p>
                    </div>
                    <a href="{{ route('events.report', $event) }}" wire:navigate class="shrink-0 text-sm font-medium text-indigo-600 hover:text-indigo-700">
                        {{ __('Open Report →') }}
                    </a>
                </div>
            </div>
        @endcan
        --}}

        <!-- Event Day Check-in -->
        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="text-sm font-semibold text-slate-900">{{ __('Event Day Check-in') }}</h2>
        <p class="mt-0.5 text-xs text-slate-500">{{ __('Let participants verify themselves against their existing registration on the day of the event.') }}</p>

        @if ($eventForm->checkin_enabled && $event->organization)
            <div
                x-data="{
                    copied: false,
                    url: @js($event->checkinUrl()),
                    async copy() {
                        try {
                            await navigator.clipboard.writeText(this.url);
                        } catch (e) {
                            this.$refs.checkinUrlInput.select();
                            document.execCommand('copy');
                        }
                        this.copied = true;
                        setTimeout(() => this.copied = false, 2000);
                    }
                }"
                class="mt-4 rounded-xl border border-indigo-100 bg-gradient-to-br from-indigo-50 to-violet-50 p-5 shadow-sm"
            >
                <h3 class="text-sm font-semibold text-slate-900">{{ __('Check-in link') }}</h3>

                <div class="mt-3 flex flex-col gap-2 sm:flex-row">
                    <input type="text" readonly x-ref="checkinUrlInput" value="{{ $event->checkinUrl() }}" @focus="$event.target.select()"
                        class="min-w-0 flex-1 rounded-lg border-slate-200 bg-white/80 font-mono text-sm text-slate-700 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <div class="flex gap-2">
                        <button type="button" @click="copy()" class="inline-flex items-center justify-center gap-1.5 whitespace-nowrap rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700">
                            <span x-text="copied ? '{{ __('Copied!') }}' : '{{ __('Copy') }}'"></span>
                        </button>
                        <a href="{{ $event->checkinUrl() }}" target="_blank" rel="noopener" class="inline-flex items-center justify-center gap-1.5 whitespace-nowrap rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50">
                            {{ __('Open') }}
                        </a>
                    </div>
                </div>

                @if ($eventForm->checkin_qr_enabled)
                    <div class="mt-4" x-data="qrCanvas(@js($event->checkinUrl().'?src=qr'))" wire:ignore>
                        <p class="mb-2 text-xs font-medium text-slate-600">{{ __('QR Code') }}</p>
                        <canvas x-ref="canvas" class="rounded-lg bg-white p-2 shadow-sm"></canvas>
                        <button type="button" @click="download()" class="mt-2 block text-xs font-semibold text-indigo-600 hover:text-indigo-700">
                            {{ __('Download PNG') }}
                        </button>
                    </div>
                @endif
            </div>
        @endif

        <form wire:submit="saveCheckinSettings" class="mt-4 space-y-4">
            <label class="flex items-center gap-2">
                <input type="checkbox" wire:model.live="checkinEnabled" class="rounded border-slate-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                <span class="text-sm font-medium text-slate-700">{{ __('Enable Event Day Check-in') }}</span>
            </label>

            @if ($checkinEnabled)
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <x-input-label for="checkinStartsAt" :value="__('Check-in Starts At (optional)')" />
                        <input wire:model="checkinStartsAt" id="checkinStartsAt" type="datetime-local" class="mt-1 block w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <x-input-error :messages="$errors->get('checkinStartsAt')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="checkinEndsAt" :value="__('Check-in Ends At (optional)')" />
                        <input wire:model="checkinEndsAt" id="checkinEndsAt" type="datetime-local" class="mt-1 block w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <x-input-error :messages="$errors->get('checkinEndsAt')" class="mt-2" />
                    </div>
                </div>

                <div class="space-y-2">
                    <label class="flex items-center gap-2">
                        <input type="checkbox" wire:model="checkinLinkEnabled" class="rounded border-slate-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                        <span class="text-sm text-slate-700">{{ __('Enable Shareable Check-in Link') }}</span>
                    </label>
                    <label class="flex items-center gap-2">
                        <input type="checkbox" wire:model="checkinQrEnabled" class="rounded border-slate-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                        <span class="text-sm text-slate-700">{{ __('Enable QR Code') }}</span>
                    </label>
                    <label class="flex items-center gap-2">
                        <input type="checkbox" wire:model="checkinManualEnabled" class="rounded border-slate-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                        <span class="text-sm text-slate-700">{{ __('Allow Admin/Staff Manual Check-in') }}</span>
                    </label>
                </div>

                <div>
                    <x-input-label :value="__('Verification Fields')" />
                    <p class="mt-0.5 text-xs text-slate-400">{{ __('Choose which fields participants must enter to verify themselves at check-in.') }}</p>

                    @if ($checkinEligibleFields->isEmpty())
                        <p class="mt-2 text-xs text-amber-600">{{ __('Add at least one field above (other than Checkbox) before enabling check-in.') }}</p>
                    @else
                        <div class="mt-2 space-y-1.5">
                            @foreach ($checkinEligibleFields as $field)
                                <label class="flex items-center gap-2">
                                    <input type="checkbox" wire:model="checkinVerificationFieldIds" value="{{ $field->id }}" class="rounded border-slate-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                    <span class="text-sm text-slate-700">{{ $field->label }}</span>
                                </label>
                            @endforeach
                        </div>
                    @endif
                    <x-input-error :messages="$errors->get('checkinVerificationFieldIds')" class="mt-2" />
                </div>

                <div>
                    <x-input-label :value="__('Verification Mode')" />
                    <div class="mt-1.5 space-y-1.5">
                        @foreach ($checkinModes as $mode)
                            <label class="flex items-center gap-2">
                                <input type="radio" wire:model="checkinVerificationMode" value="{{ $mode->value }}" class="border-slate-300 text-indigo-600 focus:ring-indigo-500">
                                <span class="text-sm text-slate-700">
                                    {{ $mode === \App\Enums\CheckInVerificationMode::All ? __('Match ALL selected fields') : __('Match ANY selected field') }}
                                </span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <label class="flex items-center gap-2">
                    <input type="checkbox" wire:model="checkinOnsiteRegistrationEnabled" class="rounded border-slate-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                    <span class="text-sm text-slate-700">{{ __('Allow New Registration During Event Day') }}</span>
                </label>
                <p class="text-xs text-slate-400">{{ __('If a participant is not found, they can fill in this same registration form on the spot and be checked in immediately after.') }}</p>
            @endif

            <div class="flex justify-end">
                <x-primary-button type="submit">{{ __('Save Check-in Settings') }}</x-primary-button>
            </div>
        </form>
        </div>
    @endif

    <!-- Field Modal -->
    @if ($showFieldModal)
        <div class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0">
            <div class="fixed inset-0 bg-slate-900/50" wire:click="closeFieldModal"></div>

            <div class="relative mx-auto mb-6 transform overflow-hidden rounded-2xl bg-white shadow-xl transition-all sm:w-full sm:max-w-md">
                <form wire:submit="saveField" class="p-6 sm:p-8">
                    <h2 class="text-lg font-semibold text-slate-900">
                        {{ $editingFieldId ? __('Edit Field') : __('Add Field') }}
                    </h2>

                    <div class="mt-4">
                        <x-input-label for="fieldLabel" :value="__('Label')" />
                        <x-text-input wire:model="fieldLabel" id="fieldLabel" type="text" class="mt-1 block w-full" autofocus />
                        <x-input-error :messages="$errors->get('fieldLabel')" class="mt-2" />
                    </div>

                    <div class="mt-4">
                        <x-input-label for="fieldType" :value="__('Type')" />
                        <select wire:model.live="fieldType" id="fieldType" @if ($editingFieldId && $this->hasResponses()) disabled @endif class="mt-1 block w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-slate-50">
                            @foreach ($fieldTypes as $type)
                                <option value="{{ $type->value }}">{{ ucfirst($type->value) }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('fieldType')" class="mt-2" />
                    </div>

                    @if (in_array($fieldType, ['select', 'radio', 'checkbox']))
                        <div class="mt-4">
                            <x-input-label for="fieldOptions" :value="__('Options (one per line)')" />
                            <textarea wire:model="fieldOptions" id="fieldOptions" rows="4" @if ($editingFieldId && $this->hasResponses()) disabled @endif
                                class="mt-1 block w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-slate-50"></textarea>
                            <x-input-error :messages="$errors->get('fieldOptions')" class="mt-2" />
                        </div>
                    @endif

                    <div class="mt-4">
                        <x-input-label for="fieldHelpText" :value="__('Help text (optional)')" />
                        <x-text-input wire:model="fieldHelpText" id="fieldHelpText" type="text" class="mt-1 block w-full" />
                    </div>

                    <label class="mt-4 flex items-center gap-2">
                        <input type="checkbox" wire:model="fieldRequired" class="rounded border-slate-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                        <span class="text-sm text-slate-700">{{ __('Required') }}</span>
                    </label>

                    <div class="mt-6 flex justify-end gap-3">
                        <x-secondary-button type="button" wire:click="closeFieldModal">{{ __('Cancel') }}</x-secondary-button>
                        <x-primary-button type="submit">{{ __('Save') }}</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- Delete Field Confirmation Modal -->
    @if ($confirmingDeleteFieldId !== null)
        <div class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0">
            <div class="fixed inset-0 bg-slate-900/50" wire:click="closeDeleteFieldModal"></div>

            <div class="relative mx-auto mb-6 transform overflow-hidden rounded-2xl bg-white shadow-xl transition-all sm:w-full sm:max-w-md">
                <div class="p-6 sm:p-8">
                    <h2 class="text-lg font-semibold text-slate-900">{{ __('Delete Field') }}</h2>
                    <p class="mt-2 text-sm text-slate-500">{{ __('Are you sure you want to delete this field?') }}</p>
                    <div class="mt-6 flex justify-end gap-3">
                        <x-secondary-button type="button" wire:click="closeDeleteFieldModal">{{ __('Cancel') }}</x-secondary-button>
                        <x-danger-button wire:click="deleteField">{{ __('Delete') }}</x-danger-button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Delete Feedback Survey Confirmation Modal -->
    @if ($confirmingDeleteFeedbackForm)
        <div class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0">
            <div class="fixed inset-0 bg-slate-900/50" wire:click="closeDeleteFeedbackModal"></div>

            <div class="relative mx-auto mb-6 transform overflow-hidden rounded-2xl bg-white shadow-xl transition-all sm:w-full sm:max-w-md">
                <div class="p-6 sm:p-8">
                    <h2 class="text-lg font-semibold text-slate-900">{{ __('Delete Feedback Survey') }}</h2>
                    <p class="mt-2 text-sm text-slate-500">{{ __('Are you sure you want to delete the feedback survey? This will also permanently delete all of its responses.') }}</p>
                    <div class="mt-6 flex justify-end gap-3">
                        <x-secondary-button type="button" wire:click="closeDeleteFeedbackModal">{{ __('Cancel') }}</x-secondary-button>
                        <x-danger-button wire:click="deleteFeedbackForm">{{ __('Delete') }}</x-danger-button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
