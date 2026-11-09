<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RegisterRequest;
use App\Models\User;
use App\Services\LegalDocuments;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Self-serve registration: creates a user and logs them in in the same request
 * so the client lands straight on the hub. The confirmation letter goes out at once;
 * if the mailer fails, the account still opens and the cabinet offers to resend.
 */
class RegistrationController extends Controller
{
    public function store(RegisterRequest $request, LegalDocuments $documents): JsonResponse
    {
        $user = DB::transaction(function () use ($request, $documents) {
            $user = User::create($request->only('name', 'email', 'password'));
            $documents->record($user, 'registration');

            return $user;
        });
        rescue(fn () => $user->sendEmailVerificationNotification());

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return app(AuthController::class)->me($request);
    }
}
