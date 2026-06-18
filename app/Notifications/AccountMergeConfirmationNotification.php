<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccountMergeConfirmationNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $token,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $locale = ($notifiable->locale ?? null) === 'en' ? 'en' : 'ru';
        $url = $this->confirmationUrl();
        $minutes = 60;

        if ($locale === 'ru') {
            return (new MailMessage)
                ->subject('Подтверждение объединения аккаунтов')
                ->greeting('Здравствуйте!')
                ->line('Мы получили запрос на объединение вашего email-аккаунта Cors Port Solutions с Telegram-аккаунтом.')
                ->line('Подтвердите действие только если это ваш Telegram-аккаунт.')
                ->action('Объединить аккаунты', $url)
                ->line('Ссылка действительна '.$minutes.' минут.')
                ->line('Если вы не запрашивали объединение, просто проигнорируйте это письмо.');
        }

        return (new MailMessage)
            ->subject('Confirm account merge')
            ->greeting('Hello!')
            ->line('We received a request to merge your Cors Port Solutions email account with a Telegram account.')
            ->line('Confirm this action only if this Telegram account belongs to you.')
            ->action('Merge accounts', $url)
            ->line('This link expires in '.$minutes.' minutes.')
            ->line('If you did not request this merge, no further action is required.');
    }

    private function confirmationUrl(): string
    {
        return url('/api/account/merge/email/confirm?'.http_build_query([
            'token' => $this->token,
        ]));
    }
}
