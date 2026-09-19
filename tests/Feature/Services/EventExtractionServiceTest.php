<?php

namespace Tests\Feature\Services;

use App\Exceptions\MeetingNotConfiguredException;
use App\Services\EventExtractionService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class EventExtractionServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['services.gemini.api_key' => 'test-key', 'services.gemini.model' => 'gemini-flash-latest']);
    }

    private function fakeGeminiResponse(array $data): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    ['content' => ['parts' => [['text' => json_encode($data)]]]],
                ],
            ]),
        ]);
    }

    public function test_extracts_details_from_a_text_message_only(): void
    {
        $this->fakeGeminiResponse([
            'title' => 'Program Wanita',
            'start_date' => '2026-10-05',
            'start_time' => '09:00',
            'end_date' => null,
            'end_time' => null,
            'location' => 'Dewan Serbaguna',
            'description' => 'A community program for the Wanita committee.',
        ]);

        $result = app(EventExtractionService::class)->extract('Program Wanita, 5 Oktober, 9 pagi, di Dewan Serbaguna', null);

        $this->assertSame('Program Wanita', $result['title']);
        $this->assertSame('2026-10-05', $result['start_date']);
        $this->assertSame('09:00', $result['start_time']);
        $this->assertSame('Dewan Serbaguna', $result['location']);

        Http::assertSent(function ($request) {
            $parts = $request['contents'][0]['parts'];

            return count($parts) === 1
                && str_contains($parts[0]['text'], 'Program Wanita, 5 Oktober');
        });
    }

    public function test_extracts_details_from_an_image_only(): void
    {
        $this->fakeGeminiResponse([
            'title' => 'Mesyuarat Agung',
            'start_date' => null,
            'start_time' => null,
            'end_date' => null,
            'end_time' => null,
            'location' => null,
            'description' => null,
        ]);

        $image = UploadedFile::fake()->image('poster.jpg', 600, 800);

        $result = app(EventExtractionService::class)->extract(null, $image);

        $this->assertSame('Mesyuarat Agung', $result['title']);
        $this->assertNull($result['start_date']);

        Http::assertSent(function ($request) {
            $parts = $request['contents'][0]['parts'];

            return count($parts) === 2
                && isset($parts[0]['inline_data'])
                && $parts[0]['inline_data']['mime_type'] === 'image/jpeg'
                && ! empty($parts[0]['inline_data']['data']);
        });
    }

    public function test_extracts_details_from_both_an_image_and_a_message(): void
    {
        $this->fakeGeminiResponse([
            'title' => 'Program Wanita',
            'start_date' => null,
            'start_time' => null,
            'end_date' => null,
            'end_time' => null,
            'location' => null,
            'description' => null,
        ]);

        $image = UploadedFile::fake()->image('poster.png');

        app(EventExtractionService::class)->extract('Additional notes here', $image);

        Http::assertSent(function ($request) {
            $parts = $request['contents'][0]['parts'];

            return count($parts) === 2
                && isset($parts[0]['inline_data'])
                && str_contains($parts[1]['text'], 'Additional notes here');
        });
    }

    public function test_missing_fields_in_the_response_default_to_null(): void
    {
        $this->fakeGeminiResponse(['title' => 'Only a title']);

        $result = app(EventExtractionService::class)->extract('some message', null);

        $this->assertSame('Only a title', $result['title']);
        $this->assertNull($result['start_date']);
        $this->assertNull($result['location']);
        $this->assertNull($result['description']);
    }

    public function test_throws_on_malformed_json_response(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    ['content' => ['parts' => [['text' => 'not valid json at all']]]],
                ],
            ]),
        ]);

        $this->expectException(RuntimeException::class);

        app(EventExtractionService::class)->extract('some message', null);
    }

    public function test_throws_when_api_key_is_missing(): void
    {
        config(['services.gemini.api_key' => null]);

        $this->expectException(MeetingNotConfiguredException::class);

        app(EventExtractionService::class)->extract('some message', null);
    }
}
