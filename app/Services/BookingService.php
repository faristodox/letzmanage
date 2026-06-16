<?php

namespace App\Services;

use App\Enums\ApprovalMode;
use App\Enums\BookingStatus;
use App\Enums\RoleName;
use App\Exceptions\BookingConflictException;
use App\Models\Booking;
use App\Models\OfficeSpace;
use App\Models\User;
use App\Notifications\BookingApprovedNotification;
use App\Notifications\BookingAutoApprovedNotification;
use App\Notifications\BookingRejectedNotification;
use App\Notifications\BookingSubmittedNotification;
use App\Notifications\GuestBookingReceivedNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Collection;

class BookingService
{
    public function __construct(private SystemSettingService $settings)
    {
    }

    /**
     * Create a new booking, applying overlap validation and the branch's approval mode.
     *
     * @param  array{branch_id:int, user_id:int, space_id:int, start_time:string|Carbon, end_time:string|Carbon, title?:string|null, notes?:string|null}  $data
     */
    public function create(array $data): Booking
    {
        if ($this->hasOverlap($data['space_id'], $data['start_time'], $data['end_time'])) {
            throw new BookingConflictException;
        }

        $mode = $this->settings->getApprovalMode($data['branch_id']);

        $booking = Booking::create([
            ...$data,
            'status' => $mode === ApprovalMode::Auto ? BookingStatus::Approved : BookingStatus::Pending,
        ]);

        $booking->load(['user', 'space']);

        if ($mode === ApprovalMode::Auto) {
            Notification::send($booking->user, new BookingApprovedNotification($booking));
            Notification::send($this->approvers($booking), new BookingAutoApprovedNotification($booking));
            $this->notifyTelegram(new BookingAutoApprovedNotification($booking));
        } else {
            Notification::send($this->approvers($booking), new BookingSubmittedNotification($booking));
            $this->notifyTelegramPending($booking);
        }

        return $booking;
    }

    /**
     * Create a booking request submitted by a guest (no user account).
     *
     * The booking is auto-approved or left pending based on the branch's
     * approval mode, just like staff bookings. The guest is always emailed
     * a confirmation reflecting the resulting status.
     *
     * @param  array{branch_id:int, space_id:int, start_time:string|Carbon, end_time:string|Carbon, title?:string|null, guest_name:string, guest_email:string, guest_phone?:string|null}  $data
     */
    public function createGuestBooking(array $data): Booking
    {
        if ($this->hasOverlap($data['space_id'], $data['start_time'], $data['end_time'])) {
            throw new BookingConflictException;
        }

        $mode = $this->settings->getApprovalMode($data['branch_id']);

        $booking = Booking::create([
            ...$data,
            'user_id' => null,
            'status' => $mode === ApprovalMode::Auto ? BookingStatus::Approved : BookingStatus::Pending,
        ]);

        $booking->load(['space']);

        if ($mode === ApprovalMode::Auto) {
            Notification::send($this->approvers($booking), new BookingAutoApprovedNotification($booking));
            $this->notifyTelegram(new BookingAutoApprovedNotification($booking));
        } else {
            Notification::send($this->approvers($booking), new BookingSubmittedNotification($booking));
            $this->notifyTelegramPending($booking);
        }

        Notification::route('mail', $booking->guest_email)->notify(new GuestBookingReceivedNotification($booking));

        return $booking;
    }

    /**
     * Approve a pending booking, optionally attaching a note that is
     * included in the approval notification sent to the requester.
     */
    public function approve(Booking $booking, User $approver, ?string $note = null): Booking
    {
        if ($this->hasOverlap($booking->space_id, $booking->start_time, $booking->end_time, excludeBookingId: $booking->id)) {
            throw new BookingConflictException;
        }

        $booking->update([
            'status' => BookingStatus::Approved,
            'approved_by' => $approver->id,
            'notes' => $note,
        ]);

        $booking->load(['user', 'space']);

        $this->notifyRequester($booking, new BookingApprovedNotification($booking));
        $this->notifyTelegram(new BookingApprovedNotification($booking));

        return $booking;
    }

