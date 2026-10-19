<?php

namespace App\Http\Requests;

use App\Enums\PostPriority;
use App\Enums\PostStatus;
use App\Models\Post;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
            'status' => ['sometimes', 'required', Rule::enum(PostStatus::class)],
            'priority' => ['sometimes', 'nullable', Rule::enum(PostPriority::class)],
            'team_comment' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'is_hidden' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * A rejected post must say why: the reply is shown to everyone on the board.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            $status = $this->input('status', $this->editedPost()->status->value);
            $comment = $this->exists('team_comment') ? $this->input('team_comment') : $this->editedPost()->team_comment;
            if ($status === PostStatus::Rejected->value && blank($comment)) {
                $validator->errors()->add('team_comment', 'Напишите, почему отклонено, — это увидят все.');
            }
        }];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'category_id.exists' => 'Такой категории нет в проекте.',
            'title.min' => 'Заголовок — хотя бы 3 символа.',
            'title.max' => 'Заголовок — не длиннее 120 символов.',
            'description.min' => 'Описание — хотя бы 10 символов.',
            'description.max' => 'Описание — не длиннее 5000 символов.',
            'team_comment.max' => 'Ответ команды — не длиннее 2000 символов.',
            'status.enum' => 'Неизвестный статус.',
            'priority.enum' => 'Неизвестный приоритет.',
        ];
    }

    private function editedPost(): Post
    {
        return $this->route('post');
    }
}
