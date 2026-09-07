<div>
    <div
        x-data="{ saved: false }"
        x-on:settings-saved.window="saved = true; setTimeout(() => saved = false, 3000)"
    >
        <div x-show="saved" x-transition class="mb-4 rounded-lg bg-emerald-50 p-3 text-sm text-emerald-700 ring-1 ring-inset ring-emerald-600/20">
            {{ __('Payment settings saved successfully.') }}
        </div>

        <form wire:submit="save" class="space-y-6">
            <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-base font-semibold text-slate-900">{{ __('CHIP') }}</h3>
                        <p class="mt-1 text-sm text-slate-500">
                            {{ __('Accept online card/e-wallet payments. Fees are paid directly into your own CHIP account — connect it below.') }}
                        </p>
                    </div>
                    <label class="flex shrink-0 items-center gap-2">
                        <input type="checkbox" wire:model.live="paymentGatewayEnabled" class="rounded border-slate-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                        <span class="text-sm font-medium text-slate-700">{{ __('Enabled') }}</span>
                    </label>
                </div>

                @if ($paymentGatewayEnabled)
                    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <x-input-label for="chipBrandId" :value="__('Brand ID')" />
                            <x-text-input wire:model="chipBrandId" id="chipBrandId" type="text" class="mt-1 block w-full" />
                            <p class="mt-1 text-xs text-slate-400">{{ __('From portal.chip-in.asia/collect/developers/brands') }}</p>
                            <x-input-error :messages="$errors->get('chipBrandId')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="chipSecretKey" :value="__('Secret Key')" />
                            <x-text-input wire:model="chipSecretKey" id="chipSecretKey" type="password" class="mt-1 block w-full"
                                :placeholder="$hasStoredChipSecretKey ? __('•••••••••••••••• (leave blank to keep)') : __('From portal.chip-in.asia/collect/developers/api-keys')" />
                            @if ($hasStoredChipSecretKey)
                                <p class="mt-1 text-xs text-emerald-600">{{ __('A secret key is already saved. Enter a new one only to replace it.') }}</p>
                            @endif
                            <x-input-error :messages="$errors->get('chipSecretKey')" class="mt-2" />
                        </div>
                    </div>
                @endif
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-base font-semibold text-slate-900">{{ __('Bank Transfer') }}</h3>
                        <p class="mt-1 text-sm text-slate-500">
                            {{ __('Show your bank details on the registration form so registrants can transfer manually and upload a receipt for you to review.') }}
                        </p>
                    </div>
                    <label class="flex shrink-0 items-center gap-2">
                        <input type="checkbox" wire:model.live="bankTransferEnabled" class="rounded border-slate-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                        <span class="text-sm font-medium text-slate-700">{{ __('Enabled') }}</span>
                    </label>
                </div>

                @if ($bankTransferEnabled)
                    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div>
                            <x-input-label for="bankName" :value="__('Bank Name')" />
                            <x-text-input wire:model="bankName" id="bankName" type="text" class="mt-1 block w-full" />
                            <x-input-error :messages="$errors->get('bankName')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="bankAccountNumber" :value="__('Account Number')" />
                            <x-text-input wire:model="bankAccountNumber" id="bankAccountNumber" type="text" class="mt-1 block w-full" />
                            <x-input-error :messages="$errors->get('bankAccountNumber')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="bankAccountHolder" :value="__('Account Holder Name')" />
                            <x-text-input wire:model="bankAccountHolder" id="bankAccountHolder" type="text" class="mt-1 block w-full" />
                            <x-input-error :messages="$errors->get('bankAccountHolder')" class="mt-2" />
                        </div>
                    </div>
                @endif
            </div>

            <div class="flex justify-end">
                <x-primary-button type="submit">{{ __('Save Payment Settings') }}</x-primary-button>
            </div>
        </form>
    </div>
</div>
