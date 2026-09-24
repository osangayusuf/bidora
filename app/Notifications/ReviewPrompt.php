<?php

namespace App\Notifications;

use App\Models\Auction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReviewPrompt extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Auction $auction)
    {
        $this->afterCommit = true;
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Share your delivery feedback — {$this->auction->name}")
            ->greeting('Congratulations on your win! 📦')
            ->line("We hope you love your new \"{$this->auction->name}\".")
            ->line('Please take a moment to share your feedback and submit proof of delivery (photos/videos).')
            ->action('Submit Review', url('/auctions/'.$this->auction->id))
            ->line('Thank you for choosing Bidora!');
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'review_prompt',
            'title' => "Share your delivery feedback for \"{$this->auction->name}\"!",
            'auction_id' => $this->auction->id,
            'message' => 'Please submit a review and upload your proof-of-delivery photos/videos.',
            'url' => '/auctions/'.$this->auction->id,
            'icon' => 'rate_review',
        ];
    }
}
