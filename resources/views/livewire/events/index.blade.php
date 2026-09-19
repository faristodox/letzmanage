<div>
    <div class="mb-4 flex items-center justify-end gap-3">
        @can('create', App\Models\Event::class)
            <x-secondary-button wire:click="createFromPoster">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-4 w-4">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 8.25H7.5a2.25 2.25 0 00-2.25 2.25v9a2.25 2.25 0 002.25 2.25h9a2.25 2.25 0 002.25-2.25v-9a2.25 2.25 0 00-2.25-2.25H15m0-3-3-3m0 0-3 3m3-3V15" />
                </svg>
                {{ __('Create from Poster') }}
            </x-secondary-button>
            <x-primary-button wire:click="create">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-4 w-4">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                {{ __('New Event') }}
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
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Type') }}</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Status') }}</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Responses') }}</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Created') }}</th>
                    <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 bg-white">
                @forelse ($events as $event)
                    @php
                        $registrationForm = $event->registrationForm;
                        $feedbackForm = $event->feedbackForm;
                        $isCommitteeMeeting = $event->type->value === 'committee_meeting';
                    @endphp
                    <tr wire:key="event-{{ $event->id }}" class="hover:bg-slate-50">
                        <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-slate-900">{{ $event->title }}</td>
                        <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-600">{{ $isCommitteeMeeting ? __('Committee Meeting') : __('Event') }}</td>
                        <td class="whitespace-nowrap px-6 py-4 text-sm">
                            @php
                                $statusColors = [
                                    'draft' => 'bg-slate-100 text-slate-600 ring-slate-500/10',
                                    'published' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
                                    'closed' => 'bg-amber-50 text-amber-700 ring-amber-600/20',
                                ];
                            @endphp
                            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset {{ $statusColors[$event->status->value] }}">
                                {{ ucfirst($event->status->value) }}
                            </span>
                        </td>
                        <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-600">{{ $isCommitteeMeeting ? '—' : ($registrationForm?->responses_count ?? 0) }}</td>
                        <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-500">{{ $event->created_at->format('d M Y') }}</td>
                        <td class="whitespace-nowrap px-6 py-4 text-right text-sm font-medium space-x-3">
                            @if ($registrationForm)
                                <a href="{{ route('event-forms.builder', $registrationForm) }}" wire:navigate class="text-indigo-600 hover:text-indigo-700">{{ __('Edit') }}</a>
                                @unless ($isCommitteeMeeting)
                                    <a href="{{ route('event-forms.builder', $registrationForm) }}#form-settings" wire:navigate class="text-indigo-600 hover:text-indigo-700">{{ __('Registration') }}</a>
                                    @if ($feedbackForm)
                                        <a href="{{ route('event-forms.builder', $feedbackForm) }}" wire:navigate class="text-indigo-600 hover:text-indigo-700">{{ __('Feedback') }}</a>
                                    @else
                                        <button type="button" wire:click="createFeedbackForm({{ $event->id }})" class="text-indigo-600 hover:text-indigo-700">{{ __('Feedback') }}</button>
                                    @endif
                                @endunless
                            @endif
                            @if (! $isCommitteeMeeting)
                                @can('viewFinances', $event)
                                    <a href="{{ route('events.finances', $event) }}" wire:navigate class="text-indigo-600 hover:text-indigo-700">{{ __('Financial') }}</a>
                                    <a href="{{ route('events.report', $event) }}" wire:navigate class="text-indigo-600 hover:text-indigo-700">{{ __('Report') }}</a>
                                @endcan
                            @endif
                            @can('viewAny', App\Models\Meeting::class)
                                <a href="{{ route('meetings.index', ['event' => $event->id]) }}" wire:navigate class="text-indigo-600 hover:text-indigo-700">{{ __('AI MoM') }}</a>
                            @endcan
                            @can('delete', $event)
                                <button wire:click="confirmDelete({{ $event->id }})" class="text-red-600 hover:text-red-700">{{ __('Delete') }}</button>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-6 py-8 text-center text-sm text-slate-500">{{ __('No events yet.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </div>

    <div class="mt-4">
        {{ $events->links() }}
    </div>

    <!-- Create Modal -->
    @if ($showModal)
        <div class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0">
            <div class="fixed inset-0 bg-slate-900/50" wire:click="closeModal"></div>

            <div class="relative mx-auto mb-6 transform overflow-hidden rounded-2xl bg-white shadow-xl transition-all sm:w-full sm:max-w-lg">
                <form wire:submit="save" class="p-6 sm:p-8">
                    <h2 class="text-lg font-semibold text-slate-900">{{ __('New Event') }}</h2>
                    <p class="mt-1 text-sm text-slate-500">
                        {{ $type === 'committee_meeting' ? __("You'll set up attendance check-in on the next screen.") : __("You'll add registration fields on the next screen.") }}
                    </p>

                    @if ($canCreateCommitteeMeeting)
                        <div class="mt-4 flex gap-1 rounded-lg bg-slate-100 p-1">
                            <button type="button" wire:click="$set('type', 'event')"
                                class="flex-1 rounded-md px-3 py-1.5 text-sm font-medium transition {{ $type === 'event' ? 'bg-white text-indigo-700 shadow-sm' : 'text-slate-500 hover:text-slate-700' }}">
                                {{ __('Event') }}
                            </button>
                            <button type="button" wire:click="$set('type', 'committee_meeting')"
                                class="flex-1 rounded-md px-3 py-1.5 text-sm font-medium transition {{ $type === 'committee_meeting' ? 'bg-white text-indigo-700 shadow-sm' : 'text-slate-500 hover:text-slate-700' }}">
                                {{ __('Committee Meeting') }}
                            </button>
                        </div>
                    @endif

                    <div class="mt-4">
                        <x-input-label for="title" :value="__('Title')" />
                        <x-text-input wire:model="title" id="title" type="text" class="mt-1 block w-full" placeholder="{{ __('e.g. Annual Dinner 2026') }}" autofocus />
                        <x-input-error :messages="$errors->get('title')" class="mt-2" />
                    </div>

                    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <x-input-label for="startDate" :value="__('Event Date')" />
                            <x-text-input wire:model="startDate" id="startDate" type="date" class="mt-1 block w-full" />
                            <x-input-error :messages="$errors->get('startDate')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="startTime" :value="__('Start Time (optional)')" />
                            <x-text-input wire:model="startTime" id="startTime" type="time" class="mt-1 block w-full" />
                            <x-input-error :messages="$errors->get('startTime')" class="mt-2" />
                        </div>
                    </div>

                    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <x-input-label for="endDate" :value="__('End Date (optional)')" />
                            <x-text-input wire:model="endDate" id="endDate" type="date" class="mt-1 block w-full" />
                            <p class="mt-1 text-xs text-slate-400">{{ __('Leave blank for a one-day event.') }}</p>
                            <x-input-error :messages="$errors->get('endDate')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="endTime" :value="__('End Time (optional)')" />
                            <x-text-input wire:model="endTime" id="endTime" type="time" class="mt-1 block w-full" />
                            <x-input-error :messages="$errors->get('endTime')" class="mt-2" />
                        </div>
                    </div>

                    <div class="mt-4">
                        <x-input-label for="location" :value="__('Location (optional)')" />
                        <x-text-input wire:model="location" id="location" type="text" class="mt-1 block w-full" placeholder="{{ __('e.g. Dewan Serbaguna, Kuala Lumpur') }}" />
                        <x-input-error :messages="$errors->get('location')" class="mt-2" />
                    </div>

                    <div class="mt-6 flex justify-end gap-3">
                        <x-secondary-button type="button" wire:click="closeModal">{{ __('Cancel') }}</x-secondary-button>
                        <x-primary-button type="submit">{{ __('Create & Continue') }}</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- Create from Poster Modal -->
    @if ($showPosterModal)
        <div class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0">
            <div class="fixed inset-0 bg-slate-900/50" wire:click="closePosterModal"></div>

            <div class="relative mx-auto mb-6 transform overflow-hidden rounded-2xl bg-white shadow-xl transition-all sm:w-full sm:max-w-lg">
                <div class="p-6 sm:p-8">
                    @if ($posterStep === 'upload')
                        <h2 class="text-lg font-semibold text-slate-900">{{ __('Create Event from Poster') }}</h2>
                        <p class="mt-1 text-sm text-slate-500">
                            {{ __("Upload a poster image and/or paste the event message — we'll extract the details for you to review.") }}
                        </p>

                        @if ($extractionError)
                            <div class="mt-4 rounded-lg bg-red-50 p-3 text-sm text-red-700 ring-1 ring-inset ring-red-600/10">
                                {{ $extractionError }}
                            </div>
                        @endif

                        <div class="mt-4">
                            <x-input-label for="posterImage" :value="__('Poster Image (optional)')" />
                            @if ($posterImage)
                                <img src="{{ $posterImage->temporaryUrl() }}" alt="{{ __('Preview') }}" class="mt-2 max-h-48 w-full max-w-xs rounded-lg object-cover ring-1 ring-slate-200">
                            @endif
                            <input wire:model="posterImage" id="posterImage" type="file" accept="image/*" class="mt-2 block w-full text-sm text-slate-500 file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-indigo-700 hover:file:bg-indigo-100">
                            <div wire:loading wire:target="posterImage" class="mt-1 text-xs text-slate-500">{{ __('Uploading...') }}</div>
                            <x-input-error :messages="$errors->get('posterImage')" class="mt-2" />
                        </div>

                        <div class="mt-4">
                            <x-input-label for="posterMessage" :value="__('Event Message (optional)')" />
                            <textarea wire:model="posterMessage" id="posterMessage" rows="4" class="mt-1 block w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="{{ __('Paste the WhatsApp/promotional message here...') }}"></textarea>
                            <x-input-error :messages="$errors->get('posterMessage')" class="mt-2" />
                        </div>

                        <div class="mt-6 flex justify-end gap-3">
                            <x-secondary-button type="button" wire:click="closePosterModal">{{ __('Cancel') }}</x-secondary-button>
                            <x-primary-button type="button" wire:click="extractFromPoster" wire:loading.attr="disabled" wire:target="extractFromPoster">
                                <span wire:loading.remove wire:target="extractFromPoster">{{ __('Extract Details') }}</span>
                                <span wire:loading wire:target="extractFromPoster">{{ __('Extracting...') }}</span>
                            </x-primary-button>
                        </div>
                    @else
                        <h2 class="text-lg font-semibold text-slate-900">{{ __('Review Event Details') }}</h2>
                        <p class="mt-1 text-sm text-slate-500">{{ __("Double check before creating — AI extraction isn't perfect.") }}</p>

                        @if ($posterImage)
                            <img src="{{ $posterImage->temporaryUrl() }}" alt="{{ __('Poster') }}" class="mt-4 max-h-48 w-full max-w-xs rounded-lg object-cover ring-1 ring-slate-200">
                        @endif

                        <form wire:submit="saveFromPoster" class="mt-4 space-y-4">
                            <div>
                                <x-input-label for="poster_title" :value="__('Title')" />
                                <x-text-input wire:model="title" id="poster_title" type="text" class="mt-1 block w-full" autofocus />
                                <x-input-error :messages="$errors->get('title')" class="mt-2" />
                            </div>

                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <x-input-label for="poster_startDate" :value="__('Event Date')" />
                                    <x-text-input wire:model="startDate" id="poster_startDate" type="date" class="mt-1 block w-full" />
                                    <x-input-error :messages="$errors->get('startDate')" class="mt-2" />
                                </div>
                                <div>
                                    <x-input-label for="poster_startTime" :value="__('Start Time (optional)')" />
                                    <x-text-input wire:model="startTime" id="poster_startTime" type="time" class="mt-1 block w-full" />
                                    <x-input-error :messages="$errors->get('startTime')" class="mt-2" />
                                </div>
                            </div>

                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <x-input-label for="poster_endDate" :value="__('End Date (optional)')" />
                                    <x-text-input wire:model="endDate" id="poster_endDate" type="date" class="mt-1 block w-full" />
                                    <x-input-error :messages="$errors->get('endDate')" class="mt-2" />
                                </div>
                                <div>
                                    <x-input-label for="poster_endTime" :value="__('End Time (optional)')" />
                                    <x-text-input wire:model="endTime" id="poster_endTime" type="time" class="mt-1 block w-full" />
                                    <x-input-error :messages="$errors->get('endTime')" class="mt-2" />
                                </div>
                            </div>

                            <div>
                                <x-input-label for="poster_location" :value="__('Location (optional)')" />
                                <x-text-input wire:model="location" id="poster_location" type="text" class="mt-1 block w-full" />
                                <x-input-error :messages="$errors->get('location')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="poster_description" :value="__('Description (optional)')" />
                                <textarea wire:model="description" id="poster_description" rows="3" class="mt-1 block w-full rounded-lg border-slate-200 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                                <x-input-error :messages="$errors->get('description')" class="mt-2" />
                            </div>

                            <div class="mt-6 flex justify-between gap-3">
                                <x-secondary-button type="button" wire:click="backToPoster">{{ __('Back') }}</x-secondary-button>
                                <div class="flex gap-3">
                                    <x-secondary-button type="button" wire:click="closePosterModal">{{ __('Cancel') }}</x-secondary-button>
                                    <x-primary-button type="submit">{{ __('Create Event') }}</x-primary-button>
                                </div>
                            </div>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    @endif

    <!-- Delete Confirmation Modal -->
    @if ($confirmingDeleteId !== null)
        <div class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0">
            <div class="fixed inset-0 bg-slate-900/50" wire:click="closeDeleteModal"></div>

            <div class="relative mx-auto mb-6 transform overflow-hidden rounded-2xl bg-white shadow-xl transition-all sm:w-full sm:max-w-md">
                <div class="p-6 sm:p-8">
                    <h2 class="text-lg font-semibold text-slate-900">{{ __('Delete Event') }}</h2>
                    <p class="mt-2 text-sm text-slate-500">
                        {{ __('Are you sure you want to delete this event? This will also permanently delete its registration form and all of its responses.') }}
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