    /**
     * Reject a pending booking.
     */
    public function reject(Booking $booking, User $approver, ?string $reason = null): Booking
    {
        $booking->update([
            'status' => BookingStatus::Rejected,
            'approved_by' => $approver->id,
            'notes' => $reason,
        ]);

        $booking->load(['user', 'space']);

        $this->notifyRequester($booking, new BookingRejectedNotification($booking));
        $this->notifyTelegram(new BookingRejectedNotification($booking));

        return $booking;
    }

    /**
     * Cancel a booking.
     */
    public function cancel(Booking $booking): Booking
    {
        $booking->update(['status' => BookingStatus::Cancelled]);

        return $booking;
    }

    /**
     * Determine whether the given time range overlaps with an existing
     * approved booking for the same space.
     */
    public function hasOverlap(int $spaceId, string|Carbon $start, string|Carbon $end, ?int $excludeBookingId = null): bool
    {
        return Booking::whereIn('space_id', $this->conflictingSpaceIds($spaceId))
            ->where('status', BookingStatus::Approved)
            ->when($excludeBookingId, fn ($query) => $query->where('id', '!=', $excludeBookingId))
            ->where('start_time', '<', $end)
            ->where('end_time', '>', $start)
            ->exists();
    }

    /** @return array<int> */
    public function conflictingSpaceIds(int $spaceId): array
    {
        $space = OfficeSpace::with('children')->find($spaceId);

        if (! $space) {
            return [$spaceId];
        }

        // Always include the space itself + all its sub-spaces
        $ids = [$spaceId, ...$space->children->pluck('id')->all()];

        // If this is a sub-space, also block the parent
        if ($space->parent_id) {
            $ids[] = $space->parent_id;
        }

        return array_unique($ids);
    }

    /**
     * Broadcast a booking event to the shared admin Telegram chat, if configured.
     */
    private function notifyTelegram(\Illuminate\Notifications\Notification $notification): void
    {
        $chatId = config('services.telegram.chat_id');

        if (! $chatId) {
            return;
        }

        Notification::route('telegram', $chatId)->notify($notification);
    }

    /**
     * Send a Telegram message for a pending booking with inline Approve / Reject buttons.
     */
    private function notifyTelegramPending(Booking $booking): void
    {
        $chatId = config('services.telegram.chat_id');
        $token = config('services.telegram.token');

        if (! $chatId || ! $token) {
            return;
        }

        $text = "🆕 *New booking request*\n"
            ."{$booking->requesterName()} requested \"{$booking->space->name}\"\n"
            ."📅 {$booking->start_time->format('D, d M Y')} · {$booking->start_time->format('H:i')}-{$booking->end_time->format('H:i')}";

        if ($booking->isGuest()) {
            $text .= "\n✉️ {$booking->guest_email}";
        }

        if ($booking->title) {
            $text .= "\n📝 {$booking->title}";
        }

        Http::post("https://api.telegram.org/bot{$token}/sendMessage", [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'reply_markup' => json_encode([
                'inline_keyboard' => [[
                    ['text' => '✅ Approve', 'callback_data' => "approve_{$booking->id}"],
                    ['text' => '❌ Reject', 'callback_data' => "reject_{$booking->id}"],
                ]],
            ]),
        ]);
    }

    /**
     * Notify whoever requested the booking, whether a registered user or a guest.
     */
    private function notifyRequester(Booking $booking, \Illuminate\Notifications\Notification $notification): void
    {
        if ($booking->user) {
            Notification::send($booking->user, $notification);

            return;
        }

        if ($booking->guest_email) {
            Notification::route('mail', $booking->guest_email)->notify($notification);
        }
    }

    /**
     * Users who can approve bookings for the given booking's branch:
     * all Admins plus Managers assigned to that branch.
     *
     * @return Collection<int, User>
     */
    private function approvers(Booking $booking): Collection
    {
        return User::role(RoleName::Admin->value)
            ->get()
            ->merge(
                User::role(RoleName::Manager->value)
                    ->where('branch_id', $booking->branch_id)
                    ->get()
            )
            ->unique('id');
    }
}
