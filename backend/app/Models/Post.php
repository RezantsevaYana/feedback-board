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

#[Fillable(['category_id', 'title', 'description', 'author_name', 'author_token', 'author_user_id', 'status_id', 'priority', 'team_comment', 'is_hidden'])]
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
     * The signed-in author (null for anonymous widget submissions).
     *
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_user_id');
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
     * category=<id>, category=none, or several comma-separated (category=3,7,none): any of them matches.
     *
     * @param  Builder<Post>  $query
     */
    public function scopeInCategory(Builder $query, ?string $category): void
    {
        if ($category === null || $category === '') {
            return;
        }

        $values = explode(',', $category);
        $ids = array_map('intval', array_diff($values, ['none']));
        $withoutCategory = in_array('none', $values, true);

        $query->where(function (Builder $query) use ($ids, $withoutCategory): void {
            if ($ids !== []) {
                $query->whereIn('category_id', $ids);
            }
            if ($withoutCategory) {
                $query->orWhereNull('category_id');
            }
        });
    }

    /**
     * priority=<value>, priority=none (not set), or several comma-separated (priority=high,none): any of them matches.
     *
     * @param  Builder<Post>  $query
     */
    public function scopeWithPriorities(Builder $query, ?string $priorities): void
    {
        if ($priorities === null || $priorities === '') {
            return;
        }

        $values = explode(',', $priorities);
        $set = array_values(array_diff($values, ['none']));
        $unset = in_array('none', $values, true);

        $query->where(function (Builder $query) use ($set, $unset): void {
            if ($set !== []) {
                $query->whereIn('priority', $set);
            }
            if ($unset) {
                $query->orWhereNull('priority');
            }
        });
    }

    /**
     * status_id=<id> or several comma-separated (status_id=3,5): any of them matches.
     *
     * @param  Builder<Post>  $query
     */
    public function scopeInStatuses(Builder $query, ?string $statusIds): void
    {
        if ($statusIds === null || $statusIds === '') {
            return;
        }

        $query->whereIn('status_id', array_map('intval', explode(',', $statusIds)));
    }

    /**
     * The list view: posts still in «Новое» first, the rest after them; the chosen sort applies inside both parts.
     * Null leaves the order to sorted() alone.
     *
     * @param  Builder<Post>  $query
     */
    public function scopeNewFirst(Builder $query, ?int $newStatusId): void
    {
        if ($newStatusId !== null) {
            $query->orderByRaw('case when posts.status_id = ? then 0 else 1 end', [$newStatusId]);
        }
    }

    /**
     * @param  Builder<Post>  $query
     */
    public function scopeSorted(Builder $query, string $sort): void
    {
        match ($sort) {
            'manual' => $query->orderByRaw('kanban_position is null desc')->orderBy('kanban_position')->latest('id'),
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
