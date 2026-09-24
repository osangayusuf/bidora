<?php

namespace App\Notifications;

use App\Models\TicketMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TicketMessageReceivedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly TicketMessage $message
    ) {
        $this->afterCommit = true;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $ticket = $this->message->ticket;

        return (new MailMessage)
            ->subject("New reply on support ticket #{$ticket->id}")
            ->greeting("Hello {$notifiable->name},")
            ->line("You have received a new reply from {$this->message->sender->name} on your assigned support ticket #{$ticket->id} ({$ticket->subject}).")
            ->line('Message body:')
            ->line('"'.$this->message->body.'"')
            ->action('View Ticket', url("/support/tickets/{$ticket->id}"));
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'ticket_message_received',
            'title' => 'New Reply on Ticket',
            'message' => "New reply from {$this->message->sender->name} on ticket #{$this->message->ticket_id}",
            'icon' => 'reply',
            'url' => "/support/tickets/{$this->message->ticket_id}",
            'ticket_id' => $this->message->ticket_id,
            'message_id' => $this->message->id,
        ];
    }
}
