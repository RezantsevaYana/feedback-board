<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\ProjectRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AcceptInvitationRequest;
use App\Http\Requests\Admin\StoreInvitationsRequest;
use App\Http\Resources\InvitationResource;
use App\Mail\InvitationMail;
use App\Models\Project;
use App\Models\ProjectInvitation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Letters-and-tokens half of slice 2. Three admin-facing actions —
 * bulk invite, resend, cancel — and two public ones for the acceptance page.
 */
class InvitationController extends Controller
{
    /**
     * Bulk invite. The role is one for the batch. Already-member and already-pending-with-same-role
     * emails are reported in "skipped" instead of failing the whole batch.
     */
    public function store(StoreInvitationsRequest $request, Project $board): JsonResponse
    {
        $this->authorizeConfigure($request, $board);

        $role = ProjectRole::from($request->validated('role'));
        $invitees = $request->validated('invitees');
        $inviter = $request->user();

        $existingMembers = $board->members()->pluck('email')->map(fn (string $e) => Str::lower($e))->all();
        $pendingByEmail = $board->invitations()->pending()->get()->keyBy(fn (ProjectInvitation $i) => Str::lower($i->email));

        $created = [];
        $skipped = [];

        DB::transaction(function () use ($board, $role, $invitees, $inviter, $existingMembers, $pendingByEmail, &$created, &$skipped) {
            foreach ($invitees as $row) {
                $email = Str::lower($row['email']);

                if (in_array($email, $existingMembers, true)) {
                    $skipped[] = ['email' => $email, 'reason' => 'already_member'];

                    continue;
                }

                $existing = $pendingByEmail->get($email);
                if ($existing) {
                    // same email, same board — reset the slot instead of creating a duplicate.
                    $existing->update([
                        'role' => $role,
                        'invited_by' => $inviter->id,
                        'token' => ProjectInvitation::newToken(),
                        'expires_at' => ProjectInvitation::defaultExpiry(),
                    ]);
                    $created[] = $existing->fresh('inviter');

                    continue;
                }

                $created[] = ProjectInvitation::create([
                    'project_id' => $board->id,
                    'email' => $email,
                    'role' => $role,
                    'invited_by' => $inviter->id,
                    'token' => ProjectInvitation::newToken(),
                    'expires_at' => ProjectInvitation::defaultExpiry(),
                ]);
            }
        });

        foreach ($created as $invitation) {
            // Letter failure must NOT revert the DB write — the admin can hit "resend" and the row stays.
            rescue(fn () => Mail::send(new InvitationMail($invitation->loadMissing(['project', 'inviter']))));
        }

        return response()->json([
            'data' => InvitationResource::collection(collect($created)),
            'skipped' => $skipped,
        ], 201);
    }

    public function resend(Request $request, Project $board, ProjectInvitation $invitation): Response
    {
        $this->authorizeConfigure($request, $board);
        $this->guardOwns($board, $invitation);
        if ($invitation->accepted_at !== null) {
            abort(422, 'Это приглашение уже принято.');
        }

        $invitation->update([
            'token' => ProjectInvitation::newToken(),
            'expires_at' => ProjectInvitation::defaultExpiry(),
        ]);
        rescue(fn () => Mail::send(new InvitationMail($invitation->loadMissing(['project', 'inviter']))));

        return response()->noContent();
    }

    public function destroy(Request $request, Project $board, ProjectInvitation $invitation): Response
    {
        $this->authorizeConfigure($request, $board);
        $this->guardOwns($board, $invitation);

        $invitation->delete();

        return response()->noContent();
    }

