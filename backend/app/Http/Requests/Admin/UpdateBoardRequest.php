<?php

namespace App\Http\Requests\Admin;

use App\Enums\BoardVisibility;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Board settings: rename, the widget domains and the widget colour. Each field is optional so the
 * "General", "Domains" and "Подключение" sections can save independently. Slug is read-only
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
            // #rrggbb after prepareForValidation; null brings back the service's own green
            'widget_color' => ['sometimes', 'nullable', 'string', 'regex:/^#[0-9a-f]{6}$/'],
            // both ways: closing and opening back (02.11.2026, docs/ROLES_PLAN.md §5); the UI warns before either
            'visibility' => ['sometimes', 'string', Rule::in(array_map(fn (BoardVisibility $v) => $v->value, BoardVisibility::cases()))],
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
            'widget_color.regex' => 'Цвет — HEX из 6 или 3 цифр, например #1d8547',
            'visibility.in' => 'Неизвестный режим доступа.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('name')) {
            $this->merge(['name' => is_string($this->name) ? trim($this->name) : $this->name]);
        }
        if ($this->has('widget_color')) {
            $this->merge(['widget_color' => $this->normalizeColor($this->widget_color)]);
        }
        if ($this->has('origins')) {
            $this->merge(['origins' => array_values(array_filter(array_map(
                fn ($o) => is_string($o) ? rtrim(trim($o), '/') : $o,
                (array) $this->origins,
            ), fn ($o) => is_string($o) && $o !== ''))]);
        }
    }

    /**
     * Accepts what people paste: «1D8547», «#1d8547», short «#1d8», spaces around. An empty string means
     * «back to default». Anything else still comes out as «#…» and the regex rejects it.
     */
    private function normalizeColor(mixed $color): mixed
    {
        if (! is_string($color)) {
            return $color;
        }
        $hex = strtolower(ltrim(trim($color), '#'));
        if ($hex === '') {
            return null;
        }
        if (preg_match('/^[0-9a-f]{3}$/', $hex)) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        return '#'.$hex;
    }
}
