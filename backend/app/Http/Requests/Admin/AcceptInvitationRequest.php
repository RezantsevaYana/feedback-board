<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Accepting an invitation. For a brand-new account, name and password are required (the controller
 * checks it — only it knows whether the account is new; password may become optional once
 * magic-link login lands in slice 2). For an already-registered user
 * who happens to be logged in, both fields are ignored — the controller takes the current user.
 */
class AcceptInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'nullable', 'string', 'max:80'],
            'password' => ['sometimes', 'nullable', 'string', 'min:8', 'max:72'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.max' => 'Имя — не длиннее 80 символов.',
            'password.min' => 'Пароль — хотя бы 8 символов.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => is_string($this->name) ? trim($this->name) : $this->name,
            'password' => is_string($this->password) ? $this->password : null,
        ]);
        if ($this->name === '') {
            $this->merge(['name' => null]);
        }
        if ($this->password === '') {
            $this->merge(['password' => null]);
        }
    }
}
