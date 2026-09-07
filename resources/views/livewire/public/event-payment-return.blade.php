<div>
    @if ($outcome === 'paid')
        <div class="rounded-2xl border border-emerald-100 bg-emerald-50 p-8 text-center">
            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-emerald-100 text-emerald-600">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                </svg>
            </div>
            <h2 class="mt-4 text-xl font-bold text-slate-900">{{ __('Payment received') }}</h2>
            <p class="mt-2 text-sm text-slate-600 max-w-md mx-auto">
                {{ __('Thanks — your payment is confirmed and your registration is complete.') }}
            </p>
            <a href="{{ url('/') }}" class="mt-6 inline-flex items-center gap-2 rounded-lg bg-gradient-to-r from-indigo-600 to-violet-600 px-6 py-2.5 text-sm font-semibold text-white shadow-lg shadow-indigo-200 transition hover:shadow-indigo-300 hover:-translate-y-0.5">
                {{ __('Back to home') }}
            </a>
        </div>
    @elseif ($outcome === 'failed')
        <div class="rounded-2xl border border-red-100 bg-red-50 p-8 text-center">
            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-red-100 text-red-600">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </div>
            <h2 class="mt-4 text-xl font-bold text-slate-900">{{ __('Payment not completed') }}</h2>
            <p class="mt-2 text-sm text-slate-600 max-w-md mx-auto">
                {{ __('Your payment was cancelled or did not go through. Your registration details are saved — please contact the organizer to complete payment.') }}
            </p>
            <a href="{{ url('/') }}" class="mt-6 inline-flex items-center gap-2 rounded-lg bg-gradient-to-r from-indigo-600 to-violet-600 px-6 py-2.5 text-sm font-semibold text-white shadow-lg shadow-indigo-200 transition hover:shadow-indigo-300 hover:-translate-y-0.5">
                {{ __('Back to home') }}
            </a>
        </div>
    @elseif ($outcome === 'not_found')
        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-8 text-center">
            <h2 class="text-xl font-bold text-slate-900">{{ __('Payment not found') }}</h2>
            <p class="mt-2 text-sm text-slate-600">{{ __("We couldn't find a payment matching this link.") }}</p>
        </div>
    @else
        <div class="rounded-2xl border border-amber-100 bg-amber-50 p-8 text-center">
            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-amber-100 text-amber-600">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                </svg>
            </div>
            <h2 class="mt-4 text-xl font-bold text-slate-900">{{ __('Payment pending confirmation') }}</h2>
            <p class="mt-2 text-sm text-slate-600 max-w-md mx-auto">
                {{ __("We're still confirming your payment — this can take a moment. If this doesn't update shortly, please contact the organizer.") }}
            </p>
            <a href="{{ url('/') }}" class="mt-6 inline-flex items-center gap-2 rounded-lg bg-gradient-to-r from-indigo-600 to-violet-600 px-6 py-2.5 text-sm font-semibold text-white shadow-lg shadow-indigo-200 transition hover:shadow-indigo-300 hover:-translate-y-0.5">
                {{ __('Back to home') }}
            </a>
        </div>
    @endif
</div>
