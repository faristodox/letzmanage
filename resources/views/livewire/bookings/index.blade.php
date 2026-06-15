<div>
    <div class="mb-4 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="w-full sm:w-56">
            <select wire:model.live="statusFilter" class="block w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">{{ __('All statuses') }}</option>
                @foreach ($statuses as $statusOption)
                    <option value="{{ $statusOption->value }}">{{ ucfirst($statusOption->value) }}</option>
                @endforeach
            </select>
        </div>

        @can('create', App\Models\Booking::class)
            <x-primary-button wire:click="create">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-4 w-4">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                {{ __('New Booking') }}
            </x-primary-button>
        @endcan
    </div>

    @if (session('status'))
        <div class="mb-4 rounded-lg bg-emerald-50 p-3 text-sm text-emerald-700 ring-1 ring-inset ring-emerald-600/20">
            {{ session('status') }}
        </div>
    @endif

    @if (session('error'))
        <div class="mb-4 rounded-lg bg-red-50 p-3 text-sm text-red-700 ring-1 ring-inset ring-red-600/10">
            {{ session('error') }}
        </div>
    @endif

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Space') }}</th>
                    @if ($showAllColumns)
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Branch') }}</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Booked By') }}</th>
                    @endif
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Title') }}</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Start') }}</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('End') }}</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Status') }}</th>
                    <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 bg-white">
                @forelse ($bookings as $booking)
                    <tr wire:key="booking-{{ $booking->id }}" class="hover:bg-slate-50">
                        <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-slate-900">{{ $booking->space->name }}</td>
                        @if ($showAllColumns)
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-500">{{ $booking->branch->name }}</td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-500">{{ $booking->requesterName() }}</td>
                        @endif
                        <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-500">{{ $booking->title ?: '—' }}</td>
                        <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-500">{{ $booking->start_time->format('Y-m-d H:i') }}</td>
                        <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-500">{{ $booking->end_time->format('Y-m-d H:i') }}</td>
                        <td class="whitespace-nowrap px-6 py-4 text-sm">
                            @php
                                $statusColors = [
                                    'pending' => 'bg-amber-50 text-amber-700 ring-amber-600/20',
                                    'approved' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
                                    'rejected' => 'bg-red-50 text-red-700 ring-red-600/10',
                                    'cancelled' => 'bg-slate-100 text-slate-600 ring-slate-500/10',
                                ];
                            @endphp
                            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset {{ $statusColors[$booking->status->value] }}">
                                {{ ucfirst($booking->status->value) }}
                            </span>
                        </td>
                        <td class="whitespace-nowrap px-6 py-4 text-right text-sm font-medium space-x-3">
                            @if ($booking->status === App\Enums\BookingStatus::Pending)
                                @can('approve', $booking)
                                    <button wire:click="confirmApprove({{ $booking->id }})" class="text-emerald-600 hover:text-emerald-700">{{ __('Approve') }}</button>
                                @endcan
                                @can('reject', $booking)
                                    <button wire:click="confirmReject({{ $booking->id }})" class="text-red-600 hover:text-red-700">{{ __('Reject') }}</button>
                                @endcan
                            @endif
                            @if (in_array($booking->status, [App\Enums\BookingStatus::Pending, App\Enums\BookingStatus::Approved]))
                                @can('cancel', $booking)
                                    <button wire:click="confirmCancel({{ $booking->id }})" class="text-slate-500 hover:text-slate-700">{{ __('Cancel') }}</button>
                                @endcan
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $showAllColumns ? 8 : 6 }}" class="px-6 py-8 text-center text-sm text-slate-500">{{ __('No bookings found.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </div>

    <div class="mt-4">
        {{ $bookings->links() }}
    </div>

    <!-- Create Booking Modal -->
    @if ($showModal)
        <div class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0">
            <div class="fixed inset-0 bg-slate-900/50" wire:click="closeModal"></div>

            <div class="relative mx-auto mb-6 transform overflow-hidden rounded-2xl bg-white shadow-xl transition-all sm:w-full sm:max-w-md">
                <form wire:submit="save" class="p-6 sm:p-8">
                    <h2 class="text-lg font-semibold text-slate-900">{{ __('New Booking') }}</h2>

                    @if ($errorMessage)
                        <div class="mt-4 rounded-lg bg-red-50 p-3 text-sm text-red-700 ring-1 ring-inset ring-red-600/10">
                            {{ $errorMessage }}
                        </div>
                    @endif

                    <div class="mt-4">
                        <x-input-label for="space_id" :value="__('Office Space')" />
                        <select wire:model="space_id" id="space_id" class="mt-1 block w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">{{ __('Select an office space') }}</option>
                            @foreach ($spaces as $space)
                                <option value="{{ $space->id }}">{{ $space->name }} ({{ $space->type->name }}, {{ __('capacity') }} {{ $space->capacity }})</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('space_id')" class="mt-2" />
                    </div>

                    <div class="mt-4">
                        <x-input-label for="title" :value="__('Title (optional)')" />
                        <x-text-input wire:model="title" id="title" type="text" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('title')" class="mt-2" />
                    </div>

                    <div class="mt-4">
                        <x-input-label for="date" :value="__('Date')" />
                        <x-text-input wire:model="date" id="date" type="date" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('date')" class="mt-2" />
                    </div>

                    <div class="mt-4 grid grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="start_time" :value="__('Start Time')" />
                            <x-text-input wire:model="start_time" id="start_time" type="time" class="mt-1 block w-full" />
                            <x-input-error :messages="$errors->get('start_time')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="end_time" :value="__('End Time')" />
                            <x-text-input wire:model="end_time" id="end_time" type="time" class="mt-1 block w-full" />
                            <x-input-error :messages="$errors->get('end_time')" class="mt-2" />
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end gap-3">
                        <x-secondary-button type="button" wire:click="closeModal">{{ __('Cancel') }}</x-secondary-button>
                        <x-primary-button type="submit">{{ __('Submit') }}</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- Approve Modal -->
    @if ($approvingId !== null)
        <div class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0">
            <div class="fixed inset-0 bg-slate-900/50" wire:click="closeApproveModal"></div>

            <div class="relative mx-auto mb-6 transform overflow-hidden rounded-2xl bg-white shadow-xl transition-all sm:w-full sm:max-w-md">
                <form wire:submit="approve" class="p-6 sm:p-8">
                    <h2 class="text-lg font-semibold text-slate-900">{{ __('Approve Booking') }}</h2>

                    <div class="mt-4">
                        <x-input-label for="approveNote" :value="__('Note for requester (optional)')" />
                        <textarea wire:model="approveNote" id="approveNote" rows="3" placeholder="{{ __('e.g. access instructions, parking info...') }}" class="mt-1 block w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                        <p class="mt-1 text-xs text-slate-500">{{ __('This will be included in the approval email sent to the requester.') }}</p>
                    </div>

                    <div class="mt-6 flex justify-end gap-3">
                        <x-secondary-button type="button" wire:click="closeApproveModal">{{ __('Cancel') }}</x-secondary-button>
                        <x-primary-button type="submit">{{ __('Approve Booking') }}</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- Reject Modal -->
    @if ($rejectingId !== null)
        <div class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0">
            <div class="fixed inset-0 bg-slate-900/50" wire:click="closeRejectModal"></div>

            <div class="relative mx-auto mb-6 transform overflow-hidden rounded-2xl bg-white shadow-xl transition-all sm:w-full sm:max-w-md">
                <form wire:submit="reject" class="p-6 sm:p-8">
                    <h2 class="text-lg font-semibold text-slate-900">{{ __('Reject Booking') }}</h2>

                    <div class="mt-4">
                        <x-input-label for="rejectReason" :value="__('Reason (optional)')" />
                        <textarea wire:model="rejectReason" id="rejectReason" rows="3" class="mt-1 block w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                    </div>

                    <div class="mt-6 flex justify-end gap-3">
                        <x-secondary-button type="button" wire:click="closeRejectModal">{{ __('Cancel') }}</x-secondary-button>
                        <x-danger-button type="submit">{{ __('Reject Booking') }}</x-danger-button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- Cancel Confirmation Modal -->
    @if ($confirmingCancelId !== null)
        <div class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0">
            <div class="fixed inset-0 bg-slate-900/50" wire:click="closeCancelModal"></div>

            <div class="relative mx-auto mb-6 transform overflow-hidden rounded-2xl bg-white shadow-xl transition-all sm:w-full sm:max-w-md">
                <div class="p-6 sm:p-8">
                    <h2 class="text-lg font-semibold text-slate-900">{{ __('Cancel Booking') }}</h2>
                    <p class="mt-2 text-sm text-slate-500">{{ __('Are you sure you want to cancel this booking?') }}</p>
                    <div class="mt-6 flex justify-end gap-3">
                        <x-secondary-button type="button" wire:click="closeCancelModal">{{ __('Close') }}</x-secondary-button>
                        <x-danger-button wire:click="cancel">{{ __('Cancel Booking') }}</x-danger-button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
