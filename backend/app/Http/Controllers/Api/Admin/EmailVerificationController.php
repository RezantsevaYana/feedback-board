<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Email confirmation, soft mode: the account works right away, the cabinet only nags until the link is opened.
 * The letter links to the SPA (/app/verify-email/...), which posts the signed path here.
 */
class EmailVerificationController extends Controller
{
    /**
     * The signature (signed:relative) is the proof, no session needed: the letter may be opened on another device.
     */
    public function verify(User $user, string $hash): JsonResponse
    {
        abort_unless(hash_equals(sha1($user->getEmailForVerification()), $hash), 403, 'Ссылка недействительна.');

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        return response()->json(['data' => ['id' => $user->id, 'email' => $user->email]]);
    }

    public function resend(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        if (! $user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();
        }

        return response()->noContent();
    }
}