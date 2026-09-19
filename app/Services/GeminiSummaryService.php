<?php

namespace App\Services;

/**
 * Summarizes a meeting transcript into a structured Minutes of Meeting via
 * the Gemini API, through the shared GeminiClient.
 */
class GeminiSummaryService
{
    public function __construct(private GeminiClient $client) {}

    /**
     * @param  array<int, array{name: string, position: string}>  $committeeMembers  The
     *         organization's official committee/board roster (see CommitteeMember) —
     *         when given, the prompt asks Gemini to cross-check transcript
     *         attendees against it and prefer the exact registered name and
     *         position, since ASR transcripts can garble names.
     * @param  array<int, array{name: string, position: string}>  $confirmedAttendees  Attendees
     *         confirmed via the Meeting check-in flow (see Meeting::attendees) —
     *         ground truth, so when given this replaces $committeeMembers
     *         entirely for the Attendees section instead of just informing it.
     */
    public function summarize(string $transcript, string $meetingTitle, array $committeeMembers = [], array $confirmedAttendees = []): string
    {
        return $this->generate($this->buildSummaryPrompt($transcript, $meetingTitle, $committeeMembers, $confirmedAttendees));
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

    /**
     * Structured data for the official Minutes of Meeting print template —
     * a table-shaped agenda (topic/sub-points/responsible party) that the
     * plain-text minutes can't be reliably sliced back into, plus a
     * best-guess attendee list used only as a fallback on the print view
     * when the meeting has no confirmed check-in data.
     *
     * @return array{attendees: array<int, array{name: string, position: string}>, agenda_items: array<int, array{topic: string, sub_points: array<int, string>, action_by: string, notes: string}>}
     */
    public function extractAgendaItems(string $transcript, string $meetingTitle): array
    {
        return $this->parseJson($this->generate($this->buildAgendaExtractionPrompt($transcript, $meetingTitle)));
    }

    /**
     * Translates the structured agenda data above into another language,
     * preserving its exact shape — mirrors translate()'s approach of
     * translating an already-generated artifact rather than re-extracting.
     *
     * @param  array{attendees: array<int, array{name: string, position: string}>, agenda_items: array<int, array{topic: string, sub_points: array<int, string>, action_by: string, notes: string}>}  $data
     * @return array{attendees: array<int, array{name: string, position: string}>, agenda_items: array<int, array{topic: string, sub_points: array<int, string>, action_by: string, notes: string}>}
     */
    public function translateAgendaItems(array $data, string $targetLanguage): array
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE);

        return $this->parseJson($this->generate($this->buildJsonTranslationPrompt($json, $targetLanguage)));
    }

    private function generate(string $prompt): string
    {
        return $this->client->generateContent([['text' => $prompt]]);
    }

    private function buildSummaryPrompt(string $transcript, string $meetingTitle, array $committeeMembers = [], array $confirmedAttendees = []): string
    {
        $attendeeInstruction = match (true) {
            $confirmedAttendees !== [] => $this->buildConfirmedAttendeesInstruction($confirmedAttendees),
            $committeeMembers !== [] => $this->buildRosterInstruction($committeeMembers),
            default => '',
        };

        return <<<PROMPT
        Summarize the following meeting transcript into a structured Minutes of Meeting. Use these sections, in this order: Title, Date (only if mentioned in the transcript, otherwise omit), Attendees (only names identifiable from the transcript), Key Discussion Points, Decisions Made, Action Items (owner and item), Next Steps. Output as plain readable text, no markdown formatting symbols.
        {$attendeeInstruction}
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

    /**
     * @param  array<int, array{name: string, position: string}>  $confirmedAttendees
     */
    private function buildConfirmedAttendeesInstruction(array $confirmedAttendees): string
    {
        $list = collect($confirmedAttendees)
            ->map(fn (array $member) => $member['position'] !== ''
                ? "- {$member['name']} ({$member['position']})"
                : "- {$member['name']}")
            ->implode("\n");

        return <<<INSTRUCTION

        Attendance for this meeting was confirmed via check-in, not guessed from the transcript. Use EXACTLY this list for the Attendees section, verbatim, one per line — do not add, remove, or guess additional names from the transcript, even if other voices are heard:
        {$list}

        INSTRUCTION;
    }

    private function buildTranslationPrompt(string $text, string $targetLanguage): string
    {
        return <<<PROMPT
        Translate the following Minutes of Meeting into {$targetLanguage}. Keep the exact same section structure and order. Output as plain readable text, no markdown formatting symbols, and do not add any commentary — only the translated document.

        {$text}
        PROMPT;
    }

    private function buildAgendaExtractionPrompt(string $transcript, string $meetingTitle): string
    {
        return <<<PROMPT
        Analyze the following meeting transcript and output ONLY a single valid JSON object (no markdown code fences, no commentary before or after) with this exact shape:

        {
          "attendees": [{"name": "...", "position": "..."}],
          "agenda_items": [{"topic": "...", "sub_points": ["...", "..."], "action_by": "...", "notes": "..."}]
        }

        "attendees": everyone identifiable as present, with their position/role if mentioned, otherwise an empty string for position.
        "agenda_items": one entry per distinct topic discussed, in the order discussed. "sub_points" are the specific points raised under that topic, each as its own short string. "action_by" is who is responsible for follow-up on that topic (a name, role, or "Makluman" if it's informational only with no action needed). "notes" is any remark worth recording, or an empty string if none.

        Meeting title: {$meetingTitle}

        Transcript:
        {$transcript}
        PROMPT;
    }

    private function buildJsonTranslationPrompt(string $json, string $targetLanguage): string
    {
        return <<<PROMPT
        Translate every text value in the following JSON into {$targetLanguage}. Keep the exact same JSON structure and keys unchanged — only translate the values. Output ONLY the resulting valid JSON (no markdown code fences, no commentary).

        {$json}
        PROMPT;
    }

    private function parseJson(string $text): array
    {
        return $this->client->parseJson($text);
    }
}
