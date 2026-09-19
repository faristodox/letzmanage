<?php

namespace App\Services;

use App\Exceptions\MeetingNotConfiguredException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Low-level Gemini `generateContent` client — plain HTTP, no SDK, matching
 * this app's other Google service clients. Uses its own API key
 * (config('services.gemini.*')), not the service-account credentials
 * GoogleServiceAccountAuthService issues — Gemini's Developer API
 * authenticates via a simple key, no JWT exchange. Shared by
 * GeminiSummaryService (text-only prompts) and EventExtractionService
 * (text + inline image), since both need identical request/error handling.
 */
class GeminiClient
{
    private const BASE_URL = 'https://generativelanguage.googleapis.com/v1beta/';

    /**
     * @param  array<int, array<string, mixed>>  $parts  One or more Gemini
     *         content parts, e.g. [['text' => '...']] or
     *         [['inline_data' => ['mime_type' => ..., 'data' => ...]], ['text' => '...']].
     */
    public function generateContent(array $parts): string
    {
        $apiKey = config('services.gemini.api_key');

        if (! $apiKey) {
            throw new MeetingNotConfiguredException('This feature is not set up yet — the Gemini API key is missing on the server.');
        }

        $model = config('services.gemini.model');

        $result = Http::timeout(60)
            ->post(self::BASE_URL."models/{$model}:generateContent?key={$apiKey}", [
                'contents' => [
                    ['parts' => $parts],
                ],
            ]);

        if ($result->failed()) {
            throw new RuntimeException('Gemini request failed: '.$result->body());
        }

        $text = $result->json('candidates.0.content.parts.0.text');

        if (! $text) {
            throw new RuntimeException('Gemini returned no usable content: '.$result->body());
        }

        return $text;
    }

    /**
     * Gemini is asked for strict JSON but sometimes wraps it in ```json
     * fences despite the instruction not to — strip those before decoding.
     */
    public function parseJson(string $text): array
    {
        $cleaned = trim(preg_replace('/^```(?:json)?|```$/m', '', trim($text)));
        $decoded = json_decode($cleaned, true);

        if (! is_array($decoded)) {
            throw new RuntimeException('Gemini returned invalid JSON: '.$text);
        }

        return $decoded;
    }
}
