@props(['field', 'wireModel'])

@switch($field->type->value)
    @case('textarea')
        <textarea wire:model="{{ $wireModel }}" rows="3" class="block w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
        @break

    @case('select')
        <select wire:model="{{ $wireModel }}" class="block w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            <option value="">{{ __('Select…') }}</option>
            @foreach ($field->options ?? [] as $option)
                <option value="{{ $option }}">{{ $option }}</option>
            @endforeach
        </select>
        @break

    @case('radio')
        <div class="space-y-2">
            @foreach ($field->options ?? [] as $option)
                <label class="flex items-center gap-2 text-sm text-slate-700">
                    <input type="radio" wire:model="{{ $wireModel }}" value="{{ $option }}" class="border-slate-300 text-indigo-600 focus:ring-indigo-500">
                    {{ $option }}
                </label>
            @endforeach
        </div>
        @break

    @case('checkbox')
        <div class="space-y-2">
            @foreach ($field->options ?? [] as $option)
                <label class="flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" wire:model="{{ $wireModel }}" value="{{ $option }}" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                    {{ $option }}
                </label>
            @endforeach
        </div>
        @break

    @case('number')
        <input type="number" wire:model="{{ $wireModel }}" class="block w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        @break

    @case('email')
        <input type="email" wire:model="{{ $wireModel }}" class="block w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        @break

    @case('date')
        <input type="date" wire:model="{{ $wireModel }}" class="block w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        @break

    @case('phone')
        <input type="tel" wire:model="{{ $wireModel }}" class="block w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        @break

    @default
        <input type="text" wire:model="{{ $wireModel }}" class="block w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
@endswitch
