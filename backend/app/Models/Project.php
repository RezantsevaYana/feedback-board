<?php

namespace App\Models;

use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['name', 'slug', 'allowed_origins'])]
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'allowed_origins' => 'array',
        ];
    }

    /**
     * @return HasMany<Post, $this>
     */
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    /**
     * @return HasMany<Category, $this>
     */
    public function categories(): HasMany
    {
        return $this->hasMany(Category::class)->orderBy('position')->orderBy('id');
    }

    /**
     * New projects start with "Баг" and "Идея"; the team renames or replaces them on the board.
     */
    public function createDefaultCategories(): void
    {
        foreach (Category::DEFAULTS as $position => $name) {
            $this->categories()->create(['name' => $name, 'position' => $position]);
        }
    }

    /**
     * Admins who manage this project.
     *
     * @return BelongsToMany<User, $this>
     */
    public function admins(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    /**
     * Whether the widget may submit from this site. Patterns allow "*", e.g. "http://localhost:*".
     */
    public function allowsOrigin(string $origin): bool
    {
        foreach ($this->allowed_origins ?? [] as $pattern) {
            if (Str::is(rtrim($pattern, '/'), $origin)) {
                return true;
            }
        }

        return false;
    }
}
