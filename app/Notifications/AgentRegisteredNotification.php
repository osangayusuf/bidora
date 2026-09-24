<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AgentRegisteredNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly User $agent)
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
            ->subject('New Agent Registration Pending Approval')
            ->greeting('Hello Admin,')
            ->line("A new support agent, {$this->agent->name} ({$this->agent->email}), has registered and is pending approval.")
            ->line("Department: {$this->agent->department}")
            ->line("Employee ID: {$this->agent->employee_id}")
            ->action('Review Registrations', url('/admin/agents'));
    }
}
