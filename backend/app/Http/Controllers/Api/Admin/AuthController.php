<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Team login on the board (Sanctum SPA: session cookie on our domain).
 */
class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ], [
            'email.required' => 'Введите email.',
            'email.email' => 'Введите email.',
            'password.required' => 'Введите пароль.',
        ]);

        if (! Auth::guard('web')->attempt($credentials)) {
            // the same message for an unknown email and a wrong password
            throw ValidationException::withMessages(['email' => 'Неверный email или пароль.']);
        }

        $request->session()->regenerate();

        return $this->me($request);
    }

    public function logout(Request $request): Response
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }

    /**
     * The board calls this on load: 401 means guest, 200 means team mode for the listed projects.
     * `projects` — every board this user sits on (project_user). After slice 1 of docs/ROLES_PLAN.md
     *              the pivot carries the role, and owners live there too, so the merge
     *              with boards() dedupes on id.
     * `boards` — self-serve boards owned by this user, drives the admin hub.
     */
    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $pivotProjects = $user->projects()->get();
        $pivotIds = $pivotProjects->pluck('id');
        $boards = $user->boards()->orderBy('name')->get();

        return response()->json(['data' => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'email_verified' => $user->hasVerifiedEmail(),
            'projects' => $pivotProjects
                ->merge($boards->reject(fn (Project $p) => $pivotIds->contains($p->id)))
                ->sortBy('name')->values()
                ->map(fn (Project $p) => $this->brief($p, $user)),
            'boards' => $boards->map(fn (Project $p) => $this->brief($p, $user)),
        ]]);
    }

    /**
     * @return array{id: int, name: string, slug: string, role: ?string}
     */
    private function brief(Project $p, User $user): array
    {
        // $p->pivot is set when the project came via $user->projects(). For boards() we fall back to Owner
        // because the user is on the owner_id column — see the factory / ProjectService invariant.
        $role = $p->pivot?->role ?? ($p->owner_id === $user->id ? 'owner' : null);

        return [
            'id' => $p->id,
            'name' => $p->name,
            'slug' => $p->slug,
            'role' => $role,
        ];
    }
}
