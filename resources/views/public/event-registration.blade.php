<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        @php $organizationLogoPath = app(\App\Services\SystemSettingService::class)->getOrganizationLogoPath(); @endphp
        @php $organizationName = app(\App\Services\SystemSettingService::class)->getOrganizationName() ?: config('app.name', 'Letz Manage'); @endphp

        <title>{{ $event->title }} - {{ $organizationName }}</title>
        <link rel="icon" type="image/png" href="{{ $organizationLogoPath ? \Illuminate\Support\Facades\Storage::url($organizationLogoPath) : asset('favicon.svg') }}">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased text-slate-900 bg-white">

        <!-- Header -->
        <header class="sticky top-0 z-50 border-b border-slate-100 bg-white/80 backdrop-blur-md">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex items-center justify-between h-16">
                    <a href="{{ url('/') }}" class="flex items-center gap-2.5">
                        @if ($organizationLogoPath)
                            <img src="{{ \Illuminate\Support\Facades\Storage::url($organizationLogoPath) }}" alt="" class="h-9 w-9 rounded-xl object-cover ring-1 ring-slate-200">
                        @else
                            <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-600 to-violet-600 text-white font-bold shadow-sm shadow-indigo-200">
                                L
                            </div>
                        @endif
                        <span class="text-lg font-bold tracking-tight text-slate-900">{{ $organizationName }}</span>
                    </a>

                    <a href="{{ url('/') }}" class="text-sm font-medium text-slate-600 hover:text-slate-900 transition">
                        &larr; Back to home
                    </a>
                </div>
            </div>
        </header>

        <main class="relative overflow-hidden">
            <div class="absolute -top-32 right-0 -z-10 h-96 w-96 rounded-full bg-violet-200/40 blur-3xl"></div>
            <div class="absolute top-40 -left-32 -z-10 h-80 w-80 rounded-full bg-indigo-200/30 blur-3xl"></div>

            <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
                @if ($event->bannerUrl())
                    <img src="{{ $event->bannerUrl() }}" alt="" class="mb-8 aspect-[3/1] w-full rounded-2xl object-cover shadow-sm">
                @endif

                <div class="text-center">
                    <h1 class="text-3xl sm:text-4xl font-extrabold tracking-tight text-slate-900">
                        {{ $event->title }}
                    </h1>

                    @if ($event->scheduleLabel() || $event->location)
                        <div class="mt-3 flex flex-wrap items-center justify-center gap-x-5 gap-y-1.5 text-sm font-medium text-slate-600">
                            @if ($event->scheduleLabel())
                                <span class="inline-flex items-center gap-1.5">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5 text-slate-400">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                                    </svg>
                                    {{ $event->scheduleLabel() }}
                                </span>
                            @endif
                            @if ($event->scheduleLabel() && $event->location)
                                <span class="hidden h-4 w-px bg-slate-300 sm:inline-block" aria-hidden="true"></span>
                            @endif
                            @if ($event->location)
                                <span class="inline-flex items-center gap-1.5">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5 text-slate-400">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" />
                                    </svg>
                                    {{ $event->location }}
                                </span>
                            @endif
                        </div>
                    @endif

                    <p class="mt-4 text-slate-600 max-w-xl mx-auto">
                        {{ $eventForm->type->value === 'feedback' ? __('Fill in the form below to share your feedback.') : __('Fill in the form below to register.') }}
                    </p>
                </div>

                <div class="mt-12">
                    <livewire:public.event-registration :event-form="$eventForm" :preview="$preview ?? false" />
                </div>
            </div>
        </main>

        <!-- Footer -->
        <footer class="border-t border-slate-100">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 text-center text-sm text-slate-500">
                &copy; {{ now()->year }} {{ config('app.name', 'Letz Manage') }}. All rights reserved.
            </div>
        </footer>
    </body>
</html>
