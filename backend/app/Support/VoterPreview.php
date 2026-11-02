<?php

namespace App\Support;

use App\Models\Post;
use App\Models\Vote;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The team's «кто голосовал» faces on a card: the latest three voters of every post on the page, in one query
 * (a window function per post), kept on the request for AdminPostResource. The full list stays behind
 * GET /admin/posts/{id}/voters.
 */
class VoterPreview
{
    public const SIZE = 3;

    /**
     * @param  Collection<int, Post>  $posts
     */
    public static function attach(Request $request, Collection $posts): void
    {
        if ($posts->isEmpty()) {
            return;
        }

        $ranked = Vote::query()
            ->leftJoin('users', 'users.id', '=', 'votes.user_id')
            ->leftJoin('voters', 'voters.token', '=', 'votes.voter_token')
            ->whereIn('votes.post_id', $posts->pluck('id'))
            ->selectRaw('votes.post_id, votes.user_id, coalesce(users.name, voters.display_name) as name, '
                .'row_number() over (partition by votes.post_id order by votes.created_at desc, votes.id desc) as place');

        $rows = DB::query()->fromSub($ranked, 'ranked')
            ->where('place', '<=', self::SIZE)
            ->orderBy('place')
            ->get();

        $request->attributes->set('voters_preview', $rows->groupBy('post_id')->map(fn (Collection $votes) => $votes
            ->map(fn ($v) => ['name' => $v->name, 'account' => $v->user_id !== null])
            ->values()
            ->all())->all());
    }
}
