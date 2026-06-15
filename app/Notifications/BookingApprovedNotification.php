<?php

namespace App\Notifications;

use App\Models\Booking;
use App\Services\SystemSettingService;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\Telegram\TelegramMessage;

class BookingApprovedNotification extends Notification
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
        if ($notifiable instanceof AnonymousNotifiable) {
            return $notifiable->routeNotificationFor('telegram') ? ['telegram'] : ['mail'];
        }

        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject('Booking Approved')
            ->line("Your booking for \"{$this->booking->space->name}\" has been approved.")
            ->line('Date: '.$this->booking->start_time->format('d M Y, H:i').' - '.$this->booking->end_time->format('H:i'));

        if ($this->booking->notes) {
            $message->line("Note from admin: {$this->booking->notes}");
        }

        $approvalEmailNote = app(SystemSettingService::class)->getApprovalEmailNote($this->booking->branch_id);

        if ($approvalEmailNote) {
            $message->line($approvalEmailNote);
        }

        return $message->action('View Booking', route('bookings.index'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'booking_id' => $this->booking->id,
            'space_name' => $this->booking->space->name,
            'start_time' => $this->booking->start_time->toIso8601String(),
            'end_time' => $this->booking->end_time->toIso8601String(),
            'note' => $this->booking->notes,
            'message' => "Your booking for \"{$this->booking->space->name}\" has been approved.",
        ];
    }

    public function toTelegram(object $notifiable): TelegramMessage
    {
        $message = TelegramMessage::create()
            ->to($notifiable->routeNotificationFor('telegram'))
            ->line('✅ Booking approved')
            ->line("\"{$this->booking->space->name}\" for {$this->booking->requesterName()}")
            ->line('📅 '.$this->booking->start_time->format('D, d M Y').' · '.$this->booking->start_time->format('H:i').'-'.$this->booking->end_time->format('H:i'));

        if ($this->booking->title) {
            $message->line("📝 Purpose: {$this->booking->title}");
        }

        if ($this->booking->notes) {
            $message->line("ℹ️ Note: {$this->booking->notes}");
        }

        return $message;
    }
}