    /**
     * Public: the acceptance page calls this to render invitation details (board name,
     * inviter, role) before the person decides whether to click "Accept". Returns 410
     * for stale tokens so the UI can show "ссылка истекла" instead of a generic 404.
     */
    public function show(string $token): JsonResponse
    {
        $invitation = ProjectInvitation::where('token', $token)->first();
        if (! $invitation) {
            abort(404, 'Такого приглашения нет.');
        }
        if ($invitation->accepted_at !== null) {
            abort(410, 'Приглашение уже использовано.');
        }
        if ($invitation->expires_at->isPast()) {
            abort(410, 'Срок приглашения истёк. Попросите, чтобы прислали новое.');
        }
        $invitation->loadMissing(['project', 'inviter']);

        return response()->json(['data' => [
            'email' => $invitation->email,
            'role' => $invitation->role->value,
            'board' => ['slug' => $invitation->project->slug, 'name' => $invitation->project->name],
            'invited_by' => ['name' => $invitation->inviter?->name],
            'expires_at' => $invitation->expires_at,
            'account_exists' => User::where('email', $invitation->email)->exists(),
        ]]);
    }

    /**
     * Public: accept. Three branches, in order:
     *   1. Current user is logged in and their email matches — just make the pivot row.
     *   2. An account already exists for this email — tell the client to redirect to /app/login.
     *      (Password login is slice 2 of ROLES_PLAN, not slice 1 of invitations.)
     *   3. No account yet — create one with the invitation email + the provided name
     *      and password. The email auto-counts as verified
     *      (plan §: opening the letter is proof of ownership).
     */
    public function accept(AcceptInvitationRequest $request, string $token): JsonResponse
    {
        $invitation = ProjectInvitation::where('token', $token)->first();
        if (! $invitation) {
            abort(404, 'Такого приглашения нет.');
        }
        if ($invitation->accepted_at !== null) {
            abort(410, 'Приглашение уже использовано.');
        }
        if ($invitation->expires_at->isPast()) {
            abort(410, 'Срок приглашения истёк. Попросите, чтобы прислали новое.');
        }

        $currentUser = $request->user('sanctum') ?? Auth::guard('web')->user();

        if ($currentUser && Str::lower($currentUser->email) === Str::lower($invitation->email)) {
            $user = $currentUser;
        } else {
            $existing = User::where('email', $invitation->email)->first();
            if ($existing) {
                // Логинить не можем — нужен пароль. Это слайс входа; возвращаем 409 с подсказкой.
                return response()->json([
                    'message' => 'На эту почту уже есть аккаунт. Войдите, чтобы принять приглашение.',
                    'action' => 'login_required',
                    'email' => $invitation->email,
                ], 409);
            }

            // Password is required until magic-link login exists (slice 2) — without it a new user
            // would have no way to sign in again.
            $name = $request->validated('name');
            $password = $request->validated('password');
            $errors = [];
            if (! is_string($name) || $name === '') {
                $errors['name'] = ['Введите имя.'];
            }
            if (! is_string($password) || $password === '') {
                $errors['password'] = ['Придумайте пароль.'];
            }
            if ($errors) {
                return response()->json([
                    'message' => isset($errors['name']) ? 'Впишите ваше имя.' : 'Придумайте пароль.',
                    'errors' => $errors,
                ], 422);
            }

            $user = User::create([
                'name' => $name,
                'email' => $invitation->email,
                'password' => $password,
            ]);
            $user->markEmailAsVerified();
        }

        $invitation->acceptFor($user);

        // Session is present when the request came through Sanctum's stateful group (browser,
        // Origin matches). Direct API hits without a session still succeed and let the client
        // decide what to do next (e.g. show a login button).
        if ($request->hasSession()) {
            Auth::guard('web')->login($user);
            $request->session()->regenerate();
        }

        return response()->json(['data' => [
            'board' => ['slug' => $invitation->project->slug, 'name' => $invitation->project->name],
            'role' => $invitation->role->value,
        ]]);
    }

    private function authorizeConfigure(Request $request, Project $board): void
    {
        $role = $request->user()->roleOn($board);
        if (! $role?->canConfigureBoard()) {
            abort(403, 'Нужны права настройки доски.');
        }
    }

    private function guardOwns(Project $board, ProjectInvitation $invitation): void
    {
        if ($invitation->project_id !== $board->id) {
            abort(404, 'Это приглашение не из этой доски.');
        }
    }
}
