<div>
    {{-- Platform stats + signup toggle --}}
    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Organizations</p>
            <p class="mt-1 text-2xl font-bold text-slate-900">{{ $totalOrganizations }}</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Total Users</p>
            <p class="mt-1 text-2xl font-bold text-slate-900">{{ $totalUsers }}</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Public Signup</p>
            <div class="mt-2 flex items-center gap-3">
                <button
                    type="button"
                    wire:click="togglePublicSignup"
                    class="relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition-colors {{ $publicSignupEnabled ? 'bg-indigo-600' : 'bg-slate-300' }}"
                    role="switch"
                    aria-checked="{{ $publicSignupEnabled ? 'true' : 'false' }}"
                >
                    <span class="inline-block h-5 w-5 transform rounded-full bg-white shadow transition-transform {{ $publicSignupEnabled ? 'translate-x-5' : 'translate-x-0.5' }}"></span>
                </button>
                <span class="text-sm font-medium {{ $publicSignupEnabled ? 'text-emerald-600' : 'text-slate-500' }}">
                    {{ $publicSignupEnabled ? 'Enabled' : 'Disabled' }}
                </span>
            </div>
            <p class="mt-1 text-xs text-slate-400">Controls whether anyone can self-register at /register.</p>
        </div>
    </div>

    {{-- Toolbar --}}
    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="w-full sm:w-72">
            <x-text-input wire:model.live.debounce.300ms="search" type="text" class="block w-full" placeholder="{{ __('Search organizations...') }}" />
        </div>
        <x-primary-button wire:click="create">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-4 w-4">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
            </svg>
            {{ __('New Organization') }}
        </x-primary-button>
    </div>

    {{-- Table --}}
    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Organization</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Slug</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Users</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Branches</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Bookings</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">SPI Module</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Status</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    @forelse ($organizations as $organization)
                        <tr wire:key="org-{{ $organization->id }}" class="hover:bg-slate-50">
                            <td class="px-6 py-4 text-sm font-medium text-slate-900">{{ $organization->name }}</td>
                            <td class="px-6 py-4 text-sm">
                                <a href="{{ route('booking.request', $organization->slug) }}" target="_blank" class="font-mono text-indigo-600 hover:text-indigo-700">
                                    /book/{{ $organization->slug }}
                                </a>
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-600">{{ $organization->users_count }}</td>
                            <td class="px-6 py-4 text-sm text-slate-600">{{ $organization->branches_count }}</td>
                            <td class="px-6 py-4 text-sm text-slate-600">{{ $organization->bookings_count }}</td>
                            <td class="px-6 py-4 text-sm">
                                <div class="flex flex-col gap-2">
                                    <button
                                        type="button"
                                        wire:click="toggleSpi({{ $organization->id }})"
                                        class="relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition-colors {{ $organization->spi_enabled ? 'bg-indigo-600' : 'bg-slate-300' }}"
                                        role="switch"
                                        aria-checked="{{ $organization->spi_enabled ? 'true' : 'false' }}"
                                        title="{{ $organization->spi_enabled ? 'Disable SPI module' : 'Enable SPI module' }}"
                                    >
                                        <span class="inline-block h-5 w-5 transform rounded-full bg-white shadow transition-transform {{ $organization->spi_enabled ? 'translate-x-5' : 'translate-x-0.5' }}"></span>
                                    </button>

                                    @if ($organization->spi_enabled)
                                        <select
                                            wire:change="setDistrict({{ $organization->id }}, $event.target.value)"
                                            class="w-44 rounded-lg border-slate-200 py-1 text-xs text-slate-700 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        >
                                            <option value="">— pilih kawasan —</option>
                                            @foreach ($kawasanOptions as $kawasan)
                                                <option value="{{ $kawasan->value }}" @selected($organization->spi_district_code === $kawasan->value)>
                                                    {{ $kawasan->label() }} ({{ $kawasan->value }})
                                                </option>
                                            @endforeach
                                        </select>

                                        @unless ($organization->spi_district_code)
                                            <span class="text-xs font-medium text-amber-600">⚠ Pilih kawasan untuk sync</span>
                                        @endunless
                                    @endif
                                </div>
                            </td>
                            <td class="px-6 py-4 text-sm">
                                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset {{ $organization->status === App\Enums\OrganizationStatus::Active ? 'bg-emerald-50 text-emerald-700 ring-emerald-600/20' : 'bg-rose-50 text-rose-700 ring-rose-600/20' }}">
                                    {{ ucfirst($organization->status->value) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right text-sm font-medium">
                                <button
                                    wire:click="toggleStatus({{ $organization->id }})"
                                    class="{{ $organization->status === App\Enums\OrganizationStatus::Active ? 'text-rose-600 hover:text-rose-700' : 'text-emerald-600 hover:text-emerald-700' }}"
                                >
                                    {{ $organization->status === App\Enums\OrganizationStatus::Active ? __('Suspend') : __('Activate') }}
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-6 py-8 text-center text-sm text-slate-500">{{ __('No organizations found.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">
        {{ $organizations->links() }}
    </div>

    {{-- Create Organization Modal --}}
    @if ($showModal)
        <div class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0">
            <div class="fixed inset-0 bg-slate-900/50" wire:click="closeModal"></div>

            <div class="relative mx-auto mb-6 transform overflow-hidden rounded-2xl bg-white shadow-xl transition-all sm:w-full sm:max-w-md">
                <form wire:submit="save" class="p-6 sm:p-8">
                    <h2 class="text-lg font-semibold text-slate-900">{{ __('New Organization') }}</h2>
                    <p class="mt-1 text-sm text-slate-500">{{ __('Creates the organization with default branch, space types, and an admin account.') }}</p>

                    <div class="mt-4">
                        <x-input-label for="organization_name" :value="__('Organization name')" />
                        <x-text-input wire:model="organization_name" id="organization_name" type="text" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('organization_name')" class="mt-2" />
                    </div>

                    <div class="mt-4">
                        <x-input-label for="admin_name" :value="__('Admin name')" />
                        <x-text-input wire:model="admin_name" id="admin_name" type="text" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('admin_name')" class="mt-2" />
                    </div>

                    <div class="mt-4">
                        <x-input-label for="admin_email" :value="__('Admin email')" />
                        <x-text-input wire:model="admin_email" id="admin_email" type="email" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('admin_email')" class="mt-2" />
                    </div>

                    <div class="mt-4">
                        <x-input-label for="admin_password" :value="__('Admin password')" />
                        <x-text-input wire:model="admin_password" id="admin_password" type="text" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('admin_password')" class="mt-2" />
                    </div>

                    <div class="mt-6 flex justify-end gap-3">
                        <x-secondary-button type="button" wire:click="closeModal">{{ __('Cancel') }}</x-secondary-button>
                        <x-primary-button type="submit">{{ __('Create') }}</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
