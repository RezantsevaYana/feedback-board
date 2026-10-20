<?php

namespace App\Models;

use App\Enums\PostPriority;
use Database\Factories\PostFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['category_id', 'title', 'description', 'author_name', 'author_token', 'status_id', 'priority', 'team_comment', 'is_hidden'])]
class Post extends Model
{
    /** @use HasFactory<PostFactory> */
    use HasFactory;

    protected $attributes = [
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
            'priority' => PostPriority::class,
            'is_hidden' => 'boolean',
            'likes_count' => 'integer',
            'dislikes_count' => 'integer',
            'status_changed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // новый пост без явного статуса — в дефолтный столбец проекта
        static::creating(function (Post $post) {
            if (! $post->status_id && $post->project) {
                $post->status_id = $post->project->defaultStatus()?->id;
            }
        });
        // переезд в другой столбец штампуется временем смены
        static::saving(function (Post $post) {
            if (! $post->exists || $post->isDirty('status_id')) {
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
     * @return BelongsTo<Status, $this>
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class);
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

    /**
     * Полнотекстовый поиск по заголовку и описанию. Пустая строка — всё.
     *
     * @param  Builder<Post>  $query
     */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        $term = trim((string) $term);
        if ($term === '') {
            return;
        }
        $like = '%'.addcslashes($term, '%_\\').'%';
        $query->where(fn ($q) => $q
            ->where('title', 'ilike', $like)
            ->orWhere('description', 'ilike', $like));
    }
}
