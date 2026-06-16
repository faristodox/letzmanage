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
    <body class="font-sans antialiased text-slate-900">
        <div class="min-h-screen bg-slate-50">
            <livewire:layout.navigation />

            <div class="lg:pl-72">
                <div class="sticky top-0 z-30 border-b border-slate-200 bg-white/80 backdrop-blur-md">
                    <div class="flex items-center justify-between gap-4 px-4 py-5 sm:px-6 lg:px-8">
                        <div class="min-w-0 flex-1">
                            {{ $header ?? '' }}
                        </div>

                        <div class="shrink-0">
                            <livewire:layout.user-menu />
                        </div>
                    </div>
                </div>

                <main class="px-4 py-8 sm:px-6 lg:px-8">
                    {{ $slot }}
                </main>
            </div>
        </div>
    </body>
</html>
