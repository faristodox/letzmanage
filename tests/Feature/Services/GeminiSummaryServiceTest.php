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
}
