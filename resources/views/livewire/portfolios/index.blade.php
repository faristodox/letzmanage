<div>
    <div class="mb-4 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="w-full sm:w-72">
            <x-text-input wire:model.live.debounce.300ms="search" type="text" class="block w-full" placeholder="{{ __('Search portfolios...') }}" />
        </div>

        @can('create', App\Models\Portfolio::class)
            <x-primary-button wire:click="create">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-4 w-4">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                {{ __('Add Portfolio') }}
            </x-primary-button>
        @endcan
    </div>

    @if (session('status'))
        <div class="mb-4 rounded-lg bg-emerald-50 p-3 text-sm text-emerald-700 ring-1 ring-inset ring-emerald-600/20">
            {{ session('status') }}
        </div>
    @endif

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Name') }}</th>
                    <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 bg-white">
                @forelse ($portfolios as $portfolio)
                    <tr wire:key="portfolio-{{ $portfolio->id }}" class="hover:bg-slate-50">
                        <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-slate-900">{{ $portfolio->name }}</td>
                        <td class="whitespace-nowrap px-6 py-4 text-right text-sm font-medium space-x-3">
                            @can('update', $portfolio)
                                <button wire:click="edit({{ $portfolio->id }})" class="text-indigo-600 hover:text-indigo-700">{{ __('Edit') }}</button>
                            @endcan
                            @can('delete', $portfolio)
                                <button wire:click="confirmDelete({{ $portfolio->id }})" class="text-red-600 hover:text-red-700">{{ __('Delete') }}</button>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="2" class="px-6 py-8 text-center text-sm text-slate-500">{{ __('No portfolios found.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </div>

    <div class="mt-4">
        {{ $portfolios->links() }}
    </div>

    <!-- Create / Edit Modal -->
    @if ($showModal)
        <div class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0">
            <div class="fixed inset-0 bg-slate-900/50" wire:click="closeModal"></div>

            <div class="relative mx-auto mb-6 transform overflow-hidden rounded-2xl bg-white shadow-xl transition-all sm:w-full sm:max-w-md">
                <form wire:submit="save" class="p-6 sm:p-8">
                    <h2 class="text-lg font-semibold text-slate-900">
                        {{ $editingId ? __('Edit Portfolio') : __('Add Portfolio') }}
                    </h2>

                    <div class="mt-4">
                        <x-input-label for="name" :value="__('Name')" />
                        <x-text-input wire:model="name" id="name" type="text" class="mt-1 block w-full" placeholder="{{ __('e.g. Jawatankuasa WANITA') }}" />
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
            <div class="fixed inset-0 bg-slate-900/50" wire:click="closeDeleteModal"></div>

            <div class="relative mx-auto mb-6 transform overflow-hidden rounded-2xl bg-white shadow-xl transition-all sm:w-full sm:max-w-md">
                <div class="p-6 sm:p-8">
                    <h2 class="text-lg font-semibold text-slate-900">{{ __('Delete Portfolio') }}</h2>
                    <p class="mt-2 text-sm text-slate-500">
                        {{ __('Are you sure you want to delete this portfolio? Users, events, and committee members currently assigned to it will keep their own data, but will no longer be linked to it.') }}
                    </p>
                    <div class="mt-6 flex justify-end gap-3">
                        <x-secondary-button type="button" wire:click="closeDeleteModal">{{ __('Cancel') }}</x-secondary-button>
                        <x-danger-button wire:click="delete">{{ __('Delete') }}</x-danger-button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
