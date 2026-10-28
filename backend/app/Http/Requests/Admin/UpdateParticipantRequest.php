<?php

namespace App\Http\Requests\Admin;

use App\Enums\ProjectRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Change a participant's role on the board.
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
            'role' => ['required', 'string', Rule::in($assignable)],
        ];
    }

    public function messages(): array
    {
        return [
            'role.required' => 'Выберите роль.',
            'role.in' => 'Такой роли нет. Доступны: Админ, Менеджер, Голосующий, Читатель.',
        ];
    }
}
