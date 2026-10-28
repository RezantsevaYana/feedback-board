<?php

namespace Tests\Feature;

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Slice 2 of docs/ROLES_PLAN.md — GET participants, PATCH role, DELETE.
 */
class ParticipantsTest extends TestCase
{
    use RefreshDatabase;

    private Project $board;

    private User $owner;

    private User $admin;

    private User $manager;

    private User $voter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create(['email' => 'owner@acme.com']);
        $this->board = Project::factory()->create(['owner_id' => $this->owner->id]);

        $this->admin = User::factory()->create(['email' => 'anna@acme.com']);
        $this->manager = User::factory()->create(['email' => 'manager@acme.com']);
        $this->voter = User::factory()->create(['email' => 'voter@acme.com']);
        $this->board->members()->attach($this->admin, ['role' => ProjectRole::Admin->value]);
        $this->board->members()->attach($this->manager, ['role' => ProjectRole::Manager->value]);
        $this->board->members()->attach($this->voter, ['role' => ProjectRole::Voter->value]);
    }

    public function test_owner_sees_everyone_with_role(): void
    {
        $response = $this->actingAs($this->owner)
            ->getJson("/api/admin/boards/{$this->board->slug}/participants")
            ->assertOk();

        $members = collect($response->json('data.members'));
        $this->assertSame(4, $members->count());
        $this->assertEqualsCanonicalizing(
            ['owner', 'admin', 'manager', 'voter'],
            $members->pluck('role')->all(),
        );
    }

    public function test_manager_cannot_configure_board(): void
    {
        $this->actingAs($this->manager)
            ->getJson("/api/admin/boards/{$this->board->slug}/participants")
            ->assertForbidden();
    }

    public function test_admin_can_change_role(): void
    {
        $this->actingAs($this->admin)
            ->patchJson("/api/admin/boards/{$this->board->slug}/participants/{$this->voter->id}", [
                'role' => 'reader',
            ])
            ->assertOk()
            ->assertJsonPath('data.role', 'reader');
    }

    public function test_owner_row_is_untouchable(): void
    {
        $this->actingAs($this->admin)
            ->patchJson("/api/admin/boards/{$this->board->slug}/participants/{$this->owner->id}", [
                'role' => 'admin',
            ])
            ->assertStatus(422);

        $this->actingAs($this->admin)
            ->deleteJson("/api/admin/boards/{$this->board->slug}/participants/{$this->owner->id}")
            ->assertStatus(422);
    }

    public function test_cannot_remove_self(): void
    {
        $this->actingAs($this->admin)
            ->deleteJson("/api/admin/boards/{$this->board->slug}/participants/{$this->admin->id}")
            ->assertStatus(422);
    }

    public function test_destroy_removes_from_pivot(): void
    {
        $this->actingAs($this->admin)
            ->deleteJson("/api/admin/boards/{$this->board->slug}/participants/{$this->voter->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('project_user', [
            'project_id' => $this->board->id,
            'user_id' => $this->voter->id,
        ]);
    }

    public function test_patch_rejects_unknown_role(): void
    {
        $this->actingAs($this->admin)
            ->patchJson("/api/admin/boards/{$this->board->slug}/participants/{$this->voter->id}", [
                'role' => 'godmode',
            ])
            ->assertJsonValidationErrors('role');
    }

    public function test_patch_rejects_assigning_owner(): void
    {
        $this->actingAs($this->admin)
            ->patchJson("/api/admin/boards/{$this->board->slug}/participants/{$this->voter->id}", [
                'role' => 'owner',
            ])
            ->assertJsonValidationErrors('role');
    }
}
