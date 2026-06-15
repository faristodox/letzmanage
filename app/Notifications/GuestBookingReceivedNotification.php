<?php

namespace App\Notifications;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Services\SystemSettingService;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class GuestBookingReceivedNotification extends Notification
{
    use Queueable;

    public function __construct(public Booking $booking)
    {
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->greeting("Hi {$this->booking->guest_name},");

        if ($this->booking->status === BookingStatus::Approved) {
            $message->subject('Booking Confirmed')
                ->line("Your request to book \"{$this->booking->space->name}\" has been confirmed.")
                ->line('Date: '.$this->booking->start_time->format('d M Y, H:i').' - '.$this->booking->end_time->format('H:i'))
                ->line('We look forward to seeing you.');

            $approvalEmailNote = app(SystemSettingService::class)->getApprovalEmailNote($this->booking->branch_id);

            if ($approvalEmailNote) {
                $message->line($approvalEmailNote);
            }
        } else {
            $message->subject('Booking Request Received')
                ->line("We've received your request to book \"{$this->booking->space->name}\".")
                ->line('Date: '.$this->booking->start_time->format('d M Y, H:i').' - '.$this->booking->end_time->format('H:i'))
                ->line('Your request is pending review. We will email you once it has been approved or rejected.');
        }

        return $message;
    }
}
