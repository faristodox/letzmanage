<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component
{
    public bool $isConnected = false;

    public ?string $connectedEmail = null;

    public function mount(): void
    {
        $account = Auth::user()->googleAccount;

        $this->isConnected = (bool) $account?->isConnected();
        $this->connectedEmail = $account?->google_account_email;
    }

    public function disconnect(): void
    {
        Auth::user()->googleAccount?->delete();

        $this->isConnected = false;
        $this->connectedEmail = null;

        $this->dispatch('profile-updated', name: Auth::user()->name);
    }
}; ?>

<section>
    <header>
        <h2 class="text-base font-semibold text-slate-900">
            {{ __('Google Calendar') }}
        </h2>

        <p class="mt-1 text-sm text-slate-500">
            {{ __('Connect your own Google account so your approved bookings appear on your calendar.') }}
        </p>
    </header>

    <div class="mt-6">
        @if ($isConnected)
            <div class="flex items-center justify-between gap-4">
                <p class="text-sm text-slate-700">
                    {{ __('Connected as') }} <span class="font-medium text-slate-900">{{ $connectedEmail }}</span>
                </p>
                <button type="button" wire:click="disconnect" wire:confirm="{{ __('Disconnect your Google account?') }}" class="shrink-0 text-sm font-medium text-red-600 hover:text-red-700">
                    {{ __('Disconnect') }}
                </button>
            </div>
        @else
            <a href="{{ route('profile.google-calendar.connect') }}" class="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-500">
                {{ __('Connect Google Account') }}
            </a>
        @endif
    </div>
</section>
