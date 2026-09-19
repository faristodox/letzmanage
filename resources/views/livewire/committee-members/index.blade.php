<div>
    <div class="mb-4 flex items-center justify-between">
        <p class="text-sm text-slate-600">{{ __('This list is used to identify attendees correctly in the Minutes of Meeting.') }}</p>

        @can('create', App\Models\CommitteeMember::class)
            <x-primary-button wire:click="create">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-4 w-4">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                {{ __('Add Member') }}
            </x-primary-button>
        @endcan
    </div>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Name') }}</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Position') }}</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('IC Number') }}</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Portfolio') }}</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Linked Account') }}</th>
                    <th class="px-6 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 bg-white">
                @forelse ($committeeMembers as $committeeMember)
                    <tr wire:key="committee-member-{{ $committeeMember->id }}" class="hover:bg-slate-50">
                        <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-slate-900">{{ $committeeMember->name }}</td>
                        <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-600">{{ $committeeMember->position }}</td>
                        <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-500">{{ $committeeMember->maskedIcNumber() ?? '—' }}</td>
                        <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-500">{{ $committeeMember->portfolio?->name ?? '—' }}</td>
                        <td class="whitespace-nowrap px-6 py-4 text-sm">
                            @if ($committeeMember->user)
                                <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700 ring-1 ring-inset ring-emerald-600/20">
                                    {{ $committeeMember->user->email }}
                                </span>
                            @else
                                <span class="text-slate-400">—</span>
                            @endif
                        </td>
                        <td class="whitespace-nowrap px-6 py-4 text-right text-sm">
                            <button wire:click="viewAttendance({{ $committeeMember->id }})" class="font-medium text-indigo-600 hover:text-indigo-700">{{ __('Attendance') }}</button>
                            <button wire:click="edit({{ $committeeMember->id }})" class="ml-3 font-medium text-indigo-600 hover:text-indigo-700">{{ __('Edit') }}</button>
                            <button wire:click="delete({{ $committeeMember->id }})" wire:confirm="{{ __('Remove this committee member?') }}" class="ml-3 font-medium text-red-600 hover:text-red-700">{{ __('Remove') }}</button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-6 py-8 text-center text-sm text-slate-500">{{ __('No committee members added yet.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </div>

    <div class="mt-4">
        {{ $committeeMembers->links() }}
    </div>

    @if ($showModal)
        <div class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0">
            <div class="fixed inset-0 bg-slate-900/50" wire:click="closeModal"></div>

            <div class="relative mx-auto mb-6 transform overflow-hidden rounded-2xl bg-white shadow-xl transition-all sm:w-full sm:max-w-lg">
                <div class="p-6 sm:p-8">
                    <h2 class="text-lg font-semibold text-slate-900">{{ $editingId ? __('Edit Committee Member') : __('Add Committee Member') }}</h2>

                    <form wire:submit="save" class="mt-4 space-y-4">
                        <div>
                            <x-input-label for="committee_member_name" :value="__('Name')" />
                            <x-text-input wire:model="name" id="committee_member_name" type="text" class="mt-1 block w-full" placeholder="{{ __('e.g. Ahmad Zaki') }}" autofocus />
                            <x-input-error :messages="$errors->get('name')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="committee_member_position" :value="__('Position')" />
                            <x-text-input wire:model="position" id="committee_member_position" type="text" class="mt-1 block w-full" placeholder="{{ __('e.g. President') }}" />
                            <x-input-error :messages="$errors->get('position')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="committee_member_ic_number" :value="__('IC Number (MyKad)')" />
                            <x-text-input wire:model="icNumber" id="committee_member_ic_number" type="text" class="mt-1 block w-full" placeholder="{{ __('Optional — needed for meeting check-in') }}" />
                            <x-input-error :messages="$errors->get('icNumber')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="committee_member_portfolio_id" :value="__('Portfolio (optional)')" />
                            <select wire:model="portfolio_id" id="committee_member_portfolio_id" class="mt-1 block w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">{{ __('No portfolio') }}</option>
                                @foreach ($portfolios as $portfolio)
                                    <option value="{{ $portfolio->id }}">{{ $portfolio->name }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('portfolio_id')" class="mt-2" />
                        </div>

                        <div class="mt-6 flex justify-end gap-3">
                            <x-secondary-button type="button" wire:click="closeModal">{{ __('Cancel') }}</x-secondary-button>
                            <x-primary-button type="submit">{{ $editingId ? __('Save') : __('Add') }}</x-primary-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    @if ($viewingAttendance)
        <div class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0">
            <div class="fixed inset-0 bg-slate-900/50" wire:click="closeAttendanceModal"></div>

            <div class="relative mx-auto mb-6 transform overflow-hidden rounded-2xl bg-white shadow-xl transition-all sm:w-full sm:max-w-lg">
                <div class="p-6 sm:p-8">
                    <h2 class="text-lg font-semibold text-slate-900">{{ __('Attendance history') }}</h2>
                    <p class="mt-1 text-sm text-slate-500">{{ $viewingAttendance->name }} ({{ $viewingAttendance->position }})</p>

                    <div class="mt-4 max-h-96 space-y-2 overflow-y-auto">
                        @forelse ($viewingAttendance->attendedEvents as $attendedEvent)
                            <div class="rounded-lg border border-slate-200 p-3">
                                <p class="text-sm font-medium text-slate-900">{{ $attendedEvent->title }}</p>
                                <p class="text-xs text-slate-500">{{ \Illuminate\Support\Carbon::parse($attendedEvent->pivot->checked_in_at)->format('d M Y, g:i A') }}</p>
                            </div>
                        @empty
                            <p class="text-sm text-slate-500">{{ __('No recorded attendance yet.') }}</p>
                        @endforelse
                    </div>

                    <div class="mt-6 flex justify-end">
                        <x-secondary-button type="button" wire:click="closeAttendanceModal">{{ __('Close') }}</x-secondary-button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
