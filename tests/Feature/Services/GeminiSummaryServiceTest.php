<?php

namespace Tests\Feature\Services;

use App\Exceptions\MeetingNotConfiguredException;
use App\Services\GeminiSummaryService;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class GeminiSummaryServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['services.gemini.api_key' => 'test-key', 'services.gemini.model' => 'gemini-flash-latest']);
    }

    public function test_returns_the_generated_minutes_text(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    ['content' => ['parts' => [['text' => 'Title: Usrah Session'."\n".'Attendees: Faris, Ahmad']]]],
                ],
            ]),
        ]);

        $minutes = app(GeminiSummaryService::class)->summarize('Faris: hello. Ahmad: hi.', 'Usrah Session');

        $this->assertStringContainsString('Usrah Session', $minutes);
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'generateContent')
                && str_contains($request->url(), 'key=test-key')
                && str_contains($request['contents'][0]['parts'][0]['text'], 'Faris: hello. Ahmad: hi.');
        });
    }

    public function test_throws_when_api_key_is_missing(): void
    {
        config(['services.gemini.api_key' => null]);

        $this->expectException(MeetingNotConfiguredException::class);

        app(GeminiSummaryService::class)->summarize('transcript', 'title');
    }

    public function test_throws_a_runtime_exception_on_http_failure(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response(['error' => 'bad request'], 400),
        ]);

        $this->expectException(RuntimeException::class);

        app(GeminiSummaryService::class)->summarize('transcript', 'title');
    }

    public function test_throws_a_runtime_exception_when_candidates_are_empty(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response(['candidates' => []]),
        ]);

        $this->expectException(RuntimeException::class);

        app(GeminiSummaryService::class)->summarize('transcript', 'title');
    }

    public function test_returns_the_translated_text(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    ['content' => ['parts' => [['text' => 'Tajuk: Sesi Usrah']]]],
                ],
            ]),
        ]);

        $translated = app(GeminiSummaryService::class)->translate('Title: Usrah Session', 'Malay (Bahasa Malaysia)');

        $this->assertSame('Tajuk: Sesi Usrah', $translated);
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'generateContent')
                && str_contains($request['contents'][0]['parts'][0]['text'], 'Translate the following Minutes of Meeting')
                && str_contains($request['contents'][0]['parts'][0]['text'], 'Malay (Bahasa Malaysia)')
                && str_contains($request['contents'][0]['parts'][0]['text'], 'Title: Usrah Session');
        });
    }

    public function test_translate_throws_a_runtime_exception_on_http_failure(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response(['error' => 'bad request'], 400),
        ]);

        $this->expectException(RuntimeException::class);

        app(GeminiSummaryService::class)->translate('Title: Usrah Session', 'Malay (Bahasa Malaysia)');
    }

    public function test_summarize_includes_the_committee_roster_when_given(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => 'Title: Usrah Session']]]]],
            ]),
        ]);

        app(GeminiSummaryService::class)->summarize('Faris: hello.', 'Usrah Session', [
            ['name' => 'Ahmad Zaki', 'position' => 'President'],
            ['name' => 'Siti Aminah', 'position' => 'Secretary'],
        ]);

        Http::assertSent(function ($request) {
            $prompt = $request['contents'][0]['parts'][0]['text'];

            return str_contains($prompt, 'Known committee/board members')
                && str_contains($prompt, 'Ahmad Zaki (President)')
                && str_contains($prompt, 'Siti Aminah (Secretary)');
        });
    }

    public function test_summarize_omits_the_roster_instruction_when_none_given(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => 'Title: Usrah Session']]]]],
            ]),
        ]);

        app(GeminiSummaryService::class)->summarize('Faris: hello.', 'Usrah Session');

        Http::assertSent(function ($request) {
            $prompt = $request['contents'][0]['parts'][0]['text'];

            return ! str_contains($prompt, 'Known committee/board members');
        });
    }

    public function test_confirmed_attendees_override_the_roster_instruction(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => 'Title: Usrah Session']]]]],
            ]),
        ]);

        app(GeminiSummaryService::class)->summarize(
            'Faris: hello.',
            'Usrah Session',
            [['name' => 'Someone Else', 'position' => 'Treasurer']],
            [['name' => 'Ahmad Zaki', 'position' => 'President']],
        );

        Http::assertSent(function ($request) {
            $prompt = $request['contents'][0]['parts'][0]['text'];

            return str_contains($prompt, 'confirmed via check-in')
                && str_contains($prompt, 'Ahmad Zaki (President)')
                && ! str_contains($prompt, 'Known committee/board members')
                && ! str_contains($prompt, 'Someone Else');
        });
    }

    public function test_extract_agenda_items_parses_the_json_response(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => json_encode([
                    'attendees' => [['name' => 'Ahmad Zaki', 'position' => 'President']],
                    'agenda_items' => [['topic' => 'Ta\'aruf', 'sub_points' => ['Sesi perkenalan'], 'action_by' => 'Makluman', 'notes' => '']],
                ])]]]]],
            ]),
        ]);

        $data = app(GeminiSummaryService::class)->extractAgendaItems('Faris: hello.', 'Usrah Session');

        $this->assertSame('Ahmad Zaki', $data['attendees'][0]['name']);
        $this->assertSame("Ta'aruf", $data['agenda_items'][0]['topic']);
        Http::assertSent(fn ($request) => str_contains($request['contents'][0]['parts'][0]['text'], 'ONLY a single valid JSON object'));
    }

    public function test_extract_agenda_items_strips_markdown_code_fences(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => "```json\n".json_encode([
                    'attendees' => [],
                    'agenda_items' => [],
                ])."\n```"]]]]],
            ]),
        ]);

        $data = app(GeminiSummaryService::class)->extractAgendaItems('Faris: hello.', 'Usrah Session');

        $this->assertSame([], $data['attendees']);
        $this->assertSame([], $data['agenda_items']);
    }

    public function test_extract_agenda_items_throws_on_invalid_json(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => 'not json at all']]]]],
            ]),
        ]);

        $this->expectException(RuntimeException::class);

        app(GeminiSummaryService::class)->extractAgendaItems('Faris: hello.', 'Usrah Session');
    }

    public function test_translate_agenda_items_preserves_shape(): void
    {
        $translated = [
            'attendees' => [['name' => 'Ahmad Zaki', 'position' => 'Presiden']],
            'agenda_items' => [['topic' => 'Perkenalan', 'sub_points' => ['Sesi perkenalan'], 'action_by' => 'Makluman', 'notes' => '']],
        ];

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => json_encode($translated)]]]]],
            ]),
        ]);

        $result = app(GeminiSummaryService::class)->translateAgendaItems([
            'attendees' => [['name' => 'Ahmad Zaki', 'position' => 'President']],
            'agenda_items' => [['topic' => 'Introduction', 'sub_points' => ['Introduction session'], 'action_by' => 'Info only', 'notes' => '']],
        ], 'Malay (Bahasa Malaysia)');

        $this->assertSame('Presiden', $result['attendees'][0]['position']);
        $this->assertSame('Perkenalan', $result['agenda_items'][0]['topic']);
    }
}
