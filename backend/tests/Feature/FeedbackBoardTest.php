<?php

namespace Tests\Feature;

use App\Enums\ProjectRole;
use App\Models\Post;
use App\Models\Project;
use App\Models\User;
use App\Services\ProjectService;
use Database\Seeders\FeedbackBoardSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeedbackBoardTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_database_has_a_public_feedback_board(): void
    {
        $this->getJson('/api/public/projects/feedback-board')
            ->assertOk()
            ->assertJsonPath('data.published', true)
            ->assertJsonPath('data.guests_can_post', true)
            ->assertJsonPath('data.guests_can_vote', true)
            ->assertJsonCount(2, 'data.categories')
            ->assertJsonCount(3, 'data.statuses');
    }

    public function test_new_database_receives_the_current_snapshot_only_on_the_service_board(): void
    {
        $board = Project::where('slug', 'feedback-board')->firstOrFail();
        $snapshot = json_decode(file_get_contents(database_path('snapshots/feedback-board.json')), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(count($snapshot['posts']), $board->posts()->count());
        $this->assertSame(24, $board->posts()->count());
        $this->assertSame(1, $board->posts()->where('title', 'Теги предложений: настройка на доске и фильтрация по частям продукта')->count());
        $this->assertSame(0, $board->posts()->where('title', 'Надёжная идентификация автора в личном кабинете')->count());
        foreach ($snapshot['posts'] as $entry) {
            $post = $board->posts()->where('title', $entry['title'])->firstOrFail();
            $this->assertSame($entry['description'], $post->description);
            $this->assertSame($entry['kanban_position'], $post->kanban_position);
            $this->assertSame($entry['category'], $post->category?->name);
            $this->assertSame($entry['status'], $post->status?->slug);
        }
        $other = Project::factory()->create();
        $this->assertSame(0, $other->posts()->count());
    }

    public function test_snapshot_does_not_overwrite_an_already_populated_board(): void
    {
        $board = Project::where('slug', 'feedback-board')->firstOrFail();
        $post = $board->posts()->firstOrFail();
        $post->update(['title' => 'Моя редакция']);
        $removed = $board->posts()->whereKeyNot($post->id)->firstOrFail();
        $removed->delete();
        $count = $board->posts()->count();

        $migration = require database_path('migrations/2026_11_09_191802_import_feedback_board_snapshot.php');
        $migration->up();

        $this->assertSame($count, $board->posts()->count());
        $this->assertSame('Моя редакция', $post->fresh()->title);
        $this->assertModelMissing($removed);
    }

    public function test_reseeding_preserves_backlog_and_board_settings(): void
    {
        $board = Project::where('slug', 'feedback-board')->firstOrFail();
        $board->update(['name' => 'Наш бэклог']);
        $post = Post::factory()->for($board)->create();

        $this->seed(FeedbackBoardSeeder::class);

        $this->assertSame(1, Project::where('slug', 'feedback-board')->count());
        $this->assertSame('Наш бэклог', $board->fresh()->name);
        $this->assertModelExists($post);
        $this->assertSame(3, $board->statuses()->count());
        $this->assertSame(2, $board->categories()->count());
    }

    public function test_granting_another_service_admin_does_not_replace_the_original_admin_as_owner(): void
    {
        $board = Project::where('slug', 'feedback-board')->firstOrFail();
        $demo = User::factory()->create(['email' => 'admin@example.com']);
        app(ProjectService::class)->transferOwnership($board, $demo);
        $owner = User::factory()->create();

        $this->artisan('superadmin:grant', ['email' => $owner->email])->assertSuccessful();

        $this->assertSame($demo->id, $board->fresh()->owner_id);
        $this->assertSame(ProjectRole::Owner, $demo->roleOn($board));
    }

    public function test_initial_owner_is_the_first_service_admin_even_when_another_user_is_logged_in(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin@example.com',
            'permissions' => ['platform.boards' => true, 'platform.systems.roles' => true],
        ]);
        $personal = User::factory()->create([
            'permissions' => ['platform.boards' => true, 'platform.systems.roles' => true],
        ]);
        $this->actingAs($personal);

        $this->seed(FeedbackBoardSeeder::class);

        $board = Project::where('slug', 'feedback-board')->firstOrFail();
        $this->assertSame($admin->id, $board->owner_id);
        $this->assertSame(ProjectRole::Owner, $admin->roleOn($board));
    }

    public function test_reseeding_does_not_replace_an_existing_personal_owner(): void
    {
        $board = Project::where('slug', 'feedback-board')->firstOrFail();
        $owner = User::factory()->create();
        app(ProjectService::class)->transferOwnership($board, $owner);
        User::factory()->create(['permissions' => ['platform.boards' => true, 'platform.systems.roles' => true]]);

        $this->seed(FeedbackBoardSeeder::class);

        $this->assertSame($owner->id, $board->fresh()->owner_id);
        $this->assertSame(ProjectRole::Owner, $owner->roleOn($board));
    }
}
