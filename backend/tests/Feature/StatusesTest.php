<?php

namespace Tests\Feature;

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_admin_reorders_custom_statuses_between_system_ones(): void
    {
        $this->actingAs($this->admin);
        $a = $this->postJson('/api/admin/projects/demo/statuses', ['name' => 'A'])->assertCreated()->json('data.id');
        $b = $this->postJson('/api/admin/projects/demo/statuses', ['name' => 'B'])->assertCreated()->json('data.id');
        $c = $this->postJson('/api/admin/projects/demo/statuses', ['name' => 'C'])->assertCreated()->json('data.id');

        // A, B, C → C, A, B
        $this->patchJson('/api/admin/projects/demo/statuses/reorder', ['ids' => [$c, $a, $b]])->assertOk();

        $this->assertSame(
            ['Новое', 'C', 'A', 'B', 'Готово', 'Отклонено'],
            $this->project->statuses()->orderBy('position')->pluck('name')->all()
        );
    }

    public function test_reorder_rejects_system_ids_and_incomplete_lists(): void
    {
        $this->actingAs($this->admin);
        $a = $this->postJson('/api/admin/projects/demo/statuses', ['name' => 'A'])->assertCreated()->json('data.id');
        $systemNew = $this->project->statuses()->where('slug', 'new')->value('id');

        // системный id в payload — отказ
        $this->patchJson('/api/admin/projects/demo/statuses/reorder', ['ids' => [$a, $systemNew]])
            ->assertJsonValidationErrors(['ids.1']);

        // неполный список — отказ (у проекта один кастомный A, прислали пустой массив)
        $this->patchJson('/api/admin/projects/demo/statuses/reorder', ['ids' => []])
            ->assertJsonValidationErrors(['ids' => 'Передайте полный список кастомных столбцов в новом порядке.']);
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
}
