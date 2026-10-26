<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * A self-serve board opens to the public once its owner confirms the email (EnsureProjectPublished).
 */
class BoardPublishingTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Project $board;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->unverified()->create();
        $this->board = Project::factory()->create(['slug' => 'my-board', 'owner_id' => $this->owner->id]);
    }

    /** Requests from the cabinet's origin get a Sanctum session, like in the browser. */
    private function fromCabinet(): static
    {
        return $this->withHeaders(['Origin' => 'http://localhost:5173', 'Referer' => 'http://localhost:5173/my-board']);
    }

    public function test_visitors_get_403_on_every_public_endpoint_until_the_email_is_confirmed(): void
    {
        $post = Post::factory()->for($this->board)->create();
        $voter = ['X-Voter-Token' => (string) Str::uuid()];

        $this->getJson('/api/public/projects/my-board')->assertForbidden()->assertJsonPath('message', 'Доска ещё не опубликована.');
        $this->getJson('/api/public/projects/my-board/posts')->assertForbidden();
        $this->getJson("/api/public/projects/my-board/posts/{$post->id}")->assertForbidden();
        $this->getJson('/api/public/projects/my-board/leaderboard')->assertForbidden();
        $this->getJson('/api/public/projects/my-board/weekly-winners')->assertForbidden();
        $this->postJson('/api/public/projects/my-board/posts', ['title' => 'Идея'])->assertForbidden();
        $this->postJson("/api/public/posts/{$post->id}/vote", ['value' => 'like'], $voter)->assertForbidden();
        $this->assertSame(0, $post->fresh()->likes_count);
    }

    public function test_the_owner_sees_the_unpublished_board(): void
    {
        $this->actingAs($this->owner);

        $this->fromCabinet()->getJson('/api/public/projects/my-board')
            ->assertOk()
            ->assertJsonPath('data.published', false);
        $this->fromCabinet()->getJson('/api/public/projects/my-board/posts')->assertOk();
    }

    public function test_another_admin_is_a_visitor_here(): void
    {
        $this->actingAs(User::factory()->create());

        $this->fromCabinet()->getJson('/api/public/projects/my-board')->assertForbidden();
    }

    public function test_confirming_the_email_publishes_the_board(): void
    {
        $this->owner->markEmailAsVerified();

        $this->getJson('/api/public/projects/my-board')
            ->assertOk()
            ->assertJsonPath('data.published', true);
    }

    public function test_legacy_projects_without_an_owner_stay_public(): void
    {
        Project::factory()->create(['slug' => 'legacy']);

        $this->getJson('/api/public/projects/legacy')->assertOk()->assertJsonPath('data.published', true);
    }

    public function test_the_widget_reads_the_refusal_across_origins(): void
    {
        $this->getJson('/api/public/projects/my-board', ['Origin' => 'https://app.example.com'])
            ->assertForbidden()
            ->assertHeader('Access-Control-Allow-Origin', 'https://app.example.com');

        // the preflight stays open, so the browser lets the widget see the 403 on the real request
        $this->call('OPTIONS', '/api/public/projects/my-board/posts', server: ['HTTP_ORIGIN' => 'https://app.example.com'])
            ->assertNoContent();
    }
}