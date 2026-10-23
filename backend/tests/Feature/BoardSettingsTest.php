<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BoardSettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Project $board;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create();
        $this->board = Project::factory()->create([
            'slug' => 'my-board',
            'owner_id' => $this->owner->id,
            'allowed_origins' => [],
        ]);
        $this->board->createDefaultCategories();
    }

    public function test_owner_reads_and_updates_settings(): void
    {
        $this->actingAs($this->owner);

        $this->getJson('/api/admin/boards/my-board')
            ->assertOk()
            ->assertJsonPath('data.slug', 'my-board')
            ->assertJsonPath('data.allowed_origins', [])
            ->assertJsonPath('data.trash_retention_days', 30);

        $this->patchJson('/api/admin/boards/my-board', ['name' => '  Новое имя '])
            ->assertOk()
            ->assertJsonPath('data.name', 'Новое имя')
            ->assertJsonPath('data.allowed_origins', []);

        $this->patchJson('/api/admin/boards/my-board', ['origins' => ['https://app.example.com/', ' http://localhost:* ', '']])
            ->assertOk()
            ->assertJsonPath('data.name', 'Новое имя')
            ->assertJsonPath('data.allowed_origins', ['https://app.example.com', 'http://localhost:*']);

        $this->assertTrue($this->board->refresh()->allowsOrigin('http://localhost:5174'));

        // clearing the list leaves the board on direct link only
        $this->patchJson('/api/admin/boards/my-board', ['origins' => []])->assertOk()->assertJsonPath('data.allowed_origins', []);
    }

    public function test_slug_cannot_be_changed(): void
    {
        $this->actingAs($this->owner)
            ->patchJson('/api/admin/boards/my-board', ['slug' => 'other', 'name' => 'Доска'])
            ->assertOk()
            ->assertJsonPath('data.slug', 'my-board');
    }

    public function test_validation(): void
    {
        $this->actingAs($this->owner);

        $this->patchJson('/api/admin/boards/my-board', ['name' => ' '])->assertJsonValidationErrors('name');
        $this->patchJson('/api/admin/boards/my-board', ['origins' => ['app.example.com']])->assertJsonValidationErrors('origins.0');
        $this->patchJson('/api/admin/boards/my-board', ['origins' => ['https://a.com/path']])->assertJsonValidationErrors('origins.0');
        $this->patchJson('/api/admin/boards/my-board', ['origins' => ['https://a.com', 'https://a.com/']])->assertJsonValidationErrors('origins.1');
    }

    public function test_other_admin_gets_403(): void
    {
        $this->actingAs(User::factory()->create());

        $this->getJson('/api/admin/boards/my-board')->assertForbidden();
        $this->patchJson('/api/admin/boards/my-board', ['name' => 'Взлом'])->assertForbidden();
        $this->assertNotSame('Взлом', $this->board->refresh()->name);
    }

    public function test_guest_gets_401(): void
    {
        $this->getJson('/api/admin/boards/my-board')->assertUnauthorized();
        $this->patchJson('/api/admin/boards/my-board', ['name' => 'Взлом'])->assertUnauthorized();
    }

    public function test_owner_manages_board_content_without_pivot(): void
    {
        $this->actingAs($this->owner);

        $this->getJson('/api/admin/me')->assertJsonPath('data.projects.*.slug', ['my-board']);

        $this->postJson('/api/admin/projects/my-board/categories', ['name' => 'Доставка', 'color' => 'green'])->assertCreated();
        $this->postJson('/api/admin/projects/my-board/statuses', ['name' => 'В работе', 'color' => 'blue'])->assertCreated();

        $post = Post::factory()->for($this->board)->create();
        $this->patchJson("/api/admin/posts/{$post->id}", ['priority' => 'high'])->assertOk();
    }
}
