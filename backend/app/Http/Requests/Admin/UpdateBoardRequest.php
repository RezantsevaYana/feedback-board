<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Board settings: rename and the widget domains. Each field is optional so the
 * "General" and "Domains" sections can save independently. Slug is read-only
 * after creation — changing it would break connected widgets and shared links.
 */
class UpdateBoardRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:80'],
            'origins' => ['sometimes', 'array', 'max:20'],
            // same format as on create: scheme + host (+ optional :port), "*" allowed, no path
            'origins.*' => ['string', 'distinct', 'regex:#^https?://[^/\s]+$#'],
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
            'origins.max' => 'Не больше 20 доменов.',
            'origins.*.distinct' => 'Этот домен уже есть в списке.',
            'origins.*.regex' => 'Домен — схема и хост без пути, например https://app.example.com',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('name')) {
            $this->merge(['name' => is_string($this->name) ? trim($this->name) : $this->name]);
        }
        if ($this->has('origins')) {
            $this->merge(['origins' => array_values(array_filter(array_map(
                fn ($o) => is_string($o) ? rtrim(trim($o), '/') : $o,
                (array) $this->origins,
            ), fn ($o) => is_string($o) && $o !== ''))]);
        }
    }
}
