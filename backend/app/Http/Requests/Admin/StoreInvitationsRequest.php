<?php

namespace App\Http\Requests\Admin;

use App\Enums\ProjectRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Bulk invite: one role for the whole batch, each row optionally overriding the
 * "team member" auto-detection (by matching domain against projects.team_domain).
 *
 * Shape:
 *   { role: "manager",
 *     invitees: [
 *       { email: "a@acme.com" },                       // is_team_member computed from domain
 *       { email: "b@gmail.com", is_team_member: true } // explicit override
 *     ] }
 *
 * Owner isn't an assignable role — the board has one owner, set at creation and transferred
 * only via the backoffice (see docs/ROLES_PLAN.md §4).
 */
class StoreInvitationsRequest extends FormRequest
{
    public const MAX_INVITEES = 100;

    public function rules(): array
    {
        $assignable = array_values(array_filter(
            array_map(fn (ProjectRole $r) => $r->value, ProjectRole::cases()),
            fn (string $v) => $v !== ProjectRole::Owner->value,
        ));

        return [
            'role' => ['required', 'string', Rule::in($assignable)],
            'invitees' => ['required', 'array', 'min:1', 'max:'.self::MAX_INVITEES],
            'invitees.*.email' => ['required', 'email:rfc', 'max:190'],
            'invitees.*.is_team_member' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'role.required' => 'Выберите роль для приглашённых.',
            'role.in' => 'Такой роли нет. Доступны: Админ, Менеджер, Голосующий, Читатель.',
            'invitees.required' => 'Добавьте хотя бы одну почту.',
            'invitees.min' => 'Добавьте хотя бы одну почту.',
            'invitees.max' => 'За раз — не больше '.self::MAX_INVITEES.' приглашений.',
            'invitees.*.email.required' => 'Нужен email.',
            'invitees.*.email.email' => 'Это не похоже на email.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $invitees = (array) $this->input('invitees', []);
        $normalized = [];
        foreach ($invitees as $row) {
            if (! is_array($row) || ! isset($row['email'])) {
                continue;
            }
            $row['email'] = is_string($row['email']) ? Str::lower(trim($row['email'])) : $row['email'];
            $normalized[] = $row;
        }

        $this->merge([
            'invitees' => $this->dedupeByEmail($normalized),
            'role' => is_string($this->input('role')) ? Str::lower(trim($this->input('role'))) : $this->input('role'),
        ]);
    }

    /**
     * Same email twice in one batch is a mistake, not a conflict — keep the first occurrence.
     *
     * @param  array<int, array<string, mixed>>  $invitees
     * @return array<int, array<string, mixed>>
     */
    private function dedupeByEmail(array $invitees): array
    {
        $seen = [];
        $result = [];
        foreach ($invitees as $row) {
            $email = $row['email'] ?? null;
            if (! is_string($email) || $email === '' || isset($seen[$email])) {
                continue;
            }
            $seen[$email] = true;
            $result[] = $row;
        }

        return $result;
    }
}
