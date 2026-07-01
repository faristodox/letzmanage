<div x-data="{
    open: false,
    member: null,
    show(data) { this.member = data; this.open = true; },
    close() { this.open = false; this.member = null; }
}">
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

    {{-- Table --}}
    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Nama</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">No. Ahli</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Umur</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Jantina</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">No. Tel</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Naqib</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Peringkat</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500" style="min-width:180px">Jawatankuasa</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500" style="min-width:180px">Usrah Dibawa</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500" style="min-width:160px">Penglibatan Amal</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    @forelse ($members as $member)
                        @php
                            $jkList     = $member->jawatankuasa ?? [];
                            $usrahList  = $member->usrah_dibawa ?? [];
                            $amalList   = $member->penglibatan_amal ?? [];
                            $latestJk   = $jkList ? last($jkList) : null;
                            $latestUsrah = $usrahList ? last($usrahList) : null;
                            $hasMore    = count($jkList) > 1 || count($usrahList) > 1 || count($amalList) > 2;
                            $hasProfile = $latestJk || $latestUsrah || $amalList;
                        @endphp

                        <tr wire:key="member-{{ $member->id }}" class="hover:bg-slate-50">
                            {{-- Basic info --}}
                            <td class="px-4 py-3 text-sm font-medium text-slate-900">{{ $member->nama }}</td>
                            <td class="px-4 py-3 text-sm font-mono text-slate-600">{{ $member->no_ahli }}</td>
                            <td class="px-4 py-3 text-sm text-slate-600">{{ $member->umur }}</td>
                            <td class="px-4 py-3 text-sm">
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium
                                    {{ $member->jantina === 'Lelaki' ? 'bg-blue-50 text-blue-700' : 'bg-pink-50 text-pink-700' }}">
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

                            {{-- Jawatankuasa --}}
                            <td class="px-4 py-3 text-sm">
                                @if ($latestJk)
                                    <p class="font-medium text-slate-800 leading-tight">{{ $latestJk['jawatan'] ?? '—' }}</p>
                                    <p class="text-xs text-slate-500 mt-0.5 leading-tight">{{ $latestJk['nama'] ?? '' }}</p>
                                    @if (count($jkList) > 1)
                                        <button
                                            @click="show(@js(['nama' => $member->nama, 'jawatankuasa' => $jkList, 'usrah_dibawa' => $usrahList, 'penglibatan_amal' => $amalList]))"
                                            class="mt-1 text-xs font-medium text-indigo-500 hover:text-indigo-700">
                                            +{{ count($jkList) - 1 }} lagi
                                        </button>
                                    @endif
                                @else
                                    <span class="text-slate-300">—</span>
                                @endif
                            </td>

                            {{-- Usrah Dibawa --}}
                            <td class="px-4 py-3 text-sm">
                                @if ($latestUsrah)
                                    <p class="font-medium text-slate-800 leading-tight">{{ $latestUsrah['nama'] ?? '—' }}</p>
                                    <p class="text-xs text-slate-500 mt-0.5 leading-tight">
                                        Tahap {{ $latestUsrah['tahap'] ?? '—' }} · {{ $latestUsrah['kategori'] ?? '—' }}
                                    </p>
                                    @if (count($usrahList) > 1)
                                        <button
                                            @click="show(@js(['nama' => $member->nama, 'jawatankuasa' => $jkList, 'usrah_dibawa' => $usrahList, 'penglibatan_amal' => $amalList]))"
                                            class="mt-1 text-xs font-medium text-indigo-500 hover:text-indigo-700">
                                            +{{ count($usrahList) - 1 }} lagi
                                        </button>
                                    @endif
                                @else
                                    <span class="text-slate-300">—</span>
                                @endif
                            </td>

                            {{-- Penglibatan Amal --}}
                            <td class="px-4 py-3 text-sm">
                                @if ($amalList)
                                    <div class="flex flex-wrap gap-1">
                                        @foreach (array_slice($amalList, 0, 2) as $item)
                                            <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">
                                                {{ $item }}
                                            </span>
                                        @endforeach
                                        @if (count($amalList) > 2)
                                            <button
                                                @click="show(@js(['nama' => $member->nama, 'jawatankuasa' => $jkList, 'usrah_dibawa' => $usrahList, 'penglibatan_amal' => $amalList]))"
                                                class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600 hover:bg-slate-200">
                                                +{{ count($amalList) - 2 }}
                                            </button>
                                        @endif
                                    </div>
                                @else
                                    <span class="text-slate-300">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="px-4 py-12 text-center text-sm text-slate-500">
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

    {{-- Detail Modal --}}
    <div x-show="open"
         x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4"
         @keydown.escape.window="close()">

        {{-- Backdrop --}}
        <div class="absolute inset-0 bg-slate-900/50" @click="close()"></div>

        {{-- Panel --}}
        <div class="relative z-10 w-full max-w-2xl rounded-2xl bg-white shadow-xl overflow-y-auto" style="max-height: 85vh">
            <template x-if="member">
                <div>
                    {{-- Header --}}
                    <div class="sticky top-0 flex items-center justify-between border-b border-slate-200 bg-white px-6 py-4">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Profil Ahli</p>
                            <h2 class="text-base font-bold text-slate-900" x-text="member.nama"></h2>
                        </div>
                        <button @click="close()" class="rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-600">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-5 w-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <div class="divide-y divide-slate-100 px-6 py-2">

                        {{-- Jawatankuasa --}}
                        <div class="py-4">
                            <p class="mb-3 text-xs font-bold uppercase tracking-wider text-slate-400">Jawatankuasa</p>
                            <template x-if="member.jawatankuasa && member.jawatankuasa.length > 0">
                                <div class="space-y-2">
                                    <template x-for="(jk, i) in member.jawatankuasa" :key="i">
                                        <div class="flex items-start gap-3 rounded-lg bg-slate-50 px-3 py-2.5">
                                            <div class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-xs font-bold text-indigo-600" x-text="i + 1"></div>
                                            <div class="min-w-0">
                                                <p class="text-sm font-medium text-slate-800" x-text="jk.jawatan || '—'"></p>
                                                <p class="text-xs text-slate-500" x-text="jk.nama || ''"></p>
                                                <p class="text-xs text-slate-400" x-text="jk.tarikh_bentuk || ''"></p>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </template>
                            <template x-if="!member.jawatankuasa || member.jawatankuasa.length === 0">
                                <p class="text-sm text-slate-400">Tiada rekod.</p>
                            </template>
                        </div>

                        {{-- Usrah Yang Dibawa --}}
                        <div class="py-4">
                            <p class="mb-3 text-xs font-bold uppercase tracking-wider text-slate-400">Usrah Yang Dibawa</p>
                            <template x-if="member.usrah_dibawa && member.usrah_dibawa.length > 0">
                                <div class="space-y-2">
                                    <template x-for="(usrah, i) in member.usrah_dibawa" :key="i">
                                        <div class="flex items-start gap-3 rounded-lg bg-slate-50 px-3 py-2.5">
                                            <div class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-violet-100 text-xs font-bold text-violet-600" x-text="i + 1"></div>
                                            <div class="min-w-0">
                                                <p class="text-sm font-medium text-slate-800" x-text="usrah.nama || '—'"></p>
                                                <p class="text-xs text-slate-500">
                                                    <span x-text="'Tahap ' + (usrah.tahap || '—')"></span>
                                                    <span x-show="usrah.kategori"> · <span x-text="usrah.kategori"></span></span>
                                                </p>
                                                <p class="text-xs text-slate-400" x-text="usrah.tarikh_bentuk || ''"></p>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </template>
                            <template x-if="!member.usrah_dibawa || member.usrah_dibawa.length === 0">
                                <p class="text-sm text-slate-400">Tiada rekod.</p>
                            </template>
                        </div>

                        {{-- Penglibatan Amal --}}
                        <div class="py-4">
                            <p class="mb-3 text-xs font-bold uppercase tracking-wider text-slate-400">Penglibatan Amal (Semasa)</p>
                            <template x-if="member.penglibatan_amal && member.penglibatan_amal.length > 0">
                                <div class="flex flex-wrap gap-2">
                                    <template x-for="(item, i) in member.penglibatan_amal" :key="i">
                                        <span class="rounded-full bg-emerald-50 px-3 py-1 text-sm font-medium text-emerald-700" x-text="item"></span>
                                    </template>
                                </div>
                            </template>
                            <template x-if="!member.penglibatan_amal || member.penglibatan_amal.length === 0">
                                <p class="text-sm text-slate-400">Tiada rekod.</p>
                            </template>
                        </div>

                    </div>
                </div>
            </template>
        </div>
    </div>
</div>
