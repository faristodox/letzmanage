<div>
    {{-- Summary + toolbar --}}
    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex flex-wrap items-center gap-2">
            <x-text-input
                wire:model.live.debounce.300ms="search"
                type="text"
                class="w-full sm:w-64"
                placeholder="Cari nama naqib atau ahli…"
            />

            <select wire:model.live="filterLevel" class="rounded-lg border-slate-200 py-2 text-sm text-slate-700 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">Semua tahap</option>
                @foreach ($levels as $level)
                    <option value="{{ $level }}">{{ \App\Models\SpiMember::levelLabel($level) }}</option>
                @endforeach
            </select>

            <select wire:model.live="filterKawasan" class="rounded-lg border-slate-200 py-2 text-sm text-slate-700 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">Semua kawasan</option>
                @foreach ($kawasanOptions as $kawasan)
                    <option value="{{ $kawasan }}">{{ $kawasan }}</option>
                @endforeach
                @if ($hasBlankKawasan)
                    <option value="{{ \App\Livewire\SpiMembers\NaqibUsrah::NO_KAWASAN }}">Tiada kawasan</option>
                @endif
            </select>

            <span class="inline-flex items-center rounded-full bg-indigo-50 px-3 py-1.5 text-sm font-semibold text-indigo-700 ring-1 ring-inset ring-indigo-600/20">
                {{ $total }} kumpulan usrah
            </span>
        </div>

        <div class="flex gap-2">
        <button
            type="button"
            wire:click="export"
            class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50"
        >
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-4 w-4">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
            </svg>
            Export CSV
        </button>

        <button
            type="button"
            wire:click="sync"
            wire:loading.attr="disabled"
            class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 disabled:opacity-60"
        >
            <svg wire:loading wire:target="sync" class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
            </svg>
            <svg wire:loading.remove wire:target="sync" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-4 w-4">
                <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
            </svg>
            <span wire:loading.remove wire:target="sync">Sync dari SPI</span>
            <span wire:loading wire:target="sync">Menyegerak…</span>
        </button>
        </div>
    </div>

    @if ($lastSync)
        <p class="mb-3 text-xs text-slate-400">
            Terakhir disegerak: {{ $lastSync->diffForHumans() }} ({{ $lastSync->format('d M Y, H:i') }})
        </p>
    @endif

    <p class="mb-3 text-xs text-slate-500">
        Senarai naqib dan kumpulan usrah (tahap 00–05) di kawasan, mengikut SPI. Kumpulan tanpa naqib dipaparkan sebagai "Grup Sementara".
        Senarai ini dikemas kini mengikut SPI setiap kali sync.
    </p>

    {{-- Table --}}
    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">#</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Tahap</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Naqib</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Jenis</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Kategori</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Tarikh Mula</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Kawasan</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Ahli Usrah</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    @forelse ($groups as $group)
                        <tr wire:key="naqib-usrah-{{ $group->id }}" class="hover:bg-slate-50">
                            <td class="px-4 py-3 text-sm text-slate-400 tabular-nums">{{ $groups->firstItem() + $loop->index }}</td>
                            <td class="px-4 py-3 text-sm">
                                <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">
                                    {{ $group->level ? \App\Models\SpiMember::levelLabel($group->level) : '—' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm font-medium text-slate-900">
                                @if ($group->is_temporary_group)
                                    <span class="text-amber-600">{{ $group->displayName() }}</span>
                                @else
                                    {{ $group->displayName() }}
                                @endif
                                @if ($group->usrah_name && $group->usrah_name !== $group->naqib_name)
                                    <p class="text-xs font-normal text-slate-400">{{ $group->usrah_name }}</p>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm">
                                @if ($group->status)
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ strtolower($group->status) === 'active' ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">
                                        {{ $group->status }}
                                    </span>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm">
                                @if ($group->jenis)
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $group->jenis === 'Lelaki' ? 'bg-blue-50 text-blue-700' : 'bg-pink-50 text-pink-700' }}">
                                        {{ $group->jenis }}
                                    </span>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm text-slate-600">{{ $group->kategori ?: '—' }}</td>
                            <td class="px-4 py-3 text-sm text-slate-600">{{ $group->tarikh_mula ?: '—' }}</td>
                            <td class="px-4 py-3 text-sm text-slate-600">{{ $group->kawasan ?: '—' }}</td>
                            <td class="px-4 py-3 text-sm text-slate-600">
                                @if ($group->member_count > 0)
                                    <details>
                                        <summary class="cursor-pointer font-medium text-indigo-600">{{ $group->member_count }} ahli</summary>
                                        <ul class="mt-1.5 space-y-0.5 text-xs text-slate-500">
                                            @foreach ($group->members ?? [] as $member)
                                                <li>
                                                    {{ $member['nama'] ?? '—' }}
                                                    @if (! empty($member['jawatan'])) · {{ $member['jawatan'] }} @endif
                                                    @if (! empty($member['no_tel'])) · {{ $member['no_tel'] }}@endif
                                                </li>
                                            @endforeach
                                        </ul>
                                    </details>
                                @else
                                    <span class="text-slate-400">Tiada ahli</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-4 py-12 text-center text-sm text-slate-500">
                                Tiada data naqib/usrah buat masa ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($groups->hasPages())
            <div class="border-t border-slate-200 px-4 py-3">
                {{ $groups->links() }}
            </div>
        @endif
    </div>
</div>
