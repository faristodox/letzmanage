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
                {{ __('Toggle which permissions each role has. Admin always has all permissions and cannot be changed here.') }}
            </p>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full">
                <thead>
                    <tr class="border-y border-slate-200 bg-slate-50">
                        <th class="py-3 pl-6 pr-4 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 w-full">
                            {{ __('Permission') }}
                        </th>
                        @foreach ($roles as $role)
                            <th class="px-6 py-3 text-center text-xs font-semibold uppercase tracking-wide text-slate-500 whitespace-nowrap">
                                @if ($role === \App\Enums\RoleName::Admin)
                                    <span class="inline-flex items-center gap-1 rounded-full bg-violet-100 px-2.5 py-0.5 text-xs font-medium text-violet-700">
                                        {{ ucfirst($role->value) }}
                                    </span>
                                @elseif ($role === \App\Enums\RoleName::Manager)
                                    <span class="inline-flex items-center gap-1 rounded-full bg-indigo-100 px-2.5 py-0.5 text-xs font-medium text-indigo-700">
                                        {{ ucfirst($role->value) }}
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600">
                                        {{ ucfirst($role->value) }}
                                    </span>
                                @endif
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($permissionGroups as $group => $permissions)
                        {{-- Group heading row --}}
                        <tr class="bg-slate-50/60">
                            <td colspan="{{ count($roles) + 1 }}" class="py-2 pl-6 pr-4 text-xs font-semibold uppercase tracking-wider text-slate-400">
                                {{ $group }}
                            </td>
                        </tr>

                        @foreach ($permissions as $permission)
                            <tr class="hover:bg-slate-50/50 transition-colors">
                                <td class="py-3 pl-6 pr-4 text-sm text-slate-700">
                                    {{ $permission->label() }}
                                </td>

                                @foreach ($roles as $role)
                                    @php $checked = $matrix[$role->value][$permission->value] ?? false; @endphp
                                    <td class="px-6 py-3 text-center">
                                        @if ($role === \App\Enums\RoleName::Admin)
                                            {{-- Admin: always checked, locked --}}
                                            <span class="inline-flex items-center justify-center">
                                                <svg class="h-5 w-5 text-violet-500" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/>
                                                </svg>
                                            </span>
                                        @else
                                            <button
                                                wire:click="toggle('{{ $role->value }}', '{{ $permission->value }}')"
                                                type="button"
                                                class="inline-flex items-center justify-center rounded focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-1"
                                                title="{{ $checked ? 'Revoke permission' : 'Grant permission' }}"
                                            >
                                                @if ($checked)
                                                    <svg class="h-5 w-5 text-indigo-600" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/>
                                                    </svg>
                                                @else
                                                    <svg class="h-5 w-5 text-slate-200 hover:text-slate-300" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/>
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

        <div class="flex items-center justify-between border-t border-slate-200 px-6 py-4">
            <p class="text-xs text-slate-400">
                {{ __('Changes only apply after you click Save.') }}
            </p>
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
