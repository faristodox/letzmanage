<?php

namespace App\Services;

use App\Exceptions\MeetingNotConfiguredException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Uploads meeting audio to Google Cloud Storage (a transient staging step
 * Speech-to-Text requires for anything longer than ~1 minute — inline bytes
 * only work for very short audio) and drives a LongRunningRecognize job
 * against it. Plain HTTP via GoogleServiceAccountAuthService's token, no
 * SDK, matching this app's other Google service clients.
 */
class GoogleSpeechToTextService
{
    private const GCS_UPLOAD_BASE_URL = 'https://storage.googleapis.com/upload/storage/v1/';

    private const GCS_BASE_URL = 'https://storage.googleapis.com/storage/v1/';

    private const SPEECH_BASE_URL = 'https://speech.googleapis.com/v1/';

    public function __construct(private readonly GoogleServiceAccountAuthService $auth) {}

    public function uploadAudio(string $localPath, string $objectName, string $mimeType): void
    {
        $bucket = $this->bucket();

        $query = http_build_query(['uploadType' => 'media', 'name' => $objectName]);
        $stream = fopen($localPath, 'r');

        try {
            $result = Http::withToken($this->auth->getAccessToken())
                ->withBody($stream, $mimeType)
                ->timeout(120)
                ->post(self::GCS_UPLOAD_BASE_URL."b/{$bucket}/o?{$query}");
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        if ($result->failed()) {
            throw new RuntimeException('Google Cloud Storage upload failed: '.$result->body());
        }
    }

    /**
     * @return string The operation name to poll via checkOperationStatus().
     */
    public function submitLongRunningRecognize(string $gcsUri, string $encoding, string $languageCode): string
    {
        $result = Http::withToken($this->auth->getAccessToken())
            ->timeout(30)
            ->post(self::SPEECH_BASE_URL.'speech:longrunningrecognize', [
                'config' => [
                    'encoding' => $encoding,
                    'languageCode' => $languageCode,
                    'enableAutomaticPunctuation' => true,
                    'model' => 'latest_long',
                ],
                'audio' => [
                    'uri' => $gcsUri,
                ],
            ]);

        if ($result->failed()) {
            throw new RuntimeException('Google Speech-to-Text submission failed: '.$result->body());
        }

        $name = $result->json('name');

        if (! $name) {
            throw new RuntimeException('Google Speech-to-Text submission returned no operation name: '.$result->body());
        }

        return $name;
    }

    /**
     * @return array{done: bool, response: ?array, error: ?array}
     */
    public function checkOperationStatus(string $operationName): array
    {
        // speech:longrunningrecognize returns a bare numeric id, not a
        // "operations/{id}" resource name — normalize either shape here so
        // the caller doesn't need to know which one Google handed back.
        $path = str_starts_with($operationName, 'operations/') ? $operationName : "operations/{$operationName}";

        $result = Http::withToken($this->auth->getAccessToken())
            ->timeout(30)
            ->get(self::SPEECH_BASE_URL.$path);

        if ($result->failed()) {
            throw new RuntimeException('Google Speech-to-Text operation check failed: '.$result->body());
        }

        return [
            'done' => (bool) $result->json('done'),
            'response' => $result->json('response'),
            'error' => $result->json('error'),
        ];
    }

    public function extractTranscript(array $operationResponse): string
    {
        $results = $operationResponse['results'] ?? [];

        return collect($results)
            ->map(fn (array $result) => $result['alternatives'][0]['transcript'] ?? '')
            ->filter()
            ->implode("\n");
    }

    /**
     * Best-effort cleanup of the transient GCS staging object — a 404 means
     * it's already gone, not a failure (matches GoogleDriveService's
     * deleteFile() convention).
     */
    public function deleteObject(string $objectName): void
    {
        $bucket = $this->bucket();

        $result = Http::withToken($this->auth->getAccessToken())
            ->timeout(30)
            ->delete(self::GCS_BASE_URL."b/{$bucket}/o/".rawurlencode($objectName));

        if ($result->failed() && $result->status() !== 404) {
            throw new RuntimeException('Google Cloud Storage object deletion failed: '.$result->body());
        }
    }

    private function bucket(): string
    {
        $bucket = config('services.google_speech.bucket');

        if (! $bucket) {
            throw new MeetingNotConfiguredException('Meeting transcription is not set up yet — the Google Cloud Storage bucket is not configured.');
        }

        return $bucket;
    }
}
