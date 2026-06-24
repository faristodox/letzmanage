<div>
    {{-- Stats bar --}}
    <div class="mb-6 grid grid-cols-2 gap-4 sm:grid-cols-4">
        @foreach (['03' => 'Modul 03 (AA)', '04' => 'Modul 04 (AA)', '05' => 'Modul 05 (AT)'] as $lvl => $label)
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ $label }}</p>
                <p class="mt-1 text-2xl font-bold text-slate-900">{{ $stats[$lvl] ?? 0 }}</p>
                <p class="text-xs text-slate-400">ahli</p>
            </div>
        @endforeach
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Jumlah</p>
            <p class="mt-1 text-2xl font-bold text-indigo-600">{{ $stats->sum() }}</p>
            <p class="text-xs text-slate-400">semua peringkat</p>
        </div>
    </div>

    {{-- Toolbar --}}
    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex flex-wrap gap-2">
            <x-text-input
                wire:model.live.debounce.300ms="search"
                type="text"
                class="w-full sm:w-64"
                placeholder="Cari nama, no. ahli, telefon…"
            />

            <select wire:model.live="filterLevel" class="rounded-lg border-slate-200 py-2 text-sm text-slate-700 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">Semua peringkat</option>
                <option value="03">Modul 03</option>
                <option value="04">Modul 04</option>
                <option value="05">Modul 05</option>
            </select>

            <select wire:model.live="filterJantina" class="rounded-lg border-slate-200 py-2 text-sm text-slate-700 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">Semua jantina</option>
                <option value="Lelaki">Lelaki</option>
                <option value="Perempuan">Perempuan</option>
            </select>
        </div>

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

    @if ($syncMessage)
        <div class="mb-4 rounded-lg px-4 py-3 text-sm font-medium {{ $syncSuccess ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700' }}">
            {{ $syncMessage }}
        </div>
    @endif

    @if ($lastSync)
        <p class="mb-3 text-xs text-slate-400">
            Terakhir disegerak: {{ $lastSync->diffForHumans() }} ({{ $lastSync->format('d M Y, H:i') }})
        </p>
    @endif

    {{-- Table with expandable rows --}}
    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200" x-data="{ expanded: null }">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="w-8 px-3 py-3"></th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Nama</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">No. Ahli</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Umur</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Jantina</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">No. Tel</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Naqib</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Peringkat</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    @forelse ($members as $member)
                        @php
                            $latestJk     = $member->jawatankuasa ? last($member->jawatankuasa) : null;
                            $latestUsrah  = $member->usrah_dibawa ? last($member->usrah_dibawa) : null;
                            $hasProfile   = $latestJk || $latestUsrah || $member->penglibatan_amal;
                        @endphp

                        {{-- Main row --}}
                        <tr wire:key="member-{{ $member->id }}"
                            @if ($hasProfile) @click="expanded = expanded === {{ $member->id }} ? null : {{ $member->id }}" class="cursor-pointer hover:bg-slate-50" @else class="hover:bg-slate-50" @endif>

                            <td class="px-3 py-3 text-center">
                                @if ($hasProfile)
                                    <svg :class="expanded === {{ $member->id }} ? 'rotate-90' : ''"
                                         class="mx-auto h-4 w-4 text-slate-400 transition-transform"
                                         xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                                    </svg>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm font-medium text-slate-900">{{ $member->nama }}</td>
                            <td class="px-4 py-3 text-sm font-mono text-slate-600">{{ $member->no_ahli }}</td>
                            <td class="px-4 py-3 text-sm text-slate-600">{{ $member->umur }}</td>
                            <td class="px-4 py-3 text-sm">
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $member->jantina === 'Lelaki' ? 'bg-blue-50 text-blue-700' : 'bg-pink-50 text-pink-700' }}">
                                    {{ $member->jantina }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm font-mono text-slate-600">{{ $member->no_tel ?: '—' }}</td>
                            <td class="px-4 py-3 text-sm text-slate-600">{{ $member->naqib ?: '—' }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold
                                    {{ $member->level === '05' ? 'bg-violet-100 text-violet-700' : ($member->level === '04' ? 'bg-indigo-100 text-indigo-700' : 'bg-slate-100 text-slate-700') }}">
                                    {{ \App\Models\SpiMember::levelLabel($member->level) }}
                                </span>
                            </td>
                        </tr>

                        {{-- Expandable profile row --}}
                        @if ($hasProfile)
                            <tr wire:key="profile-{{ $member->id }}" x-show="expanded === {{ $member->id }}" x-cloak>
                                <td></td>
                                <td colspan="7" class="border-t border-slate-100 bg-slate-50 px-4 py-4">
                                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">

                                        {{-- JK Terkini --}}
                                        <div>
                                            <p class="mb-1.5 text-xs font-semibold uppercase tracking-wider text-slate-400">Jawatankuasa</p>
                                            @if ($latestJk)
                                                <p class="text-sm font-medium text-slate-800">{{ $latestJk['jawatan'] ?? '—' }}</p>
                                                <p class="text-xs text-slate-500">{{ $latestJk['nama'] ?? '' }}</p>
                                                @if (!empty($latestJk['tarikh_bentuk']))
                                                    <p class="mt-0.5 text-xs text-slate-400">{{ $latestJk['tarikh_bentuk'] }}</p>
                                                @endif
                                                @if (count($member->jawatankuasa) > 1)
                                                    <p class="mt-1 text-xs text-indigo-500">+{{ count($member->jawatankuasa) - 1 }} lagi</p>
                                                @endif
                                            @else
                                                <p class="text-xs text-slate-400">—</p>
                                            @endif
                                        </div>

                                        {{-- Usrah Dibawa --}}
                                        <div>
                                            <p class="mb-1.5 text-xs font-semibold uppercase tracking-wider text-slate-400">Usrah Yang Dibawa</p>
                                            @if ($latestUsrah)
                                                <p class="text-sm font-medium text-slate-800">{{ $latestUsrah['nama'] ?? '—' }}</p>
                                                <p class="text-xs text-slate-500">
                                                    Tahap {{ $latestUsrah['tahap'] ?? '—' }} · {{ $latestUsrah['kategori'] ?? '—' }}
                                                </p>
                                                @if (!empty($latestUsrah['tarikh_bentuk']))
                                                    <p class="mt-0.5 text-xs text-slate-400">{{ $latestUsrah['tarikh_bentuk'] }}</p>
                                                @endif
                                                @if (count($member->usrah_dibawa) > 1)
                                                    <p class="mt-1 text-xs text-indigo-500">+{{ count($member->usrah_dibawa) - 1 }} lagi</p>
                                                @endif
                                            @else
                                                <p class="text-xs text-slate-400">—</p>
                                            @endif
                                        </div>

                                        {{-- Penglibatan Amal --}}
                                        <div>
                                            <p class="mb-1.5 text-xs font-semibold uppercase tracking-wider text-slate-400">Penglibatan Amal</p>
                                            @if ($member->penglibatan_amal)
                                                <div class="flex flex-wrap gap-1">
                                                    @foreach ($member->penglibatan_amal as $item)
                                                        <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">
                                                            {{ $item }}
                                                        </span>
                                                    @endforeach
                                                </div>
                                            @else
                                                <p class="text-xs text-slate-400">—</p>
                                            @endif
                                        </div>

                                    </div>
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-12 text-center text-sm text-slate-500">
                                Tiada rekod ditemui.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($members->hasPages())
            <div class="border-t border-slate-200 px-4 py-3">
                {{ $members->links() }}
            </div>
        @endif
    </div>
</div>
