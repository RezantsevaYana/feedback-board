<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProjectInvitation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/**
 * «Вас приглашают» in the cabinet: pending invitations addressed to the logged-in user's email,
 * accepted or declined with one click instead of the letter's link.
 * Only for a confirmed email — otherwise anyone could register someone else's address and pick
 * up the invitations sent to it. The letter's link keeps working either way.
 */
class IncomingInvitationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        if (! $user->hasVerifiedEmail()) {
            return response()->json(['data' => []]);
        }

        $invitations = ProjectInvitation::pending()
            ->whereRaw('lower(email) = ?', [Str::lower($user->email)])
            ->whereHas('project')
            ->with(['project', 'inviter'])
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['data' => $invitations->map(fn (ProjectInvitation $i) => [
            'id' => $i->id,
            'board' => ['slug' => $i->project->slug, 'name' => $i->project->name],
            'role' => $i->role->value,
            'invited_by' => ['name' => $i->inviter?->name],
            'created_at' => $i->created_at,
            'expires_at' => $i->expires_at,
        ])]);
    }

    public function accept(Request $request, ProjectInvitation $invitation): JsonResponse
    {
        $this->guardMine($request->user(), $invitation);

        $invitation->acceptFor($request->user());

        return response()->json(['data' => [
            'board' => ['slug' => $invitation->project->slug, 'name' => $invitation->project->name],
            'role' => $invitation->role->value,
        ]]);
    }

    /** Declined = the slot is gone; the admin no longer sees it among the pending ones and can invite again. */
    public function destroy(Request $request, ProjectInvitation $invitation): Response
    {
        $this->guardMine($request->user(), $invitation);

        $invitation->delete();

        return response()->noContent();
    }

    private function guardMine(User $user, ProjectInvitation $invitation): void
    {
        // 404, not 403: someone else's invitation shouldn't even be confirmed to exist
        if (! $user->hasVerifiedEmail() || Str::lower($invitation->email) !== Str::lower($user->email)) {
            abort(404, 'Такого приглашения нет.');
        }
        if (! $invitation->isPending()) {
            abort(410, 'Приглашение уже не действует. Попросите, чтобы прислали новое.');
        }
    }
}