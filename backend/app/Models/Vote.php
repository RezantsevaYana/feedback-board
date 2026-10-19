<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['voter_token', 'ip_hash', 'value'])]
class Vote extends Model
{
    const UPDATED_AT = null;

    public const LIKE = 1;

    public const DISLIKE = -1;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['value' => 'integer'];
    }

    /** Denormalized counter on posts that this vote contributes to. */
    public static function counterFor(int $value): string
    {
        return $value === self::LIKE ? 'likes_count' : 'dislikes_count';
    }

    /**
     * @return BelongsTo<Post, $this>
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }
}
