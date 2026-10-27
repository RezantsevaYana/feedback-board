<?php

namespace App\Http\Requests\Admin;

use App\Enums\ProjectRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Change a participant's role or team-badge on the board.
 * At least one of role / is_team_member must be present — the request accepts partial updates.
 * Owner isn't an assignable target (same reason as in StoreInvitationsRequest).
 */
class UpdateParticipantRequest extends FormRequest
{
    public function rules(): array
    {
        $assignable = array_values(array_filter(
            array_map(fn (ProjectRole $r) => $r->value, ProjectRole::cases()),
            fn (string $v) => $v !== ProjectRole::Owner->value,
        ));

        return [
            'role' => ['sometimes', 'string', Rule::in($assignable)],
            'is_team_member' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'role.in' => 'Такой роли нет. Доступны: Админ, Менеджер, Голосующий, Читатель.',
        ];
    }

    protected function passedValidation(): void
    {
        if (! $this->has('role') && ! $this->has('is_team_member')) {
            abort(response()->json(['message' => 'Нечего менять.'], 422));
        }
    }
}
