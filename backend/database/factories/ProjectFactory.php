<?php

namespace Database\Factories;

use App\Enums\ProjectRole;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 99999),
            'allowed_origins' => ['https://app.example.com'],
        ];
    }

    /**
     * У каждого проекта всегда есть системные столбцы и категории — та же инвариант,
     * что держит ProjectService::create. Без столбцов Post::creating не может подобрать
     * дефолт; без категорий KanbanBoard не может сгруппировать посты по столбцам.
     * Если задан owner_id, он автоматически попадает в project_user с role=owner.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Project $project) {
            $project->createDefaultStatuses();
            $project->createDefaultCategories();
            if ($project->owner_id !== null) {
                $project->members()->syncWithoutDetaching([
                    $project->owner_id => [
                        'role' => ProjectRole::Owner->value,
                    ],
                ]);
            }
        });
    }
}
