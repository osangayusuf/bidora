<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AgentRejectedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $agentName) {}

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
            ->subject('Support Agent Registration Declined')
            ->greeting("Hello {$this->agentName},")
            ->line('Thank you for your interest in joining the Bidora support team.')
            ->line('Unfortunately, your registration as a Customer Care Agent was not approved at this time.')
            ->line('As a result, your agent registration details and account have been removed from our system.');
    }
}
