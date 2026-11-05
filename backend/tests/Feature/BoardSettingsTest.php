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
    }

    public function test_created_board_comes_back_with_its_summary(): void
    {
        // a fresh model had no visibility until re-read: the board was saved, the response was a 500
        $this->actingAs($this->owner)
            ->postJson('/api/admin/boards', ['name' => 'Новая доска', 'slug' => 'new-board'])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'new-board')
            ->assertJsonPath('data.visibility', 'public');
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

    public function test_widget_color_takes_pasted_hex_and_reaches_the_widget(): void
    {
        $this->actingAs($this->owner);

        $this->getJson('/api/admin/boards/my-board/embed')->assertJsonPath('data.widget_color', null);

        // what people paste: no «#», upper case, short form, spaces
        foreach (['  7C3AED ' => '#7c3aed', '#1D8547' => '#1d8547', '#a3f' => '#aa33ff'] as $pasted => $stored) {
            $this->patchJson('/api/admin/boards/my-board', ['widget_color' => $pasted])
                ->assertOk()
                ->assertJsonPath('data.widget_color', $stored);
        }

        // the widget reads it from the public project, the connection page from embed
        $this->getJson('/api/public/projects/my-board')->assertJsonPath('data.widget_color', '#aa33ff');
        $this->getJson('/api/admin/boards/my-board/embed')->assertJsonPath('data.widget_color', '#aa33ff');

        // other fields leave the colour alone
        $this->patchJson('/api/admin/boards/my-board', ['name' => 'Доска'])->assertJsonPath('data.widget_color', '#aa33ff');

        // empty string or null: back to the service's green
        $this->patchJson('/api/admin/boards/my-board', ['widget_color' => ''])->assertOk()->assertJsonPath('data.widget_color', null);
        $this->patchJson('/api/admin/boards/my-board', ['widget_color' => '#123456'])->assertOk();
        $this->patchJson('/api/admin/boards/my-board', ['widget_color' => null])->assertOk()->assertJsonPath('data.widget_color', null);
    }

    public function test_widget_launcher_text_saves_trims_and_reaches_the_widget(): void
    {
        $this->actingAs($this->owner);

        // default is null until the owner picks their own label
        $this->getJson('/api/admin/boards/my-board/embed')->assertJsonPath('data.widget_launcher_text', null);

        $this->patchJson('/api/admin/boards/my-board', ['widget_launcher_text' => '  Есть идея?  '])
            ->assertOk()
            ->assertJsonPath('data.widget_launcher_text', 'Есть идея?');

        // the widget reads it from the public project, the connection page from embed
        $this->getJson('/api/public/projects/my-board')->assertJsonPath('data.widget_launcher_text', 'Есть идея?');
        $this->getJson('/api/admin/boards/my-board/embed')->assertJsonPath('data.widget_launcher_text', 'Есть идея?');

        // empty / whitespace / null — back to the default («Обратная связь» is rendered by the widget itself)
        $this->patchJson('/api/admin/boards/my-board', ['widget_launcher_text' => '   '])->assertOk()->assertJsonPath('data.widget_launcher_text', null);
        $this->patchJson('/api/admin/boards/my-board', ['widget_launcher_text' => 'Напишите нам'])->assertOk();
        $this->patchJson('/api/admin/boards/my-board', ['widget_launcher_text' => null])->assertOk()->assertJsonPath('data.widget_launcher_text', null);

        // 40 chars is the hard ceiling
        $this->patchJson('/api/admin/boards/my-board', ['widget_launcher_text' => str_repeat('а', 41)])
            ->assertJsonValidationErrors(['widget_launcher_text' => 'Текст кнопки — не длиннее 40 символов.']);
    }

    public function test_widget_color_rejects_what_is_not_hex(): void
    {
        $this->actingAs($this->owner);

        foreach (['red', '#12345', '#1234567', 'rgb(0,0,0)', '#ggg'] as $bad) {
            $this->patchJson('/api/admin/boards/my-board', ['widget_color' => $bad])
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['widget_color' => 'Цвет — HEX из 6 или 3 цифр, например #1d8547']);
        }
        $this->assertNull($this->board->refresh()->widget_color);
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
