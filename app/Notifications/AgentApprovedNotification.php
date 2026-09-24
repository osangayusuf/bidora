<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AgentApprovedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct()
    {
        $this->afterCommit = true;
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        // Mail delivery disabled.
        return [];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Support Agent Registration Approved!')
            ->greeting('Hello,')
            ->line('Congratulations! Your registration as a Customer Care Agent has been approved by the administrator.')
            ->line('You can now log in to the agent portal and start managing tickets and live chats.')
            ->action('Access Support Portal', url('/support/login'));
    }
}
