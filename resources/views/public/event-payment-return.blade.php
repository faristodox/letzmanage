<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        @php $organizationLogoPath = app(\App\Services\SystemSettingService::class)->getOrganizationLogoPath(); @endphp
        @php $organizationName = app(\App\Services\SystemSettingService::class)->getOrganizationName() ?: config('app.name', 'Letz Manage'); @endphp

        <title>{{ __('Payment') }} - {{ $event->title }}</title>
        <link rel="icon" type="image/png" href="{{ $organizationLogoPath ? \Illuminate\Support\Facades\Storage::url($organizationLogoPath) : asset('favicon.svg') }}">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased text-slate-900 bg-white">
        <header class="border-b border-slate-100 bg-white/80 backdrop-blur-md">
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
                </div>
            </div>
        </header>

        <main class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
            <livewire:public.event-payment-return :event="$event" />
        </main>

        <footer class="border-t border-slate-100">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 text-center text-sm text-slate-500">
                &copy; {{ now()->year }} {{ config('app.name', 'Letz Manage') }}. All rights reserved.
            </div>
        </footer>
    </body>
</html>
