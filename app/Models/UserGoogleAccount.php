<?php

namespace App\Models;

use App\Contracts\GoogleCalendarCredentialHolder;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasGoogleCalendarCredentials;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'organization_id', 'user_id', 'google_account_email',
    'google_access_token', 'google_refresh_token', 'google_token_expires_at',
    'google_calendar_id', 'google_connected_at',
])]
class UserGoogleAccount extends Model implements GoogleCalendarCredentialHolder
{
    use BelongsToOrganization, HasFactory, HasGoogleCalendarCredentials;

    protected function casts(): array
    {
        return [
            'google_access_token' => 'encrypted',
            'google_refresh_token' => 'encrypted',
            'google_token_expires_at' => 'datetime',
            'google_connected_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isConnected(): bool
    {
        return filled($this->google_refresh_token);
    }
}
