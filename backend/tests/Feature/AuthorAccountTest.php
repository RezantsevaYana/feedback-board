<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Slice 2 of stage 2 (docs/ROLES_PLAN.md §3.2): posts.author_user_id, votes.user_id.
 *
 * On a signed-in request the author / voter is identified by their account, not by the
 * X-Voter-Token cookie — so the same person on two devices counts as one. Anonymous
 * widget submissions keep their cookie-only path untouched.
 */
class AuthorAccountTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->project = Project::factory()->create(['slug' => 'demo']);
        $this->project->createDefaultCategories();
        $this->user = User::factory()->create(['name' => 'Иван Петров']);
    }

    private function payload(array $overrides = []): array
    {
        return [
            'category_id' => $this->project->categories()->first()->id,
            'title' => 'Нужна отчётность по доскам',
            'description' => 'Понадобилось выгружать посты за неделю в CSV для менеджера.',
            ...$overrides,
        ];
    }

    public function test_signed_in_post_is_bound_to_account_and_uses_account_name(): void
    {
        // «Пришло» другое имя — его должны проигнорировать: имя всегда из аккаунта.
        $this->actingAs($this->user)
            ->postJson('/api/public/projects/demo/posts', $this->payload(['author_name' => 'Чужое имя']))
            ->assertCreated()
            ->assertJsonPath('data.author_name', 'Иван Петров');

        $post = Post::firstOrFail();
        $this->assertSame($this->user->id, $post->author_user_id);
        $this->assertSame('Иван Петров', $post->author_name);
    }

    public function test_anonymous_post_from_widget_keeps_cookie_authorship(): void
    {
        $token = (string) Str::uuid();
        $this->postJson('/api/public/projects/demo/posts', $this->payload(['author_name' => 'Лена']), ['X-Voter-Token' => $token])
            ->assertCreated()
            ->assertJsonPath('data.author_name', 'Лена');

        $post = Post::firstOrFail();
        $this->assertNull($post->author_user_id);
        $this->assertSame($token, $post->author_token);
        $this->assertSame('Лена', $post->author_name);
    }

    public function test_signed_in_vote_dedups_across_cookies(): void
    {
        $post = Post::factory()->for($this->project)->create();
        $url = "/api/public/posts/{$post->id}/vote";

        // Лаптоп: один cookie-токен, залогинен
        $this->actingAs($this->user)
            ->postJson($url, ['value' => 'like'], ['X-Voter-Token' => (string) Str::uuid()])
            ->assertOk()->assertJsonPath('data.likes_count', 1);

        // Телефон: другой cookie-токен, тот же аккаунт — не плодит второй голос
        $this->actingAs($this->user)
            ->postJson($url, ['value' => 'like'], ['X-Voter-Token' => (string) Str::uuid()])
            ->assertOk()->assertJsonPath('data.likes_count', 1);

        $this->assertSame(1, $post->votes()->count());
        $this->assertSame($this->user->id, $post->votes()->first()->user_id);
    }

    public function test_signed_in_can_cancel_their_vote_from_any_device(): void
    {
        $post = Post::factory()->for($this->project)->create();
        $url = "/api/public/posts/{$post->id}/vote";

        $this->actingAs($this->user)->postJson($url, ['value' => 'like'], ['X-Voter-Token' => (string) Str::uuid()])
            ->assertJsonPath('data.likes_count', 1);

        // Другой токен, тот же аккаунт — отмена всё равно находит голос по user_id
        $this->actingAs($this->user)->deleteJson($url, [], ['X-Voter-Token' => (string) Str::uuid()])
            ->assertOk()->assertJsonPath('data.likes_count', 0);

        $this->assertSame(0, $post->votes()->count());
    }

    public function test_anonymous_votes_stay_cookie_based(): void
    {
        $post = Post::factory()->for($this->project)->create();
        $url = "/api/public/posts/{$post->id}/vote";
        $token = (string) Str::uuid();

        $this->postJson($url, ['value' => 'like'], ['X-Voter-Token' => $token])->assertJsonPath('data.likes_count', 1);
        // Второй токен — отдельный «аноним», счётчик растёт
        $this->postJson($url, ['value' => 'like'], ['X-Voter-Token' => (string) Str::uuid()])->assertJsonPath('data.likes_count', 2);

        $this->assertSame(2, $post->votes()->count());
        $this->assertSame(0, $post->votes()->whereNotNull('user_id')->count());
    }

    public function test_signed_in_vote_without_cookie_still_works(): void
    {
        // SPA всегда шлёт X-Voter-Token, но архитектурно для залогиненного cookie необязателен.
        $post = Post::factory()->for($this->project)->create();
        $this->actingAs($this->user)
            ->postJson("/api/public/posts/{$post->id}/vote", ['value' => 'like'])
            ->assertOk()->assertJsonPath('data.likes_count', 1);

        $vote = $post->votes()->first();
        $this->assertSame($this->user->id, $vote->user_id);
        $this->assertNull($vote->voter_token);
    }

    public function test_anonymous_vote_without_cookie_is_rejected(): void
    {
        $post = Post::factory()->for($this->project)->create();
        $this->postJson("/api/public/posts/{$post->id}/vote", ['value' => 'like'])
            ->assertUnprocessable()->assertJsonValidationErrors('voter_token');
    }
}
