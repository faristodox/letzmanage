<?php

namespace App\Models;

use App\Contracts\GoogleCalendarCredentialHolder;
use App\Enums\CalendarSyncMode;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasGoogleCalendarCredentials;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'organization_id', 'sync_mode', 'google_account_email',
    'google_access_token', 'google_refresh_token', 'google_token_expires_at',
    'google_calendar_id', 'google_connected_at', 'archive_enabled', 'drive_folder_id',
    'wanita_sheet_url', 'wanita_portfolio_id',
])]
class OrganizationCalendarSetting extends Model implements GoogleCalendarCredentialHolder
{
    use BelongsToOrganization, HasFactory, HasGoogleCalendarCredentials;

    public function wanitaPortfolio(): BelongsTo
    {
        return $this->belongsTo(Portfolio::class, 'wanita_portfolio_id');
    }

    protected function casts(): array
    {
        return [
            'sync_mode' => CalendarSyncMode::class,
            'google_access_token' => 'encrypted',
            'google_refresh_token' => 'encrypted',
            'google_token_expires_at' => 'datetime',
            'google_connected_at' => 'datetime',
            'archive_enabled' => 'boolean',
        ];
    }

    public function isSharedModeConfigured(): bool
    {
        return $this->sync_mode === CalendarSyncMode::Shared && filled($this->google_refresh_token);
    }

    /**
     * Whether a Google account is connected at all, independent of what
     * sync_mode is set to — used to gate the Archive feature, which doesn't
     * care about Calendar's own sync mode.
     */
    public function hasConnectedAccount(): bool
    {
        return filled($this->google_refresh_token);
    }

    public function isArchiveReady(): bool
    {
        return $this->archive_enabled && $this->hasConnectedAccount();
    }

    public function isWanitaSyncConfigured(): bool
    {
        return filled($this->wanita_sheet_url) && $this->hasConnectedAccount();
    }
}
