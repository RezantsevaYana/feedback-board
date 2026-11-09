<?php

namespace App\Models;

use App\Enums\AccessRequestStatus;
use App\Enums\ProjectRole;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * One slot per (board, email) at a time — see the partial unique index in the migration.
 * The flow is: created → letter sent → either accepted_at set + pivot row made,
 * or expires_at passed, or the admin cancels (row removed).
 */
#[Fillable(['project_id', 'email', 'role', 'token', 'expires_at', 'invited_by'])]
class ProjectInvitation extends Model
{
    public const TTL_DAYS = 7;

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
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
     * Put the user on the board with the invited role and close the slot. Both entry points use it:
     * the letter's link (InvitationController::accept) and «Принять» in the cabinet.
     */
    public function acceptFor(User $user): void
    {
        DB::transaction(function () use ($user) {
            $this->project->members()->syncWithoutDetaching([
                $user->id => ['role' => $this->role->value],
            ]);
            $this->project->accessRequests()->pending()->where('email', $this->email)->update([
                'status' => AccessRequestStatus::Approved->value,
                'role_on_approve' => $this->role->value,
                'decided_by' => $this->invited_by,
                'decided_at' => now(),
            ]);
            $this->accepted_at = now();
            $this->save();
        });
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
