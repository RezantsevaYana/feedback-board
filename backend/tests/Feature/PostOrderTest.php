<?php

namespace Tests\Feature;

use App\Enums\PostPriority;
use App\Enums\ProjectRole;
use App\Models\Post;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_cards_appear_above_the_saved_order_and_can_then_be_moved_down(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $owner->id]);
        [$first, $second] = Post::factory()->count(2)->for($project)->create()->all();
        $this->actingAs($owner)->patchJson("/api/admin/posts/{$first->id}/order", [
            'status_id' => $first->status_id, 'before_id' => $second->id,
        ])->assertNoContent();
        [$newer, $newest] = Post::factory()->count(2)->for($project)->create()->all();

        $this->getJson("/api/admin/projects/{$project->slug}/posts?sort=manual")
            ->assertOk()->assertJsonPath('data.0.id', $newest->id)
            ->assertJsonPath('data.1.id', $newer->id)
            ->assertJsonPath('data.2.id', $first->id)
            ->assertJsonPath('data.3.id', $second->id);

        $this->patchJson("/api/admin/posts/{$newest->id}/order", [
            'status_id' => $newest->status_id, 'before_id' => null,
        ])->assertNoContent();
        $this->getJson("/api/admin/projects/{$project->slug}/posts?sort=manual")
            ->assertJsonPath('data.0.id', $newer->id)
            ->assertJsonPath('data.1.id', $first->id)
            ->assertJsonPath('data.2.id', $second->id)
            ->assertJsonPath('data.3.id', $newest->id);
    }

    public function test_order_survives_reload_without_changing_status_or_priority(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $owner->id]);
        [$first, $second, $third] = Post::factory()->count(3)->for($project)->create()->all();
        $first->update(['priority' => PostPriority::High]);
        $changedAt = $first->status_changed_at;

        $this->actingAs($owner)->patchJson("/api/admin/posts/{$first->id}/order", [
            'status_id' => $first->status_id, 'before_id' => $third->id,
        ])->assertNoContent();

        $this->getJson("/api/admin/projects/{$project->slug}/posts?sort=manual")
            ->assertOk()->assertJsonPath('data.0.id', $first->id)
            ->assertJsonPath('data.1.id', $third->id)->assertJsonPath('data.2.id', $second->id);
        $this->assertSame(PostPriority::High, $first->fresh()->priority);
        $this->assertSame($project->default_status_id, $first->fresh()->status_id);
        $this->assertSame($changedAt->toIso8601String(), $first->fresh()->status_changed_at->toIso8601String());

        $this->patchJson("/api/admin/posts/{$first->id}/order", [
            'status_id' => $first->status_id, 'before_id' => null,
        ])->assertNoContent();
        $this->getJson("/api/admin/projects/{$project->slug}/posts?sort=manual")
            ->assertJsonPath('data.2.id', $first->id);
    }

    public function test_other_boards_columns_self_and_stale_status_are_rejected(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $owner->id]);
        $post = Post::factory()->for($project)->create();
        $otherBoard = Post::factory()->create();
        $otherColumn = Post::factory()->for($project)->status('done')->create();
        $this->actingAs($owner);

        foreach ([$otherBoard->id, $otherColumn->id, $post->id] as $beforeId) {
            $this->patchJson("/api/admin/posts/{$post->id}/order", [
                'status_id' => $post->status_id, 'before_id' => $beforeId,
            ])->assertUnprocessable();
        }
        $this->patchJson("/api/admin/posts/{$post->id}/order", [
            'status_id' => $otherColumn->status_id, 'before_id' => null,
        ])->assertUnprocessable();
        $this->assertNull($post->fresh()->kanban_position);
    }

    public function test_reader_and_outsider_cannot_change_order(): void
    {
        $reader = User::factory()->create();
        $project = Project::factory()->create();
        $project->members()->attach($reader, ['role' => ProjectRole::Reader->value]);
        $post = Post::factory()->for($project)->create();

        foreach ([$reader, User::factory()->create()] as $user) {
            $this->actingAs($user)->patchJson("/api/admin/posts/{$post->id}/order", [
                'status_id' => $post->status_id, 'before_id' => null,
            ])->assertForbidden();
        }
        $this->assertNull($post->fresh()->kanban_position);
    }

    public function test_reordering_preserves_hidden_and_filtered_out_cards(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $owner->id]);
        [$first, $hidden, $third] = Post::factory()->count(3)->for($project)->create()->all();
        $hidden->update(['is_hidden' => true]);

        $this->actingAs($owner)->patchJson("/api/admin/posts/{$first->id}/order", [
            'status_id' => $first->status_id, 'before_id' => $third->id,
        ])->assertNoContent();

        $this->getJson("/api/admin/projects/{$project->slug}/posts?sort=manual&hidden=include")
            ->assertJsonCount(3, 'data')->assertJsonPath('data.2.id', $hidden->id);
        $this->assertTrue($hidden->fresh()->is_hidden);
    }
}
