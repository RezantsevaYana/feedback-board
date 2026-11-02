<?php

namespace App\Http\Resources;

use App\Models\Attachment;
use Illuminate\Http\Request;

/**
 * Team view of a post: adds priority and the hidden flag; files go through the admin endpoint.
 */
class AdminPostResource extends PostResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'priority' => $this->priority,
            'is_hidden' => $this->is_hidden,
            // the latest voters for the card's faces (VoterPreview); empty when nobody voted
            'voters_preview' => $request->attributes->get('voters_preview', [])[$this->id] ?? [],
        ];
    }

    protected function attachmentUrl(Attachment $attachment): string
    {
        return "/api/admin/attachments/{$attachment->id}";
    }
}
