<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Post;
use App\Models\Project;
use App\Models\ProjectSlugHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Редактируемый адрес доски (test.md п. 24): старый slug уходит в project_slug_history,
 * публичные ссылки и уже встроенные виджеты по нему продолжают работать — резолвер
 * подменяет старый slug на актуальный проект, а SPA получает подсказку `redirect_slug`
 * и делает router.replace.
 */
class SlugHistoryTest extends TestCase
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
        ]);
    }

    public function test_owner_renames_slug_and_old_one_lives_in_history(): void
    {
        $this->actingAs($this->owner)
            ->patchJson('/api/admin/boards/my-board', ['slug' => 'new-board'])
            ->assertOk()
            ->assertJsonPath('data.slug', 'new-board');

        $this->assertSame('new-board', $this->board->refresh()->slug);
        $this->assertDatabaseHas('project_slug_history', [
            'project_id' => $this->board->id,
            'old_slug' => 'my-board',
        ]);

        // новый адрес работает в кабинете
        $this->getJson('/api/admin/boards/new-board')->assertOk()->assertJsonPath('data.slug', 'new-board');
    }

    public function test_public_endpoint_resolves_old_slug_and_returns_redirect(): void
    {
        $this->actingAs($this->owner)->patchJson('/api/admin/boards/my-board', ['slug' => 'new-board'])->assertOk();

        // старый адрес — получаем проект под новым slug и подсказку для router.replace
        $this->getJson('/api/public/projects/my-board')
            ->assertOk()
            ->assertJsonPath('data.slug', 'new-board')
            ->assertJsonPath('data.redirect_slug', 'new-board');

        // новый адрес — redirect_slug не ставим (не было подмены)
        $this->getJson('/api/public/projects/new-board')
            ->assertOk()
            ->assertJsonPath('data.slug', 'new-board')
            ->assertJsonPath('data.redirect_slug', null);
    }

    public function test_widget_vote_and_posts_keep_working_by_old_slug(): void
    {
        $this->actingAs($this->owner)->patchJson('/api/admin/boards/my-board', ['slug' => 'new-board'])->assertOk();

        // виджет на чужом сайте всё ещё помнит старый data-project — публичный список постов отдаётся
        $this->getJson('/api/public/projects/my-board/posts')->assertOk();

        // отправка нового предложения по старому slug — создаётся на актуальном проекте
        $category = Category::factory()->for($this->board)->create();
        $before = $this->board->posts()->count();
        $this->postJson('/api/public/projects/my-board/posts', [
            'title' => 'Идея',
            'description' => 'Описание идеи длинное.',
            'category_id' => $category->id,
        ])->assertCreated();
        $this->assertSame($before + 1, $this->board->posts()->count());
    }

    public function test_history_slug_cannot_be_re_taken(): void
    {
        $this->actingAs($this->owner)->patchJson('/api/admin/boards/my-board', ['slug' => 'new-board'])->assertOk();

        // владелец пытается вернуть старый адрес — запрещено, ссылки по нему уже разошлись
        $this->patchJson('/api/admin/boards/new-board', ['slug' => 'my-board'])
            ->assertJsonValidationErrors(['slug' => 'Этот адрес уже занят. Попробуйте другой.']);

        // другой пользователь тоже не может его занять — создание доски с таким slug падает
        $other = User::factory()->create();
        $this->actingAs($other)
            ->postJson('/api/admin/boards', ['name' => 'Другая', 'slug' => 'my-board'])
            ->assertUnprocessable();
    }

    public function test_active_board_slug_cannot_be_taken_either(): void
    {
        Project::factory()->create(['slug' => 'other', 'owner_id' => $this->owner->id]);

        $this->actingAs($this->owner)
            ->patchJson('/api/admin/boards/my-board', ['slug' => 'other'])
            ->assertJsonValidationErrors(['slug' => 'Этот адрес уже занят. Попробуйте другой.']);
    }

    public function test_check_slug_knows_about_history_and_own_board(): void
    {
        $this->actingAs($this->owner)->patchJson('/api/admin/boards/my-board', ['slug' => 'new-board'])->assertOk();

        // свой текущий slug (`except`) — не считается занятым: поле переименования заранее заполнено им
        $this->postJson('/api/admin/boards/check-slug', ['slug' => 'new-board', 'except' => 'new-board'])
            ->assertOk()
            ->assertJsonPath('data.available', true);

        // освобождённый адрес — всё равно «занят», старые ссылки важнее
        $this->postJson('/api/admin/boards/check-slug', ['slug' => 'my-board'])
            ->assertOk()
            ->assertJsonPath('data.available', false)
            ->assertJsonPath('data.reason', 'taken');
    }

    public function test_renaming_back_to_current_slug_is_a_noop(): void
    {
        $this->actingAs($this->owner)
            ->patchJson('/api/admin/boards/my-board', ['slug' => 'my-board'])
            ->assertOk()
            ->assertJsonPath('data.slug', 'my-board');

        // в историю ничего не ушло
        $this->assertSame(0, ProjectSlugHistory::where('project_id', $this->board->id)->count());
    }

    public function test_multiple_renames_accumulate_history_and_all_point_home(): void
    {
        $this->actingAs($this->owner);
        $this->patchJson('/api/admin/boards/my-board', ['slug' => 'board-v2'])->assertOk();
        $this->patchJson('/api/admin/boards/board-v2', ['slug' => 'board-v3'])->assertOk();

        $this->assertDatabaseHas('project_slug_history', ['project_id' => $this->board->id, 'old_slug' => 'my-board']);
        $this->assertDatabaseHas('project_slug_history', ['project_id' => $this->board->id, 'old_slug' => 'board-v2']);

        // оба старых адреса ведут на актуальный проект
        $this->getJson('/api/public/projects/my-board')->assertJsonPath('data.slug', 'board-v3')->assertJsonPath('data.redirect_slug', 'board-v3');
        $this->getJson('/api/public/projects/board-v2')->assertJsonPath('data.slug', 'board-v3')->assertJsonPath('data.redirect_slug', 'board-v3');
    }

    public function test_post_on_renamed_board_is_reachable_by_both_slugs(): void
    {
        $post = Post::factory()->for($this->board)->create();
        $this->actingAs($this->owner)->patchJson('/api/admin/boards/my-board', ['slug' => 'renamed'])->assertOk();

        // пост виден на публичном API под обоими slug (резолвер подменит старый)
        $this->getJson("/api/public/projects/my-board/posts/{$post->id}")->assertOk();
        $this->getJson("/api/public/projects/renamed/posts/{$post->id}")->assertOk();
    }
}
