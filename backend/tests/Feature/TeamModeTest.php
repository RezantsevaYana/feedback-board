<?php

namespace Tests\Feature;

use App\Enums\PostPriority;
use App\Enums\ProjectRole;
use App\Models\Post;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TeamModeTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->project = Project::factory()->create(['slug' => 'demo']);
        $this->admin = User::factory()->create(['email' => 'anna@example.com', 'password' => 'secret-pass']);
        $this->admin->projects()->attach($this->project, [
            'role' => ProjectRole::Admin->value,
        ]);
    }

    private function statusId(string $slug): int
    {
        return $this->project->statuses()->where('slug', $slug)->value('id');
    }

    /** Requests from the board's origin get a Sanctum session, like in the browser. */
    private function fromBoard(): static
    {
        return $this->withHeaders(['Origin' => 'http://localhost:5173', 'Referer' => 'http://localhost:5173/demo']);
    }

    public function test_guest_gets_401_so_the_board_stays_public(): void
    {
        $this->getJson('/api/admin/me')->assertUnauthorized();
        $this->getJson('/api/admin/projects/demo/posts')->assertUnauthorized();
    }

    public function test_login_with_wrong_password_gives_one_generic_message(): void
    {
        $this->fromBoard()->postJson('/api/admin/login', ['email' => 'anna@example.com', 'password' => 'nope'])
            ->assertJsonValidationErrors(['email' => 'Неверный email или пароль.']);
        $this->fromBoard()->postJson('/api/admin/login', ['email' => 'nobody@example.com', 'password' => 'nope'])
            ->assertJsonValidationErrors(['email' => 'Неверный email или пароль.']);
    }

    public function test_login_starts_team_mode_and_logout_ends_it(): void
    {
        $this->fromBoard()->postJson('/api/admin/login', ['email' => 'anna@example.com', 'password' => 'secret-pass'])
            ->assertOk()
            ->assertJsonPath('data.email', 'anna@example.com')
            ->assertJsonPath('data.projects.0.slug', 'demo');

        $this->fromBoard()->getJson('/api/admin/me')->assertOk();
        $this->fromBoard()->postJson('/api/admin/logout')->assertNoContent();
        $this->assertGuest('web');
    }

    public function test_login_is_rate_limited(): void
    {
        foreach (range(1, 5) as $_) {
            $this->fromBoard()->postJson('/api/admin/login', ['email' => 'anna@example.com', 'password' => 'nope']);
        }
        $this->fromBoard()->postJson('/api/admin/login', ['email' => 'anna@example.com', 'password' => 'secret-pass'])
            ->assertTooManyRequests();
    }

    public function test_team_sees_hidden_and_rejected_with_priority(): void
    {
        $rejectedStatusId = $this->statusId('rejected');
        Post::factory()->for($this->project)->hidden()->create();
        Post::factory()->for($this->project)->status('rejected')->create(['priority' => PostPriority::Low, 'team_comment' => 'Нет']);
        Post::factory()->for($this->project)->create();

        $response = $this->actingAs($this->admin)->getJson('/api/admin/projects/demo/posts')->assertOk()->assertJsonCount(3, 'data');
        $this->assertArrayHasKey('priority', $response->json('data.0'));
        $this->assertArrayHasKey('is_hidden', $response->json('data.0'));

        $this->getJson('/api/admin/projects/demo/posts?hidden=exclude')->assertJsonCount(2, 'data');
        $this->getJson('/api/admin/projects/demo/posts?priority=low')->assertJsonCount(1, 'data');
        $this->getJson('/api/admin/projects/demo/posts?priority=none')->assertJsonCount(2, 'data');
        $this->getJson("/api/admin/projects/demo/posts?status_id={$rejectedStatusId}")->assertJsonCount(1, 'data');
    }

    public function test_team_filters_by_several_priorities_at_once(): void
    {
        $critical = Post::factory()->for($this->project)->create(['priority' => PostPriority::Critical]);
        $high = Post::factory()->for($this->project)->create(['priority' => PostPriority::High]);
        Post::factory()->for($this->project)->create(['priority' => PostPriority::Low]);
        $unset = Post::factory()->for($this->project)->create();

        $this->actingAs($this->admin);
        $ids = fn (string $priority) => collect($this->getJson("/api/admin/projects/demo/posts?priority={$priority}")->assertOk()->json('data.*.id'))->sort()->values()->all();

        $this->assertSame([$critical->id, $high->id], $ids('high,critical'));
        $this->assertSame([$critical->id, $unset->id], $ids('critical,none'));
        $this->getJson('/api/admin/projects/demo/posts?priority=high,urgent')->assertUnprocessable();
    }

    public function test_team_updates_status_priority_and_reply(): void
    {
        $post = Post::factory()->for($this->project)->create();
        $doneId = $this->statusId('done');
        $this->travel(1)->hours();

        $this->actingAs($this->admin)->patchJson("/api/admin/posts/{$post->id}", [
            'status_id' => $doneId,
            'priority' => 'high',
        ])->assertOk()
            ->assertJsonPath('data.status.slug', 'done')
            ->assertJsonPath('data.priority', 'high');

        $post->refresh();
        $this->assertTrue($post->status_changed_at->gt($post->created_at));
        $changedAt = $post->status_changed_at;

        // editing other fields keeps the status timestamp
        $this->travel(1)->hours();
        $this->patchJson("/api/admin/posts/{$post->id}", ['title' => 'Новый заголовок'])->assertOk();
        $this->assertTrue($post->refresh()->status_changed_at->eq($changedAt));

        $this->patchJson("/api/admin/posts/{$post->id}", ['status_id' => 999999, 'title' => 'x'])
            ->assertJsonValidationErrors(['status_id', 'title']);
    }

    public function test_rejecting_reason_is_optional(): void
    {
        $post = Post::factory()->for($this->project)->create();
        $rejectedId = $this->statusId('rejected');
        $this->actingAs($this->admin);

        $this->patchJson("/api/admin/posts/{$post->id}", ['status_id' => $rejectedId])
            ->assertOk()->assertJsonPath('data.status.slug', 'rejected');
        $this->patchJson("/api/admin/posts/{$post->id}", ['team_comment' => 'Уже есть в настройках'])
            ->assertOk()->assertJsonPath('data.team_comment', 'Уже есть в настройках');
        $this->patchJson("/api/admin/posts/{$post->id}", ['team_comment' => null])
            ->assertOk()->assertJsonPath('data.team_comment', null);
    }

    public function test_team_moves_post_to_another_category_of_the_same_project_only(): void
    {
        $idea = $this->project->categories()->where('name', 'Идея')->firstOrFail();
        $foreign = Project::factory()->create();
        $post = Post::factory()->for($this->project)->create();

        $this->actingAs($this->admin)->patchJson("/api/admin/posts/{$post->id}", ['category_id' => $idea->id])
            ->assertOk()->assertJsonPath('data.category.name', 'Идея');
        $this->patchJson("/api/admin/posts/{$post->id}", ['category_id' => $foreign->categories()->first()->id])
            ->assertJsonValidationErrors('category_id');
    }

    public function test_admin_of_another_project_is_forbidden(): void
    {
        $foreignProject = Project::factory()->create();
        $foreignPost = Post::factory()->for($foreignProject)->create();
        $foreignDoneId = $foreignProject->statuses()->where('slug', 'done')->value('id');

        $this->actingAs($this->admin)->getJson("/api/admin/projects/{$foreignProject->slug}/posts")->assertForbidden();
        $this->getJson("/api/admin/posts/{$foreignPost->id}")->assertForbidden();
        $this->patchJson("/api/admin/posts/{$foreignPost->id}", ['status_id' => $foreignDoneId])->assertForbidden();
        $this->deleteJson("/api/admin/posts/{$foreignPost->id}")->assertForbidden();
        $this->assertSame('new', $foreignPost->refresh()->status->slug);
    }

    public function test_delete_removes_post_and_files_and_team_can_open_files_of_hidden_posts(): void
    {
        Storage::fake('local');
        $this->post('/api/public/projects/demo/posts', [
            'title' => 'Скриншот', 'description' => 'Смотрите вложение',
            'category_id' => $this->project->categories()->first()->id,
            'files' => [UploadedFile::fake()->image('a.png')],
        ], ['Accept' => 'application/json'])->assertCreated();
        $post = Post::firstOrFail();
        $post->update(['is_hidden' => true]);
        $attachment = $post->attachments()->firstOrFail();

        $this->actingAs($this->admin)->get("/api/admin/attachments/{$attachment->id}")->assertOk();

        $this->deleteJson("/api/admin/posts/{$post->id}")->assertNoContent();
        $this->assertModelMissing($post);
        Storage::disk('local')->assertMissing($attachment->path);
    }
}
