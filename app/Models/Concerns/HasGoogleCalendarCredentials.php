<?php

namespace App\Models\Concerns;

use Illuminate\Support\Carbon;

/**
 * Shared implementation of the GoogleCalendarCredentialHolder contract for
 * models that store google_access_token/google_refresh_token/etc columns
 * (OrganizationCalendarSetting and UserGoogleAccount).
 */
trait HasGoogleCalendarCredentials
{
    public function googleAccessToken(): ?string
    {
        return $this->google_access_token;
    }

    public function googleRefreshToken(): ?string
    {
        return $this->google_refresh_token;
    }

    public function googleTokenExpiresAt(): ?Carbon
    {
        return $this->google_token_expires_at;
    }

    public function googleCalendarId(): string
    {
        return $this->google_calendar_id ?: 'primary';
    }

    public function isTokenExpired(): bool
    {
        return $this->google_token_expires_at === null || $this->google_token_expires_at->isPast();
    }

    /**
     * Persist a refreshed access token. Google's refresh response omits
     * refresh_token unless a new one was actually issued, so a null here
     * means "keep the one we already have," not "clear it."
     */
    public function updateGoogleTokens(string $accessToken, ?string $refreshToken, Carbon $expiresAt): void
    {
        $this->update([
            'google_access_token' => $accessToken,
            'google_refresh_token' => $refreshToken ?? $this->google_refresh_token,
            'google_token_expires_at' => $expiresAt,
        ]);
    }
}
