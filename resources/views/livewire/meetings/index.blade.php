<div>
    <div class="mb-4 flex items-center justify-between">
        @if ($filteredEvent)
            <p class="text-sm text-slate-600">
                {{ __('Showing meetings for') }} <span class="font-medium text-slate-900">{{ $filteredEvent->title }}</span>
                <button type="button" wire:click="clearEventFilter" class="ml-2 font-medium text-indigo-600 hover:text-indigo-700">{{ __('Clear') }}</button>
            </p>
        @else
            <span></span>
        @endif

        @can('create', App\Models\Meeting::class)
            <x-primary-button wire:click="create">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-4 w-4">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                {{ __('New AI MoM') }}
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
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Title') }}</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Event') }}</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Status') }}</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Created By') }}</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Created') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 bg-white">
                @php
                    $statusColors = [
                        'pending' => 'bg-slate-100 text-slate-600 ring-slate-500/10',
                        'uploading' => 'bg-amber-50 text-amber-700 ring-amber-600/20',
                        'transcribing' => 'bg-amber-50 text-amber-700 ring-amber-600/20',
                        'summarizing' => 'bg-amber-50 text-amber-700 ring-amber-600/20',
                        'ready' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
                        'failed' => 'bg-red-50 text-red-700 ring-red-600/20',
                    ];
                @endphp
                @forelse ($meetings as $meeting)
                    <tr wire:key="meeting-{{ $meeting->id }}" class="hover:bg-slate-50">
                        <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-slate-900">
                            <a href="{{ route('meetings.show', $meeting) }}" wire:navigate class="hover:text-indigo-600">{{ $meeting->title }}</a>
                        </td>
                        <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-600">{{ $meeting->event?->title ?? '—' }}</td>
                        <td class="whitespace-nowrap px-6 py-4 text-sm">
                            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset {{ $statusColors[$meeting->status->value] }}">
                                {{ ucfirst($meeting->status->value) }}
                            </span>
                        </td>
                        <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-600">{{ $meeting->creator?->name ?? __('Unknown') }}</td>
                        <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-500">{{ $meeting->created_at->format('d M Y') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-6 py-8 text-center text-sm text-slate-500">{{ __('No meetings yet.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </div>

    <div class="mt-4">
        {{ $meetings->links() }}
    </div>

    <!-- Create Meeting Modal -->
    @if ($showModal)
        <div class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0">
            <div class="fixed inset-0 bg-slate-900/50" wire:click="closeModal"></div>

            <div class="relative mx-auto mb-6 transform overflow-hidden rounded-2xl bg-white shadow-xl transition-all sm:w-full sm:max-w-lg">
                <div class="p-6 sm:p-8">
                    <h2 class="text-lg font-semibold text-slate-900">{{ __('New AI MoM') }}</h2>

                    <div class="mt-4">
                        <x-input-label for="meeting_title" :value="__('Title')" />
                        <x-text-input wire:model="title" id="meeting_title" type="text" class="mt-1 block w-full" placeholder="{{ __('e.g. Usrah Session') }}" autofocus />
                        <x-input-error :messages="$errors->get('title')" class="mt-2" />
                    </div>

                    @if ($events->isNotEmpty())
                        <div class="mt-4">
                            <x-input-label for="meeting_event" :value="__('Link to Event (optional)')" />
                            <select wire:model="eventId" id="meeting_event" class="mt-1 block w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">{{ __('None') }}</option>
                                @foreach ($events as $event)
                                    <option value="{{ $event->id }}">{{ $event->title }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    <div class="mt-4 flex gap-1 rounded-lg bg-slate-100 p-1">
                        <button type="button" wire:click="$set('activeTab', 'record')"
                            class="flex-1 rounded-md px-3 py-1.5 text-sm font-medium transition {{ $activeTab === 'record' ? 'bg-white text-indigo-700 shadow-sm' : 'text-slate-500 hover:text-slate-700' }}">
                            {{ __('Record') }}
                        </button>
                        <button type="button" wire:click="$set('activeTab', 'upload')"
                            class="flex-1 rounded-md px-3 py-1.5 text-sm font-medium transition {{ $activeTab === 'upload' ? 'bg-white text-indigo-700 shadow-sm' : 'text-slate-500 hover:text-slate-700' }}">
                            {{ __('Upload') }}
                        </button>
                    </div>

                    <form wire:submit="save" class="mt-4 space-y-4">
                        @if ($activeTab === 'record')
                            <div x-data="meetingRecorder" class="rounded-lg border border-slate-200 p-4 text-center">
                                <p class="text-2xl font-mono font-semibold text-slate-900" x-text="formattedElapsed"></p>

                                <div class="mt-3 flex justify-center gap-2">
                                    <x-primary-button type="button" x-show="!recording && !uploading" x-on:click="start()">{{ __('Start Recording') }}</x-primary-button>
                                    <x-danger-button type="button" x-show="recording" x-on:click="stop()">{{ __('Stop Recording') }}</x-danger-button>
                                    <span x-show="uploading" class="text-sm text-slate-500">{{ __('Preparing recording...') }}</span>
                                </div>

                                <div x-show="hybridSupported && !recording && !uploading" class="mt-3 rounded-lg bg-slate-50 p-3 text-left">
                                    <label class="flex items-start gap-2 text-sm text-slate-700">
                                        <input type="checkbox" x-model="hybridMode" class="mt-0.5 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                                        <span>{{ __('Hybrid meeting — also capture an online meeting tab (Google Meet, Zoom, Teams)') }}</span>
                                    </label>
                                    <p x-show="hybridMode" class="mt-1.5 text-xs text-slate-400">
                                        {{ __('When you click Start Recording, a browser prompt will ask you to share a screen or tab — pick the tab running the online meeting and make sure "Share tab audio" is ticked.') }}
                                    </p>
                                </div>

                                <p class="mt-2 text-xs text-slate-400">{{ __('Works best in Chrome, Firefox, or Edge. Safari isn\'t supported for live recording — use the Upload tab instead.') }}</p>
                                <p x-show="error" x-text="error" class="mt-2 text-sm text-red-600"></p>
                                <p x-show="warning" x-text="warning" class="mt-2 text-sm text-amber-600"></p>

                                <p x-show="uploaded" class="mt-2 text-sm text-emerald-600">{{ __('Recording ready — click Create below.') }}</p>
                            </div>
                        @else
                            <div>
                                <x-input-label for="meeting_file" :value="__('Audio File')" />
                                <input wire:model="file" id="meeting_file" type="file" accept="audio/webm,audio/mp3,audio/mpeg,audio/wav,audio/ogg,audio/flac" class="mt-1 block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-indigo-700 hover:file:bg-indigo-100">
                                <div wire:loading wire:target="file" class="mt-1 text-xs text-slate-500">{{ __('Uploading...') }}</div>
                                <p class="mt-1 text-xs text-slate-400">{{ __('webm, mp3, wav, ogg, or flac — up to 240MB.') }}</p>
                            </div>
                        @endif

                        <x-input-error :messages="$errors->get('file')" class="mt-2" />
                        @if ($uploadError)
                            <p class="mt-2 text-sm text-red-600">{{ $uploadError }}</p>
                        @endif

                        <div class="mt-6 flex justify-end gap-3">
                            <x-secondary-button type="button" wire:click="closeModal">{{ __('Cancel') }}</x-secondary-button>
                            <x-primary-button type="submit">{{ __('Create') }}</x-primary-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
