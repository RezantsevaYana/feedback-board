<?php

namespace App\Mail;

use App\Models\AccessRequest;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** To each owner / admin of a private board: someone asked for access, the decision is on the participants page. */
class AccessRequestedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public AccessRequest $accessRequest, public User $admin) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Запрос доступа к доске «{$this->accessRequest->project->name}»",
            to: [$this->admin->email],
        );
    }

    public function content(): Content
    {
        $frontend = rtrim((string) config('feedback.public_url'), '/');

        return new Content(
            markdown: 'mail.access-requested',
            with: [
                'boardName' => $this->accessRequest->project->name,
                'requesterName' => $this->accessRequest->name,
                'requesterEmail' => $this->accessRequest->email,
                'requestMessage' => $this->accessRequest->message,
                'url' => $frontend.'/app/boards/'.$this->accessRequest->project->slug.'/participants',
            ],
        );
    }
}