<?php

namespace App\Services;

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Creation path shared by the artisan command (`project:create`) and the self-serve
 * BoardController@store. Keeps default categories / statuses setup in one place so
 * boards created from either entry point look identical.
 */
class ProjectService
{
    /**
     * @param  array<int, string>  $origins
     */
    public function create(string $name, string $slug, array $origins, ?User $owner): Project
    {
        return DB::transaction(function () use ($name, $slug, $origins, $owner) {
            $project = Project::create([
                'name' => $name,
                'slug' => $slug,
                'owner_id' => $owner?->id,
                'allowed_origins' => array_values($origins),
            ]);
            $project->createDefaultCategories();
            $project->createDefaultStatuses();

            // Self-serve owners also sit in the pivot with role=owner. The owner_id column stays
            // as a "who created this" cache; permission checks go through roleOn($project).
            if ($owner) {
                $project->members()->attach($owner->id, ['role' => ProjectRole::Owner->value]);
            }

            return $project;
        });
    }

    /**
     * Передача владения из админки сервиса. Старого владельца не выкидываем — он остаётся
     * с ролью Admin, чтобы не отрезать его от доски (аккаунт жив, просто больше не owner).
     * Нового владельца либо повышаем, либо добавляем в пивот.
     *
     * NB: `isPublished()` зависит от `owner->hasVerifiedEmail()` — если у нового владельца
     * почта не подтверждена, доска уйдёт в «не опубликована» до подтверждения. UI предупреждает.
     */
    public function transferOwnership(Project $project, User $newOwner): void
    {
        DB::transaction(function () use ($project, $newOwner) {
            $previousOwnerId = $project->owner_id;

            if ($previousOwnerId === $newOwner->id) {
                return;
            }

            $project->owner_id = $newOwner->id;
            $project->save();

            // Нового владельца делаем owner (повышаем, если уже в пивоте с другой ролью)
            if ($project->members()->where('users.id', $newOwner->id)->exists()) {
                $project->members()->updateExistingPivot($newOwner->id, ['role' => ProjectRole::Owner->value]);
            } else {
                $project->members()->attach($newOwner->id, ['role' => ProjectRole::Owner->value]);
            }

            // Старого — на admin, если он был на доске; legacy-доски (owner_id=null) этот блок пропускают.
            if ($previousOwnerId && $project->members()->where('users.id', $previousOwnerId)->exists()) {
                $project->members()->updateExistingPivot($previousOwnerId, ['role' => ProjectRole::Admin->value]);
            }
        });
    }
}
