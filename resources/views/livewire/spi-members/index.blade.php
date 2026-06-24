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

    {{-- Sync result message --}}
    @if ($syncMessage)
        <div class="mb-4 rounded-lg px-4 py-3 text-sm font-medium {{ $syncSuccess ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700' }}">
            {{ $syncMessage }}
        </div>
    @endif

    {{-- Last sync info --}}
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
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">No. KP</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Umur</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Jantina</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Ktgr</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">No. Tel</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Naqib</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Peringkat</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    @forelse ($members as $member)
                        <tr wire:key="member-{{ $member->id }}" class="hover:bg-slate-50">
                            <td class="px-4 py-3 text-sm font-medium text-slate-900">{{ $member->nama }}</td>
                            <td class="px-4 py-3 text-sm text-slate-600 font-mono">{{ $member->no_ahli }}</td>
                            <td class="px-4 py-3 text-sm text-slate-500 font-mono">{{ $member->maskedNoKp() }}</td>
                            <td class="px-4 py-3 text-sm text-slate-600">{{ $member->umur }}</td>
                            <td class="px-4 py-3 text-sm">
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $member->jantina === 'Lelaki' ? 'bg-blue-50 text-blue-700' : 'bg-pink-50 text-pink-700' }}">
                                    {{ $member->jantina }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm text-slate-600">{{ $member->kategori }}</td>
                            <td class="px-4 py-3 text-sm text-slate-600 font-mono">{{ $member->no_tel ?: '—' }}</td>
                            <td class="px-4 py-3 text-sm text-slate-600">{{ $member->naqib ?: '—' }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold
                                    {{ $member->level === '05' ? 'bg-violet-100 text-violet-700' : ($member->level === '04' ? 'bg-indigo-100 text-indigo-700' : 'bg-slate-100 text-slate-700') }}">
                                    {{ \App\Models\SpiMember::levelLabel($member->level) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <button type="button" wire:click="showDetail({{ $member->id }})"
                                    class="text-xs font-medium text-indigo-600 hover:text-indigo-800">
                                    Profil
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-12 text-center text-sm text-slate-500">
                                Tiada rekod ditemui. Cuba klik "Sync dari SPI" untuk mengambil data terkini.
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

    {{-- Profile detail modal --}}
    @if ($detailMember)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50"
             wire:click.self="closeDetail">
            <div class="w-full max-w-2xl max-h-[90vh] overflow-y-auto rounded-2xl bg-white shadow-xl">
                <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
                    <div>
                        <h2 class="text-base font-bold text-slate-900">{{ $detailMember->nama }}</h2>
                        <p class="text-xs text-slate-500">{{ $detailMember->no_ahli }} &middot; {{ \App\Models\SpiMember::levelLabel($detailMember->level) }}</p>
                    </div>
                    <button type="button" wire:click="closeDetail" class="text-slate-400 hover:text-slate-600">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="space-y-6 px-6 py-5">
                    {{-- Jawatankuasa --}}
                    <div>
                        <h3 class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-500">Jawatankuasa</h3>
                        @if ($detailMember->jawatankuasa)
                            <div class="overflow-x-auto rounded-lg border border-slate-200">
                                <table class="min-w-full divide-y divide-slate-200 text-xs">
                                    <thead class="bg-slate-50">
                                        <tr>
                                            <th class="px-3 py-2 text-left font-semibold text-slate-500">Nama JK</th>
                                            <th class="px-3 py-2 text-left font-semibold text-slate-500">Jawatan</th>
                                            <th class="px-3 py-2 text-left font-semibold text-slate-500">Tarikh</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 bg-white">
                                        @foreach ($detailMember->jawatankuasa as $jk)
                                            <tr>
                                                <td class="px-3 py-2 text-slate-800">{{ $jk['nama'] ?? '—' }}</td>
                                                <td class="px-3 py-2 text-slate-600">{{ $jk['jawatan'] ?? '—' }}</td>
                                                <td class="px-3 py-2 text-slate-500">{{ $jk['tarikh_bentuk'] ?? '—' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <p class="text-xs text-slate-400">Tiada rekod jawatankuasa.</p>
                        @endif
                    </div>

                    {{-- Usrah dibawa --}}
                    <div>
                        <h3 class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-500">Usrah Yang Dibawa</h3>
                        @if ($detailMember->usrah_dibawa)
                            <div class="overflow-x-auto rounded-lg border border-slate-200">
                                <table class="min-w-full divide-y divide-slate-200 text-xs">
                                    <thead class="bg-slate-50">
                                        <tr>
                                            <th class="px-3 py-2 text-left font-semibold text-slate-500">Nama Usrah</th>
                                            <th class="px-3 py-2 text-left font-semibold text-slate-500">Tahap</th>
                                            <th class="px-3 py-2 text-left font-semibold text-slate-500">Kategori</th>
                                            <th class="px-3 py-2 text-left font-semibold text-slate-500">Tarikh Bentuk</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 bg-white">
                                        @foreach ($detailMember->usrah_dibawa as $usrah)
                                            <tr>
                                                <td class="px-3 py-2 text-slate-800">{{ $usrah['nama'] ?? '—' }}</td>
                                                <td class="px-3 py-2 text-slate-600">{{ $usrah['tahap'] ?? '—' }}</td>
                                                <td class="px-3 py-2 text-slate-600">{{ $usrah['kategori'] ?? '—' }}</td>
                                                <td class="px-3 py-2 text-slate-500">{{ $usrah['tarikh_bentuk'] ?? '—' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <p class="text-xs text-slate-400">Tiada rekod usrah dibawa.</p>
                        @endif
                    </div>

                    {{-- Penglibatan amal --}}
                    <div>
                        <h3 class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-500">Penglibatan Amal (Semasa)</h3>
                        @if ($detailMember->penglibatan_amal)
                            <div class="flex flex-wrap gap-2">
                                @foreach ($detailMember->penglibatan_amal as $item)
                                    <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-700">
                                        {{ $item }}
                                    </span>
                                @endforeach
                            </div>
                        @else
                            <p class="text-xs text-slate-400">Tiada rekod penglibatan amal.</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
