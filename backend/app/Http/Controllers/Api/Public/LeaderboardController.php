<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Публичный лидерборд проекта: топ авторов идей и топ «поддержали» (автор по
 * сумме голосов за свои идеи). В выборку попадают только авторы, которые
 * оставили имя — анонимные идеи учитываются в счёте, но не светят автора.
 */
class LeaderboardController extends Controller
{
    public function index(Request $request, Project $project): JsonResponse
    {
        $filters = $request->validate([
            'scope' => ['nullable', Rule::in(['all', 'week'])],
            'metric' => ['nullable', Rule::in(['posts', 'support'])],
        ]);
        $scope = $filters['scope'] ?? 'all';
        $metric = $filters['metric'] ?? 'posts';

        // `$valueExpr` — хардкод, пользовательского ввода внутри нет, безопасно для интерполяции.
        $valueExpr = $metric === 'support'
            ? 'coalesce(sum(posts.likes_count - posts.dislikes_count), 0)'
            : 'count(posts.id)';

        $rows = DB::table('posts')
            ->join('voters', 'voters.token', '=', 'posts.author_token')
            ->where('posts.project_id', $project->id)
            ->where('posts.is_hidden', false)
            ->when($scope === 'week', fn ($q) => $q->where('posts.created_at', '>=', now()->subDays(7)))
            ->groupBy('posts.author_token', 'voters.display_name')
            ->select('posts.author_token as token', 'voters.display_name as name')
            ->selectRaw("$valueExpr as value")
            // отсекаем «нулевых» (только для support — count всегда ≥ 1 в своей группе)
            ->when($metric === 'support', fn ($q) => $q->havingRaw("$valueExpr > 0"))
            ->orderByRaw("$valueExpr desc")
            ->orderBy('voters.display_name')
            ->limit(20)
            ->get();

        return response()->json([
            'data' => $rows->values()->map(fn ($row, $i) => [
                'place' => $i + 1,
                'token' => $row->token,
                'name' => $row->name,
                'value' => (int) $row->value,
            ]),
            'meta' => ['scope' => $scope, 'metric' => $metric],
        ]);
    }
}
