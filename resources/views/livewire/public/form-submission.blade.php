<div>
    @if ($preview)
        <div class="mb-4 flex items-center gap-2 rounded-lg bg-amber-50 px-4 py-3 text-sm font-medium text-amber-700 ring-1 ring-inset ring-amber-600/20">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-4 w-4 shrink-0">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
            </svg>
            {{ __("Preview mode — you're viewing this as the form owner. Submissions here are not recorded.") }}
        </div>
    @endif

    @if ($submitted)
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
            @else
                <h2 class="mt-4 text-xl font-bold text-slate-900">{{ __('Response submitted') }}</h2>
                <p class="mt-2 text-sm text-slate-600 max-w-md mx-auto">
                    {{ __("Thanks — we've recorded your response.") }}
                </p>
            @endif
            <a href="{{ url('/') }}" class="mt-6 inline-flex items-center gap-2 rounded-lg bg-gradient-to-r from-indigo-600 to-violet-600 px-6 py-2.5 text-sm font-semibold text-white shadow-lg shadow-indigo-200 transition hover:shadow-indigo-300 hover:-translate-y-0.5">
                {{ __('Back to home') }}
            </a>
        </div>
    @elseif (! $preview && ! $form->isAcceptingResponses())
        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-8 text-center">
            <h2 class="text-xl font-bold text-slate-900">{{ __('Form closed') }}</h2>
            <p class="mt-2 text-sm text-slate-600">{{ __('This form is no longer accepting responses.') }}</p>
        </div>
    @else
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
            <h2 class="text-lg font-semibold text-slate-900">{{ $form->title }}</h2>
            @if ($form->description)
                <p class="mt-1 text-sm text-slate-500">{{ $form->description }}</p>
            @endif

            <form wire:submit="submit" class="mt-6 space-y-5">
                @foreach ($form->fields as $field)
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
                            <x-forms.field-input :field="$field" :wire-model="'answers.'.$field->id" />
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
