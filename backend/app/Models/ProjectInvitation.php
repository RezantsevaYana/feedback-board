<?php

namespace App\Models;

use App\Enums\ProjectRole;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * One slot per (board, email) at a time — see the partial unique index in the migration.
 * The flow is: created → letter sent → either accepted_at set + pivot row made,
 * or expires_at passed, or the admin cancels (row removed).
 */
#[Fillable(['project_id', 'email', 'role', 'token', 'expires_at', 'invited_by', 'is_team_member'])]
class ProjectInvitation extends Model
{
    public const TTL_DAYS = 7;

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'is_team_member' => 'boolean',
            'role' => ProjectRole::class,
        ];
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<User, $this> */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    /** @param Builder<self> $query */
    public function scopePending(Builder $query): void
    {
        $query->whereNull('accepted_at')->where('expires_at', '>', now());
    }

    public function isPending(): bool
    {
        return $this->accepted_at === null && $this->expires_at->isFuture();
    }

    /**
     * 64 chars of url-safe entropy. The letter carries this verbatim; the acceptance screen
     * posts it back and we lookup by it.
     */
    public static function newToken(): string
    {
        return Str::random(64);
    }

    public static function defaultExpiry(): Carbon
    {
        return now()->addDays(self::TTL_DAYS);
    }
}
