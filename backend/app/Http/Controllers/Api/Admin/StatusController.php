<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Status;
use App\Support\EntityColor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Статусы доски: порядок всех статусов задаёт колонки канбана.
 * Начальный статус выбирается отдельно, удалять можно любой, кроме последнего.
 */
class StatusController extends Controller
{
    public function store(Request $request, Project $project): JsonResponse
    {
        Gate::authorize('board.configure', $project);

        $status = DB::transaction(function () use ($request, $project) {
            $project = Project::query()->lockForUpdate()->findOrFail($project->id);
            if ($project->statuses()->count() >= Status::MAX_PER_PROJECT) {
                throw ValidationException::withMessages(['name' => 'Не больше '.Status::MAX_PER_PROJECT.' статусов на доске.']);
            }

            $data = $this->validateFields($request, $project);

            $position = (int) $project->statuses()->max('position') + 100;

            return $project->statuses()->create([
                'slug' => $this->uniqueSlug($project, $data['name']),
                'name' => $data['name'],
                'color' => $data['color'] ?? null,
                'position' => $position,
            ]);

        });

        return response()->json(['data' => $this->present($status)], 201);
    }

    public function update(Request $request, Status $status): JsonResponse
    {
        Gate::authorize('board.configure', $status->project);

        $status->update($this->validateFields($request, $status->project, $status));

        return response()->json(['data' => $this->present($status->fresh())]);
    }

    /**
     * Полный список всех статусов в новом порядке колонок канбана.
     * Сортировка предложений в списке и статус для новых постов не меняются.
     */
    public function reorder(Request $request, Project $project): JsonResponse
    {
        Gate::authorize('board.configure', $project);

        $allIds = $project->statuses()->pluck('id')->all();

        $data = $request->validate([
            'ids' => ['present', 'array', 'size:'.count($allIds)],
            'ids.*' => ['integer', Rule::in($allIds)],
        ], [
            'ids.size' => 'Передайте полный список статусов в новом порядке.',
            'ids.*.in' => 'Можно переставлять только статусы этой доски.',
        ]);

        /** @var array<int, int> $ids */
        $ids = $data['ids'];
        if (count(array_unique($ids)) !== count($ids)) {
            throw ValidationException::withMessages(['ids' => 'Один и тот же статус повторяется.']);
        }

        DB::transaction(function () use ($ids, $project) {
            foreach ($ids as $index => $id) {
                $project->statuses()->where('id', $id)->update(['position' => 100 + $index * 100]);
            }
        });

        $fresh = $project->statuses()->get()->map(fn (Status $s) => $this->present($s))->all();

        return response()->json(['data' => $fresh]);
    }

    public function setDefault(Request $request, Project $project): JsonResponse
    {
        Gate::authorize('board.configure', $project);

        $id = DB::transaction(function () use ($request, $project) {
            $project = Project::query()->lockForUpdate()->findOrFail($project->id);
            $data = $request->validate([
                'status_id' => ['required', 'integer', Rule::exists('statuses', 'id')->where('project_id', $project->id)],
            ], ['status_id.exists' => 'Выберите статус этой доски.']);
            $project->update(['default_status_id' => $data['status_id']]);

            return $project->default_status_id;
        });

        return response()->json(['data' => ['default_status_id' => $id]]);
    }

    public function deletionInfo(Status $status): JsonResponse
    {
        Gate::authorize('board.configure', $status->project);

        return response()->json(['data' => [
            'posts_count' => $status->posts()->count(),
            'default_status_id' => $status->project->default_status_id,
            'statuses' => $status->project->statuses()->whereKeyNot($status->id)->get()
                ->map(fn (Status $s) => $this->present($s))->all(),
        ]]);
    }

    public function destroy(Request $request, Status $status): JsonResponse
    {
        Gate::authorize('board.configure', $status->project);

        $result = DB::transaction(function () use ($request, $status) {
            $project = Project::query()->lockForUpdate()->findOrFail($status->project_id);
            $status = $project->statuses()->findOrFail($status->id);
            if ($project->statuses()->count() <= 1) {
                throw ValidationException::withMessages(['status' => 'Нельзя удалить последний статус.']);
            }
            $data = $request->validate([
                'replacement_status_id' => ['required', 'integer', Rule::exists('statuses', 'id')
                    ->where('project_id', $project->id)->whereNot('id', $status->id)],
            ], [
                'replacement_status_id.required' => 'Выберите статус для переноса предложений.',
                'replacement_status_id.exists' => 'Выберите другой статус этой доски.',
            ]);
            $replacementId = (int) $data['replacement_status_id'];
            if ($project->default_status_id === $status->id) {
                $project->update(['default_status_id' => $replacementId]);
            }
            $moved = $status->posts()->update(['status_id' => $replacementId, 'status_changed_at' => now()]);
            $status->delete();

            return [
                'default_status_id' => $project->default_status_id,
                'replacement_status_id' => $replacementId,
                'moved_posts_count' => $moved,
            ];
        });

        return response()->json(['data' => $result]);
    }

    /**
     * @return array{name: string, color: string|null}
     */
    private function validateFields(Request $request, Project $project, ?Status $ignore = null): array
    {
        $request->merge([
            'name' => is_string($request->name) ? trim($request->name) : $request->name,
            ...($request->has('color') ? ['color' => EntityColor::normalize($request->input('color'))] : []),
        ]);

        return $request->validate([
            'name' => [
                'required', 'string', 'max:40',
                Rule::unique('statuses', 'name')->where('project_id', $project->id)->ignore($ignore),
            ],
            // null is a valid pick: the pill falls back to the neutral --s-new look.
            'color' => ['nullable', 'string', EntityColor::RULE],
        ], [
            'name.required' => 'Назовите статус.',
            'name.max' => 'Название — не длиннее 40 символов.',
            'name.unique' => 'Такой статус уже есть.',
            'color.regex' => 'Такой цвет недоступен.',
        ]);
    }

    /**
     * Уникальный slug в рамках проекта. Транслитерация у Str::slug съедает кириллицу
     * в пустоту — в этом случае используем фоллбек по id (создаст slug=custom-5).
     */
    private function uniqueSlug(Project $project, string $name): string
    {
        $base = Str::slug($name) ?: 'col';
        $slug = $base;
        $suffix = 1;
        while ($project->statuses()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.(++$suffix);
        }

        return $slug;
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Status $status): array
    {
        return [
            'id' => $status->id,
            'slug' => $status->slug,
            'name' => $status->name,
            'color' => $status->color,
            'position' => $status->position,
        ];
    }
}
