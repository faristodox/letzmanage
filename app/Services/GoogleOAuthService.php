<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Thin client for Google's OAuth2 endpoints, used to connect a Google
 * Calendar account (either the organization's shared account or one staff
 * member's own account — see GoogleCalendarConnectionController). No SDK,
 * plain HTTP calls, matching ChipPaymentService's style.
 */
class GoogleOAuthService
{
    private const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';

    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    private const USERINFO_URL = 'https://www.googleapis.com/oauth2/v3/userinfo';

    // calendar.events for event CRUD, drive.file for the Archive feature
    // (access only to files/folders this app itself creates, not the whole
    // Drive), userinfo.email so exchangeCodeForTokens() can attribute the
    // connection to an email address via the userinfo endpoint — that call
    // 401s with "Invalid Credentials" without this scope, even though the
    // calendar/drive-only token itself is otherwise valid.
    private const SCOPE = 'https://www.googleapis.com/auth/calendar.events https://www.googleapis.com/auth/drive.file https://www.googleapis.com/auth/userinfo.email';

    public function buildAuthorizationUrl(string $redirectUri, string $state): string
    {
        $query = http_build_query([
            'client_id' => config('services.google_calendar.client_id'),
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => self::SCOPE,
            'access_type' => 'offline',
            // Forces Google to re-issue a refresh_token even on a reconnect —
            // without this, only the very first consent grant ever returns one.
            'prompt' => 'consent',
            'state' => $state,
        ]);

        return self::AUTH_URL.'?'.$query;
    }

    /**
     * @return array{access_token: string, refresh_token: ?string, expires_in: int, email: string}
     */
    public function exchangeCodeForTokens(string $code, string $redirectUri): array
    {
        $result = Http::asForm()->timeout(15)->post(self::TOKEN_URL, [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'client_id' => config('services.google_calendar.client_id'),
            'client_secret' => config('services.google_calendar.client_secret'),
            'redirect_uri' => $redirectUri,
        ]);

        if ($result->failed()) {
            throw new RuntimeException('Google token exchange failed: '.$result->body());
        }

        $tokens = $result->json();

        $userinfo = Http::withToken($tokens['access_token'])->timeout(15)->get(self::USERINFO_URL);

        if ($userinfo->failed()) {
            throw new RuntimeException('Google userinfo retrieval failed: '.$userinfo->body());
        }

        return [
            'access_token' => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'] ?? null,
            'expires_in' => $tokens['expires_in'],
            'email' => $userinfo->json('email'),
        ];
    }

    /**
     * @return array{access_token: string, expires_in: int}
     */
    public function refreshAccessToken(string $refreshToken): array
    {
        $result = Http::asForm()->timeout(15)->post(self::TOKEN_URL, [
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
            'client_id' => config('services.google_calendar.client_id'),
            'client_secret' => config('services.google_calendar.client_secret'),
        ]);

        if ($result->failed()) {
            throw new RuntimeException('Google token refresh failed: '.$result->body());
        }

        $tokens = $result->json();

        return [
            'access_token' => $tokens['access_token'],
            'expires_in' => $tokens['expires_in'],
        ];
    }
}
