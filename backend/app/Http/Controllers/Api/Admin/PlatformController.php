<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\JsonResponse;

/**
 * «Админка сервиса» (/app/platform), first iteration: read-only lists of every board and every account.
 * Only for superadmins — the route group checks the platform.admin gate.
 */
class PlatformController extends Controller
{
    /**
     * Every board, the trashed ones included (flagged by deleted_at), newest first.
     */
    public function boards(): JsonResponse
    {
        $boards = Project::withTrashed()
            ->with('owner:id,name,email,email_verified_at')
            ->withCount(['posts', 'members'])
            ->withMax('posts as last_post_at', 'created_at')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Project $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'slug' => $p->slug,
                'visibility' => $p->visibility->value,
                // null for legacy boards made by project:create
                'owner' => $p->owner ? ['id' => $p->owner->id, 'name' => $p->owner->name, 'email' => $p->owner->email] : null,
                'published' => $p->isPublished(),
                'posts_count' => $p->posts_count,
                'members_count' => $p->members_count,
                'last_post_at' => $p->last_post_at,
                'created_at' => $p->created_at,
                'deleted_at' => $p->deleted_at,
            ]);

        return response()->json(['data' => $boards]);
    }

    /**
     * Every account, newest first, with the boards it's on and its role there.
     */
    public function users(): JsonResponse
    {
        $users = User::with('projects:id,name,slug')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'email_verified' => $u->hasVerifiedEmail(),
                'is_superadmin' => $u->is_superadmin,
                'boards' => $u->projects->map(fn (Project $p) => [
                    'name' => $p->name,
                    'slug' => $p->slug,
                    'role' => $p->pivot->role,
                ])->values(),
                'created_at' => $u->created_at,
            ]);

        return response()->json(['data' => $users]);
    }
}