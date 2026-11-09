<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\User;
use App\Services\ProjectService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FeedbackBoardSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $project = Project::withTrashed()->where('slug', 'feedback-board')->first();

            if (! $project) {
                $project = app(ProjectService::class)->create(
                    'Развитие Feedback Board', 'feedback-board', [], null,
                );
            }

            if (! $project->owner_id) {
                $owner = User::orderBy('id')->get()->first(
                    fn (User $user) => $user->hasAccess('platform.systems.roles') && $user->hasAccess('platform.boards'),
                );

                if ($owner) {
                    app(ProjectService::class)->transferOwnership($project, $owner);
                }
            }
        });
    }
}
