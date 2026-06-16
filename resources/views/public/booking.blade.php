<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>Request a Booking - {{ config('app.name', 'Letz Manage') }}</title>

        @php $organizationLogoPath = app(\App\Services\SystemSettingService::class)->getOrganizationLogoPath(); @endphp
        <link rel="icon" type="image/png" href="{{ $organizationLogoPath ? \Illuminate\Support\Facades\Storage::url($organizationLogoPath) : asset('favicon.svg') }}">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased text-slate-900 bg-white">

        @php
            $organizationName = app(\App\Services\SystemSettingService::class)->getOrganizationName() ?: config('app.name', 'Letz Manage');
        @endphp

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

            <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
                <div class="text-center">
                    <h1 class="text-3xl sm:text-4xl font-extrabold tracking-tight text-slate-900">
                        Find a space and book it in minutes
                    </h1>
                    <p class="mt-4 text-slate-600 max-w-xl mx-auto">
                        Choose a space, pick a date and time, and tell us a bit about yourself.
                        Our team will review your request and confirm by email.
                    </p>
                </div>

                <div class="mt-12">
                    <livewire:public.booking-request />
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
