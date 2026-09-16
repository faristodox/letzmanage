<?php

namespace App\Notifications;

use App\Models\Meeting;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MeetingMinutesReadyNotification extends Notification
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
            ->subject('Minutes of Meeting ready: '.$this->meeting->title)
            ->line("The minutes for \"{$this->meeting->title}\" have been generated.")
            ->line('You can view the transcript and minutes in the app under Meetings.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'meeting_id' => $this->meeting->id,
            'title' => $this->meeting->title,
            'message' => "Minutes of Meeting ready: {$this->meeting->title}",
        ];
    }
}
