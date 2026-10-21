<?php

namespace App\Http\Requests;

use App\Enums\PostPriority;
use App\Models\Post;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Team edit of a post; every field is optional so drag-and-drop can send only the status (and a reply).
 */
class UpdatePostRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'category_id' => ['sometimes', 'nullable', 'integer', Rule::exists('categories', 'id')->where('project_id', $this->editedPost()->project_id)],
            'title' => ['sometimes', 'required', 'string', 'min:3', 'max:120'],
            'description' => ['sometimes', 'required', 'string', 'min:10', 'max:5000'],
            'status_id' => ['sometimes', 'required', 'integer', Rule::exists('statuses', 'id')->where('project_id', $this->editedPost()->project_id)],
            'priority' => ['sometimes', 'nullable', Rule::enum(PostPriority::class)],
            'team_comment' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'is_hidden' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'category_id.exists' => 'Такой категории нет в проекте.',
            'status_id.exists' => 'Такого столбца нет в этом проекте.',
            'title.min' => 'Заголовок — хотя бы 3 символа.',
            'title.max' => 'Заголовок — не длиннее 120 символов.',
            'description.min' => 'Описание — хотя бы 10 символов.',
            'description.max' => 'Описание — не длиннее 5000 символов.',
            'team_comment.max' => 'Ответ команды — не длиннее 2000 символов.',
            'priority.enum' => 'Неизвестный приоритет.',
        ];
    }

    private function editedPost(): Post
    {
        return $this->route('post');
    }
}
