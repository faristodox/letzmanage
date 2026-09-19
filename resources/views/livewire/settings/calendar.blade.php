<div>
    <div
        x-data="{ saved: false }"
        x-on:settings-saved.window="saved = true; setTimeout(() => saved = false, 3000)"
    >
        @if (session('status'))
            <div class="mb-4 rounded-lg bg-emerald-50 p-3 text-sm text-emerald-700 ring-1 ring-inset ring-emerald-600/20">
                {{ session('status') }}
            </div>
        @endif

        <div x-show="saved" x-transition class="mb-4 rounded-lg bg-emerald-50 p-3 text-sm text-emerald-700 ring-1 ring-inset ring-emerald-600/20">
            {{ __('Settings saved successfully.') }}
        </div>

        <div class="mb-6 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <h3 class="text-base font-semibold text-slate-900">{{ __('Connected Google Account') }}</h3>
            <p class="mt-1 text-sm text-slate-500">
                {{ __('One Google account, shared by both Calendar Sync (in Shared mode) and File Archive below.') }}
            </p>

            <div class="mt-4 rounded-lg bg-slate-50 p-4">
                @if ($isConnected)
                    <div class="flex items-center justify-between gap-4">
                        <p class="text-sm text-slate-700">
                            {{ __('Connected as') }} <span class="font-medium text-slate-900">{{ $connectedEmail }}</span>
                        </p>
                        <button type="button" wire:click="disconnect" wire:confirm="{{ __('Disconnect this Google account? This also turns off Calendar Sync and File Archive access.') }}" class="shrink-0 text-sm font-medium text-red-600 hover:text-red-700">
                            {{ __('Disconnect') }}
                        </button>
                    </div>
                @else
                    <p class="text-sm text-slate-600">{{ __("Connect a Google account to enable Calendar Sync's Shared mode and/or File Archive.") }}</p>
                    <a href="{{ route('settings.calendar.google.connect') }}" class="mt-3 inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-500">
                        {{ __('Connect Google Account') }}
                    </a>
                @endif
            </div>
        </div>

        <form wire:submit="save" class="space-y-6">
            <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="text-base font-semibold text-slate-900">{{ __('Google Calendar Sync') }}</h3>
                <p class="mt-1 text-sm text-slate-500">
                    {{ __('Push published events to Google Calendar automatically. Choose who owns the connected Google account.') }}
                </p>

                <div class="mt-4 space-y-3">
                    <label class="flex items-start gap-3 rounded-lg border border-slate-200 p-3 has-[:checked]:border-indigo-400 has-[:checked]:bg-indigo-50">
                        <input type="radio" wire:model.live="syncMode" value="disabled" class="mt-0.5 text-indigo-600 focus:ring-indigo-500">
                        <span>
                            <span class="block text-sm font-medium text-slate-900">{{ __('Disabled') }}</span>
                            <span class="block text-sm text-slate-500">{{ __("Don't sync events to Google Calendar.") }}</span>
                        </span>
                    </label>

                    <label class="flex items-start gap-3 rounded-lg border border-slate-200 p-3 has-[:checked]:border-indigo-400 has-[:checked]:bg-indigo-50">
                        <input type="radio" wire:model.live="syncMode" value="shared" class="mt-0.5 text-indigo-600 focus:ring-indigo-500">
                        <span>
                            <span class="block text-sm font-medium text-slate-900">{{ __('One shared organization account') }}</span>
                            <span class="block text-sm text-slate-500">{{ __('Uses the connected Google account above. Every published event, from any staff member, syncs onto that one calendar.') }}</span>
                        </span>
                    </label>

                    <label class="flex items-start gap-3 rounded-lg border border-slate-200 p-3 has-[:checked]:border-indigo-400 has-[:checked]:bg-indigo-50">
                        <input type="radio" wire:model.live="syncMode" value="individual" class="mt-0.5 text-indigo-600 focus:ring-indigo-500">
                        <span>
                            <span class="block text-sm font-medium text-slate-900">{{ __('Individual staff accounts') }}</span>
                            <span class="block text-sm text-slate-500">{{ __("Each staff member connects their own Google account from their Profile page. The events they created sync, and only once they've connected.") }}</span>
                        </span>
                    </label>
                </div>
                <x-input-error :messages="$errors->get('syncMode')" class="mt-2" />
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-base font-semibold text-slate-900">{{ __('File Archive') }}</h3>
                        <p class="mt-1 text-sm text-slate-500">
                            {{ __('Let staff upload files that are stored in the connected Google account above, browsable from the Archive page.') }}
                        </p>
                    </div>
                    <label class="flex shrink-0 items-center gap-2">
                        <input type="checkbox" wire:model="archiveEnabled" class="rounded border-slate-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                        <span class="text-sm font-medium text-slate-700">{{ __('Enabled') }}</span>
                    </label>
                </div>
            </div>

            <div class="flex justify-end">
                <x-primary-button type="submit">{{ __('Save Settings') }}</x-primary-button>
            </div>
        </form>

        <div class="mt-6 rounded-xl border border-slate-200 bg-white p-6 shadow-sm" x-data="{ source: @entangle('holidaySource') }">
            <h3 class="text-base font-semibold text-slate-900">{{ __('Holiday Calendar') }}</h3>
            <p class="mt-1 text-sm text-slate-500">
                {{ __('Choose where public (and optionally school) holidays shown on the Calendar page come from.') }}
            </p>

            <form wire:submit="syncHolidays" class="mt-4 space-y-4">
                <div>
                    <x-input-label for="holidaySource" :value="__('Source')" />
                    <select wire:model="holidaySource" x-model="source" id="holidaySource" class="mt-1 block w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @foreach (App\Enums\HolidaySource::cases() as $source)
                            <option value="{{ $source->value }}">{{ $source->label() }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('holidaySource')" class="mt-2" />
                </div>

                <div x-show="source === 'cutisekolah'">
                    <x-input-label for="holidayState" :value="__('State (for school holidays)')" />
                    <select wire:model="holidayState" id="holidayState" class="mt-1 block w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">{{ __('Select a state') }}</option>
                        @foreach (App\Enums\MalaysianState::cases() as $state)
                            <option value="{{ $state->value }}">{{ $state->label() }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('holidayState')" class="mt-2" />
                    <p class="mt-1 text-xs text-slate-400">{{ __('National public holidays are always included; the state only affects school holidays.') }}</p>
                </div>

                <div class="flex items-center justify-end gap-3">
                    <span wire:loading wire:target="syncHolidays" class="text-xs text-slate-400">{{ __('Syncing…') }}</span>
                    <x-primary-button type="submit" wire:loading.attr="disabled" wire:target="syncHolidays">
                        <span x-text="source === 'cutisekolah' ? '{{ __('Save & Sync') }}' : '{{ __('Save') }}'">{{ $holidaySource === 'cutisekolah' ? __('Save & Sync') : __('Save') }}</span>
                    </x-primary-button>
                </div>
            </form>
        </div>

        <div class="mt-6 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <h3 class="text-base font-semibold text-slate-900">{{ __('WANITA Calendar Sync') }}</h3>
            <p class="mt-1 text-sm text-slate-500">
                {{ __('Sync events from the Jawatankuasa WANITA committee\'s Google Sheet calendar onto the Calendar page. Only green and turquoise events are imported; each is tagged "(WANITA)".') }}
            </p>

            @unless ($isConnected)
                <p class="mt-4 text-sm text-amber-700">{{ __('Connect a Google account above first — the connected account needs access to the sheet.') }}</p>
            @else
                <form wire:submit="syncWanita" class="mt-4 space-y-4">
                    <div>
                        <x-input-label for="wanitaSheetUrl" :value="__('Google Sheet URL')" />
                        <x-text-input wire:model="wanitaSheetUrl" id="wanitaSheetUrl" type="text" class="mt-1 block w-full" placeholder="https://docs.google.com/spreadsheets/d/..." />
                        <x-input-error :messages="$errors->get('wanitaSheetUrl')" class="mt-2" />
                        <p class="mt-1 text-xs text-slate-400">{{ __('The connected Google account above must already have at least view access to this sheet.') }}</p>
                    </div>

                    <div class="flex items-center justify-end gap-3">
                        <span wire:loading wire:target="syncWanita" class="text-xs text-slate-400">{{ __('Syncing…') }}</span>
                        <x-primary-button type="submit" wire:loading.attr="disabled" wire:target="syncWanita">
                            {{ __('Save & Sync') }}
                        </x-primary-button>
                    </div>
                </form>
            @endunless
        </div>
    </div>
</div>
