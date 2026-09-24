<?php

namespace App\Notifications;

use App\Models\PointSubscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AutoTopUpEnded extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly PointSubscription $subscription)
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
        $package = $this->subscription->subscriptionPackage?->name ?? 'points';

        return (new MailMessage)
            ->subject('Your auto top-up has ended')
            ->greeting('Your auto top-up has ended')
            ->line("We've upgraded how auto top-ups work, so your {$package} auto top-up has been cancelled and you will not be charged again.")
            ->line('You can start a new auto top-up with your card at any time from your wallet.')
            ->action('Open wallet', url('/wallet'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'auto_top_up_ended',
            'title' => 'Your auto top-up has ended.',
            'url' => '/wallet',
            'subscription_id' => $this->subscription->id,
            'message' => 'Your auto top-up was cancelled and you will not be charged again. You can start a new one from your wallet.',
        ];
    }
}
