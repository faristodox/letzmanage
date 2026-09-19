<?php

namespace App\Livewire\Settings;

use App\Enums\CalendarSyncMode;
use App\Enums\HolidaySource;
use App\Enums\MalaysianState;
use App\Models\OrganizationCalendarSetting;
use App\Services\CutiSekolahHolidayService;
use Illuminate\Validation\Rule;
use Livewire\Component;

class Calendar extends Component
{
    public string $syncMode = 'disabled';

    public bool $archiveEnabled = false;

    public bool $isConnected = false;

    public ?string $connectedEmail = null;

    public string $holidaySource = 'google';

    public ?string $holidayState = null;

    public function mount(): void
    {
        $this->authorize('viewAny', OrganizationCalendarSetting::class);

        $setting = auth()->user()->organization?->calendarSetting;

        $this->syncMode = $setting?->sync_mode?->value ?? CalendarSyncMode::Disabled->value;
        $this->archiveEnabled = (bool) $setting?->archive_enabled;
        $this->isConnected = (bool) $setting?->hasConnectedAccount();
        $this->connectedEmail = $setting?->google_account_email;

        $holidaySetting = auth()->user()->organization?->holidayCalendarSetting;

        $this->holidaySource = $holidaySetting?->source?->value ?? HolidaySource::Google->value;
        $this->holidayState = $holidaySetting?->state;
    }

    public function saveHolidaySettings(): void
    {
        $this->authorize('update', new OrganizationCalendarSetting);

        $data = $this->validate([
            'holidaySource' => ['required', Rule::enum(HolidaySource::class)],
            'holidayState' => [Rule::requiredIf($this->holidaySource === HolidaySource::CutiSekolah->value), 'nullable', Rule::enum(MalaysianState::class)],
        ]);

        if ($data['holidaySource'] !== HolidaySource::CutiSekolah->value) {
            $data['holidayState'] = null;
            $this->holidayState = null;
        }

        auth()->user()->organization->holidayCalendarSetting()->updateOrCreate([], [
            'source' => $data['holidaySource'],
            'state' => $data['holidayState'],
        ]);

        $this->dispatch('settings-saved');
    }

    public function syncHolidays(CutiSekolahHolidayService $cutiSekolah): void
    {
        $this->authorize('update', new OrganizationCalendarSetting);

        $this->saveHolidaySettings();

        if ($this->holidaySource === HolidaySource::CutiSekolah->value) {
            $cutiSekolah->sync($this->holidayState);
        }
    }

    public function save(): void
    {
        $this->authorize('update', new OrganizationCalendarSetting);

        $data = $this->validate([
            'syncMode' => ['required', Rule::enum(CalendarSyncMode::class)],
            'archiveEnabled' => ['boolean'],
        ]);

        auth()->user()->organization->calendarSetting()->updateOrCreate([], [
            'sync_mode' => $data['syncMode'],
            'archive_enabled' => $data['archiveEnabled'],
        ]);

        $this->dispatch('settings-saved');
    }

    public function disconnect(): void
    {
        $this->authorize('update', new OrganizationCalendarSetting);

        auth()->user()->organization->calendarSetting?->update([
            'google_account_email' => null,
            'google_access_token' => null,
            'google_refresh_token' => null,
            'google_token_expires_at' => null,
            'google_calendar_id' => null,
            'google_connected_at' => null,
            // Cleared too — a future reconnect might be a different Google
            // account, and a folder id from the old one wouldn't be valid.
            'drive_folder_id' => null,
        ]);

        $this->isConnected = false;
        $this->connectedEmail = null;

        $this->dispatch('settings-saved');
    }

    public function render()
    {
        return view('livewire.settings.calendar');
    }
}
