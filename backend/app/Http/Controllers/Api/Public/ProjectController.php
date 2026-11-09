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
            // the widget and the board check this to decide whether to show the login prompt
            'visibility' => $project->visibility->value,
            // the widget paints its button and accents with it; null = the service's green
            'widget_color' => $project->widget_color,
            // custom label on the launcher button; null = the default «Обратная связь»
            'widget_launcher_text' => $project->widget_launcher_text,
            // true → the board page lets search engines index it; false → BoardPage sets <meta robots noindex>
            // (test.md п. 19). Closed boards gate before this reaches gosts, but we still send it for teams.
            'search_indexable' => $project->search_indexable,
            // test.md п. 40: what a guest may do on this open board. false → the board form / the widget show
            // «Войдите, чтобы …» instead of the controls; the widget on the site hides itself when posts are off
            'guests_can_post' => $project->guests_can_post,
            'guests_can_vote' => $project->guests_can_vote,
            'categories' => $project->categories->map(fn (Category $c) => ['id' => $c->id, 'name' => $c->name, 'color' => $c->color]),
            'default_status_id' => $project->default_status_id,
            'statuses' => $project->statuses->map(fn (Status $s) => [
                'id' => $s->id,
                'slug' => $s->slug,
                'name' => $s->name,
                'color' => $s->color,
                'position' => $s->position,
            ]),
        ]]);
    }
}
