<?php

namespace App\Models;

use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[Fillable(['name', 'slug', 'owner_id', 'allowed_origins'])]
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory, SoftDeletes;

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
     * Колонки канбана этого проекта: 3 системных + добавленные командой, в порядке позиций.
     *
     * @return HasMany<Status, $this>
     */
    public function statuses(): HasMany
    {
        return $this->hasMany(Status::class)->orderBy('position')->orderBy('id');
    }

    /**
     * New projects start with "Баг" and "Идея"; the team renames or replaces them on the board.
     */
    public function createDefaultCategories(): void
    {
        $position = 0;
        foreach (Category::DEFAULTS as $name => $color) {
            $this->categories()->create(['name' => $name, 'color' => $color, 'position' => $position++]);
        }
    }

    /**
     * Три системных статуса на канбане: Новое → …кастомные команды… → Готово / Отклонено.
     * Между 100 и 9000 есть «воздух» для кастомных позиций.
     */
    public function createDefaultStatuses(): void
    {
        $defaults = [
            ['slug' => 'new', 'name' => 'Новое', 'position' => 100],
            ['slug' => 'done', 'name' => 'Готово', 'position' => 9000],
            ['slug' => 'rejected', 'name' => 'Отклонено', 'position' => 9100],
        ];
        foreach ($defaults as $s) {
            $this->statuses()->create([...$s, 'color' => Status::DEFAULT_COLORS[$s['slug']], 'is_system' => true]);
        }
    }

    /**
     * Куда класть новые посты по умолчанию и куда возвращаются посты при удалении кастомного столбца.
     */
    public function defaultStatus(): ?Status
    {
        return $this->statuses()->where('slug', Status::FALLBACK_SLUG)->first();
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
     * Self-serve owner — the admin who created this board from the UI.
     * Null for legacy projects created via `project:create` artisan command.
     *
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
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
