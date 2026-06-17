<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification
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
        $locale = $this->mailLocale($notifiable);
        $url = $this->resetUrl($notifiable);
        $minutes = (int) config('auth.passwords.users.expire', 60);

        if ($locale === 'ru') {
            return (new MailMessage)
                ->subject('Сброс пароля')
                ->greeting('Здравствуйте!')
                ->line('Вы получили это письмо, потому что был запрошен сброс пароля для аккаунта Cors Port Solutions.')
                ->action('Сбросить пароль', $url)
                ->line('Ссылка действительна '.$minutes.' минут.')
                ->line('Если вы не запрашивали сброс пароля, просто проигнорируйте это письмо.');
        }

        return (new MailMessage)
            ->subject('Reset password')
            ->greeting('Hello!')
            ->line('You are receiving this email because a password reset was requested for your Cors Port Solutions account.')
            ->action('Reset password', $url)
            ->line('This link expires in '.$minutes.' minutes.')
            ->line('If you did not request a password reset, no further action is required.');
    }

    private function resetUrl(object $notifiable): string
    {
        return rtrim((string) config('app.frontend_url'), '/').'/app/reset-password?'.http_build_query([
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);
    }

    private function mailLocale(object $notifiable): string
    {
        return ($notifiable->locale ?? null) === 'en' ? 'en' : 'ru';
    }
}
