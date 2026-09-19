<div>
    @if (session('status'))
        <div class="mb-4 rounded-lg bg-emerald-50 p-3 text-sm text-emerald-700 ring-1 ring-inset ring-emerald-600/20">
            {{ session('status') }}
        </div>
    @endif

    @can('create', App\Models\ArchivedFile::class)
        <div class="mb-6 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            @if ($archiveReady)
                <form wire:submit="save" class="flex flex-col gap-3 sm:flex-row sm:items-end">
                    <div class="flex-1">
                        <x-input-label for="file" :value="__('Upload a file')" />
                        <input wire:model="file" id="file" type="file" class="mt-1 block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-indigo-700 hover:file:bg-indigo-100">
                        <div wire:loading wire:target="file" class="mt-1 text-xs text-slate-500">{{ __('Uploading...') }}</div>
                        <p class="mt-1 text-xs text-slate-400">{{ __('Any file type, up to 5MB.') }}</p>
                        <x-input-error :messages="$errors->get('file')" class="mt-2" />
                        @if ($uploadError)
                            <p class="mt-2 text-sm text-red-600">{{ $uploadError }}</p>
                        @endif
                    </div>
                    <x-primary-button type="submit">{{ __('Save to Archive') }}</x-primary-button>
                </form>
            @else
                <p class="text-sm text-slate-600">
                    {{ __('File Archive is not set up yet.') }}
                    @can('viewAny', App\Models\OrganizationCalendarSetting::class)
                        <a href="{{ route('settings.calendar') }}" wire:navigate class="font-medium text-indigo-600 hover:text-indigo-700">{{ __('Connect Google Drive in Settings →') }}</a>
                    @else
                        {{ __('Ask an admin to connect Google Drive in Settings.') }}
                    @endcan
                </p>
            @endif
        </div>
    @endcan

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Name') }}</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Size') }}</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Uploaded By') }}</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Uploaded') }}</th>
                    <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 bg-white">
                @forelse ($files as $archivedFile)
                    <tr wire:key="archived-file-{{ $archivedFile->id }}" class="hover:bg-slate-50">
                        <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-slate-900">{{ $archivedFile->original_name }}</td>
                        <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-600">{{ $archivedFile->humanSize() }}</td>
                        <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-600">{{ $archivedFile->creator?->name ?? __('Unknown') }}</td>
                        <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-500">{{ $archivedFile->created_at->format('d M Y') }}</td>
                        <td class="whitespace-nowrap px-6 py-4 text-right text-sm font-medium space-x-3">
                            @can('view', $archivedFile)
                                <button wire:click="download({{ $archivedFile->id }})" class="text-indigo-600 hover:text-indigo-700">{{ __('Download') }}</button>
                                <span x-data="{
                                        copied: false,
                                        url: @js($archivedFile->driveViewUrl()),
                                        async copy() {
                                            try {
                                                await navigator.clipboard.writeText(this.url);
                                            } catch (e) {}
                                            this.copied = true;
                                            setTimeout(() => this.copied = false, 2000);
                                        }
                                    }">
                                    <button type="button" x-on:click="copy()" class="text-indigo-600 hover:text-indigo-700" x-text="copied ? '{{ __('Copied!') }}' : '{{ __('Copy Link') }}'"></button>
                                </span>
                            @endcan
                            @can('delete', $archivedFile)
                                <button wire:click="confirmDelete({{ $archivedFile->id }})" class="text-red-600 hover:text-red-700">{{ __('Delete') }}</button>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-6 py-8 text-center text-sm text-slate-500">{{ __('No files yet.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </div>

    <div class="mt-4">
        {{ $files->links() }}
    </div>

    <!-- Delete Confirmation Modal -->
    @if ($confirmingDeleteId !== null)
        <div class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0">
            <div class="fixed inset-0 bg-slate-900/50" wire:click="closeDeleteModal"></div>

            <div class="relative mx-auto mb-6 transform overflow-hidden rounded-2xl bg-white shadow-xl transition-all sm:w-full sm:max-w-md">
                <div class="p-6 sm:p-8">
                    <h2 class="text-lg font-semibold text-slate-900">{{ __('Delete File') }}</h2>
                    <p class="mt-2 text-sm text-slate-500">
                        {{ __('Are you sure you want to delete this file? It will also be removed from Google Drive.') }}
                    </p>
                    <div class="mt-6 flex justify-end gap-3">
                        <x-secondary-button type="button" wire:click="closeDeleteModal">{{ __('Cancel') }}</x-secondary-button>
                        <x-danger-button wire:click="delete">{{ __('Delete') }}</x-danger-button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
