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
        <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Name') }}</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Position') }}</th>
                    <th class="px-6 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 bg-white">
                @forelse ($committeeMembers as $committeeMember)
                    <tr wire:key="committee-member-{{ $committeeMember->id }}" class="hover:bg-slate-50">
                        <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-slate-900">{{ $committeeMember->name }}</td>
                        <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-600">{{ $committeeMember->position }}</td>
                        <td class="whitespace-nowrap px-6 py-4 text-right text-sm">
                            <button wire:click="edit({{ $committeeMember->id }})" class="font-medium text-indigo-600 hover:text-indigo-700">{{ __('Edit') }}</button>
                            <button wire:click="delete({{ $committeeMember->id }})" wire:confirm="{{ __('Remove this committee member?') }}" class="ml-3 font-medium text-red-600 hover:text-red-700">{{ __('Remove') }}</button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="px-6 py-8 text-center text-sm text-slate-500">{{ __('No committee members added yet.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
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

                        <div class="mt-6 flex justify-end gap-3">
                            <x-secondary-button type="button" wire:click="closeModal">{{ __('Cancel') }}</x-secondary-button>
                            <x-primary-button type="submit">{{ $editingId ? __('Save') : __('Add') }}</x-primary-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
