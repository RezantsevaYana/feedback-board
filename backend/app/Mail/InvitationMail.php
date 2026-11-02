<?php

namespace App\Mail;

use App\Models\ProjectInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public ProjectInvitation $invitation) {}

    public function envelope(): Envelope
    {
        $board = $this->invitation->project->name;

        return new Envelope(
            subject: "Приглашение в доску «{$board}»",
            to: [$this->invitation->email],
        );
    }

    public function content(): Content
    {
        $frontend = rtrim((string) config('feedback.public_url'), '/');

        return new Content(
            markdown: 'mail.invitation',
            with: [
                'url' => $frontend.'/app/invite/'.$this->invitation->token,
                'boardName' => $this->invitation->project->name,
                'inviterName' => $this->invitation->inviter->name,
                'roleLabel' => $this->roleLabel(),
                'expiresInDays' => ProjectInvitation::TTL_DAYS,
            ],
        );
    }

    private function roleLabel(): string
    {
        return self::roleLabelFor($this->invitation->role);
    }

    /** Role names as the letters say them; AccessApprovedMail uses the same ones. */
    public static function roleLabelFor(\App\Enums\ProjectRole $role): string
    {
        return match ($role->value) {
            'admin' => 'Админ',
            'manager' => 'Менеджер',
            'voter' => 'Участник',
            'reader' => 'Читатель',
            default => 'Участник',
        };
    }
}
