<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WeeklyWinnersTest extends TestCase
{
    use RefreshDatabase;

    public function test_zero_tied_and_negative_scores_do_not_produce_winners(): void
    {
        $project = Project::factory()->create();
        $category = $project->categories()->first();
        foreach ([[0, 0], [3, 3], [1, 2]] as [$likes, $dislikes]) {
            Post::factory()->for($project)->create([
                'category_id' => $category->id,
                'likes_count' => $likes,
                'dislikes_count' => $dislikes,
            ]);
        }

        $this->getJson("/api/public/projects/{$project->slug}/weekly-winners")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.post', null)
            ->assertJsonPath('data.1.post', null);
    }

    public function test_highest_positive_score_wins_each_category(): void
    {
        $project = Project::factory()->create();
        foreach ($project->categories as $category) {
            Post::factory()->for($project)->create([
                'category_id' => $category->id, 'likes_count' => 5, 'dislikes_count' => 4,
            ]);
        }
        $winner = Post::factory()->for($project)->create([
            'category_id' => $project->categories->first()->id,
            'likes_count' => 4, 'dislikes_count' => 1,
        ]);

        $this->getJson("/api/public/projects/{$project->slug}/weekly-winners")
            ->assertOk()
            ->assertJsonPath('data.0.post.id', $winner->id)
            ->assertJsonPath('data.1.post.likes_count', 5);
    }

    public function test_hidden_old_and_future_posts_are_not_winners(): void
    {
        $this->travelTo(now()->startOfWeek()->addDays(2));
        $project = Project::factory()->create();
        $category = $project->categories()->first();
        foreach ([
            ['is_hidden' => true],
            ['created_at' => now()->startOfWeek()->subSecond()],
            ['created_at' => now()->endOfWeek()->addSecond()],
        ] as $attributes) {
            Post::factory()->for($project)->create(array_merge([
                'category_id' => $category->id, 'likes_count' => 10,
            ], $attributes));
        }

        $this->getJson("/api/public/projects/{$project->slug}/weekly-winners")
            ->assertOk()
            ->assertJsonPath('data.0.post', null);
    }
}
