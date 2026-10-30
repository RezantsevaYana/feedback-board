<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\Project;
use App\Models\User;
use App\Models\Voter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Top authors: an account is one author on any device; a guest is counted by the browser token,
 * which never leaves the server (the row carries an opaque key and a «me» flag).
 */
class LeaderboardTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        $this->project = Project::factory()->create(['slug' => 'demo']);
    }

    /** @param  array<string, mixed>  $attributes */
    private function makePost(array $attributes): Post
    {
        return Post::factory()->for($this->project)->create($attributes);
    }

    public function test_an_account_on_two_devices_is_one_author_under_the_profile_name(): void
    {
        $anna = User::factory()->create(['name' => 'Анна']);
        $this->makePost(['author_user_id' => $anna->id, 'author_token' => (string) Str::uuid(), 'author_name' => 'Анна']);
        $this->makePost(['author_user_id' => $anna->id, 'author_token' => (string) Str::uuid(), 'author_name' => 'Анна']);
        $anna->update(['name' => 'Анна Смирнова']);

        $this->getJson('/api/public/projects/demo/leaderboard')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Анна Смирнова')
            ->assertJsonPath('data.0.value', 2)
            ->assertJsonPath('data.0.key', 'u:'.$anna->id);
    }

    public function test_guests_are_counted_by_browser_and_the_token_stays_hidden(): void
    {
        $token = (string) Str::uuid();
        Voter::create(['token' => $token, 'display_name' => 'Лена']);
        $this->makePost(['author_token' => $token, 'author_name' => 'Лена']);
        $this->makePost(['author_token' => $token, 'author_name' => 'Лена']);
        $this->makePost(['author_token' => (string) Str::uuid(), 'author_name' => null]); // anonymous: not listed

        $response = $this->getJson('/api/public/projects/demo/leaderboard')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Лена')
            ->assertJsonPath('data.0.value', 2);

        $this->assertStringNotContainsString($token, $response->getContent());
        $this->assertStringStartsWith('t:', $response->json('data.0.key'));
    }

    public function test_accounts_and_guests_rank_together(): void
    {
        $anna = User::factory()->create(['name' => 'Анна']);
        $token = (string) Str::uuid();
        Voter::create(['token' => $token, 'display_name' => 'Лена']);
        $this->makePost(['author_user_id' => $anna->id, 'author_token' => $token]);
        $this->makePost(['author_token' => $token, 'author_name' => 'Лена']);
        $this->makePost(['author_token' => $token, 'author_name' => 'Лена']);

        // Anna's post from Lena's browser counts for Anna, not for the browser
        $this->getJson('/api/public/projects/demo/leaderboard')
            ->assertJsonPath('data.0.name', 'Лена')
            ->assertJsonPath('data.0.value', 2)
            ->assertJsonPath('data.1.name', 'Анна')
            ->assertJsonPath('data.1.value', 1);
    }

    public function test_me_is_marked_by_account_or_by_browser(): void
    {
        $anna = User::factory()->create(['name' => 'Анна']);
        $token = (string) Str::uuid();
        Voter::create(['token' => $token, 'display_name' => 'Лена']);
        $this->makePost(['author_user_id' => $anna->id]);
        $this->makePost(['author_token' => $token, 'author_name' => 'Лена']);

        $this->getJson('/api/public/projects/demo/leaderboard', ['X-Voter-Token' => $token])
            ->assertJsonFragment(['name' => 'Лена', 'me' => true])
            ->assertJsonFragment(['name' => 'Анна', 'me' => false]);

        $this->actingAs($anna)->getJson('/api/public/projects/demo/leaderboard')
            ->assertJsonFragment(['name' => 'Анна', 'me' => true])
            ->assertJsonFragment(['name' => 'Лена', 'me' => false]);
    }

    public function test_support_sums_votes_per_account(): void
    {
        $anna = User::factory()->create(['name' => 'Анна']);
        $this->makePost(['author_user_id' => $anna->id, 'likes_count' => 3, 'dislikes_count' => 1]);
        $this->makePost(['author_user_id' => $anna->id, 'likes_count' => 2]);

        $this->getJson('/api/public/projects/demo/leaderboard?metric=support')
            ->assertJsonPath('data.0.value', 4);
    }

    public function test_hidden_posts_dont_count(): void
    {
        $anna = User::factory()->create(['name' => 'Анна']);
        $this->makePost(['author_user_id' => $anna->id, 'is_hidden' => true]);

        $this->getJson('/api/public/projects/demo/leaderboard')->assertJsonCount(0, 'data');
    }
}