<?php

namespace App\Http\Controllers;

use App\Enums\BookingStatus;
use App\Enums\RoleName;
use App\Exceptions\BookingConflictException;
use App\Models\Booking;
use App\Models\User;
use App\Services\BookingService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;

class TelegramWebhookController extends Controller
{
    public function __invoke(Request $request, BookingService $bookingService): Response
    {
        $payload = $request->json()->all();

        if (! isset($payload['callback_query'])) {
            return response('ok');
        }

        $callback = $payload['callback_query'];
        $callbackId = $callback['id'];
        $chatId = $callback['message']['chat']['id'] ?? null;
        $messageId = $callback['message']['message_id'] ?? null;
        $data = $callback['data'] ?? '';

        // Only accept callbacks from our authorized Telegram chat
        if ((string) $chatId !== (string) config('services.telegram.chat_id')) {
            $this->answer($callbackId, '⛔ Unauthorized');

            return response('ok');
        }

        if (! preg_match('/^(approve|reject)_(\d+)$/', $data, $m)) {
            return response('ok');
        }

        [, $action, $bookingId] = $m;

        $booking = Booking::with(['space', 'user'])->find((int) $bookingId);

        if (! $booking) {
            $this->answer($callbackId, '⚠️ Booking not found.');

            return response('ok');
        }

        if ($booking->status !== BookingStatus::Pending) {
            $this->answer($callbackId, "ℹ️ Already {$booking->status->value}.");

            return response('ok');
        }

        $approver = User::role(RoleName::Admin->value)->firstOrFail();

        if ($action === 'approve') {
            try {
                $bookingService->approve($booking, $approver);
                $this->answer($callbackId, '✅ Booking approved!');
                $this->editMessage($chatId, $messageId,
                    "✅ *Approved* by Telegram\n"
                    ."{$booking->requesterName()} — {$booking->space->name}\n"
                    ."📅 {$booking->start_time->format('D, d M Y')} · {$booking->start_time->format('H:i')}-{$booking->end_time->format('H:i')}"
                );
            } catch (BookingConflictException) {
                $this->answer($callbackId, '⚠️ Conflict — slot no longer available.');
            }
        } else {
            $bookingService->reject($booking, $approver);
            $this->answer($callbackId, '❌ Booking rejected.');
            $this->editMessage($chatId, $messageId,
                "❌ *Rejected* by Telegram\n"
                ."{$booking->requesterName()} — {$booking->space->name}\n"
                ."📅 {$booking->start_time->format('D, d M Y')} · {$booking->start_time->format('H:i')}-{$booking->end_time->format('H:i')}"
            );
        }

        return response('ok');
    }

    private function answer(string $callbackId, string $text): void
    {
        Http::post($this->apiUrl('answerCallbackQuery'), [
            'callback_query_id' => $callbackId,
            'text' => $text,
        ]);
    }

    private function editMessage(int|string $chatId, int $messageId, string $text): void
    {
        Http::post($this->apiUrl('editMessageText'), [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
            'parse_mode' => 'Markdown',
        ]);
    }

    private function apiUrl(string $method): string
    {
        return 'https://api.telegram.org/bot'.config('services.telegram.token').'/'.$method;
    }
}
