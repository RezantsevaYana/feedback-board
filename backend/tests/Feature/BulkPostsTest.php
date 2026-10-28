<?php

namespace Tests\Feature;

use App\Enums\ProjectRole;
use App\Models\Category;
use App\Models\Post;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Массовые операции над карточками: перенос в столбец, назначение категории,
 * скрыть/вернуть, удалить. Админ работает в рамках одного проекта за раз.
 */
class BulkPostsTest extends TestCase
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

    private function makePosts(int $count): array
    {
        return Post::factory()->count($count)->for($this->project)
            ->create(['status_id' => $this->statusId('new')])
            ->pluck('id')->all();
    }

    public function test_bulk_move_updates_status_and_shared_reply(): void
    {
        $ids = $this->makePosts(3);
        $doneId = $this->statusId('done');

        $this->actingAs($this->admin)
            ->postJson('/api/admin/posts/bulk/move', ['ids' => $ids, 'status_id' => $doneId, 'team_comment' => 'Выкатили в v2.3'])
            ->assertOk()
            ->assertJsonCount(3, 'data');

        foreach ($ids as $id) {
            $this->assertDatabaseHas('posts', ['id' => $id, 'status_id' => $doneId, 'team_comment' => 'Выкатили в v2.3']);
        }
    }

    public function test_bulk_move_stamps_status_changed_at(): void
    {
        $ids = $this->makePosts(2);
        Post::whereIn('id', $ids)->update(['status_changed_at' => now()->subMonth()]);

        $this->actingAs($this->admin)
            ->postJson('/api/admin/posts/bulk/move', ['ids' => $ids, 'status_id' => $this->statusId('done')])
            ->assertOk();

        foreach ($ids as $id) {
            $this->assertTrue(Post::find($id)->status_changed_at->isToday(), "status_changed_at not restamped for $id");
        }
    }

    public function test_bulk_move_rejects_status_from_another_project(): void
    {
        $other = Project::factory()->create();
        $foreignStatus = $other->statuses()->where('slug', 'done')->value('id');

        $this->actingAs($this->admin)
            ->postJson('/api/admin/posts/bulk/move', ['ids' => $this->makePosts(2), 'status_id' => $foreignStatus])
            ->assertJsonValidationErrors(['status_id' => 'Такого столбца нет в этом проекте.']);
    }

    public function test_bulk_categorize_sets_and_clears_category(): void
    {
        $ids = $this->makePosts(3);
        $category = Category::factory()->for($this->project)->create(['name' => 'Доставка', 'color' => 'green']);

        $this->actingAs($this->admin)
            ->postJson('/api/admin/posts/bulk/categorize', ['ids' => $ids, 'category_id' => $category->id])
            ->assertOk();
        foreach ($ids as $id) {
            $this->assertDatabaseHas('posts', ['id' => $id, 'category_id' => $category->id]);
        }

        $this->actingAs($this->admin)
            ->postJson('/api/admin/posts/bulk/categorize', ['ids' => $ids, 'category_id' => null])
            ->assertOk();
        foreach ($ids as $id) {
            $this->assertDatabaseHas('posts', ['id' => $id, 'category_id' => null]);
        }
    }

    public function test_bulk_hide_flips_both_ways(): void
    {
        $ids = $this->makePosts(3);

        $this->actingAs($this->admin)
            ->postJson('/api/admin/posts/bulk/hide', ['ids' => $ids, 'hidden' => true])
            ->assertOk();
        $this->assertSame(3, Post::whereIn('id', $ids)->where('is_hidden', true)->count());

        $this->actingAs($this->admin)
            ->postJson('/api/admin/posts/bulk/hide', ['ids' => $ids, 'hidden' => false])
            ->assertOk();
        $this->assertSame(0, Post::whereIn('id', $ids)->where('is_hidden', true)->count());
    }

    public function test_bulk_delete_removes_posts(): void
    {
        $ids = $this->makePosts(4);

        $this->actingAs($this->admin)
            ->deleteJson('/api/admin/posts/bulk', ['ids' => $ids])
            ->assertNoContent();

        $this->assertSame(0, Post::whereIn('id', $ids)->count());
    }

    public function test_guest_cannot_bulk_act(): void
    {
        $this->postJson('/api/admin/posts/bulk/hide', ['ids' => $this->makePosts(1), 'hidden' => true])
            ->assertUnauthorized();
    }

    public function test_admin_from_another_project_gets_403(): void
    {
        $outsider = User::factory()->create();
        $outsider->projects()->attach(Project::factory()->create(), [
            'role' => ProjectRole::Admin->value,
        ]);

        $this->actingAs($outsider)
            ->postJson('/api/admin/posts/bulk/hide', ['ids' => $this->makePosts(2), 'hidden' => true])
            ->assertForbidden();
    }

    public function test_posts_from_mixed_projects_are_rejected(): void
    {
        $mine = $this->makePosts(2);
        $theirs = Post::factory()->for(Project::factory()->create())->create()->id;

        $this->actingAs($this->admin)
            ->postJson('/api/admin/posts/bulk/hide', ['ids' => [...$mine, $theirs], 'hidden' => true])
            ->assertJsonValidationErrors(['ids' => 'Карточки принадлежат разным проектам.']);
    }

    public function test_empty_or_oversized_selection_is_rejected(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/api/admin/posts/bulk/hide', ['ids' => [], 'hidden' => true])
            ->assertJsonValidationErrors(['ids' => 'Выберите карточки.']);

        $this->actingAs($this->admin)
            ->postJson('/api/admin/posts/bulk/hide', ['ids' => range(1, 101), 'hidden' => true])
            ->assertJsonValidationErrors(['ids' => 'За раз — не больше 100 карточек.']);
    }
}
