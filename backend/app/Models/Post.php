<?php

namespace App\Models;

use App\Enums\PostPriority;
use App\Enums\PostStatus;
use Database\Factories\PostFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['category_id', 'title', 'description', 'author_name', 'status', 'priority', 'team_comment', 'is_hidden'])]
class Post extends Model
{
    /** @use HasFactory<PostFactory> */
    use HasFactory;

    protected $attributes = [
        'status' => 'new',
        'is_hidden' => false,
        'likes_count' => 0,
        'dislikes_count' => 0,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PostStatus::class,
            'priority' => PostPriority::class,
            'is_hidden' => 'boolean',
            'likes_count' => 'integer',
            'dislikes_count' => 'integer',
            'status_changed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Post $post) {
            // the default status is not "dirty" on create, so new posts are stamped explicitly
            if (! $post->exists || $post->isDirty('status')) {
                $post->status_changed_at = now();
            }
        });
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return HasMany<Attachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class);
    }

    /**
     * @return HasMany<Vote, $this>
     */
    public function votes(): HasMany
    {
        return $this->hasMany(Vote::class);
    }

    /**
     * Posts shown on the public board: everything except what the team hid.
     *
     * @param  Builder<Post>  $query
     */
    public function scopeListedOnBoard(Builder $query): void
    {
        $query->where('is_hidden', false);
    }

    /**
     * category=<id> or category=none.
     *
     * @param  Builder<Post>  $query
     */
    public function scopeInCategory(Builder $query, ?string $category): void
    {
        match ($category) {
            null, '' => null,
            'none' => $query->whereNull('category_id'),
            default => $query->where('category_id', (int) $category),
        };
    }

    /**
     * @param  Builder<Post>  $query
     */
    public function scopeSorted(Builder $query, string $sort): void
    {
        match ($sort) {
            'new' => $query->latest('id'),
            'priority' => $query
                ->orderByRaw("case priority when 'critical' then 4 when 'high' then 3 when 'medium' then 2 when 'low' then 1 else 0 end desc")
                ->latest('id'),
            // popularity = likes minus dislikes
            default => $query->orderByRaw('likes_count - dislikes_count desc')->latest('id'),
        };
    }
}
