<?php

namespace Database\Factories;

use App\Enums\PostStatus;
use App\Models\Post;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Post>
 */
class PostFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'author_name' => fake()->optional()->firstName(),
            'status' => PostStatus::New,
        ];
    }

    public function status(PostStatus $status): static
    {
        return $this->state(['status' => $status]);
    }

    public function hidden(): static
    {
        return $this->state(['is_hidden' => true]);
    }
}
