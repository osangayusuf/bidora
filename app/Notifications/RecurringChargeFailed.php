<?php

namespace App\Notifications;

use App\Models\PointSubscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RecurringChargeFailed extends Notification implements ShouldQueue
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
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('We could not process your points auto top-up')
            ->greeting('Auto top-up charge failed')
            ->line("Your ₦{$this->subscription->amount_naira} {$this->subscription->frequency->label()} auto top-up could not be charged.")
            ->line('Paystack will retry automatically. Please make sure your card has sufficient funds and is not expired.')
            ->action('Manage subscription', url('/wallet'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'recurring_charge_failed',
            'title' => "Auto top-up charge of ₦{$this->subscription->amount_naira} failed.",
            'url' => '/wallet',
            'subscription_id' => $this->subscription->id,
            'message' => "We couldn't process your ₦{$this->subscription->amount_naira} {$this->subscription->frequency->label()} auto top-up. Please check your card.",
        ];
    }
}
