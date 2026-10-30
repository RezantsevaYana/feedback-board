<?php

namespace App\Mail;

use App\Models\AccessRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** The request wasn't approved. No links, the reason only if the admin gave one, and not who decided. */
class AccessRejectedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public AccessRequest $accessRequest) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Доступ к доске «{$this->accessRequest->project->name}» не одобрен",
            to: [$this->accessRequest->email],
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.access-rejected',
            with: [
                'name' => $this->accessRequest->name,
                'boardName' => $this->accessRequest->project->name,
                'reason' => $this->accessRequest->reject_reason,
            ],
        );
    }
}