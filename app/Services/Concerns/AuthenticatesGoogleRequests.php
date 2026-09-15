<?php

namespace App\Services\Concerns;

use App\Contracts\GoogleCalendarCredentialHolder;
use App\Services\GoogleOAuthService;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Shared by GoogleCalendarService and GoogleDriveService: refreshes the
 * holder's access token first if it's expired (persisting the refreshed
 * token back onto the holder), then returns an authenticated HTTP client
 * scoped to the given base URL.
 */
trait AuthenticatesGoogleRequests
{
    private function authenticatedClient(GoogleCalendarCredentialHolder $holder, GoogleOAuthService $oauth, string $baseUrl): PendingRequest
    {
        if ($holder->isTokenExpired()) {
            $tokens = $oauth->refreshAccessToken($holder->googleRefreshToken());
            $holder->updateGoogleTokens($tokens['access_token'], null, now()->addSeconds($tokens['expires_in']));
        }

        // A bounded timeout so a stalled connection to Google fails loudly
        // instead of hanging the request indefinitely.
        return Http::withToken($holder->googleAccessToken())->timeout(15)->baseUrl($baseUrl);
    }
}
