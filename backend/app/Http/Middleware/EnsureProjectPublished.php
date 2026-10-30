<?php

namespace App\Http\Middleware;

use App\Enums\BoardVisibility;
use App\Models\Attachment;
use App\Models\Post;
use App\Models\Project;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Three gates, highest priority first:
 *
 *   1. Project exists but the self-serve owner hasn't confirmed the email → closed to everyone
 *      but the team. The board can't be used to put a page online without a verified contact.
 *   2. Project is marked `private` → open to project_user members only.
 *   3. Everyone else sees the board.
 *
 * The 403 payload carries a stable `reason` ("owner_unverified" / "login_required" /
 * "no_access") so the SPA can show a specific screen instead of a generic error toast.
 * Goes after AllowProjectOrigin on widget routes, so the widget still gets CORS headers and reads the body.
 */
class EnsureProjectPublished
{
    public function handle(Request $request, Closure $next): Response
    {
        $project = $this->project($request);
        if ($project === null) {
            return $next($request);
        }

        /** @var User|null $user */
        $user = $request->user('sanctum');
        $hasRole = $user?->roleOn($project) !== null;

        // Owner hasn't confirmed the email yet — the board stays "ещё не опубликована".
        if (! $project->isPublished()) {
            if (! $hasRole) {
                return $this->deny('owner_unverified', 'Доска ещё не опубликована.');
            }

            return $next($request);
        }

        // Private boards: only members get through. The name goes along, so the gate screen can say
        // which board it is (the visitor has its link anyway); nothing else of the board does.
        if ($project->visibility === BoardVisibility::Private && ! $hasRole) {
            $board = ['name' => $project->name];

            return $user === null
                ? $this->deny('login_required', 'Это закрытая доска. Войдите, чтобы её увидеть.', $board)
                : $this->deny('no_access', 'У вас нет доступа к этой доске.', $board);
        }

        return $next($request);
    }

    /** @param  array{name: string}|null  $board */
    private function deny(string $reason, string $message, ?array $board = null): Response
    {
        return response()->json(array_filter(['message' => $message, 'reason' => $reason, 'board' => $board]), 403);
    }

    private function project(Request $request): ?Project
    {
        $bound = $request->route('project') ?? $request->route('post') ?? $request->route('attachment');

        return match (true) {
            $bound instanceof Project => $bound,
            $bound instanceof Post => $bound->project,
            $bound instanceof Attachment => $bound->post->project,
            default => null,
        };
    }
}
