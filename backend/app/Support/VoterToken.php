<?php

namespace App\Support;

use App\Models\Post;
use App\Models\Vote;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Anonymous voter id: a UUID the board keeps in localStorage and sends as X-Voter-Token.
 */
class VoterToken
{
    public const HEADER = 'X-Voter-Token';

    /**
     * С доски прилетает в заголовке X-Voter-Token. Виджет (cross-origin) заголовок
     * не шлёт, чтобы не инициировать CORS-preflight — там же мультипарт-форма,
     * поэтому пробрасываем токен полем `voter_token` в теле запроса.
     */
    public static function from(Request $request): ?string
    {
        $token = $request->header(self::HEADER) ?? $request->input('voter_token');

        return is_string($token) && Str::isUuid($token) ? strtolower($token) : null;
    }

    public static function require(Request $request): string
    {
        return self::from($request) ?? throw ValidationException::withMessages([
            'voter_token' => 'Не удалось определить браузер для голосования. Обновите страницу.',
        ]);
    }

    /**
     * Remember how the current browser voted on these posts (post id => +1/-1), so resources can output my_vote.
     *
     * @param  Collection<int, Post>  $posts
     */
    public static function markVoted(Request $request, Collection $posts): void
    {
        $token = self::from($request);
        if ($token === null || $posts->isEmpty()) {
            return;
        }

        $votes = Vote::where('voter_token', $token)
            ->whereIn('post_id', $posts->pluck('id'))
            ->pluck('value', 'post_id')
            ->all();

        $request->attributes->set('my_votes', $votes);
    }

    public static function ipHash(Request $request): string
    {
        return hash('sha256', $request->ip().config('app.key'));
    }
}
