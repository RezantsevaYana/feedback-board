<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\ProjectRole;
use App\Http\Controllers\Controller;
use App\Models\AccessRequest;
use App\Models\Project;
use App\Models\User;
use App\Services\LoginLockout;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Team login on the board (Sanctum SPA: session cookie on our domain).
 */
class AuthController extends Controller
{
    public function login(Request $request, LoginLockout $lockout): JsonResponse
    {
        $request->merge(['email' => is_string($request->email) ? Str::lower(trim($request->email)) : $request->email]);
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ], [
            'email.required' => 'Введите email.',
            'email.email' => 'Введите email.',
            'password.required' => 'Введите пароль.',
        ]);

        // checked before the password, so the right one doesn't get through a lock either
        if ($lockout->locked($credentials['email'])) {
            throw ValidationException::withMessages([
                'password' => 'Вход по паролю заблокирован на '.LoginLockout::LOCK_MINUTES.' минут после '.LoginLockout::MAX_ATTEMPTS
                    .' неверных попыток. Чтобы войти раньше, задайте новый пароль по ссылке «Забыли пароль?».',
            ])->status(429);
        }

        if (! Auth::guard('web')->attempt($credentials)) {
            $lockout->failed($credentials['email']);
            // the same message for an unknown email and a wrong password
            throw ValidationException::withMessages(['email' => 'Неверный email или пароль.']);
        }

        $lockout->clear($credentials['email']);
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

        // waiting access requests, one query for all boards; brief() shows them only where this user decides
        $pending = AccessRequest::pending()
            ->whereIn('project_id', $pivotIds->merge($boards->pluck('id'))->unique())
            ->selectRaw('project_id, count(*) as total')->groupBy('project_id')
            ->pluck('total', 'project_id');

        return response()->json(['data' => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'email_verified' => $user->hasVerifiedEmail(),
            // opens «Админка сервиса» (/admin, Orchid) in the account menu. hasAccess() comes from Orchid's
            // UserAccess trait: it reads the `permissions` jsonb and matches 'platform.index' there
            // or on any role attached to the user.
            'is_superadmin' => $user->hasAccess('platform.index'),
            'projects' => $pivotProjects
                ->merge($boards->reject(fn (Project $p) => $pivotIds->contains($p->id)))
                ->sortBy('name')->values()
                ->map(fn (Project $p) => $this->brief($p, $user, $pending)),
            'boards' => $boards->map(fn (Project $p) => $this->brief($p, $user, $pending)),
        ]]);
    }

    /**
     * «Настройки аккаунта» → «Профиль». Only the name: it signs this user's posts on the boards.
     * The email isn't editable in V1.
     */
    public function update(Request $request): JsonResponse
    {
        $request->merge(['name' => is_string($request->name) ? trim($request->name) : $request->name]);
        $request->validate([
            'name' => ['required', 'string', 'max:80'],
        ], [
            'name.required' => 'Представьтесь — хотя бы имя.',
            'name.max' => 'Имя — не длиннее 80 символов.',
        ]);

        $request->user()->update(['name' => $request->name]);

        return $this->me($request);
    }

    /**
     * `pending_requests` — access requests waiting on this board; 0 where this user doesn't decide on them.
     *
     * @param  Collection<int, int>  $pending  board id → waiting requests
     * @return array{id: int, name: string, slug: string, role: ?string, pending_requests: int}
     */
    private function brief(Project $p, User $user, Collection $pending): array
    {
        // $p->pivot is set when the project came via $user->projects(). For boards() we fall back to Owner
        // because the user is on the owner_id column — see the factory / ProjectService invariant.
        $role = $p->pivot?->role ?? ($p->owner_id === $user->id ? 'owner' : null);
        $decides = $role !== null && ProjectRole::from($role)->canConfigureBoard();

        return [
            'id' => $p->id,
            'name' => $p->name,
            'slug' => $p->slug,
            'role' => $role,
            // the cabinet's side menu lists the «Доступ» sections of this mode
            'visibility' => $p->visibility->value,
            'pending_requests' => $decides ? (int) ($pending[$p->id] ?? 0) : 0,
        ];
    }
}
