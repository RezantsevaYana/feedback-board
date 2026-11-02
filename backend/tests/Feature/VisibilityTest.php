<?php

namespace Tests\Feature;

use App\Enums\BoardVisibility;
use App\Enums\ProjectRole;
use App\Models\Post;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Slice 1 of stage 2 (docs/ROLES_PLAN.md): projects.visibility — closed boards.
 */
class VisibilityTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Project $board;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create(['email' => 'owner@acme.com']);
        $this->board = Project::factory()->create([
            'slug' => 'closed',
            'owner_id' => $this->owner->id,
            'visibility' => BoardVisibility::Private,
        ]);
    }

    public function test_default_visibility_is_public(): void
    {
        $fresh = Project::factory()->create(['slug' => 'fresh', 'owner_id' => $this->owner->id]);
        // factory doesn't set visibility, the DB default fills it in; re-read to pick up the default
        $this->assertSame(BoardVisibility::Public, $fresh->fresh()->visibility);
    }

    public function test_guest_on_private_board_gets_login_required_reason(): void
    {
        $this->getJson('/api/public/projects/closed')
            ->assertStatus(403)
            ->assertJsonPath('reason', 'login_required');
    }

    public function test_signed_in_outsider_gets_no_access_reason(): void
    {
        $outsider = User::factory()->create();
        $this->actingAs($outsider)->getJson('/api/public/projects/closed')
            ->assertStatus(403)
            ->assertJsonPath('reason', 'no_access');
    }

    public function test_member_sees_private_board(): void
    {
        $voter = User::factory()->create();
        $this->board->members()->attach($voter, ['role' => ProjectRole::Voter->value]);

        $this->actingAs($voter)->getJson('/api/public/projects/closed')
            ->assertOk()
            ->assertJsonPath('data.visibility', 'private');
    }

    public function test_public_board_lets_everyone_through(): void
    {
        $open = Project::factory()->create(['slug' => 'open', 'owner_id' => $this->owner->id]);
        $this->getJson('/api/public/projects/open')->assertOk()
            ->assertJsonPath('data.visibility', 'public');
    }

    public function test_private_posts_endpoint_also_closed_to_outsiders(): void
    {
        Post::factory()->for($this->board)->create();
        $this->getJson('/api/public/projects/closed/posts')
            ->assertStatus(403)
            ->assertJsonPath('reason', 'login_required');
    }

    public function test_admin_can_flip_public_to_private(): void
    {
        $open = Project::factory()->create(['slug' => 'to-close', 'owner_id' => $this->owner->id]);
        $this->actingAs($this->owner)
            ->patchJson('/api/admin/boards/to-close', ['visibility' => 'private'])
            ->assertOk()
            ->assertJsonPath('data.visibility', 'private');
    }

    public function test_private_board_opens_back_with_its_votes(): void
    {
        $member = User::factory()->create();
        $this->board->members()->attach($member->id, ['role' => ProjectRole::Voter->value]);
        $post = Post::factory()->for($this->board)->create();
        $this->actingAs($member)->postJson("/api/public/posts/{$post->id}/vote", ['value' => 'like'])->assertOk();
        // the sanctum guard keeps the last user between requests inside one test
        $this->app['auth']->forgetGuards();

        $this->actingAs($this->owner)
            ->patchJson('/api/admin/boards/closed', ['visibility' => 'public'])
            ->assertOk()
            ->assertJsonPath('data.visibility', 'public')
            ->assertJsonPath('data.posts_count', 1);
        $this->app['auth']->forgetGuards();

        // a guest sees the board and the members' votes as they were, and votes by browser on top
        $this->getJson('/api/public/projects/closed/posts')->assertOk()->assertJsonPath('data.0.likes_count', 1);
        $this->postJson("/api/public/posts/{$post->id}/vote", ['value' => 'like'], ['X-Voter-Token' => (string) \Illuminate\Support\Str::uuid()])
            ->assertOk()
            ->assertJsonPath('data.likes_count', 2);

        // the member keeps the role and the vote by account
        $this->assertSame(ProjectRole::Voter, $member->fresh()->roleOn($this->board->fresh()));
        $this->actingAs($member)->getJson('/api/public/projects/closed/posts')->assertJsonPath('data.0.my_vote', 'like');
    }

    public function test_unknown_visibility_is_rejected(): void
    {
        $this->actingAs($this->owner)
            ->patchJson('/api/admin/boards/closed', ['visibility' => 'secret'])
            ->assertJsonValidationErrors('visibility');
    }

    public function test_non_admin_cannot_change_visibility(): void
    {
        $manager = User::factory()->create();
        $this->board->members()->attach($manager, ['role' => ProjectRole::Manager->value]);

        $this->actingAs($manager)
            ->patchJson('/api/admin/boards/closed', ['visibility' => 'private'])
            ->assertForbidden();
    }

    public function test_settings_response_includes_visibility(): void
    {
        $this->actingAs($this->owner)->getJson('/api/admin/boards/closed')
            ->assertOk()
            ->assertJsonPath('data.visibility', 'private');
    }
}
