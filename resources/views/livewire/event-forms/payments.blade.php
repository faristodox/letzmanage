<div class="space-y-6">
    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Registrant') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Method') }}</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Amount') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Status') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Receipt') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Submitted') }}</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    @forelse ($payments as $payment)
                        <tr wire:key="payment-{{ $payment->id }}" class="hover:bg-slate-50">
                            <td class="whitespace-nowrap px-4 py-3 text-sm text-slate-700">
                                {{ $payment->response?->reference ?: __('Response #:id', ['id' => $payment->event_form_response_id]) }}
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-sm text-slate-700">
                                {{ $payment->method->value === 'chip' ? __('CHIP') : __('Bank Transfer') }}
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-right text-sm font-medium text-slate-900">
                                {{ $payment->currency }} {{ number_format($payment->amount, 2) }}
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-sm">
                                @php
                                    $statusColors = [
                                        'pending' => 'bg-amber-50 text-amber-700 ring-amber-600/20',
                                        'paid' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
                                        'rejected' => 'bg-red-50 text-red-700 ring-red-600/20',
                                        'failed' => 'bg-red-50 text-red-700 ring-red-600/20',
                                        'expired' => 'bg-slate-100 text-slate-600 ring-slate-500/10',
                                    ];
                                @endphp
                                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset {{ $statusColors[$payment->status->value] }}">
                                    {{ ucfirst($payment->status->value) }}
                                </span>
                                @if ($payment->reviewedBy)
                                    <p class="mt-1 text-xs text-slate-400">
                                        {{ __('by :name, :date', ['name' => $payment->reviewedBy->name, 'date' => $payment->reviewed_at?->format('d M, H:i')]) }}
                                    </p>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-sm">
                                @if ($payment->receiptUrl())
                                    <a href="{{ $payment->receiptUrl() }}" target="_blank" rel="noopener" class="inline-block">
                                        <img src="{{ $payment->receiptUrl() }}" alt="{{ __('Receipt') }}" class="h-12 w-12 rounded-lg object-cover ring-1 ring-slate-200 transition hover:ring-indigo-400">
                                    </a>
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-sm text-slate-500">{{ $payment->created_at->format('d M Y, H:i') }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-right text-sm font-medium space-x-3">
                                @if ($payment->status->value === 'pending')
                                    <button type="button" wire:click="approve({{ $payment->id }})" class="text-emerald-600 hover:text-emerald-700">
                                        {{ __('Approve') }}
                                    </button>
                                    @if ($payment->method->value === 'bank_transfer')
                                        <button type="button" wire:click="confirmReject({{ $payment->id }})" class="text-red-600 hover:text-red-700">
                                            {{ __('Reject') }}
                                        </button>
                                    @endif
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-sm text-slate-500">{{ __('No payments yet.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Reject Confirmation Modal -->
    @if ($confirmingRejectId !== null)
        <div class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0">
            <div class="fixed inset-0 bg-slate-900/50" wire:click="closeRejectModal"></div>

            <div class="relative mx-auto mb-6 transform overflow-hidden rounded-2xl bg-white shadow-xl transition-all sm:w-full sm:max-w-md">
                <div class="p-6 sm:p-8">
                    <h2 class="text-lg font-semibold text-slate-900">{{ __('Reject Payment') }}</h2>
                    <p class="mt-2 text-sm text-slate-500">{{ __('Are you sure this receipt should be rejected? The registrant will need to be contacted separately — this only updates the payment status.') }}</p>
                    <div class="mt-6 flex justify-end gap-3">
                        <x-secondary-button type="button" wire:click="closeRejectModal">{{ __('Cancel') }}</x-secondary-button>
                        <x-danger-button wire:click="reject">{{ __('Reject') }}</x-danger-button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
