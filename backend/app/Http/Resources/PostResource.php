<?php

namespace App\Http\Resources;

use App\Models\Attachment;
use App\Models\Post;
use App\Models\Vote;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public view of a post: no priority, no hidden flag.
 *
 * @mixin Post
 */
class PostResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'category' => $this->category ? ['id' => $this->category->id, 'name' => $this->category->name, 'color' => $this->category->color] : null,
            'title' => $this->title,
            'description' => $this->description,
            'author_name' => $this->author_name,
            'status' => $this->status ? [
                'id' => $this->status->id,
                'slug' => $this->status->slug,
                'name' => $this->status->name,
                'color' => $this->status->color,
                'position' => $this->status->position,
                'is_system' => $this->status->is_system,
            ] : null,
            'likes_count' => $this->likes_count,
            'dislikes_count' => $this->dislikes_count,
            'my_vote' => match ($request->attributes->get('my_votes', [])[$this->id] ?? null) {
                Vote::LIKE => 'like',
                Vote::DISLIKE => 'dislike',
                default => null,
            },
            'team_comment' => $this->team_comment,
            'attachments' => $this->whenLoaded('attachments', fn () => $this->attachments->map(fn (Attachment $a) => [
                'id' => $a->id,
                'url' => $this->attachmentUrl($a),
                'original_name' => $a->original_name,
            ])),
            'attachments_count' => $this->whenCounted('attachments'),
            'created_at' => $this->created_at,
            'status_changed_at' => $this->status_changed_at,
        ];
    }

    protected function attachmentUrl(Attachment $attachment): string
    {
        return "/api/public/attachments/{$attachment->id}";
    }
}
