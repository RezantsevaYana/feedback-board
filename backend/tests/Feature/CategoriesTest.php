<?php

namespace Tests\Feature;

use App\Enums\ProjectRole;
use App\Models\Category;
use App\Models\Post;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestWith;
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
        $this->admin = User::factory()->create();
        $this->admin->projects()->attach($this->project, [
            'role' => ProjectRole::Admin->value,
        ]);
    }

    public function test_team_adds_renames_and_deletes_categories(): void
    {
        $this->actingAs($this->admin);

        $id = $this->postJson('/api/admin/projects/demo/categories', ['name' => '  Доставка ', 'color' => 'green'])
            ->assertCreated()->assertJsonPath('data.name', 'Доставка')->assertJsonPath('data.color', 'green')->json('data.id');
        $this->postJson('/api/admin/projects/demo/categories', ['name' => 'Баг', 'color' => 'red'])
            ->assertJsonValidationErrors(['name' => 'Такая категория уже есть.']);
        $this->postJson('/api/admin/projects/demo/categories', ['name' => 'Фуксия', 'color' => 'ultraviolet'])
            ->assertJsonValidationErrors(['color' => 'Такой цвет недоступен.']);

        $this->patchJson("/api/admin/categories/{$id}", ['name' => 'Доставка и оплата', 'color' => 'blue'])
            ->assertOk()->assertJsonPath('data.color', 'blue');
        $this->getJson('/api/public/projects/demo')->assertJsonPath('data.categories.*.name', ['Баг', 'Идея', 'Доставка и оплата']);

        $post = Post::factory()->for($this->project)->create(['category_id' => $id]);
        $this->deleteJson("/api/admin/categories/{$id}")->assertNoContent();
        $this->assertNull($post->refresh()->category_id); // the post stays, without a category
    }

    public function test_limits_and_permissions(): void
    {
        $this->actingAs($this->admin);
        foreach (range(3, Category::MAX_PER_PROJECT) as $i) {
            $this->postJson('/api/admin/projects/demo/categories', ['name' => "Категория {$i}", 'color' => 'blue'])->assertCreated();
        }
        $this->postJson('/api/admin/projects/demo/categories', ['name' => 'Лишняя', 'color' => 'blue'])->assertJsonValidationErrors('name');

        $foreign = Project::factory()->create();
        $this->postJson("/api/admin/projects/{$foreign->slug}/categories", ['name' => 'Чужая', 'color' => 'blue'])->assertForbidden();
        $this->patchJson('/api/admin/categories/'.$foreign->categories()->first()->id, ['name' => 'Взлом', 'color' => 'blue'])->assertForbidden();
        $this->deleteJson('/api/admin/categories/'.$foreign->categories()->first()->id)->assertForbidden();

        auth()->forgetGuards();
        $this->postJson('/api/admin/projects/demo/categories', ['name' => 'Гость', 'color' => 'blue'])->assertUnauthorized();
    }

    public function test_custom_hex_colour_is_normalized_persisted_and_can_be_cleared(): void
    {
        $this->actingAs($this->admin);

        $id = $this->postJson('/api/admin/projects/demo/categories', ['name' => 'Свой цвет', 'color' => '  A1B2C3  '])
            ->assertCreated()->assertJsonPath('data.color', '#a1b2c3')->json('data.id');
        $this->assertDatabaseHas('categories', ['id' => $id, 'color' => '#a1b2c3']);

        $this->patchJson("/api/admin/categories/{$id}", ['name' => 'Свой цвет', 'color' => '#F0A'])
            ->assertOk()->assertJsonPath('data.color', '#ff00aa');
        $this->assertDatabaseHas('categories', ['id' => $id, 'color' => '#ff00aa']);

        $this->getJson('/api/public/projects/demo')->assertOk()
            ->assertJsonFragment(['id' => $id, 'name' => 'Свой цвет', 'color' => '#ff00aa']);

        $this->patchJson("/api/admin/categories/{$id}", ['name' => 'Свой цвет', 'color' => null])
            ->assertOk()->assertJsonPath('data.color', null);
        $this->assertDatabaseHas('categories', ['id' => $id, 'color' => null]);
    }

    #[TestWith(['#12GG34'])]
    #[TestWith(['#12345678'])]
    #[TestWith(['red; background:url(example)'])]
    public function test_custom_colour_rejects_invalid_hex_and_css(string $color): void
    {
        $this->actingAs($this->admin);

        $this->postJson('/api/admin/projects/demo/categories', ['name' => 'Некорректный цвет', 'color' => $color])
            ->assertJsonValidationErrors(['color' => 'Такой цвет недоступен.']);
    }
}
