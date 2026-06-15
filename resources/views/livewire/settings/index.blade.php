<div>
    <div
        x-data="{ saved: false }"
        x-on:settings-saved.window="saved = true; setTimeout(() => saved = false, 3000)"
    >
        <div x-show="saved" x-transition class="mb-4 rounded-lg bg-emerald-50 p-3 text-sm text-emerald-700 ring-1 ring-inset ring-emerald-600/20">
            {{ __('Settings saved successfully.') }}
        </div>

        <form wire:submit="save" class="space-y-6">
            <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="text-base font-semibold text-slate-900">{{ __('Organization Branding') }}</h3>
                <p class="mt-1 text-sm text-slate-500">
                    {{ __('This logo and name are shown on the public booking page header.') }}
                </p>

                <div class="mt-4 max-w-sm">
                    <x-input-label for="organizationName" :value="__('Organization Name')" />
                    <x-text-input wire:model="organizationName" id="organizationName" type="text" class="mt-1 block w-full" :placeholder="config('app.name', 'Letz Manage')" />
                    <x-input-error :messages="$errors->get('organizationName')" class="mt-2" />
                </div>

                <div class="mt-4">
                    <x-input-label :value="__('Logo')" />

                    <div class="mt-2 flex items-center gap-4">
                        @if ($organizationLogo)
                            <img src="{{ $organizationLogo->temporaryUrl() }}" alt="" class="h-12 w-12 rounded-xl object-cover ring-1 ring-slate-200">
                        @elseif ($existingLogoPath)
                            <img src="{{ \Illuminate\Support\Facades\Storage::url($existingLogoPath) }}" alt="" class="h-12 w-12 rounded-xl object-cover ring-1 ring-slate-200">
                        @else
                            <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-600 to-violet-600 text-base font-bold text-white shadow-sm shadow-indigo-200">
                                L
                            </div>
                        @endif

                        <input type="file" wire:model="organizationLogo" accept="image/*" class="block text-sm text-slate-600">
                    </div>

                    <div wire:loading wire:target="organizationLogo" class="mt-1 text-xs text-slate-400">{{ __('Uploading...') }}</div>
                    <x-input-error :messages="$errors->get('organizationLogo')" class="mt-2" />

                    @if ($existingLogoPath && ! $organizationLogo)
                        <label class="mt-2 flex items-center gap-2 text-sm text-slate-600">
                            <input type="checkbox" wire:model="removeLogo" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                            {{ __('Remove current logo') }}
                        </label>
                    @endif
                </div>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="text-base font-semibold text-slate-900">{{ __('Booking Approval Mode') }}</h3>
                <p class="mt-1 text-sm text-slate-500">
                    {{ __('Choose whether new bookings are approved automatically or require manual approval by a manager or admin.') }}
                </p>

                <div class="mt-4 max-w-xs">
                    <x-input-label for="globalMode" :value="__('Global Default')" />
                    <select wire:model="globalMode" id="globalMode" class="mt-1 block w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @foreach ($modes as $mode)
                            <option value="{{ $mode->value }}">{{ ucfirst($mode->value) }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('globalMode')" class="mt-2" />
                </div>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="text-base font-semibold text-slate-900">{{ __('Per-Branch Overrides') }}</h3>
                <p class="mt-1 text-sm text-slate-500">
                    {{ __('Optionally override the approval mode for individual branches. Leave as "Use global default" to inherit the setting above.') }}
                </p>

                <div class="mt-4 divide-y divide-slate-100">
                    @foreach ($branches as $branch)
                        <div class="flex items-center justify-between gap-4 py-3">
                            <span class="text-sm font-medium text-slate-900">{{ $branch->name }}</span>
                            <select wire:model="branchModes.{{ $branch->id }}" class="block w-48 rounded-lg border-slate-200 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="default">{{ __('Use global default') }}</option>
                                @foreach ($modes as $mode)
                                    <option value="{{ $mode->value }}">{{ ucfirst($mode->value) }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="text-base font-semibold text-slate-900">{{ __('Approval Email Additional Info') }}</h3>
                <p class="mt-1 text-sm text-slate-500">
                    {{ __('This text is appended to the approval email/confirmation sent to the requester whenever a booking is approved (e.g. parking, access instructions, contact details).') }}
                </p>

                <div class="mt-4">
                    <x-input-label for="globalApprovalNote" :value="__('Global Default')" />
                    <textarea wire:model="globalApprovalNote" id="globalApprovalNote" rows="3" class="mt-1 block w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="{{ __('e.g. Please check in at the front desk and mention your booking reference.') }}"></textarea>
                    <x-input-error :messages="$errors->get('globalApprovalNote')" class="mt-2" />
                </div>

                <div class="mt-6 divide-y divide-slate-100">
                    @foreach ($branches as $branch)
                        <div class="py-3">
                            <x-input-label :for="'branchApprovalNotes-'.$branch->id" :value="$branch->name" />
                            <textarea wire:model="branchApprovalNotes.{{ $branch->id }}" id="branchApprovalNotes-{{ $branch->id }}" rows="2" class="mt-1 block w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="{{ __('Leave blank to use the global default above.') }}"></textarea>
                            <x-input-error :messages="$errors->get('branchApprovalNotes.'.$branch->id)" class="mt-2" />
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="flex justify-end">
                <x-primary-button type="submit">{{ __('Save Settings') }}</x-primary-button>
            </div>
        </form>

        @can('viewAny', App\Models\OfficeSpaceType::class)
            <div class="mt-8 border-t border-slate-200 pt-8">
                <livewire:office-space-types.index />
            </div>
        @endcan
    </div>
</div>
