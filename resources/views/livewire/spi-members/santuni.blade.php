<div>
    {{-- Summary + toolbar --}}
    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex flex-wrap items-center gap-2">
            <x-text-input
                wire:model.live.debounce.300ms="search"
                type="text"
                class="w-full sm:w-64"
                placeholder="Cari nama atau no. KP…"
            />

            <select wire:model.live="filterJantina" class="rounded-lg border-slate-200 py-2 text-sm text-slate-700 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">Semua jantina</option>
                <option value="Lelaki">Lelaki</option>
                <option value="Perempuan">Perempuan</option>
            </select>

            <span class="inline-flex items-center rounded-full bg-amber-50 px-3 py-1.5 text-sm font-semibold text-amber-700 ring-1 ring-inset ring-amber-600/20">
                {{ $total }} ahli baru
            </span>
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

    @if ($lastSync)
        <p class="mb-3 text-xs text-slate-400">
            Terakhir disegerak: {{ $lastSync->diffForHumans() }} ({{ $lastSync->format('d M Y, H:i') }})
        </p>
    @endif

    <p class="mb-3 text-xs text-slate-500">
        Senarai ahli baru yang telah diluluskan dan menunggu untuk disantuni (diagihkan kepada usrah/naqib) di kawasan.
        Senarai ini dikemas kini mengikut SPI setiap kali sync — ahli yang telah diproses akan hilang dari senarai.
    </p>

    {{-- Table --}}
    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">#</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Nama</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">No. KP</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Umur</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Peringkat</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Jantina</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Kategori</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">No. Tel</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Tarikh Lulus</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    @forelse ($members as $member)
                        <tr wire:key="santuni-{{ $member->id }}" class="hover:bg-slate-50">
                            <td class="px-4 py-3 text-sm text-slate-400 tabular-nums">{{ $members->firstItem() + $loop->index }}</td>
                            <td class="px-4 py-3 text-sm font-medium text-slate-900">{{ $member->nama }}</td>
                            <td class="px-4 py-3 text-sm font-mono text-slate-600">{{ $member->maskedNoKp() }}</td>
                            <td class="px-4 py-3 text-sm text-slate-600">{{ $member->umur ?: '—' }}</td>
                            <td class="px-4 py-3 text-sm">
                                <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">
                                    {{ $member->peringkat ?: '—' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm">
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $member->jantina === 'Lelaki' ? 'bg-blue-50 text-blue-700' : 'bg-pink-50 text-pink-700' }}">
                                    {{ $member->jantina ?: '—' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm text-slate-600">{{ $member->kategori ?: '—' }}</td>
                            <td class="px-4 py-3 text-sm font-mono text-slate-600">{{ $member->no_tel ?: '—' }}</td>
                            <td class="px-4 py-3 text-sm text-slate-600">{{ $member->tarikh_lulus ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-4 py-12 text-center text-sm text-slate-500">
                                Tiada ahli baru untuk disantuni buat masa ini.
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
