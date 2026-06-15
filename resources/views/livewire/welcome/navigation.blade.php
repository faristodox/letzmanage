<nav class="flex items-center gap-2">
    @auth
        <a
            href="{{ url('/dashboard') }}"
            class="rounded-md px-4 py-2 text-sm font-medium text-gray-700 ring-1 ring-transparent transition hover:text-indigo-600 focus:outline-none focus-visible:ring-indigo-500"
        >
            Dashboard
        </a>
    @else
        @if (Route::has('register'))
            <a
                href="{{ route('register') }}"
                class="rounded-md px-4 py-2 text-sm font-medium text-gray-700 ring-1 ring-transparent transition hover:text-indigo-600 focus:outline-none focus-visible:ring-indigo-500"
            >
                Register
            </a>
        @endif

        <a
            href="{{ route('login') }}"
            class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-indigo-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2"
        >
            Log in
        </a>
    @endauth
</nav>
