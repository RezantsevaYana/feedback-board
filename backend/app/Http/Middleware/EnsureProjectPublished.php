<?php

namespace App\Http\Middleware;

use App\Models\Attachment;
use App\Models\Post;
use App\Models\Project;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A self-serve board opens to the public once its owner has confirmed the email (Project::isPublished).
 * Until then the board, its posts, votes and files answer 403 to everyone but the team, so the owner
 * can set it up and look at it, while unconfirmed accounts can't be used to put pages online.
 * Goes after AllowProjectOrigin on widget routes, so the widget still gets CORS headers and can read the message.
 */
class EnsureProjectPublished
{
    public function handle(Request $request, Closure $next): Response
    {
        $project = $this->project($request);

        if ($project !== null && ! $project->isPublished()) {
            /** @var User|null $user */
            $user = $request->user('sanctum');
            if (! $user?->managesProject($project)) {
                return response()->json(['message' => 'Доска ещё не опубликована.'], 403);
            }
        }

        return $next($request);
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