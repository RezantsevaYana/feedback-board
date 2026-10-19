<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Post;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoriesTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->project = Project::factory()->create(['slug' => 'demo']);
        $this->project->createDefaultCategories();
        $this->admin = User::factory()->create();
        $this->admin->projects()->attach($this->project);
    }

    public function test_team_adds_renames_and_deletes_categories(): void
    {
        $this->actingAs($this->admin);

        $id = $this->postJson('/api/admin/projects/demo/categories', ['name' => '  Доставка '])
            ->assertCreated()->assertJsonPath('data.name', 'Доставка')->json('data.id');
        $this->postJson('/api/admin/projects/demo/categories', ['name' => 'Баг'])
            ->assertJsonValidationErrors(['name' => 'Такая категория уже есть.']);

        $this->patchJson("/api/admin/categories/{$id}", ['name' => 'Доставка и оплата'])->assertOk();
        $this->getJson('/api/public/projects/demo')->assertJsonPath('data.categories.*.name', ['Баг', 'Идея', 'Доставка и оплата']);

        $post = Post::factory()->for($this->project)->create(['category_id' => $id]);
        $this->deleteJson("/api/admin/categories/{$id}")->assertNoContent();
        $this->assertNull($post->refresh()->category_id); // the post stays, without a category
    }

    public function test_limits_and_permissions(): void
    {
        $this->actingAs($this->admin);
        foreach (range(3, Category::MAX_PER_PROJECT) as $i) {
            $this->postJson('/api/admin/projects/demo/categories', ['name' => "Категория {$i}"])->assertCreated();
        }
        $this->postJson('/api/admin/projects/demo/categories', ['name' => 'Лишняя'])->assertJsonValidationErrors('name');

        $foreign = Project::factory()->create();
        $foreign->createDefaultCategories();
        $this->postJson("/api/admin/projects/{$foreign->slug}/categories", ['name' => 'Чужая'])->assertForbidden();
        $this->patchJson('/api/admin/categories/'.$foreign->categories()->first()->id, ['name' => 'Взлом'])->assertForbidden();
        $this->deleteJson('/api/admin/categories/'.$foreign->categories()->first()->id)->assertForbidden();

        auth()->forgetGuards();
        $this->postJson('/api/admin/projects/demo/categories', ['name' => 'Гость'])->assertUnauthorized();
    }
}
