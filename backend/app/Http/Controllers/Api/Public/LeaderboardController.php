<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\User;
use App\Support\VoterToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Публичный лидерборд проекта: топ авторов идей и топ «поддержали» (автор по
 * сумме голосов за свои идеи). В выборку попадают только авторы с именем —
 * анонимные идеи учитываются в счёте, но не светят автора.
 *
 * Автор — это аккаунт, если пост написан из аккаунта (posts.author_user_id): один человек
 * с ноутбука и телефона — одна строка, имя из профиля. Пост гостя (виджет, открытая доска)
 * считается по браузеру (posts.author_token) с именем из voters, как раньше.
 *
 * Токен браузера наружу не отдаём (по нему голосуют): строка несёт непрозрачный `key`
 * и флаг `me` — «это вы», по аккаунту или по токену текущего браузера.
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

        // `$valueExpr` и `$authorExpr` — хардкод, пользовательского ввода внутри нет, безопасно для интерполяции.
        $valueExpr = $metric === 'support'
            ? 'coalesce(sum(posts.likes_count - posts.dislikes_count), 0)'
            : 'count(posts.id)';
        $authorExpr = "case when posts.author_user_id is not null then 'u:' || posts.author_user_id else 't:' || posts.author_token end";

        $rows = DB::table('posts')
            ->leftJoin('users', 'users.id', '=', 'posts.author_user_id')
            // голос гостя по токену — только для постов без аккаунта, иначе браузер аккаунта задвоит строку
            ->leftJoin('voters', fn ($join) => $join->on('voters.token', '=', 'posts.author_token')->whereNull('posts.author_user_id'))
            ->where('posts.project_id', $project->id)
            ->where('posts.is_hidden', false)
            ->where(fn ($q) => $q->whereNotNull('users.id')->orWhereNotNull('voters.display_name'))
            ->when($scope === 'week', fn ($q) => $q->where('posts.created_at', '>=', now()->subDays(7)))
            ->groupByRaw($authorExpr)
            ->selectRaw("$authorExpr as author")
            ->selectRaw('max(coalesce(users.name, voters.display_name)) as name')
            ->selectRaw("$valueExpr as value")
            // отсекаем «нулевых» (только для support — count всегда ≥ 1 в своей группе)
            ->when($metric === 'support', fn ($q) => $q->havingRaw("$valueExpr > 0"))
            ->orderByRaw("$valueExpr desc")
            ->orderByRaw('max(coalesce(users.name, voters.display_name))')
            ->limit(20)
            ->get();

        /** @var User|null $user */
        $user = $request->user('sanctum');
        $mine = array_filter([
            $user ? 'u:'.$user->id : null,
            ($token = VoterToken::from($request)) ? 't:'.$token : null,
        ]);

        return response()->json([
            'data' => $rows->values()->map(fn ($row, $i) => [
                'place' => $i + 1,
                'key' => $this->publicKey($row->author),
                'name' => $row->name,
                'value' => (int) $row->value,
                'me' => in_array($row->author, $mine, true),
            ]),
            'meta' => ['scope' => $scope, 'metric' => $metric],
        ]);
    }

    /** `u:{id}` как есть; токен браузера — только хешем, чтобы по лидерборду нельзя было голосовать за кого-то. */
    private function publicKey(string $author): string
    {
        return str_starts_with($author, 't:')
            ? 't:'.substr(hash('sha256', substr($author, 2).config('app.key')), 0, 16)
            : $author;
    }
}