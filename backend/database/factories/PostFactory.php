<?php

namespace Database\Factories;

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
            // status_id проставит Post::creating из project->defaultStatus()
        ];
    }

    /**
     * Явно положить пост в столбец по slug (например, 'done' или 'rejected').
     */
    public function status(string $slug): static
    {
        return $this->afterMaking(function (Post $post) use ($slug) {
            $post->status_id = $post->project->statuses()->where('slug', $slug)->value('id');
        });
    }

    public function hidden(): static
    {
        return $this->state(['is_hidden' => true]);
    }
}
