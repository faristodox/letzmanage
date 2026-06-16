<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Letz Manage') }}</title>

        @php $_faviconPath = app(\App\Services\SystemSettingService::class)->getOrganizationLogoPath(); @endphp
        <link rel="icon" type="image/png" href="{{ $_faviconPath ? \Illuminate\Support\Facades\Storage::url($_faviconPath) : asset('favicon.ico') }}">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased text-slate-900 bg-white">
        <div class="min-h-screen flex">
            <!-- Branding panel -->
            <div class="hidden lg:flex lg:w-1/2 relative overflow-hidden bg-gradient-to-br from-indigo-600 to-violet-600">
                <div class="absolute -top-24 -right-24 h-72 w-72 rounded-full bg-white/10"></div>
                <div class="absolute bottom-10 -left-16 h-72 w-72 rounded-full bg-white/10"></div>

                <div class="relative z-10 flex flex-col justify-between w-full p-12 text-white">
                    <a href="/" wire:navigate class="flex items-center gap-2.5">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-white/15 font-bold text-lg backdrop-blur">
                            L
                        </div>
                        <span class="text-xl font-bold tracking-tight">{{ config('app.name', 'Letz Manage') }}</span>
                    </a>

                    <div>
                        <h2 class="text-3xl font-bold leading-tight max-w-md">
                            Run every branch from one workspace
                        </h2>
                        <p class="mt-4 text-indigo-100 max-w-md">
                            Manage branches, office spaces, and bookings &mdash; with role-based
                            access and configurable approval workflows.
                        </p>

                        <ul class="mt-8 space-y-3 text-sm">
                            <li class="flex items-center gap-3">
                                <span class="flex h-6 w-6 items-center justify-center rounded-full bg-white/15">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                    </svg>
                                </span>
                                Branch &amp; office space management
                            </li>
                            <li class="flex items-center gap-3">
                                <span class="flex h-6 w-6 items-center justify-center rounded-full bg-white/15">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                    </svg>
                                </span>
                                Booking calendar with conflict prevention
                            </li>
                            <li class="flex items-center gap-3">
                                <span class="flex h-6 w-6 items-center justify-center rounded-full bg-white/15">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                    </svg>
                                </span>
                                Role-based access for every team
                            </li>
                        </ul>
                    </div>

                    <p class="text-xs text-indigo-100">
                        &copy; {{ now()->year }} {{ config('app.name', 'Letz Manage') }}. All rights reserved.
                    </p>
                </div>
            </div>

            <!-- Form panel -->
            <div class="flex flex-1 flex-col justify-center px-6 py-12 sm:px-12 lg:px-20">
                <div class="mx-auto w-full max-w-sm">
                    <a href="/" wire:navigate class="lg:hidden flex items-center gap-2.5 mb-10">
                        <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-600 to-violet-600 text-white font-bold">
                            L
                        </div>
                        <span class="text-lg font-bold tracking-tight text-slate-900">{{ config('app.name', 'Letz Manage') }}</span>
                    </a>

                    {{ $slot }}
                </div>
            </div>
        </div>
    </body>
</html>
