<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\Telegram\TelegramMessage;

class BookingAutoApprovedNotification extends Notification
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
            return $notifiable->routeNotificationFor('telegram') ? ['telegram'] : [];
        }

        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Booking Auto-Approved')
            ->line("{$this->booking->requesterName()} booked \"{$this->booking->space->name}\" and it was automatically approved.")
            ->line('Date: '.$this->booking->start_time->format('d M Y, H:i').' - '.$this->booking->end_time->format('H:i'))
            ->action('View Booking', url('/bookings/'.$this->booking->id));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'booking_id' => $this->booking->id,
            'space_name' => $this->booking->space->name,
            'requested_by' => $this->booking->requesterName(),
            'start_time' => $this->booking->start_time->toIso8601String(),
            'end_time' => $this->booking->end_time->toIso8601String(),
            'message' => "{$this->booking->requesterName()} booked \"{$this->booking->space->name}\" (auto-approved).",
        ];
    }

    public function toTelegram(object $notifiable): TelegramMessage
    {
        $message = TelegramMessage::create()
            ->to($notifiable->routeNotificationFor('telegram'))
            ->line('⚡ Booking auto-approved')
            ->line("{$this->booking->requesterName()} booked \"{$this->booking->space->name}\"")
            ->line('📅 '.$this->booking->start_time->format('D, d M Y').' · '.$this->booking->start_time->format('H:i').'-'.$this->booking->end_time->format('H:i'));

        if ($this->booking->title) {
            $message->line("📝 Purpose: {$this->booking->title}");
        }

        return $message;
    }
}
