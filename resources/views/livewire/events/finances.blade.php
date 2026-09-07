<div class="space-y-6">
    <!-- Summary -->
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

    @can('manageFinances', $event)
        <div class="flex flex-wrap gap-2">
            <x-primary-button wire:click="recordIncome">{{ __('+ Record Income') }}</x-primary-button>
            <x-secondary-button wire:click="recordExpense">{{ __('+ Record Expense') }}</x-secondary-button>
        </div>
    @endcan

    <!-- Transactions -->
    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Date') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Type') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Category') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Source') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Description') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Registrant') }}</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Amount') }}</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    @forelse ($transactions as $transaction)
                        <tr wire:key="transaction-{{ $transaction->id }}" class="hover:bg-slate-50">
                            <td class="whitespace-nowrap px-4 py-3 text-sm text-slate-500">{{ $transaction->transaction_date->format('d M Y') }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-sm">
                                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset {{ $transaction->type->value === 'income' ? 'bg-emerald-50 text-emerald-700 ring-emerald-600/20' : 'bg-red-50 text-red-700 ring-red-600/20' }}">
                                    {{ ucfirst($transaction->type->value) }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-sm text-slate-700">{{ $transaction->category }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-sm">
                                @php
                                    $sourceColors = [
                                        'manual' => 'bg-slate-100 text-slate-600 ring-slate-500/10',
                                        'gateway' => 'bg-indigo-50 text-indigo-700 ring-indigo-600/20',
                                        'bank_transfer' => 'bg-sky-50 text-sky-700 ring-sky-600/20',
                                    ];
                                    $sourceLabels = [
                                        'manual' => __('Manual'),
                                        'gateway' => __('CHIP'),
                                        'bank_transfer' => __('Bank Transfer'),
                                    ];
                                @endphp
                                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset {{ $sourceColors[$transaction->source->value] }}">
                                    {{ $sourceLabels[$transaction->source->value] }}
                                </span>
                            </td>
                            <td class="max-w-xs truncate px-4 py-3 text-sm text-slate-500">{{ $transaction->description ?: '—' }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-sm text-slate-500">
                                {{ $transaction->response ? ($transaction->response->reference ?: __('Response #:id', ['id' => $transaction->response->id])) : '—' }}
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-right text-sm font-medium {{ $transaction->type->value === 'income' ? 'text-emerald-600' : 'text-red-600' }}">
                                {{ $transaction->type->value === 'income' ? '+' : '-' }}{{ number_format($transaction->amount, 2) }}
                                @if ($transaction->gateway_fee_amount !== null)
                                    <p class="mt-0.5 text-xs font-normal text-slate-400">
                                        {{ __('Fee: :fee · Net: :net', ['fee' => number_format($transaction->gateway_fee_amount, 2), 'net' => number_format($transaction->netAmount(), 2)]) }}
                                    </p>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-right text-sm font-medium space-x-3">
                                @can('manageFinances', $event)
                                    <button type="button" wire:click="edit({{ $transaction->id }})" class="text-indigo-600 hover:text-indigo-700">{{ __('Edit') }}</button>
                                    <button type="button" wire:click="confirmDelete({{ $transaction->id }})" class="text-red-600 hover:text-red-700">{{ __('Delete') }}</button>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-8 text-center text-sm text-slate-500">{{ __('No transactions recorded yet.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Record/Edit Modal -->
    @if ($showModal)
        <div class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0">
            <div class="fixed inset-0 bg-slate-900/50" wire:click="closeModal"></div>

            <div class="relative mx-auto mb-6 transform overflow-hidden rounded-2xl bg-white shadow-xl transition-all sm:w-full sm:max-w-md">
                <form wire:submit="save" class="p-6 sm:p-8">
                    <h2 class="text-lg font-semibold text-slate-900">
                        @if ($editingId)
                            {{ $type === 'income' ? __('Edit Income') : __('Edit Expense') }}
                        @else
                            {{ $type === 'income' ? __('Record Income') : __('Record Expense') }}
                        @endif
                    </h2>

                    <div class="mt-4">
                        <x-input-label for="category" :value="__('Category')" />
                        <x-text-input wire:model="category" id="category" type="text" class="mt-1 block w-full" placeholder="{{ $type === 'income' ? __('e.g. Registration Fee, Sponsorship') : __('e.g. Venue, Catering, Materials') }}" autofocus />
                        <x-input-error :messages="$errors->get('category')" class="mt-2" />
                    </div>

                    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <x-input-label for="amount" :value="__('Amount')" />
                            <x-text-input wire:model="amount" id="amount" type="number" step="0.01" min="0.01" class="mt-1 block w-full" />
                            <x-input-error :messages="$errors->get('amount')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="transactionDate" :value="__('Date')" />
                            <input wire:model="transactionDate" id="transactionDate" type="date" class="mt-1 block w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <x-input-error :messages="$errors->get('transactionDate')" class="mt-2" />
                        </div>
                    </div>

                    @if ($type === 'income' && $registrationResponses->isNotEmpty())
                        <div class="mt-4">
                            <x-input-label for="responseId" :value="__('Link to Registrant (optional)')" />
                            <select wire:model="responseId" id="responseId" class="mt-1 block w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">{{ __('— Not linked —') }}</option>
                                @foreach ($registrationResponses as $response)
                                    <option value="{{ $response->id }}">{{ $response->reference ?: __('Response #:id', ['id' => $response->id]) }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('responseId')" class="mt-2" />
                        </div>
                    @endif

                    <div class="mt-4">
                        <x-input-label for="description" :value="__('Description (optional)')" />
                        <textarea wire:model="description" id="description" rows="2" class="mt-1 block w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                        <x-input-error :messages="$errors->get('description')" class="mt-2" />
                    </div>

                    <div class="mt-6 flex justify-end gap-3">
                        <x-secondary-button type="button" wire:click="closeModal">{{ __('Cancel') }}</x-secondary-button>
                        <x-primary-button type="submit">{{ __('Save') }}</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- Delete Confirmation Modal -->
    @if ($confirmingDeleteId !== null)
        <div class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0">
            <div class="fixed inset-0 bg-slate-900/50" wire:click="closeDeleteModal"></div>

            <div class="relative mx-auto mb-6 transform overflow-hidden rounded-2xl bg-white shadow-xl transition-all sm:w-full sm:max-w-md">
                <div class="p-6 sm:p-8">
                    <h2 class="text-lg font-semibold text-slate-900">{{ __('Delete Transaction') }}</h2>
                    <p class="mt-2 text-sm text-slate-500">{{ __('Are you sure you want to delete this transaction? This cannot be undone.') }}</p>
                    <div class="mt-6 flex justify-end gap-3">
                        <x-secondary-button type="button" wire:click="closeDeleteModal">{{ __('Cancel') }}</x-secondary-button>
                        <x-danger-button wire:click="delete">{{ __('Delete') }}</x-danger-button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
