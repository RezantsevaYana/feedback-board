<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * «Забыли пароль?» for an address without an account. The page answers the same either way,
 * so the owner of the mailbox learns it here: maybe they signed in with another address.
 */
class NoAccountMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $email) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'У этой почты нет аккаунта',
            to: [$this->email],
        );
    }

    public function content(): Content
    {
        $frontend = rtrim((string) config('feedback.public_url'), '/');

        return new Content(
            markdown: 'mail.no-account',
            with: [
                'email' => $this->email,
                'loginUrl' => $frontend.'/app/login',
                'registerUrl' => $frontend.'/app/register',
            ],
        );
    }
}