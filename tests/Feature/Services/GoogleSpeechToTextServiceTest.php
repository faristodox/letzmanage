<?php

namespace Tests\Feature\Services;

use App\Exceptions\MeetingNotConfiguredException;
use App\Services\GoogleSpeechToTextService;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class GoogleSpeechToTextServiceTest extends TestCase
{
    // Same test-only RSA key fixture used by GoogleServiceAccountAuthServiceTest.
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

    private ?string $credentialsPath = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->credentialsPath = storage_path('app/test-speech-credentials-'.uniqid().'.json');

        file_put_contents($this->credentialsPath, json_encode([
            'client_email' => 'meeting-transcription@letz-manage.iam.gserviceaccount.com',
            'private_key' => self::PRIVATE_KEY,
        ]));

        config([
            'services.google_speech.credentials_path' => $this->credentialsPath,
            'services.google_speech.bucket' => 'letzmanage-meeting-audio',
        ]);

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'fake-token', 'expires_in' => 3600]),
        ]);
    }

    protected function tearDown(): void
    {
        if ($this->credentialsPath && file_exists($this->credentialsPath)) {
            unlink($this->credentialsPath);
        }

        parent::tearDown();
    }

    public function test_upload_audio_streams_the_file_to_the_configured_bucket(): void
    {
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'fake-token', 'expires_in' => 3600]),
            'https://storage.googleapis.com/upload/storage/v1/b/letzmanage-meeting-audio/o*' => Http::response(['name' => 'meetings/1/test.wav']),
        ]);

        $path = tempnam(sys_get_temp_dir(), 'meeting').'.wav';
        file_put_contents($path, 'fake-audio-bytes');

        app(GoogleSpeechToTextService::class)->uploadAudio($path, 'meetings/1/test.wav', 'audio/wav');

        unlink($path);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'uploadType=media')
            && str_contains($request->url(), 'name=meetings%2F1%2Ftest.wav'));
    }

    public function test_upload_audio_throws_when_bucket_is_not_configured(): void
    {
        config(['services.google_speech.bucket' => null]);

        $this->expectException(MeetingNotConfiguredException::class);

        app(GoogleSpeechToTextService::class)->uploadAudio(tempnam(sys_get_temp_dir(), 'meeting'), 'x', 'audio/wav');
    }

    public function test_submit_long_running_recognize_returns_the_operation_name(): void
    {
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'fake-token', 'expires_in' => 3600]),
            'https://speech.googleapis.com/v1/speech:longrunningrecognize' => Http::response(['name' => '1234567890']),
        ]);

        $name = app(GoogleSpeechToTextService::class)->submitLongRunningRecognize(
            'gs://letzmanage-meeting-audio/meetings/1/test.wav',
            'WEBM_OPUS',
            'en-US'
        );

        $this->assertSame('1234567890', $name);
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'longrunningrecognize')
                && $request['config']['encoding'] === 'WEBM_OPUS'
                && $request['audio']['uri'] === 'gs://letzmanage-meeting-audio/meetings/1/test.wav';
        });
    }

    public function test_check_operation_status_normalizes_a_bare_operation_id(): void
    {
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'fake-token', 'expires_in' => 3600]),
            'https://speech.googleapis.com/v1/operations/1234567890' => Http::response(['done' => true, 'response' => ['results' => []]]),
        ]);

        $status = app(GoogleSpeechToTextService::class)->checkOperationStatus('1234567890');

        $this->assertTrue($status['done']);
    }

    public function test_check_operation_status_reports_not_done(): void
    {
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'fake-token', 'expires_in' => 3600]),
            'https://speech.googleapis.com/v1/operations/*' => Http::response(['done' => false]),
        ]);

        $status = app(GoogleSpeechToTextService::class)->checkOperationStatus('1234567890');

        $this->assertFalse($status['done']);
    }

    public function test_extract_transcript_joins_multiple_results(): void
    {
        $service = app(GoogleSpeechToTextService::class);

        $transcript = $service->extractTranscript([
            'results' => [
                ['alternatives' => [['transcript' => 'Hello everyone.']]],
                ['alternatives' => [['transcript' => 'Let us begin.']]],
            ],
        ]);

        $this->assertSame("Hello everyone.\nLet us begin.", $transcript);
    }

    public function test_extract_transcript_handles_empty_alternatives_gracefully(): void
    {
        $service = app(GoogleSpeechToTextService::class);

        $transcript = $service->extractTranscript([
            'results' => [
                ['alternatives' => [[]], 'resultEndTime' => '0.5s'],
            ],
        ]);

        $this->assertSame('', $transcript);
    }

    public function test_delete_object_treats_404_as_success(): void
    {
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'fake-token', 'expires_in' => 3600]),
            'https://storage.googleapis.com/storage/v1/b/letzmanage-meeting-audio/o/*' => Http::response(['error' => 'not found'], 404),
        ]);

        app(GoogleSpeechToTextService::class)->deleteObject('meetings/1/test.wav');

        $this->assertTrue(true);
    }

    public function test_delete_object_throws_on_a_real_failure(): void
    {
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'fake-token', 'expires_in' => 3600]),
            'https://storage.googleapis.com/storage/v1/b/letzmanage-meeting-audio/o/*' => Http::response(['error' => 'server error'], 500),
        ]);

        $this->expectException(RuntimeException::class);

        app(GoogleSpeechToTextService::class)->deleteObject('meetings/1/test.wav');
    }
}
