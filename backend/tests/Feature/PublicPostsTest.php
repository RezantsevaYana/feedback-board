<?php

namespace Tests\Feature;

use App\Enums\PostPriority;
use App\Enums\PostStatus;
use App\Models\Post;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicPostsTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->project = Project::factory()->create(['slug' => 'demo', 'allowed_origins' => ['https://app.example.com', 'http://localhost:*']]);
        $this->project->createDefaultCategories();
    }

    private function validPayload(array $overrides = []): array
    {
        return [
            'category_id' => $this->project->categories()->first()->id,
            'title' => 'Не открывается календарь',
            'description' => 'Нажимаю на календарь — белый экран.',
            ...$overrides,
        ];
    }

    public function test_board_lists_all_but_hidden_posts_and_hides_team_fields(): void
    {
        $visible = Post::factory()->for($this->project)->create(['priority' => PostPriority::High, 'likes_count' => 5]);
        $rejected = Post::factory()->for($this->project)->status(PostStatus::Rejected)->create(['team_comment' => 'Уже есть']);
        Post::factory()->for($this->project)->hidden()->create();
        Post::factory()->create(); // another project

        $response = $this->getJson('/api/public/projects/demo/posts')->assertOk();

        $response->assertJsonCount(2, 'data')->assertJsonPath('data.*.id', [$visible->id, $rejected->id]);
        $this->assertArrayNotHasKey('priority', $response->json('data.0'));
        $this->assertArrayNotHasKey('is_hidden', $response->json('data.0'));
    }

    public function test_board_filters_and_sorts_by_likes_minus_dislikes(): void
    {
        [$bug, $idea] = $this->project->categories()->get()->all();
        $liked = Post::factory()->for($this->project)->create(['category_id' => $idea->id, 'likes_count' => 10, 'dislikes_count' => 8]);
        $loved = Post::factory()->for($this->project)->create(['category_id' => $idea->id, 'likes_count' => 5, 'dislikes_count' => 0]);
        Post::factory()->for($this->project)->create(['category_id' => $bug->id, 'status' => PostStatus::Done]);
        $loose = Post::factory()->for($this->project)->create(['category_id' => null]);

        $this->getJson("/api/public/projects/demo/posts?category={$idea->id}")
            ->assertJsonPath('data.*.id', [$loved->id, $liked->id])
            ->assertJsonPath('data.0.category.name', 'Идея');
        $this->getJson("/api/public/projects/demo/posts?category={$idea->id}&sort=new")
            ->assertJsonPath('data.*.id', [$loved->id, $liked->id]);
        $this->getJson('/api/public/projects/demo/posts?category=none')->assertJsonPath('data.*.id', [$loose->id]);
        $this->getJson('/api/public/projects/demo/posts?status=done')->assertJsonCount(1, 'data');
        $this->getJson('/api/public/projects/demo/posts?status=planned')->assertUnprocessable();
    }

    public function test_creates_post_with_screenshots(): void
    {
        $response = $this->post('/api/public/projects/demo/posts', $this->validPayload([
            'author_name' => '  Лена ',
            'files' => [UploadedFile::fake()->image('shot.png'), UploadedFile::fake()->image('two.jpg')],
        ]), ['Origin' => 'https://app.example.com', 'Accept' => 'application/json']);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'new')
            ->assertJsonPath('data.category.name', 'Баг')
            ->assertJsonPath('data.author_name', 'Лена')
            ->assertJsonCount(2, 'data.attachments')
            ->assertHeader('Access-Control-Allow-Origin', 'https://app.example.com');

        $post = Post::firstOrFail();
        $this->assertNotNull($post->status_changed_at);
        foreach ($post->attachments as $attachment) {
            Storage::disk('local')->assertExists($attachment->path);
            $this->assertStringNotContainsString('shot', $attachment->path); // random name on disk
        }
    }

    public function test_validation_messages_are_in_russian(): void
    {
        $this->postJson('/api/public/projects/demo/posts', ['title' => 'ab'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'category_id' => 'Выберите, к чему относится предложение.',
                'title' => 'Заголовок — хотя бы 3 символа.',
                'description' => 'Опишите подробнее.',
            ]);
    }

    public function test_rejects_non_images_by_content_and_too_many_files(): void
    {
        // a real file (not UploadedFile::fake), so the MIME type is detected from the content like in production
        $path = tempnam(sys_get_temp_dir(), 'fb');
        file_put_contents($path, '<?php echo "not an image";');
        $fake = new UploadedFile($path, 'shot.png', 'image/png', null, true);
        $this->post('/api/public/projects/demo/posts', $this->validPayload(['files' => [$fake]]), ['Accept' => 'application/json'])
            ->assertJsonValidationErrors(['files.0' => 'Скриншот должен быть картинкой PNG, JPG, WebP или GIF.']);

        $big = UploadedFile::fake()->image('big.png')->size(6000);
        $this->post('/api/public/projects/demo/posts', $this->validPayload(['files' => [$big]]), ['Accept' => 'application/json'])
            ->assertJsonValidationErrors(['files.0' => 'Каждый скриншот — не больше 5 МБ.']);

        $six = array_map(fn ($i) => UploadedFile::fake()->image("s{$i}.png"), range(1, 6));
        $this->post('/api/public/projects/demo/posts', $this->validPayload(['files' => $six]), ['Accept' => 'application/json'])
            ->assertJsonValidationErrors(['files' => 'Можно приложить не больше 5 скриншотов.']);

        $this->assertSame(0, Post::count());
    }

    public function test_widget_can_submit_only_from_allowed_origins(): void
    {
        $this->postJson('/api/public/projects/demo/posts', $this->validPayload(), ['Origin' => 'https://evil.example.org'])
            ->assertForbidden();
        $this->postJson('/api/public/projects/demo/posts', $this->validPayload(), ['Origin' => 'http://localhost:5174'])
            ->assertCreated();
        // the board itself is always allowed
        $this->postJson('/api/public/projects/demo/posts', $this->validPayload(), ['Origin' => config('app.frontend_url')])
            ->assertCreated();

        $this->options('/api/public/projects/demo/posts', [], ['Origin' => 'https://app.example.com'])
            ->assertNoContent()
            ->assertHeader('Access-Control-Allow-Origin', 'https://app.example.com');
        $this->options('/api/public/projects/demo/posts', [], ['Origin' => 'https://evil.example.org'])
            ->assertForbidden();
    }

    public function test_category_must_belong_to_the_project_and_is_optional_without_categories(): void
    {
        $foreign = Project::factory()->create();
        $foreign->createDefaultCategories();
        $this->postJson('/api/public/projects/demo/posts', $this->validPayload(['category_id' => $foreign->categories()->first()->id]))
            ->assertJsonValidationErrors(['category_id' => 'Такой категории больше нет — обновите страницу.']);

        $bare = Project::factory()->create(['slug' => 'bare']);
        $this->postJson('/api/public/projects/bare/posts', $this->validPayload(['category_id' => null]))
            ->assertCreated()->assertJsonPath('data.category', null);
    }

    public function test_widget_reads_project_categories_cross_origin(): void
    {
        $this->getJson('/api/public/projects/demo', ['Origin' => 'https://app.example.com'])
            ->assertOk()
            ->assertHeader('Access-Control-Allow-Origin', 'https://app.example.com')
            ->assertJsonPath('data.categories.*.name', ['Баг', 'Идея']);
        $this->getJson('/api/public/projects/demo', ['Origin' => 'https://evil.example.org'])->assertForbidden();
    }

    public function test_post_by_direct_link(): void
    {
        $rejected = Post::factory()->for($this->project)->status(PostStatus::Rejected)->create(['team_comment' => 'Уже есть']);
        $hidden = Post::factory()->for($this->project)->hidden()->create();
        $foreign = Post::factory()->create();

        $this->getJson("/api/public/projects/demo/posts/{$rejected->id}")
            ->assertOk()->assertJsonPath('data.team_comment', 'Уже есть');
        $this->getJson("/api/public/projects/demo/posts/{$hidden->id}")->assertNotFound();
        $this->getJson("/api/public/projects/demo/posts/{$foreign->id}")->assertNotFound();
    }

    public function test_attachments_of_hidden_posts_are_not_served(): void
    {
        $this->post('/api/public/projects/demo/posts', $this->validPayload(['files' => [UploadedFile::fake()->image('a.png')]]), ['Accept' => 'application/json']);
        $post = Post::firstOrFail();
        $attachment = $post->attachments()->firstOrFail();

        $this->get("/api/public/attachments/{$attachment->id}")->assertOk()->assertHeader('Content-Type', 'image/png');

        $post->update(['is_hidden' => true]);
        $this->getJson("/api/public/attachments/{$attachment->id}")->assertNotFound();
    }

    public function test_unknown_project_is_404(): void
    {
        $this->getJson('/api/public/projects/nope/posts')->assertNotFound();
        $this->getJson('/api/public/projects/demo')->assertOk()->assertJsonPath('data.slug', 'demo');
    }
}
