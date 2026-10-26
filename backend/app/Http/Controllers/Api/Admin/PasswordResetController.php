<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Forgotten password: a letter with a one-time link to the SPA (/app/reset-password/{token}?email=…),
 * the page posts the new password here. The link lives 60 minutes (config/auth.php, passwords.users.expire).
 */
class PasswordResetController extends Controller
{
    /**
     * The same answer whether the address is known or not, so the form can't be used to probe for accounts.
     * The broker's own throttle (one letter per minute per address) is swallowed for the same reason.
     */
    public function send(Request $request): Response
    {
        $request->merge(['email' => is_string($request->email) ? Str::lower(trim($request->email)) : $request->email]);
        $request->validate(['email' => ['required', 'email']], [
            'email.required' => 'Введите email.',
            'email.email' => 'Введите email в формате name@example.com.',
        ]);

        rescue(fn () => Password::sendResetLink($request->only('email')));

        return response()->noContent();
    }

    /**
     * A new password logs the user in right away: the link from the letter proves the address,
     * so it also counts as an email confirmation.
     */
    public function reset(Request $request): JsonResponse
    {
        $request->merge(['email' => is_string($request->email) ? Str::lower(trim($request->email)) : $request->email]);
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'max:72'],
        ], [
            'password.required' => 'Придумайте пароль.',
            'password.min' => 'Пароль — хотя бы 8 символов.',
            'password.max' => 'Пароль — не длиннее 72 символов.',
        ]);

        $resetUser = null;
        $status = Password::reset($request->only('email', 'password', 'token'), function (User $user, string $password) use (&$resetUser) {
            $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)]);
            if (! $user->hasVerifiedEmail()) {
                $user->email_verified_at = now();
            }
            $user->save();
            $resetUser = $user;
        });

        if ($status !== Password::PASSWORD_RESET) {
            // unknown address and a stale or used token read the same to the visitor
            throw ValidationException::withMessages(['token' => 'Ссылка устарела или уже использована. Запросите новое письмо.']);
        }

        Auth::guard('web')->login($resetUser);
        $request->session()->regenerate();

        return app(AuthController::class)->me($request);
    }
}