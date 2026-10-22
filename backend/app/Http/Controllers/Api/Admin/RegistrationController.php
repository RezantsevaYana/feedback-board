<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RegisterRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Self-serve registration: creates a user and logs them in in the same request
 * so the client lands straight on the hub.
 */
class RegistrationController extends Controller
{
    public function store(RegisterRequest $request): JsonResponse
    {
        $user = User::create($request->only('name', 'email', 'password'));

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return app(AuthController::class)->me($request);
    }
}
