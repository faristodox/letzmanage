<?php

namespace App\Services;

use App\Exceptions\MeetingNotConfiguredException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Summarizes a meeting transcript into a structured Minutes of Meeting via
 * the Gemini API. Plain HTTP, no SDK — matching this app's other Google
 * service clients. Uses its own API key (config('services.gemini.*')), not
 * the service-account credentials GoogleServiceAccountAuthService issues —
 * Gemini's Developer API authenticates via a simple key, no JWT exchange.
 */
class GeminiSummaryService
{
    private const BASE_URL = 'https://generativelanguage.googleapis.com/v1beta/';

    /**
     * @param  array<int, array{name: string, position: string}>  $committeeMembers  The
     *         organization's official committee/board roster (see CommitteeMember) —
     *         when given, the prompt asks Gemini to cross-check transcript
     *         attendees against it and prefer the exact registered name and
     *         position, since ASR transcripts can garble names.
     */
    public function summarize(string $transcript, string $meetingTitle, array $committeeMembers = []): string
    {
        return $this->generate($this->buildSummaryPrompt($transcript, $meetingTitle, $committeeMembers));
    }

    /**
     * Translates already-generated Minutes of Meeting text into another
     * language, preserving its section structure — used to offer a Malay
     * version alongside the English one without re-summarizing from the raw
     * transcript (translation is more reliable than asking for an
     * independent summary in a second language).
     */
    public function translate(string $text, string $targetLanguage): string
    {
        return $this->generate($this->buildTranslationPrompt($text, $targetLanguage));
    }

    private function generate(string $prompt): string
    {
        $apiKey = config('services.gemini.api_key');

        if (! $apiKey) {
            throw new MeetingNotConfiguredException('Meeting transcription is not set up yet — the Gemini API key is missing on the server.');
        }

        $model = config('services.gemini.model');

        $result = Http::timeout(60)
            ->post(self::BASE_URL."models/{$model}:generateContent?key={$apiKey}", [
                'contents' => [
                    ['parts' => [['text' => $prompt]]],
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

    private function buildSummaryPrompt(string $transcript, string $meetingTitle, array $committeeMembers = []): string
    {
        $rosterInstruction = $committeeMembers ? $this->buildRosterInstruction($committeeMembers) : '';

        return <<<PROMPT
        Summarize the following meeting transcript into a structured Minutes of Meeting. Use these sections, in this order: Title, Date (only if mentioned in the transcript, otherwise omit), Attendees (only names identifiable from the transcript), Key Discussion Points, Decisions Made, Action Items (owner and item), Next Steps. Output as plain readable text, no markdown formatting symbols.
        {$rosterInstruction}
        Meeting title: {$meetingTitle}

        Transcript:
        {$transcript}
        PROMPT;
    }

    /**
     * @param  array<int, array{name: string, position: string}>  $committeeMembers
     */
    private function buildRosterInstruction(array $committeeMembers): string
    {
        $roster = collect($committeeMembers)
            ->map(fn (array $member) => "- {$member['name']} ({$member['position']})")
            ->implode("\n");

        return <<<INSTRUCTION

        Known committee/board members for this organization:
        {$roster}
        When listing Attendees, match speakers in the transcript against this list (the transcript may misspell or mishear names) and use their exact registered name and position, formatted as "Name (Position)". If someone in the transcript isn't on this list, just list their name as heard, with no position.

        INSTRUCTION;
    }

    private function buildTranslationPrompt(string $text, string $targetLanguage): string
    {
        return <<<PROMPT
        Translate the following Minutes of Meeting into {$targetLanguage}. Keep the exact same section structure and order. Output as plain readable text, no markdown formatting symbols, and do not add any commentary — only the translated document.

        {$text}
        PROMPT;
    }
}
