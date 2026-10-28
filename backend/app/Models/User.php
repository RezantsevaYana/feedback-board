<?php

namespace App\Models;

use App\Enums\ProjectRole;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Boards this user has any role on. After slice 1 of docs/ROLES_PLAN.md the pivot carries
     * the role — see Project::PIVOT_FIELDS. Legacy "team admins"
     * created by admin:create / project:create live on the same pivot with role=admin.
     *
     * @return BelongsToMany<Project, $this>
     */
    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class)
            ->withPivot(Project::PIVOT_FIELDS);
    }

    /**
     * Boards this admin created from the self-serve UI.
     *
     * @return HasMany<Project, $this>
     */
    public function boards(): HasMany
    {
        return $this->hasMany(Project::class, 'owner_id');
    }

    /**
     * This user's role on the given board, or null if they're not on it.
     * Reads the pivot once per project; call sites that already loaded members get it for free.
     */
    public function roleOn(Project $project): ?ProjectRole
    {
        $pivot = $this->projects()->whereKey($project->getKey())->first()?->pivot;

        return $pivot ? ProjectRole::from($pivot->role) : null;
    }

    /**
     * On the board with any role. Only lets members see a board that isn't published yet
     * (EnsureProjectPublished); what they may do there is decided by the board.* gates.
     */
    public function managesProject(Project $project): bool
    {
        return $this->roleOn($project) !== null;
    }
}
