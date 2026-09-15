<?php

namespace App\Contracts;

use Illuminate\Support\Carbon;

/**
 * A model that holds one connected Google account's OAuth tokens for
 * Calendar sync — either an organization's single shared account
 * (OrganizationCalendarSetting) or one staff member's own account
 * (UserGoogleAccount). GoogleCalendarService and the sync job depend on
 * this interface, not the concrete models, so both connection types are
 * handled identically.
 */
interface GoogleCalendarCredentialHolder
{
    public function googleAccessToken(): ?string;

    public function googleRefreshToken(): ?string;

    public function googleTokenExpiresAt(): ?Carbon;

    public function googleCalendarId(): string;

    public function isTokenExpired(): bool;

    public function updateGoogleTokens(string $accessToken, ?string $refreshToken, Carbon $expiresAt): void;
}
