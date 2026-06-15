<div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h3 class="text-base font-semibold text-slate-900">{{ __('Office Space Types') }}</h3>
            <p class="mt-1 text-sm text-slate-500">
                {{ __('Manage the types available when adding or editing office spaces (e.g. Meeting Room, Hot Desk).') }}
            </p>
        </div>

        <x-primary-button wire:click="create" class="shrink-0">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-4 w-4">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
            </svg>
            {{ __('Add Type') }}
        </x-primary-button>
    </div>

    <div class="mt-4 divide-y divide-slate-100">
        @forelse ($types as $type)
            <div wire:key="type-{{ $type->id }}" class="flex items-center justify-between gap-4 py-3">
                <div>
                    <span class="text-sm font-medium text-slate-900">{{ $type->name }}</span>
                    @if ($type->office_spaces_count > 0)
                        <span class="ml-2 text-xs text-slate-500">{{ __('in use by :count space(s)', ['count' => $type->office_spaces_count]) }}</span>
                    @endif
                </div>
                <div class="flex items-center gap-3 text-sm font-medium">
                    <button wire:click="edit({{ $type->id }})" class="text-indigo-600 hover:text-indigo-700">{{ __('Edit') }}</button>
                    <button wire:click="confirmDelete({{ $type->id }})" class="text-red-600 hover:text-red-700">{{ __('Delete') }}</button>
                </div>
            </div>
        @empty
            <p class="py-4 text-sm text-slate-500">{{ __('No office space types yet.') }}</p>
        @endforelse
    </div>

    <!-- Create / Edit Modal -->
    @if ($showModal)
        <div class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0">
            <div class="fixed inset-0 bg-slate-900/50" wire:click="closeModal"></div>

            <div class="relative mx-auto mb-6 transform overflow-hidden rounded-2xl bg-white shadow-xl transition-all sm:w-full sm:max-w-md">
                <form wire:submit="save" class="p-6 sm:p-8">
                    <h2 class="text-lg font-semibold text-slate-900">
                        {{ $editingId ? __('Edit Office Space Type') : __('Add Office Space Type') }}
                    </h2>

                    <div class="mt-4">
                        <x-input-label for="name" :value="__('Name')" />
                        <x-text-input wire:model="name" id="name" type="text" class="mt-1 block w-full" autofocus placeholder="e.g. Meeting Room" />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
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
            <div class="fixed inset-0 bg-slate-900/50" wire:click="cancelDelete"></div>

            <div class="relative mx-auto mb-6 transform overflow-hidden rounded-2xl bg-white shadow-xl transition-all sm:w-full sm:max-w-md">
                <div class="p-6 sm:p-8">
                    <h2 class="text-lg font-semibold text-slate-900">{{ __('Delete Office Space Type') }}</h2>
                    <p class="mt-2 text-sm text-slate-500">
                        {{ __('Are you sure you want to delete this office space type?') }}
                    </p>

                    @if ($deleteError)
                        <div class="mt-4 rounded-lg bg-red-50 p-3 text-sm text-red-700 ring-1 ring-inset ring-red-600/10">
                            {{ $deleteError }}
                        </div>
                    @endif

                    <div class="mt-6 flex justify-end gap-3">
                        <x-secondary-button type="button" wire:click="cancelDelete">{{ __('Cancel') }}</x-secondary-button>
                        <x-danger-button wire:click="delete">{{ __('Delete') }}</x-danger-button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
