<?php

namespace App\Models;

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
     * Projects this admin manages — legacy pivot used by boards created via
     * `admin:create` and `project:create` artisan commands. Still backs team-mode login
     * on the public board. Self-serve boards use the owner_id column instead; see boards().
     *
     * @return BelongsToMany<Project, $this>
     */
    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class);
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

    public function managesProject(Project $project): bool
    {
        // self-serve owner (owner_id) or a legacy team member (project_user pivot)
        return $project->owner_id === $this->id
            || $this->projects()->whereKey($project->getKey())->exists();
    }
}
