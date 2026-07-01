<?php

use Livewire\Volt\Component;

new class extends Component
{
}; ?>

<div x-data="{ sidebarOpen: false }">
    <!-- Mobile top bar -->
    <div class="sticky top-0 z-40 flex items-center gap-x-4 border-b border-slate-200 bg-white px-4 py-3 sm:px-6 lg:hidden">
        <button type="button" class="-m-2.5 p-2.5 text-slate-500" @click="sidebarOpen = true">
            <span class="sr-only">{{ __('Open sidebar') }}</span>
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-6 w-6">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
            </svg>
        </button>

        <a href="{{ route('dashboard') }}" wire:navigate class="flex items-center gap-2.5">
            <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-gradient-to-br from-indigo-600 to-violet-600 text-sm font-bold text-white shadow-sm shadow-indigo-200">
                L
            </div>
            <span class="text-base font-bold tracking-tight text-slate-900">{{ config('app.name', 'Letz Manage') }}</span>
        </a>
    </div>

    <!-- Mobile slide-over sidebar -->
    <div x-show="sidebarOpen" class="relative z-50 lg:hidden" style="display: none;">
        <div class="fixed inset-0 bg-slate-900/50"
             x-show="sidebarOpen"
             x-transition:enter="transition-opacity ease-linear duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition-opacity ease-linear duration-300"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             @click="sidebarOpen = false"></div>

        <div class="fixed inset-y-0 left-0 flex w-72 max-w-[80%] flex-col bg-white"
             x-show="sidebarOpen"
             x-transition:enter="transition ease-in-out duration-300 transform"
             x-transition:enter-start="-translate-x-full"
             x-transition:enter-end="translate-x-0"
             x-transition:leave="transition ease-in-out duration-300 transform"
             x-transition:leave-start="translate-x-0"
             x-transition:leave-end="-translate-x-full">
            <div class="flex grow flex-col gap-y-5 overflow-y-auto px-6 py-6">
                <div class="flex items-center justify-between">
                    <a href="{{ route('dashboard') }}" wire:navigate class="flex items-center gap-2.5">
                        <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-600 to-violet-600 text-base font-bold text-white shadow-sm shadow-indigo-200">
                            L
                        </div>
                        <span class="text-lg font-bold tracking-tight text-slate-900">{{ config('app.name', 'Letz Manage') }}</span>
                    </a>

                    <button type="button" class="-m-2.5 p-2.5 text-slate-500" @click="sidebarOpen = false">
                        <span class="sr-only">{{ __('Close sidebar') }}</span>
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-6 w-6">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                @include('livewire.layout.navigation-links')
            </div>
        </div>
    </div>

    <!-- Desktop sidebar -->
    <aside class="hidden lg:fixed lg:inset-y-0 lg:flex lg:flex-col"
           style="transition: width 0.2s ease;"
           :style="$store.sidebar.collapsed ? 'width: 4rem' : 'width: 18rem'">
        <div class="flex grow flex-col gap-y-5 overflow-y-auto overflow-x-hidden border-r border-slate-200 bg-white py-6"
             style="transition: padding 0.2s ease;"
             :class="$store.sidebar.collapsed ? 'px-2' : 'px-6'">

            <!-- Logo + collapse toggle -->
            {{-- Expanded: logo + name + collapse button --}}
            <div x-show="!$store.sidebar.collapsed" x-cloak class="flex items-center justify-between">
                <a href="{{ route('dashboard') }}" wire:navigate class="flex items-center gap-2.5 min-w-0 overflow-hidden">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-600 to-violet-600 text-base font-bold text-white shadow-sm shadow-indigo-200">
                        L
                    </div>
                    <span class="text-lg font-bold tracking-tight text-slate-900 truncate">
                        {{ config('app.name', 'Letz Manage') }}
                    </span>
                </a>
                <button @click="$store.sidebar.toggle()" type="button" title="Collapse sidebar"
                        class="shrink-0 rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600 transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-4 w-4">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                    </svg>
                </button>
            </div>

            {{-- Collapsed: just the expand button centered --}}
            <div x-show="$store.sidebar.collapsed" x-cloak class="flex justify-center">
                <button @click="$store.sidebar.toggle()" type="button" title="Expand sidebar"
                        class="rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-600 transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-5 w-5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                    </svg>
                </button>
            </div>

            @include('livewire.layout.navigation-links')
        </div>
    </aside>
</div>
