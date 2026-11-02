<?php

namespace Tests\Feature;

use App\Enums\ProjectRole;
use App\Models\Post;
use App\Models\Project;
use App\Models\User;
use App\Models\Vote;
use App\Models\Voter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * «Кто голосовал» for the triage team (Owner/Admin/Manager).
 *
 * Mix of signed-in voters and anonymous widget cookies in one list so the client can split by kind.
 */
class VoterListTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;

    private Post $post;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create(['name' => 'Главный']);
        $this->project = Project::factory()->create(['owner_id' => $this->owner->id]);
        $this->project->members()->syncWithoutDetaching([$this->owner->id => ['role' => ProjectRole::Owner->value]]);
        $this->post = Post::factory()->for($this->project)->create();
    }

    private function url(): string
    {
        return "/api/admin/posts/{$this->post->id}/voters";
    }

    public function test_lists_signed_in_and_anonymous_voters_split_by_kind(): void
    {
        $signedIn = User::factory()->create(['name' => 'Иван Петров']);
        $this->project->members()->syncWithoutDetaching([$signedIn->id => ['role' => ProjectRole::Voter->value]]);
        $this->post->votes()->create(['user_id' => $signedIn->id, 'value' => Vote::LIKE, 'ip_hash' => str_repeat('a', 64)]);

        $tokenLena = (string) Str::uuid();
        Voter::create(['token' => $tokenLena, 'display_name' => 'Лена']);
        $this->post->votes()->create(['voter_token' => $tokenLena, 'value' => Vote::DISLIKE, 'ip_hash' => str_repeat('b', 64)]);

        // nameless cookie voter — must surface as «Аноним»
        $this->post->votes()->create(['voter_token' => (string) Str::uuid(), 'value' => Vote::LIKE, 'ip_hash' => str_repeat('c', 64)]);

        $response = $this->actingAs($this->owner)->getJson($this->url())
            ->assertOk()
            ->assertJsonCount(3, 'data');

        $byName = collect($response->json('data'))->keyBy('name');
        $this->assertSame('like', $byName['Иван Петров']['kind']);
        $this->assertTrue($byName['Иван Петров']['account']);
        $this->assertSame('dislike', $byName['Лена']['kind']);
        $this->assertFalse($byName['Лена']['account']);
        $this->assertSame('like', $byName['Аноним']['kind']);
    }

    public function test_the_team_list_carries_the_latest_three_voters_for_the_faces(): void
    {
        foreach (['Анна', 'Борис', 'Вера', 'Глеб'] as $i => $name) {
            $user = User::factory()->create(['name' => $name]);
            $this->post->votes()->create(['user_id' => $user->id, 'value' => Vote::LIKE, 'ip_hash' => str_repeat('a', 64), 'created_at' => now()->addMinutes($i)]);
        }
        $other = Post::factory()->for($this->project)->create();

        $posts = collect($this->actingAs($this->owner)->getJson("/api/admin/projects/{$this->project->slug}/posts")
            ->assertOk()->json('data'))->keyBy('id');

        // newest first, three at most; the card says «+1» from likes_count
        $this->assertSame(['Глеб', 'Вера', 'Борис'], array_column($posts[$this->post->id]['voters_preview'], 'name'));
        $this->assertTrue($posts[$this->post->id]['voters_preview'][0]['account']);
        $this->assertSame([], $posts[$other->id]['voters_preview']);
    }

    public function test_endpoint_is_denied_for_voters_and_readers(): void
    {
        $voter = User::factory()->create();
        $this->project->members()->syncWithoutDetaching([$voter->id => ['role' => ProjectRole::Voter->value]]);
        $this->actingAs($voter)->getJson($this->url())->assertForbidden();

        $reader = User::factory()->create();
        $this->project->members()->syncWithoutDetaching([$reader->id => ['role' => ProjectRole::Reader->value]]);
        $this->actingAs($reader)->getJson($this->url())->assertForbidden();
    }

    public function test_manager_can_see_the_list(): void
    {
        $manager = User::factory()->create();
        $this->project->members()->syncWithoutDetaching([$manager->id => ['role' => ProjectRole::Manager->value]]);

        $this->actingAs($manager)->getJson($this->url())->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_guest_gets_unauthenticated(): void
    {
        $this->getJson($this->url())->assertUnauthorized();
    }
}
