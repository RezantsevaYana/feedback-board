<?php

namespace App\Models;

use App\Enums\AccessRequestStatus;
use App\Enums\ProjectRole;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * «Запросить доступ» on a private board. One pending row per (board, email) — see the migration.
 * Approving doesn't put the person on the board by itself: it issues an invitation with the chosen role,
 * and the letter's link goes through the usual acceptance screen (name and password, or «Присоединиться»).
 */
#[Fillable(['project_id', 'email', 'name', 'message', 'user_id', 'status', 'role_on_approve', 'reject_reason', 'decided_by', 'decided_at'])]
class AccessRequest extends Model
{
    /** After a rejection the same address can't ask this board again for a day: no letter to the admins either. */
    public const REJECT_COOLDOWN_HOURS = 24;

    protected function casts(): array
    {
        return [
            'status' => AccessRequestStatus::class,
            'role_on_approve' => ProjectRole::class,
            'decided_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @param Builder<self> $query */
    public function scopePending(Builder $query): void
    {
        $query->where('status', AccessRequestStatus::Pending->value);
    }

    public function isPending(): bool
    {
        return $this->status === AccessRequestStatus::Pending;
    }
}