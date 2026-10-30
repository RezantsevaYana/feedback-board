<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\AccessRequestStatus;
use App\Enums\ProjectRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateParticipantRequest;
use App\Http\Resources\AccessRequestResource;
use App\Http\Resources\InvitationResource;
use App\Http\Resources\ParticipantResource;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/**
 * Board team: list + role changes + removal. Pending invitations and access requests live next to members
 * on the same screen, so the list endpoint returns all three.
 *
 * Authorization: board.configure (owner / admin). The two special guardrails are enforced
 * in-controller because they aren't role-level rules — they're invariants:
 *   - Owner's row is immutable.
 *   - You can't remove yourself (prevents a board losing its last admin by accident).
 */
class ParticipantController extends Controller
{
    public function index(Request $request, Project $board): JsonResponse
    {
        $this->authorizeConfigure($request, $board);

        $members = $board->members()->orderBy('name')->get();
        $invitations = $board->invitations()->with('inviter')->pending()
            ->orderByDesc('created_at')->get();

        $requests = $board->accessRequests()->pending()->orderBy('created_at')->get();

        // «Добавлен» column: created the board / asked by the URL and got approved / came by invitation /
        // anything else (added before roles, by the CLI). An approved request also goes through an invitation,
        // so it's checked first.
        $invited = $board->invitations()->whereNotNull('accepted_at')->pluck('email')
            ->map(fn (string $e) => Str::lower($e))->flip();
        $approved = $board->accessRequests()->where('status', AccessRequestStatus::Approved->value)->pluck('email')
            ->map(fn (string $e) => Str::lower($e))->flip();
        foreach ($members as $member) {
            $email = Str::lower($member->email);
            $member->setAttribute('source', match (true) {
                $member->id === $board->owner_id => 'created',
                $approved->has($email) && $invited->has($email) => 'request',
                $invited->has($email) => 'invitation',
                default => 'manual',
            });
        }

        return response()->json([
            'data' => [
                'members' => ParticipantResource::collection($members),
                'invitations' => InvitationResource::collection($invitations),
                'requests' => AccessRequestResource::collection($requests),
            ],
        ]);
    }

    public function update(UpdateParticipantRequest $request, Project $board, User $user): JsonResponse
    {
        $this->authorizeConfigure($request, $board);
        $this->guardMembership($board, $user);
        $this->guardOwnerUntouchable($board, $user);

        $board->members()->updateExistingPivot($user->id, ['role' => $request->validated('role')]);

        $updated = $board->members()->whereKey($user->id)->first();

        return response()->json(['data' => new ParticipantResource($updated)]);
    }

    public function destroy(Request $request, Project $board, User $user): Response
    {
        $this->authorizeConfigure($request, $board);
        $this->guardMembership($board, $user);
        $this->guardOwnerUntouchable($board, $user);

        if ($request->user()->id === $user->id) {
            abort(422, 'Нельзя удалить себя с доски. Передайте владение или попросите другого админа.');
        }

        $board->members()->detach($user->id);

        return response()->noContent();
    }

    private function authorizeConfigure(Request $request, Project $board): void
    {
        $role = $request->user()->roleOn($board);
        if (! $role?->canConfigureBoard()) {
            abort(403, 'Нужны права настройки доски.');
        }
    }

    private function guardMembership(Project $board, User $user): void
    {
        if (! $board->members()->whereKey($user->id)->exists()) {
            abort(404, 'Этот пользователь не на вашей доске.');
        }
    }

    private function guardOwnerUntouchable(Project $board, User $user): void
    {
        if ($board->owner_id === $user->id) {
            abort(422, 'Владельца доски нельзя изменить или удалить. Передача владения — через поддержку.');
        }
    }
}
