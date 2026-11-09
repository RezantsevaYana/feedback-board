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

    public function test_a_board_created_from_the_cabinet_carries_its_onboarding_step_until_done(): void
    {
        $this->actingAs($this->owner)
            ->postJson('/api/admin/boards', ['name' => 'Новая доска', 'slug' => 'new-board'])
            ->assertCreated()
            ->assertJsonPath('data.onboarding_step', 2);

        $this->patchJson('/api/admin/boards/new-board', ['onboarding_step' => 4])
            ->assertOk()
            ->assertJsonPath('data.onboarding_step', 4);
        $hub = collect($this->getJson('/api/admin/boards')->json('data'))->keyBy('slug');
        $this->assertSame(4, $hub['new-board']['onboarding_step']);

        // finished, or «Скрыть» on the hub card: nothing to continue
        $this->patchJson('/api/admin/boards/new-board', ['onboarding_step' => null])
            ->assertOk()
            ->assertJsonPath('data.onboarding_step', null);

        // step 1 is the create form itself, there is no step 7
        $this->patchJson('/api/admin/boards/new-board', ['onboarding_step' => 1])->assertUnprocessable();
        $this->patchJson('/api/admin/boards/new-board', ['onboarding_step' => 7])->assertUnprocessable();
    }

    public function test_boards_made_outside_the_cabinet_have_no_onboarding_to_continue(): void
    {
        $this->actingAs($this->owner)
            ->getJson('/api/admin/boards/my-board')
            ->assertOk()
            ->assertJsonPath('data.onboarding_step', null);
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

    public function test_search_indexable_defaults_to_true_and_can_be_toggled(): void
    {
        $this->actingAs($this->owner);

        // fresh board — visible to search engines by default
        $this->getJson('/api/admin/boards/my-board')
            ->assertOk()
            ->assertJsonPath('data.search_indexable', true);
        $this->getJson('/api/public/projects/my-board')->assertJsonPath('data.search_indexable', true);

        // turn it off — stored, and reaches the public endpoint that BoardPage uses to set <meta robots noindex>
        $this->patchJson('/api/admin/boards/my-board', ['search_indexable' => false])
            ->assertOk()
            ->assertJsonPath('data.search_indexable', false);
        $this->getJson('/api/public/projects/my-board')->assertJsonPath('data.search_indexable', false);

        // back on
        $this->patchJson('/api/admin/boards/my-board', ['search_indexable' => true])
            ->assertOk()
            ->assertJsonPath('data.search_indexable', true);

        // не bool → 422
        $this->patchJson('/api/admin/boards/my-board', ['search_indexable' => 'maybe'])
            ->assertJsonValidationErrors(['search_indexable']);
    }

    public function test_guest_permissions_default_to_everyone_and_toggle(): void
    {
        $this->actingAs($this->owner);

        // fresh board — guests may both post and vote
        $this->getJson('/api/admin/boards/my-board')
            ->assertOk()
            ->assertJsonPath('data.guests_can_post', true)
            ->assertJsonPath('data.guests_can_vote', true);
        $this->getJson('/api/public/projects/my-board')
            ->assertJsonPath('data.guests_can_post', true)
            ->assertJsonPath('data.guests_can_vote', true);

        // flip posts off — reaches both admin and public (widget reads the public one)
        $this->patchJson('/api/admin/boards/my-board', ['guests_can_post' => false])
            ->assertOk()
            ->assertJsonPath('data.guests_can_post', false)
            ->assertJsonPath('data.guests_can_vote', true);
        $this->getJson('/api/public/projects/my-board')->assertJsonPath('data.guests_can_post', false);

        // flip votes off, posts back on — independent switches
        $this->patchJson('/api/admin/boards/my-board', ['guests_can_post' => true, 'guests_can_vote' => false])
            ->assertOk()
            ->assertJsonPath('data.guests_can_post', true)
            ->assertJsonPath('data.guests_can_vote', false);

        // не bool → 422 на обоих полях
        $this->patchJson('/api/admin/boards/my-board', ['guests_can_post' => 'maybe'])
            ->assertJsonValidationErrors(['guests_can_post']);
        $this->patchJson('/api/admin/boards/my-board', ['guests_can_vote' => 'yes please'])
            ->assertJsonValidationErrors(['guests_can_vote']);
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

    public function test_slug_validation(): void
    {
        $this->actingAs($this->owner);

        // формат, длина, зарезервированные — те же сообщения, что и при создании
        $this->patchJson('/api/admin/boards/my-board', ['slug' => 'Bad Slug'])->assertJsonValidationErrors('slug');
        $this->patchJson('/api/admin/boards/my-board', ['slug' => 'a'])->assertJsonValidationErrors('slug');
        $this->patchJson('/api/admin/boards/my-board', ['slug' => str_repeat('a', 65)])->assertJsonValidationErrors('slug');
        $this->patchJson('/api/admin/boards/my-board', ['slug' => 'admin'])->assertJsonValidationErrors('slug');

        // свой текущий slug — не «занят», просто ничего не меняет (переименование по своему же полю)
        $this->patchJson('/api/admin/boards/my-board', ['slug' => 'my-board'])
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
