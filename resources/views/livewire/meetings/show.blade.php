<div @if ($meeting->isProcessing()) wire:poll.10s @endif>
    @php
        $statusColors = [
            'pending' => 'bg-slate-100 text-slate-600 ring-slate-500/10',
            'uploading' => 'bg-amber-50 text-amber-700 ring-amber-600/20',
            'transcribing' => 'bg-amber-50 text-amber-700 ring-amber-600/20',
            'summarizing' => 'bg-amber-50 text-amber-700 ring-amber-600/20',
            'ready' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
            'failed' => 'bg-red-50 text-red-700 ring-red-600/20',
        ];
        $steps = ['uploading' => 'Uploading', 'transcribing' => 'Transcribing', 'summarizing' => 'Summarizing', 'ready' => 'Ready'];
        $currentStepIndex = array_search($meeting->status->value, array_keys($steps));
    @endphp

    <div class="mb-4 flex items-center justify-between">
        <a href="{{ route('meetings.index') }}" wire:navigate class="text-sm font-medium text-indigo-600 hover:text-indigo-700">
            {{ __('← Back to Meetings') }}
        </a>
        @can('delete', $meeting)
            <x-danger-button wire:click="delete" wire:confirm="{{ __('Delete this meeting? This cannot be undone.') }}">
                {{ __('Delete') }}
            </x-danger-button>
        @endcan
    </div>

    <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h2 class="text-lg font-semibold text-slate-900">{{ $meeting->title }}</h2>
                @if ($meeting->event)
                    <p class="mt-0.5 text-sm text-slate-500">{{ __('Linked to') }}: {{ $meeting->event->title }}</p>
                @endif
            </div>
            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset {{ $statusColors[$meeting->status->value] }}">
                {{ ucfirst($meeting->status->value) }}
            </span>
        </div>

        @if ($meeting->status->value === 'failed')
            <div class="mt-4 rounded-lg bg-red-50 p-3 text-sm text-red-700 ring-1 ring-inset ring-red-600/10">
                {{ $meeting->failure_reason ?: __('Something went wrong.') }}
            </div>
        @elseif ($currentStepIndex !== false)
            <div class="mt-6 flex items-center gap-2">
                @foreach ($steps as $key => $label)
                    @php $stepIndex = array_search($key, array_keys($steps)); @endphp
                    <div class="flex flex-1 items-center gap-2">
                        <div class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-xs font-semibold {{ $stepIndex <= $currentStepIndex ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-400' }}">
                            {{ $stepIndex + 1 }}
                        </div>
                        <span class="text-xs {{ $stepIndex <= $currentStepIndex ? 'text-slate-900' : 'text-slate-400' }}">{{ __($label) }}</span>
                        @if (! $loop->last)
                            <div class="h-px flex-1 {{ $stepIndex < $currentStepIndex ? 'bg-indigo-600' : 'bg-slate-200' }}"></div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif

        @if ($meeting->archivedFile)
            <p class="mt-4 text-sm text-slate-500">
                {{ __('Original recording archived:') }}
                <a href="{{ $meeting->archivedFile->driveViewUrl() }}" target="_blank" class="font-medium text-indigo-600 hover:text-indigo-700">{{ __('View in Drive →') }}</a>
            </p>
        @endif
    </div>

    @if ($meeting->minutes)
        <div class="mt-6 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex items-center justify-between">
                <h3 class="text-sm font-semibold text-slate-900">{{ __('Minutes of Meeting') }}</h3>
                <div class="flex items-center gap-3">
                    @if ($meeting->minutes_ms)
                        <div class="flex gap-1 rounded-lg bg-slate-100 p-1">
                            <button type="button" wire:click="setLanguage('en')"
                                class="rounded-md px-2.5 py-1 text-xs font-medium transition {{ $language === 'en' ? 'bg-white text-indigo-700 shadow-sm' : 'text-slate-500 hover:text-slate-700' }}">
                                {{ __('English') }}
                            </button>
                            <button type="button" wire:click="setLanguage('ms')"
                                class="rounded-md px-2.5 py-1 text-xs font-medium transition {{ $language === 'ms' ? 'bg-white text-indigo-700 shadow-sm' : 'text-slate-500 hover:text-slate-700' }}">
                                {{ __('Bahasa Malaysia') }}
                            </button>
                        </div>
                    @endif
                    <button wire:click="downloadMinutes" class="text-sm font-medium text-indigo-600 hover:text-indigo-700">{{ __('Download') }}</button>
                </div>
            </div>
            <div class="mt-3 whitespace-pre-line text-sm text-slate-700">{{ $this->currentMinutes() }}</div>
        </div>
    @endif

    @if ($meeting->transcript)
        <details class="mt-6 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <summary class="cursor-pointer text-sm font-semibold text-slate-900">{{ __('Full Transcript') }}</summary>
            <div class="mt-3 flex justify-end">
                <button wire:click="downloadTranscript" class="text-sm font-medium text-indigo-600 hover:text-indigo-700">{{ __('Download') }}</button>
            </div>
            <div class="mt-3 whitespace-pre-line text-sm text-slate-600">{{ $meeting->transcript }}</div>
        </details>
    @endif
</div>
