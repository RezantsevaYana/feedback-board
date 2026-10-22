<?php

namespace App\Services;

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

            return $project;
        });
    }
}
