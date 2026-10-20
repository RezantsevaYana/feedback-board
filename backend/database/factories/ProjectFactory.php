<?php

namespace Database\Factories;

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
     * У каждого проекта всегда есть системные столбцы — без них Post::creating
     * не может подобрать дефолт.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Project $project) {
            $project->createDefaultStatuses();
        });
    }
}
