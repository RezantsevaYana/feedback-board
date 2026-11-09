<?php

namespace App\Mail;

use App\Models\ProjectInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The request was approved. Carries the invitation's one-time link: the acceptance screen asks a newcomer
 * for a name and a password, and lets an existing account join with one button.
 */
class AccessApprovedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public ProjectInvitation $invitation) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Доступ к доске «{$this->invitation->project->name}» открыт",
            to: [$this->invitation->email],
        );
    }

    public function content(): Content
    {
        $frontend = rtrim((string) config('feedback.public_url'), '/');

        return new Content(
            markdown: 'mail.access-approved',
            with: [
                'url' => $this->invitation->accepted_at
                    ? $frontend.'/'.$this->invitation->project->slug
                    : $frontend.'/app/invite/'.$this->invitation->token,
                'joined' => $this->invitation->accepted_at !== null,
                'boardName' => $this->invitation->project->name,
                'approverName' => $this->invitation->inviter->name,
                'roleLabel' => InvitationMail::roleLabelFor($this->invitation->role),
                'expiresInDays' => ProjectInvitation::TTL_DAYS,
            ],
        );
    }
}
