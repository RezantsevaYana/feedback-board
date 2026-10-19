<?php

namespace App\Http\Requests;

use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Submission from the widget or the board form (multipart/form-data).
 */
class StorePostRequest extends FormRequest
{
    public const MAX_FILES = 5;

    public const MAX_FILE_KB = 5120;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Project $project */
        $project = $this->route('project');

        return [
            // a category is required as soon as the project has any
            'category_id' => [
                $project->categories()->exists() ? 'required' : 'nullable',
                'integer',
                Rule::exists('categories', 'id')->where('project_id', $project->id),
            ],
            'title' => ['required', 'string', 'min:3', 'max:120'],
            'description' => ['required', 'string', 'min:10', 'max:5000'],
            'author_name' => ['nullable', 'string', 'max:80'],
            'files' => ['nullable', 'array', 'max:'.self::MAX_FILES],
            // mimetypes checks the real file content, not the extension
            'files.*' => ['file', 'mimetypes:image/png,image/jpeg,image/webp,image/gif', 'max:'.self::MAX_FILE_KB],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'category_id.required' => 'Выберите, к чему относится предложение.',
            'category_id.integer' => 'Выберите, к чему относится предложение.',
            'category_id.exists' => 'Такой категории больше нет — обновите страницу.',
            'title.required' => 'Напишите заголовок.',
            'title.min' => 'Заголовок — хотя бы 3 символа.',
            'title.max' => 'Заголовок — не длиннее 120 символов.',
            'description.required' => 'Опишите подробнее.',
            'description.min' => 'Опишите подробнее, хотя бы 10 символов.',
            'description.max' => 'Описание — не длиннее 5000 символов.',
            'author_name.max' => 'Имя — не длиннее 80 символов.',
            'files.max' => 'Можно приложить не больше '.self::MAX_FILES.' скриншотов.',
            'files.*.mimetypes' => 'Скриншот должен быть картинкой PNG, JPG, WebP или GIF.',
            'files.*.max' => 'Каждый скриншот — не больше 5 МБ.',
            'files.*.file' => 'Не удалось загрузить файл.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'title' => is_string($this->title) ? trim($this->title) : $this->title,
            'description' => is_string($this->description) ? trim($this->description) : $this->description,
            'author_name' => is_string($this->author_name) ? (trim($this->author_name) ?: null) : $this->author_name,
        ]);
    }
}
