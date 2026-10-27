<?php

namespace App\Services;

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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
                'team_domain' => $owner ? $this->teamDomainFor($owner->email) : null,
                'allowed_origins' => array_values($origins),
            ]);
            $project->createDefaultCategories();
            $project->createDefaultStatuses();

            // Self-serve owners also sit in the pivot with role=owner. The owner_id column stays
            // as a "who created this" cache; permission checks go through roleOn($project).
            if ($owner) {
                $project->members()->attach($owner->id, [
                    'role' => ProjectRole::Owner->value,
                    'is_team_member' => true,
                ]);
            }

            return $project;
        });
    }

    /**
     * Owner's email domain unless it's a public mail provider — see config/feedback.php.
     * Public providers (gmail, yandex, …) return null: the "team" badge isn't automatic there.
     */
    private function teamDomainFor(string $email): ?string
    {
        $domain = Str::lower(Str::after($email, '@'));
        if ($domain === '') {
            return null;
        }
        $publicDomains = array_map('strtolower', (array) config('feedback.public_email_domains', []));

        return in_array($domain, $publicDomains, true) ? null : $domain;
    }
}
