<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * What a post is about: "Баг", "Идея", "Доставка"… Configured by the team per project.
 */
#[Fillable(['name', 'color', 'position'])]
class Category extends Model
{
    // Default categories a new project starts with: name => palette slug.
    public const DEFAULTS = ['Баг' => 'red', 'Идея' => 'blue'];

    // Palette shown in the admin modal; CSS tokens for each slug live in scss/base/_tokens.scss.
    public const PALETTE = ['red', 'orange', 'yellow', 'green', 'blue', 'pink'];

    public const MAX_PER_PROJECT = 20;

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
