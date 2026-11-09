<?php

namespace App\Http\Requests\Admin;

use App\Enums\BoardVisibility;
use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Board settings: rename, slug, the widget domains and the widget colour. Each field is optional so the
 * "General", "Domains" and "Подключение" sections can save independently. Slug is editable
 * (test.md п. 24) — старый адрес записывается в project_slug_history, виджеты и ссылки по нему
 * продолжают работать (резолвер подменит, фронт сделает router.replace).
 */
class UpdateBoardRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $board = $this->route('board');
        $boardId = $board instanceof Project ? $board->id : null;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:80'],
            'slug' => [
                'sometimes',
                'required',
                'string',
                'alpha_dash:ascii',
                'min:2',
                'max:64',
                Rule::notIn(config('feedback.reserved_slugs')),
                // занят среди активных и в корзине (unique считает физические строки, soft-delete не учитывает)
                Rule::unique('projects', 'slug')->ignore($boardId),
                // занят в истории — нельзя увести отданный когда-то кому-то slug, иначе старые ссылки сломаются
                Rule::unique('project_slug_history', 'old_slug'),
            ],
            'origins' => ['sometimes', 'array', 'max:20'],
            // same format as on create: scheme + host (+ optional :port), "*" allowed, no path
            'origins.*' => ['string', 'distinct', 'regex:#^https?://[^/\s]+$#'],
            // #rrggbb after prepareForValidation; null brings back the service's own green
            'widget_color' => ['sometimes', 'nullable', 'string', 'regex:/^#[0-9a-f]{6}$/'],
            // custom label on the widget launcher button; null brings back the default «Обратная связь»
            'widget_launcher_text' => ['sometimes', 'nullable', 'string', 'max:40'],
            // let search engines index the board page (test.md п. 19). Off → <meta robots noindex> + robots.txt Disallow
            'search_indexable' => ['sometimes', 'boolean'],
            // guest permissions on an open board (test.md п. 40). Off → 403 on the public endpoint; if guests can't
            // post, the widget on the site is hidden (there's no way to log in from there)
            'guests_can_post' => ['sometimes', 'boolean'],
            'guests_can_vote' => ['sometimes', 'boolean'],
            // both ways: closing and opening back (02.11.2026, docs/ROLES_PLAN.md §5); the UI warns before either
            'visibility' => ['sometimes', 'string', Rule::in(array_map(fn (BoardVisibility $v) => $v->value, BoardVisibility::cases()))],
            // the new-board onboarding: the step to continue from (2–6, step 1 creates the board); null — done or hidden
            'onboarding_step' => ['sometimes', 'nullable', 'integer', 'between:2,6'],
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
            'origins.max' => 'Не больше 20 доменов.',
            'origins.*.distinct' => 'Этот домен уже есть в списке.',
            'origins.*.regex' => 'Домен — схема и хост без пути, например https://app.example.com',
            'widget_color.regex' => 'Цвет — HEX из 6 или 3 цифр, например #1d8547',
            'widget_launcher_text.max' => 'Текст кнопки — не длиннее 40 символов.',
            'visibility.in' => 'Неизвестный режим доступа.',
            'onboarding_step.between' => 'Неизвестный шаг настройки.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('name')) {
            $this->merge(['name' => is_string($this->name) ? trim($this->name) : $this->name]);
        }
        if ($this->has('slug')) {
            $this->merge(['slug' => is_string($this->slug) ? Str::lower(trim($this->slug)) : $this->slug]);
        }
        if ($this->has('widget_color')) {
            $this->merge(['widget_color' => $this->normalizeColor($this->widget_color)]);
        }
        if ($this->has('widget_launcher_text')) {
            // пустая строка (или только пробелы) — вернуть стандартный текст; max:40 отработает уже по тримленному
            $text = is_string($this->widget_launcher_text) ? trim($this->widget_launcher_text) : $this->widget_launcher_text;
            $this->merge(['widget_launcher_text' => $text === '' ? null : $text]);
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
