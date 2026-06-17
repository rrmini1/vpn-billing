<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

class VerifyEmailNotification extends Notification
{
    use Queueable;

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $locale = $this->mailLocale($notifiable);
        $url = $this->verificationUrl($notifiable);
        $minutes = (int) config('auth.verification.expire', 60);

        if ($locale === 'ru') {
            return (new MailMessage)
                ->subject('Подтверждение email')
                ->greeting('Здравствуйте!')
                ->line('Нажмите кнопку ниже, чтобы подтвердить email в Cors Port Solutions.')
                ->action('Подтвердить email', $url)
                ->line('Ссылка действительна '.$minutes.' минут.')
                ->line('Если вы не создавали аккаунт, просто проигнорируйте это письмо.');
        }

        return (new MailMessage)
            ->subject('Verify email address')
            ->greeting('Hello!')
            ->line('Click the button below to verify your email address in Cors Port Solutions.')
            ->action('Verify email', $url)
            ->line('This link expires in '.$minutes.' minutes.')
            ->line('If you did not create an account, no further action is required.');
    }

    private function verificationUrl(MustVerifyEmail $notifiable): string
    {
        return URL::temporarySignedRoute(
            'verification.verify',
            Carbon::now()->addMinutes((int) config('auth.verification.expire', 60)),
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ],
        );
    }

    private function mailLocale(object $notifiable): string
    {
        return ($notifiable->locale ?? null) === 'en' ? 'en' : 'ru';
    }
}
