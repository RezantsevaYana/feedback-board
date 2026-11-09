<?php

namespace Tests\Feature;

use App\Enums\ProjectRole;
use App\Models\Post;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class StatusesTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->project = Project::factory()->create(['slug' => 'demo']);
        $this->admin = User::factory()->create();
        $this->admin->projects()->attach($this->project, ['role' => ProjectRole::Admin->value]);
    }

    public function test_admin_reorders_all_statuses_and_public_board_returns_that_order(): void
    {
        $this->actingAs($this->admin);
        $a = $this->postJson('/api/admin/projects/demo/statuses', ['name' => 'A'])->assertCreated()->json('data.id');
        $ids = $this->project->statuses()->pluck('id', 'slug');
        $order = [$ids['rejected'], $a, $ids['done'], $ids['new']];

        $response = $this->patchJson('/api/admin/projects/demo/statuses/reorder', ['ids' => $order])->assertOk();

        $this->assertSame($order, array_column($response->json('data'), 'id'));
        $this->assertSame(['Отклонено', 'A', 'Готово', 'Новое'], $this->project->statuses()->pluck('name')->all());
        $this->assertSame($order, array_column($this->getJson('/api/public/projects/demo')->assertOk()->json('data.statuses'), 'id'));
        $this->assertSame($ids['new'], $this->project->defaultStatus()->id);
    }

    public function test_reorder_rejects_incomplete_lists_without_changing_order(): void
    {
        $this->actingAs($this->admin);
        $before = $this->project->statuses()->pluck('id')->all();

        $this->patchJson('/api/admin/projects/demo/statuses/reorder', ['ids' => [$before[0]]])
            ->assertJsonValidationErrors(['ids' => 'Передайте полный список статусов в новом порядке.']);

        $this->assertSame($before, $this->project->statuses()->pluck('id')->all());
    }

    public function test_reorder_rejects_duplicate_ids_without_changing_order(): void
    {
        $this->actingAs($this->admin);
        $before = $this->project->statuses()->pluck('id')->all();

        $this->patchJson('/api/admin/projects/demo/statuses/reorder', ['ids' => [$before[0], $before[0], $before[2]]])
            ->assertJsonValidationErrors(['ids' => 'Один и тот же статус повторяется.']);

        $this->assertSame($before, $this->project->statuses()->pluck('id')->all());
    }

    public function test_new_status_is_appended_after_reordered_columns(): void
    {
        $this->actingAs($this->admin);
        $order = $this->project->statuses()->pluck('id')->reverse()->values()->all();
        $this->patchJson('/api/admin/projects/demo/statuses/reorder', ['ids' => $order])->assertOk();

        $added = $this->postJson('/api/admin/projects/demo/statuses', ['name' => 'Следующий'])->assertCreated()->json('data.id');

        $this->assertSame([...$order, $added], $this->project->statuses()->pluck('id')->all());
    }

    public function test_new_status_is_appended_after_legacy_positions(): void
    {
        $this->actingAs($this->admin);
        $this->project->statuses()->where('slug', 'done')->update(['position' => 9000]);
        $this->project->statuses()->where('slug', 'rejected')->update(['position' => 9100]);

        $added = $this->postJson('/api/admin/projects/demo/statuses', ['name' => 'Новый'])->assertCreated()->json('data.id');

        $this->assertSame($added, $this->project->statuses()->get()->last()->id);
    }

    public function test_reorder_requires_configure_permission(): void
    {
        $outsider = User::factory()->create();
        $this->actingAs($outsider);
        $this->patchJson('/api/admin/projects/demo/statuses/reorder', ['ids' => []])->assertForbidden();
    }

    public function test_reorder_rejects_statuses_from_another_project(): void
    {
        $this->actingAs($this->admin);
        $foreign = Project::factory()->create();
        $foreignCustom = $foreign->statuses()->create([
            'slug' => 'x', 'name' => 'X', 'position' => 300, 'is_system' => false,
        ]);

        $this->patchJson('/api/admin/projects/demo/statuses/reorder', ['ids' => [$foreignCustom->id]])
            ->assertJsonValidationErrors(['ids.0']);

        $this->assertSame(300, $foreignCustom->refresh()->position);
    }

    public function test_selected_initial_status_is_used_for_new_posts_and_queue_counts(): void
    {
        $this->actingAs($this->admin);
        $existing = Post::factory()->for($this->project)->create();
        $initial = $this->project->statuses()->where('slug', 'done')->firstOrFail();

        $this->patchJson('/api/admin/projects/demo/statuses/default', ['status_id' => $initial->id])
            ->assertOk()->assertJsonPath('data.default_status_id', $initial->id);

        $this->assertDatabaseHas('projects', ['id' => $this->project->id, 'default_status_id' => $initial->id]);
        $this->assertSame($existing->status_id, $existing->fresh()->status_id);
        $this->postJson('/api/public/projects/demo/posts', ['category_id' => $this->project->categories()->first()->id, 'title' => 'Идея после настройки', 'description' => 'Описание предложения'])
            ->assertCreated()->assertJsonPath('data.status.id', $initial->id);
        $this->getJson('/api/admin/boards')->assertOk()->assertJsonPath('data.0.queue_count', 1);
        $this->getJson('/api/public/projects/demo')->assertOk()->assertJsonPath('data.default_status_id', $initial->id);
        $this->getJson('/api/public/projects/demo/posts?new_first=1')->assertOk()->assertJsonPath('new_count', 1)
            ->assertJsonPath('data.0.status.id', $initial->id);
    }

    public function test_initial_status_rejects_a_status_from_another_board(): void
    {
        $this->actingAs($this->admin);
        $before = $this->project->default_status_id;
        $foreign = Project::factory()->create()->defaultStatus();

        $this->patchJson('/api/admin/projects/demo/statuses/default', ['status_id' => $foreign->id])
            ->assertJsonValidationErrors(['status_id' => 'Выберите статус этой доски.']);

        $this->assertSame($before, $this->project->fresh()->default_status_id);
    }

    public function test_member_without_configuration_permission_cannot_change_or_delete_statuses(): void
    {
        $reader = User::factory()->create();
        $reader->projects()->attach($this->project, ['role' => ProjectRole::Reader->value]);
        $this->actingAs($reader);
        $statuses = $this->project->statuses()->get();

        $this->patchJson('/api/admin/projects/demo/statuses/default', ['status_id' => $statuses[1]->id])->assertForbidden();
        $this->getJson('/api/admin/statuses/'.$statuses[0]->id.'/deletion')->assertForbidden();
        $this->deleteJson('/api/admin/statuses/'.$statuses[0]->id, ['replacement_status_id' => $statuses[1]->id])->assertForbidden();

        $this->assertSame(3, $this->project->statuses()->count());
    }

    public function test_deleting_initial_status_moves_all_posts_and_replaces_the_default(): void
    {
        $this->actingAs($this->admin);
        $source = $this->project->defaultStatus();
        $source->update(['is_system' => true]);
        $target = $this->project->statuses()->where('slug', 'done')->firstOrFail();
        $visible = Post::factory()->for($this->project)->create(['team_comment' => 'Ответ сохраняется']);
        $hidden = Post::factory()->for($this->project)->create(['is_hidden' => true]);

        $this->getJson("/api/admin/statuses/{$source->id}/deletion")->assertOk()->assertJsonPath('data.posts_count', 2);
        $this->deleteJson("/api/admin/statuses/{$source->id}", ['replacement_status_id' => $target->id])
            ->assertOk()->assertJsonPath('data.moved_posts_count', 2)->assertJsonPath('data.default_status_id', $target->id);

        $this->assertDatabaseMissing('statuses', ['id' => $source->id]);
        $this->assertSame($target->id, $this->project->fresh()->defaultStatus()->id);
        $this->assertSame($target->id, $visible->fresh()->status_id);
        $this->assertSame('Ответ сохраняется', $visible->fresh()->team_comment);
        $this->assertSame($target->id, $hidden->fresh()->status_id);
        $this->assertTrue($hidden->fresh()->is_hidden);
        $this->postJson('/api/public/projects/demo/posts', ['category_id' => $this->project->categories()->first()->id, 'title' => 'После удаления', 'description' => 'Новая идея'])
            ->assertCreated()->assertJsonPath('data.status.id', $target->id);
    }

    #[TestWith(['done'])]
    #[TestWith(['rejected'])]
    public function test_statuses_created_with_board_can_be_deleted_without_changing_initial_status(string $slug): void
    {
        $this->actingAs($this->admin);
        $source = $this->project->statuses()->where('slug', $slug)->firstOrFail();
        $source->update(['is_system' => true]);
        $target = $this->project->defaultStatus();
        $post = Post::factory()->for($this->project)->create(['status_id' => $source->id]);

        $this->deleteJson("/api/admin/statuses/{$source->id}", ['replacement_status_id' => $target->id])
            ->assertOk()->assertJsonPath('data.moved_posts_count', 1);

        $this->assertDatabaseMissing('statuses', ['id' => $source->id]);
        $this->assertSame($target->id, $post->fresh()->status_id);
        $this->assertSame($target->id, $this->project->fresh()->default_status_id);
    }

    public function test_deleting_last_status_is_rejected(): void
    {
        $this->actingAs($this->admin);
        $target = $this->project->defaultStatus();
        foreach ($this->project->statuses()->whereKeyNot($target->id)->get() as $status) {
            $this->deleteJson("/api/admin/statuses/{$status->id}", ['replacement_status_id' => $target->id])->assertOk();
        }

        $this->deleteJson("/api/admin/statuses/{$target->id}", ['replacement_status_id' => $target->id])
            ->assertJsonValidationErrors(['status' => 'Нельзя удалить последний статус.']);

        $this->assertSame(1, $this->project->statuses()->count());
        $this->assertSame($target->id, $this->project->fresh()->default_status_id);
    }

    public function test_deletion_requires_a_recipient_even_when_there_are_no_posts(): void
    {
        $this->actingAs($this->admin);
        $source = $this->project->defaultStatus();

        $this->deleteJson("/api/admin/statuses/{$source->id}")
            ->assertJsonValidationErrors(['replacement_status_id' => 'Выберите статус для переноса предложений.']);

        $this->assertSame(3, $this->project->statuses()->count());
    }

    public function test_deletion_rejects_self_and_foreign_recipients_without_moving_posts(): void
    {
        $this->actingAs($this->admin);
        $source = $this->project->defaultStatus();
        $post = Post::factory()->for($this->project)->create();
        $foreign = Project::factory()->create()->defaultStatus();

        foreach ([$source->id, $foreign->id] as $id) {
            $this->deleteJson("/api/admin/statuses/{$source->id}", ['replacement_status_id' => $id])
                ->assertJsonValidationErrors(['replacement_status_id' => 'Выберите другой статус этой доски.']);
        }

        $this->assertSame($source->id, $post->fresh()->status_id);
        $this->assertSame(3, $this->project->statuses()->count());
    }

    public function test_migration_backfills_existing_and_trashed_boards_without_tying_default_to_column_order(): void
    {
        $connection = DB::getDefaultConnection();
        config(['database.connections.status_migration' => ['driver' => 'sqlite', 'database' => ':memory:', 'foreign_key_constraints' => true]]);
        DB::setDefaultConnection('status_migration');
        try {
            Schema::create('projects', function (Blueprint $table) {
                $table->id();
                $table->timestamp('deleted_at')->nullable();
            });
            Schema::create('statuses', function (Blueprint $table) {
                $table->id();
                $table->foreignId('project_id')->constrained();
                $table->string('slug');
                $table->integer('position');
            });
            DB::table('projects')->insert([
                ['id' => 1, 'deleted_at' => null],
                ['id' => 2, 'deleted_at' => now()],
                ['id' => 3, 'deleted_at' => null],
            ]);
            DB::table('statuses')->insert([
                ['id' => 11, 'project_id' => 1, 'slug' => 'done', 'position' => 100],
                ['id' => 12, 'project_id' => 1, 'slug' => 'new', 'position' => 9000],
                ['id' => 21, 'project_id' => 2, 'slug' => 'done', 'position' => 400],
                ['id' => 22, 'project_id' => 2, 'slug' => 'rejected', 'position' => 100],
            ]);
            $migration = require database_path('migrations/2026_11_09_095844_add_default_status_id_to_projects.php');

            $migration->up();

            $this->assertDatabaseHas('projects', ['id' => 1, 'default_status_id' => 12]);
            $this->assertDatabaseHas('projects', ['id' => 2, 'default_status_id' => 22]);
            $this->assertDatabaseHas('projects', ['id' => 3, 'default_status_id' => null]);
            $this->assertSame(4, DB::table('statuses')->count());
        } finally {
            DB::setDefaultConnection($connection);
            DB::purge('status_migration');
        }
    }

    public function test_custom_hex_colour_is_normalized_persisted_and_can_be_cleared(): void
    {
        $this->actingAs($this->admin);

        $id = $this->postJson('/api/admin/projects/demo/statuses', ['name' => 'Свой цвет', 'color' => '  A1B2C3  '])
            ->assertCreated()->assertJsonPath('data.color', '#a1b2c3')->json('data.id');
        $this->assertDatabaseHas('statuses', ['id' => $id, 'color' => '#a1b2c3']);

        $this->patchJson("/api/admin/statuses/{$id}", ['name' => 'Свой цвет', 'color' => '#F0A'])
            ->assertOk()->assertJsonPath('data.color', '#ff00aa');
        $this->assertDatabaseHas('statuses', ['id' => $id, 'color' => '#ff00aa']);

        $this->getJson('/api/public/projects/demo')->assertOk()
            ->assertJsonFragment(['id' => $id, 'name' => 'Свой цвет', 'color' => '#ff00aa']);

        $this->patchJson("/api/admin/statuses/{$id}", ['name' => 'Свой цвет', 'color' => null])
            ->assertOk()->assertJsonPath('data.color', null);
        $this->assertDatabaseHas('statuses', ['id' => $id, 'color' => null]);
    }

    #[TestWith(['#12GG34'])]
    #[TestWith(['#12345678'])]
    #[TestWith(['red; background:url(example)'])]
    public function test_custom_colour_rejects_invalid_hex_and_css(string $color): void
    {
        $this->actingAs($this->admin);

        $this->postJson('/api/admin/projects/demo/statuses', ['name' => 'Некорректный цвет', 'color' => $color])
            ->assertJsonValidationErrors(['color' => 'Такой цвет недоступен.']);
    }
}
