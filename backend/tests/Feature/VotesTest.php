<?php

namespace Tests\Feature;

use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class VotesTest extends TestCase
{
    use RefreshDatabase;

    public function test_like_dislike_switch_and_cancel(): void
    {
        $post = Post::factory()->create(['likes_count' => 3]);
        $url = "/api/public/posts/{$post->id}/vote";
        $me = ['X-Voter-Token' => (string) Str::uuid()];

        $this->postJson($url, ['value' => 'like'], $me)->assertOk()
            ->assertJson(['data' => ['likes_count' => 4, 'dislikes_count' => 0, 'my_vote' => 'like']]);
        $this->postJson($url, ['value' => 'like'], $me)->assertJsonPath('data.likes_count', 4); // idempotent

        // switching moves the vote, one reaction per browser
        $this->postJson($url, ['value' => 'dislike'], $me)
            ->assertJson(['data' => ['likes_count' => 3, 'dislikes_count' => 1, 'my_vote' => 'dislike']]);
        $this->assertSame(1, $post->votes()->count());

        $this->postJson($url, ['value' => 'dislike'], ['X-Voter-Token' => (string) Str::uuid()])->assertJsonPath('data.dislikes_count', 2);

        $this->deleteJson($url, [], $me)->assertOk()
            ->assertJson(['data' => ['likes_count' => 3, 'dislikes_count' => 1, 'my_vote' => null]]);
        $this->deleteJson($url, [], $me)->assertJsonPath('data.dislikes_count', 1);

        $this->postJson($url, ['value' => 'love'], $me)->assertJsonValidationErrors('value');
    }

    public function test_my_vote_is_reported_per_browser(): void
    {
        $post = Post::factory()->create();
        $token = (string) Str::uuid();
        $this->postJson("/api/public/posts/{$post->id}/vote", ['value' => 'dislike'], ['X-Voter-Token' => $token]);

        $url = "/api/public/projects/{$post->project->slug}/posts";
        $this->getJson($url, ['X-Voter-Token' => $token])->assertJsonPath('data.0.my_vote', 'dislike');
        $this->getJson($url, ['X-Voter-Token' => (string) Str::uuid()])->assertJsonPath('data.0.my_vote', null);
        $this->getJson($url)->assertJsonPath('data.0.my_vote', null);
        $this->getJson("{$url}/{$post->id}", ['X-Voter-Token' => $token])->assertJsonPath('data.my_vote', 'dislike');
    }

    public function test_requires_a_valid_token_and_a_visible_post(): void
    {
        $post = Post::factory()->create();
        $this->postJson("/api/public/posts/{$post->id}/vote", ['value' => 'like'])->assertUnprocessable()->assertJsonValidationErrors('voter_token');
        $this->postJson("/api/public/posts/{$post->id}/vote", ['value' => 'like'], ['X-Voter-Token' => 'not-a-uuid'])->assertUnprocessable();

        $hidden = Post::factory()->hidden()->create();
        $this->postJson("/api/public/posts/{$hidden->id}/vote", ['value' => 'like'], ['X-Voter-Token' => (string) Str::uuid()])->assertNotFound();
    }
}
