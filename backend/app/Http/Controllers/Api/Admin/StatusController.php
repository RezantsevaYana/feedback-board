<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Status;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Кастомные столбцы канбана: команда может добавлять/переименовывать/удалять
 * свои между системными «Новое» и «Готово/Отклонено». Системные статусы
 * (slug в Status::SYSTEM_SLUGS) защищены от удаления.
 */
class StatusController extends Controller
{
    public function store(Request $request, Project $project): JsonResponse
    {
        Gate::authorize('board.configure', $project);

        if ($project->statuses()->count() >= Status::MAX_PER_PROJECT) {
            throw ValidationException::withMessages(['name' => 'Не больше '.Status::MAX_PER_PROJECT.' столбцов в проекте.']);
        }

        $data = $this->validateFields($request, $project);

        // Новые кастомные статусы встают между «Новое» (pos ≈ 100) и «Готово» (pos ≈ 9000):
        // позиция = max(позиций кастомных) + 100, но строго меньше позиции «Готово».
        $maxCustom = (int) $project->statuses()->where('is_system', false)->max('position');
        $position = $maxCustom === 0 ? 200 : $maxCustom + 100;

        $status = $project->statuses()->create([
            'slug' => $this->uniqueSlug($project, $data['name']),
            'name' => $data['name'],
            'color' => $data['color'] ?? null,
            'position' => $position,
            'is_system' => false,
        ]);

        return response()->json(['data' => $this->present($status)], 201);
    }

    public function update(Request $request, Status $status): JsonResponse
    {
        Gate::authorize('board.configure', $status->project);

        $status->update($this->validateFields($request, $status->project, $status));

        return response()->json(['data' => $this->present($status->fresh())]);
    }

    /**
     * Переупорядочивание кастомных столбцов между системными «Новое» и «Готово».
     * Принимает полный список id кастомных статусов в новом порядке; системные
     * (slug в Status::SYSTEM_SLUGS) остаются на своих местах и в payload не участвуют.
     * Раздаём position с шагом 100, начиная с 200, — «воздух» между 100 и 9000 хватает
     * на MAX_PER_PROJECT - 3 кастомных.
     */
    public function reorder(Request $request, Project $project): JsonResponse
    {
        Gate::authorize('board.configure', $project);

        $customIds = $project->statuses()->where('is_system', false)->pluck('id')->all();

        $data = $request->validate([
            'ids' => ['present', 'array', 'size:'.count($customIds)],
            'ids.*' => ['integer', Rule::in($customIds)],
        ], [
            'ids.size' => 'Передайте полный список кастомных столбцов в новом порядке.',
            'ids.*.in' => 'Нельзя переставить системный или чужой столбец.',
        ]);

        /** @var array<int, int> $ids */
        $ids = $data['ids'];
        if (count(array_unique($ids)) !== count($ids)) {
            throw ValidationException::withMessages(['ids' => 'Один и тот же столбец повторяется.']);
        }

        DB::transaction(function () use ($ids, $project) {
            foreach ($ids as $index => $id) {
                $project->statuses()->where('id', $id)->update(['position' => 200 + $index * 100]);
            }
        });

        $fresh = $project->statuses()->get()->map(fn (Status $s) => $this->present($s))->all();

        return response()->json(['data' => $fresh]);
    }

    /**
     * Системные нельзя удалить; посты удалённого кастомного столбца переносятся
     * в дефолтный (slug='new').
     */
    public function destroy(Status $status): Response
    {
        Gate::authorize('board.configure', $status->project);

        if ($status->is_system) {
            throw ValidationException::withMessages(['status' => 'Этот столбец нельзя удалить.']);
        }

        $fallback = $status->project->defaultStatus();
        abort_unless($fallback, 500, 'default status missing');

        DB::transaction(function () use ($status, $fallback) {
            $status->posts()->update(['status_id' => $fallback->id]);
            $status->delete();
        });

        return response()->noContent();
    }

    /**
     * @return array{name: string, color: string|null}
     */
    private function validateFields(Request $request, Project $project, ?Status $ignore = null): array
    {
        $request->merge(['name' => is_string($request->name) ? trim($request->name) : $request->name]);

        return $request->validate([
            'name' => [
                'required', 'string', 'max:40',
                Rule::unique('statuses', 'name')->where('project_id', $project->id)->ignore($ignore),
            ],
            // null is a valid pick: the pill falls back to the neutral --s-new look.
            'color' => ['nullable', 'string', Rule::in(Status::PALETTE)],
        ], [
            'name.required' => 'Назовите столбец.',
            'name.max' => 'Название — не длиннее 40 символов.',
            'name.unique' => 'Такой столбец уже есть.',
            'color.in' => 'Такой цвет недоступен.',
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
            'is_system' => $status->is_system,
        ];
    }
}
