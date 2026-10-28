<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Categories the team configures per project. The list itself comes with GET /public/projects/{slug}.
 */
class CategoryController extends Controller
{
    public function store(Request $request, Project $project): JsonResponse
    {
        Gate::authorize('board.configure', $project);

        if ($project->categories()->count() >= Category::MAX_PER_PROJECT) {
            throw ValidationException::withMessages(['name' => 'Не больше '.Category::MAX_PER_PROJECT.' категорий в проекте.']);
        }

        $data = $this->validateFields($request, $project);
        $category = $project->categories()->create([
            'name' => $data['name'],
            'color' => $data['color'],
            'position' => (int) $project->categories()->max('position') + 1,
        ]);

        return response()->json(['data' => $this->present($category)], 201);
    }

    public function update(Request $request, Category $category): JsonResponse
    {
        Gate::authorize('board.configure', $category->project);

        $category->update($this->validateFields($request, $category->project, $category));

        return response()->json(['data' => $this->present($category)]);
    }

    /**
     * Posts of a deleted category stay on the board without a category.
     */
    public function destroy(Category $category): Response
    {
        Gate::authorize('board.configure', $category->project);

        $category->delete();

        return response()->noContent();
    }

    /**
     * @return array{name: string, color: string|null}
     */
    private function validateFields(Request $request, Project $project, ?Category $ignore = null): array
    {
        $request->merge(['name' => is_string($request->name) ? trim($request->name) : $request->name]);

        return $request->validate([
            'name' => [
                'required', 'string', 'max:40',
                Rule::unique('categories', 'name')->where('project_id', $project->id)->ignore($ignore),
            ],
            // null is a valid pick: the tag falls back to the neutral --tag token.
            'color' => ['nullable', 'string', Rule::in(Category::PALETTE)],
        ], [
            'name.required' => 'Назовите категорию.',
            'name.max' => 'Название — не длиннее 40 символов.',
            'name.unique' => 'Такая категория уже есть.',
            'color.in' => 'Такой цвет недоступен.',
        ]);
    }

    /**
     * @return array{id: int, name: string, color: string|null}
     */
    private function present(Category $category): array
    {
        return ['id' => $category->id, 'name' => $category->name, 'color' => $category->color];
    }
}
