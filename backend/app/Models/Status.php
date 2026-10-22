<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Колонка на канбане. Три системных статуса (new/done/rejected) создаются
 * вместе с проектом и защищены от удаления; остальные команда добавляет сама.
 */
#[Fillable(['name', 'slug', 'color', 'position', 'is_system'])]
class Status extends Model
{
    /** Защищены от удаления; у каждого проекта всегда есть эти три. */
    public const SYSTEM_SLUGS = ['new', 'done', 'rejected'];

    /** Куда возвращаются посты, если удалили кастомный столбец. */
    public const FALLBACK_SLUG = 'new';

    /** Shared palette with App\Models\Category: slug → CSS token in scss/base/_tokens.scss. */
    public const PALETTE = ['red', 'orange', 'yellow', 'green', 'blue', 'pink'];

    /** Default colours for the three system columns created with a fresh project. */
    public const DEFAULT_COLORS = ['new' => 'blue', 'done' => 'green', 'rejected' => 'red'];

    public const MAX_PER_PROJECT = 15;

    protected $attributes = [
        'is_system' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'position' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return HasMany<Post, $this>
     */
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }
}
