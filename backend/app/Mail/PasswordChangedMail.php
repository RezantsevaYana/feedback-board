<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * After «Сменить пароль» in the profile. The password itself never goes into a letter;
 * the letter only tells the owner, with the way back if it wasn't them.
 */
class PasswordChangedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $user) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Пароль изменён',
            to: [$this->user->email],
        );
    }

    public function content(): Content
    {
        $frontend = rtrim((string) config('feedback.public_url'), '/');

        return new Content(
            markdown: 'mail.password-changed',
            with: [
                'name' => $this->user->name,
                'resetUrl' => $frontend.'/app/forgot-password?'.http_build_query(['email' => $this->user->email]),
            ],
        );
    }
}