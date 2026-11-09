<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Self-serve board creation (POST /api/admin/boards). The free-tier board-count
 * limit is enforced in the controller before validation runs so we can return a
 * clean 403 instead of a field error.
 */
class CreateBoardRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
            'slug' => [
                'required',
                'string',
                'alpha_dash:ascii',
                'min:2',
                'max:64',
                Rule::notIn(config('feedback.reserved_slugs')),
                'unique:projects,slug',
                // test.md п. 24: освобождённый адрес занят ссылками и виджетами, переоформить его нельзя
                Rule::unique('project_slug_history', 'old_slug'),
            ],
            'origins' => ['nullable', 'array', 'max:20'],
            // Scheme + host (+ optional :port). No path. Wildcard * is allowed inside the host part — "http://localhost:*" is used in local dev.
            'origins.*' => ['string', 'regex:#^https?://[^/\s]+$#'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Назовите доску — чтобы на неё было легко сослаться.',
            'name.max' => 'Название — не длиннее 80 символов.',
            'slug.required' => 'Введите адрес доски.',
            'slug.alpha_dash' => 'Адрес — только латиница, цифры и дефис.',
            'slug.min' => 'Адрес — хотя бы 2 символа.',
            'slug.max' => 'Адрес — не длиннее 64 символов.',
            'slug.not_in' => 'Этот адрес зарезервирован. Выберите другой.',
            'slug.unique' => 'Этот адрес уже занят. Попробуйте другой.',
            'origins.*.regex' => 'Домен — схема и хост без пути, например https://app.example.com',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => is_string($this->name) ? trim($this->name) : $this->name,
            'slug' => is_string($this->slug) ? Str::lower(trim($this->slug)) : $this->slug,
            'origins' => array_values(array_filter(array_map(
                fn ($o) => is_string($o) ? rtrim(trim($o), '/') : $o,
                (array) $this->origins,
            ), fn ($o) => is_string($o) && $o !== '')),
        ]);
    }
}
