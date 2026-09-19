<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;

/**
 * Extracts structured event details from a promotional poster image and/or
 * a pasted event message, via Gemini's multimodal input — a committee
 * uploads what they already made for promotion instead of retyping the
 * same details into a form. Runs synchronously (a single-image Gemini call
 * is a few seconds, unlike Meeting Minutes' audio transcription), so there's
 * no job/queue/status-enum involved.
 */
class EventExtractionService
{
    public function __construct(private GeminiClient $client) {}

    /**
     * @return array{title: ?string, start_date: ?string, start_time: ?string, end_date: ?string, end_time: ?string, location: ?string, description: ?string}
     */
    public function extract(?string $message, ?UploadedFile $image): array
    {
        $parts = [];

        if ($image) {
            $parts[] = [
                'inline_data' => [
                    'mime_type' => $image->getMimeType(),
                    'data' => base64_encode(file_get_contents($image->getRealPath())),
                ],
            ];
        }

        $parts[] = ['text' => $this->buildPrompt($message)];

        $data = $this->client->parseJson($this->client->generateContent($parts));

        return [
            'title' => $data['title'] ?? null,
            'start_date' => $data['start_date'] ?? null,
            'start_time' => $data['start_time'] ?? null,
            'end_date' => $data['end_date'] ?? null,
            'end_time' => $data['end_time'] ?? null,
            'location' => $data['location'] ?? null,
            'description' => $data['description'] ?? null,
        ];
    }

    private function buildPrompt(?string $message): string
    {
        $today = now()->format('Y-m-d');
        $messageSection = $message
            ? "Promotional message accompanying the poster (if a poster image is also attached, treat this text as authoritative wherever the two disagree):\n{$message}"
            : '';

        return <<<PROMPT
        Extract event details from the attached poster image and/or the message below, whichever is provided. Output ONLY a single valid JSON object (no markdown code fences, no commentary before or after) with this exact shape:

        {
          "title": "... or null",
          "start_date": "YYYY-MM-DD or null if not determinable",
          "start_time": "HH:MM in 24-hour format, or null",
          "end_date": "YYYY-MM-DD or null",
          "end_time": "HH:MM in 24-hour format, or null",
          "location": "... or null",
          "description": "a short 1-3 sentence summary of the event, or null"
        }

        Today's date is {$today}. This event is in Malaysia (Asia/Kuala_Lumpur timezone) — resolve any relative or partial date (e.g. "this Saturday", "25 Disember" with no year, "esok") using today's date as reference. The poster or message may be in Malay, English, or a mix of both. If a field truly cannot be determined, use null rather than guessing.

        {$messageSection}
        PROMPT;
    }
}
