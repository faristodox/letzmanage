<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Letz Manage') }}</title>

        <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <style>
            [x-cloak] { display: none !important; }
            @media (min-width: 1024px) {
                .main-content { padding-left: 18rem; transition: padding-left 0.2s ease; }
                .main-content.sidebar-collapsed { padding-left: 4rem; }
            }
        </style>

        <script>
            function registerSidebarStore() {
                if (! window.Alpine || Alpine.store('sidebar')) {
                    return;
                }
                Alpine.store('sidebar', {
                    collapsed: localStorage.getItem('sidebarCollapsed') === 'true',
                    toggle() {
                        this.collapsed = !this.collapsed;
                        localStorage.setItem('sidebarCollapsed', this.collapsed);
                    }
                });
            }
            // Full page load: Alpine not started yet.
            document.addEventListener('alpine:init', registerSidebarStore);
            // wire:navigate (e.g. after login): Alpine already running, alpine:init won't fire again.
            document.addEventListener('livewire:navigated', registerSidebarStore);
            // In case Alpine is already running when this script executes (wire:navigate head swap).
            registerSidebarStore();
        </script>
    </head>
    <body class="font-sans antialiased text-slate-900">
        <div class="min-h-screen bg-slate-50">
            <livewire:layout.navigation />

            <div x-data class="main-content" :class="($store.sidebar?.collapsed) ? 'sidebar-collapsed' : ''">
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
