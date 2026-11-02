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
        // an open board has only a team: everyone else is a guest by the link
        if ($this->openBoard()) {
            $assignable = ProjectRole::openBoardAssignable();
        }

        return [
            'role' => ['required', 'string', Rule::in($assignable)],
        ];
    }

    public function messages(): array
    {
        return [
            'role.required' => 'Выберите роль.',
            'role.in' => $this->openBoard()
                ? 'На открытой доске только команда: Админ или Менеджер. Остальным приглашение не нужно — доска открыта по ссылке.'
                : 'Такой роли нет. Доступны: Админ, Менеджер, Участник, Читатель.',
        ];
    }

    private function openBoard(): bool
    {
        $board = $this->route('board');

        return $board instanceof \App\Models\Project && ! $board->isPrivate();
    }
}
