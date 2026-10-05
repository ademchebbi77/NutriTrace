<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccountReviewed extends Notification
{
    public function __construct(
        private readonly bool $approved,
        private readonly ?string $reason = null,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        if ($this->approved) {
            return (new MailMessage)
                ->subject(__('account.mail.approved_subject'))
                ->line(__('account.mail.approved_line'))
                ->action(__('account.mail.approved_action'), route('dashboard'));
        }

        return (new MailMessage)
            ->subject(__('account.mail.rejected_subject'))
            ->line(__('account.mail.rejected_line'))
            ->line(__('account.mail.reason', ['reason' => $this->reason]));
    }
}
