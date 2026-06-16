<div>
    <div class="mb-4 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="w-full sm:w-72">
            <x-text-input wire:model.live.debounce.300ms="search" type="text" class="block w-full" placeholder="{{ __('Search office spaces...') }}" />
        </div>

        @can('create', [App\Models\OfficeSpace::class, auth()->user()->branch_id])
            <x-primary-button wire:click="create">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-4 w-4">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                {{ __('Add Office Space') }}
            </x-primary-button>
        @endcan
    </div>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Image') }}</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Name') }}</th>
                    @if (auth()->user()->hasRole(App\Enums\RoleName::Admin->value))
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Branch') }}</th>
                    @endif
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Type') }}</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Capacity') }}</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Facilities') }}</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Status') }}</th>
                    <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 bg-white">
                @forelse ($spaces as $space)
                    <tr wire:key="space-{{ $space->id }}" class="hover:bg-slate-50">
                        <td class="whitespace-nowrap px-6 py-4">
                            @if ($space->image_path)
                                <img src="{{ \Illuminate\Support\Facades\Storage::url($space->image_path) }}" alt="{{ $space->name }}" class="h-12 w-16 rounded-lg object-cover">
                            @else
                                <div class="flex h-12 w-16 items-center justify-center rounded-lg bg-slate-100 text-slate-400">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-6 w-6">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 001.5 1.5zm10.5-11.25h.008v.008h-.008V8.25zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" />
                                    </svg>
                                </div>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-sm font-medium text-slate-900">
                            {{ $space->name }}
                            @if ($space->parent)
                                <span class="ml-1.5 inline-flex items-center rounded-full bg-violet-50 px-2 py-0.5 text-xs font-medium text-violet-700 ring-1 ring-inset ring-violet-600/20">
                                    Part of {{ $space->parent->name }}
                                </span>
                            @endif
                        </td>
                        @if (auth()->user()->hasRole(App\Enums\RoleName::Admin->value))
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-500">{{ $space->branch->name }}</td>
                        @endif
                        <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-500">{{ $space->type->name }}</td>
                        <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-500">{{ $space->capacity }}</td>
                        <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-500">{{ $space->facilities ? implode(', ', $space->facilities) : '—' }}</td>
                        <td class="whitespace-nowrap px-6 py-4 text-sm">
                            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset {{ $space->status === App\Enums\OfficeSpaceStatus::Active ? 'bg-emerald-50 text-emerald-700 ring-emerald-600/20' : 'bg-amber-50 text-amber-700 ring-amber-600/20' }}">
                                {{ ucfirst($space->status->value) }}
                            </span>
                        </td>
                        <td class="whitespace-nowrap px-6 py-4 text-right text-sm font-medium space-x-3">
                            @can('update', $space)
                                <button wire:click="edit({{ $space->id }})" class="text-indigo-600 hover:text-indigo-700">{{ __('Edit') }}</button>
                            @endcan
                            @can('delete', $space)
                                <button wire:click="confirmDelete({{ $space->id }})" class="text-red-600 hover:text-red-700">{{ __('Delete') }}</button>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-6 py-8 text-center text-sm text-slate-500">{{ __('No office spaces found.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </div>

    <div class="mt-4">
        {{ $spaces->links() }}
    </div>

    <!-- Create / Edit Modal -->
    @if ($showModal)
        <div class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0">
            <div class="fixed inset-0 bg-slate-900/50" wire:click="closeModal"></div>

            <div class="relative mx-auto mb-6 transform overflow-hidden rounded-2xl bg-white shadow-xl transition-all sm:w-full sm:max-w-md">
                <form wire:submit="save" class="p-6 sm:p-8">
                    <h2 class="text-lg font-semibold text-slate-900">
                        {{ $editingId ? __('Edit Office Space') : __('Add Office Space') }}
                    </h2>

                    @if (auth()->user()->hasRole(App\Enums\RoleName::Admin->value))
                        <div class="mt-4">
                            <x-input-label for="branch_id" :value="__('Branch')" />
                            <select wire:model="branch_id" id="branch_id" class="mt-1 block w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">{{ __('Select a branch') }}</option>
                                @foreach ($branches as $branch)
                                    <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('branch_id')" class="mt-2" />
                        </div>
                    @endif

                    <div class="mt-4">
                        <x-input-label for="name" :value="__('Name')" />
                        <x-text-input wire:model="name" id="name" type="text" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>

                    <div class="mt-4">
                        <x-input-label for="image" :value="__('Photo')" />

                        @if ($image)
                            <img src="{{ $image->temporaryUrl() }}" alt="{{ __('Preview') }}" class="mt-2 h-32 w-full rounded-lg object-cover">
                        @elseif ($existingImagePath && ! $removeImage)
                            <img src="{{ \Illuminate\Support\Facades\Storage::url($existingImagePath) }}" alt="{{ __('Current photo') }}" class="mt-2 h-32 w-full rounded-lg object-cover">
                        @endif

                        <input wire:model="image" id="image" type="file" accept="image/*" class="mt-2 block w-full text-sm text-slate-500 file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-indigo-700 hover:file:bg-indigo-100">
                        <div wire:loading wire:target="image" class="mt-1 text-xs text-slate-500">{{ __('Uploading...') }}</div>
                        <x-input-error :messages="$errors->get('image')" class="mt-2" />

                        @if ($existingImagePath && ! $image)
                            <label class="mt-2 flex items-center gap-2 text-sm text-slate-600">
                                <input type="checkbox" wire:model="removeImage" class="rounded border-slate-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                {{ __('Remove current photo') }}
                            </label>
                        @endif
                    </div>

                    <div class="mt-4">
                        <x-input-label for="type_id" :value="__('Type')" />
                        <select wire:model="type_id" id="type_id" class="mt-1 block w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @foreach ($types as $typeOption)
                                <option value="{{ $typeOption->id }}">{{ $typeOption->name }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('type_id')" class="mt-2" />
                        @can('viewAny', App\Models\OfficeSpaceType::class)
                            <p class="mt-1 text-xs text-slate-500">
                                {{ __('Need a different type?') }}
                                <a href="{{ route('settings.index') }}" class="font-medium text-indigo-600 hover:text-indigo-700">{{ __('Manage types in Settings') }}</a>
                            </p>
                        @endcan
                    </div>

                    <div class="mt-4">
                        <x-input-label for="capacity" :value="__('Capacity')" />
                        <x-text-input wire:model="capacity" id="capacity" type="number" min="1" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('capacity')" class="mt-2" />
                    </div>

                    <div class="mt-4">
                        <x-input-label for="facilities" :value="__('Facilities (comma separated)')" />
                        <x-text-input wire:model="facilities" id="facilities" type="text" class="mt-1 block w-full" placeholder="projector, whiteboard" />
                        <x-input-error :messages="$errors->get('facilities')" class="mt-2" />
                    </div>

                    <div class="mt-4">
                        <x-input-label for="parent_id" :value="__('Sub-space of (optional)')" />
                        <select wire:model="parent_id" id="parent_id" class="mt-1 block w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">{{ __('— None (standalone space) —') }}</option>
                            @foreach ($parentOptions as $option)
                                <option value="{{ $option->id }}">{{ $option->name }}</option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-slate-500">{{ __('If set, booking this space will also block the parent, and vice versa.') }}</p>
                        <x-input-error :messages="$errors->get('parent_id')" class="mt-2" />
                    </div>

                    <div class="mt-4">
                        <x-input-label for="status" :value="__('Status')" />
                        <select wire:model="status" id="status" class="mt-1 block w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @foreach ($statuses as $statusOption)
                                <option value="{{ $statusOption->value }}">{{ ucfirst($statusOption->value) }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('status')" class="mt-2" />
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
                    <h2 class="text-lg font-semibold text-slate-900">{{ __('Delete Office Space') }}</h2>
                    <p class="mt-2 text-sm text-slate-500">
                        {{ __('Are you sure you want to delete this office space? This will also remove its bookings.') }}
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
