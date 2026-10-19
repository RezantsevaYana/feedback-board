<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Project;
use Illuminate\Http\JsonResponse;

class ProjectController extends Controller
{
    public function show(Project $project): JsonResponse
    {
        return response()->json(['data' => [
            'name' => $project->name,
            'slug' => $project->slug,
            'categories' => $project->categories->map(fn (Category $c) => ['id' => $c->id, 'name' => $c->name]),
        ]]);
    }
}
