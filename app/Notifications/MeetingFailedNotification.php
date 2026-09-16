<?php

namespace App\Notifications;

use App\Models\Meeting;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MeetingFailedNotification extends Notification
{
    use Queueable;

    public function __construct(public Meeting $meeting)
    {
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Meeting processing failed: '.$this->meeting->title)
            ->line("Something went wrong while processing \"{$this->meeting->title}\".")
            ->line($this->meeting->failure_reason ?: 'An unknown error occurred.')
            ->line('You can try recording or uploading it again from the Meetings page.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'meeting_id' => $this->meeting->id,
            'title' => $this->meeting->title,
            'failure_reason' => $this->meeting->failure_reason,
            'message' => "Meeting processing failed: {$this->meeting->title}",
        ];
    }
}
