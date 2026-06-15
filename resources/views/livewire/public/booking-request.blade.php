<div>
    @if ($submitted)
        <div class="rounded-2xl border border-emerald-100 bg-emerald-50 p-8 text-center">
            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-emerald-100 text-emerald-600">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                </svg>
            </div>
            @if ($autoApproved)
                <h2 class="mt-4 text-xl font-bold text-slate-900">Booking confirmed</h2>
                <p class="mt-2 text-sm text-slate-600 max-w-md mx-auto">
                    Thanks, {{ $guest_name }}. We've emailed a confirmation to {{ $guest_email }}.
                    Your booking is confirmed &mdash; we look forward to seeing you.
                </p>
            @else
                <h2 class="mt-4 text-xl font-bold text-slate-900">Request submitted</h2>
                <p class="mt-2 text-sm text-slate-600 max-w-md mx-auto">
                    Thanks, {{ $guest_name }}. We've emailed a confirmation to {{ $guest_email }}.
                    Your request is pending review &mdash; we'll let you know as soon as it's approved.
                </p>
            @endif
            <a href="{{ url('/') }}" class="mt-6 inline-flex items-center gap-2 rounded-lg bg-gradient-to-r from-indigo-600 to-violet-600 px-6 py-2.5 text-sm font-semibold text-white shadow-lg shadow-indigo-200 transition hover:shadow-indigo-300 hover:-translate-y-0.5">
                Back to home
            </a>
        </div>
    @else
        <!-- Progress steps -->
        <div class="mb-8 flex items-center justify-center">
            @foreach (['Choose a space', 'Date & time', 'Your details'] as $i => $label)
                @php $stepNumber = $i + 1; @endphp
                <div class="flex items-center {{ $i < 2 ? 'flex-1' : '' }}">
                    <div class="flex flex-col items-center {{ $i > 0 ? 'flex-1' : '' }}">
                        <div class="flex h-8 w-8 items-center justify-center rounded-full text-sm font-semibold {{ $step >= $stepNumber ? 'bg-gradient-to-r from-indigo-600 to-violet-600 text-white' : 'bg-slate-100 text-slate-400' }}">
                            @if ($step > $stepNumber)
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                </svg>
                            @else
                                {{ $stepNumber }}
                            @endif
                        </div>
                        <span class="mt-1.5 text-xs font-medium {{ $step >= $stepNumber ? 'text-slate-900' : 'text-slate-400' }} hidden sm:block">{{ $label }}</span>
                    </div>
                    @if ($i < 2)
                        <div class="mx-2 h-0.5 flex-1 rounded {{ $step > $stepNumber ? 'bg-indigo-500' : 'bg-slate-100' }}"></div>
                    @endif
                </div>
            @endforeach
        </div>

        @if ($errorMessage)
            <div class="mb-6 rounded-lg bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700">
                {{ $errorMessage }}
            </div>
        @endif

        {{-- Step 1: Choose a space --}}
        @if ($step === 1)
            @if (count($branches) > 1 && ! $branch_id)
                <div>
                    <h2 class="text-lg font-semibold text-slate-900">Choose a branch</h2>
                    <p class="mt-1 text-sm text-slate-500">Select the location you'd like to visit.</p>

                    <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-4">
                        @foreach ($branches as $branch)
                            <button type="button" wire:click="selectBranch({{ $branch->id }})" class="rounded-2xl border border-slate-200 bg-white p-5 text-left shadow-sm transition hover:border-indigo-300 hover:shadow-md">
                                <h3 class="font-semibold text-slate-900">{{ $branch->name }}</h3>
                                @if ($branch->location)
                                    <p class="mt-1 text-sm text-slate-500">{{ $branch->location }}</p>
                                @endif
                            </button>
                        @endforeach
                    </div>
                </div>
            @else
                <div>
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h2 class="text-lg font-semibold text-slate-900">Choose a space</h2>
                            <p class="mt-1 text-sm text-slate-500">Pick the room or desk you'd like to book.</p>
                        </div>
                        @if (count($branches) > 1)
                            <button type="button" wire:click="changeBranch" class="whitespace-nowrap text-sm font-medium text-indigo-600 hover:text-indigo-700">
                                Change branch
                            </button>
                        @endif
                    </div>

                    <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
                        @forelse ($spaces as $space)
                            <button type="button" wire:click="selectSpace({{ $space->id }})" class="group overflow-hidden rounded-2xl border border-slate-200 bg-white text-left shadow-sm transition hover:border-indigo-300 hover:shadow-md">
                                @if ($space->image_path)
                                    <img src="{{ \Illuminate\Support\Facades\Storage::url($space->image_path) }}" alt="{{ $space->name }}" class="h-40 w-full object-cover">
                                @else
                                    <div class="flex h-40 w-full items-center justify-center bg-slate-100 text-slate-300">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-12 w-12">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 001.5 1.5zm10.5-11.25h.008v.008h-.008V8.25zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" />
                                        </svg>
                                    </div>
                                @endif

                                <div class="p-4">
                                    <div class="flex items-center justify-between gap-2">
                                        <h3 class="font-semibold text-slate-900">{{ $space->name }}</h3>
                                        <span class="whitespace-nowrap rounded-full bg-indigo-50 px-2.5 py-0.5 text-xs font-medium text-indigo-700">{{ $space->type->name }}</span>
                                    </div>
                                    <p class="mt-1 text-sm text-slate-500">
                                        Up to {{ $space->capacity }} {{ \Illuminate\Support\Str::plural('person', $space->capacity) }}
                                    </p>
                                    @if (! empty($space->facilities))
                                        <div class="mt-2 flex flex-wrap gap-1.5">
                                            @foreach (array_slice($space->facilities, 0, 3) as $facility)
                                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600">{{ $facility }}</span>
                                            @endforeach
                                        </div>
                                    @endif
                                    <span class="mt-3 inline-flex items-center gap-1 text-sm font-semibold text-indigo-600 group-hover:text-indigo-700">
                                        Select this space
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3" />
                                        </svg>
                                    </span>
                                </div>
                            </button>
                        @empty
                            <p class="text-sm text-slate-500">No office spaces are currently available at this branch.</p>
                        @endforelse
                    </div>
                </div>
            @endif
        @endif

        {{-- Step 2: Date & time --}}
        @if ($step === 2 && $selectedSpace)
            <div>
                <div class="flex items-center justify-between gap-4 rounded-2xl border border-slate-100 bg-slate-50 p-4">
                    <div class="flex items-center gap-3">
                        @if ($selectedSpace->image_path)
                            <img src="{{ \Illuminate\Support\Facades\Storage::url($selectedSpace->image_path) }}" alt="{{ $selectedSpace->name }}" class="h-12 w-16 rounded-lg object-cover">
                        @else
                            <div class="flex h-12 w-16 items-center justify-center rounded-lg bg-slate-200 text-slate-400">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-6 w-6">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 001.5 1.5zm10.5-11.25h.008v.008h-.008V8.25zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" />
                                </svg>
                            </div>
                        @endif
                        <div>
                            <h3 class="font-semibold text-slate-900">{{ $selectedSpace->name }}</h3>
                            <p class="text-sm text-slate-500">{{ $selectedSpace->type->name }} &middot; Up to {{ $selectedSpace->capacity }} {{ \Illuminate\Support\Str::plural('person', $selectedSpace->capacity) }}</p>
                        </div>
                    </div>
                    <button type="button" wire:click="backToSpaces" class="whitespace-nowrap text-sm font-medium text-indigo-600 hover:text-indigo-700">
                        Change space
                    </button>
                </div>

                <h2 class="mt-6 text-lg font-semibold text-slate-900">Pick a date</h2>

                <div class="mt-3 flex items-center gap-2">
                    <button type="button" wire:click="previousWeek" @if($weekOffset === 0) disabled @endif class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-slate-200 text-slate-500 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                        </svg>
                    </button>

                    <div class="grid flex-1 grid-cols-7 gap-2">
                        @foreach ($days as $day)
                            @php $dateString = $day->format('Y-m-d'); @endphp
                            <button type="button" wire:click="selectDay('{{ $dateString }}')" class="flex flex-col items-center rounded-xl border px-2 py-2.5 text-center transition {{ $date === $dateString ? 'border-indigo-500 bg-indigo-50 text-indigo-700' : 'border-slate-200 text-slate-600 hover:border-indigo-200' }}">
                                <span class="text-xs font-medium uppercase">{{ $day->format('D') }}</span>
                                <span class="mt-0.5 text-sm font-semibold">{{ $day->format('j M') }}</span>
                                @if ($day->isToday())
                                    <span class="mt-0.5 text-[10px] font-medium {{ $date === $dateString ? 'text-indigo-500' : 'text-slate-400' }}">Today</span>
                                @endif
                            </button>
                        @endforeach
                    </div>

                    <button type="button" wire:click="nextWeek" class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-slate-200 text-slate-500 transition hover:bg-slate-50">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                        </svg>
                    </button>
                </div>

                <h2 class="mt-6 text-lg font-semibold text-slate-900">Pick a start time</h2>

                @if ($startSlots->isEmpty())
                    <p class="mt-3 text-sm text-slate-500">No available time slots for this day &mdash; please pick another date.</p>
                @else
                    <div class="mt-3 grid grid-cols-3 sm:grid-cols-4 lg:grid-cols-6 gap-2">
                        @foreach ($startSlots as $slot)
                            <button
                                type="button"
                                wire:click="selectSlot('{{ $slot['time'] }}')"
                                @disabled(! $slot['available'])
                                class="rounded-lg border px-3 py-2 text-sm font-medium transition
                                    {{ $start_time === $slot['time']
                                        ? 'border-indigo-500 bg-indigo-600 text-white'
                                        : ($slot['available']
                                            ? 'border-slate-200 text-slate-700 hover:border-indigo-300 hover:bg-indigo-50'
                                            : 'border-slate-100 bg-slate-50 text-slate-300 cursor-not-allowed') }}"
                            >
                                {{ $slot['label'] }}
                            </button>
                        @endforeach
                    </div>
                @endif

                @if ($start_time)
                    <h2 class="mt-6 text-lg font-semibold text-slate-900">Pick an end time</h2>

                    @if ($endSlots->isEmpty())
                        <p class="mt-3 text-sm text-slate-500">No available end times for this start &mdash; please pick another start time.</p>
                    @else
                        <div class="mt-3 grid grid-cols-3 sm:grid-cols-4 lg:grid-cols-6 gap-2">
                            @foreach ($endSlots as $slot)
                                <button
                                    type="button"
                                    wire:click="selectEndSlot('{{ $slot['time'] }}')"
                                    class="rounded-lg border px-3 py-2 text-sm font-medium transition
                                        {{ $end_time === $slot['time']
                                            ? 'border-indigo-500 bg-indigo-600 text-white'
                                            : 'border-slate-200 text-slate-700 hover:border-indigo-300 hover:bg-indigo-50' }}"
                                >
                                    <span class="block">
                                        {{ $slot['label'] }}
                                        @if ($slot['nextDay'])
                                            <span class="text-[10px] font-semibold align-top {{ $end_time === $slot['time'] ? 'text-indigo-100' : 'text-indigo-500' }}">+1d</span>
                                        @endif
                                    </span>
                                    <span class="block text-xs {{ $end_time === $slot['time'] ? 'text-indigo-100' : 'text-slate-400' }}">{{ $slot['duration'] }}</span>
                                </button>
                            @endforeach
                        </div>
                    @endif
                @endif

                <div class="mt-8 flex items-center justify-between">
                    <button type="button" wire:click="backToSpaces" class="inline-flex items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50">
                        Back
                    </button>
                    <button type="button" wire:click="proceedToDetails" @disabled(! $start_time || ! $end_time) class="inline-flex items-center justify-center gap-2 rounded-lg bg-gradient-to-r from-indigo-600 to-violet-600 px-6 py-2.5 text-sm font-semibold text-white shadow-sm shadow-indigo-200 transition hover:from-indigo-500 hover:to-violet-500 disabled:cursor-not-allowed disabled:opacity-50">
                        Next
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3" />
                        </svg>
                    </button>
                </div>
            </div>
        @endif

        {{-- Step 3: Your details --}}
        @if ($step === 3 && $selectedSpace)
            <form wire:submit="submit">
                @php
                    $summaryStart = \Carbon\Carbon::parse("{$date} {$start_time}");
                    $summaryEnd = \Carbon\Carbon::parse("{$date} {$end_time}");

                    if ($summaryEnd->lte($summaryStart)) {
                        $summaryEnd->addDay();
                    }

                    $crossesDate = ! $summaryEnd->isSameDay($summaryStart);
                @endphp
                <div class="rounded-2xl border border-slate-100 bg-slate-50 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Booking summary</p>
                    <p class="mt-1 text-sm font-semibold text-slate-900">{{ $selectedSpace->name }}</p>
                    @if ($crossesDate)
                        <p class="text-sm text-slate-600">
                            {{ $summaryStart->format('D, j M Y · g:i A') }} &ndash; {{ $summaryEnd->format('D, j M Y · g:i A') }}
                        </p>
                    @else
                        <p class="text-sm text-slate-600">
                            {{ $summaryStart->format('D, j M Y') }} &middot; {{ $summaryStart->format('g:i A') }}&ndash;{{ $summaryEnd->format('g:i A') }}
                        </p>
                    @endif
                </div>

                <h2 class="mt-6 text-lg font-semibold text-slate-900">Your details</h2>

                <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="guest_name" class="block text-sm font-medium text-slate-700">Full name</label>
                        <input wire:model="guest_name" id="guest_name" type="text" class="mt-1.5 block w-full rounded-lg border-slate-200 px-3 py-2.5 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                        <x-input-error :messages="$errors->get('guest_name')" class="mt-2" />
                    </div>
                    <div>
                        <label for="guest_email" class="block text-sm font-medium text-slate-700">Email</label>
                        <input wire:model="guest_email" id="guest_email" type="email" class="mt-1.5 block w-full rounded-lg border-slate-200 px-3 py-2.5 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                        <x-input-error :messages="$errors->get('guest_email')" class="mt-2" />
                    </div>
                    <div>
                        <label for="guest_phone" class="block text-sm font-medium text-slate-700">Phone (optional)</label>
                        <input wire:model="guest_phone" id="guest_phone" type="text" class="mt-1.5 block w-full rounded-lg border-slate-200 px-3 py-2.5 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                        <x-input-error :messages="$errors->get('guest_phone')" class="mt-2" />
                    </div>
                    <div>
                        <label for="title" class="block text-sm font-medium text-slate-700">Purpose (optional)</label>
                        <input wire:model="title" id="title" type="text" placeholder="e.g. Client meeting" class="mt-1.5 block w-full rounded-lg border-slate-200 px-3 py-2.5 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                        <x-input-error :messages="$errors->get('title')" class="mt-2" />
                    </div>
                </div>

                <div class="mt-8 flex items-center justify-between">
                    <button type="button" wire:click="backToSchedule" class="inline-flex items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50">
                        Back
                    </button>
                    <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-gradient-to-r from-indigo-600 to-violet-600 px-6 py-3 text-sm font-semibold text-white shadow-lg shadow-indigo-200 transition hover:shadow-indigo-300 hover:-translate-y-0.5">
                        Submit request
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3" />
                        </svg>
                    </button>
                </div>
            </form>
        @endif
    @endif
</div>
