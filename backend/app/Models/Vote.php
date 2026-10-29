<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['voter_token', 'user_id', 'ip_hash', 'value'])]
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

    /**
     * The signed-in voter (null when the vote came from a cookie-only anonymous browser).
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
