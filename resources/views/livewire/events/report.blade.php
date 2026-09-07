<div class="space-y-8">
    <div class="flex justify-end gap-2">
        <a href="{{ route('events.report.print', $event) }}" target="_blank" rel="noopener" class="inline-flex shrink-0 items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-4 w-4">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0 1 10.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0 .229 2.523a1.125 1.125 0 0 1-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0 0 21 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 0 0-1.913-.247M6.34 18H5.25A2.25 2.25 0 0 1 3 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 0 1 1.913-.247m10.5 0a48.536 48.536 0 0 0-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5Zm-3 0h.008v.008H15V10.5Z" />
            </svg>
            {{ __('Print / Save as PDF') }}
        </a>
        <button type="button" wire:click="export" class="inline-flex shrink-0 items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-4 w-4">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
            </svg>
            {{ __('Export CSV') }}
        </button>
    </div>

    <!-- Program Details -->
    <div>
        <h2 class="mb-3 text-sm font-semibold text-slate-900">{{ __('Program Details') }}</h2>

        @can('update', $event)
            <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                <form wire:submit="saveReportDetails" class="space-y-6">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <div>
                            <x-input-label for="eventDate" :value="__('Date')" />
                            <input wire:model="eventDate" id="eventDate" type="date" class="mt-1 block w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <x-input-error :messages="$errors->get('eventDate')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="eventTime" :value="__('Time')" />
                            <x-text-input wire:model="eventTime" id="eventTime" type="text" class="mt-1 block w-full" placeholder="{{ __('e.g. 9:00 AM - 1:00 PM') }}" />
                            <x-input-error :messages="$errors->get('eventTime')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="theme" :value="__('Theme')" />
                            <x-text-input wire:model="theme" id="theme" type="text" class="mt-1 block w-full" />
                            <x-input-error :messages="$errors->get('theme')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="venue" :value="__('Venue')" />
                            <x-text-input wire:model="venue" id="venue" type="text" class="mt-1 block w-full" />
                            <x-input-error :messages="$errors->get('venue')" class="mt-2" />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <x-input-label for="objectives" :value="__('Objectives')" />
                            <textarea wire:model="objectives" id="objectives" rows="4" class="mt-1 block w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                            <x-input-error :messages="$errors->get('objectives')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="problems" :value="__('Problems')" />
                            <textarea wire:model="problems" id="problems" rows="4" class="mt-1 block w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                            <x-input-error :messages="$errors->get('problems')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="achievements" :value="__('Achievements')" />
                            <textarea wire:model="achievements" id="achievements" rows="4" class="mt-1 block w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                            <x-input-error :messages="$errors->get('achievements')" class="mt-2" />
                        </div>
                        <div>
                            @php $directorsRemarksLabel = __("Director's Remarks"); @endphp
                            <x-input-label for="directorsRemarks" :value="$directorsRemarksLabel" />
                            <textarea wire:model="directorsRemarks" id="directorsRemarks" rows="4" class="mt-1 block w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                            <x-input-error :messages="$errors->get('directorsRemarks')" class="mt-2" />
                        </div>
                    </div>

                    <div>
                        <div class="flex items-center justify-between">
                            <x-input-label :value="__('Itinerary')" />
                            <x-secondary-button type="button" wire:click="addItineraryRow">{{ __('+ Add Row') }}</x-secondary-button>
                        </div>
                        <div class="mt-2 space-y-2">
                            @foreach ($itineraryRows as $index => $row)
                                <div wire:key="itinerary-row-{{ $index }}" class="flex items-start gap-2">
                                    <input wire:model="itineraryRows.{{ $index }}.time" type="text" placeholder="{{ __('Time') }}" class="w-32 rounded-lg border-slate-200 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <input wire:model="itineraryRows.{{ $index }}.activity" type="text" placeholder="{{ __('Activity') }}" class="min-w-0 flex-1 rounded-lg border-slate-200 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <button type="button" wire:click="removeItineraryRow({{ $index }})" class="shrink-0 px-2 py-2 text-slate-400 hover:text-red-600">&times;</button>
                                </div>
                            @endforeach
                        </div>
                        <x-input-error :messages="$errors->get('itineraryRows.*.time')" class="mt-2" />
                        <x-input-error :messages="$errors->get('itineraryRows.*.activity')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label :value="__('Sign-off')" />
                        <div class="mt-2 grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <div class="space-y-2">
                                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Prepared By') }}</p>
                                <x-text-input wire:model="preparedByName" type="text" class="block w-full" placeholder="{{ __('Name') }}" />
                                <x-text-input wire:model="preparedByPosition" type="text" class="block w-full" placeholder="{{ __('Position') }}" />
                                <input wire:model="preparedByDate" type="date" class="block w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">

                                <p class="pt-1 text-xs text-slate-500">{{ __('Signature (optional)') }}</p>
                                @if ($preparedBySignature)
                                    <img src="{{ $preparedBySignature->temporaryUrl() }}" alt="{{ __('Signature preview') }}" class="h-16 max-w-[10rem] rounded border border-slate-200 bg-white object-contain p-1">
                                @elseif ($existingPreparedBySignaturePath && ! $removePreparedBySignature)
                                    <img src="{{ \Illuminate\Support\Facades\Storage::url($existingPreparedBySignaturePath) }}" alt="{{ __('Current signature') }}" class="h-16 max-w-[10rem] rounded border border-slate-200 bg-white object-contain p-1">
                                @endif
                                <input wire:model="preparedBySignature" type="file" accept="image/*" class="block w-full text-xs text-slate-500 file:mr-2 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-2 file:py-1 file:text-xs file:font-medium file:text-indigo-700 hover:file:bg-indigo-100">
                                <div wire:loading wire:target="preparedBySignature" class="text-xs text-slate-500">{{ __('Uploading...') }}</div>
                                <x-input-error :messages="$errors->get('preparedBySignature')" class="mt-1" />
                                @if ($existingPreparedBySignaturePath && ! $preparedBySignature)
                                    <label class="flex items-center gap-1.5 text-xs text-slate-600">
                                        <input type="checkbox" wire:model="removePreparedBySignature" class="rounded border-slate-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                        {{ __('Remove current signature') }}
                                    </label>
                                @endif
                            </div>
                            <div class="space-y-2">
                                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Reviewed By') }}</p>
                                <x-text-input wire:model="reviewedByName" type="text" class="block w-full" placeholder="{{ __('Name') }}" />
                                <x-text-input wire:model="reviewedByPosition" type="text" class="block w-full" placeholder="{{ __('Position') }}" />
                                <input wire:model="reviewedByDate" type="date" class="block w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">

                                <p class="pt-1 text-xs text-slate-500">{{ __('Signature (optional)') }}</p>
                                @if ($reviewedBySignature)
                                    <img src="{{ $reviewedBySignature->temporaryUrl() }}" alt="{{ __('Signature preview') }}" class="h-16 max-w-[10rem] rounded border border-slate-200 bg-white object-contain p-1">
                                @elseif ($existingReviewedBySignaturePath && ! $removeReviewedBySignature)
                                    <img src="{{ \Illuminate\Support\Facades\Storage::url($existingReviewedBySignaturePath) }}" alt="{{ __('Current signature') }}" class="h-16 max-w-[10rem] rounded border border-slate-200 bg-white object-contain p-1">
                                @endif
                                <input wire:model="reviewedBySignature" type="file" accept="image/*" class="block w-full text-xs text-slate-500 file:mr-2 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-2 file:py-1 file:text-xs file:font-medium file:text-indigo-700 hover:file:bg-indigo-100">
                                <div wire:loading wire:target="reviewedBySignature" class="text-xs text-slate-500">{{ __('Uploading...') }}</div>
                                <x-input-error :messages="$errors->get('reviewedBySignature')" class="mt-1" />
                                @if ($existingReviewedBySignaturePath && ! $reviewedBySignature)
                                    <label class="flex items-center gap-1.5 text-xs text-slate-600">
                                        <input type="checkbox" wire:model="removeReviewedBySignature" class="rounded border-slate-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                        {{ __('Remove current signature') }}
                                    </label>
                                @endif
                            </div>
                            <div class="space-y-2">
                                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Approved By') }}</p>
                                <x-text-input wire:model="approvedByName" type="text" class="block w-full" placeholder="{{ __('Name') }}" />
                                <x-text-input wire:model="approvedByPosition" type="text" class="block w-full" placeholder="{{ __('Position') }}" />
                                <input wire:model="approvedByDate" type="date" class="block w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">

                                <p class="pt-1 text-xs text-slate-500">{{ __('Signature (optional)') }}</p>
                                @if ($approvedBySignature)
                                    <img src="{{ $approvedBySignature->temporaryUrl() }}" alt="{{ __('Signature preview') }}" class="h-16 max-w-[10rem] rounded border border-slate-200 bg-white object-contain p-1">
                                @elseif ($existingApprovedBySignaturePath && ! $removeApprovedBySignature)
                                    <img src="{{ \Illuminate\Support\Facades\Storage::url($existingApprovedBySignaturePath) }}" alt="{{ __('Current signature') }}" class="h-16 max-w-[10rem] rounded border border-slate-200 bg-white object-contain p-1">
                                @endif
                                <input wire:model="approvedBySignature" type="file" accept="image/*" class="block w-full text-xs text-slate-500 file:mr-2 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-2 file:py-1 file:text-xs file:font-medium file:text-indigo-700 hover:file:bg-indigo-100">
                                <div wire:loading wire:target="approvedBySignature" class="text-xs text-slate-500">{{ __('Uploading...') }}</div>
                                <x-input-error :messages="$errors->get('approvedBySignature')" class="mt-1" />
                                @if ($existingApprovedBySignaturePath && ! $approvedBySignature)
                                    <label class="flex items-center gap-1.5 text-xs text-slate-600">
                                        <input type="checkbox" wire:model="removeApprovedBySignature" class="rounded border-slate-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                        {{ __('Remove current signature') }}
                                    </label>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <x-primary-button type="submit">{{ __('Save Program Details') }}</x-primary-button>
                    </div>
                </form>
            </div>
        @else
            @if ($reportDetail)
                <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div><dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('Date') }}</dt><dd class="mt-1 text-sm text-slate-700">{{ $reportDetail->event_date?->format('d M Y') ?? '—' }}</dd></div>
                        <div><dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('Time') }}</dt><dd class="mt-1 text-sm text-slate-700">{{ $reportDetail->event_time ?: '—' }}</dd></div>
                        <div><dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('Theme') }}</dt><dd class="mt-1 text-sm text-slate-700">{{ $reportDetail->theme ?: '—' }}</dd></div>
                        <div><dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('Venue') }}</dt><dd class="mt-1 text-sm text-slate-700">{{ $reportDetail->venue ?: '—' }}</dd></div>
                    </dl>
                </div>
            @else
                <p class="rounded-xl border border-slate-200 bg-white p-4 text-sm text-slate-500 shadow-sm">{{ __('No program details added yet.') }}</p>
            @endif
        @endcan
    </div>

    <!-- Registration & Check-in -->
    <div>
        <h2 class="mb-3 text-sm font-semibold text-slate-900">{{ __('Registration & Check-in') }}</h2>

        @if ($registrationForm)
            <div class="grid grid-cols-2 gap-4 {{ $registrationForm->checkin_enabled ? 'lg:grid-cols-4' : '' }}">
                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Total Registered') }}</p>
                    <p class="mt-1 text-2xl font-bold text-slate-900">{{ $totalRegistered }}</p>
                </div>
                @if ($registrationForm->checkin_enabled)
                    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Checked In') }}</p>
                        <p class="mt-1 text-2xl font-bold text-emerald-600">{{ $checkedInCount }}</p>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Not Checked In') }}</p>
                        <p class="mt-1 text-2xl font-bold text-slate-900">{{ $notCheckedInCount }}</p>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('On-site') }}</p>
                        <p class="mt-1 text-2xl font-bold text-slate-900">{{ $onsiteCount }}</p>
                    </div>
                @endif
            </div>
            <a href="{{ route('event-forms.responses', $registrationForm) }}" wire:navigate class="mt-3 inline-block text-sm font-medium text-indigo-600 hover:text-indigo-700">
                {{ __('View all responses →') }}
            </a>
        @else
            <p class="rounded-xl border border-slate-200 bg-white p-4 text-sm text-slate-500 shadow-sm">{{ __('No registration form created yet.') }}</p>
        @endif
    </div>

    <!-- Financial Summary -->
    <div>
        <h2 class="mb-3 text-sm font-semibold text-slate-900">{{ __('Financial Summary') }}</h2>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Total Income') }}</p>
                <p class="mt-1 text-2xl font-bold text-emerald-600">{{ number_format($totalIncome, 2) }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Total Expenses') }}</p>
                <p class="mt-1 text-2xl font-bold text-red-600">{{ number_format($totalExpenses, 2) }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Net Balance') }}</p>
                <p class="mt-1 text-2xl font-bold {{ $netBalance >= 0 ? 'text-slate-900' : 'text-red-600' }}">{{ number_format($netBalance, 2) }}</p>
            </div>
        </div>

        @if ($incomeByCategory->isNotEmpty() || $expensesByCategory->isNotEmpty())
            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                @if ($incomeByCategory->isNotEmpty())
                    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <h3 class="text-sm font-semibold text-slate-900">{{ __('Income by Category') }}</h3>
                        <dl class="mt-3 divide-y divide-slate-100">
                            @foreach ($incomeByCategory as $category => $amount)
                                <div class="flex items-center justify-between py-2 text-sm">
                                    <dt class="text-slate-600">{{ $category }}</dt>
                                    <dd class="font-medium text-emerald-600">{{ number_format($amount, 2) }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    </div>
                @endif

                @if ($expensesByCategory->isNotEmpty())
                    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <h3 class="text-sm font-semibold text-slate-900">{{ __('Expenses by Category') }}</h3>
                        <dl class="mt-3 divide-y divide-slate-100">
                            @foreach ($expensesByCategory as $category => $amount)
                                <div class="flex items-center justify-between py-2 text-sm">
                                    <dt class="text-slate-600">{{ $category }}</dt>
                                    <dd class="font-medium text-red-600">{{ number_format($amount, 2) }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    </div>
                @endif
            </div>
        @endif

        <a href="{{ route('events.finances', $event) }}" wire:navigate class="mt-3 inline-block text-sm font-medium text-indigo-600 hover:text-indigo-700">
            {{ __('Open Ledger →') }}
        </a>
    </div>

    <!-- Feedback Summary -->
    <div>
        <h2 class="mb-3 text-sm font-semibold text-slate-900">{{ __('Feedback Summary') }}</h2>

        @if ($feedbackForm)
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Total Responses') }}</p>
                <p class="mt-1 text-2xl font-bold text-slate-900">{{ $feedbackResponseCount }}</p>
            </div>

            @if ($charts->isNotEmpty())
                <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($charts as $chart)
                        <x-charts.card :title="$chart['field']->label" :type="$chart['chartType']" :labels="$chart['labels']" :data="$chart['data']" />
                    @endforeach
                </div>
            @endif

            @if ($comments->isNotEmpty())
                <div class="mt-4 space-y-4">
                    @foreach ($comments as $entry)
                        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                            <h3 class="text-sm font-semibold text-slate-900">{{ $entry['field']->label }}</h3>
                            <p class="mt-0.5 text-xs text-slate-400">{{ __('Most recent :count comments', ['count' => $entry['comments']->count()]) }}</p>
                            <ul class="mt-3 space-y-2">
                                @foreach ($entry['comments'] as $comment)
                                    <li class="rounded-lg bg-slate-50 px-3 py-2 text-sm text-slate-700">{{ $comment }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endforeach
                </div>
            @endif

            <a href="{{ route('event-forms.responses', $feedbackForm) }}" wire:navigate class="mt-3 inline-block text-sm font-medium text-indigo-600 hover:text-indigo-700">
                {{ __('View all feedback responses →') }}
            </a>
        @else
            <p class="rounded-xl border border-slate-200 bg-white p-4 text-sm text-slate-500 shadow-sm">{{ __('No feedback survey created yet.') }}</p>
        @endif
    </div>
</div>
