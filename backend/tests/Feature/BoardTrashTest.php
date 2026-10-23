<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BoardTrashTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Project $board;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create();
        $this->board = Project::factory()->create(['slug' => 'my-board', 'owner_id' => $this->owner->id]);
    }

    public function test_delete_needs_the_slug_typed_in(): void
    {
        $this->actingAs($this->owner);

        $this->deleteJson('/api/admin/boards/my-board')->assertJsonValidationErrors('confirm');
        $this->deleteJson('/api/admin/boards/my-board', ['confirm' => 'my-boar'])->assertJsonValidationErrors('confirm');
        $this->assertFalse($this->board->refresh()->trashed());

        $this->deleteJson('/api/admin/boards/my-board', ['confirm' => 'my-board'])
            ->assertOk()
            ->assertJsonPath('data.slug', 'my-board')
            ->assertJsonStructure(['data' => ['deleted_at', 'purge_at']]);

        $this->assertSoftDeleted($this->board);
    }

    public function test_trashed_board_leaves_the_hub_and_the_board_and_keeps_its_slug(): void
    {
        $this->actingAs($this->owner);
        $this->deleteJson('/api/admin/boards/my-board', ['confirm' => 'my-board'])->assertOk();

        $this->getJson('/api/admin/boards')
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('trashed.0.slug', 'my-board');
        $this->getJson('/api/admin/me')->assertJsonCount(0, 'data.boards')->assertJsonCount(0, 'data.projects');
        $this->getJson('/api/public/projects/my-board')->assertNotFound();
        $this->getJson('/api/admin/boards/my-board')->assertNotFound();

        $this->postJson('/api/admin/boards/check-slug', ['slug' => 'my-board'])->assertJsonPath('data.reason', 'taken');
    }

    public function test_restore_brings_the_board_back(): void
    {
        $this->actingAs($this->owner);
        $this->deleteJson('/api/admin/boards/my-board', ['confirm' => 'my-board'])->assertOk();

        $this->postJson('/api/admin/boards/my-board/restore')
            ->assertOk()
            ->assertJsonPath('data.slug', 'my-board')
            ->assertJsonPath('data.queue_count', 0);

        $this->assertNotSoftDeleted($this->board);
        $this->getJson('/api/admin/boards')->assertJsonCount(1, 'data')->assertJsonCount(0, 'trashed');
        $this->getJson('/api/public/projects/my-board')->assertOk();
    }

    public function test_other_admin_can_neither_delete_nor_restore(): void
    {
        $this->actingAs(User::factory()->create());

        $this->deleteJson('/api/admin/boards/my-board', ['confirm' => 'my-board'])->assertForbidden();
        $this->assertNotSoftDeleted($this->board);

        $this->board->delete();
        $this->postJson('/api/admin/boards/my-board/restore')->assertForbidden();
        $this->assertSoftDeleted($this->board);
    }

    public function test_guest_gets_401(): void
    {
        $this->deleteJson('/api/admin/boards/my-board', ['confirm' => 'my-board'])->assertUnauthorized();
        $this->postJson('/api/admin/boards/my-board/restore')->assertUnauthorized();
    }

    public function test_purge_removes_only_boards_past_retention(): void
    {
        Storage::fake('local');
        config(['feedback.trash_retention_days' => 30]);

        $post = Post::factory()->for($this->board)->create();
        Storage::disk('local')->put("attachments/{$post->id}/shot.png", 'x');
        $this->board->delete();
        $this->board->forceFill(['deleted_at' => now()->subDays(31)])->save();

        $recent = Project::factory()->create(['owner_id' => $this->owner->id]);
        $recent->delete();
        $live = Project::factory()->create(['owner_id' => $this->owner->id]);

        $this->artisan('boards:purge-trashed', ['--dry-run' => true])->assertSuccessful();
        $this->assertNotNull(Project::withTrashed()->find($this->board->id));

        $this->artisan('boards:purge-trashed')->assertSuccessful();

        $this->assertNull(Project::withTrashed()->find($this->board->id));
        $this->assertNull(Post::find($post->id));
        Storage::disk('local')->assertMissing("attachments/{$post->id}/shot.png");
        $this->assertSoftDeleted($recent);
        $this->assertNotSoftDeleted($live);
    }
}