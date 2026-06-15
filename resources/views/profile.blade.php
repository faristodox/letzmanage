<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ __('Profile') }}</h1>
            <p class="mt-1 text-sm text-slate-500">{{ __('Manage your account information and security settings.') }}</p>
        </div>
    </x-slot>

    <div class="max-w-3xl space-y-6">
        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
            <livewire:profile.update-profile-information-form />
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
            <livewire:profile.update-password-form />
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
            <livewire:profile.delete-user-form />
        </div>
    </div>
</x-app-layout>
