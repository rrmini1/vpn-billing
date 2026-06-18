<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AttachEmailConfirmationMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $token,
        public readonly string $mailLocale = 'ru',
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->isRussian() ? 'Подтверждение email' : 'Confirm email',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.attach-email-confirmation',
            with: [
                'actionUrl' => $this->actionUrl(),
                'isRussian' => $this->isRussian(),
            ],
        );
    }

    /**
     * @return array<int, mixed>
     */
    public function attachments(): array
    {
        return [];
    }

    private function actionUrl(): string
    {
        return rtrim((string) config('app.frontend_url'), '/').'/app/attach-email?'.http_build_query([
            'token' => $this->token,
        ]);
    }

    private function isRussian(): bool
    {
        return $this->mailLocale !== 'en';
    }
}
