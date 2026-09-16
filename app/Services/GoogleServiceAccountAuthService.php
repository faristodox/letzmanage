<?php

namespace App\Services;

use App\Exceptions\MeetingNotConfiguredException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Service-account JWT-bearer auth for Google Cloud APIs (Speech-to-Text,
 * Cloud Storage) — distinct from GoogleOAuthService's per-org/per-user
 * consent flow used by Calendar/Drive, since these are billed to a GCP
 * *project* via a service account, not to a specific person's Google
 * account. No SDK: builds and signs the JWT with openssl_sign(), matching
 * this app's plain-HTTP convention for external services.
 */
class GoogleServiceAccountAuthService
{
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    private const SCOPE = 'https://www.googleapis.com/auth/cloud-platform';

    private const CACHE_KEY = 'google-speech-access-token';

    public function getAccessToken(): string
    {
        return Cache::get(self::CACHE_KEY) ?? $this->mintAccessToken();
    }

    private function mintAccessToken(): string
    {
        $credentials = $this->readCredentials();

        $jwt = $this->buildSignedJwt($credentials['client_email'], $credentials['private_key']);

        $result = Http::asForm()->timeout(15)->post(self::TOKEN_URL, [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt,
        ]);

        if ($result->failed()) {
            throw new RuntimeException('Google service-account token exchange failed: '.$result->body());
        }

        $accessToken = $result->json('access_token');
        $expiresIn = $result->json('expires_in');

        // 60s safety margin so a token never expires mid-request.
        Cache::put(self::CACHE_KEY, $accessToken, max(1, $expiresIn - 60));

        return $accessToken;
    }

    /**
     * @return array{client_email: string, private_key: string}
     */
    private function readCredentials(): array
    {
        $path = config('services.google_speech.credentials_path');

        if (! $path || ! is_readable($path)) {
            throw new MeetingNotConfiguredException;
        }

        $credentials = json_decode((string) file_get_contents($path), true);

        if (! is_array($credentials) || empty($credentials['client_email']) || empty($credentials['private_key'])) {
            throw new MeetingNotConfiguredException('Meeting transcription is not set up yet — the Google service-account key file is invalid.');
        }

        return $credentials;
    }

    private function buildSignedJwt(string $clientEmail, string $privateKey): string
    {
        $now = time();

        $header = $this->base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));

        $claims = $this->base64UrlEncode(json_encode([
            'iss' => $clientEmail,
            'scope' => self::SCOPE,
            'aud' => self::TOKEN_URL,
            'iat' => $now,
            'exp' => $now + 3600,
        ]));

        $signingInput = "{$header}.{$claims}";

        if (! openssl_sign($signingInput, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Failed to sign the Google service-account JWT.');
        }

        return $signingInput.'.'.$this->base64UrlEncode($signature);
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
