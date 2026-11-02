<?php

namespace Tests\Feature;

use App\Enums\BoardVisibility;
use App\Enums\ProjectRole;
use App\Models\Post;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The role table of docs/ROLES_PLAN.md §1 on the admin API:
 * triage (posts) — owner / admin / manager; configure (categories, columns, settings) — owner / admin;
 * deleting the board — owner only.
 */
class RolePermissionsTest extends TestCase
{
    use RefreshDatabase;

    private Project $board;

    /** @var array<string, User> */
    private array $users = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->users['owner'] = User::factory()->create();
        $this->board = Project::factory()->create(['owner_id' => $this->users['owner']->id]);

        foreach ([ProjectRole::Admin, ProjectRole::Manager, ProjectRole::Voter, ProjectRole::Reader] as $role) {
            $this->users[$role->value] = User::factory()->create();
            $this->board->members()->attach($this->users[$role->value], ['role' => $role->value]);
        }
        $this->users['outsider'] = User::factory()->create();
    }

    public function test_triage_is_for_owner_admin_manager(): void
    {
        $post = Post::factory()->for($this->board)->create();

        foreach ($this->users as $role => $user) {
            $allowed = in_array($role, ['owner', 'admin', 'manager'], true);

            $this->actingAs($user)
                ->getJson("/api/admin/projects/{$this->board->slug}/posts")
                ->assertStatus($allowed ? 200 : 403);
            $this->actingAs($user)
                ->patchJson("/api/admin/posts/{$post->id}", ['is_hidden' => true])
                ->assertStatus($allowed ? 200 : 403);
            $this->actingAs($user)
                ->postJson('/api/admin/posts/bulk/hide', ['ids' => [$post->id], 'hidden' => false])
                ->assertStatus($allowed ? 200 : 403);
        }
    }

    public function test_on_a_closed_board_voting_and_posting_are_for_everyone_but_the_reader(): void
    {
        $this->board->update(['visibility' => BoardVisibility::Private]);
        $post = Post::factory()->for($this->board)->create();
        $category = $this->board->categories()->first() ?? $this->board->categories()->create(['name' => 'Идея']);

        foreach ($this->users as $role => $user) {
            if ($role === 'outsider') {
                continue; // doesn't see a closed board at all (VisibilityTest)
            }
            $allowed = $role !== 'reader';
            // public endpoints read the user through the sanctum guard, which remembers the previous one
            $this->app['auth']->forgetGuards();

            $this->actingAs($user)
                ->postJson("/api/public/posts/{$post->id}/vote", ['value' => 'like'])
                ->assertStatus($allowed ? 200 : 403);
            $this->actingAs($user)
                ->postJson("/api/public/projects/{$this->board->slug}/posts", ['title' => "Идея от {$role}", 'description' => 'Описание идеи для проверки прав.', 'category_id' => $category->id])
                ->assertStatus($allowed ? 201 : 403);
        }
    }

    public function test_on_an_open_board_the_reader_is_a_guest(): void
    {
        $post = Post::factory()->for($this->board)->create();
        $category = $this->board->categories()->first() ?? $this->board->categories()->create(['name' => 'Идея']);
        $this->app['auth']->forgetGuards();

        // anyone by the link votes and posts there, so a reader signed in may too
        $this->actingAs($this->users['reader'])
            ->postJson("/api/public/posts/{$post->id}/vote", ['value' => 'like'])
            ->assertOk();
        $this->actingAs($this->users['reader'])
            ->postJson("/api/public/projects/{$this->board->slug}/posts", ['title' => 'Идея читателя', 'description' => 'Описание идеи для проверки прав.', 'category_id' => $category->id])
            ->assertCreated();
    }

    public function test_configure_is_for_owner_admin(): void
    {
        foreach ($this->users as $role => $user) {
            $allowed = in_array($role, ['owner', 'admin'], true);

            $this->actingAs($user)
                ->postJson("/api/admin/projects/{$this->board->slug}/categories", ['name' => "Кат {$role}", 'color' => 'green'])
                ->assertStatus($allowed ? 201 : 403);
            $this->actingAs($user)
                ->postJson("/api/admin/projects/{$this->board->slug}/statuses", ['name' => "Столбец {$role}"])
                ->assertStatus($allowed ? 201 : 403);
            $this->actingAs($user)
                ->getJson("/api/admin/boards/{$this->board->slug}")
                ->assertStatus($allowed ? 200 : 403);
            $this->actingAs($user)
                ->getJson("/api/admin/boards/{$this->board->slug}/embed")
                ->assertStatus($allowed ? 200 : 403);
        }
    }

    public function test_only_owner_deletes_the_board(): void
    {
        $this->actingAs($this->users['admin'])
            ->deleteJson("/api/admin/boards/{$this->board->slug}", ['confirm' => $this->board->slug])
            ->assertForbidden();

        $this->actingAs($this->users['owner'])
            ->deleteJson("/api/admin/boards/{$this->board->slug}", ['confirm' => $this->board->slug])
            ->assertOk();
    }
}