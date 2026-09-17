<div>
    @if ($step === 'verify')
        <form wire:submit="submit" class="space-y-4">
            <div>
                <x-input-label for="ic_number" :value="__('IC Number (MyKad)')" />
                <x-text-input wire:model="icNumber" id="ic_number" type="text" class="mt-1 block w-full" placeholder="{{ __('e.g. 901231145566') }}" autofocus />
                <x-input-error :messages="$errors->get('icNumber')" class="mt-2" />
            </div>

            <x-primary-button type="submit" class="w-full justify-center">{{ __('Check In') }}</x-primary-button>
        </form>
    @elseif ($step === 'success')
        <div class="rounded-xl bg-emerald-50 p-6 text-center ring-1 ring-inset ring-emerald-600/20">
            <p class="text-lg font-semibold text-emerald-800">{{ __('Checked in!') }}</p>
            <p class="mt-1 text-sm text-emerald-700">{{ $matchedName }} ({{ $matchedPosition }})</p>
        </div>
    @elseif ($step === 'already')
        <div class="rounded-xl bg-indigo-50 p-6 text-center ring-1 ring-inset ring-indigo-600/20">
            <p class="text-lg font-semibold text-indigo-800">{{ __('You\'re already checked in.') }}</p>
            <p class="mt-1 text-sm text-indigo-700">{{ $matchedName }} ({{ $matchedPosition }})</p>
        </div>
    @elseif ($step === 'not_found')
        <div class="rounded-xl bg-red-50 p-6 text-center ring-1 ring-inset ring-red-600/20">
            <p class="text-sm text-red-700">{{ __('We couldn\'t recognize that IC number for this meeting. Please double-check it or contact the meeting organizer.') }}</p>
        </div>
        <button type="button" wire:click="$set('step', 'verify')" class="mt-3 text-sm font-medium text-indigo-600 hover:text-indigo-700">{{ __('Try again') }}</button>
    @endif
</div>
