<div>
    {{-- Success banner --}}
    @if ($saved)
        <div
            x-data="{ show: true }"
            x-show="show"
            x-init="setTimeout(() => show = false, 3000)"
            x-transition
            class="mb-6 rounded-lg bg-emerald-50 p-3 text-sm text-emerald-700 ring-1 ring-inset ring-emerald-600/20"
        >
            {{ __('Role permissions saved successfully.') }}
        </div>
    @endif

    <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="p-6 pb-4">
            <h3 class="text-base font-semibold text-slate-900">{{ __('Role Permission Matrix') }}</h3>
            <p class="mt-1 text-sm text-slate-500">
                {{ __('Click to toggle permissions for Manager and Staff. Admin always has all permissions and cannot be changed.') }}
            </p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full border-collapse">
                <thead>
                    <tr class="border-y border-slate-200 bg-slate-50">
                        <th class="py-3 pl-6 pr-4 text-left text-xs font-semibold uppercase tracking-wide text-slate-500" style="width: 100%">
                            {{ __('Permission') }}
                        </th>
                        @foreach ($roles as $role)
                            <th class="py-3 text-center text-xs font-semibold uppercase tracking-wide whitespace-nowrap" style="width: 120px; min-width: 120px">
                                @if ($role === \App\Enums\RoleName::Admin)
                                    <span class="inline-flex items-center gap-1 rounded-full bg-violet-100 px-3 py-1 text-xs font-semibold text-violet-700">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                                        </svg>
                                        Admin
                                    </span>
                                @elseif ($role === \App\Enums\RoleName::Manager)
                                    <span class="inline-flex items-center rounded-full bg-indigo-100 px-3 py-1 text-xs font-semibold text-indigo-700">
                                        Manager
                                    </span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">
                                        Staff
                                    </span>
                                @endif
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($permissionGroups as $group => $permissions)
                        {{-- Group heading --}}
                        <tr class="bg-slate-50/70 border-t border-slate-200">
                            <td colspan="{{ count($roles) + 1 }}" class="py-2 pl-6 pr-4 text-xs font-bold uppercase tracking-widest text-slate-400">
                                {{ $group }}
                            </td>
                        </tr>

                        @foreach ($permissions as $permission)
                            <tr class="border-t border-slate-100 hover:bg-slate-50/50 transition-colors">
                                <td class="py-3 pl-6 pr-4 text-sm text-slate-700 font-medium">
                                    {{ $permission->label() }}
                                </td>

                                @foreach ($roles as $role)
                                    @php $checked = $matrix[$role->value][$permission->value] ?? false; @endphp
                                    <td class="px-4 py-3 text-center">
                                        @if ($role === \App\Enums\RoleName::Admin)
                                            {{-- Admin: always checked, locked --}}
                                            <span class="inline-flex items-center justify-center" title="Admin always has this permission">
                                                <svg class="h-6 w-6 text-violet-500" viewBox="0 0 24 24" fill="currentColor">
                                                    <path fill-rule="evenodd" d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12zm13.36-1.814a.75.75 0 10-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 00-1.06 1.06l2.25 2.25a.75.75 0 001.14-.094l3.75-5.25z" clip-rule="evenodd"/>
                                                </svg>
                                            </span>
                                        @else
                                            <button
                                                wire:click="toggle('{{ $role->value }}', '{{ $permission->value }}')"
                                                type="button"
                                                class="inline-flex items-center justify-center rounded-full focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition-colors"
                                                title="{{ $checked ? 'Click to revoke' : 'Click to grant' }}"
                                            >
                                                @if ($checked)
                                                    {{-- Checked: filled indigo circle --}}
                                                    <svg class="h-6 w-6 text-indigo-500 hover:text-indigo-600" viewBox="0 0 24 24" fill="currentColor">
                                                        <path fill-rule="evenodd" d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12zm13.36-1.814a.75.75 0 10-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 00-1.06 1.06l2.25 2.25a.75.75 0 001.14-.094l3.75-5.25z" clip-rule="evenodd"/>
                                                    </svg>
                                                @else
                                                    {{-- Unchecked: empty circle --}}
                                                    <svg class="h-6 w-6 text-slate-300 hover:text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                                        <circle cx="12" cy="12" r="9.75" stroke-dasharray="2 0"/>
                                                    </svg>
                                                @endif
                                            </button>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="flex items-center justify-between border-t border-slate-200 px-6 py-4 bg-slate-50/50 rounded-b-xl">
            <div class="flex items-center gap-6 text-xs text-slate-500">
                <span class="flex items-center gap-1.5">
                    <svg class="h-4 w-4 text-indigo-500" viewBox="0 0 24 24" fill="currentColor">
                        <path fill-rule="evenodd" d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12zm13.36-1.814a.75.75 0 10-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 00-1.06 1.06l2.25 2.25a.75.75 0 001.14-.094l3.75-5.25z" clip-rule="evenodd"/>
                    </svg>
                    Allowed
                </span>
                <span class="flex items-center gap-1.5">
                    <svg class="h-4 w-4 text-slate-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <circle cx="12" cy="12" r="9.75"/>
                    </svg>
                    Not allowed
                </span>
                <span class="flex items-center gap-1.5">
                    <svg class="h-4 w-4 text-violet-500" viewBox="0 0 24 24" fill="currentColor">
                        <path fill-rule="evenodd" d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12zm13.36-1.814a.75.75 0 10-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 00-1.06 1.06l2.25 2.25a.75.75 0 001.14-.094l3.75-5.25z" clip-rule="evenodd"/>
                    </svg>
                    Admin (locked)
                </span>
            </div>
            <button
                wire:click="save"
                wire:loading.attr="disabled"
                type="button"
                class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-60"
            >
                <span wire:loading.remove wire:target="save">{{ __('Save Changes') }}</span>
                <span wire:loading wire:target="save">{{ __('Saving…') }}</span>
            </button>
        </div>
    </div>
</div>
