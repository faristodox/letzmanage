<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ config('app.name', 'Letz Manage') }} - Multi-Branch Office Management</title>
        <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">

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
                    <div class="flex items-center gap-2.5">
                        <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-600 to-violet-600 text-white font-bold shadow-sm shadow-indigo-200">
                            L
                        </div>
                        <span class="text-lg font-bold tracking-tight text-slate-900">{{ config('app.name', 'Letz Manage') }}</span>
                    </div>

                    <nav class="hidden md:flex items-center gap-8 text-sm font-medium text-slate-600">
                        <a href="#features" class="hover:text-slate-900 transition">Features</a>
                        <a href="#modules" class="hover:text-slate-900 transition">Modules</a>
                        <a href="#workflow" class="hover:text-slate-900 transition">How it works</a>
                    </nav>

                    <livewire:welcome.navigation />
                </div>
            </div>
        </header>

        <main>
            <!-- Hero -->
            <section class="relative overflow-hidden">
                <div class="absolute inset-0 -z-10 bg-gradient-to-b from-indigo-50 via-white to-white"></div>
                <div class="absolute -top-32 right-0 -z-10 h-96 w-96 rounded-full bg-violet-200/50 blur-3xl"></div>
                <div class="absolute top-40 -left-32 -z-10 h-80 w-80 rounded-full bg-indigo-200/40 blur-3xl"></div>

                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 lg:py-28">
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-16 items-center">
                        <div>
                            <h1 class="text-4xl sm:text-5xl lg:text-6xl font-extrabold tracking-tight text-slate-900 leading-tight">
                                Run every branch
                                <span class="bg-gradient-to-r from-indigo-600 to-violet-600 bg-clip-text text-transparent">from one workspace</span>
                            </h1>

                            <p class="mt-6 text-lg text-slate-600 max-w-xl">
                                {{ config('app.name', 'Letz Manage') }} brings your branches, office spaces, and
                                bookings together &mdash; with role-based access and configurable approval
                                workflows so every team works the way it should.
                            </p>

                            <div class="mt-10 flex flex-wrap items-center gap-4">
                                @auth
                                    <a href="{{ url('/dashboard') }}"
                                       class="inline-flex items-center gap-2 rounded-lg bg-gradient-to-r from-indigo-600 to-violet-600 px-6 py-3 text-sm font-semibold text-white shadow-lg shadow-indigo-200 transition hover:shadow-indigo-300 hover:-translate-y-0.5">
                                        Go to Dashboard
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3" />
                                        </svg>
                                    </a>
                                @else
                                    <a href="{{ route('login') }}"
                                       class="inline-flex items-center gap-2 rounded-lg bg-gradient-to-r from-indigo-600 to-violet-600 px-6 py-3 text-sm font-semibold text-white shadow-lg shadow-indigo-200 transition hover:shadow-indigo-300 hover:-translate-y-0.5">
                                        Sign in to your account
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3" />
                                        </svg>
                                    </a>
                                @endauth

                                <a href="{{ route('booking.request') }}"
                                   class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-6 py-3 text-sm font-semibold text-slate-700 transition hover:border-slate-300 hover:bg-slate-50">
                                    Request a booking
                                </a>
                            </div>

                            <dl class="mt-14 grid grid-cols-3 gap-8 max-w-md">
                                <div>
                                    <dt class="text-2xl font-extrabold text-slate-900">Auto</dt>
                                    <dd class="mt-1 text-sm text-slate-500">or manual booking approvals</dd>
                                </div>
                                <div>
                                    <dt class="text-2xl font-extrabold text-slate-900">Real-time</dt>
                                    <dd class="mt-1 text-sm text-slate-500">availability &amp; conflicts</dd>
                                </div>
                                <div>
                                    <dt class="text-2xl font-extrabold text-slate-900">Role-based</dt>
                                    <dd class="mt-1 text-sm text-slate-500">access for every team</dd>
                                </div>
                            </dl>
                        </div>

                        <!-- Dashboard preview mockup -->
                        <div class="relative">
                            <div class="absolute -inset-4 rounded-3xl bg-gradient-to-br from-indigo-100 to-violet-100 -z-10 rotate-2"></div>

                            <div class="rounded-2xl border border-slate-100 bg-white shadow-2xl shadow-slate-200 overflow-hidden">
                                <!-- window chrome -->
                                <div class="flex items-center gap-2 border-b border-slate-100 bg-slate-50 px-4 py-3">
                                    <span class="h-2.5 w-2.5 rounded-full bg-rose-300"></span>
                                    <span class="h-2.5 w-2.5 rounded-full bg-amber-300"></span>
                                    <span class="h-2.5 w-2.5 rounded-full bg-emerald-300"></span>
                                    <span class="ml-3 text-xs font-medium text-slate-400">{{ strtolower(str_replace(' ', '', config('app.name', 'letzmanage'))) }}.app/bookings/calendar</span>
                                </div>

                                <div class="p-6">
                                    <!-- stat cards -->
                                    <div class="grid grid-cols-3 gap-3">
                                        <div class="rounded-xl bg-indigo-50 p-3">
                                            <div class="text-xs font-medium text-indigo-600">Branches</div>
                                            <div class="mt-1 text-xl font-bold text-slate-900">5</div>
                                        </div>
                                        <div class="rounded-xl bg-violet-50 p-3">
                                            <div class="text-xs font-medium text-violet-600">Spaces</div>
                                            <div class="mt-1 text-xl font-bold text-slate-900">24</div>
                                        </div>
                                        <div class="rounded-xl bg-emerald-50 p-3">
                                            <div class="text-xs font-medium text-emerald-600">Approved</div>
                                            <div class="mt-1 text-xl font-bold text-slate-900">87%</div>
                                        </div>
                                    </div>

                                    <!-- mini calendar -->
                                    <div class="mt-5 rounded-xl border border-slate-100 p-4">
                                        <div class="flex items-center justify-between">
                                            <span class="text-sm font-semibold text-slate-700">June 2026</span>
                                            <span class="text-xs text-slate-400">Meeting Room A</span>
                                        </div>
                                        <div class="mt-3 grid grid-cols-7 gap-1.5 text-center text-[10px] text-slate-400">
                                            <span>S</span><span>M</span><span>T</span><span>W</span><span>T</span><span>F</span><span>S</span>
                                        </div>
                                        <div class="mt-1 grid grid-cols-7 gap-1.5">
                                            @for ($i = 1; $i <= 21; $i++)
                                                <div class="aspect-square rounded-md text-[10px] flex items-center justify-center
                                                    @if (in_array($i, [3, 11, 17])) bg-indigo-600 text-white font-semibold
                                                    @elseif (in_array($i, [7, 14])) bg-amber-100 text-amber-700
                                                    @else bg-slate-50 text-slate-400 @endif">
                                                    {{ $i }}
                                                </div>
                                            @endfor
                                        </div>
                                    </div>

                                    <!-- booking rows -->
                                    <div class="mt-5 space-y-2">
                                        <div class="flex items-center justify-between rounded-lg border border-slate-100 px-3 py-2">
                                            <div class="flex items-center gap-2">
                                                <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                                                <span class="text-xs font-medium text-slate-700">Team Standup &mdash; Meeting Room A</span>
                                            </div>
                                            <span class="text-xs text-slate-400">09:00</span>
                                        </div>
                                        <div class="flex items-center justify-between rounded-lg border border-slate-100 px-3 py-2">
                                            <div class="flex items-center gap-2">
                                                <span class="h-2 w-2 rounded-full bg-amber-400"></span>
                                                <span class="text-xs font-medium text-slate-700">Client Review &mdash; Pending approval</span>
                                            </div>
                                            <span class="text-xs text-slate-400">14:30</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Logo / trust strip -->
            <section class="border-y border-slate-100 bg-slate-50">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                    <p class="text-center text-xs font-semibold uppercase tracking-widest text-slate-400">
                        One platform for branches, spaces, people &amp; bookings
                    </p>
                </div>
            </section>

            <!-- Features -->
            <section id="features" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-24">
                <div class="max-w-2xl">
                    <h2 class="text-sm font-semibold uppercase tracking-widest text-indigo-600">Features</h2>
                    <p class="mt-3 text-3xl sm:text-4xl font-bold tracking-tight text-slate-900">
                        Everything your operations team needs
                    </p>
                    <p class="mt-4 text-slate-600">
                        Built for organizations managing multiple branches &mdash; from setup to day-to-day
                        booking management.
                    </p>
                </div>

                <div id="modules" class="mt-12 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                    <div class="group rounded-2xl border border-slate-100 p-6 transition hover:border-indigo-100 hover:shadow-xl hover:shadow-slate-100">
                        <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600 transition group-hover:bg-indigo-600 group-hover:text-white">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 21h18M5 21V7l7-4 7 4v14M9 21v-6h6v6" />
                            </svg>
                        </div>
                        <h3 class="mt-4 font-semibold text-slate-900">Branch Management</h3>
                        <p class="mt-2 text-sm text-slate-600">
                            Set up and organize every branch location, with managers and staff scoped to
                            their own branch.
                        </p>
                    </div>

                    <div class="group rounded-2xl border border-slate-100 p-6 transition hover:border-indigo-100 hover:shadow-xl hover:shadow-slate-100">
                        <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-violet-50 text-violet-600 transition group-hover:bg-violet-600 group-hover:text-white">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2M5 21H3m4-4h2m4 0h2m-6-4h2m4 0h2m-6-4h2m4 0h2" />
                            </svg>
                        </div>
                        <h3 class="mt-4 font-semibold text-slate-900">Office Spaces</h3>
                        <p class="mt-2 text-sm text-slate-600">
                            Catalog meeting rooms, hot desks, and shared spaces with capacity, type, and
                            facilities at a glance.
                        </p>
                    </div>

                    <div class="group rounded-2xl border border-slate-100 p-6 transition hover:border-indigo-100 hover:shadow-xl hover:shadow-slate-100">
                        <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600 transition group-hover:bg-emerald-600 group-hover:text-white">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                        </div>
                        <h3 class="mt-4 font-semibold text-slate-900">Bookings &amp; Calendar</h3>
                        <p class="mt-2 text-sm text-slate-600">
                            Reserve spaces with automatic overlap detection and a clear monthly calendar
                            view for every space.
                        </p>
                    </div>

                    <div class="group rounded-2xl border border-slate-100 p-6 transition hover:border-indigo-100 hover:shadow-xl hover:shadow-slate-100">
                        <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-amber-50 text-amber-600 transition group-hover:bg-amber-600 group-hover:text-white">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <h3 class="mt-4 font-semibold text-slate-900">Approval Workflows</h3>
                        <p class="mt-2 text-sm text-slate-600">
                            Choose automatic or manual approval per branch, with managers reviewing and
                            actioning requests.
                        </p>
                    </div>

                    <div class="group rounded-2xl border border-slate-100 p-6 transition hover:border-indigo-100 hover:shadow-xl hover:shadow-slate-100">
                        <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-rose-50 text-rose-600 transition group-hover:bg-rose-600 group-hover:text-white">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                            </svg>
                        </div>
                        <h3 class="mt-4 font-semibold text-slate-900">Notifications</h3>
                        <p class="mt-2 text-sm text-slate-600">
                            Email and in-app alerts keep staff and managers informed on booking status
                            changes in real time.
                        </p>
                    </div>

                    <div class="group rounded-2xl border border-slate-100 p-6 transition hover:border-indigo-100 hover:shadow-xl hover:shadow-slate-100">
                        <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-sky-50 text-sky-600 transition group-hover:bg-sky-600 group-hover:text-white">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-1.13a4 4 0 10-4-4 4 4 0 004 4zm6 0a4 4 0 10-4-4" />
                            </svg>
                        </div>
                        <h3 class="mt-4 font-semibold text-slate-900">Roles &amp; Permissions</h3>
                        <p class="mt-2 text-sm text-slate-600">
                            Admins, managers, and staff each get a tailored experience with permissions
                            scoped to their branch.
                        </p>
                    </div>
                </div>
            </section>

            <!-- How it works -->
            <section id="workflow" class="bg-slate-50 border-y border-slate-100">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-24">
                    <div class="max-w-2xl">
                        <h2 class="text-sm font-semibold uppercase tracking-widest text-indigo-600">How it works</h2>
                        <p class="mt-3 text-3xl sm:text-4xl font-bold tracking-tight text-slate-900">
                            From setup to booking in three steps
                        </p>
                    </div>

                    <div class="mt-12 grid grid-cols-1 md:grid-cols-3 gap-8">
                        <div class="relative">
                            <div class="flex h-12 w-12 items-center justify-center rounded-full bg-indigo-600 text-white font-bold">1</div>
                            <h3 class="mt-4 font-semibold text-slate-900">Set up branches &amp; spaces</h3>
                            <p class="mt-2 text-sm text-slate-600">
                                Admins configure branches and add office spaces with capacity and facilities.
                            </p>
                        </div>
                        <div class="relative">
                            <div class="flex h-12 w-12 items-center justify-center rounded-full bg-indigo-600 text-white font-bold">2</div>
                            <h3 class="mt-4 font-semibold text-slate-900">Invite your team</h3>
                            <p class="mt-2 text-sm text-slate-600">
                                Add managers and staff, assign roles, and they're scoped to the right branch
                                automatically.
                            </p>
                        </div>
                        <div class="relative">
                            <div class="flex h-12 w-12 items-center justify-center rounded-full bg-indigo-600 text-white font-bold">3</div>
                            <h3 class="mt-4 font-semibold text-slate-900">Book with confidence</h3>
                            <p class="mt-2 text-sm text-slate-600">
                                Staff book spaces, conflicts are prevented automatically, and approvals
                                follow your chosen workflow.
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- CTA -->
            <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-24">
                <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-indigo-600 to-violet-600 px-8 py-16 text-center shadow-2xl shadow-indigo-200">
                    <div class="absolute -top-24 -right-24 h-64 w-64 rounded-full bg-white/10"></div>
                    <div class="absolute -bottom-24 -left-24 h-64 w-64 rounded-full bg-white/10"></div>

                    <h2 class="text-3xl sm:text-4xl font-bold tracking-tight text-white">
                        Ready to get started?
                    </h2>
                    <p class="mt-4 text-indigo-100 max-w-xl mx-auto">
                        Sign in to manage your branches, office spaces, and bookings from one place.
                    </p>

                    <div class="mt-8 flex flex-wrap items-center justify-center gap-4">
                        @auth
                            <a href="{{ url('/dashboard') }}"
                               class="inline-flex items-center gap-2 rounded-lg bg-white px-6 py-3 text-sm font-semibold text-indigo-600 shadow-lg transition hover:-translate-y-0.5">
                                Go to Dashboard
                            </a>
                        @else
                            <a href="{{ route('login') }}"
                               class="inline-flex items-center gap-2 rounded-lg bg-white px-6 py-3 text-sm font-semibold text-indigo-600 shadow-lg transition hover:-translate-y-0.5">
                                Sign in to your account
                            </a>
                            <a href="{{ route('booking.request') }}"
                               class="inline-flex items-center gap-2 rounded-lg border border-white/30 px-6 py-3 text-sm font-semibold text-white transition hover:bg-white/10">
                                Request a booking
                            </a>
                        @endauth
                    </div>
                </div>
            </section>
        </main>

        <!-- Footer -->
        <footer class="border-t border-slate-100">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 flex flex-col sm:flex-row items-center justify-between gap-4">
                <div class="flex items-center gap-2.5">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-gradient-to-br from-indigo-600 to-violet-600 text-white text-sm font-bold">
                        L
                    </div>
                    <span class="text-sm font-semibold text-slate-700">{{ config('app.name', 'Letz Manage') }}</span>
                </div>
                <p class="text-sm text-slate-500">
                    &copy; {{ now()->year }} {{ config('app.name', 'Letz Manage') }}. All rights reserved.
                </p>
            </div>
        </footer>
    </body>
</html>
