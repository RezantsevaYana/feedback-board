<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\Vote;
use App\Support\VoterToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * One reaction per post per browser (X-Voter-Token): a like or a dislike. Switching moves the vote.
 */
class VoteController extends Controller
{
    public function store(Request $request, Post $post): JsonResponse
    {
        abort_if($post->is_hidden, 404);
        $token = VoterToken::require($request);
        $value = $request->validate(
            ['value' => ['required', Rule::in(['like', 'dislike'])]],
            ['value.*' => 'Укажите like или dislike.'],
        )['value'] === 'like' ? Vote::LIKE : Vote::DISLIKE;

        DB::transaction(function () use ($request, $post, $token, $value) {
            $vote = $post->votes()->where('voter_token', $token)->lockForUpdate()->first();

            if ($vote === null) {
                $vote = $post->votes()->createOrFirst(['voter_token' => $token], ['ip_hash' => VoterToken::ipHash($request), 'value' => $value]);
                if ($vote->wasRecentlyCreated) {
                    $post->increment(Vote::counterFor($value));
                }
            } elseif ($vote->value !== $value) {
                $post->decrement(Vote::counterFor($vote->value));
                $post->increment(Vote::counterFor($value));
                $vote->update(['value' => $value]);
            }
        });

        return $this->state($post, $value === Vote::LIKE ? 'like' : 'dislike');
    }

    public function destroy(Request $request, Post $post): JsonResponse
    {
        abort_if($post->is_hidden, 404);
        $token = VoterToken::require($request);

        DB::transaction(function () use ($post, $token) {
            $vote = $post->votes()->where('voter_token', $token)->lockForUpdate()->first();
            if ($vote !== null) {
                $vote->delete();
                $post->decrement(Vote::counterFor($vote->value));
            }
        });

        return $this->state($post, null);
    }

    private function state(Post $post, ?string $myVote): JsonResponse
    {
        $post->refresh();

        return response()->json(['data' => [
            'likes_count' => $post->likes_count,
            'dislikes_count' => $post->dislikes_count,
            'my_vote' => $myVote,
        ]]);
    }
}
