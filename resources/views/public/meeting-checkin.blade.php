<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ __('Check-in') }} - {{ $meeting->title }}</title>
        <link rel="icon" type="image/png" href="{{ asset('favicon.svg') }}">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased text-slate-900 bg-white">
        <main class="min-h-screen flex items-center justify-center px-4 py-16">
            <div class="w-full max-w-md">
                <div class="text-center">
                    <h1 class="text-2xl font-extrabold tracking-tight text-slate-900">{{ __('Meeting Check-in') }}</h1>
                    <p class="mt-2 text-slate-600">{{ $meeting->title }}</p>
                </div>

                <div class="mt-10">
                    <livewire:public.meeting-checkin :meeting="$meeting" />
                </div>
            </div>
        </main>
    </body>
</html>
