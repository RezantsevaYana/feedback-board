<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\Vote;
use App\Support\VoterToken;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * One reaction per post per voter: a like or a dislike. Switching moves the vote.
 *
 * «Voter» is the account for signed-in users (votes.user_id, enforced by a partial unique
 * index) and the X-Voter-Token cookie for the anonymous widget (votes.voter_token, legacy
 * unique). Signed-in users keep their one vote across devices; the cookie-only path stays
 * for the cross-origin widget that has no session.
 */
class VoteController extends Controller
{
    public function store(Request $request, Post $post): JsonResponse
    {
        abort_if($post->is_hidden, 404);
        $user = $request->user('sanctum');
        // a member whose role can't vote (Читатель) is refused on a closed board; on an open one they're a guest
        // like anyone with the link. Taking a vote back stays open, see destroy()
        abort_if($post->project->isPrivate() && $user?->roleOn($post->project)?->canVote() === false, 403, 'Читатель на этой доске не голосует — только читает.');
        // open board + owner hid voting from guests (test.md п. 40). Members get through regardless of this flag;
        // destroy() stays open — withdrawing an old vote isn't new activity
        if (! $post->project->isPrivate() && $user === null && ! $post->project->guests_can_vote) {
            return response()->json([
                'message' => 'Войдите в аккаунт, чтобы проголосовать.',
                'reason' => 'guest_vote_disabled',
            ], 403);
        }
        // Signed-in users identify themselves by account, so the cookie is optional for them;
        // anonymous callers still need it (otherwise there's no way to deduplicate votes).
        $token = $user === null ? VoterToken::require($request) : VoterToken::from($request);
        $value = $request->validate(
            ['value' => ['required', Rule::in(['like', 'dislike'])]],
            ['value.*' => 'Укажите like или dislike.'],
        )['value'] === 'like' ? Vote::LIKE : Vote::DISLIKE;

        DB::transaction(function () use ($request, $post, $token, $user, $value) {
            $vote = $this->existingVote($post, $token, $user?->id)?->lockForUpdate()->first();

            if ($vote === null) {
                $attributes = [
                    'voter_token' => $token,
                    'user_id' => $user?->id,
                    'ip_hash' => VoterToken::ipHash($request),
                    'value' => $value,
                ];
                // Match the branch used in the lookup, so createOrFirst's own uniqueness guard
                // retries against the same key the partial index enforces.
                $match = $user !== null ? ['user_id' => $user->id] : ['voter_token' => $token];
                $vote = $post->votes()->createOrFirst($match, $attributes);
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
        $user = $request->user('sanctum');
        $token = $user === null ? VoterToken::require($request) : VoterToken::from($request);

        DB::transaction(function () use ($post, $token, $user) {
            $vote = $this->existingVote($post, $token, $user?->id)?->lockForUpdate()->first();
            if ($vote !== null) {
                $post->decrement(Vote::counterFor($vote->value));
                $vote->delete();
            }
        });

        return $this->state($post, null);
    }

    /**
     * Find this voter's vote on the post: by account first (survives new devices and cleared
     * cookies), by cookie otherwise. Returns null when there's no way to identify the voter —
     * which only happens if an anonymous caller somehow got past VoterToken::require().
     *
     * @return HasMany<Vote, Post>|null
     */
    private function existingVote(Post $post, ?string $token, ?int $userId): ?HasMany
    {
        if ($userId !== null) {
            return $post->votes()->where('user_id', $userId);
        }
        if ($token !== null) {
            return $post->votes()->where('voter_token', $token);
        }

        return null;
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
