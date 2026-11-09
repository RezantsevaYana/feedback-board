<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use App\Models\Category;
use App\Models\Post;
use App\Models\Project;
use Illuminate\Http\JsonResponse;

/**
 * «Пост недели» в каждой категории проекта: по одному победителю на категорию,
 * отобранному среди постов текущей ISO-недели (пн 00:00 — вс 23:59) по разнице
 * лайков и дизлайков, только с положительным рейтингом. Категории без победителя возвращаются с post=null,
 * чтобы список не «прыгал» от недели к неделе.
 */
class WeeklyWinnersController extends Controller
{
    public function index(Project $project): JsonResponse
    {
        $weekStart = now()->startOfWeek();
        $weekEnd = now()->endOfWeek();

        $categories = Category::where('project_id', $project->id)
            ->orderBy('position')
            ->get();

        // Один запрос на все посты недели, затем groupBy в памяти — победитель
        // = первый пост в своей группе после сортировки по рейтингу.
        $winners = Post::where('project_id', $project->id)
            ->whereNotNull('category_id')
            ->where('is_hidden', false)
            ->whereColumn('likes_count', '>', 'dislikes_count')
            ->where('created_at', '>=', $weekStart)
            ->where('created_at', '<=', $weekEnd)
            ->orderBy('category_id')
            ->orderByRaw('likes_count - dislikes_count desc')
            ->orderBy('id', 'desc')
            ->with(['category', 'status'])
            ->get()
            ->groupBy('category_id')
            ->map(fn ($posts) => $posts->first());

        return response()->json([
            'data' => $categories->map(fn (Category $category) => [
                'category' => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'color' => $category->color,
                    'position' => $category->position,
                ],
                'post' => $winners->get($category->id)
                    ? (new PostResource($winners->get($category->id)))->resolve(request())
                    : null,
            ])->values(),
            'meta' => [
                'week_start' => $weekStart->toIso8601String(),
                'week_end' => $weekEnd->toIso8601String(),
            ],
        ]);
    }
}
