<?php

namespace App\Http\Controllers\Api\Public;

use App\Enums\AccessRequestStatus;
use App\Enums\BoardVisibility;
use App\Enums\ProjectRole;
use App\Http\Controllers\Controller;
use App\Mail\AccessRequestedMail;
use App\Models\AccessRequest;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * «Запросить доступ» on the gate screen of a private board (docs/ROLES_PLAN.md, stage 3).
 *
 * The answer is always the same 202 with the address the reply will go to, whatever happened inside:
 * a member, a repeat, an address rejected less than a day ago or a public board all look like a sent request,
 * so the form can't be used to learn who is on the board or what was decided.
 */
class AccessRequestController extends Controller
{
    public function store(Request $request, Project $project): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user('sanctum');

        // a logged-in visitor asks as their account: name and email come from the profile
        if ($user) {
            $request->merge(['name' => $user->name, 'email' => $user->email]);
        }
        $request->merge([
            'name' => is_string($request->name) ? trim($request->name) : $request->name,
            'email' => is_string($request->email) ? Str::lower(trim($request->email)) : $request->email,
            'message' => is_string($request->message) ? trim($request->message) : $request->message,
        ]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email', 'max:160'],
            'message' => ['nullable', 'string', 'max:1000'],
        ], [
            'name.required' => 'Представьтесь — команде доски нужно понять, кто просит доступ.',
            'name.max' => 'Имя — не длиннее 80 символов.',
            'email.required' => 'Введите почту: на неё придёт ответ.',
            'email.email' => 'Введите email в формате name@example.com.',
            'email.max' => 'Почта — не длиннее 160 символов.',
            'message.max' => 'Пояснение — не длиннее 1000 символов.',
        ]);

        if ($this->accepts($project, $data['email'])) {
            $this->record($project, $data, $user);
        }

        return response()->json(['data' => ['email' => $data['email']]], 202);
    }

    /**
     * Only a published private board takes requests, and not from its members. After a rejection the address
     * waits a day (AccessRequest::REJECT_COOLDOWN_HOURS), so a refused person can't flood the admins' mail.
     */
    private function accepts(Project $project, string $email): bool
    {
        return $project->isPublished()
            && $project->visibility === BoardVisibility::Private
            && ! $project->members()->whereRaw('lower(email) = ?', [$email])->exists()
            && ! $project->accessRequests()
                ->where('email', $email)
                ->where('status', AccessRequestStatus::Rejected->value)
                ->where('decided_at', '>', now()->subHours(AccessRequest::REJECT_COOLDOWN_HOURS))
                ->exists();
    }

    /**
     * A repeat while the first one waits updates it (one card per person for the admins) and stays quiet;
     * a new request goes to every owner and admin of the board by email.
     *
     * @param  array{name: string, email: string, message: ?string}  $data
     */
    private function record(Project $project, array $data, ?User $user): void
    {
        $pending = $project->accessRequests()->pending()->where('email', $data['email'])->first();
        if ($pending) {
            $pending->update(['name' => $data['name'], 'message' => $data['message'] ?? $pending->message, 'user_id' => $user?->id ?? $pending->user_id]);

            return;
        }

        $accessRequest = $project->accessRequests()->create([
            'email' => $data['email'],
            'name' => $data['name'],
            'message' => $data['message'] ?: null,
            'user_id' => $user?->id,
            'status' => AccessRequestStatus::Pending,
        ]);

        $deciders = $project->members()
            ->wherePivotIn('role', [ProjectRole::Owner->value, ProjectRole::Admin->value])
            ->get();
        foreach ($deciders as $admin) {
            rescue(fn () => Mail::send(new AccessRequestedMail($accessRequest->setRelation('project', $project), $admin)));
        }
    }
}