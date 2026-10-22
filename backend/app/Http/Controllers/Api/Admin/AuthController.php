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
     * `projects` — legacy teams via project_user pivot (still used by team-mode login on the board).
     * `boards` — self-serve boards owned by this user, drives the admin hub.
     */
    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json(['data' => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'projects' => $user->projects()->orderBy('name')->get()
                ->map(fn (Project $p) => ['id' => $p->id, 'name' => $p->name, 'slug' => $p->slug]),
            'boards' => $user->boards()->orderBy('name')->get()
                ->map(fn (Project $p) => ['id' => $p->id, 'name' => $p->name, 'slug' => $p->slug]),
        ]]);
    }
}
