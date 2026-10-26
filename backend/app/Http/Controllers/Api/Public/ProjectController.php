<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Project;
use App\Models\Status;
use Illuminate\Http\JsonResponse;

class ProjectController extends Controller
{
    public function show(Project $project): JsonResponse
    {
        return response()->json(['data' => [
            'name' => $project->name,
            'slug' => $project->slug,
            // false only reaches the team: everyone else gets 403 from EnsureProjectPublished
            'published' => $project->isPublished(),
            'categories' => $project->categories->map(fn (Category $c) => ['id' => $c->id, 'name' => $c->name, 'color' => $c->color]),
            'statuses' => $project->statuses->map(fn (Status $s) => [
                'id' => $s->id,
                'slug' => $s->slug,
                'name' => $s->name,
                'color' => $s->color,
                'position' => $s->position,
                'is_system' => $s->is_system,
            ]),
        ]]);
    }
}
