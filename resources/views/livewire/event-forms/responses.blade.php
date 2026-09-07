<div class="space-y-6">
    <!-- Stats + Export -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="grid grid-cols-3 gap-4 {{ $eventForm->checkin_enabled ? 'lg:grid-cols-6' : '' }}">
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Total') }}</p>
                <p class="mt-1 text-2xl font-bold text-slate-900">{{ $totalCount }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Today') }}</p>
                <p class="mt-1 text-2xl font-bold text-slate-900">{{ $todayCount }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('This Week') }}</p>
                <p class="mt-1 text-2xl font-bold text-slate-900">{{ $weekCount }}</p>
            </div>
            @if ($eventForm->checkin_enabled)
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

        @can('viewResponses', $eventForm)
            <button type="button" wire:click="export" class="inline-flex shrink-0 items-center gap-2 self-start rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-4 w-4">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                </svg>
                {{ __('Export CSV') }}
            </button>
        @endcan
    </div>

    <div class="w-full sm:w-72">
        <x-text-input wire:model.live.debounce.300ms="search" type="text" class="block w-full" placeholder="{{ __('Search by reference or answer...') }}" />
    </div>

    @can('viewResponses', $eventForm)
        <!-- Analysis -->
        @if ($charts->isNotEmpty())
            <div>
                <h2 class="mb-3 text-sm font-semibold text-slate-900">{{ __('Analysis') }}</h2>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($charts as $chart)
                        <x-charts.card :title="$chart['field']->label" :type="$chart['chartType']" :labels="$chart['labels']" :data="$chart['data']" />
                    @endforeach
                </div>
            </div>
        @endif
    @endcan

    <!-- Table -->
    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Reference') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Submitted') }}</th>
                        @can('viewResponses', $eventForm)
                            @foreach ($previewFields as $field)
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ $field->label }}</th>
                            @endforeach
                        @endcan
                        @if ($eventForm->checkin_enabled)
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Check-in') }}</th>
                        @endif
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    @forelse ($responses as $response)
                        <tr wire:key="response-{{ $response->id }}" class="hover:bg-slate-50">
                            <td class="whitespace-nowrap px-4 py-3 text-sm font-mono text-slate-500">{{ $response->reference ?: __('Response #:id', ['id' => $response->id]) }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-sm text-slate-500">{{ $response->created_at->format('d M Y, H:i') }}</td>
                            @can('viewResponses', $eventForm)
                                @foreach ($previewFields as $field)
                                    @php $answer = $response->answers[$field->id] ?? null; @endphp
                                    <td class="max-w-xs truncate px-4 py-3 text-sm text-slate-700">
                                        {{ is_array($answer) ? implode(', ', $answer) : ($answer ?: '—') }}
                                    </td>
                                @endforeach
                            @endcan
                            @if ($eventForm->checkin_enabled)
                                <td class="whitespace-nowrap px-4 py-3 text-sm">
                                    @if ($response->checkIn)
                                        <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700 ring-1 ring-inset ring-emerald-600/20">
                                            {{ __('Checked In') }}
                                        </span>
                                        <p class="mt-1 text-xs text-slate-400">
                                            {{ $response->checkIn->checked_in_at->format('d M, H:i') }} &middot; {{ ucfirst($response->checkIn->method->value) }}
                                        </p>
                                    @else
                                        <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600 ring-1 ring-inset ring-slate-500/10">
                                            {{ __('Not Checked In') }}
                                        </span>
                                    @endif
                                </td>
                            @endif
                            <td class="whitespace-nowrap px-4 py-3 text-right text-sm font-medium space-x-3">
                                @can('viewResponses', $eventForm)
                                    <button type="button" wire:click="view({{ $response->id }})" class="text-indigo-600 hover:text-indigo-700">{{ __('View') }}</button>
                                @endcan
                                @if ($eventForm->checkin_enabled)
                                    @can('manualCheckIn', $eventForm)
                                        @if (! $response->checkIn)
                                            <button type="button" wire:click="checkIn({{ $response->id }})" class="text-emerald-600 hover:text-emerald-700">{{ __('Check In') }}</button>
                                        @endif
                                    @endcan
                                    @can('undoCheckIn', $eventForm)
                                        @if ($response->checkIn)
                                            <button type="button" wire:click="undoCheckIn({{ $response->id }})" class="text-amber-600 hover:text-amber-700">{{ __('Undo') }}</button>
                                        @endif
                                    @endcan
                                @endif
                                @can('manageResponses', $eventForm)
                                    <button type="button" wire:click="confirmDelete({{ $response->id }})" class="text-red-600 hover:text-red-700">{{ __('Delete') }}</button>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            @php
                                $columnCount = 3 + (auth()->user()->can('viewResponses', $eventForm) ? $previewFields->count() : 0) + ($eventForm->checkin_enabled ? 1 : 0);
                            @endphp
                            <td colspan="{{ $columnCount }}" class="px-4 py-8 text-center text-sm text-slate-500">{{ __('No responses yet.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($responses->hasPages())
            <div class="border-t border-slate-200 px-4 py-3">
                {{ $responses->links() }}
            </div>
        @endif
    </div>

    <!-- View Response Modal -->
    @if ($viewingId !== null)
        @php $viewingResponse = $responses->firstWhere('id', $viewingId); @endphp
        @if ($viewingResponse)
            <div class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0">
                <div class="fixed inset-0 bg-slate-900/50" wire:click="closeViewModal"></div>

                <div class="relative mx-auto mb-6 max-h-[85vh] transform overflow-y-auto rounded-2xl bg-white shadow-xl transition-all sm:w-full sm:max-w-lg">
                    <div class="p-6 sm:p-8">
                        <div class="flex items-center justify-between">
                            <h2 class="text-lg font-semibold text-slate-900">{{ __('Response') }}</h2>
                            <span class="text-xs text-slate-400">{{ $viewingResponse->created_at->format('d M Y, H:i') }}</span>
                        </div>
                        @if ($viewingResponse->reference)
                            <p class="mt-1 text-xs font-mono text-slate-400">{{ $viewingResponse->reference }}</p>
                        @endif
                        @if ($eventForm->checkin_enabled)
                            <p class="mt-2 text-xs text-slate-500">
                                {{ __('Check-in:') }}
                                @if ($viewingResponse->checkIn)
                                    <span class="font-medium text-emerald-600">{{ __('Checked in') }}</span>
                                    {{ $viewingResponse->checkIn->checked_in_at->format('d M Y, H:i') }} ({{ ucfirst($viewingResponse->checkIn->method->value) }})
                                @else
                                    <span class="font-medium text-slate-600">{{ __('Not checked in') }}</span>
                                @endif
                            </p>
                        @endif

                        <dl class="mt-4 divide-y divide-slate-100">
                            @foreach ($fields as $field)
                                @php $answer = $viewingResponse->answers[$field->id] ?? null; @endphp
                                <div class="py-3">
                                    <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ $field->label }}</dt>
                                    <dd class="mt-1 text-sm text-slate-800">{{ is_array($answer) ? implode(', ', $answer) : ($answer ?: '—') }}</dd>
                                </div>
                            @endforeach
                        </dl>

                        <div class="mt-6 flex justify-end">
                            <x-secondary-button type="button" wire:click="closeViewModal">{{ __('Close') }}</x-secondary-button>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    @endif

    <!-- Delete Confirmation Modal -->
    @if ($confirmingDeleteId !== null)
        <div class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0">
            <div class="fixed inset-0 bg-slate-900/50" wire:click="closeDeleteModal"></div>

            <div class="relative mx-auto mb-6 transform overflow-hidden rounded-2xl bg-white shadow-xl transition-all sm:w-full sm:max-w-md">
                <div class="p-6 sm:p-8">
                    <h2 class="text-lg font-semibold text-slate-900">{{ __('Delete Response') }}</h2>
                    <p class="mt-2 text-sm text-slate-500">{{ __('Are you sure you want to delete this response? This cannot be undone.') }}</p>
                    <div class="mt-6 flex justify-end gap-3">
                        <x-secondary-button type="button" wire:click="closeDeleteModal">{{ __('Cancel') }}</x-secondary-button>
                        <x-danger-button wire:click="delete">{{ __('Delete') }}</x-danger-button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
