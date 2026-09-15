<?php

namespace App\Livewire\Archive;

use App\Exceptions\ArchiveNotConfiguredException;
use App\Models\ArchivedFile;
use App\Services\ArchiveFileService;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class Index extends Component
{
    use WithFileUploads, WithPagination;

    public $file = null;

    public ?int $confirmingDeleteId = null;

    public ?string $uploadError = null;

    public function mount(): void
    {
        $this->authorize('viewAny', ArchivedFile::class);
    }

    /**
     * Named save(), not upload() — "upload" is a reserved method name on
     * Livewire's client-side $wire object (its built-in file-upload
     * trigger), and a component action sharing that name silently breaks
     * the wire:model="file" upload mechanism itself (UploadManager throws
     * trying to read a file's .name from `undefined`, before any request
     * is even sent — costly to track down without a browser console).
     */
    public function save(ArchiveFileService $archiveService): void
    {
        $this->authorize('create', ArchivedFile::class);

        $this->validate(['file' => ['required', 'file', 'max:5120']]);
        $this->uploadError = null;

        try {
            $archiveService->upload(auth()->user()->organization, auth()->user(), $this->file);
        } catch (ArchiveNotConfiguredException $e) {
            $this->uploadError = $e->getMessage();

            return;
        }

        $this->reset('file');
        session()->flash('status', __('File uploaded.'));
    }

    public function confirmDelete(int $id): void
    {
        $file = ArchivedFile::findOrFail($id);
        $this->authorize('delete', $file);

        $this->confirmingDeleteId = $id;
    }

    public function closeDeleteModal(): void
    {
        $this->confirmingDeleteId = null;
    }

    public function delete(ArchiveFileService $archiveService): void
    {
        $file = ArchivedFile::findOrFail($this->confirmingDeleteId);
        $this->authorize('delete', $file);

        $archiveService->delete($file);
        $this->confirmingDeleteId = null;
    }

    public function download(int $id, ArchiveFileService $archiveService)
    {
        $file = ArchivedFile::findOrFail($id);
        $this->authorize('view', $file);

        $contents = $archiveService->download($file);

        return response()->streamDownload(
            fn () => print ($contents),
            $file->original_name,
            ['Content-Type' => $file->mime_type]
        );
    }

    public function render()
    {
        $setting = auth()->user()->organization?->calendarSetting;

        return view('livewire.archive.index', [
            'files' => ArchivedFile::with('creator')->orderByDesc('created_at')->paginate(15),
            'archiveReady' => (bool) $setting?->isArchiveReady(),
        ]);
    }
}
