<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\ProjectRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateParticipantRequest;
use App\Http\Resources\InvitationResource;
use App\Http\Resources\ParticipantResource;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/**
 * Board team: list + role changes + removal. Pending invitations live next to members
 * on the same screen, so the list endpoint returns both.
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

        // «Добавлен» column: created the board / came by invitation / anything else (added before roles, by the CLI)
        $invited = $board->invitations()->whereNotNull('accepted_at')->pluck('email')
            ->map(fn (string $e) => Str::lower($e))->flip();
        foreach ($members as $member) {
            $member->setAttribute('source', match (true) {
                $member->id === $board->owner_id => 'created',
                $invited->has(Str::lower($member->email)) => 'invitation',
                default => 'manual',
            });
        }

        return response()->json([
            'data' => [
                'members' => ParticipantResource::collection($members),
                'invitations' => InvitationResource::collection($invitations),
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
