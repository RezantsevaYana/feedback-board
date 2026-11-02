<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\AccessRequestStatus;
use App\Enums\ProjectRole;
use App\Http\Controllers\Controller;
use App\Http\Resources\InvitationResource;
use App\Mail\AccessApprovedMail;
use App\Mail\AccessRejectedMail;
use App\Models\AccessRequest;
use App\Models\Project;
use App\Models\ProjectInvitation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

/**
 * «Запросы на доступ» on the participants page: approve with a role, reject with an optional reason,
 * and the address can't ask again for a day (AccessRequest::REJECT_COOLDOWN_HOURS). The list itself comes with the participants (ParticipantController::index).
 * Authorization: owner / admin, like the rest of the team screen.
 */
class AccessRequestController extends Controller
{
    /**
     * Approval issues an invitation with the chosen role (or refreshes a pending one for the same address),
     * and the «Доступ открыт» letter carries its link. The person lands on the board through the usual
     * acceptance screen, so there is one way onto a board and no second login path.
     */
    public function approve(Request $request, Project $board, AccessRequest $accessRequest): JsonResponse
    {
        $this->authorizeConfigure($request, $board);
        $this->guardPending($board, $accessRequest);

        $role = ProjectRole::from($request->validate([
            'role' => ['required', 'string', Rule::in($this->assignable())],
        ], [
            'role.required' => 'Выберите роль.',
            'role.in' => 'Такой роли нет. Доступны: Админ, Менеджер, Участник, Читатель.',
        ])['role']);

        $invitation = DB::transaction(function () use ($request, $board, $accessRequest, $role) {
            $accessRequest->update([
                'status' => AccessRequestStatus::Approved,
                'role_on_approve' => $role,
                'decided_by' => $request->user()->id,
                'decided_at' => now(),
            ]);

            $fields = [
                'role' => $role,
                'invited_by' => $request->user()->id,
                'token' => ProjectInvitation::newToken(),
                'expires_at' => ProjectInvitation::defaultExpiry(),
            ];
            $pending = $board->invitations()->pending()->where('email', $accessRequest->email)->first();
            if ($pending) {
                $pending->update($fields);

                return $pending;
            }

            return $board->invitations()->create(['email' => $accessRequest->email] + $fields);
        });

        rescue(fn () => Mail::send(new AccessApprovedMail($invitation->load(['project', 'inviter']))));

        return response()->json(['data' => new InvitationResource($invitation)]);
    }

    /** Reject: the letter says so, with the reason if given. */
    public function reject(Request $request, Project $board, AccessRequest $accessRequest): Response
    {
        $this->authorizeConfigure($request, $board);
        $this->guardPending($board, $accessRequest);

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ], [
            'reason.max' => 'Причина — не длиннее 500 символов.',
        ]);
        $accessRequest->update([
            'status' => AccessRequestStatus::Rejected,
            'reject_reason' => trim((string) ($data['reason'] ?? '')) ?: null,
            'decided_by' => $request->user()->id,
            'decided_at' => now(),
        ]);

        rescue(fn () => Mail::send(new AccessRejectedMail($accessRequest->setRelation('project', $board))));

        return response()->noContent();
    }

    /** @return list<string> */
    private function assignable(): array
    {
        return array_values(array_map(
            fn (ProjectRole $r) => $r->value,
            array_filter(ProjectRole::cases(), fn (ProjectRole $r) => $r !== ProjectRole::Owner),
        ));
    }

    private function authorizeConfigure(Request $request, Project $board): void
    {
        if (! $request->user()->roleOn($board)?->canConfigureBoard()) {
            abort(403, 'Нужны права настройки доски.');
        }
    }

    private function guardPending(Project $board, AccessRequest $accessRequest): void
    {
        if ($accessRequest->project_id !== $board->id) {
            abort(404, 'Этот запрос не из этой доски.');
        }
        if (! $accessRequest->isPending()) {
            abort(422, 'По этому запросу уже приняли решение.');
        }
    }
}