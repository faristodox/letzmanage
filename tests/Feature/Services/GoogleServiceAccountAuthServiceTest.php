<?php

namespace Tests\Feature\Services;

use App\Exceptions\MeetingNotConfiguredException;
use App\Services\GoogleServiceAccountAuthService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class GoogleServiceAccountAuthServiceTest extends TestCase
{
    // Fixed, test-only RSA key pair (same fixture already used by
    // ChipWebhookTest) — openssl_pkey_new() can't generate keys at runtime
    // on some PHP/Windows installs, but openssl_sign() works fine against a
    // pre-generated key, which is all this service needs.
    private const PRIVATE_KEY = <<<'PEM'
    -----BEGIN PRIVATE KEY-----
    MIIEvQIBADANBgkqhkiG9w0BAQEFAASCBKcwggSjAgEAAoIBAQDjajuvpeG/6b8n
    tXKmNT3iHWrmzKvYksbnZNqA2+S7Vgfl23f9+eWkwIdnripBmo+aKDxiu+4si7Fw
    R91to9TjieTLh8lIBS6q5r4fM5Ym+TGux7R8+MxgHtuGrBlzL2qqk1mH/GzhcK8L
    CaNCJx1mW+7dP7a18eAll0zxf0UGlIhBt7UsMNWPBJbgycUfcWVVonnIcwZtPNSL
    FWlD9JYAZMVWogn1rG/IBXNif2WCbb5PhMbchmghYtp6HAw3bRulZ4wplk/3ssnm
    cStJKzsdkePEJXHEQnlF4/8IjNhO39gY2h8fDovDF0IsqLWXMM0mOf//yD+1DYto
    j6NG3+VRAgMBAAECggEAHAOt6xcW7m6DyQc54p4r66MelGRxpen83SvoG9gavvYb
    3nWw/CUw5CEAfSXwGClLV9zthWcrsaqatt9veE5mjwohWG43fir/QvfOQ2c2L8Ji
    W6rHwd5fRNcASYCBWRZmADHLJWyT2Biqw4QSK7fIUB/ylqg+4H51k4PmK1i3ftYJ
    VegKPSiQKwbFPHOfbSwwNgA3KgP9Svzg4snwRTXOLsW0W4t7Mmr6xSiE58mNl1LT
    zzs8ojjd9wEsDIGn/vc7asLQVRBMszh2IVefc48o5+nqL0cPV0JrEw/+Vsdvzjmr
    WWC+AiaitKaWK18rzB+mb/Kh+KrHPY2sBcPbd6vUTQKBgQD2qrekzYuNDMWy25UO
    O6xD4xLSPirxs9tOTBxVAPV84Y5J0PhyrPNcHnxH2+MbMFWQ6SGV9Pg3Ml1hhIRG
    KqwNs9RLmLho6yFXFnChiSv4x1J3GK8chDVtxffXoOrToRQnwYnad+q6qN9yVFSK
    j7LE0zdHkEOYCazYHFoCkA3DRQKBgQDsBQll2EAiiqKDna2orq83DTgrkmT1a2S/
    NwsOgG55SRQjSDbZjXKy/bEnZNM/O/CdhgAiZAYUVew84z4JBEnQYzMO2VnU1M01
    xxNjWgwKCsCznzj0iJYlmvPrAVAcCzSGyr/p3xxenRELa75+Pe55GLfXNVCrfuxv
    PCLxhHLUnQKBgGqB6G3mut0aqLrECaZtqcJeaCAT6+MVsBoszwb9NQLJOfExpDWP
    7DzYhP1aOsPgqPG7WF8xuYPL4XpcB+lsP4JjJcXGmcnjzS+XZua5Hh17o2X9aI89
    nvxZQN0AhKUApn1MGkQVB2u1w1XQh/iUd7J5KSNjbWxWsSVXiJ4WqXqpAoGBAMjM
    fDNeqSn6AsuxQcbKX52ZrJk9YpF9/efE365FzDd52h0uWiP6+IOZ3LdkS2l4CH0s
    PT8FFDsG4wbmWqf3MwmM7CqM9qVhKvm+1hrnyGhev5XSN/Wrovp5e14L7uj9C4JG
    SsKhpBRG0vKBhz8GV7ZFNlttK6XMRUFZy2zpP3ztAoGAUPgTwIKlPU1uUrK0vH2t
    1jPoAQ+ppBYJP1lxuer115vizUG18NSGghHi5lclX062qyycR3O7iA2B9UgJDttu
    EAS5gD5kp3b2VqTJ1RvkOUQa1kJTmvzKOym7cQtNc55GvkIcrVcBx5AieFgjeH8z
    szhlYSysmhUZqD8fjXrfPT4=
    -----END PRIVATE KEY-----
    PEM;

    protected function tearDown(): void
    {
        $path = config('services.google_speech.credentials_path');

        if ($path && str_contains($path, 'test-credentials') && file_exists($path)) {
            unlink($path);
        }

        parent::tearDown();
    }

    private function writeFakeCredentials(): string
    {
        $path = storage_path('app/test-credentials-'.uniqid().'.json');

        file_put_contents($path, json_encode([
            'client_email' => 'meeting-transcription@letz-manage.iam.gserviceaccount.com',
            'private_key' => self::PRIVATE_KEY,
        ]));

        config(['services.google_speech.credentials_path' => $path]);

        return $path;
    }

    public function test_returns_a_real_looking_access_token_on_success(): void
    {
        $this->writeFakeCredentials();

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'fake-access-token', 'expires_in' => 3600]),
        ]);

        $token = app(GoogleServiceAccountAuthService::class)->getAccessToken();

        $this->assertSame('fake-access-token', $token);
        Http::assertSent(function ($request) {
            return $request->url() === 'https://oauth2.googleapis.com/token'
                && $request['grant_type'] === 'urn:ietf:params:oauth:grant-type:jwt-bearer'
                && substr_count($request['assertion'], '.') === 2;
        });
    }

    public function test_caches_the_token_and_does_not_re_request_it(): void
    {
        $this->writeFakeCredentials();

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'fake-access-token', 'expires_in' => 3600]),
        ]);

        $service = app(GoogleServiceAccountAuthService::class);
        $service->getAccessToken();
        $service->getAccessToken();

        Http::assertSentCount(1);
    }

    public function test_throws_a_friendly_exception_when_credentials_file_is_missing(): void
    {
        config(['services.google_speech.credentials_path' => storage_path('app/does-not-exist.json')]);

        $this->expectException(MeetingNotConfiguredException::class);

        app(GoogleServiceAccountAuthService::class)->getAccessToken();
    }

    public function test_throws_a_runtime_exception_when_google_rejects_the_token_request(): void
    {
        $this->writeFakeCredentials();

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['error' => 'invalid_grant'], 400),
        ]);

        $this->expectException(RuntimeException::class);

        app(GoogleServiceAccountAuthService::class)->getAccessToken();
    }
}
