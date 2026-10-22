<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

/**
 * Self-serve admin registration.
 */
class RegisterRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email', 'max:160', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'max:72'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Представьтесь — хотя бы имя.',
            'name.max' => 'Имя — не длиннее 80 символов.',
            'email.required' => 'Введите email.',
            'email.email' => 'Введите email в формате name@example.com.',
            'email.unique' => 'Этот email уже занят. Войдите или восстановите пароль.',
            'password.required' => 'Придумайте пароль.',
            'password.min' => 'Пароль — хотя бы 8 символов.',
            'password.max' => 'Пароль — не длиннее 72 символов.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => is_string($this->name) ? trim($this->name) : $this->name,
            'email' => is_string($this->email) ? Str::lower(trim($this->email)) : $this->email,
        ]);
    }
}
