<div>
    @php $isFeedback = $eventForm->type->value === 'feedback'; @endphp

    @if ($preview)
        <div class="mb-4 flex items-center gap-2 rounded-lg bg-amber-50 px-4 py-3 text-sm font-medium text-amber-700 ring-1 ring-inset ring-amber-600/20">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-4 w-4 shrink-0">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
            </svg>
            {{ __("Preview mode — you're viewing this as the form owner. Submissions here are not recorded.") }}
        </div>
    @endif

    @if ($step === 'done')
        <div class="rounded-2xl border border-emerald-100 bg-emerald-50 p-8 text-center">
            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-emerald-100 text-emerald-600">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                </svg>
            </div>
            @if ($preview)
                <h2 class="mt-4 text-xl font-bold text-slate-900">{{ __('Looks good') }}</h2>
                <p class="mt-2 text-sm text-slate-600 max-w-md mx-auto">
                    {{ __('Validation passed — this is what a successful submission would show. No response was recorded.') }}
                </p>
            @elseif ($isFeedback)
                <h2 class="mt-4 text-xl font-bold text-slate-900">{{ __('Feedback submitted') }}</h2>
                <p class="mt-2 text-sm text-slate-600 max-w-md mx-auto">
                    {{ __("Thanks for your feedback — we've recorded your response.") }}
                </p>
            @elseif ($responseId)
                <h2 class="mt-4 text-xl font-bold text-slate-900">{{ __('Payment submitted') }}</h2>
                <p class="mt-2 text-sm text-slate-600 max-w-md mx-auto">
                    {{ __("Thanks — we've recorded your payment and it's pending review. You'll be confirmed once it's approved.") }}
                </p>
            @else
                <h2 class="mt-4 text-xl font-bold text-slate-900">{{ __('Registration submitted') }}</h2>
                <p class="mt-2 text-sm text-slate-600 max-w-md mx-auto">
                    {{ __("Thanks for registering — we've recorded your response.") }}
                </p>
            @endif
            <a href="{{ url('/') }}" class="mt-6 inline-flex items-center gap-2 rounded-lg bg-gradient-to-r from-indigo-600 to-violet-600 px-6 py-2.5 text-sm font-semibold text-white shadow-lg shadow-indigo-200 transition hover:shadow-indigo-300 hover:-translate-y-0.5">
                {{ __('Back to home') }}
            </a>
        </div>
    @elseif ($step === 'payment')
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
            <h2 class="text-lg font-semibold text-slate-900">{{ __('Complete Your Payment') }}</h2>
            <p class="mt-1 text-sm text-slate-500">
                {{ __('Your registration is saved. Complete payment below to confirm your spot.') }}
            </p>

            <div class="mt-4 rounded-lg bg-slate-50 px-4 py-3">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Amount Due') }}</p>
                <p class="mt-1 text-2xl font-bold text-slate-900">{{ __('MYR') }} {{ number_format($paymentAmountDue, 2) }}</p>
            </div>

            @if ($paymentSubmitError)
                <div class="mt-4 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700 ring-1 ring-inset ring-red-600/20">
                    {{ $paymentSubmitError }}
                </div>
            @endif

            <div class="mt-6 space-y-6">
                @if (in_array('chip', $availablePaymentMethods))
                    <div class="rounded-xl border border-slate-200 p-5">
                        <h3 class="text-sm font-semibold text-slate-900">{{ __('Pay Online') }}</h3>
                        <p class="mt-1 text-xs text-slate-500">{{ __('Pay securely via card or e-wallet.') }}</p>
                        <button type="button" wire:click="payWithChip" wire:loading.attr="disabled" wire:target="payWithChip"
                            class="mt-3 w-full rounded-lg bg-gradient-to-r from-indigo-600 to-violet-600 px-6 py-2.5 text-sm font-semibold text-white shadow-lg shadow-indigo-200 disabled:opacity-60">
                            <span wire:loading.remove wire:target="payWithChip">{{ __('Pay Now via CHIP') }}</span>
                            <span wire:loading wire:target="payWithChip">{{ __('Redirecting...') }}</span>
                        </button>
                    </div>
                @endif

                @if (in_array('bank_transfer', $availablePaymentMethods))
                    <div class="rounded-xl border border-slate-200 p-5">
                        <h3 class="text-sm font-semibold text-slate-900">{{ __('Bank Transfer') }}</h3>
                        <p class="mt-1 text-xs text-slate-500">{{ __('Transfer the amount above, then upload your receipt below.') }}</p>

                        <dl class="mt-3 space-y-1 text-sm">
                            <div class="flex justify-between"><dt class="text-slate-500">{{ __('Bank') }}</dt><dd class="font-medium text-slate-900">{{ $bankName }}</dd></div>
                            <div class="flex justify-between"><dt class="text-slate-500">{{ __('Account No.') }}</dt><dd class="font-medium text-slate-900">{{ $bankAccountNumber }}</dd></div>
                            <div class="flex justify-between"><dt class="text-slate-500">{{ __('Account Holder') }}</dt><dd class="font-medium text-slate-900">{{ $bankAccountHolder }}</dd></div>
                        </dl>

                        <form wire:submit="submitBankTransfer" class="mt-4">
                            <x-input-label for="paymentReceipt" :value="__('Upload Receipt')" />
                            <input wire:model="paymentReceipt" id="paymentReceipt" type="file" accept="image/*" class="mt-1 block w-full text-sm text-slate-500 file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-indigo-700 hover:file:bg-indigo-100">
                            <div wire:loading wire:target="paymentReceipt" class="mt-1 text-xs text-slate-500">{{ __('Uploading...') }}</div>
                            <x-input-error :messages="$errors->get('paymentReceipt')" class="mt-2" />

                            <button type="submit" class="mt-3 w-full rounded-lg border border-slate-200 bg-white px-6 py-2.5 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
                                {{ __('Submit Payment Proof') }}
                            </button>
                        </form>
                    </div>
                @endif
            </div>
        </div>
    @elseif (! $preview && ! $eventForm->isAcceptingResponses())
        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-8 text-center">
            <h2 class="text-xl font-bold text-slate-900">{{ $isFeedback ? __('Feedback closed') : __('Registration closed') }}</h2>
            <p class="mt-2 text-sm text-slate-600">{{ __('This form is no longer accepting responses.') }}</p>
        </div>
    @else
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
            <h2 class="text-lg font-semibold text-slate-900">{{ $event->title }}</h2>
            @if ($event->description)
                <p class="mt-1 text-sm text-slate-500">{{ $event->description }}</p>
            @endif

            <form wire:submit="submit" class="mt-6 space-y-5">
                @foreach ($eventForm->fields as $field)
                    <div>
                        <label class="block text-sm font-medium text-slate-700">
                            {{ $field->label }}
                            @if ($field->required)
                                <span class="text-red-500">*</span>
                            @endif
                        </label>

                        @if ($field->help_text)
                            <p class="mt-0.5 text-xs text-slate-400">{{ $field->help_text }}</p>
                        @endif

                        <div class="mt-1.5">
                            <x-event-forms.field-input :field="$field" :wire-model="'answers.'.$field->id" />
                        </div>

                        <x-input-error :messages="$errors->get('answers.'.$field->id)" class="mt-1.5" />
                    </div>
                @endforeach

                <div class="pt-2">
                    <button type="submit" class="w-full rounded-lg bg-gradient-to-r from-indigo-600 to-violet-600 px-6 py-3 text-sm font-semibold text-white shadow-lg shadow-indigo-200 transition hover:shadow-indigo-300">
                        {{ $preview ? __('Test Submit') : __('Submit') }}
                    </button>
                </div>
            </form>
        </div>
    @endif
</div>
